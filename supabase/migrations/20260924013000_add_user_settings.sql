CREATE TABLE IF NOT EXISTS public.user_settings (
  username TEXT PRIMARY KEY REFERENCES public.users(username) ON DELETE CASCADE,
  payload JSONB NOT NULL
);

ALTER TABLE public.user_settings ENABLE ROW LEVEL SECURITY;
