# Hypothèse 2 — Tutti (cent voix, un geste)

Maquette autonome : `index.html`, `css/tutti.css`, `js/tutti.js` (noyau),
`js/tutti-sections.js` (rendu Pixi + textes par lignes), `js/main.js` (amorçage,
formations, calendrier de scroll). Aucune dépendance locale hors `../../assets/`
(photos et masques) ; Pixi 7.4.0, Lenis 1.1.14, GSAP 3.12.5 par CDN, polices
Google (Cormorant Garamond, Inter).

Voir : `http://localhost:8765/hypotheses/h2-tutti/` (serveur de l'utilisateur,
depuis la racine du projet). Paramètre de test : `?nolenis=1` (scroll natif, sans
lissage molette).

## Ce qui est fait

Un point de lumière par musicien : 96 points sur ordinateur, 64 sur téléphone,
quatre pupitres aux teintes de la graduation chromatique (cordes crème, bois
lavande, cuivres cyan, percussions vert profond). Les points vivent en temps réel
(dérive lente, scintillation, respiration posée, souffle du pointeur) et le scroll
est le geste du chef : il les rassemble en formations successives, et les photos
apparaissent à travers eux.

1. **Ouverture (bleuse-1).** Écran noir, points en dérive libre, halo
   d'anticipation à peine visible sur le visage. En descendant (course 0,72 hauteur
   d'écran sur ordinateur, 0,34 en tactile) : les points se rassemblent sur le
   sujet (cibles tirées dans `bleuse-1-masque.png`, plus denses sur le visage) ;
   chaque point ouvre une fenêtre douce qui montre d'abord une lumière crème
   (luminance de la photo), puis la photo ; les fenêtres grandissent (× 6) et
   fusionnent ; le sujet entier se pose ; le fond (bokeh) monte ; les cordes se
   rangent en 4 colonnes aux positions des stries de la maquette bleuse-2 où la
   photo pivotée de 180° transparaît à 0,5 (départs 0,70 / 0,73 / 0,76 / 0,79 de
   la course, la 4e est achevée à 0,99) ; bois et cuivres se posent sur les bokeh,
   percussions dans l'ombre ; le petit bloc de lorem entre par lignes à partir de
   0,98 de la course, une fois les colonnes posées.
   Sur ordinateur, la photo « s'attarde » (ralenti du cadre, fonction exacte du
   scroll, repris du moteur existant) pour rester visible pendant la transformation.
   En portrait (téléphone), la photo est agrandie de 25 % et cadrée sur le visage,
   placé à mi-hauteur d'écran : il est révélé (couleur, sujet entier) avant de
   sortir du cadre par le haut.
2. **Respiration (noir).** Les points forment le plan de salle (éventail : cordes
   devant, bois, cuivres, percussions au fond, le chef = point crème plus grand,
   immobile), légende des pupitres, puis deux blocs de lorem et un emplacement de
   citation presse qui entrent par lignes une fois la formation posée.
3. **Deuxième photo (bleuse-3).** La photo arrive par la lumière (luminance chaude
   puis couleurs). Les points se rangent dans les 14 colonnes de la grille de la
   maquette bleuse-4 (8 colonnes écran en portrait) ; chaque point qui se pose
   allume une **barre de lumière verticale** dans sa colonne (hauteur constante,
   bouts fondus, intensité qui monte avec l'arrivée du point) ; les barres se
   complètent et la colonne « se remplit » par segments. La largeur et la plume de
   chaque colonne sont calculées dans le shader (pas dans la barre) : la colonne
   est une bande droite dès sa naissance, plume large qui se resserre à 1-2 px
   quand ses membres sont tous posés, lumière lavande claire qui devient découpe
   (photo pivotée + lavande #5A638D à 50 %), le chef devant (masque). Quand les
   points repartent, les colonnes redeviennent lumière puis s'éteignent sur place.
   Les points posés sur la photo s'atténuent de moitié et leurs rangs sont décalés
   (pas de treillis).
4. **Agenda = constellation.** Points le long d'une ligne de temps qui ondule ;
   cinq points plus grands et plus lumineux (halo) = les cinq temps forts, qui
   s'allument avec leur texte (date, titre serif, lieu en petites capitales) quand
   le scroll les atteint. Mention « Exemples de temps forts ». En portrait : ligne
   verticale, textes à droite.
5. **Pied de page (bleuse-5).** Les points se posent sur les taches de lumière du
   sol (pixels clairs de la moitié basse, échantillonnés au chargement), puis les
   deux voiles crème chaud (#F3EAD8 à 0,45) se forment sur 0,5 écran : gauche
   depuis le haut, droite depuis le bas (bandes écran en portrait). Le bloc de
   lorem est posé sur la zone sombre du haut (machinerie), comme sur bleuse-6,
   avec un voile radial discret. Ligne de pied de page et emplacement « Écouter —
   à venir » (inerte).

Nav fixe : logo « Pierre » (Cormorant italique) sur « Blɘusɘ » (Inter gras, e
retournés par `transform: scaleX(-1)` pour ne pas dépendre du glyphe U+0258),
« FR | EN » à droite.

## Les douze règles, et où elles sont appliquées

1. **Anacrouse** — `easeG` dans `js/tutti.js` : `1 − (1 − t^3,2)^3,5`, soit 10 % au
   premier tiers (mesuré 0,100), 99 % à 0,9, dérivable partout (pas d'à-coup).
   Halo d'anticipation sur le visage avant toute image (`updateOuverture`,
   `main.js`) ; la lumière d'une fenêtre s'allume pendant que le point approche
   (`smooth01((q0 − 0,1) / 0,85)`) ; la barre d'une colonne de la photo 2 s'allume
   par `easeG` pendant que son point se pose.
2. **Entrées en pupitres** — `ParticleSet.assignDelays` (`tutti.js`) : retards par
   groupes de 3, du plus proche du sujet au plus éloigné, dans la bande de chaque
   pupitre (cordes 0–0,42, bois 0,30–0,64, cuivres 0,52–0,84, percussions 0,70–1) ;
   `CFG.spread = 0,6` de la course. Colonnes de l'ouverture décalées de 0,03 par
   colonne, voiles de 0,15, temps forts à départs irréguliers (0 / 0,05 / 0,11 /
   0,15 / 0,22 hauteur d'écran) ; les colonnes de la photo 2 se remplissent au
   rythme de leurs membres (familles mêlées). Jamais de balayage régulier.
3. **Asymétrie** — même `easeG` pour tout (attaque lente, résolution longue) ; le
   retour est le même chemin puisque tout est fonction de `scrollY`.
4. **Bords vivants** — shader de l'ouverture (`tutti-sections.js`) : plume des
   colonnes `mix(0,05, 0, q) + aa` (large puis 1–2 px) ; détourage moyenné sur 5
   prélèvements (bord ≈ 8 px) ; fenêtres gaussiennes (`TEX.win`). Photo 2 : plume
   de chaque colonne `colFeather × (1 − q) + aa` (jusqu'au bord de sa cellule,
   puis 1–2 px), bouts des barres en S (`TEX.bar`). Voiles : plume
   `mix(0,10, 0,012, q)` et front vertical doux (`soft = 0,4`).
5. **Rubato interne** — glissements internes de la photo dans les shaders :
   sujet `(1 − uSubject) × 2 %`, colonnes `(1 − q) × 3 %`, photo 2
   `(1 − uSettle) × 3 %`, photo 3 `(1 − uReveal) × 1,5 %` ; `uSlide = 0` en
   mouvement réduit.
6. **Respiration continue** — `Scheduler.update` (`tutti.js`) : cercle de 1,4 px en
   6 à 10 s par point (`CFG.sway`) plus 2 % de dérive résiduelle, soit ≤ 2 px
   d'amplitude posé (mesuré 1,7 px crête) ; scintillation ± 15 % en 2 à 5 s
   (`CFG.scint`), halos qui respirent à ± 0,4 % en 7–9 s. Le chef du plan de salle
   est strictement immobile (ni dérive, ni respiration). Tout est coupé si
   `prefers-reduced-motion` (halos compris).
7. **Lumière avant découpe** — ouverture : `uCut` fait passer les fenêtres de la
   lumière crème (`uLightCol × luminance`) à la photo ; colonnes `rotLight → rot` ;
   photos 2 et 3 : `uReveal` (luminance chaude, puis couleur) ; colonnes de la
   photo 2 d'abord lumière lavande claire (`stripLight`) puis découpe, colonne par
   colonne (`smoothstep(0,3, 0,95, q)`).
8. **Densité faible** — `buildSchedule` (`main.js`) : les textes n'entrent qu'après
   la transformation. Mesuré (ordinateur 1440 × 900) : ouverture, 4e colonne
   achevée à 642 px, texte 636 → 804, départ des points vers le plan à 816 ;
   photo 2, colonnes posées à 2837, texte 2856 → 3162, départ des points à 3179 ;
   pied, points posés à 4991, texte 5002 → 5272, voiles 5279 → 5729, ligne de pied
   5740 → 5838 ; respiration, plan posé à 1356, première voix à partir de 1454.
9. **Silence** — sections noires (respiration 1,8 écran, agenda prolongé de 62 svh
   après les temps forts), marges larges (`.voices`, `.events`), bande noire de
   64 vh sous la dernière photo.
10. **Échelle du geste** — sur les images : translations ≤ 3 % de la hauteur,
    textes 10 px, plumes et largeurs de colonnes seulement. Transitions entre
    formations (hauteurs d'écran) : plan 0,60, colonnes 0,82, constellation 0,72,
    taches 0,63, voiles 0,50 (bridé par la fin du document). L'ouverture est UNE
    transformation de 0,72 écran (fenêtres 0 → 0,62, sujet 0,58 → 0,78, fond
    0,62 → 0,92, colonnes 0,70 → 0,99) : ses phases s'enchaînent sans arrêt, elles
    ne sont pas des transitions séparées. Le déplacement des points entre deux
    formations est le propos de l'hypothèse (les voix se déplacent), pas une
    transformation d'image.
11. **Synchronie exacte** — `Scheduler.update(s)` et les `update*` de `main.js` ne
    lisent que `window.scrollY` ; aucune inertie entre scroll et effet (test :
    même scrollY → image identique au pixel, voir ci-dessous). Seuls dérive,
    scintillation, respiration, souffle et halos lisent le temps. Lenis ne lisse
    que la molette sur ordinateur et n'est pas chargé en tactile.
12. **Typographie** — classe `Lines` (`tutti-sections.js`) : découpe en lignes
    mesurées (une ponctuation collée à un `<em>` reste collée au mot, les lignes
    sont séparées par une espace dans `textContent`), chaque ligne = opacité
    0 → 1, flou 2 px → 0, montée 10 px → 0, avec un décalage de 1/(n−1) × 0,5 de la
    course du bloc : 15 à 22 px de scroll par ligne (respiration, pied, photo 2),
    9 px pour le bloc de l'ouverture (contraint par la sortie du cadre, voir
    limites). Jamais lettre par lettre. Petites capitales ≥ 11 px partout.

## Réglages à ajuster

- `js/tutti.js` → `window.Tutti.CFG` : `N`, `families` (teintes, bandes de retard),
  `group`, `spread`, `ease.a/b` (3,2 / 3,5), `radius`, `alpha`, `scint`, `drift`
  (16 / 24 s + 5 / 10 s : crête ≤ 12 px/s), `sway` (1,4 px), `breath` (rayon
  140 px, force, retour 2,6 s), `window.r0/grow` (fenêtres), `opening.course`
  (0,72 ordinateur / 0,34 tactile), `linger` / `lingerSpan` (ralenti de la
  première photo), `maxPixels` (budget des canvas), `seed` (disposition).
- `js/main.js` → `buildSchedule` : bornes de chaque transition (en px de scroll,
  calculées depuis les hauteurs de section) et déclencheurs des textes (ouverture
  0,98 S1 sur 0,26 S1 ; photo 2 `top + 0,04 vh` sur 0,34 vh ; pied `top − 0,31 vh`
  sur 0,3 vh) ; `updateOuverture` : phases `uCut` (0,3–0,6), `grow` (0,46–0,72),
  `uSubject` (0,58–0,78), `uBg` (0,62–0,92), colonnes (0,70 + 0,03 k, durée 0,2) ;
  `updateOrchestre` : finesse d'une colonne = part de ses membres posés ;
  `buildFormations` : positions des colonnes (UV photo / écran), bokeh, plan de
  salle (rayons, ouvertures d'arcs, aplatissement), grille de la photo 2 (cellule
  `cellW` = 1,2 période, hauteur de barre `barH` = 2,2 espacements, plume
  maximale `colFeather`), voiles.
- `js/tutti-sections.js` → `cover()` : agrandissement portrait de l'ouverture
  (× 1,25, visage à 0,40 de la hauteur) ; `FRAG_ORCHESTRE` : largeur
  `mix(0,7, 1, q)`, bascule lumière → découpe `smoothstep(0,3, 0,95, q)`.
- `css/tutti.css` : hauteurs des temps (`.plan` 100 svh, `.voices`,
  `#agenda` marge basse, `.events --dy` pour l'ondulation, `#pied --after` 64 vh /
  50 svh), positions des blocs de texte (`.tutti__text--right`, ouverture à 60 %,
  pied à 13 % avec voile `::before`), typographies (petites capitales .72rem /
  .7rem mobile).

## Protocole de test (22/10/2026, seconde passe après vérification)

Chrome headless piloté par CDP (Node 25), serveur statique local, rendu
SwiftShader puis GPU (`--use-angle=metal`, « ANGLE Metal Renderer: Apple M5 »,
images identiques). Captures et planches dans le dossier de travail
`/private/tmp/claude-501/-Users-carrelcommunication-Documents-Server/356771ff-5972-4840-9843-23b6f6bbb817/scratchpad/fix-h2-tutti/`
(`shots/`, `planche-*.jpg`).

- Console : 0 erreur, 0 exception, 0 requête en échec, 0 HTTP ≥ 400 (desktop,
  mobile, mouvement réduit, molette). Repli sans WebGL : 5 avertissements
  attendus, `<img>` visibles au cadrage exact du canvas, textes à 1.
- Desktop 1440 × 900 : 6 positions (+ texte de l'ouverture, voix, agenda, fin) et
  planche-contact de 12 images de l'ouverture (0 → 1 hauteur d'écran). Lecture de
  la planche : noir et dérive → lumière sépia sur le visage → fenêtres qui
  fusionnent → couleur → sujet entier → fond → colonnes → texte après les
  colonnes ; pixels allumés 0,6 % (y 82) → 5,7 % → 11,1 % → 11,6 % → 20,2 %
  (y 409), aucune image n'arrive en bloc. Planche de la photo 2 (12 vues) : les
  colonnes naissent en barres droites à bouts fondus, se remplissent, se
  resserrent ; stries posées mesurées à 44 px pour 44,3 nominaux (bleuse-4).
- Mobile 390 × 844, dpr 3, tactile, UA iPhone : N = 64, Lenis absent, composition
  portrait ; à la pose du sujet (0,78 de la course, y 224) le haut du crâne est à
  34 px du haut de l'écran et les yeux à 230 px ; colonnes et texte sur le bas du
  visage et la chemise. 11 positions + 2 planches de l'ouverture (0 → 1 écran et
  0 → 1,3 S1). Glissé tactile depuis la photo (`Input.synthesizeScrollGesture`,
  touch) : scrollY 0 → 399 et 2602 → 2905. `touch-action: pan-y` vérifié sur les
  canvas, EventSystem Pixi retiré (× 5).
- Même scrollY → même image (temps gelé via `TUTTI.freeze(true)`), aller → détour
  → retour en 5 positions (ouverture 420 et 560, photo 2, constellation, voiles) :
  0 pixel différent (barre de défilement exclue). Temps libre : 0,5 à 1,1 % de
  pixels changent (vie ambiante seule). Vie au repos : 2,4 % des pixels changent
  en 4 s en haut de page (dérive libre, crête 11,0 px/s, moyenne 4,6), 0,64 %
  posé (excursion 1,7 px crête).
- Cadence pendant un scroll molette réel (Lenis actif) sur Metal : 59,7 rAF/s sur
  5,9 s, médiane 16,7 ms, p99 18,4 ms, un seul écart > 50 ms (62,5 ms), 331
  frames sur 351 avec changement de scroll ; sans Lenis 59,9 rAF/s (max
  22,2 ms) ; 60,4 rAF/s au repos.
- `prefers-reduced-motion` émulé : points immobiles sur la formation de chaque
  section, photos en fondu, textes sans flou ni montée, 0 pixel bouge en 2 s
  (ouverture et photo 2), aucune erreur.

## Limites connues

- Tactile : pas de ralenti (défilement natif pur), donc la course de l'ouverture
  est courte (0,34 écran) : la révélation complète du visage tient en 290 px de
  scroll, un seul geste de pouce sur un téléphone. La fin de l'ouverture
  (colonnes, texte) se joue sur le bas du visage et la chemise.
- Ordinateur : le bloc de l'ouverture doit être entré avant que le haut de la
  photo ralentie ne sorte du cadre, et après la 4e colonne (0,99 S1) : sa cascade
  est de 0,26 S1 (168 px), soit 9 px de scroll par ligne, moins que les autres
  blocs (15 à 22 px).
- Les phases de l'ouverture (fenêtres, sujet, fond, chaque colonne) durent
  chacune moins de 0,6 écran : c'est une seule transformation continue de 0,72
  écran, pas quatre transitions ; l'allonger ferait sortir la photo du cadre.
- Photos 992 × 619 : floues en plein écran (bicubique dans le shader), on fait avec.
- Cinq contextes WebGL (un par section) : confortable sur ordinateur, à surveiller
  sur de vieux téléphones ; en cas d'échec, l'`<img>` de repli s'affiche et les
  textes restent lisibles.
- Simplifications par rapport au SPEC : toutes les colonnes de la photo 2 passent
  derrière le chef (pas de distinction « 2 stries derrière le bras ») ; la photo
  pivotée des colonnes est calculée dans le shader (pas de second sprite) ; la
  dérive est une somme de sinus (pas de curl noise) ; les cinq temps forts sont
  cinq points de cordes ; le souffle en tactile n'agit que pendant le glissé.
- Lenis ajoute un lissage entre la molette et la position de scroll (pas entre le
  scroll et l'effet) ; désactivable avec `?nolenis=1` ou en retirant le script.
- Non testé dans Safari/iOS réel (pas de simulateur) : tactile vérifié en émulation
  Chrome ; Metal/SwiftShader rendent la même image.
