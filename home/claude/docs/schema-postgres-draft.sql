-- Student Note Sharing Portal: PROPOSED schema v0 (Postgres on Neon, D-005)
-- Status: Proposed. Run on the `dev` branch only until you approve it for `main`.
-- Mirrors backend-schema.md. No passwords are stored here: the auth method is still Open (D-P03).

CREATE TABLE institutions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL UNIQUE,
  email_domain text,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institution_id uuid REFERENCES institutions(id),
  email text NOT NULL UNIQUE,
  display_name text NOT NULL,
  role text NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'moderator', 'admin')),
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE courses (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institution_id uuid REFERENCES institutions(id),
  name text NOT NULL,
  UNIQUE (institution_id, name)
);

CREATE TABLE subjects (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  name text NOT NULL,
  UNIQUE (course_id, name)
);

CREATE TABLE notes (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  uploader_id uuid NOT NULL REFERENCES users(id),
  subject_id uuid NOT NULL REFERENCES subjects(id),
  title text NOT NULL CHECK (char_length(title) BETWEEN 3 AND 200),
  description text,
  status text NOT NULL DEFAULT 'pending'
    CHECK (status IN ('draft', 'pending', 'published', 'rejected', 'removed')),
  file_ref text NOT NULL,                       -- storage key, never a public URL (T-08)
  ownership_attested boolean NOT NULL CHECK (ownership_attested),
  created_at timestamptz NOT NULL DEFAULT now(),
  published_at timestamptz,
  search tsvector GENERATED ALWAYS AS (
    to_tsvector('english', coalesce(title, '') || ' ' || coalesce(description, ''))
  ) STORED
);

CREATE TABLE tags (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL UNIQUE
);

CREATE TABLE note_tags (
  note_id uuid NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
  tag_id uuid NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
  PRIMARY KEY (note_id, tag_id)
);

CREATE TABLE reports (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  note_id uuid NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
  reporter_id uuid NOT NULL REFERENCES users(id),   -- restricted: never exposed (T-10)
  reason text NOT NULL,
  details text,
  status text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'resolved', 'dismissed')),
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (note_id, reporter_id)
);

CREATE TABLE moderation_actions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  note_id uuid NOT NULL REFERENCES notes(id),
  actor_id uuid NOT NULL REFERENCES users(id),
  action text NOT NULL CHECK (action IN ('approve', 'reject', 'remove', 'restore', 'takedown')),
  reason_category text CHECK (reason_category IN
    ('copyright', 'low_quality', 'duplicate', 'off_topic', 'abusive', 'privacy', 'other')),
  internal_note text,                                -- admin-only
  created_at timestamptz NOT NULL DEFAULT now()
);

-- Audit log is append-only (FR-11)
CREATE FUNCTION forbid_change() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  RAISE EXCEPTION 'moderation_actions is append-only';
END
$$;

CREATE TRIGGER moderation_actions_append_only
  BEFORE UPDATE OR DELETE ON moderation_actions
  FOR EACH ROW EXECUTE FUNCTION forbid_change();

-- Transparency view (D-P01, option A): allow-listed fields only.
-- Excludes actor identity, internal notes, reporter identity (T-10, T-11).
CREATE VIEW transparency_log AS
  SELECT m.id, m.note_id, m.action, m.reason_category, m.created_at, u.role AS actor_role
  FROM moderation_actions m
  JOIN users u ON u.id = m.actor_id;

CREATE INDEX notes_status_created_idx ON notes (status, created_at DESC);
CREATE INDEX notes_subject_idx ON notes (subject_id);
CREATE INDEX notes_search_idx ON notes USING GIN (search);
CREATE INDEX reports_status_idx ON reports (status);
CREATE INDEX moderation_actions_note_idx ON moderation_actions (note_id);