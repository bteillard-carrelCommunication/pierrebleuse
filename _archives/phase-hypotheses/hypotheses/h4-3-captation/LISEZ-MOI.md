# Hypothèse 4-3 — Captation (la nuée sur le concert) · LISEZ-MOI

Variante de l'hypothèse 4 construite le 28/10/2026 : la **captation du concert en fond plein
cadre**, filtrée ou assombrie, et la nuée d'étourneaux par-dessus, en « éclaircir », conduite par
la musique puis apaisée par le scroll comme dans H4. Même moteur, même panneau, mêmes sections
sous l'ouverture : seule l'ouverture change. Le moteur, ses réglages et leurs valeurs sont décrits
dans `../h4-etourneaux/LISEZ-MOI.md`.

Ouvrir : `http://localhost:8888/pierrebleuse/hypotheses/h4-3-captation/` (MAMP : la vidéo
hébergée a besoin des requêtes partielles d'Apache).

## Deux fonds

| Fond | URL | Statut |
|---|---|---|
| **Vidéo hébergée** (défaut) | `./` | `media/captation.mp4`, tirée de `VideoPierreBleuse.mov`, **lancée en muet dès le chargement** (le son sur « Écouter ») ; la voie de mise en ligne |
| **YouTube plein cadre** | `?fond=youtube` | **maquette interne** : lecteur rogné, filtré, recouvert, contraire aux règles de YouTube (voir plus bas) |

Le choix se fait aussi dans le panneau (« Fond de l'ouverture », recharge la page).

### La vidéo hébergée (28/10/2026)

Source : `VideoPierreBleuse.mov` (déposée dans ce dossier, gardée telle quelle) : montage de 65,7 s
de la captation de Prades, H.264 1080p à 60 i/s + AAC, enregistré avec OBS. Version web,
`media/captation.mp4` (17,2 Mo) :

- **première seconde coupée** : la capture d'écran avait gardé le bandeau de Chrome
  « www.youtube.com – Pour quitter le plein écran, appuyez sur échap » (0 → 0,85 s) ; la vidéo
  commence au plan suivant (1,0 s), donc 64,7 s, et la boucle ne le remontre plus ;
- 30 i/s (fond : moitié moins à décoder sous la nuée), H.264 High, ~2 Mbit/s (même qualité
  apparente que la source, déjà adoucie), AAC 160 kbit/s, index en tête (`+faststart`) : la
  lecture part avant la fin du téléchargement ;
- `media/affiche.jpg` : sa première image (affiche de l'élément vidéo et de l'image dessous) ;
- `data/captation.json` : bande-son analysée (`outils/analyse-bande-son.py` de H4, 3,7 s) :
  1 942 trames à 30 Hz, **4 ruptures** (5,55 s nouveauté, 19,05 s tutti, 39,15 s effondrement,
  55,15 s nouveauté) et 96 attaques : la nuée change de figure pendant la boucle.

Lancement : lecture muette par script au chargement (aucun navigateur n'accepte le son sans
geste) ; pas de lecture automatique en mouvement réduit (`prefers-reduced-motion`, ou `?reduit=1`),
l'affiche reste. Commandes de la conversion, pour refaire ou remplacer la vidéo, plus bas.

### YouTube plein cadre : comment

- **Consentement d'abord** (CNIL), comme dans H4 : rien n'est demandé à YouTube avant le clic sur
  « Charger la captation » ; la façade se range dans le texte de l'ouverture, au-dessus de
  « Écouter ». En attendant, l'affiche (`image-sans-vol.png`, l'image de la captation à 56:39,
  soit le début de l'extrait) tient lieu de fond.
- **Lecteur sans interface** : `controls 0`, `disablekb 1`, `fs 0`, `iv_load_policy 3`, lecture
  muette ; aucun pointeur ne l'atteint (pas de survol, donc ni titre ni boutons), hors de l'ordre
  de tabulation, `aria-hidden`.
- **Rognage** : le lecteur 16:9 couvre l'ouverture et la déborde en haut et en bas d'au moins
  72 px et 7,5 % de sa hauteur (`YT_ROGNE` dans `main.js`), là où YouTube pose titre, boutons et
  logo. Point focal horizontal du chef (60 %, 62 % en portrait), centré en hauteur.
- **Affiche par-dessus** tant que le lecteur ne joue pas : elle s'efface en 1,2 s après 2,5 s de
  lecture (le titre de départ a disparu), revient s'il cesse de jouer plus de 0,6 s, et aussitôt
  en pause (`AFFICHE_YT`). Elle a exactement le cadre du lecteur : le fondu se fait sans saut.
- Extrait 3399 → 3699 s en boucle, recalé avant la fin (pas d'écran de fin). Le son vient du
  lecteur (« Écouter » le démute) ; il n'est jamais capté ni analysé, comme dans H4.

### Pourquoi « maquette interne »

Les règles des services d'API de YouTube interdisent de recouvrir, rogner ou modifier le lecteur et
d'en masquer la marque : c'est exactement ce que fait ce mode (rognage, filtre, nuée par-dessus).
Il sert à **juger l'effet** sur la vraie captation ; il ne peut pas être mis en ligne. La version
publiable est la **vidéo hébergée** : fichier maître à demander au Festival de Prades (droits de
l'image et du son à régler), sans YouTube ni consentement. Le panneau le rappelle sous le bloc
« Vidéo de fond ».

## Vidéo de fond : filtre et assombrissement

Bloc **« Vidéo de fond »**, en tête du panneau (touche `P`). Il s'applique à l'affiche, à la vidéo
hébergée et au lecteur YouTube.

| Réglage | Bornes | Défaut | Effet |
|---|---|---|---|
| Filtre | préréglages | Aucun | pose les curseurs ci-dessous d'un coup ; toucher un curseur → « Personnalisé » |
| Assombrir | 0 → 95 % | 40 % | voile noir : la nuée (« éclaircir ») ressort d'autant mieux |
| Saturation | 0 → 150 % | 100 % | |
| Contraste | 50 → 150 % | 100 % | |
| Négatif | 0 → 100 % | 0 | inversion, façon solarisations des graduations chromatiques du DA |
| Flou | 0 → 16 px | 0 | la boîte du média déborde du cadre (bords nets), la nuée s'y recale |
| Teinte | Bleu de scène `#1747c9`, Lavande `#5a638d`, Cyan `#2fb7c2`, Bleu nuit `#0a1524` | Bleu de scène | couleur posée en « couleur » : la lumière de la vidéo est gardée |
| Force de la teinte | 0 → 100 % | 0 | |

**Préréglages** (`FILTRES` dans `../h4-etourneaux/js/parametres.js`) : Aucun ; Noir et blanc
(saturation 0, contraste 115) ; **Scène bleue** (N&B, contraste 110, bleu de scène 60 %) ;
Lavande (N&B, lavande 70 %) ; Négatif (inversion, saturation 80, et assombrir 75 % : sur un fond
devenu clair la nuée ne se verrait plus) ; Rêve (saturation 40, flou 6 px, bleu nuit 45 %).

Pour une présentation : `?filtre=scene&assombrir=25`, `?filtre=nb`, `?fond=youtube&filtre=lavande`…
Ces paramètres ne sont pas mémorisés (comme `?fond=` ou `?figure=` dans H4) ; un choix dans le
panneau l'est (`localStorage`, clé `h4-captation-reglages-v3`, à part de H4).

Rendu : filtre CSS sur la boîte du média (`invert → saturate → contrast → blur`), puis un calque
de teinte en `mix-blend-mode: color`, puis le voile noir, le tout dans le cadre de l'ouverture,
sous le canvas de la nuée.

## Ce qui change par rapport à H4

- **Valeurs par défaut** : celles des trois pages H4 (configuration du 28/10, voir le LISEZ-MOI de
  H4). La vidéo jouant dès le chargement, c'est le contexte **vidéo** qui s'applique d'emblée
  (19 000 points fins, taille 0,4) ; l'ambiance ne sert qu'avant le départ de la vidéo ou en pause.
- **Errance** : la nuée voyage d'un côté à l'autre du cadre et ne fait que traverser le centre,
  où se trouvent la plupart des visages de ce montage (gros plans du violoncelliste, du chef).
- Ni visage dégagé ni passage derrière le chef (le chef bouge) : réglages cachés et neutralisés
  (`imageDA: false`) ; avec une vidéo qui serait l'image du DA, `imageDA: true` les rétablit,
  masque ▶ compris.
- **Pause de l'animation** arrête aussi la vidéo de fond et l'écoute (WCAG 2.2.2 ; H4 ne le
  faisait pas, sa vidéo étant une image fixe). Si la vidéo part alors que l'animation est en pause
  (lecture automatique, lecteur chargé après coup), elle est arrêtée.
- En YouTube, sans fichier maître analysé, la nuée suit la **conduite manuelle** (panneau →
  « Conduite (YouTube) », touches `1` à `5` et `R`), partagée avec H4 (même vidéo, même clé
  `h4-conduite-xqEzYw4YqRw`) ; sinon elle reste surtout en « masse ». « Figure forcée » permet de
  montrer traîne, masse, voile ou fil.

## Refaire ou remplacer la vidéo hébergée

1. Encoder pour le web, dans `media/` (commandes utilisées le 28/10 ; `-ss 1.0` coupe le bandeau
   de la capture, à adapter ou retirer pour une autre source) :
   ```
   ffmpeg -ss 1.0 -i VideoPierreBleuse.mov -map 0:v:0 -map 0:a:0 -vf "fps=30" -c:v libx264 \
     -preset slow -crf 23 -maxrate 2M -bufsize 4M -pix_fmt yuv420p -profile:v high -g 60 \
     -c:a aac -b:a 160k -movflags +faststart media/captation.mp4
   ffmpeg -i media/captation.mp4 -frames:v 1 -q:v 3 media/affiche.jpg
   ```
   (Une boucle de 30 à 60 s suffit pour un fond ; au-delà, prévoir aussi une version légère pour
   le téléphone.)
2. Analyser sa bande-son (Python 3.9 + ffmpeg 8, ~17 s pour 4 min) :
   ```
   python3 ../h4-etourneaux/outils/analyse-bande-son.py media/captation.mp4 data/captation.json \
     --titre "…" --interpretes "…, Pierre Bleuse" --licence "© Festival Pablo Casals de Prades"
   ```
3. Dans `index.html`, `window.H4Variante.video` :
   `src: 'media/captation.mp4'`, `affiche: 'media/affiche.jpg'`, `piste: 'data/captation.json'`,
   `boucle: [0, durée en s]`, `credit: '…'`, et **`imageDA: false`** (le premier de `fonds` est le
   fond par défaut : ici `['video', 'youtube']`).
4. Vérifier au HUD (`?fond=video&hud=1`) : horloge `media`, piste chargée, figures qui changent aux
   ruptures. Les seuils des ruptures sont réglés sur la *Pathétique* (voir H4).

Pour la mise en ligne, mieux vaut repartir du fichier maître du Festival de Prades que de cette
capture d'écran de YouTube (image plus nette, droits réglés).

## Fichiers

| Fichier | Rôle |
|---|---|
| `index.html` | page (copie de celle de H4, chemins vers `../h4-etourneaux/`) et configuration `window.H4Variante` |
| `VideoPierreBleuse.mov` | vidéo source (non servie par la page) |
| `media/captation.mp4`, `media/affiche.jpg` | vidéo de fond et son affiche |
| `data/captation.json` | bande-son pré-analysée de la vidéo |
| `vignette.jpg` | vignette du bac à sable (la vidéo à 34 s, réglages par défaut) |
| `../h4-etourneaux/js/*.js`, `css/etourneaux.css` | modules partagés avec H4 (voir son LISEZ-MOI) |

Ajouts aux modules partagés, sans effet sur H4 : `parametres.js` (`FOND`, `TEINTES`, `FILTRES`,
`filtreDe`, globaux `fond*`) ; `reglages.js` (options `choix`, `caches`, `fondVideo`, `sousTitre`,
`noteFond` ; bloc « Vidéo de fond ») ; `sync.js` (`opts.playerVars`) ; `main.js`
(`window.H4Variante`, fonds `noir` et YouTube plein cadre, traitement, pause de la vidéo) ;
`etourneaux.css` (§ 1 ter).

## Vérifications (Chrome headless, GPU Metal, 28/10/2026)

- 0 erreur console et 0 exception sur H4 (image, vidéo, YouTube) et sur les deux variantes ; H4
  inchangée (même rectangle de la nuée, mêmes réglages, visage dégagé gardé en YouTube conforme).
- YouTube plein cadre : avant le clic, seuls `localhost`, `unpkg.com`, `cdnjs.cloudflare.com` et
  Google Fonts au journal réseau ; après, `www.youtube.com`. Lecture muette et horloge `youtube`
  1,5 s après le clic (écart −9,8 ms à la première mesure, puis −0,1 à 1,4 ms), affiche effacée
  entre 2,5 et 4 s ; aucune interface
  YouTube visible sur les captures en lecture ni en pause. Pause de l'animation → lecteur en
  pause (état 2), affiche revenue ; reprise → lecture.
- Filtres appliqués au lecteur YouTube lui-même (« Scène bleue » vérifiée en lecture) et à la
  vidéo hébergée ; négatif et flou vérifiés sur l'affiche.
- Vidéo hébergée : pause de l'animation → `video.paused`, `currentTime` figé ; reprise → lecture.
- 28/10, vidéo fournie : au chargement, fond `video`, `media/captation.mp4` en lecture muette
  (3,8 s lues après 5 s), horloge `media`, piste chargée, crédit et affiche de la vidéo, 0 erreur ;
  valeurs du contexte vidéo identiques à la configuration fournie ; errance de −518 à +518 px
  image entre 0 et 18 s ; logo → `hypotheses/`.
- Préréglage puis curseur → « Personnalisé » ; persistance par la clé propre ; `?filtre=` et
  `?assombrir=` non mémorisés.
- Téléphone 390 × 844 (dpr 3, tactile) : façade et écoute en bas sur le dégradé, chef dans le
  cadre (point focal 62 %).
- Bouton « Pause de l'animation » : son icône ne basculait jamais vers ▶ (propriété `hidden`
  inexistante sur un SVG) ; corrigé dans `main.js`, pour H4 aussi.

## Limites connues

- **Chrome seulement** (headless). Non testés : Safari (filtre CSS et `mix-blend-mode` sur une
  iframe d'une autre origine), Firefox, téléphones réels. Le rognage est calé sur l'interface que
  YouTube affiche aujourd'hui sur ordinateur ; sur téléphone, il peut ajouter d'autres éléments
  (bouton « réactiver le son »…), et il peut changer son interface à tout moment.
- La qualité de l'image YouTube est choisie par le lecteur (souvent floue au départ).
- Coût des filtres sur une vidéo plein écran (surtout le flou) non mesuré ; le HUD affichait
  60 i/s pendant la lecture YouTube, sans mesure fine.
- Le négatif éclaircit le fond : la nuée, en « éclaircir », n'y vit que dans les zones sombres.
- Hors de l'ouverture, la vidéo continue de jouer (bande passante).
