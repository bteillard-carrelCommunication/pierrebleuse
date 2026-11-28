# Pupitre, guide de design pour Claude Code

Version 1.0, novembre 2026. Ce fichier est la référence d'identité visuelle à suivre pour tout écran, mail ou composant de Pupitre. La charte complète est dans `charte/Pupitre-charte-graphique.pdf`.

## L'essentiel

Pupitre est une application de pilotage de la communication (personal branding, agenda, mailing). Elle prend la forme d'une zone d'admin. Le ton visuel est moderne, chaleureux et professionnel : un fond clair et chaud, une navigation marine, une seule couleur vive (mandarine) pour les actions et les repères.

Trois règles passent avant tout le reste :

1. Jamais de texte blanc sur mandarine. Sur mandarine, le texte est marine.
2. La mandarine sert aux actions et aux repères, pas au texte courant ni aux grands aplats de page.
3. Titres en Outfit, tout le reste en Manrope.

## Fichiers du pack

| Fichier | Usage |
|---|---|
| `claude-code/tokens.css` | Variables CSS : couleurs, typographie, rayons, espacements. À importer en premier. |
| `claude-code/composants.css` | Composants de référence (boutons, navigation, cartes, étiquettes, bandeau, champs). À adapter à la structure existante. |
| `claude-code/tokens.json` | Les mêmes valeurs en JSON, pour un build ou un autre framework. |
| `polices/fonts.css` + `.woff2` | Outfit et Manrope auto-hébergées, licence SIL OFL 1.1. |
| `logo/svg/` | Logotype vectoriel, cinq versions. |
| `symbole/svg/` | Point d'orgue seul. |
| `icones/` | Favicon, icônes d'application, `site.webmanifest`. |
| `mailing/` | Logos PNG pour les mails. |
| `reseaux/` | Avatar et images de partage. |

Emplacement conseillé dans le dépôt, à adapter : `assets/brand/` pour logos, symboles et icônes, `assets/fonts/` pour les polices, `assets/css/` pour les deux feuilles de style.

## Couleurs

| Nom | Hex | Variable | Rôle |
|---|---|---|---|
| Marine | `#12263F` | `--pp-marine` | Textes, navigation, fonds sombres |
| Lin | `#FAF6F0` | `--pp-lin` | Fond principal des pages |
| Blanc | `#FFFFFF` | `--pp-blanc` | Cartes, champs |
| Mandarine | `#FF8B3D` | `--pp-mandarine` | Bouton principal, entrée de navigation active, repères |
| Cuivre | `#B84E0C` | `--pp-cuivre` | Liens, accents, signe du logo sur fond clair |
| Abricot | `#FFE4CC` | `--pp-abricot` | Aplats, survols, bandeaux |
| Ardoise | `#586273` | `--pp-ardoise` | Textes secondaires |
| Trait | `#EAE3D9` | `--pp-trait` | Bordures, séparateurs |
| Brume | `#B9C4D4` | `--pp-brume` | Texte secondaire sur marine |

Utiliser les variables de rôle (`--color-bg`, `--color-text`, `--color-primary`, etc.) dans les composants, pas les couleurs de marque en direct.

Répartition indicative d'un écran : lin 45 %, marine 25 %, mandarine 12 %, abricot 10 %, cuivre 8 %.

### Associations texte et fond

Autorisées (contraste AA, 4,5:1 ou plus) : marine sur lin, blanc, mandarine ou abricot ; lin ou brume sur marine ; blanc sur cuivre ; cuivre sur lin ou blanc ; ardoise sur lin ou blanc.

Interdites pour du texte : blanc sur mandarine (2,3:1), mandarine sur lin (2,2:1), cuivre sur abricot (4,2:1).

### Couleurs d'état

Proposition à valider, hors charte validée : succès `#17603D` sur `#DDF2E6`, alerte `#7A4F00` sur `#FFEFC2`, erreur `#8C1D18` sur `#FBE3E1`, info `#12263F` sur `#E3EAF3`. Ne jamais signaler un état par la couleur seule : ajouter un libellé ou une icône.

## Typographie

Charger les polices depuis `polices/fonts.css` (auto-hébergement). Éviter le CDN Google Fonts : l'appel transmet l'adresse IP du visiteur à Google, ce qui pose une question RGPD.

| Style | Police | Taille | Graisse | Interligne | Autre |
|---|---|---|---|---|---|
| Titre 1 | Outfit | 44 px | 600 | 1 | Interlettrage -0,02em |
| Titre 2 | Outfit | 28 px | 600 | 1,15 | Interlettrage -0,02em |
| Titre 3 | Outfit | 20 px | 600 | 1,25 | Interlettrage -0,02em |
| Texte | Manrope | 16 px | 400 | 1,5 | |
| Interface | Manrope | 15 px | 500 ou 600 | 1 | Boutons, navigation |
| Petit | Manrope | 13 px | 400 | 1,45 | Légendes, métadonnées |
| Libellé | Manrope | 13 px | 600 | 1,2 | Capitales, interlettrage 0,08em |

Dates, heures et chiffres en colonnes : `font-variant-numeric: tabular-nums`.

## Composants

**Bouton principal** : fond mandarine, texte marine, Manrope 600 15 px, hauteur 44 px, marges internes 0 20 px, forme pilule. Survol `#F57B2A`, pressé `#E86E1F`. Un seul bouton principal par zone.

**Bouton secondaire** : fond transparent, bordure 1 px marine, texte marine. Survol : fond abricot.

**Bouton sombre** : fond marine, texte lin. Réservé aux bandeaux abricot.

**Navigation latérale** : colonne de 220 px, fond marine, logo inverse en haut (34 px de haut). Entrées de 44 px de haut, rayon 22 px, texte brume en Manrope 500, icônes au trait de 18 px. Entrée active : fond mandarine, texte marine, graisse 600, attribut `aria-current="page"`.

**Carte** : fond blanc, bordure 1 px trait, rayon 20 px, sans ombre.

**Étiquette** : pilule blanche, bordure trait, pastille ronde de 10 px + libellé en Manrope 600 13 px. Pastilles : mandarine (réseaux sociaux), cuivre (presse), marine (mailing). Le libellé porte toujours l'information, la pastille seule ne suffit pas.

**Bandeau** : fond abricot, texte marine, rayon 20 px, bouton sombre à droite.

**Champ** : fond blanc, bordure trait, rayon 12 px, hauteur 44 px. Au focus : bordure marine.

**Focus clavier** : contour 2 px marine décalé de 2 px sur fond clair, mandarine sur fond marine. Ne jamais le supprimer.

Style général : aplats, pas de dégradé, pas d'ombre portée, coins très arrondis, icônes au trait. Pas d'emoji dans l'interface.

## Logo

| Contexte | Fichier |
|---|---|
| Fond clair (lin, blanc) | `logo/svg/pupitre-logo-principal.svg` |
| Fond marine ou sombre | `logo/svg/pupitre-logo-inverse.svg` |
| Fond mandarine ou abricot | `logo/svg/pupitre-logo-marine.svg` |
| Fond de couleur foncée ou photo sombre | `logo/svg/pupitre-logo-blanc.svg` |
| Impression une couleur | `logo/svg/pupitre-logo-noir.svg` |
| Espace réduit, moins de 70 px de large | `symbole/svg/` ou `icones/icone-app.svg` |

Le texte du logo est vectorisé : ne jamais le recomposer en police, toujours utiliser les fichiers. Garder les proportions (rapport largeur sur hauteur de 2,89). Zone de protection : la hauteur de la lettre u tout autour. Taille minimale : 70 px de large à l'écran. Texte alternatif : `Pupitre`.

Balises d'en-tête à placer dans le `<head>`, chemins à adapter :

```html
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#12263F">
<meta property="og:image" content="/og-1200x630-marine.png">
```

## Mailing

Les messageries n'appliquent ni variables CSS ni polices web de façon fiable. Pour les gabarits de mail :

- Structure en tableaux, largeur 600 px, styles en ligne, couleurs en hexadécimal.
- Police : `Arial, Helvetica, sans-serif` partout. Outfit et Manrope peuvent être déclarées en premier, elles ne s'afficheront que dans quelques clients.
- En-tête : bandeau marine `#12263F`, logo `mailing/logo-email-inverse.png` affiché à 140 px de large (fichier de 280 px pour les écrans haute densité), attributs `width` et `alt="Pupitre"`.
- Corps : fond lin `#FAF6F0` autour, carte blanche, titre marine en gras, texte secondaire ardoise `#586273`.
- Bouton : cellule de tableau à fond mandarine `#FF8B3D`, texte marine `#12263F` en gras 16 px, rayon 24 px. Outlook l'affichera à angles droits, c'est accepté.
- Liens : cuivre `#B84E0C`, soulignés.
- Images hébergées en HTTPS, jamais en pièce jointe ni en base64.

## Consigne à coller dans Claude Code

```text
Lis claude-code/PUPITRE-DESIGN.md, claude-code/tokens.css et claude-code/composants.css
dans le dossier pupitre-identite.

Applique l'identité Pupitre à la zone d'admin :
1. Copie les logos, icônes et polices dans les dossiers d'assets du projet et installe
   fonts.css, tokens.css puis composants.css dans la chaîne de styles existante.
2. Remplace les couleurs, polices, rayons et espacements codés en dur par les variables
   de tokens.css. N'introduis aucune couleur hors palette.
3. Mets à jour la navigation, les boutons, les cartes, les étiquettes et les champs selon
   la section Composants. Adapte les classes à la structure existante plutôt que de
   dupliquer du CSS.
4. Installe le favicon, les icônes et les balises du head.
5. Vérifie les trois règles de la section L'essentiel et le contraste des textes, puis
   liste les écrans modifiés et les points restés en suspens.

Ne recompose jamais le logo en texte. Les couleurs d'état sont une proposition :
signale où tu les utilises.
```

## À ajouter dans CLAUDE.md

```markdown
## Identité visuelle
Référence : docs/design/PUPITRE-DESIGN.md (chemin à adapter).
- Couleurs, polices, rayons et espacements : uniquement via les variables de tokens.css.
- Jamais de texte blanc sur mandarine (#FF8B3D) : le texte y est marine (#12263F).
- Titres en Outfit, texte et interface en Manrope, polices auto-hébergées.
- Logo : fichiers SVG fournis, jamais recomposé en texte.
- Mails : tableaux, styles en ligne, Arial, logos PNG du dossier mailing.
```
