# CampusFlow

## Première installation

Le portail est fermé : les dix identifiants de la promo sont définis dans `db.php` et aucun autre compte ne peut être créé depuis le site. Après avoir démarré le serveur PHP, génère les dix liens de première connexion une seule fois :

```sh
php provision.php http://localhost:8000
```

Transmets chaque lien uniquement à la personne concernée. Le lien permet de choisir un mot de passe d'au moins 10 caractères avec une lettre et un chiffre, puis devient inutilisable. Les mots de passe sont stockés uniquement sous forme de hash. Une session reste active pendant 7 jours sur l'appareil avant de demander une nouvelle connexion.