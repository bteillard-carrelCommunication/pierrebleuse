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
la base de référence. Il s'écrit sur le poste de référence, une fois ses migrations passées :

```sh
php pupitre/sql/mise-a-jour.php            # écrit mise-a-jour-base/pupitre-mise-a-jour-AAAAMMJJ.sql
php pupitre/sql/mise-a-jour.php --voir     # dit ce qu'il écrirait, table par table, sans rien écrire
```

Le script part dans git : il ne porte ni compte, ni lien personnel, ni adresse de correspondant.

| Tables | Sur le poste d'arrivée |
|---|---|
| les comptes : `comptes`, `liens_connexion`, `tentatives_connexion` | **gardées** : on y garde son compte et son mot de passe. Créées, vides, si elles manquent ; une colonne ajoutée depuis par une migration leur est posée (ainsi `comptes.preferences`) |
| ce qui tient à un poste ou à des personnes : `taches`, `apercus`, `apercus_validations`, `depots`, `depots_fichiers`, `courriers`, `lettre_inscrits` | **vidées** : la structure arrive, pas le contenu |
| toutes les autres | **remplacées**, structure et contenu : ce qui y a été saisi sur le poste d'arrivée est perdu |

Les réglages qui ne disent que « quand ce poste a fait ceci pour la dernière fois » (travaux de nuit,
relevés) ne voyagent pas. Le journal voyage : depuis le 28/11/2026, Pupitre n'y écrit plus l'adresse
d'un correspondant que masquée (`c…@domaine`).

Sur l'autre poste, l'en-tête du script le redit :

```sh
git pull
php pupitre/sql/restaurer.php mise-a-jour-base/pupitre-mise-a-jour-AAAAMMJJ.sql --oui
php pupitre/sql/migrer.php --etat
```

La base d'avant est d'abord sauvegardée dans `pupitre/stockage/sauvegardes/`. Sans ligne de commande,
le fichier s'importe tel quel avec phpMyAdmin (MySQL 8). Le script peut être rejoué. Après lui,
« Le site » doit dire « aucun écart » : la base et les fichiers du site viennent du même commit.
L'historique des publications arrive sans ses fichiers de retour en arrière
(`stockage/publications/`, hors git) : « Revenir à la publication précédente » ne vaut qu'à partir de
la première publication faite sur ce poste. Les fichiers de la médiathèque (`stockage/medias/`, hors
git) ne voyagent pas non plus : ceux qui sont publiés sont déjà dans `assets/`.

Un seul script suffit : chaque nouveau script remplace le précédent, qui peut être retiré du dépôt.

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
