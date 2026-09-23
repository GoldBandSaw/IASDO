ALTER TABLE public.users
  ADD COLUMN IF NOT EXISTS role TEXT NOT NULL DEFAULT 'student';

ALTER TABLE public.users
  DROP CONSTRAINT IF EXISTS users_fixed_username;

ALTER TABLE public.users
  ADD CONSTRAINT users_fixed_username CHECK (
    username IN ('admin', 'antonin', 'lucas', 'aymen', 'youssef', 'maelle', 'jason', 'nolann', 'leon', 'roman', 'cedric')
  );
