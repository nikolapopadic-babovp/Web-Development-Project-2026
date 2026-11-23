<?php
/**
 * Milestone 3 CRUD endpoints; no SQL and no business rules in route handlers.
 * Authentication / role middleware is deliberately deferred to Milestone 4.
 *
 * Services are created lazily (inside the request), so a database problem can never
 * break unrelated routes such as /api/docs or /api/external/* and is always turned
 * into a JSON error by ApiResponder instead of an uncaught fatal error.
 */
function registerCrudRoutes(string $path, callable $makeService): void
{
    Flight::route('GET ' . $path, static function () use ($makeService): void {
        ApiResponder::handle(static function () use ($makeService): array {
            $service = $makeService();
            $query = Flight::request()->query->getData();
            [$limit, $offset] = Validation::pagination($query);
            if ($service instanceof CourtService) {
                return $service->search($query, $limit, $offset);
            }
            return $service->getAll($limit, $offset);
        });
    });

    Flight::route('GET ' . $path . '/@id', static function ($id) use ($makeService): void {
        ApiResponder::handle(static fn(): array => $makeService()->getById(Validation::id($id)));
    });

    Flight::route('POST ' . $path, static function () use ($makeService): void {
        ApiResponder::handle(static fn(): array => $makeService()->create(ApiResponder::body()), 201, 'Created.');
    });

    foreach (['PUT', 'PATCH'] as $method) {
        Flight::route($method . ' ' . $path . '/@id', static function ($id) use ($makeService): void {
            ApiResponder::handle(
                static fn(): array => $makeService()->update(Validation::id($id), ApiResponder::body()),
                200,
                'Updated.'
            );
        });
    }

    Flight::route('DELETE ' . $path . '/@id', static function ($id) use ($makeService): void {
        ApiResponder::handle(static function () use ($makeService, $id): mixed {
            $makeService()->delete(Validation::id($id));
            return null;
        }, 200, 'Deleted.');
    });
}

// One service per database entity. The services are the only callers of the DAOs.
registerCrudRoutes('/api/users', static fn(): UserService => new UserService());
registerCrudRoutes('/api/courts', static fn(): CourtService => new CourtService());
registerCrudRoutes('/api/runs', static fn(): RunService => new RunService());
registerCrudRoutes('/api/run-members', static fn(): RunMemberService => new RunMemberService());
registerCrudRoutes('/api/saved-courts', static fn(): SavedCourtService => new SavedCourtService());
registerCrudRoutes('/api/reviews', static fn(): ReviewService => new ReviewService());

Flight::route('GET /api', static function (): void {
    ApiResponder::send(['name' => 'COURTSIDE API', 'milestone' => '3 (services, CRUD routes, OpenAPI, external API)']);
});