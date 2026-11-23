<?php
/**
 * Public Photon (OSM) geocoding and basketball-venue discovery.
 * No config.php or credentials are read by this service.
 * Only mapped basketball courts and plausible indoor multisport halls are returned.
 * Results may not be bookable or actually permit basketball; do not promise access.
 */
final class PhotonService
{
    private const ENDPOINT = 'https://photon.komoot.io/api/';
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function findSportsHalls(
        float $lat, float $lon, int $radius = 5000, int $limit = 20,
        ?string $name = null, ?string $area = null
    ): array {
        return $this->search('halls', $lat, $lon, $radius, $limit, $name, $area);
    }

    public function findCourts(float $lat, float $lon, int $radius = 3000, int $limit = 20): array
    {
        return $this->search('courts', $lat, $lon, $radius, $limit, null, null);
    }

    private function search(
        string $kind, float $lat, float $lon, int $radius, int $limit,
        ?string $name, ?string $area
    ): array {
        $lat = Validation::number($lat, 'lat', -90, 90);
        $lon = Validation::number($lon, 'lon', -180, 180);
        $radius = Validation::integer($radius, 'radius', 200, 10000);
        $limit = Validation::integer($limit, 'limit', 1, 50);
        $name = self::checkedText($name, 'Venue name');
        $area = self::checkedText($area, 'Neighbourhood');
        if ($kind === 'courts' && ($name !== null || $area !== null)) {
            throw new ServiceException('Court search does not accept a name or neighbourhood.', 400);
        }
        if ($area !== null) {
            // IMPORTANT: "Grbavica" is a neighbourhood, not part of the hall's name.
            // Resolve its location first, then fetch ALL sports venues around that point.
            [$lat, $lon] = $this->resolveNeighbourhood($area, $lat, $lon);
        }
        $params = [
            'lat' => sprintf('%.6F', $lat),
            'lon' => sprintf('%.6F', $lon),
            'bbox' => self::boundingBox($lat, $lon, $radius),
            'limit' => 100,
            // Photon categories use OR within one comma-separated include parameter.
            // Explicitly filter upstream AND locally (the latter applies to named searches).
            'include' => $kind === 'courts'
                ? 'osm.leisure.pitch'
                : 'osm.leisure.sports_hall,osm.leisure.sports_centre,osm.building.sports_hall,osm.leisure.pitch,osm.leisure.stadium,osm.building.arena',
        ];
        if ($name !== null) $params['q'] = $name;
        // Never send an unsupported lang=bs parameter.
        $features = $this->fetchFeatures($params, 'basketball ' . $kind);
        $items = [];
        $seenIds = [];
        $seenNearNames = [];
        foreach ($features as $feature) {
            if (!is_array($feature)) continue;
            $props = $feature['properties'] ?? null;
            $geometry = $feature['geometry'] ?? null;
            $coords = is_array($geometry) ? ($geometry['coordinates'] ?? null) : null;
            if (!is_array($props) || !is_array($coords) || count($coords) < 2 ||
                !is_numeric($coords[0]) || !is_numeric($coords[1])) continue;
            $itemLon = (float)$coords[0];
            $itemLat = (float)$coords[1];
            if (!is_finite($itemLon) || !is_finite($itemLat) || abs($itemLon) > 180 || abs($itemLat) > 90) continue;
            $distance = self::distanceMeters($lat, $lon, $itemLat, $itemLon);
            if ($distance > $radius) continue;
            $key = strtolower(trim((string)($props['osm_key'] ?? '')));
            $value = strtolower(trim((string)($props['osm_value'] ?? '')));
            $extra = is_array($props['extra'] ?? null) ? $props['extra'] : [];
            $sport = self::safeText($extra['sport'] ?? $props['sport'] ?? null);
            $evidence = self::basketballEvidence($key, $value, $sport);
            if ($evidence === null || ($kind === 'courts' && $evidence !== 'confirmed')) continue;
            $label = self::safeText($props['name'] ?? null);
            if ($label === null) continue;
            $osmType = strtoupper((string)($props['osm_type'] ?? ''));
            $osmId = $props['osm_id'] ?? null;
            $type = ['N' => 'node', 'W' => 'way', 'R' => 'relation'][$osmType] ?? null;
            $unique = $type !== null && is_numeric($osmId) && (int)$osmId > 0
                ? $type . '/' . (int)$osmId
                : sprintf('point:%.6F:%.6F', $itemLat, $itemLon);
            if (isset($seenIds[$unique])) continue;
            $seenIds[$unique] = true;
            // Photon occasionally returns multiple mapped geometries for one venue.
            $normalized = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
            $nearName = $normalized . ':' . round($itemLat, 3) . ':' . round($itemLon, 3);
            if (isset($seenNearNames[$nearName])) continue;
            $seenNearNames[$nearName] = true;
            $items[] = [
                'name' => $label,
                'latitude' => round($itemLat, 6),
                'longitude' => round($itemLon, 6),
                'surface' => self::safeText($extra['surface'] ?? null),
                'category' => $value,
                'sport' => $sport,
                'access' => self::safeText($extra['access'] ?? null),
                'basketball_evidence' => $evidence,
                'osm_url' => ($type && is_numeric($osmId) && (int)$osmId > 0)
                    ? 'https://www.openstreetmap.org/' . $type . '/' . (int)$osmId : null,
                'map_url' => sprintf('https://www.openstreetmap.org/?mlat=%.6F&mlon=%.6F#map=17/%.6F/%.6F', $itemLat, $itemLon, $itemLat, $itemLon),
                'source' => 'OpenStreetMap via Photon',
                '_distance' => $distance,
            ];
        }
        // Explicitly mapped basketball first, nearby multisport halls next.
        usort($items, static function (array $a, array $b): int {
            $aRank = $a['basketball_evidence'] === 'confirmed' ? 0 : 1;
            $bRank = $b['basketball_evidence'] === 'confirmed' ? 0 : 1;
            return ($aRank <=> $bRank) ?: ($a['_distance'] <=> $b['_distance']);
        });
        $items = array_slice($items, 0, $limit);
        foreach ($items as &$item) unset($item['_distance']);
        unset($item);
        return [
            $kind => $items,
            'count' => count($items),
            'attribution' => '© OpenStreetMap contributors',
            'provider' => 'Photon',
            'search_area' => $area,
        ];
    }

    /** Convert a neighbourhood like Grbavica into a location before sports-venue discovery. */
    private function resolveNeighbourhood(string $area, float $cityLat, float $cityLon): array
    {
        // Geography of search is restricted to 20 km around the selected city.
        $params = [
            'q' => $area,
            'lat' => sprintf('%.6F', $cityLat),
            'lon' => sprintf('%.6F', $cityLon),
            'bbox' => self::boundingBox($cityLat, $cityLon, 20000),
            'limit' => 20,
        ];
        $features = $this->fetchFeatures($params, 'neighbourhood');
        $best = null;
        $bestScore = -INF;
        $target = function_exists('mb_strtolower') ? mb_strtolower($area, 'UTF-8') : strtolower($area);
        foreach ($features as $feature) {
            if (!is_array($feature)) continue;
            $p = $feature['properties'] ?? null;
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($p) || !is_array($coords) || !is_numeric($coords[0] ?? null) || !is_numeric($coords[1] ?? null)) continue;
            $key = strtolower((string)($p['osm_key'] ?? ''));
            $value = strtolower((string)($p['osm_value'] ?? ''));
            $eligible = ($key === 'place' && in_array($value, ['suburb','quarter','neighbourhood','district','locality','borough','village','hamlet'], true));
            if (!$eligible) continue;
            $lat = (float)$coords[1]; $lon = (float)$coords[0];
            if (!is_finite($lat) || !is_finite($lon) || abs($lat)>90 || abs($lon)>180) continue;
            $distance = self::distanceMeters($cityLat, $cityLon, $lat, $lon);
            if ($distance > 20000) continue;
            $resultName = trim((string)($p['name'] ?? ''));
            $normalized = function_exists('mb_strtolower') ? mb_strtolower($resultName, 'UTF-8') : strtolower($resultName);
            // Prefer exact neighbourhood, e.g. Grbavica, to Grbavica I/II.
            $score = ($normalized === $target ? 100000 : 0)
                + (str_contains($normalized, $target) ? 10000 : 0) - $distance;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$lat, $lon];
            }
        }
        if ($best === null) {
            throw new ServiceException('Neighbourhood not found in the selected city. Try a different name or search the whole city.', 404);
        }
        return $best;
    }

    /** Return confirmed basketball or potential indoor multi-sport venue; otherwise null. */
    public static function basketballEvidence(string $key, string $value, ?string $sport): ?string
    {
        $sport = strtolower(trim($sport ?? ''));
        $tokens = preg_split('/[;,]/', $sport) ?: [];
        $tokens = array_map('trim', $tokens);
        $explicit = in_array('basketball', $tokens, true) || in_array('streetball', $tokens, true);
        $multi = in_array('multi', $tokens, true) || in_array('multisport', $tokens, true) || in_array('multi_sport', $tokens, true);
        $indoorPotential = ($key === 'leisure' && in_array($value, ['sports_hall','sports_centre'], true)) ||
            ($key === 'building' && $value === 'sports_hall');
        if ($indoorPotential) return $explicit ? 'confirmed' : (($sport === '' || $multi) ? 'possible' : null);
        if (($key === 'leisure' && in_array($value, ['pitch','stadium'], true)) || ($key === 'building' && $value === 'arena')) {
            return $explicit ? 'confirmed' : null;
        }
        return null;
    }

    private static function checkedText(?string $value, string $field): ?string
    {
        if ($value === null) return null;
        $text = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length < 1 || $length > 80 || preg_match('/[\x00-\x1F\x7F]/u', $text)) {
            throw new ServiceException($field . ' must contain 1–80 printable characters.', 400);
        }
        return $text;
    }

    private function fetchFeatures(array $params, string $kind): array
    {
        $url = self::ENDPOINT . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        ExternalApiLog::info('Photon search started (' . $kind . ')');
        try {
            [$status, $body] = $this->transport !== null ? ($this->transport)($url) : $this->request($url);
        } catch (Throwable $error) {
            ExternalApiLog::failure('Photon connection failed (' . get_class($error) . ')');
            throw new ServiceException('Sports venue discovery provider did not respond.', 504);
        }
        if ((int)$status !== 200) {
            ExternalApiLog::failure('Photon HTTP ' . (int)$status);
            throw new ServiceException('Sports venue search provider returned HTTP ' . (int)$status . '.', (int)$status === 429 ? 503 : 502);
        }
        if (!is_string($body) || strlen($body) > 4_000_000) {
            throw new ServiceException('The venue search provider returned invalid data.', 502);
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ServiceException('The venue search provider returned invalid JSON.', 502);
        }
        if (!is_array($decoded) || !isset($decoded['features']) || !is_array($decoded['features'])) {
            throw new ServiceException('The venue search provider returned unexpected data.', 502);
        }
        return $decoded['features'];
    }

    private static function boundingBox(float $lat, float $lon, int $radius): string
    {
        $latDelta = $radius / 111320.0;
        $lonDelta = $radius / max(1000.0, 111320.0 * abs(cos(deg2rad($lat))));
        return sprintf('%.6F,%.6F,%.6F,%.6F',
            max(-180.0, $lon - $lonDelta), max(-90.0, $lat - $latDelta),
            min(180.0, $lon + $lonDelta), min(90.0, $lat + $latDelta));
    }

    private static function distanceMeters(float $aLat, float $aLon, float $bLat, float $bLon): float
    {
        $rLat1 = deg2rad($aLat); $rLat2 = deg2rad($bLat);
        $dLat = $rLat2 - $rLat1; $dLon = deg2rad($bLon - $aLon);
        $s = sin($dLat / 2) ** 2 + cos($rLat1) * cos($rLat2) * sin($dLon / 2) ** 2;
        return 6371000.0 * 2.0 * atan2(sqrt($s), sqrt(max(0.0, 1.0 - $s)));
    }

    private static function safeText(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        $value = trim($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, 180, 'UTF-8') : substr($value, 0, 180);
    }

    private function request(string $url): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is not available');
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Failed to initialize cURL');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'COURTSIDE/1.0 (+https://github.com/nikolapopadic-babovp)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorNo = curl_errno($ch);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('Photon cURL error #' . $errorNo);
        return [$status, (string)$body];
    }
}
