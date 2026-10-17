-- ============================================================
-- Student Note Sharing Portal — MySQL database file
-- Schema + seed data in one file (submission deliverable).
--
-- Import: phpMyAdmin (XAMPP/MAMP) > Import, or CLI:
--   mysql -u root < note_portal.sql
--
-- Seeded demo logins (all from institution #1, Westfield College):
--   admin@westfield.edu      admin123       role: admin
--   moderator@westfield.edu  moderator123   role: moderator
--   student@westfield.edu    student123     role: student
--
-- Passwords are stored as password_hash() bcrypt strings — never plaintext.
-- ============================================================

CREATE DATABASE IF NOT EXISTS note_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE note_portal;

-- Drop in reverse dependency order so the file can be re-imported safely.
DROP VIEW   IF EXISTS transparency_log;
DROP TABLE  IF EXISTS bookmarks, moderation_actions, reports, note_tags,
                     tags, notes, subjects, courses, users, institutions;

CREATE TABLE institutions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL UNIQUE,
  email_domain VARCHAR(150),
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- No password-based auth decision is open: email + password (Accepted).
-- Passwords live here as password_hash() output only (T-01).
CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id  INT UNSIGNED NOT NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(100) NOT NULL,
  role            ENUM('student','moderator','admin') NOT NULL DEFAULT 'student',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (institution_id) REFERENCES institutions(id)
) ENGINE=InnoDB;

CREATE TABLE courses (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  name           VARCHAR(150) NOT NULL,
  UNIQUE KEY courses_inst_name_uq (institution_id, name),
  FOREIGN KEY (institution_id) REFERENCES institutions(id)
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  name      VARCHAR(150) NOT NULL,
  UNIQUE KEY subjects_course_name_uq (course_id, name),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- file_ref is a server-generated random name inside uploads/ — never a
-- public URL and never user-controlled input (T-08: no path traversal).
CREATE TABLE notes (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uploader_id        INT UNSIGNED NOT NULL,
  subject_id         INT UNSIGNED NOT NULL,
  title              VARCHAR(200) NOT NULL,
  description        TEXT,
  status             ENUM('draft','pending','published','rejected','removed')
                       NOT NULL DEFAULT 'pending',
  file_ref           VARCHAR(64) NOT NULL,
  original_name      VARCHAR(255) NOT NULL,
  mime               VARCHAR(100) NOT NULL,
  size_bytes         INT UNSIGNED NOT NULL,
  ownership_attested TINYINT(1) NOT NULL,
  view_count         INT UNSIGNED NOT NULL DEFAULT 0,
  download_count     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at       TIMESTAMP NULL,
  FOREIGN KEY (uploader_id) REFERENCES users(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id),
  INDEX notes_status_created_idx (status, created_at),
  INDEX notes_subject_idx (subject_id),
  FULLTEXT KEY notes_search_idx (title, description),
  CONSTRAINT notes_title_len CHECK (CHAR_LENGTH(title) BETWEEN 3 AND 200),
  CONSTRAINT notes_attested CHECK (ownership_attested = 1)
) ENGINE=InnoDB;

CREATE TABLE tags (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE note_tags (
  note_id INT UNSIGNED NOT NULL,
  tag_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (note_id, tag_id),
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reporter identity is never exposed to anyone except admin debugging
-- (T-10). reason values mirror moderation reason categories.
CREATE TABLE reports (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note_id     INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason      VARCHAR(20) NOT NULL
              CHECK (reason IN ('copyright','low_quality','duplicate',
                                'off_topic','abusive','privacy','other')),
  details     VARCHAR(1000),
  status      ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (note_id)     REFERENCES notes(id)  ON DELETE CASCADE,
  FOREIGN KEY (reporter_id) REFERENCES users(id),
  UNIQUE KEY reports_note_reporter_uq (note_id, reporter_id),
  INDEX reports_status_idx (status)
) ENGINE=InnoDB;

-- Append-only audit log (FR-11): UPDATE and DELETE are blocked by triggers.
CREATE TABLE moderation_actions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note_id         INT UNSIGNED NOT NULL,
  actor_id        INT UNSIGNED NOT NULL,
  action          ENUM('approve','reject','remove','restore','takedown') NOT NULL,
  reason_category VARCHAR(20)
                  CHECK (reason_category IN ('copyright','low_quality','duplicate',
                        'off_topic','abusive','privacy','other')),
  internal_note   VARCHAR(1000),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (note_id)  REFERENCES notes(id),
  FOREIGN KEY (actor_id) REFERENCES users(id),
  INDEX moderation_actions_note_idx (note_id),
  INDEX moderation_actions_created_idx (created_at)
) ENGINE=InnoDB;

DELIMITER //
CREATE TRIGGER moderation_actions_no_update BEFORE UPDATE ON moderation_actions
  FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'moderation_actions is append-only'; END //
CREATE TRIGGER moderation_actions_no_delete BEFORE DELETE ON moderation_actions
  FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'moderation_actions is append-only'; END //
DELIMITER ;

-- Transparency view (D-P01, option A — redacted): actor identity,
-- internal notes and reporter identity are intentionally excluded.
CREATE VIEW transparency_log AS
  SELECT m.id, m.note_id, n.title AS note_title, m.action,
         m.reason_category, m.created_at, u.role AS actor_role
  FROM moderation_actions m
  JOIN users u ON u.id = m.actor_id
  JOIN notes n ON n.id = m.note_id;

CREATE TABLE bookmarks (
  user_id    INT UNSIGNED NOT NULL,
  note_id    INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, note_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  INDEX bookmarks_note_idx (note_id)
) ENGINE=InnoDB;

-- ============================================================
-- Seed data — one college (D-P06: Option "One college").
-- ============================================================

INSERT INTO institutions (id, name, email_domain) VALUES
  (1, 'Westfield College', 'westfield.edu');

-- password_hash('…123', PASSWORD_DEFAULT) values generated by PHP 8.5.
INSERT INTO users (id, institution_id, email, password_hash, display_name, role) VALUES
  (1, 1, 'admin@westfield.edu',
   '$2y$12$LM7xIZzasWMNXepA7zShZerLvIRQZEcdKA/0G1IqhgZMFSPdsH6pq', 'Ada Admin', 'admin'),
  (2, 1, 'moderator@westfield.edu',
   '$2y$12$hesDnNLibL15wgaFUywT/uZ2o0QURHjpPN3DJdr6wvApEG41RQM0u', 'Mia Moderator', 'moderator'),
  (3, 1, 'student@westfield.edu',
   '$2y$12$rfNqO2GCi/omFMvT8.UFUeeusqM5huOCIWoiU6h4U.0eYVqJVfLIe', 'Sam Student', 'student');

INSERT INTO courses (id, institution_id, name) VALUES
  (1, 1, 'Computer Science'),
  (2, 1, 'Information Technology'),
  (3, 1, 'Business Studies');

INSERT INTO subjects (id, course_id, name) VALUES
  (1, 1, 'Programming Fundamentals'),
  (2, 1, 'Data Structures & Algorithms'),
  (3, 1, 'Databases'),
  (4, 2, 'Web Development'),
  (5, 2, 'Computer Networks'),
  (6, 3, 'Marketing Basics');

INSERT INTO tags (id, name) VALUES
  (1, 'java'), (2, 'exam'), (3, 'sql'), (4, 'html'), (5, 'css'),
  (6, 'networks'), (7, 'dsa'), (8, 'python'), (9, 'marketing');

-- file_ref values match sample files shipped in uploads/ so the demo
-- works before any real upload exists.
INSERT INTO notes (id, uploader_id, subject_id, title, description, status,
                   file_ref, original_name, mime, size_bytes, ownership_attested,
                   view_count, download_count, created_at, published_at) VALUES
  (1,  3, 1, 'Java Basics — Sem 1 Lecture Summary',
      'Variables, loops, arrays and OOP intro from Lectures 1–6, own words.', 'published',
      'sample-java-notes.pdf', 'Java Basics Lecture Summary.pdf', 'application/pdf', 18432, 1, 23, 9,
      NOW() - INTERVAL 63 DAY, NOW() - INTERVAL 62 DAY),
  (2,  3, 2, 'Sorting Algorithms Cheat Sheet',
      'Bubble, merge, quick and insertion sort with complexity table.', 'published',
      'sample-sorting.pdf', 'Sorting Cheat Sheet.pdf', 'application/pdf', 15360, 1, 41, 22,
      NOW() - INTERVAL 56 DAY, NOW() - INTERVAL 55 DAY),
  (3,  3, 3, 'SQL Joins Explained with Examples',
      'INNER, LEFT, RIGHT and FULL joins with sample tables and results.', 'published',
      'sample-sql-joins.pdf', 'SQL Joins Explained.pdf', 'application/pdf', 20480, 1, 15, 4,
      NOW() - INTERVAL 42 DAY, NOW() - INTERVAL 41 DAY),
  (4,  3, 4, 'HTML Forms & CSS Grid Practical Notes',
      'Every input type and a grid layout exercise from the lab.', 'published',
      'sample-diagram.png', 'HTML Forms Diagram.png', 'image/png', 1024, 1, 30, 12,
      NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 34 DAY),
  (5,  2, 5, 'OSI Model Diagrams & Port Numbers',
      'Seven layers drawn from memory plus the port numbers that come up in the exam.', 'published',
      'sample-osi.pdf', 'OSI Model Notes.pdf', 'application/pdf', 17408, 1, 7, 3,
      NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 27 DAY),
  (6,  2, 6, 'Marketing 4Ps Class Notes',
      'Product, price, place, promotion with class discussion examples.', 'published',
      'sample-4ps.pdf', 'Marketing 4Ps Notes.pdf', 'application/pdf', 13312, 1, 18, 5,
      NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 13 DAY),
  (7,  3, 2, 'Binary Search Trees — Lecture 5 Notes',
      'Insert, delete and traversals with hand-traced diagrams.', 'pending',
      'sample-bst.pdf', 'BST Lecture 5.pdf', 'application/pdf', 11264, 1, 0, 0,
      NOW() - INTERVAL 2 DAY, NULL),
  (8,  2, 4, 'JavaScript Events Cheat Sheet',
      'addEventListener patterns and bubbling demo code.', 'pending',
      'sample-js-events.pdf', 'JS Events Cheat Sheet.pdf', 'application/pdf', 12800, 1, 0, 0,
      NOW() - INTERVAL 1 DAY, NULL),
  (9,  3, 1, 'Exam Predictions 2026 (Unverified)',
      'Compilation of guesses about what will appear in the exam.', 'rejected',
      'sample-predictions.pdf', 'Exam Predictions.pdf', 'application/pdf', 9216, 1, 2, 1,
      NOW() - INTERVAL 20 DAY, NULL),
  (10, 3, 3, 'ER Diagrams — Draft Work in Progress',
      'First pass of the ER diagram assignment; not finished yet.', 'draft',
      'sample-er.pdf', 'ER Diagrams Draft.pdf', 'application/pdf', 8192, 1, 0, 0,
      NOW() - INTERVAL 5 DAY, NULL),
  (11, 3, 1, 'Copy of Textbook Chapter 4',
      'Scanned chapter from the official book.', 'removed',
      'sample-textbook.pdf', 'Textbook Chapter 4.pdf', 'application/pdf', 40960, 1, 33, 14,
      NOW() - INTERVAL 30 DAY, NULL),
  (12, 2, 5, 'Subnetting Practice Problems',
      'Twenty subnetting exercises with worked answers.', 'published',
      'sample-subnetting.pdf', 'Subnetting Practice.pdf', 'application/pdf', 21504, 1, 51, 27,
      NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 6 DAY);

INSERT INTO note_tags (note_id, tag_id) VALUES
  (1, 1), (1, 2), (2, 7), (2, 2), (3, 3), (4, 4), (4, 5), (5, 6), (6, 9),
  (7, 7), (8, 4), (12, 6), (12, 2);

-- Moderation history feeds the audit log, transparency view (D-P01)
-- and admin dashboard. Append-only: never edited later (FR-11).
INSERT INTO moderation_actions (note_id, actor_id, action, reason_category,
                                internal_note, created_at) VALUES
  (1,  2, 'approve', NULL, NULL, NOW() - INTERVAL 62 DAY),
  (2,  2, 'approve', NULL, NULL, NOW() - INTERVAL 55 DAY),
  (12, 2, 'approve', NULL, NULL, NOW() - INTERVAL 6 DAY),
  (9,  2, 'reject',  'low_quality',
      'Note is speculation; content standards say no unverified exam claims.',
      NOW() - INTERVAL 19 DAY),
  (11, 1, 'remove',  'copyright',
      'Publisher complaint reference #4412. Takedown recorded.', NOW() - INTERVAL 24 DAY),
  (3,  1, 'restore', NULL,
      'Report on note 3 was dismissal pending review; note stays.', NOW() - INTERVAL 10 DAY);

INSERT INTO reports (note_id, reporter_id, reason, details, status, created_at) VALUES
  (3,  2, 'abusive',   'Sample row so the review page has a report to work through.', 'open',  NOW() - INTERVAL 11 DAY),
  (11, 2, 'copyright', 'Uploader does not own the textbook; resolved via takedown.',  'resolved', NOW() - INTERVAL 25 DAY);

INSERT INTO bookmarks (user_id, note_id, created_at) VALUES
  (3, 2, NOW() - INTERVAL 50 DAY),
  (3, 12, NOW() - INTERVAL 6 DAY);
