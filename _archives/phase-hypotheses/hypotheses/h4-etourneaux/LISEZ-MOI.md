# Hypothèse 4 — Vol d'étourneaux (la nuée et le geste) · LISEZ-MOI

Prototype de faisabilité construit le 27/10/2026 d'après `../BRIEF-COMMUN.md`, `SPEC.md`
(spécification et contrat entre modules) et `FAISABILITE.md` (recherche). Trois modules
écrits en parallèle (moteur, musique, page), puis intégrés et vérifiés ensemble.

Ouvrir : `http://localhost:8888/pierrebleuse/hypotheses/h4-etourneaux/` (MAMP).

## Ce qui est fait

Une couche indépendante de **nuages de points façon vol d'étourneaux** (35 000 à 140 000
points par défaut, 250 000 au plus) au-dessus de l'image imposée, qui reste intacte. La nuée
est **conduite par la musique** (position de lecture + analyse préalable de la bande-son),
la synchronisation tenant que le son soit audible, coupé ou lu en silence, et **apaisée par
le scroll** : sous l'ouverture elle se fait fil et accompagne la lecture de la page.

- **Rendu** : un seul canvas WebGL2 fixe, en `mix-blend-mode: lighten` (le mélange mesuré
  au bit près sur vol2 et vol3) : la chemise, les manchettes et la peau traversent toujours
  la nuée, qui domine les zones sombres. Opacité globale = `opacity` CSS du canvas ; à 0 le
  canvas n'est plus composé du tout et l'image est rendue à l'identique (voir Vérifications).
- **Points sans état** : chaque point est une fonction pure de ses graines et d'un squelette
  calculé en JS à chaque image (épine de 8 nœuds, satellite, filets, étincelles) ; même temps
  musical, même scroll, mêmes réglages → même image à l'octet près.
- **Quatre figures** : `traine` (vol1), `masse` (vol2), `voile` (vol3) et `fil` (forme
  discrète du scroll). Les transitions interpolent le squelette de la tête à la queue, en
  2 à 6 s, avec des retards étalés (« entrées en pupitres »), jamais un fondu entre deux
  nuages. vol1 est composée à ~54 % d'opacité dans la référence : la traîne porte ce gain
  (`sortie`) dans le moteur, vol2 et vol3 sont à 100 %.
- **Musique** : phase Φ = 0,55·t + 0,9·∫énergie (le vol accélère avec la sonie), figures
  choisies aux ruptures de la pièce (graves et énergie → masse, médiums et brillance →
  voile, calme → traîne), vagues d'attaque de la tête à la queue, dispersion puis
  regroupement après une rupture, vol lent et resserré dans le silence, respiration ±0,4 %.
- **Visage dégagé** autour des yeux, **passage derrière le chef** (masque
  `chef-masque.png`), **brume** au sol avec le voile, **masque ▶** sur le pictogramme
  incrusté dans le fond.

### Les cinq temps

1. **Ouverture** — `assets/image-sans-vol.png` (Prades 2022) en `object-fit: cover`, point
   focal 60 % 40 % (62 % 40 % en portrait) ; la nuée calée sur le rectangle rendu de l'image ;
   petit bloc de lorem, **Écouter**, crédit de la bande-son, choix du son (coupé, inaudible,
   audible) ; indice « Défiler ».
2. **Respiration** — section noire, lorem et emplacement de citation presse ; la nuée y est
   devenue fil (filets clairsemés calés sur la fenêtre).
3. **Deuxième photo** — `bleuse-3`, la nuée discrète passe dessus (visible dans les zones
   sombres seulement, par construction du mélange « éclaircir »).
4. **Agenda** — les cinq temps forts (exemples), posés comme des oiseaux sur un fil.
5. **Pied de page** — `bleuse-5`, puis la ligne de pied de page avec le crédit musical.

### Les trois fonds de l'ouverture

| Fond | Horloge | Comportement |
|---|---|---|
| **Image fixe** (défaut) | virtuelle en boucle, puis média audio à l'écoute | Rien n'est téléchargé avant « Écouter » (`<audio preload="none">`). Écouter reprend le son exactement à la position de la nuée ; la fin de l'écoute arrête le son et la nuée continue sur l'horloge virtuelle, sans saut. |
| **Vidéo hébergée** (`?fond=video`) | média vidéo | `captation-test.mp4` (l'image en H.264 + la même bande-son) lancée en muet par script ; Écouter rend le son, « Couper le son » revient à la lecture muette. Pas de lecture automatique en mouvement réduit (l'affiche reste). |
| **YouTube** (`?fond=youtube`) | lecteur YouTube | Façade de consentement, puis lecteur `xqEzYw4YqRw`, extrait 3399 → 3699 s en boucle. Pilotage par conduite manuelle (ou par le fichier maître quand il sera analysé, voir plus bas). |

### Variantes (28/10/2026)

Deux pages réutilisent ces modules sans les copier : **H4-2 « Nuit »** (`../h4-2-nuit/`, fond noir à
la place de la photo) et **H4-3 « Captation »** (`../h4-3-captation/`, captation plein cadre,
YouTube rogné pour la maquette ou vidéo hébergée, avec filtre et assombrissement). Chacune définit
`window.H4Variante` avant les scripts (racine des médias, fonds, vidéo, défauts) ; sans elle, H4
est inchangée. Voir leurs LISEZ-MOI. Effets sur H4 : l'icône du bouton « Pause de l'animation »
bascule désormais vers ▶ (elle ne changeait jamais, `hidden` n'existant pas comme propriété sur un
SVG) ; et, le 28/10, les nouvelles valeurs par défaut, l'errance et le logo qui ramène au sommaire
des hypothèses (`../`), communs aux trois pages.

## Fichiers

| Fichier | Rôle |
|---|---|
| `index.html` | les cinq temps, nav « Pierre Blɘusɘ » FR \| EN, canvas de la nuée, commandes fixes, HUD, `<audio>` ; filet `js-timeout` (textes visibles si l'amorçage échoue) |
| `css/etourneaux.css` | typographie (Cormorant Garamond + Inter), empilement (fonds z 0, nuée z 1, lecteur YouTube z 3, textes z 6…), ouverture et mode YouTube, panneau, HUD, téléphone |
| `js/parametres.js` | **source unique des réglages** (noms, bornes, valeurs par contexte, globaux, plafonds par appareil) → `window.H4Parametres` |
| `js/nuee.js` | moteur WebGL2 et chorégraphie (figures, squelette, musique → mouvement, rampe de couleur, brume, qualité adaptative) → `window.Nuee` |
| `js/musique.js` | piste pré-analysée, conduite manuelle, analyse en temps réel (comparaison) → `window.Musique` |
| `js/sync.js` | horloges virtuelle / média / YouTube, servo monotone, écoute → `window.Sync` |
| `js/reglages.js` | panneau de réglages (vanilla) → `window.Reglages` |
| `js/main.js` | amorçage, fonds, scroll, câblage des modules, HUD, API de test → `window.__h4` |
| `data/pathetique-iv-extrait.json` | caractéristiques pré-analysées de la bande-son de test (30 Hz, 7 canaux, 10 ruptures, 258 attaques) |
| `outils/analyse-bande-son.py` | ffmpeg → JSON, rejouable sur le fichier maître (Python 3.9, bibliothèque standard + ffmpeg 8) |
| `assets/` | fond, masque ▶, silhouette du chef, références vol1/2/3 (et calques), médias de test |

Chaque module est facultatif : sans WebGL2 (ou si le contexte est perdu) l'image reste seule ;
sans `sync.js` une horloge de secours prend le relais ; sans GSAP une boucle
`requestAnimationFrame` ; sans Lenis le défilement natif. Aucun de ces cas ne lève d'erreur.

## Lancer

- **MAMP** (Apache) sert la racine `Server/` : `http://localhost:8888/pierrebleuse/hypotheses/h4-etourneaux/`.
  Apache gère les requêtes partielles (`Range`, réponse 206), indispensables pour la vidéo
  hébergée : `php -S` et `python3 -m http.server` répondent 200 avec le fichier entier,
  la vidéo ne se positionne plus (et Safari refuse de la lire).
- Le son ne part que sur un geste (clic sur « Écouter », ou Entrée / Espace sur le bouton).
- Réglages : bouton « Réglages » ou touche `P` ; Échap ferme.

## Réglages et où les changer

**Panneau** (touche `P`) : trois contextes, chacun avec ses valeurs — **vidéo** (ouverture,
musique en cours), **ambiance** (ouverture sans musique), **scroll** (sous l'ouverture). Les
onglets montrent le poids réel de chaque contexte dans le mélange : vidéo quand un média joue
(même muet), ambiance sinon, scroll selon la sortie de l'ouverture (`p`). Copier / coller en
JSON (repli par zone de texte si le presse-papiers est refusé), Réinitialiser, mémorisation
locale (`localStorage`, clé `h4-reglages-v3`, sans erreur si le stockage est bloqué).
Commandes globales : fond (recharge la page), aperçu superposé YouTube, figure forcée,
analyse en temps réel, masque ▶, référence A/B (`volN_layerUp.png` en « éclaircir » sur le
fond, avec option « masquer la nuée »), HUD, animation.

**Valeurs par défaut** : `js/parametres.js` (`DEFS`, une valeur par contexte). Colonne vidéo :
configuration validée le 28/10/2026, confirmée pour les trois contextes le 30/10/2026, commune
aux trois pages H4 (clé de mémorisation passée en v3 le 30/10 pour qu'elle s'applique aussi aux
navigateurs qui avaient des réglages mémorisés) :

| Réglage | vidéo | ambiance | scroll | Effet |
|---|---|---|---|---|
| `opacite` | 90 | 90 | 45 | opacité CSS du calque (%) |
| `intensite` | 0,14 | 0,12 | 0,10 | lumière de chaque point |
| `densite` | 19 000 | 110 000 | 35 000 | points dessinés (ordinateur) |
| `taille` | 0,4 | 1 | 0,8 | × la tache de référence (2,9 px à 1080 px de haut) |
| `trainees` | 1 | 0,4 | 0,6 | part de tirets orientés par la vitesse |
| `brume` | 1 | 0,6 | 0 | brouillard au sol (avec le voile) |
| `vitesse` | 0,4 | 0,6 | 0,35 | vitesse de base du vol (déformation du temps) |
| `amplitude` | 0,4 | 0,8 | 0,5 | ampleur des déformations |
| `sensMusique` | 1,15 | 0 | 0,4 | gain des caractéristiques musicales |
| `sensScroll` | 1,75 | 0,3 | 1 | cisaillement selon la vitesse de défilement |
| `errance` | 1 | 1 | 0 | la figure voyage d'un côté à l'autre du cadre (voir ci-dessous) |
| `visage` | 0,91 | 0,7 | 0 | la nuée contourne les yeux |
| `derriere` | 0,83 | 0,5 | 0 | passage derrière la silhouette |

**Errance** (28/10/2026) : les figures sont posées sur l'image (la masse devant le chef, comme
vol2) ; sans plus, la nuée restait au centre, sur les visages. Toute la figure voyage donc d'un
côté à l'autre du cadre : ±27 % de la largeur de l'image (±7 % en hauteur), cycle de 36 s, en
créneau adouci (≈ 13 s sur chaque côté, la traversée du centre en quelques secondes), la queue
suivant la tête de son retard. Fonction de l'horloge murale, pas de la phase musicale : rien ne
saute quand le média boucle, tout se fige avec la pause ou `?t=`. Départ à gauche (le texte de
l'ouverture est à droite). Constantes : `ERRANCE` dans `js/nuee.js` ; en portrait, la course se
resserre avec le cadrage comme le reste de la figure.

`APPAREILS` : 250 000 points et DPR 2 sur ordinateur, 60 000 points et DPR 1,5 en tactile
(l'alpha est compensé quand le moteur dessine moins de points).

**Ailleurs dans le code** :

- `js/nuee.js` → `FIGURES` : géométrie de chaque figure (8 nœuds de l'épine `[x, y, z,
  demi-largeur, demi-épaisseur, torsion, filets]`, densité relative `rel`, satellite `sat`,
  col) et paramètres `g` (bruit, nappe, alphas par groupe `aCorps`/`aSat`/`aFil`/`aEtinc`,
  `eclat` lumière des points, `grain` taille, `diffus`/`portee` halo du voile, `mouvement`,
  `onde`, `tordre`, **`sortie`** = opacité de la référence : traîne 0,54). Constantes :
  `RETARD` 2,4 s (queue derrière la tête), `BASE`/`GAIN` de la phase, `YEUX`/`R_YEUX`
  (visage dégagé), rampe de couleur et brume (42 × brume × poids du voile) dans `FS_COMPO`.
  Programme : une rupture change de figure si sa force ≥ 0,2 et 9 s au moins après le
  changement précédent ; transition de 2 + 4·(1 − force) s, commencée 0,3 s avant
  (anacrouse) ; qualité adaptative au-delà de 22 ms entre images.
- `js/main.js` : `FONDU_CONTEXTE` (1,5 s, bascule ambiance ↔ vidéo), `YT_DEBORD` (1,6 : la
  nuée déborde du lecteur en mode conforme), `EXTRAIT`, `YT` (vidéo, début, fin), `PISTES`
  (piste pré-analysée par fond).
- `js/sync.js` : `TAU` 0,2 s (servo), `SAUT` 0,3 s (recalage si l'horloge est en retard,
  média audible), `RECUL` 2 s, `GEL` 50 ms, `BLOCAGE` 6 s (repli sur l'horloge virtuelle) ;
  YouTube : recalage au-delà de 250 ms.
- `js/musique.js` : `NIVEAUX` de la conduite (1 à 5 → énergie 0,08 / 0,28 / 0,5 / 0,74 /
  0,97), `reaction` 0,2 s, `anticipation` 0,25 s, `rampe` 1 s.
- `css/etourneaux.css` : `object-position` du fond (et `--focal-x/y` du repli CSS du masque),
  jetons d'empilement `--z-*`, `--panneau-l`.

## Paramètres d'URL

| Paramètre | Effet |
|---|---|
| `?fond=image\|video\|youtube` | fond de l'ouverture (non mémorisé tant qu'on ne le change pas dans le panneau) |
| `?figure=auto\|traine\|masse\|voile\|fil` | figure forcée |
| `?hud=1` | HUD : cadence, points, horloge et écart de synchronisation, figures, valeurs pré-analysées / mesurées, poids des contextes |
| `?t=96` | horloge figée à 96 s (temps musical et horloge murale) : captures répétables |
| `?nolenis=1` | défilement natif sur ordinateur |
| `?reduit=1` | mouvement réduit forcé |
| `?panneau=1` | panneau ouvert au chargement |

## API de test (`window.__h4`)

- `geler(t)` / `degeler()` : fige le temps musical et l'horloge murale à `t` (déterminisme) ;
- `scroll(p)` : place la sortie de l'ouverture à `p` (0-1), renvoie le `scrollY` appliqué ;
- `figure(nom)` : force une figure (`'auto'` ou `null` pour revenir au programme) ;
- `contexte(nom)` : impose le contexte de l'ouverture (`'video'`, `'ambiance'`, `'auto'`) ;
- `etat()` : horloge (`t`, `source`, `enLecture`, `ecartMs` mesuré dans l'image), `p`,
  vitesse, frontière, poids, réglages mélangés, opacité, rectangle de l'image, statistiques
  du moteur (`points`, `ips`, `figures` tête et queue, `phase`, `dispersion`, `sortie`…) ;
- `relayout()`, et les objets `nuee`, `lecteur`, `piste`, `conduite`, `youtube`,
  `reglages`, `lenis`.

## YouTube : les règles et comment le bac à sable les respecte

- **Consentement avant tout chargement** (CNIL) : une façade locale (affiche, texte,
  liens Google / YouTube) ; rien n'est demandé à YouTube ni à Google Vidéo avant le clic sur
  « Charger la vidéo YouTube ». Vérifié au journal réseau : avant le clic, seuls
  `127.0.0.1`, `unpkg.com`, `cdnjs.cloudflare.com` et Google Fonts ; après, `www.youtube.com`.
- **Disposition conforme par défaut** : le lecteur 16:9 est entier, jamais recouvert ni
  rogné (≥ 480 px de large sur ordinateur, pleine largeur et ≥ 200 px de haut sur
  téléphone) ; la nuée vole **derrière et autour** (z 1 sous le lecteur z 3) ; la nav ne
  suit pas le scroll ; le panneau se range à côté du lecteur ou devient une feuille sous
  lui ; un élément fixe qui toucherait le lecteur est masqué (filet de sécurité, jamais
  déclenché aux tailles testées).
- **Aperçu superposé** (réglage, désactivé par défaut) : nuée par-dessus le lecteur, avec
  l'étiquette « Aperçu superposé — non conforme aux règles YouTube, test interne ». Test
  interne uniquement.
- **Synchronisation** par `getCurrentTime()` (servo, recalage au-delà de 250 ms, gel hors
  lecture, ancrage immédiat sur nos `seekTo`). Le son du lecteur n'est **jamais** capté ni
  analysé : le mode « inaudible » n'existe pas avec YouTube (il coupe le son et le dit).
- **Conduite manuelle** (tant que le fichier maître n'est pas analysé) : panneau →
  « Conduite (YouTube) » → « Commencer l'enregistrement », puis pendant la lecture `1` à `5`
  = niveau, `R` = rupture, retour arrière = annuler. Les temps viennent du lecteur. Mémorisée
  localement (clé `h4-conduite-xqEzYw4YqRw`), exportable / importable en JSON (format piste).

## Bande-son de test : crédits

Piotr Ilitch Tchaïkovski, Symphonie n° 6 en si mineur « Pathétique », op. 74, IV. Finale
(Adagio lamentoso), **Musopen Symphony Orchestra**, enregistrement versé au **domaine
public** (Wikimedia Commons :
<https://commons.wikimedia.org/wiki/File:Tchaikovsky,_Symphony_No._6_in_B_minor,_Op._74,_%27Pathetique%27_-_IV._Finale_Adagio_lamentoso.ogg>).
Extrait 5:08 → 9:12 (244 s), AAC 160 kbit/s : `assets/media/pathetique-iv-extrait.m4a`, et
la même piste dans `assets/media/captation-test.mp4`. Le brief interdit le son faute de
droits réglés : cette bande-son de domaine public est l'exception retenue pour juger la
synchronisation ; la captation de Prades n'est lue que par le lecteur YouTube du festival.

## Analyser le fichier maître de Prades (quand le festival le fournira)

1. Isoler l'extrait voulu du fichier maître, en temps de la vidéo YouTube (ici 3399 → 3699 s),
   hors du dépôt (seul le JSON est utile) :
   `ffmpeg -ss 3399 -to 3699 -i maitre.mp4 -vn -ac 2 -ar 48000 ~/prades-2022-extrait.wav`
2. L'analyser avec l'offset de la trame 0 = temps média du début de l'extrait :
   ```
   python3 outils/analyse-bande-son.py ~/prades-2022-extrait.wav data/prades-2022-extrait.json \
     --offset 3399 --titre "Tchaïkovski, …" --interpretes "…, Pierre Bleuse" \
     --licence "© Festival Pablo Casals de Prades" --source-url "https://www.youtube.com/watch?v=xqEzYw4YqRw"
   ```
   (`--verifier` contrôle d'abord les calages sur signaux synthétiques ; `--anticipation`
   0,25 s par défaut, `--anticipation-attaques` 0,05 s ; `--trace` écrit les séries
   intermédiaires.) Le traitement d'un extrait de 4 min prend ~17 s.
3. Dans `js/main.js`, `PISTES.youtube = 'data/prades-2022-extrait.json'` : en mode YouTube
   la nuée suit alors cette piste (la conduite manuelle ne sert plus). Corriger un décalage
   global en changeant `offset` dans le JSON (canaux et événements se décalent d'un bloc).
4. Vérifier au HUD (`?fond=youtube&hud=1`) : figures, énergie pré-analysée, écart d'horloge.
   Les seuils des ruptures (8 LU, nouveauté) sont réglés sur la *Pathétique* et seront à
   revoir sur une autre pièce.

## Vérifications (Chrome headless, 27/10/2026)

Harnais hors projet (`scratchpad/…/h4-build/integration/`, client CDP Python `cdp.py`,
serveur local avec requêtes partielles), Chrome 156 headless : SwiftShader pour les images,
GPU Metal (Apple M1) pour les temps. Ces mesures valent pour Chrome uniquement.

**Intégration des trois modules** (ce qui a été corrigé en les assemblant) :

- *Fin de l'écoute* : `Sync.lecteur.pause()` figeait l'horloge musicale, donc le vol (le
  squelette ne bougeait plus). Ajout de `lecteur.relacher()` (le média s'arrête, l'horloge
  virtuelle repart de la même position) pour l'image fixe ; vidéo hébergée et YouTube
  reviennent à la lecture muette (« Couper le son »). Lecture refusée (navigateur, erreur du
  média) : le bouton revient aussitôt à « Écouter », avec une note.
- *Opacité 0* : le canvas restait composé (écarts de ±3 niveaux sur 37 374 pixels, sous le
  halo du texte et l'indice) ; il est désormais masqué à 0.
- *Figures* : vol1 est composée à ~54 % (mesuré sur les références) alors que la page
  l'affichait à 100 % (traîne deux fois trop lourde) → gain `sortie` par figure dans le
  moteur. Masse : grain plus fin (plus de points visibles, moins lumineux chacun). Voile :
  halo diffus réduit (45 % → 18 % des points), épine redessinée (aile haute gauche jusqu'au
  bord, arche au-dessus de la tête, pli droit, drapé bas prolongé à gauche).
- *Mouvement réduit* : l'image fixe est refaite quand la piste arrive (elle pouvait rester
  calculée sans piste) ; pas de lecture automatique de la vidéo hébergée.
- *Conduite* : `vide` et `revision` (le moteur replanifie quand on ajoute un repère).
- *HUD et API* : écart d'horloge mesuré dans l'image même ; `__h4.contexte()` ; table
  `PISTES` (fichier maître pour YouTube).

**Protocole du brief** :

- **0 erreur console, 0 exception** au chargement, au scroll, en mouvement réduit, en mode
  vidéo, en mode YouTube (un seul avertissement, émis par le script de YouTube :
  `postMessage` avant la navigation de l'iframe) et CDN bloqués (seules les requêtes
  bloquées sont signalées).
- Ordinateur 1440 × 900, six positions (haut, ouverture à mi-course, ouverture terminée,
  respiration, deuxième photo, pied) et planche de 12 images de l'ouverture (0 → 900 px) :
  la figure, calée sur l'image, devient fil calé sur la fenêtre (ancrage de p 0,15 à 0,85),
  sans rupture. Le fil reste visible sur le noir et dans les ombres de la photo 2.
- Téléphone 390 × 844, dpr 3, tactile (UA iPhone), quatre positions : la nuée reste dans le
  tiers visible autour du chef, yeux dégagés. Glissé tactile de 420 px depuis la photo :
  la page défile de 413 à 421 px (canvas `pointer-events: none`, `touch-action: pan-y`).
- **Même scrollY → même image** (horloge figée `?t=40`, défilement natif) : aller puis
  retour à 300, 700, 1500 et 1925 px, captures identiques à l'octet (MD5).
- Planches de 12 images sur ~10 s de temps musical (`__h4.geler`) : ambiance 15 → 25,5 s,
  la traîne devient voile à la rupture de 18,55 s (poids du voile en tête 0,02 → 1 entre
  18,8 et 22,6 s, en queue 0,05 → 1 entre 21,7 et 25,5 s : entrée en pupitres) ; contexte
  vidéo 85 → 95,5 s, dispersion au tutti de 87,95 s (0,37 à 88,8 s) puis masse regroupée.
- Mouvement réduit émulé : phase identique sur 2 s, captures identiques, un seul rendu par
  position de scroll, pas de Lenis.
- GSAP, Lenis ou les deux bloqués : la page démarre, la nuée vole (boucle rAF), la molette
  défile, les textes s'affichent.
- Molette réelle avec Lenis (Metal, 24 crans de 120 px) : 60,0 à 60,1 rAF/s, médiane et p95
  de 16,7 ms, à dpr 1 et 2 ; dans 2 passages sur 5, une image isolée de 583 à 617 ms
  (53 à 54 rAF/s sur ces passages), à des positions différentes, non reproduite : cause non
  identifiée (ramasse-miettes ou ordonnancement du headless, comme observé sur h3).

**Vérifications propres à H4** :

- **Opacité 0** : 0 pixel différent de la page sans canvas (1440 × 900). Après avoir montré
  la nuée puis mis l'opacité à 0 : au plus 1 niveau sur 1 018 pixels (0,08 %), sous
  l'indice et les commandes (répartition des calques de Chrome).
- **Éclaircir** : aucun pixel de la chemise assombri (écart signé minimal 0 sur le plastron
  et le haut de la chemise) ; la nuée éclaire le sombre (luminance moyenne de l'écran
  18,6 → 32,2 avec la masse).
- **Masque ▶** : luminance maximale 16,9 dans la zone du pictogramme (255 sans masque),
  0 pixel au-dessus de 100 (196 sans masque).
- **Figures contre références** (corrélation des cartes de densité 96 × 54, calque seul) :
  traîne 0,744 (vol1), masse 0,777 (vol2), voile 0,647 sans brume et 0,709 avec la brume
  contre la luminance de vol3 (avant intégration : 0,739 / 0,769 / 0,611 / 0,698). Lumière
  totale : traîne 2,79e7 pour 2,86e7 attendus (vol1 × 0,54), masse 6,06e7 pour 5,82e7.
- **Écoute** (vrai clic, `--disable-audio-output`) : l'audio est placé exactement au temps
  de l'horloge virtuelle (10,1889 s → `currentTime` 10,1889), l'horloge se tient 0,16 à
  0,79 s le temps que le son démarre (headless logiciel), puis suit `currentTime` à 0,24 -
  0,32 ms en moyenne (max 0,46 - 1,17 ms) ; aucun recul. Son coupé : le HUD montre la piste
  (énergie 0,17) et l'analyse directe à zéro, comme prévu ; inaudible : gain 0, analyse
  0,89 ; audible. Fin de l'écoute : audio en pause, horloge virtuelle à 0,998 s/s, retour au
  contexte ambiance.
- **Vidéo hébergée** : lecture muette lancée par script, horloge sur le média, écart moyen
  0,28 - 0,39 ms (max 0,65 - 1,12 ms) sur 120 images ; Écouter rend le son, « Couper le
  son » revient au muet sans arrêter la vidéo.
- **YouTube** : rien vers YouTube / Google Vidéo avant le clic (journal réseau) ; après,
  lecteur prêt, lecture muette, horloge `youtube` (écart −0,07 à −2,3 ms) ; grille de
  `elementFromPoint` (événements de pointeur forcés) : 63/63 points sur le lecteur en haut,
  54/54 après 200 px de scroll, 63/63 panneau ouvert ; aperçu superposé : étiquette
  d'alerte visible, canvas au-dessus (z 4) ; Écouter → son, « Couper le son » → muet, la
  vidéo continue.
- **Temps de rendu** (Metal, Apple M1, 2880 × 1800) à 140 000 points : masse 4,7 - 5,0 ms,
  traîne 5,0 - 6,0 ms, voile 5,6 - 8,1 ms par image ; boucle rAF réelle à 60 i/s (médiane
  16,7 ms, p95 16,8 ms). Les cadences sous SwiftShader (13 à 15 i/s) ne valent rien.

## Limites connues

- **Testé dans Chrome uniquement** (headless, SwiftShader et Metal sur un M1). Non testés :
  Safari macOS et iOS (WebGL2 `texelFetch` dynamique, cibles R16F, mode économie
  d'énergie), Firefox (paliers de `currentTime` lissés en headless seulement), Android
  moyen de gamme, sorties Bluetooth, vraie latence audio perçue.
- **Voile** : la correspondance la plus faible (0,65 / 0,71). La draperie se lit, mais vol3
  a davantage de nappes superposées sur la moitié gauche et en bas ; le grain du voile reste
  un peu étiré (tirets de vitesse) là où vol3 a des points ronds et fins.
- **Traîne** : sa tête sature encore avant le gain de 0,54 (une plaque grise avec des trous
  au zoom ×2, là où vol1 montre un grain) ; ses filets d'entrée sont plus courts que ceux
  de vol1. Avec ce gain, l'ouverture au chargement (contexte ambiance, traîne) est
  **discrète** — c'est le rendu de vol1, à valider par le DA : `FIGURES.traine.g.sortie`
  dans `nuee.js`, ou l'opacité globale, le règlent.
- **Grain sur téléphone** plus gros (DPR plafonné à 1,5, 60 000 points plus lumineux) ; et
  la qualité adaptative, sur un GPU lent, dessine moins de points plus lumineux : grain
  plus grossier (visible dans toutes les captures SwiftShader).
- **Google Fonts** est demandé avant le consentement YouTube (convention du projet) : à
  héberger localement si le DPO le demande.
- **Vidéo hébergée** : « Pause de l'animation » n'arrête pas la vidéo de fond (la vidéo de
  test est une image fixe ; une vraie captation devra s'arrêter aussi, WCAG 2.2.2) ; hors de
  l'ouverture la vidéo muette continue. En mode YouTube, au consentement l'horloge passe de
  l'horloge virtuelle au début de l'extrait (3399 s) : la nuée change de phase une fois.
- **Synchronisation** : à « Écouter », l'horloge se tient 0,15 à 0,8 s (headless) le temps
  que le son démarre ; après une reprise Chrome annonce `currentTime` ~90 ms en avance à la
  première image (rattrapé en ~5 images) ; YouTube a fait reculer `getCurrentTime()` de
  250 à 540 ms juste après le démutage, en headless (l'horloge suit : un recul, une fois).
- **Déformation du temps** (vitesse ≠ 1 en ambiance) : la phase dépend du chemin parcouru
  et revient vers la phase musicale à 8 % au plus ; le programme des figures, lui, reste une
  fonction pure du temps média.
- **Canvas fixe** : pendant un lancer au doigt, il peut glisser d'une image par rapport à la
  photo (effets calés au pixel effacés dès que le scroll commence, SPEC § 3.1).
- Textes sous l'ouverture : apparition par blocs (opacité, montée 10 px, flou 2 px → net),
  pas par lignes comme h2/h3 (règle 12 du brief).
- La dispersion des ruptures part toujours dans les mêmes directions (vecteurs fixes par
  point) ; le scintillement des étincelles se recale toutes les ~16,7 min (invisible).
- Le fond fait 1920 px : agrandi sur un écran Retina ; le masque ▶ est un calque correctif
  en attendant une image propre du DA.
