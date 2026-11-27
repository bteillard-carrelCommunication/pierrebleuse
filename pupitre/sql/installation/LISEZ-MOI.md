# Pupitre : installer la base

## Voie normale : restaurer une sauvegarde

Depuis l'intervention du 27/11/2026, la base se construit par des migrations numérotées
(`sql/migrations/`), qui posent aussi des données sans lesquelles Pupitre ne fonctionne pas : les
langues, les blocs du site, les libellés de l'interface. Une installation se fait donc à partir
d'une sauvegarde complète de la base de référence :

1. `1-base-et-utilisateur.sql` : crée la base `pupitre_pierre-bleuse` et l'utilisateur `pupitre`.
   À lancer en root. Remplacer d'abord `MOT_DE_PASSE_A_CHOISIR`, puis reporter les identifiants
   dans `pupitre/.env`. Sur un hébergement mutualisé, la base et son utilisateur se créent depuis
   le panneau de l'hébergeur : cette étape est alors sans objet.
2. Sur le poste de référence : `php pupitre/sql/sauvegarder.php installation`. Le fichier écrit dans
   `pupitre/stockage/sauvegardes/` est du SQL ordinaire (une instruction par ligne).
3. Sur le serveur : `php pupitre/sql/restaurer.php <fichier> --oui`, ou, sans ligne de commande,
   l'import de ce fichier par l'outil de l'hébergeur (phpMyAdmin). La sauvegarde porte la table
   `migrations` : Pupitre sait où il en est, et `php pupitre/sql/migrer.php` n'a rien à refaire.

Une sauvegarde contient **tout** : les contenus, mais aussi les comptes, le journal et les traces
des appels à l'IA. Après une restauration sur un autre serveur, revoir les comptes (écran Comptes)
et les réglages propres au serveur (écran Réglages : racine et adresse du site).

## Mettre à jour la base d'un autre poste de développement

Le dossier `mise-a-jour-base/`, à la racine du dépôt et interdit au web, porte un script SQL tiré de
la base de référence : toutes les tables, structure et contenu, **sauf les comptes** (`comptes`,
`liens_connexion`, `tentatives_connexion`), qui n'ont pas leur place dans git et restent ceux du
poste d'arrivée. Son en-tête dit ce qu'il remplace et comment l'appliquer :

```sh
git pull
php pupitre/sql/restaurer.php mise-a-jour-base/pupitre-mise-a-jour-AAAAMMJJ.sql --oui
php pupitre/sql/migrer.php --etat
```

Tout ce qui a été saisi dans Pupitre sur le poste d'arrivée est remplacé ; sa base d'avant est
sauvegardée dans `pupitre/stockage/sauvegardes/`. L'historique des publications arrive sans ses
fichiers de retour en arrière (`stockage/publications/`, hors git) : « Revenir à la publication
précédente » ne vaut qu'à partir de la première publication faite sur ce poste.

## Fichiers de référence

- `2-structure.sql` : la structure complète après les migrations, sans aucune donnée, écrite par
  `php pupitre/sql/structure.php` (à relancer après chaque nouvelle migration). Elle sert à relire
  ou à comparer une base ; elle ne suffit pas à installer Pupitre.
- `../schema.sql` : la structure de la version 1 (37 tables), sur laquelle les migrations 001 et
  suivantes s'appliquent. `php pupitre/sql/depart.php` l'emploie pour une reprise depuis un site
  d'avant l'intervention ; il refuse de tourner sur un site déjà géré par blocs.

Exemple en ligne de commande :

```sh
mysql -h HOTE -P PORT -u root -p < pupitre/sql/installation/1-base-et-utilisateur.sql
php pupitre/sql/restaurer.php pupitre/stockage/sauvegardes/pupitre-AAAAMMJJ-HHMMSS-installation.sql --oui
php pupitre/sql/migrer.php --etat
```
