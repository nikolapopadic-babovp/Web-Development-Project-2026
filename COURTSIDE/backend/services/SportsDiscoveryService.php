<?php
/** MapLark-first venue search, with Photon and Overpass fallbacks.
 * Named venues and neighbourhoods use Photon for searchable OSM place data.
 * Only basketball-tagged courts and potential indoor multi-sport halls are returned.
 */
final class SportsDiscoveryService
{
    private $maplark;
    private $overpass;
    private $photon;

    public function __construct(?callable $maplark = null, ?callable $overpass = null, ?callable $photon = null)
    {
        $this->maplark = $maplark;
        $this->overpass = $overpass;
        $this->photon = $photon;
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
        $lat = Validation::number($lat, 'lat', -90.0, 90.0);
        $lon = Validation::number($lon, 'lon', -180.0, 180.0);
        $radius = Validation::integer($radius, 'radius', 200, 10000);
        $limit = Validation::integer($limit, 'limit', 1, 50);
        foreach (['Venue name' => $name, 'Neighbourhood' => $area] as $label => $value) {
            if ($value === null) continue;
            $length = function_exists('mb_strlen') ? mb_strlen(trim($value), 'UTF-8') : strlen(trim($value));
            if ($length < 1 || $length > 80 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new ServiceException($label . ' must contain 1–80 printable characters.', 400);
            }
        }
        // Named search and neighbourhood search are location/name queries, not MapLark tag queries.
        if ($name !== null || $area !== null) {
            $result = $this->photon !== null
                ? ($this->photon)($kind, $lat, $lon, $radius, $limit, $name, $area)
                : (new PhotonService())->findSportsHalls($lat, $lon, $radius, $limit, $name, $area);
            return self::filterHalls($result, $kind, $limit);
        }
        try {
            if ($this->maplark !== null) {
                $result = ($this->maplark)($kind, $lat, $lon, $radius, $limit);
            } else {
                $provider = new MapLarkService();
                $result = $kind === 'halls'
                    ? $provider->findSportsHalls($lat, $lon, $radius, $limit)
                    : $provider->findCourts($lat, $lon, $radius, $limit);
            }
            return self::filterHalls($result, $kind, $limit);
        } catch (ServiceException $error) {
            if (!in_array($error->status(), [502, 503, 504], true)) throw $error;
            ExternalApiLog::info('MapLark unavailable; using OSM fallback');
        }
        // Retain original injected Overpass callback behavior for existing tests.
        if ($this->overpass !== null && $this->photon === null) {
            return self::filterHalls(($this->overpass)($kind, $lat, $lon, $radius, $limit), $kind, $limit);
        }
        try {
            if ($this->photon !== null) {
                $result = ($this->photon)($kind, $lat, $lon, $radius, $limit, null, null);
            } else {
                $provider = new PhotonService();
                $result = $kind === 'halls'
                    ? $provider->findSportsHalls($lat, $lon, $radius, $limit)
                    : $provider->findCourts($lat, $lon, $radius, $limit);
            }
            return self::filterHalls($result, $kind, $limit);
        } catch (ServiceException $error) {
            if (!in_array($error->status(), [502, 503, 504], true)) throw $error;
            ExternalApiLog::info('Photon unavailable; trying Overpass');
        }
        if ($this->overpass !== null) {
            $result = ($this->overpass)($kind, $lat, $lon, $radius, $limit);
        } else {
            $provider = new OverpassService();
            $result = $kind === 'halls'
                ? $provider->findSportsHalls($lat, $lon, $radius, $limit)
                : $provider->findCourts($lat, $lon, $radius, $limit);
        }
        return self::filterHalls($result, $kind, $limit);
    }

    /** Ensure all providers obey the same user-facing venue eligibility rules. */
    private static function filterHalls(array $result, string $kind, int $limit): array
    {
        if ($kind !== 'halls' || !isset($result['halls']) || !is_array($result['halls'])) return $result;
        $clean = [];
        foreach ($result['halls'] as $item) {
            if (!is_array($item)) continue;
            $category = strtolower(trim((string)($item['category'] ?? '')));
            $sport = strtolower(trim((string)($item['sport'] ?? '')));
            $tokens = array_map('trim', preg_split('/[;,]/', $sport) ?: []);
            $explicit = in_array('basketball', $tokens, true) || in_array('streetball', $tokens, true);
            $multi = in_array('multi', $tokens, true) || in_array('multisport', $tokens, true) || in_array('multi_sport', $tokens, true);
            if (in_array($category, ['sports_hall','sports_centre'], true)) {
                if (!$explicit && $sport !== '' && !$multi) continue;
            } elseif (in_array($category, ['pitch','stadium','arena'], true)) {
                if (!$explicit) continue;
            } else {
                continue;
            }
            $item['basketball_evidence'] = $explicit ? 'confirmed' : 'possible';
            $clean[] = $item;
        }
        $result['halls'] = array_slice($clean, 0, $limit);
        $result['count'] = count($result['halls']);
        return $result;
    }
}
