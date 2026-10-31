# Vol d'étourneaux : faisabilité technique

Avis technique sur l'hypothèse 4 : une nuée de particules organique, superposée à une image
ou à une vidéo, pilotée par la musique puis par le scroll. Cette note est fondée sur une
investigation menée le 27/10/2026 (mesures sur les références, essais dans Chrome, lecture
des règles YouTube, de la CNIL, des spécifications et du code des moteurs de navigateur),
puis sur le prototype de `hypotheses/h4-etourneaux/`. Chaque affirmation qui compte
pour la décision a été contre-vérifiée par une seconde recherche indépendante.

## 1. En bref

| Question | Réponse |
|---|---|
| L'effet est-il réalisable sur un site professionnel ? | **Oui, de façon fiable**, en WebGL2 : 100 000 à 250 000 points sur ordinateur, moins sur téléphone, avec qualité adaptative. |
| Peut-on reproduire l'esprit de vol1, vol2, vol3 ? | **Oui.** Le mélange du DA est un « éclaircir » (lighten), reproductible au bit près en CSS. Les formes demandent un travail d'art (figures, transitions) plus que de la technique. |
| Peut-on synchroniser la nuée avec la musique ? | **Oui, et de façon fiable même sans son** : analyse préalable de la bande-son + pilotage par la position de lecture. L'analyse en temps réel, elle, ne marche qu'après un clic et s'éteint quand le son est coupé. |
| Avec une vidéo YouTube ? | **Seulement en partie.** La synchronisation par la position de lecture fonctionne, mais YouTube ne donne pas accès au son et **interdit de dessiner par-dessus son lecteur** ; en France, rien ne peut se charger avant le consentement. La signature de H4 (la nuée sur le chef) ne peut pas se poser sur une vidéo YouTube. |
| Avec une vidéo hébergée chez nous ? | **Oui, tout est possible** (superposition, synchronisation à l'image près, lecture muette dès l'arrivée sans consentement), à condition d'avoir les droits de diffusion de la captation. |
| Le visiteur doit-il entendre la musique ? | **Non.** La synchronisation tient son coupé ; le son ne peut de toute façon démarrer que sur un geste explicite (clic, touche), jamais au scroll ni à l'arrivée. |
| Et le scroll ? | **Réalisable et réversible** : la nuée s'allège, ralentit et se fait fil, en fonction pure de la position de scroll. |

**Recommandation.** Pour l'ouverture : image fixe (ou vidéo hébergée muette) + nuée
WebGL2, pilotée par l'**analyse préalable du fichier maître** de la captation sur une
horloge virtuelle ; un bouton **Écouter** fait entrer la musique exactement à l'endroit où
la nuée la « jouait » déjà en silence. YouTube reste possible comme lien ou comme lecteur
séparé, jamais comme support de la nuée.

## 2. Ce que disent les références

Analyse chiffrée des six fichiers fournis (détail dans le rapport d'investigation) :

- **Mélange « éclaircir »** : `composite = max(fond, nuée)` canal par canal, exact au bit
  près pour vol2 et vol3 (vol1 à 50 % d'opacité). Écran et addition sont nettement plus
  loin (écart de 5 à 19 niveaux). Conséquence voulue : la chemise, les manchettes et la
  peau traversent toujours la nuée, qui ne domine que les zones sombres.
- **Grain** : taches gaussiennes de 2 à 4 px à 1080 px de haut (σ ≈ 1,25 px), dont 30 à
  40 % de tirets courts ; chaque point est pâle (≈ 30/255), les cœurs blanchissent par
  superposition. Les calques fournis sont des rendus agrandis d'environ 1280 × 720.
- **Couleur** : une rampe fixe sur la luminance cumulée, bleu nuit dans les zones éparses,
  blanc froid dans les cœurs.
- **Volume** : 13 000 à 34 000 grains distincts, soit l'équivalent de 120 000 à 550 000
  points pâles ; 29 % (masse), 39 % (traîne) et 83 % (voile) du cadre.
- **Brume** au sol dans vol3 seulement ; vide volontaire sur les yeux dans vol3.
- Les calques `_layerUp` sont recalés au pixel près sur le fond ; les « bandes blanches »
  sont des pixels transparents (alpha 0) et n'apparaissent que si l'alpha est ignoré.
- L'image de fond porte un pictogramme ▶ incrusté (21 × 22 px) : un calque correctif
  séparé le masque, le fichier d'origine reste intact.

## 3. Synchroniser avec la musique : trois techniques à ne pas confondre

| Technique | Principe | Son audible | Son coupé volontairement | Lecture muette en fond |
|---|---|---|---|---|
| **Analyse en temps réel** | Web Audio (`AnalyserNode`) écoute le média pendant la lecture | ✅ après un clic (15 à 65 ms de retard) | ❌ zéros dans Chrome et Firefox (le « muet » est appliqué avant l'analyseur) | ❌ le contexte audio reste suspendu sans geste : zéros |
| **Analyse préalable** | Les caractéristiques (énergie, attaques, brillance, ruptures…) sont calculées une fois pour toutes à partir du fichier, stockées en JSON (≈ 50 Ko pour 4 min) | ✅ | ✅ | ✅ |
| **Position de lecture** | À chaque image, on lit le temps du média (`currentTime`, ou `getCurrentTime()` pour YouTube) et on y cherche les caractéristiques préalables | ✅ | ✅ (le temps avance pareil, muet ou non) | ✅ |

L'**analyse préalable + la position de lecture** est la seule combinaison qui fonctionne
dans tous les cas et sur tous les navigateurs. Elle permet en plus l'**anticipation**
(la nuée commence à se rassembler 0,2 à 0,4 s avant un tutti), impossible en temps réel.
Sans aucun média qui joue (refus de l'autoplay, mode économie d'énergie d'iOS, onglet de
fond), une **horloge virtuelle** prend le relais et la nuée continue de suivre la pièce.

Le suivi automatique du tempo est à proscrire : le rubato et les attaques douces de la
musique orchestrale mettent les détecteurs en échec (Holzapfel et al., 2012). On travaille
sur la dynamique, les attaques, la brillance et les ruptures, complétées au besoin par
des repères posés à la main (« conduite »).

## 4. Scénario A : vidéo YouTube intégrée

| Élément | Verdict | Pourquoi |
|---|---|---|
| Lire le son d'une vidéo YouTube dans Web Audio | ❌ non réalisable | Le lecteur est un document d'une autre origine : la page n'a accès qu'à l'API de messages, qui ne donne aucune donnée audio ; la spécification impose du silence pour un média d'une autre origine. |
| Capturer le son de l'onglet ou du micro | ❌ à exclure | Fenêtre d'autorisation à chaque fois, Chrome ordinateur seulement, silence si la vidéo est muette, et contraire à l'interdiction d'isoler le son (Developer Policies III.I). |
| Télécharger le son de YouTube pour l'analyser | ❌ interdit | Conditions d'utilisation de YouTube (accès, téléchargement, modification interdits) et Developer Policies III.E / III.I. |
| Analyser le **fichier maître** fourni par l'ayant droit | ✅ fiable | Même analyse que pour une vidéo hébergée ; il faut le fichier exact mis en ligne (un recadrage dans YouTube Studio ou une nouvelle mise en ligne décale tout). Ici : le Festival de Prades. |
| Se caler sur la position de lecture (`getCurrentTime()`) | ✅ fiable sur ordinateur | Mesuré : mise à jour toutes les ~265 ms, comblée par le lecteur ; avec une horloge locale lissée, écart de l'ordre de la milliseconde en régime établi. Bout en bout estimé à ± 20-40 ms sur ordinateur, ± 50-80 ms sur téléphone (à confirmer sur appareils, Bluetooth en plus). |
| Dessiner la nuée **par-dessus** le lecteur | ❌ interdit | *Required Minimum Functionality* : « You must not display overlays, frames, or other visual elements in front of any part of a YouTube embedded player ». Le lecteur le détecte (IntersectionObserver v2, `inview=0` dans ses statistiques), même avec un canvas transparent, une simple bande de 40 px ou une opacité de 0,99 sur un parent. |
| Nuée **derrière et autour** du lecteur | ✅ conforme | Mesuré : `inview=1` avec un canvas sous le lecteur ou à côté. |
| Lecteur plein cadre rogné (« vidéo de fond ») | ❌ interdit | Rogner cache une partie du lecteur et de ses commandes. Minimum 200 × 200 px. |
| Cacher l'interface YouTube (titre, « Autres vidéos », écran de fin) | ❌ impossible | `controls=0` ne les retire pas, `modestbranding` n'a plus d'effet, `rel=0` limite seulement aux vidéos de la même chaîne. |
| Lecteur caché ou minuscule comme source sonore | ❌ interdit | « Background player » proscrit, son non séparable. |
| Lecture automatique muette (`autoplay=1&mute=1`) | ⚠️ sous conditions | Après consentement, lecteur visible à plus de 50 %, un seul lecteur en autoplay par page ; bloquée par le mode économie d'énergie d'iOS. |
| Charger YouTube sans consentement (France) | ❌ non | CNIL (FAQ cookies, Q23) : consentement préalable pour les vidéos externes qui déposent des traceurs. `youtube-nocookie.com` n'y change rien : le chargeur de l'API (uniquement sur `www.youtube.com`) dépose 5 cookies. Il faut une « façade » cliquable. |
| Publicités | ⚠️ non maîtrisable | YouTube peut monétiser toute vidéo (« Right to Monetize »), y compris hors Programme Partenaire ; les revendications Content ID aussi. |
| Poids | ⚠️ | ≈ 1,2 Mo de scripts avant la vidéo ; sur Safari (pas d'isolation des sites), le démarrage du lecteur peut figer la page 2 s. |

**Obligations si l'on utilise l'API** (le site devient « API Client ») : lien vers les
conditions de YouTube, politique de confidentialité mentionnant les services YouTube et
Google, conditions du site renvoyant à celles de YouTube, pas de `Referrer-Policy:
no-referrer` (erreur 153).

**Conclusion A.** YouTube peut fournir une horloge, pas un support. Le bac à sable le
montre de deux façons : une **disposition conforme** (façade de consentement, lecteur
entier, nuée derrière et autour, pilotée par le temps du lecteur), et un **aperçu
superposé** explicitement marqué « non conforme, test interne », qui permet seulement de
juger l'effet sur une image en mouvement en attendant une vidéo hébergée.

## 5. Scénario B : vidéo hébergée sur notre serveur

| Élément | Verdict | Pourquoi |
|---|---|---|
| Nuée superposée à la vidéo, mélange « éclaircir », réglage d'opacité | ✅ fiable | Tout est sur notre page, aucune règle tierce. |
| Lecture muette automatique (`muted playsinline`, lancée par script) | ✅ fiable, sauf exceptions | Autorisée partout ; bloquée par le mode économie d'énergie d'iOS et certains réglages : l'image fixe (poster) doit être l'état prévu, et l'horloge virtuelle prend le relais. |
| Son dès l'arrivée | ❌ non | Tous les navigateurs exigent un geste (clic, touche) ; le scroll et la molette n'en sont pas. |
| Synchronisation par `currentTime` | ✅ fiable | Indépendante de l'audibilité. Chrome rafraîchit à chaque image (gigue ≈ 1 ms), Safari interpole, Firefox avance par paliers de 40 à 50 ms (à lisser). |
| Synchronisation à l'image près | ✅ | `requestVideoFrameCallback` : Chrome 83, Safari 15.4, Firefox 132. |
| Analyse en temps réel du son | ⚠️ comparaison seulement | Après un clic ; zéros si la vidéo est coupée (Chrome, Firefox) ; sur iOS, le bouton silencieux coupe Web Audio et les flux HLS donnent des zéros ; 15 à 65 ms de retard non compensé. |
| Analyse préalable (ffmpeg) | ✅ fiable | Une passe de quelques secondes pour 4 min ; ≈ 50 Ko de JSON compressé à 30 Hz. Rejouable à chaque nouvelle captation (`outils/analyse-bande-son.py`). |
| Poids | ⚠️ | 720p ≈ 95 Mo pour 5 min, 1080p ≈ 190 Mo : ne télécharger la vidéo que si elle est montrée ; pour l'écoute seule, un AAC de ≈ 5 Mo suffit. Serveur capable de servir des plages d'octets (Apache oui ; `php -S` et `python -m http.server` non). |
| Consentement | ✅ aucun | Pas de traceur tiers si tout est hébergé chez nous. |
| Droits | ⚠️ à régler | Diffuser soi-même une captation suppose les droits de diffusion (orchestre, interprètes, producteur), alors qu'un lecteur YouTube s'appuie sur ceux de la chaîne. |

**Conclusion B.** Le scénario hébergé est le seul qui permette la signature de H4 sur
une image en mouvement, de façon fiable et conforme. Son coût est juridique (droits) et
de bande passante, pas technique.

## 6. Le visiteur doit-il entendre la musique ?

Non. Avec l'analyse préalable, la nuée suit la pièce de la même façon que le son soit
audible, coupé par le visiteur, ou que la vidéo tourne muette en fond, et même si aucun
média ne joue (horloge virtuelle). Le son, lui, ne peut entrer que par un geste
explicite : d'où le principe retenu, la nuée « joue » la pièce en silence dès l'arrivée et
**Écouter** fait entrer la musique à la position courante, sans saut.

## 7. Rendu et mouvement

| Technologie | Verdict | Raison |
|---|---|---|
| Canvas 2D, Pixi ParticleContainer, Three.js mis à jour par le processeur | ❌ | Limités par le processeur : ≈ 280 ns par point en JavaScript sur M1, 20 000 rectangles Canvas 2D = 21,5 ms. |
| Boids (vrai comportement de nuée) | ❌ | Coût quadratique (la démo Three.js s'arrête à 1 024 oiseaux), chaotiques : impossibles à rembobiner (seek, scroll arrière) et dépendants de la cadence. |
| GPGPU (positions dans des textures) | ⚠️ | Garde un état : même défaut que les boids pour le seek ; rendu flottant inégal sur iOS. |
| WebGPU | ⚠️ plus tard | Pas encore universel (iOS < 26, Firefox Android, Linux) ; inutile pour ce modèle. |
| **WebGL2 sans état** (retenu) | ✅ | Un seul contexte, un seul appel de dessin, aucun tampon : la position de chaque point est une fonction pure de ses graines et du temps musical. Mesuré sur M1 : 100 000 points ≈ 0,5 ms, 400 000 ≈ 3,1 ms par image. Même temps → même image, à l'octet près. |

Le comportement collectif est **dessiné** plutôt que simulé : un corps déformable (épine,
nappe ou tube, torsion, filets, satellite) dont la queue suit la tête avec retard, ce qui
produit les traînées et les vagues des vrais vols, et dont les paramètres passent d'une
figure à l'autre (traîne, masse, voile, fil). Le rendu additionne des points pâles puis
applique la rampe de couleur mesurée ; le calque est posé sur l'image en `mix-blend-mode:
lighten`, et l'opacité globale est l'`opacity` CSS : réglage instantané, sans nouveau
rendu, image intacte à 0.

**Budgets indicatifs** (ordinateur mesuré, téléphones extrapolés) : ordinateur 150 000 à
400 000 points, iPhone récent 80 000 à 150 000, Android moyen de gamme 30 000 à 60 000,
entrée de gamme 15 000 à 25 000. Qualité adaptative (nombre de points puis résolution)
d'après l'intervalle entre images. Le mode économie d'énergie d'iOS plafonne à 30 i/s.

## 8. Scroll

L'état lié au scroll (figure « fil », densité, taille, opacité, amplitude, ancrage de
l'image vers la fenêtre) est une fonction pure de la position : l'aller et le retour
donnent la même image (règle 11 du brief commun). Le ralentissement est une déformation du
temps intégrée, jamais une multiplication du temps. Limite connue : sur écran tactile, un
canvas `position: fixed` recalé en JavaScript peut glisser d'une image par rapport à la
photo pendant un lancer rapide ; le prototype efface les effets calés au pixel (visage,
passage derrière le chef) dès que le scroll commence. En production : canvas dans la même
couche de défilement que l'image pour l'ouverture, ou rendu partagé entre deux canvas
(OffscreenCanvas + `bitmaprenderer`).

## 9. Accessibilité

- WCAG 2.2.2 (niveau A) : une animation automatique de plus de 5 s à côté d'autres
  contenus doit pouvoir être mise en pause : bouton toujours accessible.
- `prefers-reduced-motion` : une image fixe de la nuée.
- Le son ne démarre jamais seul ; commandes au clavier.
- La nuée est décorative (`aria-hidden`), sans effet sur le défilement au doigt.

## 10. Architecture recommandée pour une production

1. **Préparation (une fois par captation)** : fichier maître (vidéo ou audio) → `outils/
   analyse-bande-son.py` (ffmpeg) → JSON de caractéristiques (30 Hz, entiers) + repères
   manuels éventuels (« conduite ») ; stockés avec la captation dans le futur CMS, avec
   le décalage de début d'extrait.
2. **Page** : image (ou vidéo hébergée muette, poster = image) + un canvas WebGL2 en
   « éclaircir » ; horloge virtuelle au chargement, horloge média dès que le média joue,
   bouton Écouter ; réglages par contexte (vidéo, ambiance, scroll) exportés en JSON depuis
   le bac à sable et stockés comme configuration.
3. **YouTube**, si on le garde : façade de consentement, lecteur entier non recouvert,
   nuée autour ; horloge sur le temps du lecteur ; caractéristiques tirées du fichier
   maître identique à la mise en ligne.
4. **Côté serveur** : rien de spécial (fichiers statiques, plages d'octets pour la vidéo,
   compression du JSON). Compatible avec la cible PHP / MySQL.

## 11. Résultats mesurés sur le prototype

*À compléter après la construction et la vérification du prototype.*

## 12. À vérifier ou à décider

- **Appareils réels** : iPhone (économie d'énergie activée et non), Android moyen de gamme,
  Safari macOS, Firefox, une sortie Bluetooth : cadence, coût du mélange « éclaircir »,
  dérive thermique sur 5 à 10 min, écart de synchronisation perçu (test « clap »).
- **Fichier maître de Prades 2022** et autorisation du festival, pour analyser la vraie
  captation ; droits de diffusion si l'on choisit la vidéo hébergée.
- **Choix du DA** : « éclaircir » (le chef reste net, comme ses compositions) ou
  « écran » (voile le chef) ; part du visage laissée dégagée ; grain plus gros ou plus
  clairsemé sur téléphone.
- Version propre de l'image de fond sans pictogramme ▶, et en plus haute définition.

## Sources principales

- YouTube : [Required Minimum Functionality](https://developers.google.com/youtube/terms/required-minimum-functionality),
  [Developer Policies](https://developers.google.com/youtube/terms/developer-policies),
  [Developer Policies Guide](https://developers.google.com/youtube/terms/developer-policies-guide),
  [IFrame Player API](https://developers.google.com/youtube/iframe_api_reference),
  [Conditions d'utilisation](https://www.youtube.com/t/terms).
- CNIL : [FAQ cookies et autres traceurs](https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies/FAQ).
- Autoplay et audio : [Chrome autoplay policy](https://developer.chrome.com/blog/autoplay),
  [WebKit, politiques vidéo iOS](https://webkit.org/blog/6784/new-video-policies-for-ios/),
  [WebKit, autoplay macOS](https://webkit.org/blog/7734/auto-play-policy-changes-for-macos/),
  [WebKit bug 216887 (économie d'énergie)](https://bugs.webkit.org/show_bug.cgi?id=216887),
  [Web Audio API](https://webaudio.github.io/web-audio-api/),
  [HTML, activation utilisateur](https://html.spec.whatwg.org/multipage/interaction.html#user-activation-processing-model),
  [requestVideoFrameCallback](https://web.dev/articles/requestvideoframecallback-rvfc).
- Rendu : [état de WebGPU](https://github.com/gpuweb/gpuweb/wiki/implementation-status),
  [démo GPGPU birds de Three.js](https://raw.githubusercontent.com/mrdoob/three.js/dev/examples/webgl_gpgpu_birds.html),
  [caniuse mix-blend-mode](https://caniuse.com/css-mixblendmode).
- Musique : Holzapfel et al., « Selective sampling for beat tracking evaluation », IEEE TASLP 20(9), 2012.
- Bande-son de test : [Tchaïkovski, Symphonie n° 6, IV, Musopen (domaine public)](https://commons.wikimedia.org/wiki/File:Tchaikovsky,_Symphony_No._6_in_B_minor,_Op._74,_%27Pathetique%27_-_IV._Finale_Adagio_lamentoso.ogg).
