CREATE TABLE IF NOT EXISTS public.resource_reports (
  id BIGSERIAL PRIMARY KEY,
  resource_id TEXT NOT NULL,
  reporter TEXT NOT NULL REFERENCES public.users(username) ON DELETE CASCADE,
  reason TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'open',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (resource_id, reporter)
);

ALTER TABLE public.resource_reports ENABLE ROW LEVEL SECURITY;
