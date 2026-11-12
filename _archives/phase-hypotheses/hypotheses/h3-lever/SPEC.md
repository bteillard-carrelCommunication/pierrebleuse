# Hypothèse 3 — Lever, la lumière et le silence

**Pitch.** La retenue comme signe de stature. Page presque noire, photos dans la
pénombre : c'est **la lumière** qui révèle, jamais la découpe. Un seul fil
continu, **la trace de la baguette**, traverse toute la page et conduit l'œil
d'une section à l'autre. Le silence avant la première note.

**Ce que ça dit de l'excellence.** Les codes des maisons de très haut niveau :
sobriété, lumière, temps long. L'autorité de celui qui n'a pas besoin de montrer.
Les fragments inversés du DA existent toujours, mais **à l'intérieur de faisceaux
de lumière** (voir `../_ref/identite-v4-scene-bleue*.jpg` : les colonnes de
lumière bleue de la scène).

## Système

- **Lumière** : chaque photo est rendue par un shader (Pixi Mesh, comme
  `../../js/stripes-effect.js`) : `col = photo × (ambiant + spot + faisceaux)`.
  Ambiant 0,10 à 0,14 (la photo est devinée, pas vue). Le **spot** est une
  ellipse très douce (plume ≥ 40 % de la largeur) dont la position et le rayon
  sont fonction du scroll ; les **faisceaux** sont des bandes verticales à bords en
  dégradé (plume 25 à 40 % de leur largeur), jamais des rectangles. Température de
  la lumière = graduation chromatique : or chaud sur bleuse-1 (bokeh), bleu / cyan
  froid sur bleuse-3 (scène), crème sur bleuse-5.
- **La trace** : un `<svg>` absolu sur toute la hauteur de la page, un seul
  `<path>` continu (courbe de Bézier fluide inspirée d'un schéma de direction à
  quatre temps : descente, gauche, droite, remontée — répété en s'étirant sur la
  page, sur le tiers gauche puis traversant vers la droite au niveau des photos),
  trait 1 px crème à 55 %, **tracé par le scroll** (`stroke-dasharray` /
  `stroke-dashoffset` = fonction exacte de scrollY), avec une **tête lumineuse**
  (petit halo radial, 24 px, qui respire ± 10 % sur 4 s). La trace passe derrière
  les textes et devant les photos (fusion `screen`).
- **Typographie** : grands textes serif (Cormorant Garamond light, 40 à 72 px) qui
  s'allument (ombre lumineuse 0 → 18 px puis retour à 6 px, couleur de la lumière
  locale) par lignes, selon le scroll (règle 12). Les petits blocs de lorem en
  Inter 15 px blanc.

## Les cinq temps

1. **Ouverture (bleuse-1).** Noir. Le spot naît en haut à gauche (petit, faible),
   grandit et descend vers le visage sur la première hauteur d'écran de scroll ;
   la photo apparaît par la lumière, le fond doré ensuite. Puis deux **faisceaux**
   verticaux à bords doux glissent à l'emplacement des stries du DA (x 0,18–0,35
   de la photo) : à l'intérieur, la photo pivotée de 180° transparaît à 0,35, dans
   une lumière un peu plus chaude. Le **bandeau** du DA devient une **barre de
   lumière horizontale** (y 0,745–0,824) qui s'allume en dernier, à bords doux,
   dans laquelle les fragments retournés apparaissent à 0,5. Jamais de bord dur.
2. **Respiration (noir).** Une grande citation serif (emplacement « Citation
   presse — à venir », lorem, 2 lignes) s'allume ligne par ligne, puis un petit
   bloc de lorem. La trace passe entre les deux.
3. **Deuxième photo (bleuse-3).** Lumière froide. Les faisceaux prennent les
   positions de la grille du DA (14 stries de `stripeGrid`, vides autour du chef),
   mais ce sont des **colonnes de lumière bleue** (comme sur la scène) qui montent
   du bas, à bords doux, et révèlent à l'intérieur la photo pivotée teintée
   lavande ; le chef reste devant (masque `bleuse-3-masque.png`), lui-même éclairé
   par un spot chaud. L'ensemble reste sombre et contrasté.
4. **Agenda = rampe de lumière.** Cinq lampes (petits halos) en ligne, qui
   s'allument une à une quand la trace les atteint ; chaque lampe éclaire son
   titre serif, lieu et date en petites capitales. Mention « exemples ».
5. **Pied de page (bleuse-5).** Les **deux voiles** du DA = deux colonnes de
   lumière crème, à plume très large, gauche descendant du haut, droite montant du
   bas, qui éclairent la photo (le chef accroupi reste dans la pénombre, entre
   elles, puis s'éclaire doucement en dernier). La trace se termine au centre, et
   sa tête s'éteint lentement.

## Mobile (portrait)

Spot et faisceaux recalculés en coordonnées écran ; le sujet reste au centre
(point focal) ; la trace reste sur le bord gauche. Texte en bas sur dégradé sombre.

## Réglages exposés

Ambiant, plume du spot et des faisceaux, températures (trois teintes), vitesse de
la trace, amplitude de respiration, durées des temps.
