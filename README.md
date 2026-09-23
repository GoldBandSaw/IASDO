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

### 5. Créer les dix liens de première connexion

Les dix comptes existent déjà. Personne ne peut créer un onzième compte.

Sur ton ordinateur, ouvre PowerShell dans le dossier du projet et écris :

```powershell
$env:DATABASE_URL="COLLE ICI LA LONGUE LIGNE SUPABASE"
php provision.php "https://campusflow-xxxx.onrender.com"
```

Remplace `COLLE ICI LA LONGUE LIGNE SUPABASE` et l'adresse Render par les vraies valeurs.

Le programme affiche dix liens. Copie chaque lien et envoie-le uniquement à la bonne personne. Chaque lien ne fonctionne qu'une seule fois.

### 6. Première connexion d'un étudiant

1. L'étudiant clique sur son lien.
2. Il choisit un mot de passe d'au moins 10 caractères avec une lettre et un chiffre.
3. Il clique sur **Activer mon compte**.
4. Il ouvre ensuite l'adresse Render.
5. Il écrit son identifiant et son mot de passe.

Les identifiants sont :

`antonin`, `lucas`, `aymen`, `youssef`, `maelle`, `jason`, `nolann`, `leon`, `roman`, `cedric`

Un étudiant peut changer son nom affiché et son mot de passe dans **Paramètres**. Son identifiant ne change jamais.

### Important

- Les données sont conservées dans Supabase même si Render redémarre.
- Le site est fermé : il faut être connecté pour voir les pages de la classe.
- Les sessions durent sept jours sur un appareil.
- Les notes et ressources sont communes à la classe.
- Le mot de passe Supabase est secret : ne le partage jamais avec la classe.
