# Pupitre : installer la base

Deux scripts, à lancer dans l'ordre :

1. `1-base-et-utilisateur.sql` : crée la base `pupitre_pierre-bleuse` et l'utilisateur `pupitre`.
   À lancer en root. Remplacer d'abord `MOT_DE_PASSE_A_CHOISIR`, puis reporter les identifiants
   dans `pupitre/.env`.
2. `2-structure.sql` : les 37 tables, exportées du MySQL local le 22/11/2026, sans aucune donnée.

La voie normale reste `php pupitre/sql/depart.php` après l'étape 1 : il applique
`sql/schema.sql` (même structure) puis reprend les contenus du site. `2-structure.sql` sert quand
`depart.php` ne peut pas tourner. Dans ce cas la reprise des contenus reste à faire.

Les deux scripts sont rejouables et ne détruisent rien.

Exemple en ligne de commande :

```sh
mysql -h HOTE -P PORT -u root -p < pupitre/sql/installation/1-base-et-utilisateur.sql
mysql -h HOTE -P PORT -u pupitre -p pupitre_pierre-bleuse < pupitre/sql/installation/2-structure.sql
```
