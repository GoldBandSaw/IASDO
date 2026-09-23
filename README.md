# CampusFlow

## Publier le site (comme une recette)

Le site est déjà préparé pour **Render + Supabase**. Fais ces étapes dans l'ordre.

### 1. Ouvrir Supabase

1. Va sur [supabase.com](https://supabase.com).
2. Clique sur ton projet CampusFlow.
3. À gauche, clique sur **Project Settings** (la roue dentée).
4. Clique sur **Database**.
5. Cherche **Connection string**.
6. Choisis **URI**.
7. Copie toute la longue ligne qui commence par `postgresql://`.

Ne mets jamais cette ligne dans un fichier GitHub, un message public ou du code visible.

### 2. Ouvrir Render

1. Va sur [render.com](https://render.com).
2. Clique sur ton service CampusFlow.
3. Clique sur **Environment**.
4. Clique sur **Add Environment Variable**.
5. Dans **Name**, écris exactement :

   `DATABASE_URL`

6. Dans **Value**, colle la longue ligne copiée depuis Supabase.
7. Clique sur **Save Changes**.
8. Clique sur **Manual Deploy**.
9. Clique sur **Deploy latest commit**.

Attends que Render affiche **Live**. Si le premier chargement est lent, attends une minute : le serveur gratuit se réveille.

Dans **Environment**, ajoute également `ADMIN_PASSWORD` avec le mot de passe du compte `admin`, ainsi que `COMMON_CALENDAR_URL` avec le lien iCalendar commun de l'Université d'Orléans. Le compte admin est créé automatiquement au premier chargement et peut valider ou refuser les propositions depuis **Administration**.

### 3. Ouvrir Supabase et mettre le schéma en place

1. Dans Supabase, clique sur **SQL Editor**.
2. Clique sur **New query**.
3. Copie-colle le contenu de `supabase/migrations/20260923111500_create_campusflow_schema.sql`.
4. Clique sur **Run**.
5. Fais la même chose avec `supabase/migrations/20260923120000_lock_users_and_sessions.sql`.

Tu dois voir les tables `users`, `sessions`, `tasks`, `settings`, `courses`, `resources` et `proposals`.

### 4. Ouvrir le site

1. Dans Render, copie l'adresse qui ressemble à :

   `https://campusflow-xxxx.onrender.com`

2. Colle cette adresse dans ton navigateur.
3. Tu dois voir l'écran **Connexion**.

Si tu vois une erreur, retourne dans Render et vérifie que la variable s'appelle bien `DATABASE_URL`, sans espace.

### 5. Première connexion des dix étudiants

Les dix comptes sont créés automatiquement par le site au premier chargement. Aucun shell Render ni PHP local n'est nécessaire.

Les identifiants sont `antonin`, `lucas`, `aymen`, `youssef`, `maelle`, `jason`, `nolann`, `leon`, `roman` et `cedric`. Le mot de passe initial est `CampusFlow2026!` pour chacun. Chaque étudiant peut ensuite le remplacer dans **Paramètres**.

Un étudiant peut changer son nom affiché et son mot de passe dans **Paramètres**. Son identifiant ne change jamais.

### Important

- Les données sont conservées dans Supabase même si Render redémarre.
- Le site est fermé : il faut être connecté pour voir les pages de la classe.
- Les sessions durent sept jours sur un appareil.
- Les notes et ressources sont communes à la classe.
- Le mot de passe Supabase est secret : ne le partage jamais avec la classe.
