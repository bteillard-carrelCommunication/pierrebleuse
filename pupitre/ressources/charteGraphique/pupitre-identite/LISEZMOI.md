# Pupitre, pack d'identité visuelle

Version 1.0, novembre 2026. Logo Le Point d'orgue, palette Mandarine, typographies Outfit et Manrope.

## Contenu

| Dossier | Contenu |
|---|---|
| `charte/` | La charte graphique en PDF, 11 pages. |
| `logo/svg/` | Logotype vectoriel, cinq versions : principal, inverse, marine, blanc, noir. |
| `logo/png/` | Les mêmes en PNG transparent, 600, 1200 et 2400 px de large. |
| `logo/pdf/` | Les mêmes en PDF vectoriel, pour l'impression. |
| `symbole/` | Le point d'orgue seul, quatre couleurs, en SVG et PNG. |
| `icones/` | Icône d'application (arrondie et carrée), favicon `.ico` et `.svg`, icône Apple, icônes 192 et 512, manifeste web. |
| `mailing/` | Logos PNG pour les mails, 280 et 560 px de large. |
| `reseaux/` | Avatar 1080 px et images de partage 1200 x 630 px, trois fonds. |
| `polices/` | Outfit et Manrope en `.woff2`, feuille `fonts.css`, licences. |
| `claude-code/` | Guide de design, variables CSS, tokens JSON, composants de référence. |
| `apercus/` | Captures de la zone d'admin, du mail et de la palette. |

## Quel logo utiliser

| Fond | Fichier |
|---|---|
| Clair (lin, blanc) | `pupitre-logo-principal` |
| Marine ou sombre | `pupitre-logo-inverse` |
| Mandarine ou abricot | `pupitre-logo-marine` |
| Couleur foncée, photo sombre | `pupitre-logo-blanc` |
| Impression une couleur | `pupitre-logo-noir` |

Sous 70 px de large, utiliser le symbole ou l'icône.

## Couleurs

| Nom | Hex |
|---|---|
| Marine | #12263F |
| Lin | #FAF6F0 |
| Mandarine | #FF8B3D |
| Cuivre | #B84E0C |
| Abricot | #FFE4CC |
| Ardoise | #586273 |

Jamais de texte blanc sur mandarine : le texte y est marine.

## Pour Claude Code

Copier le dossier `pupitre-identite` dans le dépôt, puis donner à Claude Code la consigne qui figure à la fin de `claude-code/PUPITRE-DESIGN.md`.

## Polices

Outfit et Manrope sont publiées sous licence SIL Open Font License 1.1, qui autorise l'hébergement sur nos serveurs. Les textes de licence sont dans `polices/`.
