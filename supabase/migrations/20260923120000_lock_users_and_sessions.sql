DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'users_fixed_username'
      AND conrelid = 'public.users'::regclass
  ) THEN
    ALTER TABLE public.users
      ADD CONSTRAINT users_fixed_username CHECK (
        username IN ('antonin', 'lucas', 'aymen', 'youssef', 'maelle', 'jason', 'nolann', 'leon', 'roman', 'cedric')
      );
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.sessions (
  id TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  last_activity BIGINT NOT NULL
);

ALTER TABLE public.sessions ENABLE ROW LEVEL SECURITY;
