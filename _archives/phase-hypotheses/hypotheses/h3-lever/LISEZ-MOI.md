# Hypothèse 3 — Lever (la lumière et le silence) · LISEZ-MOI

Maquette construite le 22/10/2026 d'après `../BRIEF-COMMUN.md` et `SPEC.md`,
reprise le soir même après la vérification (voir « Deuxième passe » plus bas).
Ouvrir : `http://localhost:8765/hypotheses/h3-lever/` (serveur lancé depuis la
racine `PierreBleuse/`, les photos sont lues dans `../../assets/`).

## Ce qui est fait

Une page presque noire. Les photos sont dans la pénombre (ambiant 0,10 à 0,12) ;
c'est **la lumière** qui les révèle, jamais la découpe. Un seul fil continu, la
**trace de la baguette**, traverse toute la page et conduit l'œil.

| Fichier | Rôle |
|---|---|
| `index.html` | les cinq temps, le logo « Pierre Blɘusɘ », la nav FR \| EN, le `<svg>` de la trace |
| `css/lever.css` | base typographique (Cormorant Garamond + Inter), scènes, textes, lampes, voile de la nav, mobile |
| `js/light-scene.js` | **moteur lumière** : Pixi Mesh + shader généré par composition ; `PRESETS` par photo (ordinateur / portrait) ; ralenti sans épinglage ; rendu à la demande ; respiration |
| `js/lever.js` | boot (Lenis à la souris seulement, ticker GSAP), **trace** tracée par le scroll, **textes par lignes**, **rampe de lampes**, indice de défilement |

### Les cinq temps

1. **Ouverture (bleuse-1)** — fenêtre = exactement une hauteur d'écran de scroll
   (ordinateur), la photo ralentie à 16 % au plus fort pour que le visage reste
   dans le cadre jusqu'au bout. Noir ; un petit spot doré naît sous le logo,
   visible dans la brume, et descend vers le visage **à vitesse constante** en
   grandissant et en s'intensifiant (0 → 0,70 de la fenêtre) ; le fond doré
   monte pendant la descente (0,10 → 0,42), avant le texte ; le petit bloc de
   lorem s'allume par lignes (0,44 → 0,665) ; puis deux **faisceaux** à bords doux
   à la place des stries du DA (0,46 et 0,52 → 0,94 / 1,0), la lumière d'abord,
   la photo pivotée de 180° à 0,35 ensuite ; enfin le bandeau du DA devenu
   **barre de lumière** (0,48 → 1,0) qui s'allume depuis le sujet avec un retard
   local de 30 % aux bords, et dans laquelle les fragments retournés apparaissent
   à 0,5 (0,62 → 0,98).
2. **Respiration (noir).** « Citation presse — à venir », la citation en grand
   serif (3 lignes à 1440 px) avec un halo doré (0 → 18 px → 6 px), « Nom du
   média — à venir », puis un petit bloc de lorem : tout s'allume par lignes,
   libellés compris. La trace passe entre les deux.
3. **Deuxième photo (bleuse-3).** Lumière froide. Le chef s'éclaire d'abord d'un
   spot chaud ; puis 14 **colonnes de lumière bleue** (positions de la grille du
   DA, 5 pupitres du chef vers les bords) montent du bas, plus vives à leur base,
   et révèlent à l'intérieur la photo pivotée teintée lavande #5A638D ; le chef
   reste devant (masque `bleuse-3-masque.png`) et reçoit moins leur lumière.
4. **Agenda = rampe de lumière.** Cinq lampes en ligne (colonne sur téléphone),
   chacune allumée quand la tête de la trace l'atteint ; date en petites
   capitales, titre serif, lieu. Mention « exemples » (allumée par lignes elle
   aussi). Dates du brief, dans l'ordre chronologique.
5. **Pied de page (bleuse-5)** — fenêtre = le dernier écran de scroll. Deux
   **voiles de lumière crème** à plume très large (gauche depuis le haut 0 → 0,62,
   droite depuis le bas 0,38 → 1,0) ; le chef accroupi reste dans la pénombre
   entre les deux puis s'éclaire doucement en dernier (0,60 → 1,0) ; la trace se
   termine sur lui et sa tête s'éteint sur la dernière demi-hauteur d'écran. Puis
   une ligne de pied de page.

### La trace

Un seul `<path>` (Catmull-Rom → Béziers) construit en JS à partir des positions
réelles des sections et des lampes (`desktopBeats` / `mobileBeats` dans
`lever.js`) : schéma à quatre temps — descente, gauche, droite, petite remontée —
dans le tiers gauche, traversée vers la droite au niveau des photos, descente
vers la gauche à droite du titre « Agenda », de lampe en lampe, sortie sans
crochet, fin au centre du pied de page. `stroke-dashoffset` est une fonction
exacte de `scrollY` (table scroll → longueur d'arc, interpolation linéaire entre
les temps). La longueur d'arc de chaque temps est **analytique** (chaque temps est
la fin d'un segment de Bézier : somme des longueurs des segments, recalée sur
`getTotalLength()`), et la **vitesse de tracé est plafonnée à 3 px de trait par px
de scroll** (`traceMaxSpeed`) : un temps trop proche du précédent est repoussé.
La tête (halo radial 24 px) est posée à `getPointAtLength` (un seul appel par
frame). Trait 1 px crème à 55 %, `mix-blend-mode: screen`, derrière les textes
(z 3) et devant les photos (z 0).

## Où sont appliquées les douze règles

| # | Règle | Où |
|---|---|---|
| 1 | Anacrouse | `anac()` (JS dans `light-scene.js` et GLSL identique) : ease-in-sine → 10 % au premier tiers. Toutes les phases du shader (`raw()` → `anac()`), les lignes de texte, les lampes, la tête de trace passent par elle. Seule exception assumée : l'intensité du spot de l'ouverture monte linéairement (il est « visible dans la brume » dès le départ, voir deuxième passe). |
| 2 | Entrées en pupitres | `columnGrid()` : 14 colonnes en 5 groupes du chef vers les bords, retards étalés sur 71 % de la course + décalage de 0,012 dans le groupe ; final de l'ouverture : faisceau 1 à 0,46, faisceau 2 à 0,52, barre à 0,48, fragments 0,62 → 0,72 (trois pupitres étalés sur 54 % de la course) ; voiles : 0 / 0,38 pour une course 0,62 (61 %). Jamais de balayage gauche → droite (la barre part du sujet vers les deux bords). |
| 3 | Asymétrie | `anac()` : attaque sinus lente, résolution longue (ease-out d'exposant 1,6, raccord 0,18 → 0,78) : **pente maximale 2,32**. Le quart raccordé court faisait 5,4, le cubic 4,1 : les deux faisaient arriver spot, barre et voiles en une image de planche-contact. Disparition : même fonction de scrollY, donc même chemin à l'envers. |
| 4 | Bords vivants | `front(v, q, s0, s1)` : plume du front d'apparition de 45 % → 10 % de la hauteur pour la lumière, 35 % → 3 % pour la découpe ; plume latérale des découpes `cutPlume` 0,28 de la largeur → 2 px ; segments de la barre `segPlume` 0,02 → 1 px, bords horizontaux conservés à 8 % de la hauteur ; vignette écran : aucun bord de photo dur sur le noir ; profils de colonnes `bump()` sans aplat (`plume` 0,5). |
| 5 | Rubato interne | glissement vertical de la photo pivotée `cutOff = (1 − qc) · 0,03 · uSlide · sens` et des fragments de la barre (0,03 de la hauteur), en décalage avec le bord qui les encadre. |
| 6 | Respiration continue | `LightScene.tick()` : `uBreath` = ±3 % d'intensité, ±0,4 % de rayon, période 8 s, **8 images/s** (64 pas par période, 0,3 % par pas), seulement photo à l'écran et onglet visible ; tête de trace : `.trace__breath` ±5 % sur 4 s. Coupé par `prefers-reduced-motion` (CSS et `params.breath = 0`). |
| 7 | Lumière avant découpe | dans chaque colonne / faisceau : lumière `anac(r)` dès le début, découpe `anac((r − 0,34) / 0,66)` ; barre : lumière à 0,48, fragments à 0,62 → 0,98 ; spot avant tout le reste ; image de repli à la luminosité de l'ambiant (pas de baisse des lumières au chargement). |
| 8 | Densité faible | ouverture : spot (0 → 0,70) · fond doré (0,10 → 0,42, avant le texte) · texte (0,44 → 0,665) · faisceaux (0,46 → 1,0) · barre (0,48 → 1,0). Le texte entre pendant la fin du spot (déjà posé à 95 %) et avant la partie visible des faisceaux (à gauche, lui à droite) ; photo 2 : spot (0 → 0,42) puis colonnes (0,30 → 1,0), le texte au tout début (0,04 → 0,26) ; pied : voile gauche, voile droit, chef. |
| 9 | Silence | section « Respiration » noire (26 vh / 24 vh de marges), agenda noir (22 vh / 24 vh), marges larges, 36 vh entre la citation et le lorem ; après l'ouverture, la photo éclairée s'éloigne seule sur ~850 px. |
| 10 | Échelle du geste | translations ≤ 10 px (lignes), glissements ≤ 3 % de la hauteur, aucune échelle sur les photos ; ouverture sur 900 px (une hauteur d'écran) : spot 630 px (70 %), faisceaux 432 px (48 %), barre 468 px (52 %), fragments 234 px ; colonnes 0,42 × 990 ≈ 46 % chacune et 80 % pour l'ensemble ; voiles 0,62 × 900 = 558 px (62 %) chacun, 900 px pour la paire. |
| 11 | Synchronie exacte | tout est recalculé depuis `window.scrollY` à chaque frame (`gsap.ticker`), aucune inertie : `progress = clamp((s − p0) / (p1 − p0))`, `strokeDashoffset = total − lenAt(scrollY)`. Mesuré : écart progression / f(scrollY) = 0 et écart dashoffset ≤ 0,005 px sur 458 frames de molette ; 5 couples aller / retour identiques au bit près (MD5). Seuls `tick()` et le halo CSS vivent en temps réel. |
| 12 | Typographie | `TextBlock.split()` découpe en lignes réelles (mots mesurés, regroupés par `offsetTop`) ; `styleLine()` : flou 2 px → 0, montée 10 px, opacité, halo 0 → 18 px → 6 px (`.glow`) ; décalage 66 px de scroll par ligne (44 en tactile) ≈ 80–120 ms, course 0,26 écran ; dans une photo : course 0,12 et décalage 0,015 de la fenêtre ; jamais lettre par lettre. Les libellés (« Citation presse — à venir », « Nom du média — à venir », « Temps forts … · exemples ») passent par la même mécanique. |

## Réglages à ajuster et où

`js/light-scene.js` → `PRESETS['bleuse-1' | 'bleuse-3' | 'bleuse-5']`, par
composition `desktop` / `portrait` :

- `ambient`, `ambientColor` (par photo) ; `haze` (brume générale), `spot.hazeFrom / hazeTo` (brume du spot, forte à la naissance) ;
- `spot.from / to` : `uv` (point de la photo) ou `pos` (point de l'écran), `radius` en fraction de la largeur, `intensity` ; `delay`, `duration` en fraction de la fenêtre ; `geo` (position, rayon, brume) et `icurve` (intensité) : `'anac'` (défaut), `'linear'` ou `'smooth'` — l'ouverture utilise `'linear'` pour les deux ;
- `fill` (fond doré) : `intensity`, `ambientTo`, `delay`, `duration` ;
- `beams` : `color`, `intensity`, `plume` (0,5 = aucun aplat), `fade` (intensité au sommet), `cutAlpha` / `cutTint` / `cutTintMix` (photo pivotée à l'intérieur), `cutInset`, `cutAfter` (découpe après la lumière), `cutPlume`, `duration`, `items = [x0, x1, retard, sens, derrière le sujet]` ; `columnGrid({... delay0, spread, groups })` pour la grille ;
- `band` : `y0, y1, intensity, plume, from` (x d'où part la lumière), `lag` (retard local du balayage aux bords, 0,30), `delay, duration, cut, segDuration, segPlume, segments` ;
- `veils` : `intensity, plume, duration, items = [x0, x1, retard, sens]` ;
- `vignette: [x, y]` (fondu des bords vers le noir, en fraction de l'écran) ;
- `params` / `touch` : `linger` (0,84 = la photo défile à 16 % au plus fort), `lingerSpan`, `progressBefore / After / MinSpan` (fenêtre de l'effet en hauteurs d'écran), `breath*`, `maxRes` (densité max du canvas, 2), `maxPixels`.

`js/lever.js` → `SETTINGS` : `lead` (hauteur de la tête de trace dans l'écran),
`leadStart`, `headFade`, `lineGap`, `lineCourse`, `lineStart`, `lampCourse`,
`lampAhead`, `traceMaxSpeed` (3 px de trait par px de scroll), `sceneLineCourse`
/ `sceneLineGap` (lignes d'un texte posé dans une photo) ; `desktopBeats()` /
`mobileBeats()` : les temps de la trace (`s` explicite = scroll où la tête y
passe ; sinon déduit de `lead`). `data-phase="début,fin"` sur un bloc de texte
dans une photo = fenêtre de ses lignes en progression de la scène.
`window.LEVER.trace.maxSpeed` / `.capped` disent si le plafond a dû repousser des temps.

`css/lever.css` → `--glow` (couleur du halo des textes, par section), trait de
la trace (`.trace__path`), lampes (`.lamp__light`), réglure mobile (2,4 rem),
voile de la nav (`.nav::before`, dégradé noir de 0,86 → 0 sur la hauteur de la
nav + 44 px), luminosité de l'image de repli (`.scene__img`, 0,14 = l'ambiant).

Dans la console : `LEVER.scenes[0].params`, `LEVER.scenes[0].setProgress(0.8); LEVER.scenes[0].visible = true; LEVER.scenes[0].render()` pour figer un état.

## Deuxième passe (22/10/2026, soir) — réponse à la vérification

Constats du vérificateur → ce qui a été fait, et ce qui a été mesuré après.

1. **Marches dans l'ouverture et le pied de page.** Cause réelle : la pente de
   `anac()` (4,08) et, pour le visage, le fait qu'un spot qui *voyage* allume
   le visage d'un coup à l'arrivée (gaussienne). Faits : `anac` à pente max
   **2,32** ; spot de l'ouverture à descente et intensité **linéaires** (géométrie
   `geo: 'linear'`, intensité `icurve: 'linear'`, 0,22 → 1,0) sur 0,70 de la
   fenêtre ; barre sur 0,52 avec un balayage à **retard local** (`lag` 0,30 :
   le centre s'allume sur toute la course, les bords avec 30 % de retard) et une
   intensité 0,62 au lieu de 0,85 ; faisceaux sur 0,48 ; fragments sur 0,26 ;
   voiles sur 0,62 et spot du pied sur 0,40, fenêtre du pied portée à un écran.
   Mesuré sur la planche de 12 images (82 px) avec suivi du cadre : **visage
   11 → 13 → 23 → 48 → 75 → 99 → 110** (max +27 par image, sur six images, au
   lieu de 245 px de rien puis +55 et +44) ; **barre** centre +14,5 max, gauche
   +28, droite +15 (au lieu de +38 d'un coup) ; faisceaux +18 ; fond doré +5,5 ;
   pied : voile gauche +17,8 max, voile droit +10, chef +5 ; photo 2 : chef +13,
   colonnes +3. Le critère « ≤ 20 par image » n'est pas atteint partout : avec
   une anacrouse (10 % au premier tiers) et une résolution longue, 80 % d'une
   montée tient dans ~45 % de la course, et un visage qui gagne +100 de luminance
   sur 630 px de scroll fait forcément ~+27 sur 82 px. Aller plus bas voudrait
   dire une courbe quasi linéaire (plus d'anacrouse) ou une ouverture sur deux
   écrans.
2. **Point culminant hors écran.** `params: { progressMinSpan: 1.0, linger: 0.84,
   lingerSpan: 1.7 }` : haut du visage à **+146 px** au début de la barre
   (p 0,48), +32 px à p 0,9, −6 px à p = 1 (les yeux restent à l'écran) ; la
   barre finit à 439–510 px du haut de l'écran. Tactile : `progressMinSpan 0.55`
   et barre portrait à 0,72–0,80 du cadre → à p = 1 elle est à 144–211 px, sous
   le logo (bas à 57 px) et au-dessus de la première ligne du lorem ; sans
   ralenti (défilement natif), le visage reste hors du final sur téléphone :
   compromis conservé, voir limites.
3. **Éclair des textes au relayout.** `relayout()` finit par `updateAll(scrollY)`
   (tout restylé dans la même frame, avant la peinture), les lignes sont créées à
   `opacity: 0`, et `buildTrace()` ne fait plus 3 165 `getPointAtLength` :
   longueurs d'arc analytiques. Mesuré : relayout **1,0 à 1,4 ms** (au lieu de
   406–434), 0 ligne visible à la mutation et à la fin de la tâche après un
   `resize`, lorem à 0 pendant toute la séquence de chargement (14 captures),
   ancrages exacts (≤ 0,1 px entre `getPointAtLength(len)` et le temps).
4. **Collision du logo.** `.nav::before` : voile noir dégradé (0,86 → 0 sur la
   hauteur de la nav + 44 px). « Agenda », la citation, « Red Fox », « Ravel
   Abroad » passent dessous en s'éteignant ; le logo reste lisible. Sur la photo
   à scrollY 0, la bande 60–120 px vaut 3,8 de luminance (déjà noire par la
   vignette).
5. **Fouet de la trace.** Temps de l'agenda redessinés (descente vers la gauche
   à droite du titre, 0,12 H entre deux lampes, sortie sans crochet) avec des `s`
   explicites, plus le plafond `traceMaxSpeed` = 3. Mesuré : vitesse max **3,06
   px/px** (au lieu de 13,8), tête toujours entre 182 et 642 px de l'écran,
   aucun tronçon de 50 px au-dessus de 3,05 ; téléphone 2,45 sans plafonnement.
6. **Règle 8.** Fond doré à 0,10 → 0,42, texte à 0,44 → 0,665 (`data-phase`,
   course 0,12, décalage 0,015), partie visible des faisceaux après 0,60.
7. **Libellés secs.** `data-lines` sur `.quote__label`, `.quote__src`,
   `.agenda__sub` : allumés par la même courbe que les textes.
8. **Baisse des lumières au chargement.** `.scene__img` à `brightness(0.14)`
   (= l'ambiant du shader), fondu du canvas 0,8 s. Mesuré : visage **13,7 →
   12,8 → 11,4** pendant le chargement (au lieu de 43,9 → 23,9 → 12,7).
9. **Rendu au repos.** `breathFps` 20 → 8 : **7,3 à 7,7 rendus/s** au repos
   (au lieu de 16,7), rAF à 60,7/s.
10. **favicon** : `<link rel="icon" href="data:,">` — plus aucune requête en échec.

Non retenu : masquer le logo après le premier écran (le voile suffit et garde
la marque) ; ease-out-quart (voir règle 3) ; sortir la respiration du shader (le
rayon du spot respire aussi).

## Vérifications (Chrome headless, CDP, 22/10/2026 soir)

Harnais : `scratchpad/fix-h3-lever/*.mjs` (hors projet), serveur 8789, CDP 9369,
SwiftShader pour les captures, GPU Metal (Apple M5) pour les temps.

- 0 erreur console, 0 exception, 0 requête en échec, sur ordinateur (1440 × 900),
  téléphone (390 × 844, dpr 3, tactile, UA iPhone) et `prefers-reduced-motion`.
- Captures ordinateur à 6 positions (+ agenda), téléphone à 4 positions (+ 3) ;
  planches-contact de 12 images de l'ouverture (ordinateur et téléphone), de la
  photo 2 et du pied de page, avec luminance par zone suivie avec le décalage du
  cadre (chiffres ci-dessus).
- Glissé tactile depuis la photo (`Input.synthesizeScrollGesture`, 500 px) :
  la page défile de 502–503 px ; canvas `pointer-events: none` /
  `touch-action: pan-y`, EventSystem Pixi détaché.
- Même scrollY aller puis retour (495, 820, 2900, 4600, 5500) : captures
  identiques au bit près (MD5) ; retour en haut → tout éteint, trace vide,
  lignes à 0.
- Molette réelle (103 crans de 60 px, Metal) : **59,4 rAF/s** (56,4 en
  mouvement, dt médian 16,7 ms, p95 18 ms), écart progression / f(scrollY) = 0 ;
  SwiftShader 18–24 rAF/s (rendu logiciel, sans valeur pour la fluidité).
- Mouvement réduit : Lenis absent, `breath` 0, `linger` 0, `uSimple` 1,
  `uSlide` 0, animations CSS coupées, fondu du canvas instantané.
- Compositions : `desktop` ×3 à 1440 × 900, `portrait` ×3 à 390 × 844 (point
  focal à x = 195) ; lorem mobile 15,2 px sur 320 px de large.

## Limites connues

- Les photos font 992 × 619 px : floues en plein écran (bicubique Catmull-Rom
  pour limiter la casse) ; la définition viendra des HD du DA.
- `anac()` résout en ease-out d'exposant 1,6 (pente max 2,32), pas en quart ni
  en quint : avec ces exposants la planche-contact montre une marche. À valider
  par le DA ; l'exposant est dans `anac()` (JS et GLSL, à garder identiques).
- Le spot de l'ouverture n'a pas d'anacrouse sur son intensité (linéaire) :
  il est « petit et faible » dès le départ, ce qui est le parti de la SPEC.
- Sur téléphone (défilement natif, pas de ralenti), le final de l'ouverture
  (faisceaux, barre) se joue sur le buste et les mains, le visage étant déjà
  sorti par le haut : seule une fenêtre de ~250 px le garderait, trop courte.
- Au bout de l'ouverture (p = 1), le haut du front passe sous le voile de la
  nav (0 → 118 px) : il s'assombrit légèrement, les yeux restent éclairés.
- La trace est posée dans la page (coordonnées de document) alors que les photos
  ralentissent : sur ordinateur le fil glisse par rapport à la photo pendant le
  ralenti, plus qu'avant (translation du cadre jusqu'à 857 px) ; c'est voulu (le
  fil appartient à la page), ses passages « au niveau des photos » ne visent pas
  un détail précis.
- Sur GPU Metal (Chrome headless), une frame isolée de 67 à 152 ms apparaît une
  fois par passage de molette (p95 à 18 ms), à une position différente à chaque
  passage (scrollY 524, 585, 1481, 4315, 4561) : le préchauffage du shader à
  p = 1 dans `init()` ne l'a pas fait disparaître, ce n'est donc pas une
  compilation tardive mais plutôt le ramasse-miettes ou l'ordonnancement du
  headless. Le préchauffage est conservé (toutes les branches compilées, canvas
  encore invisible) ; il coûte deux rendus par scène à l'init — négligeable sur
  GPU (boot complet mesuré à 614–620 ms sur Metal, trois scènes prêtes), mais en
  SwiftShader (rendu logiciel des tests) le boot passe de 2 à 9 s à cause du
  JIT, chiffre sans valeur pour un vrai navigateur.
- Le logo « Blɘusɘ » : e retournés par CSS (`.flip`) faute de glyphe U+0258
  dans Cormorant / Inter ; graisse et police à caler sur le logo définitif.
- Sans WebGL : la `<img>` reste seule, éclaircie (`brightness(0.7)`), sans
  lumière mobile ; la trace et les textes fonctionnent.
- Testé en Chrome headless uniquement (pas de simulateur iOS sur ce poste) ;
  rendu Safari / vrai GPU mobile à vérifier sur appareil.
- `mix-blend-mode: screen` sur un `<svg>` de la hauteur du document : léger
  coût de composition ; à retirer si une machine faible peine.
