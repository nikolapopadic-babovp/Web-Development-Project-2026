-- COURTSIDE - Milestone 2 database schema and sample data
-- All data below is still just SAMPLE data for development; it is not live-verified court data.

DROP DATABASE IF EXISTS courtside;
CREATE DATABASE courtside CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE courtside;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('player','admin') NOT NULL DEFAULT 'player',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- courts
-- ---------------------------------------------------------------------------
CREATE TABLE courts (
  court_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  address      VARCHAR(255) NULL,
  city         VARCHAR(100) NOT NULL,
  latitude     DECIMAL(9,6) NULL,
  longitude    DECIMAL(9,6) NULL,
  surface_type ENUM('asphalt','concrete','rubber','wood','other') NOT NULL DEFAULT 'asphalt',
  is_indoor    TINYINT(1) NOT NULL DEFAULT 0,
  amenities    TEXT NULL,
  source       VARCHAR(50) NOT NULL DEFAULT 'sample',   -- 'sample', 'manual' or 'maplark' (later)
  external_id  VARCHAR(100) NULL,                        -- provider id, used later to avoid duplicate imports
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (court_id),
  UNIQUE KEY uq_courts_source_external (source, external_id),
  KEY idx_courts_city (city)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- runs (pickup games). starts_at is stored in UTC.
-- ---------------------------------------------------------------------------
CREATE TABLE runs (
  run_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  court_id        INT UNSIGNED NOT NULL,
  creator_user_id INT UNSIGNED NOT NULL,
  title           VARCHAR(150) NOT NULL,
  starts_at       DATETIME NOT NULL,
  max_players     SMALLINT UNSIGNED NOT NULL,
  skill_level     ENUM('beginner','intermediate','competitive','all') NOT NULL DEFAULT 'all',
  notes           TEXT NULL,
  status          ENUM('open','full','cancelled','completed') NOT NULL DEFAULT 'open',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (run_id),
  KEY idx_runs_court (court_id),
  KEY idx_runs_creator (creator_user_id),
  KEY idx_runs_starts_at (starts_at),
  CONSTRAINT chk_runs_max_players CHECK (max_players > 0),
  -- A court or a creator that still has runs cannot be deleted by accident.
  CONSTRAINT fk_runs_court   FOREIGN KEY (court_id)        REFERENCES courts (court_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_runs_creator FOREIGN KEY (creator_user_id) REFERENCES users (user_id)   ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- run_members (users <-> runs, many-to-many)
-- ---------------------------------------------------------------------------
CREATE TABLE run_members (
  run_member_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id            INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED NOT NULL,
  membership_status ENUM('joined','waitlisted','cancelled') NOT NULL DEFAULT 'joined',
  joined_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (run_member_id),
  UNIQUE KEY uq_run_members_run_user (run_id, user_id),
  KEY idx_run_members_user (user_id),
  -- Pure link rows: removed together with the run or the user.
  CONSTRAINT fk_run_members_run  FOREIGN KEY (run_id)  REFERENCES runs (run_id)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_run_members_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- saved_courts (users <-> courts, many-to-many)
-- ---------------------------------------------------------------------------
CREATE TABLE saved_courts (
  saved_court_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  court_id       INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (saved_court_id),
  UNIQUE KEY uq_saved_courts_user_court (user_id, court_id),
  KEY idx_saved_courts_court (court_id),
  CONSTRAINT fk_saved_courts_user  FOREIGN KEY (user_id)  REFERENCES users (user_id)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_saved_courts_court FOREIGN KEY (court_id) REFERENCES courts (court_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- reviews (one review per user per court)
-- ---------------------------------------------------------------------------
CREATE TABLE reviews (
  review_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  court_id   INT UNSIGNED NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL,
  comment    TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (review_id),
  UNIQUE KEY uq_reviews_user_court (user_id, court_id),
  KEY idx_reviews_court (court_id),
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  -- A user who wrote reviews cannot be deleted silently (history is kept); reviews go with a deleted court.
  CONSTRAINT fk_reviews_user  FOREIGN KEY (user_id)  REFERENCES users (user_id)   ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_court FOREIGN KEY (court_id) REFERENCES courts (court_id) ON DELETE CASCADE  ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ===========================================================================
-- SAMPLE DATA
-- Test accounts (bcrypt hashes, cost 10):
--   admin@courtside.test   / Admin123!
--   player@courtside.test  / Player123!   (all other sample players use the same password)
-- ===========================================================================
INSERT INTO users (user_id, full_name, email, password_hash, role) VALUES
(1, 'Admin COURTSIDE', 'admin@courtside.test',  '$2y$10$j88WHaGs5oGGTO5Umg6vK.bDDf8rRcTxh8B/04PeCAR4UkAkn1hoS', 'admin'),
(2, 'Amar Hodžić',     'player@courtside.test', '$2y$10$BItYsuAEu47tW/ho8S7ZCOFgmifS8FdIQrfIu3wvLEHvjGkJmzYne', 'player'),
(3, 'Lejla Mujić',     'lejla@courtside.test',  '$2y$10$BItYsuAEu47tW/ho8S7ZCOFgmifS8FdIQrfIu3wvLEHvjGkJmzYne', 'player'),
(4, 'Nedim Kovač',     'nedim@courtside.test',  '$2y$10$BItYsuAEu47tW/ho8S7ZCOFgmifS8FdIQrfIu3wvLEHvjGkJmzYne', 'player'),
(5, 'Sara Petrović',   'sara@courtside.test',   '$2y$10$BItYsuAEu47tW/ho8S7ZCOFgmifS8FdIQrfIu3wvLEHvjGkJmzYne', 'player');

INSERT INTO courts (court_id, name, address, city, latitude, longitude, surface_type, is_indoor, amenities, source, external_id) VALUES
(1, 'Skenderija Outdoor Courts',    'Terezija, Sarajevo',      'Sarajevo',      43.849800, 18.403400, 'asphalt', 0, 'Full court, Lights, Water nearby',   'sample', 'sample-court-1'),
(2, 'Dobrinja Neighbourhood Court', 'Dobrinja, Sarajevo',      'Sarajevo',      43.821000, 18.343000, 'rubber',  0, 'Half court, Benches, Open access',   'sample', 'sample-court-2'),
(3, 'East Sarajevo Sports Hall',    'Lukavica, East Sarajevo', 'East Sarajevo', 43.818900, 18.379700, 'wood',    1, 'Indoor, Changing rooms, Full court', 'sample', 'sample-court-3'),
(4, 'Mostar Riverside Court',       'Riverside, Mostar',       'Mostar',        43.343800, 17.807800, 'asphalt', 0, 'Outdoor, Open access, River walk',   'sample', 'sample-court-4'),
(5, 'Banja Luka Park Court',        'Borik, Banja Luka',       'Banja Luka',    44.779000, 17.200700, 'asphalt', 0, 'Outdoor, Benches, Park',             'sample', 'sample-court-5'),
(6, 'Grbavica Community Gym',       'Grbavica, Sarajevo',      'Sarajevo',      43.848000, 18.388000, 'wood',    1, 'Indoor, Full court, Changing rooms', 'sample', 'sample-court-6');

INSERT INTO runs (run_id, court_id, creator_user_id, title, starts_at, max_players, skill_level, notes, status) VALUES
(1, 1, 2, 'Friday Night Buckets',         '2026-11-06 17:30:00', 10, 'intermediate', 'Bring a light and a dark shirt. We will split teams on arrival.', 'open'),
(2, 2, 3, 'Beginner Friendly Shootaround','2026-11-07 16:00:00',  8, 'beginner',     'No pressure, just fundamentals and fun.',                        'open'),
(3, 3, 4, 'Full Court, Full Energy',      '2026-11-08 18:00:00', 12, 'competitive',  'Full-court games. Please arrive ten minutes early.',             'open'),
(4, 5, 5, 'Sunday Park Run',              '2026-11-08 10:00:00', 10, 'intermediate', 'Outdoor run. Check conditions before travelling.',               'open'),
(5, 6, 2, 'Last Week Run',                '2026-10-24 17:00:00', 10, 'all',          'Finished sample run, kept for history.',                         'completed');

INSERT INTO run_members (run_id, user_id, membership_status) VALUES
(1, 2, 'joined'), (1, 3, 'joined'), (1, 4, 'joined'),
(2, 3, 'joined'), (2, 5, 'joined'),
(3, 4, 'joined'), (3, 2, 'joined'), (3, 5, 'waitlisted'),
(4, 5, 'joined'), (4, 3, 'cancelled'),
(5, 2, 'joined'), (5, 4, 'joined');

INSERT INTO saved_courts (user_id, court_id) VALUES
(2, 1), (2, 3), (3, 2), (3, 1), (4, 3), (5, 5), (5, 6);

INSERT INTO reviews (user_id, court_id, rating, comment) VALUES
(2, 1, 5, 'Great lights and a central location.'),
(3, 1, 4, 'Busy on Fridays but worth it.'),
(3, 2, 4, 'Relaxed court for casual games.'),
(4, 3, 5, 'Best floor in the region.'),
(5, 5, 4, 'Nice park setting, benches in good shape.'),
(2, 6, 3, 'Good gym, a bit crowded in the evening.');
