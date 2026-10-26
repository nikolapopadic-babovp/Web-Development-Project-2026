# AGENTS.md

## Project
COURTSIDE is a responsive single-page web application for discovering basketball courts, saving favourites, and organising or joining pickup games. It is designed for local basketball players and a platform administrator.

**Roles:** player, admin. **Planned entities:** users, courts, runs, run_members, saved_courts, reviews (six entities).

## Technology stack
- Frontend: HTML templates, CSS, JavaScript, jQuery, Bootstrap 5; single-page application (SPA).
- Backend: PHP 8.1+ with FlightPHP REST API, PDO, MySQL (later milestones).
- Local environment: XAMPP / Apache and MySQL.
- Planned external API: MapLark OSM Features API for basketball court discovery. API credentials must remain server-side; do not call a secret-bearing API directly from the static frontend.

## Folder structure
- `frontend/css/`, `frontend/js/`, `frontend/views/`, `frontend/assets/`
- `backend/routes/`, `services/`, `dao/`, `middleware/`, `config/`, `logs/`
- `.github/agents/`: Frontend, Backend, Reviewer custom agents
- `.agents/skills/update-ai-log/SKILL.md`
- `docs/`: AI log, draft ERD and project notes

## Frontend rules
- Each view is a separate HTML fragment in `frontend/views/`; `frontend/index.html` is the SPA shell.
- Navigation must change views without a full page reload (hash routing).
- Use Bootstrap 5 classes and shared CSS; avoid inline styles and duplicated layout markup.
- Keep all Milestone 1 data explicitly labelled as demo/sample data. Do not imply that a sample court is live-verified.
- Use accessible labels, semantic elements, keyboard-operable controls and responsive layouts.
- Do not add libraries or frameworks without asking first.

## Backend rules
- Requests follow `route -> middleware -> service -> DAO -> database` as applicable.
- Only services call DAO classes. Routes never call DAO classes directly.
- All SQL uses PDO prepared statements.
- Routes return JSON with appropriate HTTP status codes and a consistent shape: `{ "data": ..., "message": "..." }`.
- Use reusable JWT authentication, role authorization, request logging and error-handling middleware in later milestones.
- Never expose stack traces, SQL statements, filesystem paths or secrets in API responses.

## Conventions
- PHP classes: PascalCase (`CourtService`, `CourtDao`); class filenames match class names.
- JavaScript and PHP variables/functions: camelCase.
- Database tables and columns: snake_case.
- Keep API keys, database credentials and JWT secrets in ignored `backend/config/config.php`; commit only `config.example.php`.

## Rules for AI assistants
- Read this file before editing and stay within the requested task scope.
- Do not add libraries/frameworks or change the planned database entities without asking first.
- Do not put passwords, keys, tokens or secrets in source code or Git.
- Do not edit generated/runtime files in `backend/logs/`.
- Make small, reviewable changes. Explain changed files and important design decisions.
- Never invent user actions, test results, AI prompts, credentials, API responses or live deployment URLs.
- Before finishing, run available syntax/structure checks and report what was and was not tested. Do not paste full code into the chat.
- Do not test anything yourselves unless I state that.