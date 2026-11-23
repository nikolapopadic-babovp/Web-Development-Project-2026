<?php
/** Find OpenStreetMap sports venues and basketball pitches using MapLark's v3 OSM Features API.
 * No SQL here: this is a separate upstream integration service.
 */
final class MapLarkService
{
    private const ENDPOINT = 'https://api.maplark.com/v3/osm_features';
    private ?string $providedKey;
    /** @var callable|null Injectable transport, for isolated tests. */
    private $transport;

    public function __construct(?string $apiKey = null, ?callable $transport = null)
    {
        $this->providedKey = $apiKey;
        $this->transport = $transport;
    }

    public function findCourts(float $latitude, float $longitude, int $radius = 3000, int $limit = 20): array
    {
        return $this->discover($latitude, $longitude, $radius, $limit, false);
    }

    /** Sports halls, sports centres and arenas (OSM mapping may also include stadiums). */
    public function findSportsHalls(float $latitude, float $longitude, int $radius = 5000, int $limit = 20): array
    {
        return $this->discover($latitude, $longitude, $radius, $limit, true);
    }

    private function discover(float $latitude, float $longitude, int $radius, int $limit, bool $halls): array
    {
        $latitude = Validation::number($latitude, 'lat', -90.0, 90.0);
        $longitude = Validation::number($longitude, 'lon', -180.0, 180.0);
        $radius = Validation::integer($radius, 'radius', 200, 10000);
        $limit = Validation::integer($limit, 'limit', 1, 50);
        $key = $this->key();
        if ($key === '') {
            ExternalApiLog::failure('configuration missing (MapLark key not set)');
            throw new ServiceException('Court discovery is not configured yet. Please contact the administrator.', 503);
        }
        // Tag filters are fixed by our backend (not user-controlled).
        // MapLark v3 uses | for OR. Some OSM 'stadium' records are outdoors;
        // preserve the actual OSM category rather than promising they are indoor.
        $tags = $halls
            ? 'leisure=sports_centre|leisure=sports_hall|building=sports_hall|building=arena|building=stadium|leisure=stadium'
            : 'leisure=pitch,sport=basketball';
        $params = http_build_query([
            'location' => sprintf('%.6F,%.6F', $latitude, $longitude),
            'radius' => $radius,
            'tags' => $tags,
            'limit' => $limit,
        ], '', '&', PHP_QUERY_RFC3986);
        ExternalApiLog::info('GET /v3/osm_features requested');
        try {
            [$httpCode, $body] = $this->transport
                ? ($this->transport)(self::ENDPOINT . '?' . $params, $key)
                : $this->get(self::ENDPOINT . '?' . $params, $key);
        } catch (Throwable $error) {
            ExternalApiLog::failure('transport failed (' . get_class($error) . ', code=' . (int) $error->getCode() . ')');
            throw new ServiceException('Court discovery is temporarily unavailable. Please try again later.', 504);
        }
        ExternalApiLog::info('GET /v3/osm_features upstream_status=' . (int) $httpCode);
        if ($httpCode !== 200) {
            ExternalApiLog::failure('upstream HTTP ' . (int) $httpCode);
            if ($httpCode === 401 || $httpCode === 403) {
                throw new ServiceException('MapLark access denied. Check your API key and plan.', 502);
            }
            if ($httpCode === 400) {
                throw new ServiceException('MapLark rejected the search parameters (HTTP 400).', 502);
            }
            if ($httpCode === 429) {
                throw new ServiceException('MapLark rate limit reached. Try again later.', 503);
            }
            throw new ServiceException('Court discovery is temporarily unavailable. Please try again later.', 502);
        }
        if (!is_string($body) || strlen($body) > 4_000_000) {
            ExternalApiLog::failure('upstream response invalid/oversized');
            throw new ServiceException('Court discovery returned an invalid response.', 502);
        }
        try {
            $geojson = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            ExternalApiLog::failure('upstream response was not valid JSON');
            throw new ServiceException('Court discovery returned an invalid response.', 502);
        }
        if (!is_array($geojson) || ($geojson['type'] ?? null) !== 'FeatureCollection' || !isset($geojson['features']) || !is_array($geojson['features'])) {
            ExternalApiLog::failure('upstream response schema unexpected');
            throw new ServiceException('Court discovery returned an invalid response.', 502);
        }
        $items = [];
        foreach ($geojson['features'] as $feature) {
            if (!is_array($feature)) continue;
            $coordinates = self::center($feature['geometry'] ?? null);
            if ($coordinates === null) continue;
            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $tags = is_array($properties['tags'] ?? null) ? $properties['tags'] : $properties;
            $name = self::label($tags['name'] ?? null) ?: ($halls ? 'Unnamed sports facility' : 'Unnamed basketball court');
            $identifier = self::osmLink($feature, $properties);
            $items[] = [
                'name' => $name,
                'latitude' => $coordinates[1],
                'longitude' => $coordinates[0],
                'surface' => self::label($tags['surface'] ?? null),
                'category' => self::label($tags['leisure'] ?? $tags['building'] ?? null),
                'sport' => self::label($tags['sport'] ?? null),
                'access' => self::label($tags['access'] ?? null),
                'osm_url' => $identifier,
                'map_url' => sprintf('https://www.openstreetmap.org/?mlat=%.6F&mlon=%.6F#map=17/%.6F/%.6F',
                    $coordinates[1], $coordinates[0], $coordinates[1], $coordinates[0]),
                'source' => 'OpenStreetMap via MapLark',
            ];
            if (count($items) >= $limit) break;
        }
        return [
            $halls ? 'halls' : 'courts' => $items,
            'count' => count($items),
            'attribution' => '© OpenStreetMap contributors',
            'provider' => 'MapLark',
        ];
    }

    private function key(): string
    {
        if ($this->providedKey !== null) return trim($this->providedKey);
        $file = __DIR__ . '/../config/config.php';
        if (!is_file($file)) return '';
        $config = require $file;
        $key = is_array($config) ? ($config['maplark_api_key'] ?? '') : '';
        if (!is_string($key)) return '';
        $key = trim($key);
        return $key === 'maplark-api-key' ? '' : $key;
    }

    /** @return array{0:int,1:string} */
    private function get(string $url, string $key): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) throw new RuntimeException('cURL initialization failed');
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Accept: application/geo+json'],
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'COURTSIDE/1.0 (university project)',
            ]);
            $result = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errorCode = curl_errno($curl);
            curl_close($curl);
            if ($result === false) throw new RuntimeException('MapLark network connection failed', $errorCode);
            return [$status, (string) $result];
        }
        // Portable PHP fallback for installations without ext-curl; TLS verification remains on.
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$key}\r\nAccept: application/geo+json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) throw new RuntimeException('MapLark network connection failed');
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $m)) { $status = (int) $m[1]; break; }
        }
        return [$status, (string) $body];
    }

    private static function center(mixed $geometry): ?array
    {
        if (!is_array($geometry) || !is_string($geometry['type'] ?? null)) return null;
        $type = $geometry['type'];
        $coordinates = $geometry['coordinates'] ?? null;
        if ($type === 'Point') return self::validPair($coordinates);
        if (!in_array($type, ['MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'], true)) return null;
        $pairs = [];
        self::collectPairs($coordinates, $pairs);
        if (!$pairs) return null;
        $lon = array_sum(array_column($pairs, 0)) / count($pairs);
        $lat = array_sum(array_column($pairs, 1)) / count($pairs);
        return [round($lon, 6), round($lat, 6)]; // approximate center, not a verified entrance
    }

    private static function collectPairs(mixed $item, array &$pairs): void
    {
        if (count($pairs) >= 20000 || !is_array($item)) return;
        $pair = self::validPair($item);
        if ($pair !== null) { $pairs[] = $pair; return; }
        foreach ($item as $child) self::collectPairs($child, $pairs);
    }

    private static function validPair(mixed $value): ?array
    {
        if (!is_array($value) || count($value) < 2 || !is_numeric($value[0] ?? null) || !is_numeric($value[1] ?? null)) return null;
        $lon = (float) $value[0]; $lat = (float) $value[1];
        if (!is_finite($lon) || !is_finite($lat) || abs($lon) > 180 || abs($lat) > 90) return null;
        return [round($lon, 6), round($lat, 6)];
    }

    private static function label(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? self::limitLabel(trim($value), 180) : null;
    }

    private static function limitLabel(string $text, int $limit): string
    {
        return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
    }

    private static function osmLink(array $feature, array $properties): ?string
    {
        $type = $properties['osm_type'] ?? null;
        $id = $properties['osm_id'] ?? null;
        if ((!$type || !$id) && is_string($feature['id'] ?? null) && preg_match('~^(node|way|relation)[/:](\d+)$~', $feature['id'], $m)) {
            $type = $m[1]; $id = $m[2];
        }
        if (!in_array($type, ['node', 'way', 'relation'], true) || !ctype_digit((string) $id) || (int) $id < 1) return null;
        return 'https://www.openstreetmap.org/' . $type . '/' . $id;
    }
}

