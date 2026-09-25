# Original User Request

## 2026-09-25T09:05:46Z

# Teamwork Project Prompt — Final

Application d'une série de 5 correctifs UI/UX, d'optimisations de requêtes et d'ajout de fonctionnalités (upload multiple) sur le projet existant.

Working directory: c:\Users\anton\Projets\IASDO
Integrity mode: development
Requested team: [full team]

## Requirements

### R1. UI Globale : Fixer la barre latérale droite
La barre latérale droite doit être "sticky" ou "fixed" sur desktop, avec son propre scroll si nécessaire, pour rester accessible après un défilement vers le bas dans le contenu principal.

### R2. Performances : Ressources récentes
Remplacer la requête des ressources récentes par celle de la page "Ressources" (mêmes filtres, jointures légères). Appliquer un tri par date décroissante et une limite (ex: 5 ou 10) pour optimiser le temps de chargement.

### R3. Emploi du temps : Correctifs d'affichage (CSS Grid)
Corriger le bloc (ex: événement du dimanche) qui s'étale horizontalement et casse la grille. La grille doit tenir dans un conteneur fixe sans déborder (pas de scroll horizontal). Ajuster le padding/alignement pour que les heures "06h" et "07h" en haut ne soient pas rognées.

### R4. Interactions Emploi du temps : Modales au clic
- Clic sur une carte de cours : ouvrir une modale listant les ressources associées (réutiliser la requête de la page Ressources).
- Clic sur une tâche : ouvrir la modale d'édition (`Edit Task`) pré-remplie avec les informations actuelles.

### R5. Ressources : Upload multiple de fichiers
Permettre l'upload simultané jusqu'à 10 fichiers dans le formulaire d'ajout de ressources. Le traitement vers le stockage cloud doit se faire fichier par fichier (ou Promise.all maîtrisée) pour éviter les timeouts, sans altérer l'upload unitaire existant.

## Verification Resources
L'équipe doit lancer et utiliser le serveur de développement local (ex: `npm run dev` ou équivalent) pour vérifier visuellement le rendu (grille CSS, barre sticky, modales) et tester fonctionnellement l'upload de multiples fichiers.

## Acceptance Criteria

### UI et Layout
- [ ] La barre latérale droite reste visible lors du scroll vers le bas sur desktop.
- [ ] La grille de l'emploi du temps s'affiche sans barre de défilement horizontale.
- [ ] Les graduations "06h" et "07h" sont intégralement visibles.
- [ ] Les tâches/événements restent confinés à leur colonne respective.

### Fonctionnalités
- [ ] Le clic sur un cours affiche une modale avec ses ressources.
- [ ] Le clic sur une tâche ouvre le formulaire de modification pré-rempli.
- [ ] Le champ d'upload accepte la sélection multiple (jusqu'à 10 fichiers).
- [ ] L'upload s'effectue correctement pour chaque fichier.

### Performances
- [ ] La section "Ressources récentes" charge les données rapidement sans ralentir la page.
