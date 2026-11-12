# Hypothèse 4 — Vol d'étourneaux, la nuée et le geste

**Pitch.** Des dizaines de milliers de points, comme un vol d'étourneaux, traversent
l'image du chef. La nuée s'étire en traîne, se ramasse en masse, se déploie en voile,
se disperse et se reforme : c'est la musique qui la conduit. Quand on quitte la zone
vidéo, la nuée s'apaise et se fait fil, et elle accompagne la lecture de la page.

**Statut.** Expérimentation de faisabilité, pas une maquette finie. Elle doit permettre
de juger trois choses : la crédibilité artistique (fidèle à `vol1/2/3.png`), la
fiabilité de la synchronisation musicale (son audible, son coupé, lecture muette en
fond) et la continuité avec le scroll. Elle reste indépendante des autres hypothèses.

**Ce que ça dit de l'excellence.** Une masse de voix qui bouge comme un seul corps, sans
chef visible : la cohésion d'un orchestre, rendue par le comportement collectif le plus
spectaculaire de la nature, mais traitée avec retenue (points fins, lumière froide,
dynamique de la musique plutôt que de la pulsation).

---

## 1. Matière

| Fichier | Rôle |
|---|---|
| `assets/image-sans-vol.png` | **Fond imposé**, 1920 × 1080. Image de la captation de Prades 2022 (Tchaïkovski, à 56:39). Très sombre (luminance médiane 3/255). |
| `assets/masque-lecture.png` | Calque correctif 80 × 80 px qui masque le pictogramme ▶ incrusté dans le fond. À poser en (925, 525) px image, soit `left: 48,177 %`, `top: 48,611 %`, `width: 4,1667 %`, `height: 7,4074 %` du rectangle de l'image. Activé par défaut, désactivable. Le PNG de fond n'est pas modifié. |
| `assets/chef-masque.png` | Silhouette du chef (blanc = chef), 480 × 270, Apple Vision (`outils/seg.swift`). Sert au « visage dégagé » et au passage de la nuée derrière le chef. |
| `assets/vol1.png`, `vol2.png`, `vol3.png` | Les trois états de référence (composites). |
| `assets/vol*_layerUp.png` | Les nuées seules. Recalées au pixel près sur le fond. `vol1_layerUp` porte une opacité de 50 % dans son alpha ; les bandes blanches de vol1 (droite) et vol2 (gauche) sont des pixels d'alpha 0. |
| `assets/media/pathetique-iv-extrait.m4a` | Bande-son de test, **domaine public** : Tchaïkovski, Symphonie n° 6 « Pathétique », IV, Musopen Symphony Orchestra (Wikimedia Commons), extrait 5:08 → 9:12, AAC 160 kbit/s, 244 s. Calme, montée, apogée (~1:30-2:20), effondrement et quasi-silence (~2:50-3:30), reprise. |
| `assets/media/captation-test.mp4` | « Vidéo hébergée » de test (scénario B) : l'image imposée en H.264 25 i/s + la même bande-son. Visuellement identique au fond. |
| YouTube `xqEzYw4YqRw` | Captation Festival Pablo Casals 2022 (Dvořák · Tchaïkovski, D. Müller-Schott, P. Bleuse), chaîne du Festival de Prades. Extrait de test : **3399 → 3699 s**, en boucle. |

## 2. Ce que disent les références (mesures, voir `FAISABILITE.md`)

- **Mélange : éclaircir (lighten)**, au bit près sur vol2 et vol3 : `C = (1 − a)·B + a·max(B, L)`.
  vol1 est à `a ≈ 0,5`. Conséquence voulue : les parties claires du chef (chemise,
  manchettes, peau) traversent toujours la nuée, qui domine les zones sombres.
- **Grain** : taches gaussiennes de σ ≈ 1,25 px image (largeur à mi-hauteur ≈ 2,9 px à 1080 px
  de haut), tailles log-normales (p10/p90 ≈ 0,6× / 1,4×), 30 à 40 % de tirets courts
  (allongement 1,6 à 2,4) d'orientation aléatoire, sauf dans les traînes rapides.
- **Lumière** : chaque point est pâle (pic médian ≈ 30/255, p90 ≈ 60) ; les cœurs sont des
  superpositions additives (plus d'un point par px²) qui saturent vers le blanc.
  < 1 % de pixels blancs purs. 1 à 3 % d'« étincelles » isolées, grosses et vives.
- **Couleur** : une rampe fixe appliquée à la luminance cumulée, identique dans les trois
  états : Y 0 → (0,0,0) ; 12 → (4,13,28) ; 40 → (35,45,59) ; 128 → (124,133,145) ;
  200 → (197,205,214) ; 236 → (230,237,243) ; 255 → (250,253,254). Les points épars
  sont bleu nuit, les cœurs blanc froid.
- **Brume** : seulement dans vol3. Brouillard ardoise au sol, nul au-dessus de y = 0,5,
  luminance ≈ 28 (≈ rgb(37,45,60)) à y ≥ 0,85, plus forte sur les bords. Elle voile
  l'orchestre en bas, jamais la chemise.
- **Les trois figures** (coordonnées normalisées de l'image, origine en haut à gauche) :
  - **Traîne (vol1)** : filets fins et multiples qui entrent par la gauche (y 0,15-0,45),
    descendent vers la droite (~26°) et s'épaississent en une tête dense et repliée sur
    le buste ; nœud brillant vers (0,55-0,75 ; 0,60-0,90), coque en arc jusqu'à x 0,9.
    Barycentre (0,61 ; 0,59). 39 % du cadre. Le bras à la baguette reste dégagé.
  - **Masse (vol2)** : corps compact et dense (x 0,33-0,70 ; y 0,30-0,90), panache qui
    monte vers le haut à gauche (x 0,45-0,60 ; y 0,02-0,30), satellite rond à gauche
    (x 0,17-0,40 ; y 0,28-0,52) relié par un col fin, quelques grosses étincelles isolées.
    Bords nets, pas de brume. 29 % du cadre.
  - **Voile (vol3)** : draperie de nappes repliées sur tout le cadre (aile haute gauche,
    aile haute droite en arc au-dessus de la tête, pli diagonal sur la barbe et la main,
    pli lumineux en (0,80 ; 0,75), nappe basse sur la brume). Arêtes lumineuses là où les
    nappes se présentent par la tranche. **Vide volontaire sur les yeux.** 83 % du cadre.
- **Repères du fond** : yeux (0,586 ; 0,315) ; tête x 0,544-0,690, y 0,156-0,435 ;
  pointe de baguette (0,343 ; 0,320) ; main à la baguette (0,361 ; 0,550) ; main levée au
  visage (0,643 ; 0,386) ; manchette (0,695 ; 0,489) ; plastron (0,572 ; 0,729) ;
  nœud papillon (0,615 ; 0,545).

## 3. Système

### 3.1 Rendu (un seul contexte WebGL2)

- **Couches** : `<img>` du fond (intact, sert aussi de repli) → canvas de la nuée en
  `mix-blend-mode: lighten`, opaque noir là où il n'y a rien (le noir est neutre pour
  « éclaircir ») → textes et commandes. **Opacité globale 0-100 % = `opacity` CSS du
  canvas** : instantanée, sans nouveau rendu, et à 0 l'image est rendue à l'identique.
- **Canvas unique `position: fixed`** couvrant la fenêtre, au-dessus des fonds et sous le
  contenu. Dans l'ouverture, la nuée est calée sur le rectangle rendu de l'image
  (`object-fit: cover` + point focal) recalculé à chaque image ; au-delà, elle se cale
  sur la fenêtre. Sur écran tactile le défilement natif peut faire « glisser » la nuée
  d'une image pendant un lancer : les effets calés au pixel (visage, passage derrière le
  chef) s'effacent dès que le scroll commence. Limite documentée, piste de production
  dans `FAISABILITE.md`.
- **Particules sans état** : un seul `gl.drawArrays(gl.POINTS, 0, N)` sans attribut ; chaque
  point est identifié par `gl_VertexID`, ses graines viennent d'un hachage entier (les
  identifiants sont mélangés : tout préfixe 0..N est un échantillon uniforme, donc la
  densité se règle par le nombre dessiné). Position = fonction pure de (graines,
  uniformes). Même état d'entrée → image identique à l'octet près.
- **Accumulation puis rampe** : les points (gaussiennes) s'additionnent dans un tampon de
  luminance hors écran (demi-flottant si `EXT_color_buffer_half_float`/`float`, sinon
  RGBA8 avec alpha ≥ 4/255), puis une passe plein écran applique la rampe de couleur, la
  brume et un léger tramage anti-bandes. Repli si le tampon échoue : sprites à cœur
  #DCE4EE et halo #0A1524.
- **Profondeur** : légère perspective (focale ≈ 1500 px image) ; près = plus gros et plus
  lumineux (±40 % de taille, ×2,5 de lumière entre loin et près). Les arêtes lumineuses
  des plis naissent d'elles-mêmes quand une nappe se présente par la tranche.
- **Taille** : FWHM = 2,9 px image × échelle de couverture × réglage « taille », minimum
  1,5 px CSS ; quand le minimum agrandit un point, son alpha est réduit de
  (taille voulue / taille affichée)² pour éviter le scintillement.
- **Tirets** : capsule orientée selon la vitesse (différence finie de la position) dans le
  sprite, réglage « traînées ».
- **Visage dégagé** : répulseur doux autour des yeux (0,59 ; 0,315), rayon ≈ 0,06 de la
  largeur, plus atténuation par le haut du masque du chef. **Passage derrière le chef** :
  les points de profondeur négative sont atténués par `chef-masque.png`.
- `antialias: false`, `premultipliedAlpha: true`, `failIfMajorPerformanceCaveat: true`
  (sinon : image seule), DPR plafonné (2 ordinateur, 1,5 tactile), `highp` partout dans
  le vertex shader, temps replié côté JS, `webglcontextlost` → arrêt et image seule,
  `webglcontextrestored` → recompilation.

### 3.2 Mouvement : un corps procédural déformable

Ni boids ni simulation : un état chaotique ne se rembobine pas (seek, scroll arrière) et
coûte trop sur mobile. Le comportement collectif est **dessiné** :

- **Squelette** calculé côté JS en double précision à chaque image, fonction pure de la
  phase musicale Φ, des poids des figures, des pulsions et du scroll : une épine (6 à 8
  points de contrôle), un profil de largeur, une épaisseur, une torsion, des filets de
  traîne, un satellite, une amplitude de bruit. Envoyé en uniformes.
- **Chaque point** a une coordonnée le long du corps `u` (0 = tête, 1 = queue), deux
  coordonnées de section `a, b`, un groupe (corps / satellite / filet / étincelle), une
  luminosité et une taille. Position = épine(Φ − retard·u) + repère tordu × section
  (nappe, tube ou disque selon la figure) + bruit cohérent dans l'espace du corps (les
  voisins restent voisins, comme dans un vrai vol).
- **Retard le long du corps** : la queue suit le chemin de la tête avec un retard ; les
  attaques musicales se propagent de la tête à la queue à vitesse finie (les « vagues »
  des vols d'étourneaux).
- **Figures** : `traine`, `masse`, `voile` (les trois références) et `fil` (forme discrète
  du scroll : filets très fins et clairsemés, comme la traîne de vol1). Une figure est un
  jeu de paramètres du squelette ; les transitions interpolent ces paramètres sur 2 à 6 s
  avec des retards étalés par points (règle 2, « entrées en pupitres »), jamais un fondu
  entre deux nuages sans rapport.
- **Cadrage** : les figures sont décrites dans l'espace de l'image et rapprochées de la
  fenêtre visible quand le cadrage `cover` rogne (portrait) : sur téléphone la nuée
  reste dans le tiers visible autour du chef.
- **Jamais répétitif** : périodes incommensurables, bruit 1D de longue période pour le
  squelette, ordre des figures dicté par la musique.

### 3.3 Musique → mouvement (interprétation, pas égaliseur)

| Caractéristique (0-1) | Effet |
|---|---|
| `energie` (sonie lissée) | vitesse de la phase Φ (intégrée : `Φ(t) = base·t + gain·∫energie`), amplitude des déformations, ouverture du corps |
| `attaque` (force d'attaque) | vague qui parcourt le corps de la tête à la queue, léger frisson de densité |
| `brillance` (centroïde spectral) | lumière et finesse des points, tension des nappes |
| `graves` / `mediums` / `aigus` | poids de la masse / largeur des nappes / scintillement des bords |
| `nouveaute` + `ruptures` | changement de figure, dispersion puis regroupement sur 3 à 5 s |
| silence | vol lent et resserré, respiration (règle 6) |

L'analyse préalable fournit une **anticipation** de 0,2 à 0,4 s (l'anacrouse : la nuée
commence à se rassembler avant le tutti). Pas de suivi de tempo (le rubato orchestral
met les détecteurs en échec).

### 3.4 Synchronisation

Principe retenu, valable que le son soit audible ou non : **analyse préalable de la
bande-son + pilotage par la position de lecture**.

- **Horloge virtuelle** (au chargement, sans son, sans consentement) : la nuée « joue »
  l'extrait en silence, en boucle. **Écouter** reprend le média exactement à cette
  position, avec le son : la musique arrive sur un mouvement qui la suivait déjà.
- **Horloge média** (vidéo ou audio hébergés) : `currentTime` lu à chaque image, servo
  monotone qui cesse d'extrapoler si `currentTime` reste figé > 50 ms, recalage sur
  `seeked`, `ratechange`, `waiting`/`playing`, lissage de l'escalier de Firefox (pas de
  40 à 50 ms), écart mesuré contre `currentTime` pour le HUD.
- **Horloge YouTube** : `getCurrentTime()` comme observation, extrapolation par
  `performance.now()`, absorption progressive des petits écarts, recalage au-delà de
  250 ms, gel hors lecture, ancrage immédiat sur nos propres `seekTo`.
- **Analyse en temps réel** (comparaison uniquement, après un clic) : `AnalyserNode` sur
  le média hébergé. Le HUD montre côte à côte la valeur pré-analysée et la valeur
  mesurée : vidéo coupée → zéros (Chrome, Firefox), et retard de l'analyseur.
- **Conduite manuelle** (YouTube sans fichier maître) : pendant la lecture, on « dirige »
  au clavier (1 à 5 = niveau, R = rupture, retour arrière = annuler). Les temps viennent du
  lecteur, rien n'est capté du son. Enregistrée localement, exportable en JSON, et elle se
  comporte comme une piste pré-analysée.

### 3.5 Scroll

- `p` (0 → 1) : progression de la sortie de l'ouverture, fonction pure de `scrollY`.
  Mélange de figure vers `fil`, nombre de points × d(p), taille, opacité CSS,
  amplitude ; ancrage image → fenêtre. Ralentir = déformer le temps (on intègre
  `vitesse(p)·dt`), jamais multiplier `t`.
- La vitesse de scroll lissée donne un léger cisaillement (la nuée « traîne » comme un
  tissu), qui retombe dès qu'on s'arrête.
- Aux frontières de sections, les fils se resserrent légèrement (fonction du scroll).
- Règle 11 : tout ce qui dépend du scroll est réversible ; seule la vie propre de la nuée
  (horloge) tourne en temps réel.

### 3.6 Fonds testés

| Fond | Horloge | Remarque |
|---|---|---|
| **Image fixe** (défaut, imposé) | virtuelle, puis média audio à l'écoute | Le cas principal. |
| **Vidéo hébergée** (scénario B) | média vidéo (lecture muette par script, puis son) | Mêmes mécanismes qu'une vraie vidéo : autoplay muet, `currentTime`, `requestVideoFrameCallback`, analyse temps réel en comparaison. |
| **YouTube** (scénario A) | YouTube | Chargé seulement après un clic qui vaut consentement (CNIL). **Disposition conforme** : lecteur 16:9 entier, jamais recouvert ni rogné, nuée **derrière et autour**. **Aperçu superposé** (désactivé par défaut) : nuée par-dessus le lecteur, *non conforme aux règles YouTube, test interne uniquement*. Pilotage : conduite manuelle, ou analyse du fichier maître quand il sera fourni. |

## 4. Réglages

Panneau vanilla (sans dépendance), ouvert par le bouton « Réglages » ou la touche `P`.
Trois contextes, chacun avec son jeu de valeurs : **vidéo** (ouverture, musique en
cours), **ambiance** (ouverture sans musique), **scroll** (sous l'ouverture). Le mélange
entre contextes suit l'état réel (musique lancée ou non, `p`). Liste, bornes et valeurs
par défaut : `js/parametres.js` (source unique). Copier / coller en JSON, réinitialiser,
mémorisation locale. Commandes globales : fond, écoute, son coupé / inaudible / audible,
analyse temps réel, figure forcée, masque ▶, référence A/B (`volN_layerUp.png` en
« éclaircir » sur le fond, pour comparer), HUD, pause de l'animation.

## 5. Accessibilité et performance

- Bouton **Pause / Reprendre l'animation** toujours accessible (WCAG 2.2.2).
  `prefers-reduced-motion` : une image fixe de la nuée, pas de boucle.
- Le son ne démarre que sur un geste explicite (clic, touche), jamais au scroll.
- Arrêt de la boucle si l'onglet est caché ; reprise directement à l'instant courant.
- Budgets indicatifs : ordinateur 100 à 250 k points, iPhone récent 80 à 150 k, Android
  moyen de gamme 30 à 60 k. Qualité adaptative sur l'intervalle entre images (N puis DPR).
- Lenis sur ordinateur seulement, défilement natif au doigt ; canvas
  `pointer-events: none; touch-action: pan-y`.

## 6. Fichiers et contrat entre modules

```
index.html            page (cinq temps : ouverture, respiration, deuxième photo, agenda, pied de page)
css/etourneaux.css
js/parametres.js      schéma des réglages (source unique)               → window.H4Parametres
js/nuee.js            moteur WebGL2 + chorégraphie                       → window.Nuee
js/musique.js         pistes pré-analysées, conduite, analyse temps réel → window.Musique
js/sync.js            horloges virtuelle / média / YouTube, lecteur      → window.Sync
js/reglages.js        panneau de réglages                                → window.Reglages
js/main.js            amorçage, fonds, scroll, câblage, HUD, API de test → window.__h4
data/pathetique-iv-extrait.json   caractéristiques pré-analysées (30 Hz, uint8)
outils/analyse-bande-son.py       ffmpeg → JSON (rejouable sur le fichier maître)
```

### Piste (pré-analysée ou conduite) — `Musique`

```js
const piste = await Musique.chargerPiste('data/pathetique-iv-extrait.json');
piste.fps; piste.duree; piste.offset;          // offset = temps média de la trame 0
piste.at(t)    // → { energie, attaque, brillance, graves, mediums, aigus, nouveaute } (0-1, interpolé ; 0 hors plage)
piste.evenements // → { ruptures: [{ t, force }], attaques: [{ t, force }] }  (temps média)
piste.cumul(t)   // → ∫ energie dτ de offset à t (sommes préfixées, O(1)) ; Φ = base·(t−offset) + gain·cumul(t)
const conduite = Musique.conduite({ cle: 'h4-conduite-xqEzYw4YqRw' }); // même interface que piste + enregistrer(t, niveau) / rupture(t) / annuler() / exporter() / importer(json)
const direct = Musique.analyseDirecte(mediaElement); // après un geste : direct.valeurs() → mêmes clés ; direct.mode = 'coupe' | 'inaudible' | 'audible'
```

Format JSON : `{ version, source, titre, interpretes, licence, offset, fps, duree,
anticipation, canaux: [...], donnees: { energie: [0-255…], … }, evenements: { ruptures,
attaques } }`.

### Horloges — `Sync`

```js
const lecteur = Sync.lecteur({ media, boucle: [debut, fin], autoplayMuet }); // horloge virtuelle puis média
lecteur.temps(); lecteur.enLecture; lecteur.source;   // 'virtuelle' | 'media' | 'youtube'
lecteur.ecouter(); lecteur.pause(); lecteur.aller(t); lecteur.son('coupe' | 'inaudible' | 'audible');
lecteur.relacher();                                    // fin d'écoute : pause du média, l'horloge virtuelle repart à la même position
lecteur.ecartMs(); lecteur.etat;                       // horloge − temps brut du média (HUD) ; explication en clair
const yt = await Sync.youtube(conteneur, { videoId: 'xqEzYw4YqRw', debut: 3399, fin: 3699 }); // après clic de consentement
yt.lecteur  // même interface ; yt.player = YT.Player
```

### Moteur — `Nuee`

```js
const nuee = Nuee.create(canvas, { masque: imgChef, maxPoints, dprMax, tactile });
nuee.ok;                                           // false → image seule
nuee.layout({ largeur, hauteur, dpr, rectImage: { x, y, w, h } }); // px CSS dans le canvas
nuee.frame({
  mur, dt,                         // secondes horloge murale (vie propre, respiration)
  media: { t, enLecture },         // temps musical
  piste,                           // piste ou conduite active (déterministe), ou null
  direct,                          // valeurs d'analyse temps réel, ou null
  scroll: { p, v, frontiere },     // 0-1, px/s, proximité d'une frontière 0-1
  params,                          // réglages déjà mélangés entre contextes
  figure,                          // null (auto) | 'traine' | 'masse' | 'voile' | 'fil'
  reduit,                          // mouvement réduit : image fixe
});
nuee.stats();                      // { points, ips, ms, figures: {traine, masse, voile, fil}, phase }
```

Chaque figure porte aussi un gain de sortie `sortie` (traîne 0,54 : l'opacité du composite
vol1), appliqué après la rampe.

Déterminisme : à `mur` gelé, mêmes `media.t`, `piste`, `scroll.p`, `params`, `figure`
→ image identique. API de test (`window.__h4`) : `geler(t)`, `degeler()`, `scroll(p)`,
`figure(nom)`, `contexte('video' | 'ambiance' | 'auto')`, `etat()`, `relayout()`.
La conduite expose aussi `vide` et `revision` (incrémentée à chaque modification).
