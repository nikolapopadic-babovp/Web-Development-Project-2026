# COURTSIDE — Milestone 3

COURTSIDE is a responsive single-page application for finding basketball courts, saving favourites, and planning and joining pickup games. The backend is a **FlightPHP REST API** with PHP services, PDO DAOs and a MySQL database. Six database entities have full CRUD endpoints, documented with OpenAPI/Swagger. A **sports-venue discovery** feature (external API) is visible on the *Find courts* screen.

## Milestone 3 checklist

| Requirement | Status |
|---|---|
| One service per entity with validation and business rules | Done — six services |
| FlightPHP routes for all CRUD operations | Done — GET list/detail, POST, PUT, PATCH, DELETE for six entities |
| JSON with correct status codes | 200, 201, 400, 404, 409, 500 (401/403 arrive with authentication in Milestone 4) |
| OpenAPI documentation + Swagger page | Done — `backend/openapi.json`, UI at `/backend/api/docs` |
| External API service + endpoint | Done — `GET /backend/api/external/sports-halls` (and `/external/courts`) |
| External API visible in the frontend | Done — *Find courts → Find local sports halls and arenas* |
| Timeout, error handling, key in config | Done — 10 s timeout, friendly JSON errors, key only in `config.php`, failures in `backend/logs/error.log` |

**Next milestone (M4):** JWT login, role middleware, request-logging and error-handling middleware, fully connected frontend. Until then the CRUD endpoints are **not protected** and the other SPA screens still show sample data.

## Stack
- Backend: PHP 8.1+, FlightPHP 3, Composer, PDO, MySQL/MariaDB.
- Frontend: HTML view fragments, JavaScript, jQuery, Bootstrap 5, CSS, hash-based SPA router.
- External API: [MapLark OSM Features v3](https://maplark.com/developer) (needs an API key). If MapLark is unavailable the backend automatically falls back to the public OpenStreetMap services **Photon** and then **Overpass** (no key). Searches by venue name or neighbourhood use Photon directly.
- Documentation: OpenAPI 3.0.3 + Swagger UI (loaded from the jsDelivr CDN, so the browser needs internet).

## Folder structure
```text
COURTSIDE/
  frontend/      # SPA: css, js, views, assets
  backend/
    index.php    # single FlightPHP entry point
    routes/      # api.php (CRUD), external.php, docs.php, ApiResponder.php
    services/    # six entity services, Validation, external API services, ExternalApiLog
    dao/         # PDO DAOs (BaseDao + one per entity)
    config/      # config.example.php (config.php is git-ignored)
    swagger/     # Swagger UI page
    openapi.json # OpenAPI specification
    tests/       # DAO and mocked external-API tests
    logs/        # info.log / error.log (git-ignored)
  database/      # courtside.sql, ERD source
  docs/          # DATABASE.md, ERD.md, erd.svg, AI_LOG.md
  AGENTS.md
```

## Run locally (XAMPP)
1. Start Apache and MySQL in XAMPP.
2. Import `database/courtside.sql` in phpMyAdmin. **This drops and recreates the `courtside` database.**
3. Copy `backend/config/config.example.php` to `backend/config/config.php` and fill in your MySQL values and, optionally, `maplark_api_key`. Never commit `config.php`.
4. In `backend/` run `composer install`, then `composer dump-autoload -o`.
5. Frontend: <http://localhost/Courtside/COURTSIDE/frontend/#/courts>
6. API base: <http://localhost/Courtside/COURTSIDE/backend/api>
7. Swagger UI: <http://localhost/Courtside/COURTSIDE/backend/api/docs>
8. OpenAPI JSON: <http://localhost/Courtside/COURTSIDE/backend/api/openapi.json>
9. External API example: <http://localhost/Courtside/COURTSIDE/backend/api/external/sports-halls?lat=43.8563&lon=18.4131&radius=5000&limit=20>

(Adjust the folder names in the URLs if your project sits somewhere else under `htdocs`.)

## Entities and relationships
- `users` — accounts with the roles `player` and `admin`.
- `courts` — courts and locations; optional external source and ID.
- `runs` — scheduled pickup games; belong to one court and one creator (user).
- `run_members` — users taking part in runs (user ↔ run).
- `saved_courts` — favourite courts (user ↔ court).
- `reviews` — one rating per user per court.

See [`docs/DATABASE.md`](docs/DATABASE.md), [`docs/ERD.md`](docs/ERD.md) and [`database/courtside.sql`](database/courtside.sql).

## Roles and test accounts
| Role | Email | Password |
|---|---|---|
| admin | `admin@courtside.test` | `Admin123!` |
| player | `player@courtside.test` | `Player123!` |

These accounts exist in the sample database. JWT login and role checks are implemented in Milestone 4, so in Milestone 3 every endpoint is open and the API must not be exposed publicly.

## External API
- **Providers:** MapLark first, then Photon, then Overpass (all OpenStreetMap data).
- **Feature:** *Find courts → Find local sports halls and arenas* shows nearby basketball courts and indoor sports halls as cards with an OpenStreetMap link. Venues can be searched by name or neighbourhood.
- **Endpoints:** `GET /api/external/sports-halls` (`lat`, `lon`, `radius`, `limit`, optional `name`, `area`) and `GET /api/external/courts`. Both are documented in Swagger.
- **Safety:** the browser never sees the API key; requests have timeouts; failures are written to `backend/logs/error.log` and shown as a friendly message. The rest of the application keeps working when the external API is down.
- Results are not imported into the local database and do not guarantee that a venue is open to the public.
- Attribution: © [OpenStreetMap contributors](https://www.openstreetmap.org/copyright).

## Documentation and deployment
- `AGENTS.md` — rules for AI coding assistants; `docs/AI_LOG.md` — AI usage per milestone.
- Deployed application: *added in Milestone 5*.