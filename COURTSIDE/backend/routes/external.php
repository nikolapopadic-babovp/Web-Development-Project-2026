<?php
/** Third-party court discovery with OpenStreetMap fallback: input validation -> service -> JSON. */
Flight::route('GET /api/external/courts', static function (): void {
    ApiResponder::handle(static function (): array {
        $query = Flight::request()->query->getData();
        if (!isset($query['lat']) || !isset($query['lon'])) {
            throw new ServiceException('lat and lon query parameters are required.', 400);
        }
        $lat = Validation::number($query['lat'], 'lat', -90.0, 90.0);
        $lon = Validation::number($query['lon'], 'lon', -180.0, 180.0);
        $radius = isset($query['radius']) ? Validation::integer($query['radius'], 'radius', 200, 10000) : 3000;
        $limit = isset($query['limit']) ? Validation::integer($query['limit'], 'limit', 1, 50) : 20;
        return (new SportsDiscoveryService())->findCourts($lat, $lon, $radius, $limit);
    });
});

/** Sports halls and arenas discovery; leaves /external/courts intact for compatibility. */
Flight::route('GET /api/external/sports-halls', static function (): void {
    ApiResponder::handle(static function (): array {
        $query = Flight::request()->query->getData();
        if (!isset($query['lat']) || !isset($query['lon'])) {
            throw new ServiceException('lat and lon query parameters are required.', 400);
        }
        $lat = Validation::number($query['lat'], 'lat', -90.0, 90.0);
        $lon = Validation::number($query['lon'], 'lon', -180.0, 180.0);
        $radius = isset($query['radius']) ? Validation::integer($query['radius'], 'radius', 200, 10000) : 5000;
        $limit = isset($query['limit']) ? Validation::integer($query['limit'], 'limit', 1, 50) : 20;
        if (isset($query['name']) && !is_string($query['name'])) {
            throw new ServiceException('Venue name must be text.', 400);
        }
        if (isset($query['area']) && !is_string($query['area'])) {
            throw new ServiceException('Neighbourhood must be text.', 400);
        }
        $name = isset($query['name']) ? trim($query['name']) : null;
        $area = isset($query['area']) ? trim($query['area']) : null;
        if ($name === '') $name = null;
        if ($area === '') $area = null;
        return (new SportsDiscoveryService())->findSportsHalls($lat, $lon, $radius, $limit, $name, $area);
    });
});
