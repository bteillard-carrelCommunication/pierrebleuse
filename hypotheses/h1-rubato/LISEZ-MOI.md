# Hypothèse 1 — Rubato, le temps musical

Maquette construite le 22/10/2026 d'après `../BRIEF-COMMUN.md` et `SPEC.md`, puis
**corrigée le 22/10/2026 (soir)** après vérification indépendante (verdict « ok avec
réserves » : voir § Correctifs). Les compositions du DA (stries, bandeau, voiles ;
presets `bleuse-1`, `bleuse-3`, `bleuse-5`, desktop + portrait) sont celles du moteur
d'origine : seule la **manière d'arriver** change. Rien n'est modifié hors de ce dossier.

Voir la page : `http://localhost:8765/hypotheses/h1-rubato/` (serveur de
l'utilisateur, lancé depuis la racine du projet) — les assets sont lus en
`../../assets/`, les bibliothèques en CDN (Lenis 1.1.14, Pixi 7.4.0, GSAP 3.12.5).

## Fichiers

| Fichier | Rôle |
|---|---|
| `index.html` | les cinq temps : ouverture (bleuse-1), respiration (section noire + citation presse), deuxième photo (bleuse-3), agenda-partition, pied de page (bleuse-5 + colophon) ; nav logo « Pierre Blɘusɘ » + FR \| EN ; favicon SVG inline ; filet de sécurité `js-timeout` |
| `css/styles.css` | base typographique reprise de `../../css/styles.css` (Cormorant Garamond + Inter), palette noir / crème #EFE6D3 / lavande #5A638D, voile de nav, mobile, mouvement réduit, mode dégradé |
| `js/stripes-effect.js` | moteur dérivé de `../../js/stripes-effect.js` : courbe `phrase()`, lumière avant découpe, pupitres, bords vivants, rubato interne, respiration, graduation chromatique, ralenti adouci, géométrie du scroll (`measure()`), ombre douce sous le texte de bleuse-5 |
| `js/choreo.js` | textes par lignes, agenda-partition (portée + notes), nav qui s'efface devant les textes — tout en fonction de scrollY |
| `js/main.js` | boot robuste : Lenis (molette seulement), ticker GSAP (ou rAF s'il manque), heroes (ou photo seule), chorégraphie, rideau de chargement |

## Correctifs du 22/10 (soir) — réponse aux constats de la vérification

| # | Constat | Correctif | Mesuré après |
|---|---|---|---|
| 1 | Nav fixe en collision avec les textes qui défilent dessous (ouverture, agenda, mobile) | **Chaque partie de la nav s'efface devant un texte qui arrive dessous** (logo et FR \| EN séparément, sur 48 px d'approche, marge 20 px, `choreo.js` → `updateNav()`), fonction de la position, et revient dès que le texte est passé. Zones : `.hero__text` (si entré), `.rest__a/__b`, `.quote`, `.agenda__head`, `.staff`. Plus un **voile sombre dégradé** derrière la nav (`.nav::before`, 150 px, 0,5 → 0), absent à l'ouverture au repos, qui apparaît entre 5 et 20 % d'écran de scroll (`--nav-veil`). Je n'ai pas retenu « nav effacée pour toute la page après 0,38 vh » : le logo fait partie de l'identité sur chaque écran du DA. | 0 recouvrement avec nav visible sur 26 positions scannées (ouverture, en-tête d'agenda, colonne de notes mobile) |
| 2 | Règle 8 : les textes entraient pendant que l'image se transformait encore | Le texte posé sur une photo n'entre qu'**après la pose** (`t0 = p1`) ; le hero de pied **réserve l'espace** nécessaire avant le colophon (`gap`, calculé dans `measure()`). Au passage, **course au chargement corrigée** : `collect()` pouvait tourner avant la création des heros (`document.fonts.ready` déjà résolu → micro-tâche entre `choreo.js` et `main.js`), les textes de photo devenaient des blocs ordinaires — le hero est maintenant résolu à chaque `measure()`. | bleuse-3 et bleuse-5 : 0 ligne > 0 tant que `progress` < 1, sur 17 positions chacune (desktop et mobile) |
| 3 | Texte blanc sur le voile crème de bleuse-5 : 3,6:1 | Voile ramené à l'alpha du DA (voir 10) + **ombre très plumée sous le bloc, dans le shader, avant le voile** (`textShade` 0,45, boîte du bloc élargie de 24 px, plume 4,5 / 6 % d'écran) : la lumière du sol se calme sous le texte, le voile reste uniforme. Ombre du texte un peu renforcée. | fond moyen (99, 95, 88) → **6,35:1** ; pire bande de 20 px **4,6:1** (était 2,9:1) — fin de page, pose et fin d'entrée du texte |
| 4 | gsap manquant → page muette | `main.js` : ticker **rAF de secours** si GSAP manque, photo seule si Pixi ou le moteur manquent, textes en blocs ordinaires sans moteur ; `index.html` : `js-timeout` posé après 4 s si le rideau n'est pas levé → CSS rend tout visible. | gsap / Pixi / moteur / main.js / tout CDN bloqués : rideau levé (ou `js-timeout`), nav et textes à 1, 0 exception |
| 5 | Polices arrivant après le rideau : le texte d'ouverture rejouait | Lignes recréées après `is-loaded` marquées `.is-set` ; l'animation `line-in` ne vise que `:not(.is-set)`. | polices retenues 3,7 s après le rideau : 8 lignes recréées, `is-set` 8/8, opacité 1, animation `none` |
| 6 | Règle 2 : étalements 46 % / 14 % / 21 % après normalisation | bleuse-3 : `spread` 0,6 → **1,15** (= 61 % de la course) ; bleuse-5 : voile droit à 0,75 avec `veilDuration` 0,5 (= 60 %). bleuse-1 : gardé — les deux paires de stries sont l'attaque (0 → 21 %), le bandeau la réponse (31 → 66 %) : la composition entière s'étale sur 66 %. | 61 % / 60 % / 66 % (global) |
| 7 | Le tableau achevé n'est jamais vu entier | Heros du milieu : la composition se pose quand la photo est **alignée en haut** (`progressAfter` 0,1 → cadre à −45 px à p = 1, contre −190) ; hero de pied : cadre à +36 px à la pose (`poseOffset` 0,04), coupe de fin de page 185 px (tête du chef entière). Hero du haut : `progressTop` 0,75 → 0,6 (cadre à −302 px à p = 1 au lieu de −401 : les yeux restent visibles). **`linger` 0,3 écarté** : un ralenti plus faible fait partir la photo plus vite (cadre à −397 px à p = 1, calculé) ; 0,5 est la valeur du SPEC. | cadres mesurés à p = 1 : 0 / −45 / +36 px (desktop) |
| 8 | Règle 10 : fenêtres mobiles 0,50 / 0,52 vh | `progressBefore` 0,65, `progressAfter` 0,05, `progressTop` 0,6 en tactile ; hero de pied 0,6 vh partout. | fenêtres desktop 0,60 / 0,85 / 0,60 vh ; mobile 0,60 / 0,70 / 0,60 vh |
| 9 | Veuves typographiques dans l'agenda | `text-wrap: balance` sur titres et lieux (mesuré avant la découpe en lignes) + insécables `Ravel Abroad`, `Hayato Sumino`, `Milano Musica`. | 0 veuve dans l'agenda (desktop et mobile) ; restent les fins de paragraphes justifiés (« facilisi. », « claritatem. », « futurum. »), normales |
| 10 | Voiles de bleuse-5 beige kaki | **Mesuré sur `bleuse-6.png` du DA** (même zone que la photo nue, six zones) : voiles **blanc pur, alpha 0,30** (0,298 à 0,303), aucune gradation hors voile. Réglé à **0,30** (crème #F3EAD8 gardé pour la chaleur), sépia sous les voiles 0,5 → **0,2**. Le SPEC disait 0,45 : à valider avec le DA, la maquette dit 0,30. | `uVeilAlpha` 0,30, `uGradeMix` 0,2 |
| 11 | Mobile : le dégradé du bloc d'ouverture mordait sur le bandeau | Bandeau portrait remonté à 0,68 → 0,76 de l'écran ; dégradé du bloc plus bas (0,8 à 30 %, padding 4 rem). | bandeau 574 → 641 px, bloc de texte à partir de 657 px |
| 12 | Pour mémoire | favicon SVG inline (plus de 404) ; LISEZ-MOI : plume et portée documentés comme écarts volontaires (voir Limites). | 17 requêtes, 0 en échec |

## La grammaire de mouvement : où chaque règle s'applique

1. **Anacrouse** — `phrase(t)` (`stripes-effect.js`, en tête + `PHRASE_GLSL`) : 0 → 10 % sur le
   premier tiers (ease-in-sine), raccord de pente C1, puis résolution à pente nulle. Utilisée par
   toutes les stries, segments de bandeau, voiles (shader), lignes de texte, portée et notes
   (`choreo.js`). L'entrée au chargement (`@keyframes line-in`) a le même palier : 10 % à 33 %.
2. **Entrées en pupitres** — `pupitres()` : tri par distance au sujet, paires, retards étalés
   sur `spread` (+ `jitter` 0,03 dans la paire). Les étalements sont mesurés **après
   normalisation** (part de la course réelle) : bleuse-1 stries 4 → 3 (0 / 0,04) puis 2 → 1
   (0,26 / 0,30), bandeau 1 → 3 → 2 → 4 (0 / 0,18 / 0,32 / 0,50) à partir de `band.start` 0,45,
   composition entière sur 66 % ; bleuse-3 : 14 stries en 7 paires du chef vers les bords,
   `spread` 1,15 → 61 % ; bleuse-5 : voile gauche puis, à 60 %, voile droit.
   Agenda : notes {1, 2} à 0,30 / 0,38, {3} à 0,56, {4, 5} à 0,68 / 0,74 — pas de cadence régulière.
3. **Asymétrie** — même `phrase()` partout ; la disparition refait le chemin à l'envers parce que
   tout est une fonction de la position (voir 11).
4. **Bords vivants** — shader : `aa = mix(PLUME · largeur, uAA, qC²)` (plume 25 % de la largeur
   de l'élément, puis 1,5 px) ; `reveal(t, q, soft)` avec `soft` 0,22 → 0,05 ; bandeau :
   plume verticale 25 % de sa hauteur → 1,5 px ; voiles : 4 % de la largeur → 1,5 px.
   Agenda : les notes sont annoncées par un halo en dégradé radial, jamais une arête.
5. **Rubato interne** — stries : `sOff = (1 − qR) · 0,03 · dir` (photo pivotée, courbe `qR` qui
   démarre à 30 % du temps de la strie, donc plus lente que le bord `qC`) ; bandeau : glissement
   horizontal `(1 − qR) · 0,02`. `uSlide` = 0 en mouvement réduit.
6. **Respiration continue** — `applyBreath()` : échelle du mesh ± 0,3 % (`breath` 0,003) sur
   8 s (`breathPeriod`), `uBreath` déplace la lumière de 0,6 % de la largeur. Rendu continu
   SEULEMENT photo visible et onglet actif, à ~30 i/s hors scroll (`breathInterval` 33 ms) ;
   sinon rendu à la demande. Coupée si `prefers-reduced-motion`. La ligne « défiler » respire
   sur 6 s (`@keyframes breathe`).
7. **Lumière avant découpe** — pour chaque strie et voile : `qL = phrase(tt / 0,7)`,
   `qC = phrase((tt − 0,22) / 0,78)`, lumière = `qL · (1 − qC)` dans une boîte large et diffuse
   (`lW`), couleur `light.color` par composition (doré du bokeh / bleu de scène / crème),
   `light.gain` 0,08 à 0,11 ; le bandeau a sa propre lumière `bL`, 0,10 avant la première pièce,
   éteinte sous les pièces puis en fin de partition. Graduation chromatique du DA (`grade`) :
   bleuse-1 → sépia / crème à 0,35 (toute l'image), bleuse-3 → lavande / bleu nuit à 0,40 hors
   sujet (masque), bleuse-5 → sépia doux à 0,2 sous les voiles crème #F3EAD8 (alpha 0,30, valeur
   mesurée sur la maquette du DA). Agenda : halo (lumière) puis point (forme) puis texte.
8. **Densité faible** — le texte posé sur une photo n'entre qu'**après la pose** de la composition
   (`t0 = p1`, `measure()`) ; les stries précèdent le bandeau ; les sections noires ne font entrer
   que du texte. Le bloc de l'ouverture entre au chargement, avant tout scroll. La nav s'efface
   devant un texte qui passe dessous (une seule chose à lire à la fois).
9. **Silence** — section `.rest` (marges 18 / 20 rem), agenda (16 / 18 rem), espace noir réservé
   sous bleuse-5 (`gap`, ~50 px sur 1440 × 900) puis colophon ; sur ordinateur, le ralenti laisse un
   temps noir entre deux photos.
10. **Échelle du geste** — translations ≤ 3 % (stries) / 2 % (bandeau), échelle 0,3 %,
    montée des lignes 10 px ; fenêtres : hero du haut 0,6 écran, hero du milieu 0,85 (0,7 en
    tactile), hero de pied 0,6 ; un bloc de texte jusqu'à 1 écran, portée 1 écran.
11. **Synchronie exacte** — `StripesHero.update(scrollY)` et `Choreo.update(scrollY)` à chaque
    frame, sans lissage ni chronomètre. Lenis ne lisse que le scroll lui-même (souris / trackpad),
    jamais en tactile. La nav (opacités, voile) est aussi une fonction de la position, sans
    transition. Vérifié : à scrollY identique, aller puis retour donnent des PNG identiques octet
    pour octet.
12. **Typographie** — `splitLines()` découpe chaque `[data-lines]` en lignes réelles
    (`.line > .line__in`), justification conservée (`text-align-last`) ; retard 0,09 écran par
    ligne (≈ 80 à 120 ms à vitesse de lecture), flou 2 px → net, montée 10 px, courbe `phrase()`.
    Jamais lettre par lettre. Redécoupe à l'arrivée des polices (sans rejouer l'entrée de
    l'ouverture) et au changement de largeur ; `text-wrap: balance` sur les titres de l'agenda.

## Géométrie du scroll (`measure()`, par hero)

- Hero du haut : composition de 0 à `progressTop` · vh (0,6) ; à p = 1 le cadre a remonté de
  302 px sur 1440 × 900 (ralenti 0,5 : la photo absorbe la moitié de la vitesse au départ) — les
  yeux restent visibles, pas le front. Sans épinglage, c'est la limite d'une fenêtre de 0,6 écran.
- Hero du milieu : fenêtre de `c − 0,75 vh` à `c + 0,1 vh` (c = photo alignée en haut) ; à p = 1
  le cadre est à −45 px : le tableau (bleuse-4) est vu entier. Texte sur 0,6 vh ensuite.
- Hero de pied : la fin de page impose tout. On veut le cadre à `poseOffset` · vh du haut à la
  pose (0,04 ; 0,25 en tactile) ET la fenêtre du texte après la pose (`textSpanBottom` 0,42 ; 0,35
  en tactile) : `measure()` calcule l'espace noir `gap` à réserver sous la photo
  (`sEnd = reserve − off + L·smooth01((h − reserve)/2h)`). Fin de page : cadre à −185 px
  (desktop), −(hauteur du colophon) en tactile.
- `frameTopAt(s)` donne la position du cadre pour un scroll s (utilisé par les tests).

## Réglages (où les toucher)

| Réglage | Valeur | Où |
|---|---|---|
| Courbe (palier, point d'inflexion) | 10 % à 1/3 | `PHRASE_K`, `PHRASE_A` — `stripes-effect.js` + `PHRASE_GLSL` |
| Étalement des pupitres (temps de partition) | bleuse-3 `spread` 1,15 (61 % de la course), `jitter` 0,03, paires | `pupitres()`, presets `stripeGrid` / `screenGrid` |
| Retards du bandeau | 0 / 0,32 / 0,18 / 0,50 (segments 1-2-3-4), `start` 0,45 | preset `bleuse-1`, `band.segments[*][4]` |
| Voiles | retards 0 / 0,75, `veilDuration` 0,5 ; `alpha` 0,30 (DA mesuré : blanc 0,30) ; `grade.mix` 0,2 | preset `bleuse-5` |
| Durées (temps de partition, normalisées) | `stripeDuration` 0,70 / 0,75, `bandDuration` 0,50 | presets |
| Décalage lumière / découpe / rubato | 0,7 · 0,22 · 0,30 | `buildStripesFragment()` (`qL`, `qC`, `qR`) |
| Plume initiale | `PLUME` 0,25 de la largeur ; voiles 0,04 ; `soft` 0,22 → 0,05 | `buildStripesFragment()` |
| Amplitude du rubato | 0,03 (stries), 0,02 (bandeau) | `buildStripesFragment()` |
| Couleur et gain de la lumière | `light: { color, gain }` | presets |
| Gradations | `GRADE.sepia / lavande / creme`, `grade.mix`, `grade.on` (`all` / `outside` / `veils`) | presets |
| Ombre sous le texte de bleuse-5 | `textShade` 0,45 (0 = aucune), plume 0,045 / 0,06 d'écran | preset `bleuse-5.desktop`, shader (« 4 bis »), `layout()` |
| Bandeau portrait | `y0` 0,68, `y1` 0,76 (desktop : 0,745 / 0,824, maquette) | preset `bleuse-1.portrait` |
| Respiration | `breath` 0,003, `breathPeriod` 8 s, `breathInterval` 33 ms ; `setBreath(false)` pour couper | `params`, `applyBreath()` |
| Ralenti | `linger` 0,5, `lingerSpan` 1,0 (0 en tactile) | `params` |
| Fenêtres de composition | `progressBefore` 0,75 / 0,65, `progressAfter` 0,1 / 0,05, `progressTop` 0,6, `progressSpanBottom` 0,6, `poseOffset` 0,04 / 0,25 (desktop / tactile) | `params`, `measure()` |
| Texte sur photo | après la pose (`textGap` 0), `textSpan` 0,6 / 0,5, `textSpanBottom` 0,42 / 0,35 | `params`, `measure()` |
| Lignes | `stagger` 0,09, `duration` 0,30, `maxSpan` 1,0, `startAt` 0,88, `rise` 10, `blur` 2 | `T` dans `choreo.js` |
| Portée et notes | `staffStart` 0,85, `staffSpan` 1,0, `noteDot` 0,22, `noteHalo` 0,16, `noteText` 0,06, `data-delay` des notes | `T` dans `choreo.js`, `index.html` |
| Nav qui s'efface | zones, `margin` 20 px, `fade` 48 px, voile entre 5 et 20 % d'écran | `NAV` dans `choreo.js` ; `.nav::before` dans `styles.css` |
| Positions des textes sur photo | `.hero__text--tr` (22 %), `--r` (56 %), `--r5` (40 %) | `styles.css` |
| Colophon | padding 3,5 rem (desktop), 2 rem (mobile) — sa hauteur = coupe du cadre en fin de page | `styles.css` |

## Mobile, repli, accessibilité

- Portrait (ratio < 0,9) : compositions `portrait` des presets (coordonnées écran), point focal
  gardé ; textes en bas sur dégradé sombre (bleuse-1 sous le bandeau) ; portée verticale ;
  `100svh`, gouttières 16 px, pas de Lenis, pas de ralenti.
- Canvas décoratif : `pointer-events: none; touch-action: pan-y`, EventSystem de Pixi retiré ;
  `<img>` de repli sous chaque canvas, même cadrage (`objectPosition`), affichée si WebGL échoue
  (`is-fallback`) ou si le contexte est perdu.
- `prefers-reduced-motion` : shaders « fondu » (plus de volets, de lumière, de rubato), pas de
  respiration ni de ralenti, textes en simple fondu ; la nav s'efface toujours (opacité seule).
- Mode dégradé : sans GSAP → boucle rAF ; sans Pixi ou sans moteur → photos seules, textes
  intacts ; sans `main.js` → `js-timeout` après 4 s, tout visible sans chorégraphie.
- Logo : « Pierre » en italique serif au-dessus de « Bleuse » en gras, les deux e retournés
  (`transform: scaleX(-1)`) — l'`aria-label` dit « Pierre Bleuse », `data-logo` porte la graphie
  « Pierre Blɘusɘ ».

## Protocole de test (22/10/2026 soir, Chrome 155 headless, WebGL SwiftShader, serveur 8787, CDP 9367)

0 erreur console, 0 exception, 0 requête en échec sur tous les scénarios (desktop 1440 × 900,
mobile 390 × 844 dpr 3 tactile UA iPhone, mouvement réduit, 6 scénarios de robustesse) ; captures
desktop à 6 positions (+ 6 intermédiaires), mobile à 4 (+ 8) ; planches-contact de l'ouverture
(12 images, 0 → 1 écran, desktop ; 8 images mobile), du recouvrement texte / stries de bleuse-3,
du pied de page, de la photo 2 mobile ; règle 8 : 0 ligne de texte visible tant que la composition
n'est pas posée (34 positions) ; nav : 0 recouvrement avec une partie visible (26 positions) ;
contraste du bloc de bleuse-5 : 6,35:1 moyen, 4,6:1 sur la pire bande de 20 px ; glissé tactile
depuis les trois photos : Δ ≈ +500 px (fin de page : +471) ; même scrollY → PNG identiques octet
pour octet (aller puis retour, 4 positions, respiration coupée) ; rendu à la demande : 0 rendu en
1,5 s immobile sans respiration (≈ 23 i/s avec) ; cadence pendant un scroll molette réel : 30 à
45 rAF/s avec respiration, 47 à 53 sans — en RENDU LOGICIEL, valeur indicative, à refaire sur GPU
(`--use-angle=metal`) ; mouvement réduit émulé sans erreur. Captures, planches et scripts dans
le dossier de travail hors projet (`scratchpad/fix-h1-rubato/`).

## Limites connues

- Hero du haut : à p = 1 le cadre a remonté de 302 px (1440 × 900) — les yeux du chef restent
  visibles, le front est coupé. Sans épinglage et avec une fenêtre de 0,6 écran, un ralenti de
  0,5 ne peut pas faire mieux ; `linger` 0,65 donnerait −231 px mais s'approche d'une butée.
  Sur mobile (pas de ralenti), le visage est sorti de l'écran à p = 1 de l'ouverture.
- La nav s'efface pendant qu'un texte passe dessous : sur mobile, elle est donc absente pendant
  la plus grande partie des sections de texte (respiration, agenda) et revient sur les photos.
- Plume d'apparition fixée à 25 % de la largeur de l'élément (et non 6 à 10 %) : sur des stries
  de 30 px, 8 % ferait moins de 3 px, invisible — écart volontaire, l'intention (large d'abord,
  net ensuite) est gardée.
- La portée se trace de gauche à droite sur ordinateur (lecture d'une partition) : exception
  assumée à la règle 2 pour la ligne seule ; les notes entrent par groupes irréguliers. Sous
  900 px elle devient verticale.
- Hero de pied : la fenêtre du texte est resserrée (0,42 écran, retards des lignes × 0,5) pour
  que la tête du chef reste entière en fin de page ; en tactile, la composition se pose avec le
  cadre encore à 25 % du haut (sinon le texte n'aurait plus de place avant le colophon) et la
  fin de page coupe le cadre de la hauteur du colophon (~136 px), la tête restant entière.
- Voiles de bleuse-5 : alpha 0,30 mesuré sur la maquette du DA, contre 0,45 dans le SPEC — à
  trancher avec lui (un seul nombre à changer : `veils.alpha`).
- Le rideau de chargement (logo, texte d'ouverture, indication « défiler ») est la seule entrée
  temporelle hors ambiances : une fois, au chargement, avant tout scroll.
- La respiration implique un rendu continu (~23 à 30 i/s) tant qu'une photo est visible : coût
  batterie modéré ; `setBreath(false)` ou `breath: 0` la coupe (le rendu redevient à la demande).
- Les e retournés du logo sont dessinés par CSS, pas par le glyphe U+0258 (absent de Cormorant).
- Textes : lorem ipsum ; citation presse = emplacement ; pas de son.
