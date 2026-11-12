# Brief commun — trois hypothèses de site-performance pour Pierre Bleuse

Lire en entier avant de coder. Ce brief s'applique aux trois maquettes
(`h1-rubato/`, `h2-tutti/`, `h3-lever/`). Chaque maquette a en plus son
cahier des charges (`SPEC.md` dans son dossier).

## 1. Le contexte en dix lignes

- Pierre **Bleuse** (avec un S) : chef d'orchestre français, directeur musical de
  l'Ensemble intercontemporain, chef principal de l'Orchestre symphonique d'Odense,
  directeur artistique du Festival Pablo Casals de Prades. Invité du Concertgebouw,
  du London Philharmonic, de l'Orchestre de Paris, de la Scala (mai 2027), du Dutch
  National Opera (création *Red Fox* de Du Yun, mars 2027).
- Nouveau site en préparation par l'agence Carrel. Le DA (Philippe Pouyfourcat) a
  jugé les premières maquettes **« trop mécaniques, trop radicales »** et demande
  **progressivité, finesse, douceur**. Il veut que le site soit **« une performance
  artistique en soi »** et pose la question : **comment retranscrire visuellement
  son niveau d'excellence ?**
- Plan de communication : fonction n° 1 = réduire l'incertitude des décideurs
  (programmateurs, agence HarrisonParrott) → exactitude et lisibilité ; n° 2 =
  rendre visible la reconnaissance des pairs → **les mots viennent de l'extérieur**
  (presse, institutions), le site ne fait pas parler Pierre de lui. « Une expression
  travaillée, jamais fabriquée. » Personnalité : audacieux, dynamique, souriant.
- Identité visuelle V4 du DA (voir `_ref/`) : logo **« Pierre Blɘusɘ »** (les e
  retournés, U+0258), fragments de photo **inversés / pivotés de 180°**, stries
  verticales issues du **plissé de la chemise** (bleuse-1), lavande **#5A638D**,
  lumière de scène bleue, et une **graduation chromatique** (solarisations : crème
  / sépia, lavande, cyan, vert profond — `_ref/graduation-chromatique-*.jpg`).
  Le motif commun : **l'inversion et la ligne verticale**.

## 2. Matière disponible

- Photos (992 × 619 px, c'est tout ce qu'on a ; elles sont floues en plein écran,
  on fait avec) : `../../assets/bleuse-1.jpg` (mains ouvertes, lunettes, bokeh doré,
  chemise plissée), `../../assets/bleuse-3.jpg` (en frac devant l'orchestre),
  `../../assets/bleuse-5.jpg` (accroupi sur scène, noir et blanc, taches de lumière
  au sol). Maquettes du DA : `bleuse-2.jpg` (bandeau + stries sur bleuse-1),
  `bleuse-4.jpg` (grille de stries lavande sur bleuse-3), `bleuse-6.png` (deux
  voiles blancs sur bleuse-5).
- Masques de détourage (blanc = sujet, même taille que la photo) :
  `../../assets/bleuse-1-masque.png`, `../../assets/bleuse-3-masque.png`.
- Moteur existant (référence, à copier si utile, ne pas modifier l'original) :
  `../../js/stripes-effect.js` (Pixi Mesh + shader généré : stries, bandeau, voiles,
  compositions desktop/portrait, bicubique, rendu à la demande) et `../../js/main.js`
  (Lenis + ticker GSAP, ralenti sans épinglage). `../../css/styles.css` pour la base
  typographique (Cormorant Garamond + Inter via Google Fonts).
- Textes : **uniquement du lorem ipsum** pour les corps de texte (petits blocs
  justifiés, blanc, ~15 px, comme sur `bleuse-6.png`). Les libellés d'interface sont
  en français. Pour l'agenda, utiliser ces temps forts publics (mention « exemples »
  dans la page) :
  - 13 janvier 2027 · Philharmonie de Paris · EIC 50 : œuvre collective de 50 compositeurs
  - 13 → 28 mars 2027 · Dutch National Opera, Amsterdam · *Red Fox*, création mondiale de Du Yun, mise en scène Barrie Kosky
  - avril 2027 · tournée Kammerorchester Basel avec Hayato Sumino (cinq villes)
  - 16 mai 2027 · Teatro alla Scala, Milan · Orchestra della RAI, festival Milano Musica
  - 6 novembre 2026 · sortie du disque *Ravel Abroad*
- Citations presse : des **emplacements** (« Citation presse — à venir », lorem),
  jamais de citation inventée attribuée à un média réel.

## 3. Grammaire de mouvement « douceur, progressivité, finesse »

Ces douze règles sont LA réponse à la critique du DA. Chaque maquette les applique.

1. **Anacrouse.** Rien ne commence sec. Toute apparition a une phase quasi
   invisible (0 → 10 % sur le premier tiers de sa course) : on sent que quelque
   chose vient avant de le voir.
2. **Entrées en pupitres.** Jamais tout en même temps. Les éléments d'un même
   effet entrent avec des retards étalés (≥ 60 % de la course), par petits groupes
   de 2 ou 3, du sujet vers les bords. Jamais de balayage gauche → droite régulier.
3. **Asymétrie.** Attaque lente, résolution longue : ease-in-sine sur le premier
   tiers puis ease-out-quart (ou quint) sur le reste. La disparition (scroll vers
   le haut) refait exactement le même chemin à l'envers.
4. **Bords vivants.** Aucun bord dur. Un bord d'apparition est d'abord large et
   diffus (plume de 6 à 10 % de la largeur) puis se resserre (1 à 2 px) une fois
   l'élément posé. Les aplats ont des bords en dégradé ou en plume, pas d'arêtes.
5. **Rubato interne.** Ce qui est « dedans » (photo retournée, texte) glisse
   lentement et peu (≤ 3 % de la hauteur) pendant l'apparition, en décalage avec
   le bord qui l'encadre.
6. **Respiration continue.** Hors scroll, une oscillation très lente (période 6 à
   10 s, amplitude ≤ 0,4 % d'échelle ou ≤ 2 px), consciemment imperceptible, qui
   empêche l'image d'être morte. Désactivée si `prefers-reduced-motion`.
7. **Lumière avant découpe.** Une transition passe par la lumière (luminance,
   température, graduation chromatique du DA) avant de passer par la forme : une
   strie apparaît d'abord comme une variation de lumière, puis comme une découpe.
8. **Densité faible.** Une seule chose « parle » à la fois. Les textes n'entrent
   pas pendant qu'une image se transforme.
9. **Silence.** Des zones de repos (sections noires, marges larges) entre deux
   temps forts ; le scroll traverse des respirations.
10. **Échelle du geste.** Amplitudes petites (translations ≤ 40 px, échelles
    ≤ 5 %), durées longues (une transition occupe 60 à 100 % d'une hauteur d'écran
    de scroll).
11. **Synchronie exacte.** L'état visuel est une fonction de la position de scroll
    (aller = retour), jamais d'un chronomètre — sauf la respiration (règle 6) et les
    ambiances (particules, lumière) qui vivent en temps réel sans dépendre du
    scroll. Pas d'inertie ajoutée entre le scroll et l'effet (exigence client).
12. **Typographie.** Les textes apparaissent par lignes (décalage de 80 à 120 ms
    d'équivalent-scroll), flou 2 px → net, montée de 8 à 12 px. Jamais lettre par
    lettre.

## 4. Contraintes techniques (non négociables)

- HTML / CSS / JS vanilla, sans npm ni bundler. Bibliothèques par CDN, versions
  figées : `https://unpkg.com/lenis@1.1.14/dist/lenis.min.js`,
  `https://cdnjs.cloudflare.com/ajax/libs/pixi.js/7.4.0/pixi.min.js`,
  `https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js` (+ ScrollTrigger
  si besoin). Polices Google Fonts (Cormorant Garamond, Inter) ; une autre police
  Google est permise si elle sert le concept.
- **Pas d'épinglage** (`pin`) : il crée une butée. Pas de `position: fixed` sur
  les photos. Sur écran tactile, défilement natif pur (pas de Lenis en tactile).
- Tout canvas est décoratif : `pointer-events: none; touch-action: pan-y`, et
  retirer l'EventSystem de Pixi (`app.renderer.events.setTargetElement(null)`),
  sinon le doigt ne fait plus défiler la page sur mobile.
- Rendu à la demande quand l'image est statique ; une boucle continue est admise
  seulement pour une ambiance vivante (particules, lumière), avec pause hors écran
  (IntersectionObserver) et quand l'onglet est caché.
- Une `<img>` de repli sous chaque canvas (visible si WebGL échoue), même cadrage.
- Composition **portrait** pour les téléphones (ratio largeur/hauteur < 0,9) : un
  smartphone ne montre qu'un tiers de la photo ; le sujet doit rester dans le cadre
  (point focal) et les effets rester lisibles.
- `prefers-reduced-motion` : les ambiances s'arrêtent, les transitions deviennent de
  simples fondus.
- Pas de son dans la maquette (droits non réglés). Un emplacement « écouter » est
  permis s'il est inerte et signalé « à venir ».
- Hauteur d'écran mobile : `100svh`. Gouttières mobiles 16 px.

## 5. Structure de page attendue (les cinq temps)

Même ossature pour les trois maquettes, afin de les comparer :

1. **Ouverture** — photo bleuse-1 plein écran, logo « Pierre Blɘusɘ » en haut à
   gauche, petit bloc de lorem. C'est ici que l'hypothèse se joue le plus.
2. **Respiration** — section noire : un ou deux petits blocs de lorem + un
   emplacement de citation presse (« voix extérieure »).
3. **Deuxième photo** — bleuse-3 plein écran, traitée selon l'hypothèse.
4. **Agenda** — les cinq temps forts ci-dessus, présentés selon l'hypothèse
   (partition, constellation, rampe de lumière…), mention « exemples ».
5. **Pied de page** — bleuse-5, traitée selon l'hypothèse, puis une ligne de pied
   de page (lorem).

Nav : logo à gauche (texte `Pierre` en italique serif au-dessus de `Blɘusɘ` en
gras), « FR | EN » à droite, discrets.

## 6. Protocole de test (obligatoire avant de rendre)

- Servir le projet depuis sa racine : `cd /Users/carrelcommunication/Documents/Server/PierreBleuse && python3 -m http.server <TON PORT> --bind 127.0.0.1 &`
  puis ouvrir `http://127.0.0.1:<TON PORT>/hypotheses/<dossier>/`. Ne pas toucher au
  port 8765 (serveur de l'utilisateur).
- Chrome headless avec WebGL logiciel (sans `--disable-gpu`) :
  `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --remote-debugging-port=<TON PORT CDP> --use-angle=swiftshader --enable-unsafe-swiftshader --no-first-run --user-data-dir=<ton dossier>/chrome about:blank &`
  Pilotage par CDP en Node 25 (WebSocket natif) : `Page.navigate`, `Runtime.evaluate`
  (portée globale), `Page.captureScreenshot`, `Emulation.setDeviceMetricsOverride`,
  `Input.dispatchMouseEvent` type `mouseWheel` pour un vrai scroll molette,
  `Input.synthesizeScrollGesture` (`gestureSourceType: 'touch'`) en émulation mobile.
  Activer `Network.setCacheDisabled`. Timeout par sonde. Tuer ton Chrome à la fin.
- Vérifier : 0 erreur console, 0 exception ; captures desktop 1440 × 900 à 6 positions
  de scroll (haut, ouverture à mi-course, ouverture terminée, respiration, deuxième
  photo, pied de page) et mobile 390 × 844 (dpr 3, tactile) à 4 positions ;
  **planche-contact de 12 images** de l'ouverture (scroll de 0 à 1 hauteur d'écran
  par pas réguliers) pour juger la continuité ; glissé tactile depuis la photo qui
  fait bien défiler ; même scrollY → même image (aller puis retour) ; cadence
  pendant un scroll molette (rAF/s) ; `prefers-reduced-motion` émulé
  (`Emulation.setEmulatedMedia`) sans erreur.
- Outil de planche-contact : compiler une fois
  `swiftc -O /Users/carrelcommunication/Documents/Server/PierreBleuse/outils/sheet.swift -o <ton dossier>/sheet`
  puis `sheet <sortie.png> <colonnes> <largeur cellule> <images…>`.
- REGARDE tes captures (lis les fichiers image) avant de rendre : une page qui
  « marche » mais qui est laide ou mécanique n'est pas rendue.

## 7. Livrable

Dans ton dossier `hypotheses/<dossier>/` : `index.html`, `css/`, `js/`, `SPEC.md`
(déjà là), et `LISEZ-MOI.md` (ce qui est fait, les réglages à ajuster et où, les
limites connues). Captures et planche-contact dans ton dossier de travail hors
projet. Rien d'autre dans le projet : ne modifie aucun fichier hors de ton dossier.
