# Pupitre · LISEZ-MOI

Pupitre pilote la communication de l'artiste et administre son site. Première version : PHP,
MySQL, HTML, CSS et JS écrits à la main, sans framework, sans Composer ni npm.

Ce fichier est complété étape par étape.

## Poste de développement (relevé du 12/11/2026)

| Outil | Version | Où |
|---|---|---|
| PHP servi par Apache | 8.3.14 | MAMP, `http://localhost:8888/` |
| PHP en ligne de commande | 8.3.14 et 8.4.1 | `/Applications/MAMP/bin/php/php8.3.14/bin/php` (absent du PATH) |
| MySQL | 8.0.40 | MAMP, port 8889 |
| Python | 3.15.2 | `/opt/homebrew/bin/python3` |
| ffmpeg | 8.0 | `/opt/homebrew/bin/ffmpeg` |
| Chrome | pour les tests | piloté par `outils/cdp.py` (Node n'est pas installé) |

## Témoin du rendu du site

`docs/pupitre/captures/reference/` garde l'état du site au 12/11/2026, avant que ses contenus ne
passent dans `data/` : captures de l'accueil, de `concerts.html` et de `presse.html` en français et
en anglais, DOM rendu et texte visible de chaque page.

```
python3 outils/temoin.py capturer /tmp/apres
python3 outils/temoin.py comparer docs/pupitre/captures/reference /tmp/apres
```
