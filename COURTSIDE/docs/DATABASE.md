# COURTSIDE — Database schema (Milestone 2)

Implemented in [`database/courtside.sql`](../database/courtside.sql) (MySQL / MariaDB, InnoDB, `utf8mb4`). It follows the draft ERD in [`ERD.md`](ERD.md): six entities, snake_case names, primary keys named `<entity>_id`.

## Tables
| Table | Purpose | Key columns |
|---|---|---|
| `users` | Player and admin accounts | `user_id` PK, `email` UNIQUE, `password_hash`, `role` ENUM(player, admin) |
| `courts` | Basketball courts | `court_id` PK, `city`, `latitude`/`longitude`, `surface_type`, `is_indoor`, `source` + `external_id` UNIQUE |
| `runs` | Scheduled pickup games | `run_id` PK, `court_id` FK, `creator_user_id` FK, `starts_at` (UTC), `max_players` > 0, `status` |
| `run_members` | Users in runs (many-to-many) | `run_member_id` PK, `run_id` FK, `user_id` FK, UNIQUE (`run_id`, `user_id`), `membership_status` |
| `saved_courts` | Favourite courts (many-to-many) | `saved_court_id` PK, `user_id` FK, `court_id` FK, UNIQUE (`user_id`, `court_id`) |
| `reviews` | Court ratings and comments | `review_id` PK, `user_id` FK, `court_id` FK, `rating` 1–5, UNIQUE (`user_id`, `court_id`) |

## Enum values
- `users.role`: `player`, `admin`
- `courts.surface_type`: `asphalt`, `concrete`, `rubber`, `wood`, `other`
- `runs.skill_level`: `beginner`, `intermediate`, `competitive`, `all`
- `runs.status`: `open`, `full`, `cancelled`, `completed`
- `run_members.membership_status`: `joined`, `waitlisted`, `cancelled`

## Relationships and delete behaviour
| Foreign key | ON DELETE | Reason |
|---|---|---|
| `runs.court_id` → `courts` | RESTRICT | A court that still has runs cannot be removed by accident |
| `runs.creator_user_id` → `users` | RESTRICT | Keep run history; delete or reassign runs first |
| `run_members.run_id` → `runs` | CASCADE | Membership rows are meaningless without the run |
| `run_members.user_id` → `users` | CASCADE | Link row only |
| `saved_courts.user_id` / `court_id` | CASCADE | Link rows only |
| `reviews.user_id` → `users` | RESTRICT | Reviews are history and are not deleted silently with a user |
| `reviews.court_id` → `courts` | CASCADE | Reviews of a deleted court have no purpose |

All foreign keys use `ON UPDATE CASCADE`.

## Constraints
- `users.email` is unique; `users.role` is limited to `player` / `admin`.
- `runs.max_players > 0` and `reviews.rating BETWEEN 1 AND 5` are CHECK constraints (enforced by MySQL 8.0.16+ and MariaDB 10.2.1+).
- `runs.starts_at` is stored in UTC; the frontend converts to local time for display.
- Passwords are stored only as bcrypt hashes (`password_hash`); the sample accounts are listed in the README.
- `courts (source, external_id)` is unique so a court imported from MapLark later cannot be inserted twice.

## DAO layer
`backend/dao/` contains `Database` (PDO connection from `config/config.php`), `BaseDao` (reusable `getAll`, `getById`, `insert`, `update`, `delete`) and one DAO per table: `UserDao`, `CourtDao`, `RunDao`, `RunMemberDao`, `SavedCourtDao`, `ReviewDao`. All SQL uses PDO prepared statements; services (Milestone 3) will be the only layer calling the DAOs.
