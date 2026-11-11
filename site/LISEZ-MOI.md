# Site Pierre Bleuse · LISEZ-MOI

Code principal du site, à partir du 12 novembre 2026. Point de départ : la page validée
`hypotheses/h4-2-nuit/` (la nuée d'étourneaux sur fond noir), copiée ici avec des chemins locaux.

Ouvrir : `http://localhost:8888/pierrebleuse/site/` (MAMP).

## Provenance

| Fichier | Provenance |
|---|---|
| `index.html` | copie de `hypotheses/h4-2-nuit/index.html` : chemins locaux, titre et description du site, `noindex`, `window.H4Variante = { nom: 'site', racine: './', fonds: ['noir'] }` (clé de réglages `h4-site-reglages-v3`) |
| `css/etourneaux.css`, `js/parametres.js`, `musique.js`, `sync.js`, `nuee.js`, `reglages.js`, `main.js`, `data/pathetique-iv-extrait.json` | moteur de la nuée : copies identiques, octet pour octet, de `hypotheses/h4-etourneaux/` au commit `a2f5a98` |
| `css/site.css`, `js/contenu.js`, `js/agenda.js` | fichiers du site, branchés dans `index.html` (les deux scripts avant le moteur), à écrire |
| `data/agenda.json` | source de l'agenda |
| `assets/img/bleuse-1` à `bleuse-6` | photographies en 992 px (copies de `assets/` à la racine du dépôt) |
| `assets/media/pathetique-iv-extrait.m4a` | bande-son de test |
| `assets/vol1_layerUp.png` à `vol3_layerUp.png` | références A/B du panneau de réglages |

La documentation du moteur reste `hypotheses/h4-etourneaux/LISEZ-MOI.md` et `SPEC.md`.
Les textes de la page sont dans `docs/site/CONTENU-SITE.md`.

## Point de départ vérifié (12/11/2026, Chrome headless)

`site/` et `hypotheses/h4-2-nuit/` comparés à 1440 × 900 et à 390 × 844 (tactile) : même rectangle
de la nuée, mêmes réglages, mêmes positions et mêmes opacités des blocs `[data-revele]` au
défilement, bouton Écouter et panneau de réglages identiques, 0 erreur en console.
