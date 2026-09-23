CREATE TABLE IF NOT EXISTS tasks (
  id TEXT PRIMARY KEY,
  payload JSONB NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  payload JSONB NOT NULL
);

CREATE TABLE IF NOT EXISTS courses (
  name TEXT PRIMARY KEY
);

CREATE TABLE IF NOT EXISTS resources (
  id TEXT PRIMARY KEY,
  payload JSONB NOT NULL
);

CREATE TABLE IF NOT EXISTS proposals (
  id BIGSERIAL PRIMARY KEY,
  course TEXT NOT NULL,
  title TEXT NOT NULL,
  resource_type TEXT NOT NULL,
  url TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS users (
  username TEXT PRIMARY KEY,
  display_name TEXT NOT NULL,
  password_hash TEXT NOT NULL DEFAULT '',
  setup_token_hash TEXT NOT NULL DEFAULT '',
  setup_used BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT users_fixed_username CHECK (username IN ('antonin', 'lucas', 'aymen', 'youssef', 'maelle', 'jason', 'nolann', 'leon', 'roman', 'cedric'))
);

CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  last_activity BIGINT NOT NULL
);

INSERT INTO users (username, display_name, setup_token_hash) VALUES
  ('antonin', 'antonin', ''),
  ('lucas', 'lucas', ''),
  ('aymen', 'aymen', ''),
  ('youssef', 'youssef', ''),
  ('maelle', 'maelle', ''),
  ('jason', 'jason', ''),
  ('nolann', 'nolann', ''),
  ('leon', 'leon', ''),
  ('roman', 'roman', ''),
  ('cedric', 'cedric', '')
ON CONFLICT (username) DO NOTHING;

ALTER TABLE public.tasks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.courses ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.resources ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.proposals ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sessions ENABLE ROW LEVEL SECURITY;
