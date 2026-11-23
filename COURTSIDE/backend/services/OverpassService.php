<?php
/** Community OpenStreetMap Overpass API integration, without credentials.
 *  Fixed HTTPS host, constrained OSM tags and validated numeric parameters.
 *  Intended for low-volume university demonstration; public servers have usage limits.
 */
final class OverpassService
{
    private const ENDPOINT = 'https://overpass.private.coffee/api/interpreter';
    /** @var callable|null Optional injectable HTTP transport for deterministic tests. */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function findCourts(float $lat, float $lon, int $radius = 3000, int $limit = 20): array
    {
        return $this->search($lat, $lon, $radius, $limit, false);
    }

    public function findSportsHalls(float $lat, float $lon, int $radius = 5000, int $limit = 20): array
    {
        return $this->search($lat, $lon, $radius, $limit, true);
    }

    private function search(float $lat, float $lon, int $radius, int $limit, bool $halls): array
    {
        $lat = Validation::number($lat, 'lat', -90.0, 90.0);
        $lon = Validation::number($lon, 'lon', -180.0, 180.0);
        $radius = Validation::integer($radius, 'radius', 200, 10000);
        $limit = Validation::integer($limit, 'limit', 1, 50);
        $around = sprintf('(around:%d,%.6F,%.6F)', $radius, $lat, $lon);
        // Never interpolate arbitrary user text into Overpass QL.
        $selectors = $halls
            ? 'nwr' . $around . '["leisure"~"^(sports_centre|sports_hall|stadium)$"];'
              . 'nwr' . $around . '["building"~"^(sports_hall|arena|stadium)$"];'
            : 'nwr' . $around . '["leisure"="pitch"]["sport"~"(^|;)basketball(;|$)"];';
        $query = '[out:json][timeout:15];(' . $selectors . ');out center 120;';
        ExternalApiLog::info('Overpass OSM features request started (' . ($halls ? 'sports_halls' : 'basketball_courts') . ')');
        try {
            [$httpCode, $body] = $this->transport !== null
                ? ($this->transport)(self::ENDPOINT, $query)
                : $this->request(self::ENDPOINT, $query);
        } catch (Throwable $error) {
            ExternalApiLog::failure('Overpass transport failed (' . get_class($error) . ', code=' . (int) $error->getCode() . ')');
            throw new ServiceException('Sports venue discovery is temporarily unavailable. Please try again later.', 504);
        }
        if ($httpCode !== 200) {
            ExternalApiLog::failure('Overpass HTTP ' . (int) $httpCode);
            throw new ServiceException(
                $httpCode === 429 ? 'The map service is busy. Try again shortly.' : 'The map service is temporarily unavailable.',
                $httpCode === 429 ? 503 : 502
            );
        }
        if (!is_string($body) || strlen($body) > 4_000_000) {
            throw new ServiceException('The map service returned an invalid response.', 502);
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ServiceException('The map service returned invalid JSON.', 502);
        }
        if (!is_array($decoded) || !isset($decoded['elements']) || !is_array($decoded['elements'])) {
            throw new ServiceException('The map service returned an unexpected response.', 502);
        }
        $items = [];
        $seen = [];
        foreach ($decoded['elements'] as $element) {
            if (!is_array($element)) continue;
            $type = $element['type'] ?? null;
            $id = $element['id'] ?? null;
            if (!in_array($type, ['node','way','relation'], true) || !is_numeric($id) || (int) $id < 1) continue;
            $key = $type . '/' . (int) $id;
            if (isset($seen[$key])) continue;
            $point = ($type === 'node') ? $element : ($element['center'] ?? null);
            if (!is_array($point)) continue;
            $itemLat = $point['lat'] ?? null;
            $itemLon = $point['lon'] ?? null;
            if (!is_numeric($itemLat) || !is_numeric($itemLon)) continue;
            $itemLat = (float) $itemLat;
            $itemLon = (float) $itemLon;
            if (!is_finite($itemLat) || !is_finite($itemLon) || abs($itemLat) > 90 || abs($itemLon) > 180) continue;
            $seen[$key] = true;
            $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
            $name = self::safeText($tags['name'] ?? null) ?: ($halls ? 'Unnamed sports facility' : 'Unnamed basketball court');
            $items[] = [
                'name' => $name,
                'latitude' => round($itemLat, 6),
                'longitude' => round($itemLon, 6),
                'surface' => self::safeText($tags['surface'] ?? null),
                'category' => self::safeText($tags['leisure'] ?? $tags['building'] ?? null),
                'sport' => self::safeText($tags['sport'] ?? null),
                'access' => self::safeText($tags['access'] ?? null),
                'osm_url' => 'https://www.openstreetmap.org/' . $key,
                'map_url' => sprintf('https://www.openstreetmap.org/?mlat=%.6F&mlon=%.6F#map=17/%.6F/%.6F', $itemLat, $itemLon, $itemLat, $itemLon),
                'source' => 'OpenStreetMap via Overpass',
            ];
            if (count($items) >= $limit) break;
        }
        return [
            $halls ? 'halls' : 'courts' => $items,
            'count' => count($items),
            'attribution' => '© OpenStreetMap contributors',
            'provider' => 'Overpass',
        ];
    }

    private static function safeText(mixed $text): ?string
    {
        if (!is_string($text) || trim($text) === '') return null;
        $text = trim($text);
        return function_exists('mb_substr') ? mb_substr($text, 0, 180, 'UTF-8') : substr($text, 0, 180);
    }

    /** @return array{0:int,1:string} */
    private function request(string $url, string $query): array
    {
        $postBody = http_build_query(['data' => $query], '', '&', PHP_QUERY_RFC3986);
        $headers = ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8', 'Accept: application/json'];
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) throw new RuntimeException('Overpass cURL init failed');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'COURTSIDE/1.0 (university basketball court demo)',
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errorCode = curl_errno($curl);
            curl_close($curl);
            if ($body === false) throw new RuntimeException('Overpass network error', $errorCode);
            return [$status, (string) $body];
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("
", array_merge($headers, ['User-Agent: COURTSIDE/1.0 university demo'])) . "
",
                'content' => $postBody,
                'timeout' => 20,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) throw new RuntimeException('Overpass stream network error');
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $match)) {
                $status = (int) $match[1];
                break;
            }
        }
        return [$status, $body];
    }
}
