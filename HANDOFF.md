# Pierre Bleuse — bac à sable stries verticales

Document de passation pour reprendre dans Claude Code (VS Code).
Session d'origine : 15 octobre 2026, Claude Opus 4.7 depuis Claude Desktop.

---

## 1. Contexte client

**Chef d'orchestre : Pierre BLEUSE** (toujours avec un S, jamais Z — faute récurrente à surveiller).

Nouveau site en préparation. Deux volets :
- une **homepage fournie** avec plusieurs sections à image de fond plein écran ;
- des **pages de contenu** classiques.

L'exigence forte du client sur la home : **mouvement subtil et élégant** sur les images, piloté par le scroll, parallaxe, rien de balourd. Pierre Bleuse est personnellement attaché à cette sensation de fond vivant mais discret.

Derrière, à terme : CMS admin (agenda, discographie, bio, dates, etc.) en PHP / MySQL. **Hors scope** de ce bac à sable, qui ne traite QUE l'effet signature sur les hero.

---

## 2. Brief visuel

Le DA du client a fourni **deux séries** de photos retravaillées :

**Série 1 (fichiers `assets/bleuse-1.jpg` et `bleuse-2.jpg`)**
Pierre Bleuse mains ouvertes, lunettes, fond bokeh doré :
- image originelle
- version retravaillée avec un **bandeau bas** composé de **morceaux d'image copiés, inversés, décalés**, plus des **stries verticales** qui reprennent des bouts de l'image

**Série 2 (reçue en cours de session, non sauvegardée sur disque)**
Pierre Bleuse en frac dirigeant devant un orchestre :
- image originelle
- version retravaillée avec **des barres verticales plein hauteur** à largeurs variables, qui révèlent une version **miroir + décalée + teintée bleu-nuit** de la photo

C'est **la série 2 qui a orienté le shader actuel** (barres plein hauteur, pas bandeau bas). Les deux esthétiques pourront cohabiter plus tard dans le shader si besoin.

---

## 3. Référence externe

Le client a cité : **https://concertlab.com/**

À regarder pour le FEELING du scroll (fluidité, inertie, timing). L'animation de concertlab est géométrique et algorithmique — ce qu'on en garde, c'est la **sensation du scroll qui pilote l'animation dans les deux sens**, avec cette souplesse Lenis-like.

---

## 4. Choix d'architecture (décidés et motivés)

### Génération du rendu : SHADER sur photo originale (pas calques PNG)

Deux options avaient été comparées :
- **A — tout générer en code** depuis UNE photo originale (shader paramétrique)
- **B — calques PNG** livrés par le DA, le code se contente de les animer

Choix retenu : **A**. Raisons :
- un seul asset par photo au lieu de 10
- paramètres (nombre de stries, largeurs, décalages, intensité) réglables sans retour graphiste
- s'adapte à toutes les tailles d'écran
- l'effet « stries = morceaux de l'image elle-même » se code trivialement dans un fragment shader (sample de l'image à des UV différents)
- réutilisable sur chaque hero du site

### Stack technique

Rien ne change côté serveur (PHP / MySQL reste la cible). Côté front, trois briques :

1. **Lenis 1.1.14** — scroll inertiel smooth (indispensable pour que « rien ne soit balourd »)
2. **GSAP 3.12.5 + ScrollTrigger** avec `scrub: 0.5` — lie la progression de l'animation à la position du scroll, dans les deux sens
3. **Pixi.js 7.4.0** — rendu WebGL de la photo + application du fragment shader custom

Chargées via CDN (cdnjs + unpkg), versions figées dans `index.html`.

### Comportement du scroll (décidé par le client)

Question posée : « l'effet persiste une fois le scroll fini, ou revient à l'état photo nue quand la section sort du viewport ? »

Réponse client : « **il reste et go back avec le scroll top** » — autrement dit, l'effet suit linéairement la progression du scroll, reste figé à son état max une fois la section passée, et se démonte à l'identique en scroll inversé.

Implémentation : ScrollTrigger avec `start: 'top top'` + `end: 'bottom top'` + `scrub: 0.5`.
- Au chargement (scroll=0) : photo NUE, progress=0
- Scroll vers le bas : progress monte vers 1, les stries se forment
- Section passée : progress=1, stries max
- Scroll inversé : reverse propre jusqu'à 0

---

## 5. État livré

Arborescence :
```
/Users/carrelcommunication/Documents/Server/PierreBleuse/
├── HANDOFF.md                      ← ce fichier
├── index.html                      ← 2 heros + 2 sections de contenu + nav
├── css/
│   └── styles.css                  ← habillage sombre, serif Cormorant / sans Inter
├── js/
│   ├── stripes-effect.js           ← fragment shader + classe StripesHero (Pixi)
│   └── main.js                     ← boot Lenis + sync ScrollTrigger + instanciation
├── assets/
│   ├── bleuse-1.jpg                ← photo 1 (mains ouvertes bokeh)
│   └── bleuse-2.jpg                ← photo 1 retravaillée (bandeau bas) — utilisée telle quelle, le shader ajoute sa propre couche de stries
└── .claude/
    └── launch.json                 ← config du serveur de preview (optionnelle)
```

### Fragment shader actuel (dans `js/stripes-effect.js`)

Principe :
- découpage de l'axe X en N slots (`uStripeCount`)
- ~55 % des slots portent une barre (seuil `step(0.45, hash(slot))`)
- chaque barre a une **largeur variable** (0.36 à 0.84 du slot) et des **bords soft** (smoothstep)
- dans une barre : sample de l'image avec `uv.x` miroiré (`1.0 - uv.x`) + petit décalage pseudo-aléatoire horizontal et vertical
- la couleur du sample miroir est **assombrie à 0.55** et **teintée bleu-nuit** (ajout `vec3(0.02, 0.03, 0.07)`)
- l'opacité finale est multipliée par `uProgress` → l'effet se construit au scroll

Paramètres exposés (`StripesHero.params`) :
- `stripeCount: 28` — nombre de slots verticaux
- `mirrorShift: 0.08` — amplitude du décalage du sample miroir
- `stripeIntensity: 0.9` — opacité max des barres
- `parallaxAmount: 40` — en px, parallaxe douce du sprite sur la hauteur de la section

---

## 6. Vérifications déjà faites

- Rendu testé en live dans le browser pane (Chrome équivalent) : OK
- Au chargement : photo nue, pas de stries. ✓
- Scroll 50 % : stries en formation, bien visibles, pas de scintillement. ✓
- Pas d'erreur console. ✓
- Deuxième hero : même comportement indépendant. ✓

---

## 7. Pistes et questions ouvertes

### Court terme (iterations shader)

- [ ] Comparer avec concertlab.com pour calibrer durée/easing (actuellement `scrub: 0.5` + easing Lenis par défaut)
- [ ] Essayer d'ajouter l'effet « bandeau bas » de la série 1 EN PLUS des barres verticales (ou en remplacement selon la photo), via un paramètre `uBandMode`
- [ ] Tester avec `stripeCount` plus bas (16 ou 20) pour des barres plus larges façon série 2
- [ ] Essayer `mirrorShift` négatif ou plus large pour voir d'autres sensations
- [ ] Ajouter un léger **flou** ou **chromatic aberration** dans les barres pour signer l'effet
- [ ] Chargement progressif / fade-in de l'image à l'init (actuellement pop)

### Moyen terme (intégration)

- [ ] Décider si le shader a une PRESET par photo (une config par hero, stockée dans le CMS) ou une config unique globale
- [ ] Prévoir une version **"reduce motion"** (prefers-reduced-motion) qui désactive les animations
- [ ] Perf mobile : tester sur iPhone, Pixi fait du WebGL donc ça devrait tenir, mais vérifier le `resolution` max (actuellement `min(dpr, 2)`)
- [ ] Fallback canvas 2D ou simple image si WebGL non supporté (rare mais vérifier)

### Long terme (production)

- [ ] Décider du CMS : rester sur la stack PHP/MySQL de l'écurie Carrel ou autre
- [ ] Structurer les routes et templates
- [ ] Admin : agenda (dates concerts), discographie, bio, actualités, galeries
- [ ] SEO : titres, structured data (Person/MusicGroup/Event), sitemap, canonical
- [ ] Hébergement : mutualisé OVH comme Chant des Cabanes et Rungis ? À confirmer

---

## 8. Commandes utiles

### Lancer le serveur local

Le `php` du PATH n'existe pas sur ce poste, et le `php8.3.14` de MAMP n'a PAS le binaire CLI (seulement 8.2 et 8.4 l'ont).

```bash
cd /Users/carrelcommunication/Documents/Server/PierreBleuse
/Applications/MAMP/bin/php/php8.2.26/bin/php -S localhost:8765 -t .
```

Puis ouvrir : **http://localhost:8765/**

### Alternative Python (sans PHP)

```bash
cd /Users/carrelcommunication/Documents/Server/PierreBleuse
python3 -m http.server 8765
```

### Live Server (VS Code)

L'extension « Live Server » de Ritwick Dey fait exactement la même chose avec rechargement automatique à chaque save. Clic droit sur `index.html` → Open with Live Server.

---

## 9. Fichiers de référence hors projet

- Références visuelles (photos) : à ce stade, elles sont dans le projet (`assets/bleuse-*.jpg`) + d'autres existent chez le DA du client, à récupérer selon besoin.
- Mémoires persistantes de la session :
  - `[pierre-bleuse-site](~/.claude/projects/-Users-carrelcommunication-Documents-Server/memory/pierre-bleuse-site.md)` — fiche projet
  - `[feedback-pierre-bleuse-orthographe](~/.claude/projects/-Users-carrelcommunication-Documents-Server/memory/feedback-pierre-bleuse-orthographe.md)` — rappel « BLEUSE pas BLEUZE »

---

## 10. Ce qui NE doit PAS être fait sans en reparler

- Introduire une dépendance npm / un bundler (Vite, Webpack) → le bac à sable reste volontairement en HTML+JS vanilla + CDN, pour que n'importe quel éditeur puisse l'ouvrir et le servir
- Partir sur un framework (React, Vue, Svelte) sans validation explicite du client → la stack PHP/HTML/JS/CSS est la convention Carrel
- Toucher à `.htaccess`, à un `config.php` ou à des choses prod — on n'est PAS sur un déploiement, uniquement sur un bac à sable local
- Commiter / pusher sur un dépôt distant — pas encore de git init, à décider plus tard

---

## 11. Itération du 15/10/2026 (après retours client, Claude Code)

Retours client : la 1re maquette ne montrait que des barres bleues sur l'image ; le modèle (`bleuse-2.jpg`) est fait de **morceaux de photo** :
- **bandeau horizontal** : morceaux découpés ailleurs dans la photo, **retournés**, affichés à **100 %** ;
- **stries verticales** : la photo en **symétrie inversée** (rotation 180°) à **0,5** de transparence.
Demandé aussi : section 1 sur **fond noir**, traiter **bleuse-1.jpg seule** (bleuse-2 = référence du rendu), effet **un peu plus visible**, qui **disparaît au scroll up**.

Ce qui a changé :
- `index.html` : un seul hero (bleuse-1). `bleuse-2.jpg` sert de calque de comparaison : **touche R**.
- `js/stripes-effect.js` réécrit : **Pixi Mesh + shader custom** au lieu d'un `PIXI.Filter`. Cause du rendu faux : dans un filtre Pixi 7, `vTextureCoord` ne va pas de 0 à 1 sur la photo (texture de filtre recadrée sur l'écran), donc `1.0 - uv.x` ne visait pas la bonne zone. Avec le Mesh, les UV sont ceux de la photo : la composition reste calée sur le sujet.
- Composition dans `STRIPES_LAYOUT` (relevée sur bleuse-2) : 4 stries [x0, x1, retard, sens], bandeau y 0.745→0.824 en 4 segments [x0, x1, centre source X, décalage Y, retard, sens].
- Animation : section **épinglée** (`pin`, `end: '+=130%'`, `scrub: 0.6`) → l'effet se construit en entier pendant que la photo reste plein cadre (avant, il n'atteignait 1 qu'une fois la section sortie de l'écran, d'où le manque de visibilité). Stries en volet vertical alterné haut/bas, segments du bandeau en volet horizontal qui glissent en place ; léger zoom + parallaxe ; titre qui s'efface ; fondu des bords au noir ; fondu d'apparition au chargement.
- Vérifié en Chrome headless (1440×900) : photo nue à 0, effet complet à la fin de l'épinglage, retour exact à 0 en remontant, aucune erreur console.

Points d'attention :
- `bleuse-1.jpg` ne fait que **992 × 619** px → floue en plein écran desktop : demander la HD au DA.
- En portrait mobile, le cadrage cover coupe les côtés : les stries (x 0.18→0.35) sortent en partie du cadre. Prévoir une composition mobile.

---

## 12. Itération bleuse-3 → bleuse-4 (15/10/2026, soir)

Principe de la §11 **validé par le client** (affinages à venir). Nouvelle cible : `bleuse-3.jpg` (chef en frac + orchestre) → rendu `bleuse-4.jpg`.

Relevé au pixel de bleuse-4 contre bleuse-3 (script de régression, 992 × 619) :
- **grille régulière** de stries : 30,5 px de large tous les 60,25 px (départ −1,5 px), 17 positions, les n° 9-10-11 sont vides (zone libre autour du chef) ;
- remplissage **opaque** = `0,5 × photo pivotée 180° + 0,5 × #5A638D` (lavande ; la photo d'origine ne transparaît pas) ;
- les **2 stries juste à droite du chef** (n° 12 et 13) passent **derrière son bras** ; ailleurs les stries sont devant tout (y compris le bras gauche et les musiciens). La rambarde, elle, est derrière les stries.

Implémentation :
- `js/stripes-effect.js` : système de **presets par photo** (`STRIPES_PRESETS`, choisi par `data-preset` sur la section). `bleuse-1` = version §11, `bleuse-3` = grille via `stripeGrid()` (pas, largeur, positions sautées, positions « derrière »).
- **Masque de détourage** `assets/bleuse-3-masque.png` (blanc = chef), généré avec Apple Vision (`VNGenerateForegroundInstanceMaskRequest`, macOS) ; il colle au relevé de bleuse-4 à ±2 px. Sur une strie « derrière », le shader laisse la photo d'origine là où le masque est blanc. Pour les futures photos : soit le même outil Vision (script Swift d'une trentaine de lignes, cf. session), soit un masque fourni par le DA.
- Animation : stries qui **s'ouvrent depuis leur axe** (persienne), décalées du chef vers les bords, contenu pivoté qui glisse verticalement en sens alterné ; `scrub: 1` (plus souple) ; zoom + parallaxe bornés pour ne jamais découvrir de bord noir (bug corrigé).
- `index.html` sert bleuse-3 ; `?photo=1` → revoir bleuse-1 ; touche **R** → maquette du DA correspondante.
- Contrôle : rendu à progress 1 comparé à bleuse-4 → **écart moyen 2,3/255**, différences limitées aux bords des stries (1 px). Scroll aller-retour OK, aucune erreur console.

---

## 13. Maquette de home (15/10/2026)

`home.html` (le bac à sable `index.html` reste en place pour les réglages) — contenus **fictifs** :
1. hero **bleuse-1** (preset `bleuse-1`) ;
2. section intermédiaire **fond noir** : citation + court texte bio + lien ;
3. hero **bleuse-3** (preset `bleuse-3`), titre « Saison 2026 — 2027 » ;
4. agenda (4 dates fictives) + pied de page.

Changements techniques :
- `main.js` : les ScrollTrigger sont créés **après le chargement de toutes les photos, dans l'ordre de la page**, puis `ScrollTrigger.refresh()`. Indispensable avec 2 sections épinglées : sinon la 2e calcule sa position sans l'espace ajouté par l'épinglage de la 1re. `StripesHero.init()` n'appelle plus `bindScroll()`.
- Presets : `focus: [x, y]` = point de la photo gardé dans le cadre au recadrage (le chef de bleuse-3 sortait du cadre en portrait mobile).
- Vérifié en scroll réel (1440×900 et 390×844) : hero 1 épinglé 0→1170 px, hero 2 3186→4356 px, chaque effet indépendant, retour en haut = 2 photos nues.

---

## 14. Reprise

- **Outils de la session** (masque Vision, relevé au pixel des maquettes, harnais Chrome headless) : `outils/` + `outils/LISEZ-MOI.md`.
- **En attente du client** : confirmer que seul le bras du chef passe devant les 2 stries de droite sur bleuse-3 (la rambarde reste derrière, d'après le relevé de bleuse-4).
- **Prochaines étapes probables** : affinages d'animation demandés par le client ; photos HD ; composition mobile ; `prefers-reduced-motion` ; préchargement des photos (l'épinglage n'est actif qu'après chargement) ; décider où vivent les presets (config par photo dans le futur CMS ?).

---

## 15. Itération du 15-16/10/2026 (nuit) : butée, définition, smartphone, bleuse-5, lorem ipsum

**Renommage fait par l'utilisateur** : `index.html` = page d'accueil (ex-`home.html`), `_draft1.html` = bac à sable (ex-`index.html`). L'utilisateur sert le dossier avec son serveur PHP sur `localhost:8765`.

### Retours client
1. « Butée quand on arrive sur l'image, qui casse la fluidité du scroll global. »
2. « Définition de l'image amoindrie. »
3. « KO sur smartphone. »
4. Puis : nouvelle section de bas de page avec `bleuse-5.jpg` + **2 bandes blanches à 0,5 qui entourent la personne** (maquette `bleuse-6.png`) ; remplacer tous les textes d'`index.html` par de **petits blocs de lorem ipsum** comme sur bleuse-6 (« que du lorem ipsum »).
Les effets graphiques eux-mêmes sont validés.

### Causes établies (diagnostic à 4 agents sur copie figée, mesures)
- **KO smartphone, cause n°1 (bloquante)** : Pixi 7.4 pose `touch-action: none` sur son canvas (`EventSystem.addEvents`). Le canvas couvrant 84 % de l'écran, un glissé qui commence sur la photo ne fait PAS défiler la page (mesuré : 0 px ; 300 px depuis le titre). Aggravants : épinglage JS désynchronisé du scroll tactile natif (trous noirs jusqu'à 400 px), composition paysage illisible en portrait (bleuse-1 : 0 strie sur 4 dans le cadre), 2 canvas rendus 60 fois/s même hors écran (MSAA), aucun repli sans WebGL.
- **Butée** : le `pin` ScrollTrigger fait passer la vitesse de la photo de 100 % à 0 en 1-2 frames (mesuré : 86 frames figées sur le hero 1). `scrub: 1` était sans effet (onUpdate reçoit la progression brute).
- **Définition** : la source 992 px est agrandie ×2,9 (écran Retina 1440) à ×4,1 (iPhone portrait). Dans Chrome, le WebGL est aussi net que `<img>` (même bilinéaire). Le zoom de 5 % retirait ~9 %. Les JPEG portent le profil « iMac » de l'écran du DA (pas sRGB).

### Ce qui a changé (js/stripes-effect.js, js/main.js, css/styles.css, index.html, _draft1.html)
- **Plus aucun épinglage.** Chaque hero = section haute de `100svh + --hero-extra`, contenant un cadre `.hero__frame` (100svh : `<img>` + canvas + texte). Sur ordinateur (souris/trackpad, pas de reduced-motion), le cadre est translaté (`translate3d`, arrondi au pixel physique) pour un **ralenti continu** : au plus fort, la photo défile à 40 % de la page (`linger: 0.6`), sur ±0,8 écran (`lingerSpan`). Hero en haut de page : ralenti maximal dès le chargement. Hero en bas de page : le ralenti s'achève pile en fin de page, photo plein cadre. **Sur tactile : aucun translate**, défilement natif pur, seul l'effet suit le scroll.
- **Progression de l'effet** = fonction exacte de la position du scroll, recalculée et redessinée dans la même frame (demande client du 16/10 : « synchronicité parfaite », vérifié sur 775 frames molette bas/haut : écart 0). Le lissage optionnel `smoothing` est à 0. Fenêtre `p0 → p1` par hero (`progressBefore/After/MinSpan`), toujours atteignable avant la fin de page. ScrollTrigger reste chargé et synchronisé avec Lenis pour de futures animations, mais les heroes n'en dépendent plus.
- **Rendu à la demande** : `autoStart: false`, `app.render()` seulement si l'effet ou le cadrage change ET si la photo est visible ; `antialias: false` ; résolution `min(dpr, 3)` dans un budget de 6,5 Mpx.
- **Tactile** : canvas `pointer-events: none` + `touch-action: pan-y` + `renderer.events.setTargetElement(null)`.
- **Netteté** : filtrage bicubique Catmull-Rom (9 lectures) quand la photo est agrandie (`uBicubic`), zoom supprimé, une seule lecture de texture par couche (stries, bandeau), précision `highp` avec repli `mediump`.
- **Compositions portrait** (ratio largeur/hauteur < 0,9), en coordonnées ÉCRAN : bleuse-1 = 4 stries sur les bords + bandeau pleine largeur ; bleuse-3 = grille de 8 stries ; dans les deux cas les stries passent **derrière le sujet** (masques Vision `assets/bleuse-1-masque.png`, `assets/bleuse-3-masque.png`).
- **`<img data-hero-img>`** sous le canvas : affichée tout de suite, même cadrage (object-position en px), reste seule si WebGL est indisponible (classe `is-fallback`).
- **Nouvelle couche « voiles »** (aplats translucides pleine hauteur ; demande client du 16/10 : le voile gauche se déploie depuis le haut, le voile droit depuis le bas) et **preset `bleuse-5`** : relevé au pixel sur bleuse-6 → zone nette x 0,509 → 0,772 (UV photo), voiles de part et d'autre. **Opacité 0,5 selon la consigne, mais la maquette rend l'équivalent d'environ 0,32** (mesure : `maquette = 0,676 × photo + 78`) → à trancher avec le client (`alpha` dans le preset). Portrait : voiles sur 17 % de chaque bord.
- **index.html** : uniquement du lorem ipsum (petits blocs justifiés, linéale blanche ~15 px, façon bleuse-6) ; la section bleuse-5 est le **pied de page** (`<footer class="hero" data-hero …>`) ; navigation conservée. Sur mobile, le texte de bleuse-3 et bleuse-5 passe en bas sur un dégradé sombre (`hero__text--m-bottom`) pour ne pas couvrir le sujet.

### Mesures après correction
- Molette réelle (Lenis) : rapport vitesse photo / page ≥ 0,40, variation max 3 % par frame, **0 frame figée** (avant : 86). Cadence en headless : ~55 images/s contre ~6 avant (rendu continu).
- Glissé tactile qui commence sur la photo : **403 px** de défilement (avant : 0).
- WebKit (Playwright, iPhone 15 et Retina) : les 3 heroes en WebGL2, 0 erreur, composition portrait choisie sur iPhone.
- Bac à sable `_draft1.html` (+ `?photo=1`, touche R) : OK.

### Reste à faire / à trancher
- **Photos HD** du DA ou du photographe, en **sRGB** : ≥ 2 900 px de large pour un écran Retina 1440, ~4 000 px pour un iPhone en portrait (ou un recadrage portrait dédié). C'est le seul vrai levier de définition.
- **Opacité des voiles** de bleuse-5 : 0,5 (consigne) ou ~0,32 (rendu de la maquette).
- **Test sur vrai iPhone / Android** (pas de simulateur iOS sur ce poste : seulement les outils en ligne de commande).
- Réglages de feeling : `linger`, `lingerSpan`, `progressBefore/After` dans `StripesHero.params`.
- Outils de cette nuit dans `outils/` (voir `outils/LISEZ-MOI.md`, section « Ajouts du 16/10/2026 »).

### 15 bis. Vérification indépendante (5 agents) et durcissements appliqués (16/10)
Constats vérifiés par les agents puis corrigés :
- **Préchauffage** : chaque hero compile son shader et envoie ses textures dès le chargement (même hors écran). Sinon ce coût tombait en plein scroll à l'arrivée de la photo (tâches de 60 à 750 ms mesurées : une petite butée). Contrôle : précision `highp` et liaison du programme vérifiées → sinon repli sur la photo seule.
- **Perte de contexte WebGL** (iOS en arrière-plan) : `webglcontextlost` → photo seule ; `webglcontextrestored` → effet redessiné (vérifié).
- **Lenis désactivé sur écran tactile** (il n'y lisse rien et posait des écouteurs tactiles non passifs) et page fonctionnelle si son CDN tombe.
- Redimensionnement sans image noire ; changement de densité d'écran (fenêtre glissée 1x ↔ Retina) détecté ; preset inconnu ou fenêtre de hauteur nulle sans plantage général ; app Pixi détruite en cas d'échec ; maquette du bac à sable (touche R) alignée sur tout format d'écran.
- **Shader allégé** : la photo pivotée et le bandeau ne sont lus que là où ils couvrent ; jonctions du bandeau sans liseré ; mipmaps coupés ; anti-crénelage sur ~1,5 pixel physique (bords francs) ; budget canvas 9 Mpx.
- `.hero__img` sans pointeur (pas de menu « Enregistrer l'image » au doigt) ; `prefers-reduced-motion` coupe aussi les animations CSS.
Contrôle final : synchro molette exacte (652 frames, écart 0), glissé tactile depuis la photo 399 px, WebKit iPhone + Retina sans erreur.
Non vérifiable ici : vrai GPU mobile, barre d'outils iOS, iPad avec trackpad ; défilement clavier/ascenseur sur ordinateur (le ralenti peut y trembler d'une frame — à trancher si gênant).

---

## 16. Reboot du 22/10/2026 : trois hypothèses de site-performance

**Retour du DA (Philippe Pouyfourcat, 17/10)** : « le principe est le bon mais les effets sont trop mécaniques (trop radicaux) ; chercher de la progressivité, de la finesse, de la douceur, une touche féminine » et, plus fondamental : « le site ne devrait pas être juste une composition d'images mais une performance artistique en soi ; comment retranscrire visuellement son niveau d'excellence ? ». L'utilisateur a demandé un **reboot en 2-3 hypothèses maquettées**, en transmettant le brief du plan de communication (voir mémoire `pierre-bleuse-plan-com`).

**Identité V4 du DA** (PDF `pierre-bleuse-identite-visuelle-V4.pdf` + `graduation-chromatique.pdf`, Drive « Prod PIERRE BLEUSE ») : logo « Pierre Blɘusɘ » (e retournés), fragments de photo inversés / pivotés, stries = plissé de la chemise de bleuse-1, lavande #5A638D, lumière de scène bleue, graduation chromatique (solarisations crème/sépia, lavande, cyan, vert profond). Motif commun : **inversion + ligne verticale**. Extraits dans `hypotheses/_ref/`.

**Dossier `hypotheses/`** :
- `BRIEF-COMMUN.md` : contexte, matière, **grammaire de mouvement en 12 règles** (anacrouse, pupitres, asymétrie, bords vivants, rubato interne, respiration, lumière avant découpe, densité faible, silence, échelle du geste, synchronie exacte, typographie par lignes), contraintes techniques, structure en cinq temps, protocole de test.
- `index.html` : page d'accueil des trois hypothèses.
- `h1-rubato/` : les compositions du DA phrasées comme une partition (évolution du moteur existant).
- `h2-tutti/` : un point de lumière par musicien (96, quatre familles aux teintes de la graduation), formations pilotées par le scroll, photos révélées à travers les points — mise en œuvre directe des « pistes de développement » du DA.
- `h3-lever/` : lumière et silence, spot et faisceaux à bords doux, trace de la baguette (SVG tracé par le scroll), fragments inversés à l'intérieur des faisceaux.
Chaque dossier a son `SPEC.md` et, une fois construit, un `LISEZ-MOI.md` (réglages). Construction, vérification et correction par agents (workflow) le 22/10 — voir l'état final plus bas quand il sera consigné.

**Document d'analyse pour le débat avec le DA** (Claude Doc, partageable, commentable) : https://claude.ai/artifact/EBS7rGLt6QdS2hx1DJLRaB — cadre, grammaire, les trois hypothèses, comparaison, décisions.

Outils ajoutés : `outils/sheet.swift` (planche-contact d'images), `outils/pdftext` (texte d'un PDF via PDFKit, source dans le scratchpad de session, à recopier si besoin).

### 16 bis. État des trois maquettes (22/10/2026, fin de journée)
Construites, vérifiées par un agent indépendant, puis corrigées (9 agents, 0 erreur console sur les trois, synchronie exacte vérifiée au pixel, défilement tactile depuis la photo OK, reduced-motion OK) :

| Maquette | Fichiers | Notes du vérificateur (douceur / performance, sur 10) | Correctifs | Limites restantes |
|---|---|---|---|---|
| h1-rubato | index.html, css/styles.css, js/stripes-effect.js (moteur dérivé : courbe `phrase()`, lumière avant découpe, plume dynamique, rubato, respiration, graduation), js/choreo.js (textes par lignes, agenda-partition), js/main.js | 7,5 / 6,5 | 12 (nav effacée devant les textes, contraste voile, texte après transformation, robustesse CDN…) | cadre remonté de 302 px à la fin de l'ouverture (sans pin) ; visage hors cadre sur mobile à p = 1 ; opacité des voiles 0,45 vs 0,30 mesuré sur la maquette du DA ; respiration = rendu continu ~30 i/s photo visible |
| h2-tutti | index.html, css/tutti.css, js/tutti.js (particules), js/tutti-sections.js (formations), js/main.js | 7 / 7,5 | 13 (mobile : photo ×1,25 cadrée visage, course 0,34 écran ; « tuyaux d'orgue » supprimés ; typo) | révélation mobile en un pouce ; treillis de points sur bleuse-3 posée ; 5 contextes WebGL ; colonnes toutes derrière le chef ; dérive par sinus |
| h3-lever | index.html, css/lever.css, js/lever.js, js/light-scene.js (shader lumière : ambiant + spot + faisceaux + barre + voiles + brume ; trace SVG) | 6 / 6,5 | 11 (montées de lumière adoucies, textes sans flash au relayout, logo voilé mobile, trace sans fouet) | montée du visage +27/255 par pas de 82 px ; final mobile hors du visage ; photos à 10-12 % au repos ; anac exposant 1,6 (pas quart/quint) |

Mon regard après lecture des planches-contact : Tutti est la seule « performance en soi » ; Rubato la plus fidèle et la plus sûre ; Lever la plus élégante (sa photo 2 est la plus belle image) mais l'ouverture la moins douce. Un jury indépendant à trois lentilles (DA / programmateur / public mobile) a été lancé pour départager ; résultat dans le doc Claude.
Captures et planches-contact finales : scratchpad de session `fix-h1-rubato/`, `fix-h2-tutti/`, `fix-h3-lever/` (temporaires) ; les principales sont insérées dans le doc Claude https://claude.ai/artifact/EBS7rGLt6QdS2hx1DJLRaB.

### 16 ter. Jury indépendant (22/10, soir) et recommandation
Trois juges (lentilles : DA de maison de luxe / programmateur-agent / public mobile) ont regardé les mêmes captures et planches-contact.
- Classements : DA → Lever, Rubato, Tutti ; programmateur → Lever, Rubato, Tutti ; public mobile → Tutti, Lever, Rubato.
- Moyennes sur 50 (douceur, performance, excellence, lisibilité, mobile) : **Lever 39,5**, Rubato 35,2, Tutti 32,4. Détail : Lever douceur 8,8 / performance 8,2 / excellence 8,2 / lisibilité 7,5 / mobile 6,8 ; Rubato 6,7 / 5,8 / 6,8 / 8,2 / 7,7 ; Tutti 6,3 / 8,2 / 6,2 / 6,0 / 5,7.
- Le jury contredit le vérificateur technique sur Lever (douceur 9 contre 6) : le seuil de +20/255 de luminance par image est un critère d'instrument, la planche est visuellement la plus douce.
- Arguments clés : Rubato « adoucit le chemin, pas la destination » (le tableau final reste celui jugé mécanique) ; Tutti a la meilleure image conceptuelle (plan de salle) mais une ouverture dure (visage solarisé en deux images) et des points sur le crâne jugés « effet gratuit » ; Lever = la retenue et la lumière, seule faute : premier écran quasi noir (visage à 14/255 contre 115 pour Rubato).
- **Recommandation (doc Claude, section « Ce qu'il reste à trancher ») : hybride « Lever + »** = base Lever + premier écran lisible (ambiant 0,10 → ~0,30 ou spot posé au chargement) + nav de Rubato (logo/FR|EN qui s'effacent devant les textes) + plan de salle de Tutti dans la section noire + découpes du DA à 0,5 dans les faisceaux. Alternative audacieuse : ouverture de Tutti éclairée à la Lever.
- Six points à trancher avec le DA (base, lumière du premier écran, part des découpes, plan de salle, opacité des voiles, ouverture mobile) puis construire « Lever + » dans `hypotheses/h4-lever-plus/` (2-3 jours + vérification).

## 17. Page d'accueil « bac à sable » (27/10/2026)

- `index.html` = nouvelle **home de l'atelier** : sommaire assumé comme bac à sable (bandeau chantier, papier millimétré, tampon), liens vers les trois hypothèses (+ H4 Lever + à construire), les premiers essais de stries, la matière (photos, maquettes du DA, masques, planches V4), le carnet (HANDOFF, brief, SPEC/LISEZ-MOI, outils, doc Claude) et un journal daté.
- L'ancienne home de stries est renommée **`maquette-v1.html`** (`outils/test-webkit.cjs` et `outils/mesure-molette.mjs` mis à jour).

## 18. H4-2 « Nuit » et H4-3 « Captation » (28/10/2026)

Deux variantes de H4 (vol d'étourneaux, voir `hypotheses/h4-etourneaux/LISEZ-MOI.md`) sur le même moteur, chacune une page `index.html` qui définit `window.H4Variante` et charge les modules de `h4-etourneaux/` :
- `hypotheses/h4-2-nuit/` : fond noir à la place de la photo, même cadrage virtuel que H4 (cover 16:9, focal 60/40), bande-son de test.
- `hypotheses/h4-3-captation/` : captation en fond plein cadre, **YouTube rogné sans interface** (défaut, maquette interne : contraire aux règles de YouTube, consentement CNIL gardé) ou **vidéo hébergée** (`?fond=video`, voie de mise en ligne ; placeholder `captation-test.mp4` en attendant le fichier fourni). Bloc « Vidéo de fond » du panneau : assombrir, saturation, contraste, négatif, flou, teinte (bleu de scène, lavande, cyan, bleu nuit) et préréglages (Scène bleue, N&B, Lavande, Négatif, Rêve) ; `?filtre=` / `?assombrir=` pour présenter. La pause de l'animation arrête la vidéo de fond.
- Procédure pour brancher la vraie vidéo (encodage, analyse de la bande-son, configuration) : `hypotheses/h4-3-captation/LISEZ-MOI.md`.
- Vérifié dans Chrome headless (Metal) : 0 erreur sur H4 et les variantes, H4 inchangée, cycle consentement → lecture → affiche effacée → pause → reprise, filtres appliqués au lecteur YouTube.
- Même jour, second passage : H4-3 a pour fond par défaut la vidéo fournie (`VideoPierreBleuse.mov` → `media/captation.mp4`, 1re seconde coupée pour le bandeau « plein écran » de la capture OBS, 30 i/s, ~2 Mbit/s, faststart ; bande-son analysée → `data/captation.json`, 4 ruptures), lancée en muet au chargement ; YouTube passe en option (`?fond=youtube`). **Errance** dans le moteur (`ERRANCE` de `nuee.js`, réglage par contexte) : la figure voyage d'un côté à l'autre du cadre (±27 %, cycle 36 s, horloge murale) pour ne plus rester sur les visages — vaut pour les trois pages H4. **Valeurs par défaut** du contexte vidéo remplacées par la configuration fournie le 28/10 (opacité 90, 19 000 points, taille 0,4…), clé de mémorisation passée en `-v2`. Logo « Pierre Blɘusɘ » de toutes les hypothèses (h1 à h4-3) → `../` (sommaire des hypothèses).
- 30/10/2026 : configuration fournie par l'utilisateur pour les trois contextes (vidéo, ambiance, scroll) et les globaux = déjà les valeurs de `h4-etourneaux/js/parametres.js` (39 valeurs comparées, aucun écart), communes à H4, H4-2 et H4-3. Clé de mémorisation passée en `-v3` pour que d'anciens réglages mémorisés ne masquent plus ces valeurs, et `?v=20261030` sur `parametres.js`, `reglages.js`, `main.js` dans les trois pages. Sommaire des hypothèses (`hypotheses/index.html`) : textes des cartes simplifiés (H1 Rubato « Travail de découpe d'image (initial) », H2 Tutti « Visualiser l'orchestre », H3 « Au fil du temps », H4 « Le vol d'étourneaux », H4-2 Nuit, H4-3 Captation ; citations retirées).
