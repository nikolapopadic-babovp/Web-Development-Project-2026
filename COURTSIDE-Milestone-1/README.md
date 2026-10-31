# COURTSIDE — Milestone 1

COURTSIDE helps basketball players discover courts, save places they like, and organise or join pickup games. This repository contains the Milestone 1 project structure, a responsive static SPA, AI-assistant rules, and a draft database ERD.

> **Milestone 2 status:** the MySQL schema (`database/courtside.sql`, six entities with sample data) and the PDO DAO layer (`backend/dao/`) are implemented. The frontend is still the static Milestone 1 SPA with sample data and is not connected to the backend yet. REST routes, services, OpenAPI, JWT/middleware, logging and the live MapLark call comming in Milestones 3–4.

## Tech stack and project structure
- Frontend: HTML templates, CSS, JavaScript, jQuery and Bootstrap 5.
- Planned backend: PHP 8.1+, FlightPHP, PDO and MySQL.
- Local development: XAMPP / Apache.
- Planned external API: MapLark OSM Features API for basketball-court discovery. The API key must be kept in backend configuration and requests should be proxied by the backend when implemented.


COURTSIDE-Milestone-1/
├── AGENTS.md
├── README.md
├── .gitignore
├── .github/agents/                 # Frontend, Backend, Reviewer agents
├── .agents/skills/update-ai-log/   # AI log workflow skill
├── docs/                           # AI log, ERD and project notes
├── database/                       # Draft ERD artifacts for M1
├── frontend/
│   ├── index.html                  # Single-page shell
│   ├── css/styles.css
│   ├── js/app.js                   # Hash router, demo state and interactions
│   ├── assets/courtside-mark.svg
│   └── views/                      # One HTML fragment per SPA view
└── backend/
    ├── routes/                     # FlightPHP routes (Milestone 3)
    ├── services/                   # Business logic (Milestone 3)
    ├── dao/                        # Database.php, BaseDao.php and one DAO per entity
    ├── middleware/                 # (Milestone 4)
    ├── config/config.example.php   # Template; copy to config.php (ignored by Git)
    └── logs/.gitkeep               # Runtime log files are ignored by Git


## Run locally
1. Install/start XAMPP and start **Apache**. MySQL is not needed for this static milestone.
2. Copy the whole project folder into XAMPP's `htdocs` directory, for example `C:/xampp/htdocs/COURTSIDE-Milestone-1`.
3. Open `http://localhost/COURTSIDE-Milestone-1/frontend/` in your browser. Do not open `index.html` with `file://`, because the SPA fetches its view fragments over HTTP.
4. Try the court search, filters, favourites, court details, create-run form, join/leave buttons, profile and demo login/register views. Browser data can be reset by clearing site data/localStorage for this local URL.

## Database setup (Milestone 2)
1. Start **Apache** and **MySQL** in XAMPP.
2. Open phpMyAdmin → **Import** → choose `database/courtside.sql` → **Go**. This creates the `courtside` database, six tables and sample data (the file drops and recreates `courtside`, so importing again resets the data).
3. Copy `backend/config/config.example.php` to `backend/config/config.php` and set the `db` values (XAMPP default: host `127.0.0.1`, user `root`, empty password, database `courtside`). `config.php` is ignored by Git.
4. Schema documentation: [`docs/DATABASE.md`](docs/DATABASE.md). ERD: [`docs/ERD.md`](docs/ERD.md).

## SPA views and features
- Home / dashboard with discovery call-to-action and upcoming sample games.
- Court explorer with search, city, surface and indoor/outdoor filters, sort order and grid/list presentation.
- Court details with amenities, sample rating, favourite toggle and OpenStreetMap directions link.
- Pickup runs list, run details, create-run form, join/leave interaction and capacity indicators.
- Login and registration demo views, profile, saved courts and admin overview.
- Responsive navigation, footer, empty states, toast feedback and a mobile-friendly layout.
- MapLark is planned for the external-court-data feature; Milestone 1 does not make a live MapLark API call.

## Planned database entities
1. `users` — player/admin accounts.
2. `courts` — court information and location.
3. `runs` — scheduled pickup games created at a court.
4. `run_members` — membership/attendance relation between users and runs.
5. `saved_courts` — users' saved/favourite courts.
6. `reviews` — user ratings and comments for courts.

The relationships and proposed keys are documented in [`docs/ERD.svg`](docs/ERD.svg), with a visual draft at [`database/erd.mmd`](database/erd.mmd) and the Mermaid source at [`database/erd.mmd`](database/erd.mmd). The schema is now implemented in `database/courtside.sql` and documented in `docs/DATABASE.md`.

## Roles and test accounts
| Role | Email | Password |
|---|---|---|
| admin | admin@courtside.test | Admin123! |
| player | player@courtside.test | Player123! |

These accounts exist in the sample data of `database/courtside.sql` (passwords stored as bcrypt hashes). Real login against them starts in Milestone 4; the static SPA still uses its demo login.

## External API plan
MapLark's OSM Features API is the planned source for finding tagged basketball courts. The backend service will call the provider with a timeout, handle unavailable/error responses, log failures and return a friendly message. The API key will be stored in the ignored `backend/config/config.php`; only `backend/config/config.example.php` is committed. OpenStreetMap attribution must be shown wherever OSM-derived data is displayed. Live integration is not part of Milestone 1.

## API documentation and deployment
- Swagger/OpenAPI: **not implemented in Milestone 1**; planned for Milestone 3. No Swagger URL is available yet.
- Deployed application: **not deployed yet**; planned for Milestone 5. No live URL is available yet.
- Test accounts: none for a real backend yet. See the demo-access note above for the static prototype.

## AI documentation
- `AGENTS.md` contains shared project rules.
- `.github/agents/` contains Frontend, Backend and Reviewer agent definitions.
- `.agents/skills/update-ai-log/SKILL.md` describes how to propose an AI-log entry.
- `docs/AI_LOG.md` records the assistance used for this milestone. Review it, correct anything that does not match your experience, and add your own findings before submission. You must be able to explain all code during the defense.

## References
- Course document: *Project Requirements, Milestones and Deadlines*.
- Course document: *Lab 01.01 — AI Coding Assistants and Agents*.
- Bootstrap 5 documentation: https://getbootstrap.com/docs/5.3/
- MapLark OSM Features API: https://wiki.openstreetmap.org/wiki/Maplark
- OpenStreetMap: https://www.openstreetmap.org/copyright
