/*
 * main.js — amorçage de l'hypothèse 4 « Vol d'étourneaux » → window.__h4
 *
 * Une seule boucle : le ticker GSAP (lagSmoothing(0)) avec Lenis dedans, ou
 * requestAnimationFrame si GSAP manque. À chaque image :
 *   horloge musicale (Sync) → poids des contextes (vidéo / ambiance / scroll) → réglages
 *   mélangés (H4Parametres.melanger) → opacité CSS du canvas → rectangle rendu de l'image
 *   (cover + point focal, déplacé par le scroll) → nuee.layout + nuee.frame.
 * Le scroll donne p (fonction pure de scrollY), une vitesse lissée et la proximité d'une
 * frontière de section. Le son ne part que sur un clic ou une touche, jamais au scroll ;
 * la fin de l'écoute rend l'état de repos du fond sans arrêter la nuée.
 *
 * API de test (window.__h4) : geler(t), degeler(), scroll(p), figure(nom), contexte(nom),
 * etat(), relayout() ; paramètres d'URL : fond, figure, hud, t, nolenis, reduit, panneau.
 *
 * Chaque module est optionnel : sans Nuee l'image reste seule, sans Sync une horloge de
 * secours prend le relais, sans Musique la nuée vole sans piste, sans Reglages les valeurs
 * par défaut s'appliquent. Aucune erreur dans ces cas (avertissements seulement). Le
 * drapeau d'amorçage n'est posé qu'à la toute fin (filet js-timeout de index.html).
 *
 * Variantes : une page qui définit window.H4Variante avant les scripts réutilise ces modules
 * avec ses propres fonds (H4-2 « Nuit » : fond noir ; H4-3 « Captation » : YouTube plein cadre
 * ou vidéo hébergée, filtrée et assombrie). Sans elle, H4 telle quelle.
 */
(function () {
  'use strict';

  const racineDoc = document.documentElement;
  const P = window.H4Parametres || null;
  const RG = window.Reglages || null;
  const S = window.Sync || null;
  const M = window.Musique || null;
  const N = window.Nuee || null;

  const url = new URLSearchParams(location.search);
  const REDUIT = url.get('reduit') === '1' || matchMedia('(prefers-reduced-motion: reduce)').matches;
  const TACTILE = matchMedia('(hover: none), (pointer: coarse)').matches;
  const SANS_LENIS = url.get('nolenis') === '1';
  racineDoc.classList.add(TACTILE ? 'is-touch' : 'is-pointer');
  if (REDUIT) racineDoc.classList.add('reduit');

  // Variante (voir l'en-tête) : racine des médias de H4 vue de la page, fonds proposés (le premier
  // par défaut), vidéo hébergée, YouTube plein cadre, traitement de la vidéo de fond.
  const VAR = window.H4Variante || {};
  const R = typeof VAR.racine === 'string' ? VAR.racine : '';
  const TOUS_FONDS = ['image', 'noir', 'video', 'youtube'];
  const FONDS = (Array.isArray(VAR.fonds) ? VAR.fonds : []).filter((f) => TOUS_FONDS.indexOf(f) >= 0);
  if (!FONDS.length) FONDS.push('image', 'video', 'youtube');
  const YT_PLEIN = !!VAR.youtubePlein;          // lecteur en fond, plein cadre, sans commandes (maquette interne)
  const TRAITEMENT = !!VAR.fondVideo;           // filtre, teinte et voile sur la vidéo ; la pause l'arrête aussi

  const IMAGE = { l: 1920, h: 1080 };                                         // image-sans-vol.png
  const MASQUE = { x: 925 / 1920, y: 525 / 1080, l: 80 / 1920, h: 80 / 1080 }; // calque ▶, en fraction de l'image
  const EXTRAIT = [0, 244];                                                   // bande-son de test (s)
  const YT = { videoId: 'xqEzYw4YqRw', debut: 3399, fin: 3699 };
  // Vidéo hébergée : par défaut captation-test.mp4 (l'image du DA en H.264 + la bande-son de test).
  // imageDA : la vidéo montre l'image du DA (masque ▶, visage dégagé, passage derrière le chef).
  const VIDEO = Object.assign({
    src: R + 'assets/media/captation-test.mp4', affiche: R + 'assets/image-sans-vol.png',
    piste: R + 'data/pathetique-iv-extrait.json', boucle: EXTRAIT, imageDA: true, credit: null,
  }, VAR.video);
  // Pistes pré-analysées par fond (outils/analyse-bande-son.py). YouTube : null = conduite manuelle ;
  // quand le fichier maître de Prades sera analysé (--offset 3399), y mettre son JSON.
  const PISTES = { image: R + 'data/pathetique-iv-extrait.json', noir: R + 'data/pathetique-iv-extrait.json',
    video: VIDEO.piste, youtube: VAR.pisteYouTube || null };
  // v3 (30/10/2026) : les réglages mémorisés avant ne masquent plus les valeurs par défaut (parametres.js)
  const CLE_REGLAGES = VAR.nom ? 'h4-' + VAR.nom + '-reglages-v3' : 'h4-reglages-v3';
  const CLE_CONDUITE = 'h4-conduite-xqEzYw4YqRw';   // partagée avec H4 : même vidéo, même conduite
  const FONDU_CONTEXTE = 1.5;     // s : bascule ambiance ↔ vidéo quand la musique part ou s'arrête
  const YT_DEBORD = 1.6;          // mode conforme : la nuée déborde du lecteur (autour et derrière)
  const YT_ROGNE = { min: 72, part: 0.075 };         // plein cadre : bande rognée en haut et en bas (px, part du lecteur)
  const AFFICHE_YT = { apparition: 2500, retour: 600 };   // ms : le titre de YouTube s'efface ~3 s après le départ
  const FIGURES = ['auto', 'traine', 'masse', 'voile', 'fil'];
  const SONS = ['coupe', 'inaudible', 'audible'];

  /* ---------- outils ---------- */
  const avertis = new Set();
  function avertir(ctx, err) {
    if (avertis.has(ctx)) return;
    avertis.add(ctx);
    console.warn('[h4] ' + ctx, err === undefined ? '' : err);
  }
  function sur(ctx, fn, defaut) {
    try { return fn(); } catch (e) { avertir(ctx, e); return defaut; }
  }
  // propriété ou getter, ou méthode sans argument (le contrat laisse la forme ouverte)
  function lire(obj, nom) {
    if (!obj) return undefined;
    const v = sur('lecture de ' + nom, () => obj[nom]);
    return typeof v === 'function' ? sur('appel de ' + nom, () => v.call(obj)) : v;
  }
  const clamp01 = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);
  const lisse = (x) => { x = clamp01(x); return x * x * (3 - 2 * x); };
  const px = (x) => x.toFixed(2) + 'px';
  // Anacrouse (règle 1) + asymétrie (règle 3), reprise de h3-lever
  function anac(t) {
    t = clamp01(t);
    const a = 0.10 * (1 - Math.cos(Math.min(t, 1 / 3) * 1.5 * Math.PI));
    const u = clamp01((t - 1 / 3) * 1.5);
    const b = 0.10 + 0.90 * (1 - Math.pow(1 - u, 1.6));
    return a + (b - a) * lisse((t - 0.18) / 0.60);
  }
  function estSaisie(cible) {
    if (!cible || !cible.tagName) return false;
    const t = cible.tagName;
    if (t === 'TEXTAREA' || t === 'SELECT' || cible.isContentEditable) return true;
    return t === 'INPUT' && !/^(range|checkbox|radio|button|submit|reset)$/i.test(cible.type || '');
  }
  const $ = (s) => document.querySelector(s);

  /* ---------- éléments ---------- */
  const nav = $('[data-nav]');
  const canvas = $('[data-nuee]');
  const hero = $('[data-ouverture]');
  const cadre = $('[data-cadre]');
  const boiteFond = $('[data-fond]') || cadre;          // H4-3 : boîte du média, filtrée (le flou la déborde)
  const imgFond = $('[data-fond-image]');               // absente en H4-2 (fond noir)
  const texteOuverture = $('.ouverture__texte');
  const masque = $('[data-masque]');
  const reference = $('[data-reference]');
  const scene = $('[data-scene]');
  const ytBoite = $('[data-yt]');
  const ytAlerte = $('[data-yt-alerte]');
  const hint = $('[data-hint]');
  const boutonEcouter = $('[data-ecouter]');
  const libelleEcouter = $('[data-ecouter-libelle]');
  const blocEcoute = $('[data-ecoute]');
  const credit = $('[data-credit]');
  const radiosSon = Array.from(document.querySelectorAll('[data-son] input[type="radio"]'));
  const commandes = $('[data-commandes]');
  const boutonMusiqueBis = $('[data-musique-bis]');
  const boutonPause = $('[data-pause-animation]');
  const libellePause = $('[data-pause-libelle]');
  const boutonReglages = $('[data-ouvrir-reglages]');
  const hud = $('[data-hud]');
  const audio = $('[data-audio]');
  const sections = Array.from(document.querySelectorAll('[data-section]'));

  /* ---------- réglages (panneau, ou valeurs par défaut s'il manque) ---------- */
  const forces = {};
  if (FONDS.indexOf(url.get('fond')) >= 0) forces.fond = url.get('fond');
  if (FIGURES.indexOf(url.get('figure')) >= 0) forces.figure = url.get('figure');
  if (url.get('hud') === '1') forces.hud = true;
  // H4-3 : ?filtre=scene (le préréglage et ses curseurs) et ?assombrir=60, non mémorisés non plus
  if (TRAITEMENT && P && P.FILTRES) {
    const fu = url.get('filtre'), au = parseFloat(url.get('assombrir'));
    if (P.FILTRES[fu]) Object.assign(forces, { fondFiltre: fu }, P.FILTRES[fu].v);
    if (isFinite(au)) forces.fondAssombrir = Math.min(95, Math.max(0, au));
  }

  if (P) P.GLOBAUX.fond = FONDS[0];
  // Variante : ses valeurs par défaut, par contexte (H4-3 : nuée plus légère sur la captation)
  if (P && VAR.defauts) {
    for (const d of P.DEFS) {
      for (const c of P.CONTEXTES) {
        const v = VAR.defauts[c] && VAR.defauts[c][d.id];
        if (typeof v === 'number' && isFinite(v)) d.defaut[c] = v;
      }
    }
  }
  const secours = {
    valeurs: P ? P.defauts() : null,
    globaux: Object.assign({ fond: FONDS[0], superposerYouTube: false, figure: 'auto', analyseDirecte: false,
      masqueLecture: true, reference: 'aucune', hud: false, animation: true }, P ? P.GLOBAUX : {}, forces),
    extra: { masquerNuee: false, son: 'audible' },
  };
  let panneau = null;
  const G = () => (panneau ? panneau.globaux : secours.globaux);
  const V = () => (panneau ? panneau.valeurs : secours.valeurs);
  const X = () => (panneau ? panneau.extra : secours.extra);
  function definirGlobal(id, v, o) {
    if (panneau) return panneau.definirGlobal(id, v, o);
    const ancien = secours.globaux[id];
    secours.globaux[id] = v;
    if (id === 'fondFiltre' && P && P.FILTRES && P.FILTRES[v]) Object.assign(secours.globaux, P.FILTRES[v].v);
    if (ancien !== v) surGlobal(id, v, ancien);
    return true;
  }
  // Commandes sans objet dans la variante : cachées dans le panneau (leurs valeurs restent neutralisées ici)
  const CACHES = [];
  if (FONDS.length < 2) CACHES.push('fond');
  if (FONDS.indexOf('youtube') < 0 || YT_PLEIN) CACHES.push('superposerYouTube');
  if (FONDS.indexOf('image') < 0) CACHES.push('masqueLecture', 'visage', 'derriere');   // pas d'image du DA à contourner
  if (FONDS.indexOf('image') < 0 && FONDS.indexOf('noir') < 0) CACHES.push('reference');
  const LIBELLES_FONDS = { image: 'Image fixe', noir: 'Fond noir', video: 'Vidéo hébergée', youtube: YT_PLEIN ? 'YouTube plein cadre' : 'YouTube' };

  /* ---------- état ---------- */
  const FOND = FONDS.indexOf(secours.globaux.fond) >= 0 ? secours.globaux.fond : FONDS[0];  // relu après création du panneau
  let fond = FOND;
  let lenis = null, lecteur = null, nuee = null, nueeEnPanne = false, piste = null, conduite = null, direct = null;
  let yt = null, ytLecteur = null, ytEtat = 'facade', video = null;
  let ecoute = false, attenteLecture = 0, modeSon = 'audible';
  let gel = null;                  // { t, mur } : horloge figée (tests, ?t=)
  let tFige = null;                // temps musical figé pendant la pause de l'animation / mouvement réduit
  let mur = 0, dernier = performance.now(), sale = true, yRendu = NaN, mRendu = NaN;
  let m = 0, vLisse = 0, yPrec = null, dprVu = window.devicePixelRatio || 1;
  let conduiteActive = false, tConduite = 0;
  let nImages = 0, tIps = performance.now(), ips = 0, tHud = 0, opaciteVue = -1, canvasVisible = null, sansNueeVu = null, hintVu = '';
  let contexteForce = null;        // tests : 'video' | 'ambiance' impose le contexte de l'ouverture
  let animationVue = null, tPauseFond = 0;   // dernier état appliqué de l'animation (pause de la vidéo de fond)
  let ytJoue = 0, ytArret = 0, ytVisible = false;   // H4-3 : affiche par-dessus le lecteur tant qu'il ne joue pas
  const horloge = { t: 0, source: 'virtuelle', enLecture: false, ecartMs: null };
  const dern = { p: 0, v: 0, frontiere: 0, poids: { video: 0, ambiance: 1, scroll: 0 }, params: null, opacite: 0, rect: null, direct: null };
  const L = {
    vw: innerWidth, vh: innerHeight, cw: innerWidth, ch: innerHeight, dpr: 1, rem: 16,
    heroTop: 0, heroH: 1, cadre: { x: 0, y: 0, w: innerWidth, h: innerHeight },
    image: { x: 0, y: 0, w: innerWidth, h: innerHeight }, frontieres: [], yt: null, fixes: [],
  };

  if (RG && P) {
    panneau = sur('Reglages.creer', () => RG.creer({
      parametres: P, cle: CLE_REGLAGES, forces,
      choix: { fond: FONDS.map((f) => [f, LIBELLES_FONDS[f]]) },
      caches: CACHES,
      sousTitre: VAR.sousTitre,
      fondVideo: TRAITEMENT,
      noteFond: YT_PLEIN ? 'YouTube plein cadre : lecteur rogné, filtré et recouvert par la nuée, contraire aux règles de YouTube. Maquette interne pour juger l\'effet ; la mise en ligne passe par la vidéo hébergée.' : null,
      noteFondAlerte: YT_PLEIN,
      surGlobal: (id, v, ancien) => surGlobal(id, v, ancien),
      surValeur: () => { sale = true; },
      surAction: (nom, arg) => surAction(nom, arg),
      surOuverture: (ouvert) => surOuverture(ouvert),
    }), null);
  }
  fond = FONDS.indexOf(G().fond) >= 0 ? G().fond : FOND;
  modeSon = SONS.indexOf(X().son) >= 0 ? X().son : 'audible';
  const YT_CONFORME = fond === 'youtube' && !YT_PLEIN;        // H4 : lecteur entier, jamais recouvert
  const SON_SEUL = fond === 'image' || fond === 'noir';         // la musique sans vidéo : elle s'arrête à la fin de l'écoute
  const IMAGE_DA = fond === 'image' || (fond === 'video' && !!VIDEO.imageDA);   // l'image du DA à l'écran : masque ▶
  const CHEF = IMAGE_DA || YT_CONFORME;     // visage dégagé, passage derrière (H4 le garde autour du lecteur) ; pas sur le noir ni une vraie captation
  const FOND_ANIME = TRAITEMENT && (fond === 'video' || fond === 'youtube');   // la pause de l'animation arrête la vidéo
  racineDoc.classList.add('fond-' + (fond === 'youtube' && YT_PLEIN ? 'youtube-plein' : fond));
  if (TRAITEMENT) racineDoc.classList.add('fond-traite');

  /* ---------- fonds de l'ouverture ---------- */
  if (fond === 'video') {
    video = document.createElement('video');
    video.className = 'ouverture__media';
    video.muted = true;
    video.defaultMuted = true;
    video.playsInline = true;
    video.loop = true;
    video.preload = 'metadata';
    video.setAttribute('muted', '');
    video.setAttribute('playsinline', '');
    video.setAttribute('aria-hidden', 'true');
    video.setAttribute('disablepictureinpicture', '');
    video.poster = VIDEO.affiche;
    video.src = VIDEO.src;
    if (imgFond) imgFond.insertAdjacentElement('afterend', video);
    else boiteFond.prepend(video);
    // vraie captation : l'image sous la vidéo est son affiche, pas l'image du DA
    if (imgFond && !VIDEO.imageDA && VIDEO.affiche) imgFond.src = VIDEO.affiche;
  }
  // H4-3, YouTube plein cadre : le lecteur sous l'affiche (imgFond), qui s'efface quand il joue
  const ytFond = fond === 'youtube' && YT_PLEIN ? document.createElement('div') : null;
  if (ytFond) {
    ytFond.className = 'fond-yt';
    ytFond.setAttribute('aria-hidden', 'true');
    boiteFond.insertBefore(ytFond, imgFond && imgFond.parentNode === boiteFond ? imgFond : boiteFond.firstChild);
  }
  const mediaCadre = video || imgFond;                                  // celui dont on relit object-position
  const mediaSon = fond === 'video' ? video : SON_SEUL ? audio : null;

  if (fond === 'youtube' && credit) {
    credit.textContent = 'Captation du Festival Pablo Casals de Prades 2022 (YouTube), extrait 56:39 → 61:39';
  } else if (fond === 'video' && credit && VIDEO.credit) credit.textContent = VIDEO.credit;

  /* ---------- horloge : Sync.lecteur, ou horloge de secours ---------- */
  // Secours minimal (Sync absent ou en erreur) : horloge virtuelle en boucle, puis média.
  function lecteurSecours(o) {
    const media = o.media || null;
    const a = o.boucle[0], b = o.boucle[1], duree = Math.max(1e-3, b - a);
    let base = a, t0 = performance.now(), son = 'audible';
    const virtuel = () => a + ((((base - a) + (performance.now() - t0) / 1000) % duree) + duree) % duree;
    const joue = () => !!media && !media.paused && !media.ended && media.readyState >= 2;
    const appliquerSon = () => {
      if (!media) return;
      media.muted = son === 'coupe';
      media.volume = son === 'inaudible' ? 0.001 : 1;
    };
    const l = {
      get source() { return joue() ? 'media' : 'virtuelle'; },
      get enLecture() { return joue(); },
      temps() { return joue() ? media.currentTime : virtuel(); },
      ecouter() {
        if (!media) return;
        const t = l.temps();
        if (media.paused) { try { if (Math.abs(media.currentTime - t) > 0.25) media.currentTime = t; } catch (e) { /* pas encore de métadonnées */ } }
        appliquerSon();
        const pr = media.play();
        if (pr && pr.catch) pr.catch(() => {});
      },
      pause() { base = l.temps(); t0 = performance.now(); if (media) media.pause(); },
      relacher() { l.pause(); },                 // l'horloge virtuelle continue depuis la position du média
      aller(t) { base = t; t0 = performance.now(); if (media) { try { media.currentTime = t; } catch (e) { /* rien */ } } },
      son(s) { son = s; appliquerSon(); },
      ecartMs() { return joue() ? 0 : null; },
    };
    if (media && o.autoplayMuet) {
      media.muted = true;
      const pr = media.play();
      if (pr && pr.catch) pr.catch(() => {});
    }
    return l;
  }
  function creerLecteur(o) {
    if (S && typeof S.lecteur === 'function') {
      const l = sur('Sync.lecteur', () => S.lecteur(o), null);
      if (l && typeof l.temps === 'function') return l;
    }
    if (S) avertir('Sync.lecteur indisponible : horloge de secours');
    return lecteurSecours(o);
  }
  // Vidéo hébergée : lecture muette lancée par script, sauf en mouvement réduit (l'affiche reste)
  if (fond === 'youtube') lecteur = creerLecteur({ media: null, boucle: [YT.debut, YT.fin] });
  else lecteur = creerLecteur({ media: mediaSon, boucle: (fond === 'video' ? VIDEO.boucle : EXTRAIT).slice(), autoplayMuet: fond === 'video' && !REDUIT });

  /* ---------- musique : piste pré-analysée, ou conduite manuelle (YouTube) ---------- */
  if (PISTES[fond] && M && typeof M.chargerPiste === 'function') {
    sur('Musique.chargerPiste', () => {
      Promise.resolve(M.chargerPiste(PISTES[fond])).then(
        (p) => { piste = p || null; sale = true; },
        (e) => avertir('piste pré-analysée indisponible', e)
      );
    });
  }
  if (fond === 'youtube' && M && typeof M.conduite === 'function') {
    conduite = sur('Musique.conduite', () => M.conduite({ cle: CLE_CONDUITE }), null);
    if (conduite && panneau) panneau.conduite(conduite, { temps: () => (gel ? gel.t : horloge.t) });
  }
  // Analyse en temps réel : seulement après un geste, et pour comparaison (HUD)
  function assurerDirect() {
    if (direct || !mediaSon || !M || typeof M.analyseDirecte !== 'function') return;
    direct = sur('Musique.analyseDirecte', () => M.analyseDirecte(mediaSon), null) || null;
  }
  function infoConduite(c) {
    if (RG && typeof RG.infoConduite === 'function') return RG.infoConduite(c);
    return { donnees: !!c && Number(c.duree) > 0 };
  }

  /* ---------- la nuée ---------- */
  const appareil = (P && P.APPAREILS && P.APPAREILS[TACTILE ? 'tactile' : 'ordinateur']) || { maxPoints: 60000, dprMax: 1.5 };
  if (N && P && canvas && typeof N.create === 'function') {
    let chef = null;                    // silhouette du chef : seulement sur l'image du DA
    if (CHEF) {
      chef = new Image();
      chef.decoding = 'async';
      chef.src = R + 'assets/chef-masque.png';
    }
    nuee = sur('Nuee.create', () => N.create(canvas, {
      masque: chef, maxPoints: appareil.maxPoints, dprMax: appareil.dprMax, tactile: TACTILE,
    }), null) || null;
  }
  const nueeOk = () => !!nuee && !nueeEnPanne && lire(nuee, 'ok') !== false;

  /* ---------- géométrie : rectangle rendu de l'image (même formule que CSS) ---------- */
  function lirePosition(txt) {
    const mots = String(txt || '').trim().split(/\s+/);
    const cles = { left: 0, center: 0.5, right: 1, top: 0, bottom: 1 };
    const un = (mot) => {
      if (!mot) return { f: 0.5 };
      if (mot in cles) return { f: cles[mot] };
      const r = /^(-?[\d.]+)(%|px)?$/.exec(mot);
      if (!r) return { f: 0.5 };
      return r[2] === 'px' ? { px: parseFloat(r[1]) } : { f: parseFloat(r[1]) / 100 };
    };
    return { x: un(mots[0]), y: un(mots[1] || 'center') };
  }
  // object-fit: cover + object-position : échelle = max(L/l, H/h), décalage = (boîte − image) × position
  function couvrir(W, H, iw, ih, pos) {
    const s = Math.max(W / iw, H / ih);
    const w = iw * s, h = ih * s;
    const x = pos.x.px !== undefined ? pos.x.px : (W - w) * pos.x.f;
    const y = pos.y.px !== undefined ? pos.y.px : (H - h) * pos.y.f;
    return { x, y, w, h };
  }
  // Point focal du média ; sans média (H4-2), celui du cadre (--focal-x/y, mêmes valeurs que le CSS)
  function positionMedia() {
    if (mediaCadre) return lirePosition(getComputedStyle(mediaCadre).objectPosition);
    const cs = getComputedStyle(cadre);
    const f = (nom) => { const x = parseFloat(cs.getPropertyValue(nom)); return isFinite(x) ? x : 0.5; };
    return { x: { f: f('--focal-x') }, y: { f: f('--focal-y') } };
  }
  function dimensionsMedia() {
    if (video && video.videoWidth > 0) return { l: video.videoWidth, h: video.videoHeight };
    if (imgFond && imgFond.naturalWidth > 0) return { l: imgFond.naturalWidth, h: imgFond.naturalHeight };
    return IMAGE;
  }
  // H4-3 : lecteur 16:9 qui couvre la boîte, agrandi pour que le titre, les boutons et le logo que
  // YouTube pose en haut et en bas de l'image tombent hors champ (maquette interne, voir LISEZ-MOI)
  function disposerFondYouTube(W, H) {
    const h = Math.ceil(Math.max(W * 9 / 16, H + 2 * YT_ROGNE.min, H / (1 - 2 * YT_ROGNE.part)));
    const w = Math.ceil(h * 16 / 9);
    // point focal horizontal du cadre (le chef, comme sur l'image) ; centré en hauteur, les deux bandes rognées restent égales
    const fx = positionMedia().x.f;
    const r = { x: (W - w) * (isFinite(fx) ? fx : 0.5), y: (H - h) / 2, w, h };
    // l'affiche (une image de la captation) prend exactement le cadre du lecteur : fondu sans saut
    for (const n of [ytFond, imgFond]) {
      if (!n) continue;
      const s = n.style;
      s.left = px(r.x); s.top = px(r.y); s.width = w + 'px'; s.height = h + 'px';
    }
    return r;
  }
  function placerCalques() {
    // image calée dans la boîte du média ; un calque posé dans le cadre se décale du débord de la boîte
    const im = L.image;
    const dx = (n) => (n.parentNode === boiteFond ? 0 : L.cadre.dx || 0);
    const dy = (n) => (n.parentNode === boiteFond ? 0 : L.cadre.dy || 0);
    if (masque) {
      const s = masque.style;
      s.left = px(dx(masque) + im.x + im.w * MASQUE.x);
      s.top = px(dy(masque) + im.y + im.h * MASQUE.y);
      s.width = px(im.w * MASQUE.l);
      s.height = px(im.h * MASQUE.h);
      masque.classList.add('est-place');
    }
    if (reference) {
      const s = reference.style;
      s.left = px(dx(reference) + im.x); s.top = px(dy(reference) + im.y); s.width = px(im.w); s.height = px(im.h);
    }
  }

  // Mode YouTube : lecteur 16:9 entier (≥ 480 × 270 sur ordinateur, pleine largeur et ≥ 200 px
  // de haut sur téléphone), sous la nav, au-dessus des commandes fixes, à côté du panneau ouvert.
  // Si le panneau ne laisse pas 480 px à côté de lui, il devient une feuille sous le lecteur.
  function disposerYouTube(sy) {
    const tel = L.vw <= 720;
    const navH = nav ? nav.offsetHeight : 0;
    const gout = tel ? 16 : 3 * L.rem;
    const largeurPanneau = parseFloat(getComputedStyle(racineDoc).getPropertyValue('--panneau-l')) || 360;
    const ouvert = !!(panneau && panneau.ouvert) && !tel;
    const feuille = ouvert && L.vw - largeurPanneau - 2 * gout < 480;
    const panneauL = ouvert && !feuille ? largeurPanneau : 0;
    racineDoc.classList.toggle('panneau-feuille', feuille);
    const hudH = hud && !hud.hidden && !tel ? hud.offsetHeight : 0;
    const haut = navH + (tel ? 8 : 20);
    const bas = tel ? 88 : Math.max(8 * L.rem, hudH ? hudH + 2.6 * L.rem : 0);
    let l, disposition = 'cote';
    if (tel) {
      disposition = 'telephone';
      l = L.vw;
    } else {
      const colTexte = 16.5 * L.rem, ecart = 3 * L.rem;
      const dispoL = L.vw - panneauL - 2 * gout;
      const dispoH = L.vh - haut - bas;
      const cote = Math.min(dispoL - colTexte - ecart, dispoH * 16 / 9, 1280);
      if (cote >= 480) l = cote;
      else { disposition = 'pile'; l = Math.max(480, Math.min(dispoL, (dispoH - 10 * L.rem) * 16 / 9, 1280)); }
      l = Math.floor(l);
    }
    const st = hero.style;
    st.setProperty('--yt-l', l + 'px');
    st.setProperty('--yt-h', Math.round(l * 9 / 16) + 'px');
    st.setProperty('--yt-haut', haut + 'px');
    st.setProperty('--yt-bas', bas + 'px');
    st.setProperty('--yt-panneau', panneauL + 'px');
    if (scene) scene.setAttribute('data-disposition', disposition);
    const r = ytBoite.getBoundingClientRect();
    L.yt = { x: r.left, y: r.top + sy, w: r.width, h: r.height };
    // feuille de réglages (téléphone, ou ordinateur étroit) : jamais plus haute que l'espace sous
    // le lecteur quand la page est en haut (le lecteur ne fait ensuite que monter)
    if (tel || feuille) racineDoc.style.setProperty('--panneau-max-h', Math.max(160, Math.floor(L.vh - (L.yt.y + L.yt.h) - 8)) + 'px');
    else racineDoc.style.removeProperty('--panneau-max-h');
  }

  /* ---------- apparitions (blocs sous l'ouverture) : fonction pure de scrollY ---------- */
  const revelations = Array.from(document.querySelectorAll('[data-revele]'))
    .filter((el) => !hero.contains(el))
    .map((el) => {
      const freres = Array.from(el.parentNode.children).filter((x) => x.hasAttribute('data-revele'));
      return { el, haut: 0, q: -1, decalage: Math.max(0, freres.indexOf(el)) * 0.08 };
    });
  function majRevelations(y, force) {
    for (const r of revelations) {
      const q = anac((y + L.vh * 0.92 - r.haut) / (0.32 * L.vh) - r.decalage);
      if (!force && Math.abs(q - r.q) < 0.002) continue;
      r.q = q;
      const s = r.el.style;
      s.opacity = q.toFixed(3);
      if (REDUIT) continue;
      const fini = q >= 0.999;
      s.transform = fini ? '' : 'translate3d(0,' + ((1 - q) * 10).toFixed(2) + 'px,0)';   // montée de 10 px
      s.filter = fini ? '' : 'blur(' + ((1 - q) * 2).toFixed(2) + 'px)';                  // flou 2 px → net
    }
  }

  /* ---------- mise en page ---------- */
  function mesurer() {
    const sy = window.scrollY;
    L.vw = window.innerWidth;
    L.vh = window.innerHeight;
    L.rem = parseFloat(getComputedStyle(racineDoc).fontSize) || 16;
    L.cw = (canvas && canvas.clientWidth) || L.vw;
    L.ch = (canvas && canvas.clientHeight) || L.vh;
    L.dpr = Math.min(window.devicePixelRatio || 1, appareil.dprMax || 2);
    if (YT_CONFORME) disposerYouTube(sy);
    const rh = hero.getBoundingClientRect();
    L.heroTop = rh.top + sy;
    L.heroH = Math.max(1, rh.height);
    if (!YT_CONFORME) {
      const rc = boiteFond.getBoundingClientRect(), rk = cadre.getBoundingClientRect();
      L.cadre = { x: rc.left, y: rc.top + sy, w: rc.width, h: rc.height, dx: rc.left - rk.left, dy: rc.top - rk.top };
      if (ytFond) L.image = disposerFondYouTube(rc.width, rc.height);
      else {
        const d = dimensionsMedia();
        L.image = couvrir(rc.width, rc.height, d.l, d.h, positionMedia());
      }
      placerCalques();
    }
    L.frontieres = sections.map((s) => s.getBoundingClientRect().top + sy).filter((y) => y > L.heroTop + 1);
    for (const r of revelations) r.el.style.transform = '';
    for (const r of revelations) r.haut = r.el.getBoundingClientRect().top + sy;
  }
  // Éléments fixes à tenir hors du lecteur YouTube (mode conforme) ; la nav et l'indice,
  // qui défilent avec le lecteur, sont vérifiés une fois ici.
  function mesurerFixes() {
    L.fixes = [];
    if (!YT_CONFORME || !L.yt) return;
    const sy = window.scrollY;
    const lecteurVue = { x: L.yt.x, y: L.yt.y - sy, w: L.yt.w, h: L.yt.h };
    for (const el of [nav, hint]) {
      if (!el) continue;
      el.classList.remove('evite');
      el.classList.toggle('evite', chevauche(el.getBoundingClientRect(), lecteurVue));
    }
    for (const el of [commandes, hud, panneau && panneau.element]) {
      if (!el || el.hidden) continue;
      L.fixes.push({ el, r: el.getBoundingClientRect() });
    }
  }
  function chevauche(r, b) {
    return r.width > 0 && r.height > 0 &&
      r.right > b.x + 0.5 && r.left < b.x + b.w - 0.5 && r.bottom > b.y + 0.5 && r.top < b.y + b.h - 0.5;
  }
  function relayout() {
    sur('mise en page', mesurer);
    sur('évitement', mesurerFixes);
    majRevelations(window.scrollY, true);
    sale = true;
  }
  let attente = false;
  function planifier() {
    if (attente) return;
    attente = true;
    requestAnimationFrame(() => { attente = false; relayout(); });
  }

  /* ---------- scroll : p, vitesse, frontière ---------- */
  function progression(y) {
    return lisse((y - L.heroTop) / L.heroH);           // 0 en haut, 1 quand l'ouverture est sortie
  }
  function frontiere(y) {
    const c = y + L.vh / 2, portee = 0.35 * L.vh;
    let f = 0;
    for (const b of L.frontieres) {
      const d = Math.abs(b - c) / portee;
      if (d < 1) f = Math.max(f, lisse(1 - d));
    }
    return f;
  }

  /* ---------- rectangle de la nuée dans le canvas (px CSS) ---------- */
  function rectNuee(y) {
    if (YT_CONFORME && L.yt) {
      // image de la vidéo dans le lecteur (16:9 contenu), agrandie autour de son centre en mode conforme
      const s = Math.min(L.yt.w / 16, L.yt.h / 9), w = 16 * s, h = 9 * s;
      const k = G().superposerYouTube ? 1 : YT_DEBORD;
      const cx = L.yt.x + L.yt.w / 2, cy = L.yt.y - y + L.yt.h / 2;
      return { x: cx - w * k / 2, y: cy - h * k / 2, w: w * k, h: h * k };
    }
    return { x: L.cadre.x + L.image.x, y: L.cadre.y - y + L.image.y, w: L.image.w, h: L.image.h };
  }

  /* ---------- globaux ---------- */
  function appliquerMasque() {
    racineDoc.classList.toggle('sans-masque', !(IMAGE_DA || ytFond) || !G().masqueLecture);   // plein cadre : sur l'affiche
  }
  function appliquerReference() {
    const r = G().reference;
    const on = fond !== 'youtube' && !!r && r !== 'aucune';
    if (reference) {
      if (on) {
        const src = R + 'assets/' + r + '_layerUp.png';
        if (reference.getAttribute('src') !== src) reference.setAttribute('src', src);
      }
      reference.hidden = !on;
    }
    racineDoc.classList.toggle('nuee-masquee', on && !!X().masquerNuee);
  }
  function appliquerHud() {
    if (!hud) return;
    const on = !!G().hud;
    hud.hidden = !on;
    tHud = 0;
  }
  function appliquerAnimation() {
    const on = G().animation !== false;
    if (boutonPause) boutonPause.setAttribute('aria-pressed', on ? 'false' : 'true');
    if (libellePause) libellePause.textContent = on ? 'Pause de l\'animation' : 'Reprendre l\'animation';
    const ip = boutonPause && boutonPause.querySelector('[data-icone-pause]');
    const ir = boutonPause && boutonPause.querySelector('[data-icone-reprise]');
    if (ip) ip.toggleAttribute('hidden', !on);     // SVG : pas de propriété .hidden, l'attribut seulement
    if (ir) ir.toggleAttribute('hidden', on);
    racineDoc.classList.toggle('animation-en-pause', !on);
    if (on) { tFige = null; dernier = performance.now(); }
    // H4-3 : la vidéo de fond bouge, la pause l'arrête aussi (WCAG 2.2.2) ; l'écoute s'arrête avec elle
    if (FOND_ANIME && lecteur && animationVue !== on) {
      if (!on) {
        if (ecoute) basculerEcoute();
        sur('lecteur.pause', () => lecteur.pause());
      } else if (animationVue !== null) sur('lecteur.lire', () => lecteur.lire());
    }
    animationVue = on;
    sale = true;
  }
  function appliquerSuperposition() {
    const on = YT_CONFORME && !!G().superposerYouTube;
    racineDoc.classList.toggle('yt-superpose', on);
    if (ytAlerte) ytAlerte.hidden = !on;
    if (!on) for (const f of L.fixes) f.el.classList.remove('evite');
  }
  // H4-3 : filtre CSS sur la boîte du média (affiche, vidéo ou lecteur), teinte en « couleur », voile noir
  function appliquerTraitement() {
    if (!TRAITEMENT || !cadre) return;
    const g = G();
    const n = (id, d) => (typeof g[id] === 'number' && isFinite(g[id]) ? g[id] : d);
    const inv = n('fondInversion', 0), sat = n('fondSaturation', 100), con = n('fondContraste', 100), flou = n('fondFlou', 0);
    const f = [];
    if (inv > 0) f.push('invert(' + (inv / 100).toFixed(2) + ')');
    if (sat !== 100) f.push('saturate(' + (sat / 100).toFixed(2) + ')');
    if (con !== 100) f.push('contrast(' + (con / 100).toFixed(2) + ')');
    if (flou > 0) f.push('blur(' + flou + 'px)');
    const t = P && P.TEINTES ? P.TEINTES[g.fondTeinte] : null;
    const s = cadre.style;
    s.setProperty('--fond-filtre', f.length ? f.join(' ') : 'none');
    s.setProperty('--fond-flou', flou + 'px');
    s.setProperty('--fond-voile', (n('fondAssombrir', 0) / 100).toFixed(3));
    s.setProperty('--fond-teinte', t ? t.couleur : 'transparent');
    s.setProperty('--fond-teinte-o', t ? (n('fondTeinteForce', 0) / 100).toFixed(3) : '0');
    planifier();                        // le flou fait déborder la boîte du média : la nuée se recale
  }
  function surGlobal(id, v) {
    if (id !== 'fond' && id.indexOf('fond') === 0) { appliquerTraitement(); sale = true; return; }
    switch (id) {
      case 'fond': changerFond(v); break;
      case 'masqueLecture': appliquerMasque(); break;
      case 'reference': case 'masquerNuee': appliquerReference(); break;
      case 'hud': appliquerHud(); planifier(); break;
      case 'animation': appliquerAnimation(); break;
      case 'superposerYouTube': appliquerSuperposition(); planifier(); break;
      case 'analyseDirecte': if (v) assurerDirect(); break;   // le changement de case est un geste
      default: break;
    }
    sale = true;
  }
  function changerFond(v) {
    if (FONDS.indexOf(v) < 0 || v === fond) return;
    if (panneau) sur('sauvegarde', () => panneau.sauver());
    const u = new URL(location.href);
    u.searchParams.set('fond', v);
    location.assign(u.toString());
  }
  function surAction(nom, arg) {
    if (nom === 'ecouter') basculerEcoute();
    else if (nom === 'son') changerSon(arg);
  }
  function surOuverture(ouvert) {
    if (boutonReglages) boutonReglages.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    racineDoc.classList.toggle('panneau-ouvert', !!ouvert);
    if (!ouvert) racineDoc.classList.remove('panneau-feuille');
    planifier();
  }
  function basculerPanneau() {
    if (panneau) panneau.basculer();
  }

  /* ---------- écoute : appelée dans le gestionnaire du clic ou de la touche (geste) ---------- */
  function noteEcoute(txt) {
    if (!blocEcoute) return;
    let n = blocEcoute.querySelector('.ecoute__note');
    if (!txt) { if (n) n.remove(); return; }
    if (!n) { n = document.createElement('p'); n.className = 'ecoute__note'; n.setAttribute('role', 'status'); blocEcoute.appendChild(n); }
    n.textContent = txt;
  }
  // Fin de l'écoute = retour à l'état de repos du fond, la nuée ne s'arrête jamais : image fixe,
  // le média audio s'arrête et l'horloge virtuelle continue depuis la même position ; vidéo
  // hébergée et YouTube, la lecture continue en muet (comme au chargement).
  function basculerEcoute() {
    if (fond === 'youtube' && !ytLecteur) {
      noteEcoute('Le son vient du lecteur YouTube : chargez d\'abord la vidéo.');
      const b = ytBoite && ytBoite.querySelector('[data-yt-charger]');
      if (b) b.focus();
      return;
    }
    const l = lecteur;
    if (!l) return;
    noteEcoute('');
    if (ecoute) {
      if (SON_SEUL) sur('lecteur.relacher', () => (typeof l.relacher === 'function' ? l.relacher() : l.pause()));
      else sur('lecteur.son', () => l.son('coupe'));
      ecoute = false;
    } else {
      if (G().analyseDirecte || modeSon === 'inaudible') assurerDirect();
      const promesse = sur('lecteur.ecouter', () => l.ecouter());
      sur('lecteur.son', () => l.son(modeSon));
      ecoute = true;
      attenteLecture = 0;
      // lecture refusée (navigateur, erreur du média) : le bouton revient à « Écouter » tout de suite
      Promise.resolve(promesse).then((r) => {
        if (r !== 'virtuelle' || !ecoute || lecteur !== l) return;
        ecoute = false;
        majEcoute();
        noteEcoute('La lecture n\'a pas pu démarrer : la nuée continue en silence.');
      }, () => {});
    }
    majEcoute();
  }
  function changerSon(v) {
    if (SONS.indexOf(v) < 0) return;
    modeSon = v;
    if (v === 'inaudible') assurerDirect();     // « inaudible » = sortie à gain 0 du graphe d'analyse
    if (panneau) panneau.definirSon(v); else secours.extra.son = v;
    for (const r of radiosSon) r.checked = r.value === v;
    if (ecoute && lecteur) sur('lecteur.son', () => lecteur.son(v));   // hors écoute : préférence pour le prochain « Écouter »
  }
  // Libellés : sur l'image fixe la musique s'arrête (« Pause ») ; sur une vidéo, elle continue en muet.
  const LIBELLE_FIN = SON_SEUL ? 'Pause' : 'Couper le son';
  const libelleEcoute = () => (ecoute ? LIBELLE_FIN : 'Écouter');
  if (boutonMusiqueBis) {
    const t = boutonMusiqueBis.querySelector('.commande__texte');
    if (t) t.textContent = SON_SEUL ? 'Pause de la musique' : 'Couper le son';
  }
  function majEcoute() {
    const lib = libelleEcoute();
    if (libelleEcouter && libelleEcouter.textContent !== lib) libelleEcouter.textContent = lib;
    if (boutonEcouter) boutonEcouter.setAttribute('data-etat', ecoute ? 'lecture' : 'arret');
    if (boutonMusiqueBis && boutonMusiqueBis.hidden === ecoute) boutonMusiqueBis.hidden = !ecoute;
  }

  /* ---------- YouTube : façade de consentement, puis lecteur ---------- */
  // Plein cadre (H4-3) : la façade se range dans le texte de l'ouverture, au-dessus de l'écoute ;
  // l'affiche du fond (imgFond) tient lieu d'image en attendant.
  function construireFacade() {
    if (!ytBoite) return;
    if (YT_PLEIN && texteOuverture && blocEcoute && ytBoite.parentNode !== texteOuverture) texteOuverture.insertBefore(ytBoite, blocEcoute);
    ytBoite.hidden = false;
    ytBoite.textContent = '';
    const facade = document.createElement('div');
    facade.className = 'yt__facade';
    facade.setAttribute('data-yt-facade', '');
    facade.innerHTML =
      (YT_PLEIN ? '' : '<img class="yt__affiche" src="' + R + 'assets/image-sans-vol.png" alt="">') +
      '<div class="yt__consentement">' +
      '<p class="yt__surtitre">Captation · Festival Pablo Casals, Prades 2022</p>' +
      '<p class="yt__texte">' + (YT_PLEIN ? 'La vidéo de fond' : 'Cette vidéo') + ' est hébergée par YouTube. La charger dépose des cookies de YouTube (Google) ' +
      'sur votre appareil<span class="yt__plus"> et leur transmet des données de navigation. Rien n\'est chargé avant votre accord</span>.</p>' +
      '<button type="button" class="yt__bouton" data-yt-charger>' + (YT_PLEIN ? 'Charger la captation' : 'Charger la vidéo YouTube') + '</button>' +
      '<p class="yt__liens"><a href="https://policies.google.com/privacy?hl=fr" target="_blank" rel="noopener">Confidentialité Google</a>' +
      ' · <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener">Conditions de YouTube</a></p>' +
      '</div>';
    ytBoite.appendChild(facade);
    facade.querySelector('[data-yt-charger]').addEventListener('click', chargerYouTube);
  }
  // Plein cadre : l'affiche couvre le lecteur jusqu'à 2,5 s de lecture (le titre s'est effacé),
  // et revient s'il cesse de jouer plus de 0,6 s (pause, attente, erreur), aussitôt en pause voulue.
  function majAfficheYouTube(now) {
    const p = yt && yt.player;
    const st = p && p.getPlayerState ? sur('player.getPlayerState', () => p.getPlayerState(), -1) : -1;
    if (st === 1) { ytArret = 0; if (!ytJoue) ytJoue = now; }
    else { ytJoue = 0; if (!ytArret) ytArret = now; }
    let v = ytVisible ? !(ytArret && now - ytArret > AFFICHE_YT.retour) : !!ytJoue && now - ytJoue > AFFICHE_YT.apparition;
    if (G().animation === false) v = false;
    if (v !== ytVisible) { ytVisible = v; racineDoc.classList.toggle('yt-joue', v); }
  }
  function etatYouTube(txt, reessayer) {
    let n = ytBoite.querySelector('.yt__etat');
    if (!txt) { if (n) n.remove(); return; }
    if (!n) { n = document.createElement('div'); n.className = 'yt__etat'; n.setAttribute('role', 'status'); ytBoite.appendChild(n); }
    n.textContent = txt;
    if (reessayer) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'yt__bouton';
      b.textContent = 'Réessayer';
      b.addEventListener('click', () => { ytEtat = 'facade'; construireFacade(); });
      const w = document.createElement('div');
      w.append(document.createTextNode(txt), document.createElement('br'), b);
      n.textContent = '';
      n.appendChild(w);
    }
  }
  function chargerYouTube() {
    if (ytEtat !== 'facade') return;
    ytEtat = 'chargement';
    const facade = ytBoite.querySelector('[data-yt-facade]');
    if (facade) facade.remove();
    etatYouTube('Chargement du lecteur YouTube…');
    noteEcoute('');
    const porte = document.createElement('div');
    porte.className = 'yt__lecteur';
    (ytFond || ytBoite).appendChild(porte);
    if (!S || typeof S.youtube !== 'function') {
      ytEtat = 'erreur';
      etatYouTube('Lecteur YouTube indisponible (module de synchronisation absent).', true);
      return;
    }
    // plein cadre : ni commandes, ni clavier, ni plein écran, ni annotations ; pas de départ en mouvement réduit
    const vars = YT_PLEIN ? { controls: 0, disablekb: 1, fs: 0, iv_load_policy: 3, autoplay: REDUIT ? 0 : 1 } : undefined;
    let promesse;
    try { promesse = Promise.resolve(S.youtube(porte, { videoId: YT.videoId, debut: YT.debut, fin: YT.fin, playerVars: vars })); }
    catch (e) { promesse = Promise.reject(e); }
    promesse.then((r) => {
      if (!r || !r.lecteur || typeof r.lecteur.temps !== 'function') throw new Error('Sync.youtube : pas de lecteur');
      if (!r.player) {                    // API YouTube injoignable : l'horloge reste virtuelle
        lecteur = r.lecteur;
        throw new Error(lire(r.lecteur, 'etat') || 'lecteur YouTube absent');
      }
      yt = r;
      ytLecteur = r.lecteur;
      lecteur = ytLecteur;
      ytEtat = 'pret';
      ecoute = false;
      if (YT_PLEIN) {
        const ifr = sur('player.getIframe', () => r.player.getIframe(), null);
        if (ifr) { ifr.setAttribute('tabindex', '-1'); ifr.setAttribute('aria-hidden', 'true'); }
      }
      majEcoute();
      etatYouTube('');
      planifier();
    }).catch((e) => {
      avertir('Sync.youtube', e);
      ytEtat = 'erreur';
      if (porte.parentNode) porte.remove();
      etatYouTube('La vidéo YouTube n\'a pas pu être chargée.', true);
    });
  }

  /* ---------- commandes ---------- */
  if (boutonEcouter) boutonEcouter.addEventListener('click', basculerEcoute);
  if (boutonMusiqueBis) boutonMusiqueBis.addEventListener('click', basculerEcoute);
  for (const r of radiosSon) {
    r.checked = r.value === modeSon;
    r.addEventListener('change', () => { if (r.checked) changerSon(r.value); });
  }
  if (boutonPause) boutonPause.addEventListener('click', () => definirGlobal('animation', G().animation === false));
  if (boutonReglages) {
    if (panneau) boutonReglages.addEventListener('click', basculerPanneau);
    else boutonReglages.hidden = true;
  }
  document.addEventListener('keydown', (e) => {
    if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || estSaisie(e.target)) return;
    if (e.key === 'p' || e.key === 'P') { e.preventDefault(); basculerPanneau(); }
    else if (e.key === 'Escape' && panneau && panneau.ouvert) panneau.fermer();
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) { dernier = performance.now(); sale = true; }   // reprise à l'instant courant
  });

  /* ---------- boucle ---------- */
  function figureForcee() {
    const f = G().figure;
    return f && f !== 'auto' ? f : null;
  }
  function pisteActive() {
    if (fond === 'youtube') return piste || (conduiteActive ? conduite : null);   // fichier maître, sinon conduite
    return piste;
  }
  function lireHorloge() {
    const l = lecteur;
    if (!l) return;
    const t = Number(sur('lecteur.temps', () => l.temps(), NaN));
    if (isFinite(t)) horloge.t = t;
    horloge.source = lire(l, 'source') || 'virtuelle';
    horloge.enLecture = !!lire(l, 'enLecture');
    // écart horloge − temps brut du média, mesuré dans la même image que temps()
    const e = typeof l.ecartMs === 'function' ? sur('lecteur.ecartMs', () => l.ecartMs(), null) : null;
    horloge.ecartMs = typeof e === 'number' && isFinite(e) ? e : null;
  }

  function tick() {
    const now = performance.now();
    let dt = (now - dernier) / 1000;
    dernier = now;
    if (!(dt > 0)) dt = 0;
    if (dt > 0.1) dt = 0.1;
    if (lenis) sur('lenis.raf', () => lenis.raf(now));
    if ((window.devicePixelRatio || 1) !== dprVu) { dprVu = window.devicePixelRatio || 1; planifier(); }
    nImages++;
    if (now - tIps >= 1000) { ips = nImages * 1000 / (now - tIps); nImages = 0; tIps = now; }

    // scroll
    const y = window.scrollY;
    const p = progression(y);
    const vBrut = yPrec === null || dt <= 0 ? 0 : (y - yPrec) / dt;
    yPrec = y;
    vLisse += (vBrut - vLisse) * (1 - Math.exp(-dt / 0.2));
    if (Math.abs(vLisse) < 0.5) vLisse = 0;
    const fr = frontiere(y);

    // horloge et contextes (musique = un média joue vraiment, même muet)
    lireHorloge();
    const musique = horloge.enLecture && horloge.source !== 'virtuelle';
    const cible = contexteForce ? (contexteForce === 'video' ? 1 : 0) : musique ? 1 : 0;
    if (contexteForce) m = cible;                // tests : bascule immédiate
    else if (m !== cible) m = cible > m ? Math.min(1, m + dt / FONDU_CONTEXTE) : Math.max(0, m - dt / FONDU_CONTEXTE);
    const me = lisse(m);
    const poids = { video: (1 - p) * me, ambiance: (1 - p) * (1 - me), scroll: p };
    const params = P && V() ? sur('H4Parametres.melanger', () => P.melanger(V(), poids), null) : null;
    if (params && !CHEF) { params.visage = 0; params.derriere = 0; }   // pas de chef immobile à contourner
    // vidéo de fond partie alors que l'animation est en pause (lecture automatique au chargement,
    // lecteur YouTube qui démarre après le consentement) : on l'arrête, sauf écoute demandée
    if (FOND_ANIME && musique && !ecoute && G().animation === false && now - tPauseFond > 500) {
      tPauseFond = now;
      sur('lecteur.pause', () => lecteur.pause());
    }
    if (ecoute && !musique) {                     // écoute qui ne démarre pas ou s'arrête (refus, erreur, pause du lecteur) : on rend la main
      attenteLecture += dt;
      if (attenteLecture > 8) { ecoute = false; majEcoute(); }
    } else attenteLecture = 0;
    if (now - tConduite > 500) { tConduite = now; conduiteActive = !!conduite && infoConduite(conduite).donnees; }

    // calque : opacité CSS = réglage « Opacité globale » mélangé ; à 0 le canvas n'est plus composé
    // du tout (sinon Chrome rastérise autrement les calques posés dessus : écarts de ±3 niveaux)
    const ok = nueeOk();
    if (ok !== sansNueeVu) { sansNueeVu = ok; racineDoc.classList.toggle('sans-nuee', !ok); }
    const opacite = params && isFinite(params.opacite) ? clamp01(params.opacite / 100) : 0;
    if (canvas && Math.abs(opacite - opaciteVue) > 0.0005) {
      const visible = opacite > 0.0005;
      if (visible !== canvasVisible) { canvasVisible = visible; canvas.style.visibility = visible ? '' : 'hidden'; }
      opaciteVue = opacite;
      canvas.style.opacity = opacite.toFixed(4);
    }

    // indice « Défiler » : s'efface sur le premier tiers d'écran
    if (hint) {
      const o = (1 - anac(y / (0.3 * L.vh))).toFixed(3);
      if (o !== hintVu) { hintVu = o; hint.style.opacity = o; }
    }
    majRevelations(y, false);

    // mode YouTube conforme : rien de fixe ne passe sur le lecteur
    if (YT_CONFORME && L.yt && !G().superposerYouTube) {
      const b = { x: L.yt.x, y: L.yt.y - y, w: L.yt.w, h: L.yt.h };
      for (const f of L.fixes) {
        const t = chevauche(f.r, b);
        if (t !== f.el.classList.contains('evite')) f.el.classList.toggle('evite', t);
      }
    }
    if (ytFond && yt) majAfficheYouTube(now);

    // la nuée
    const valeursDirect = direct && G().analyseDirecte ? sur('direct.valeurs', () => direct.valeurs(horloge.t), null) : null;
    const rect = rectNuee(y);
    if (ok && !document.hidden && params) {
      const anime = G().animation !== false && !REDUIT;
      let tMedia, murF, dtF, rendre;
      if (gel) { tMedia = gel.t; murF = gel.mur; dtF = 0; rendre = true; }
      else if (anime) { mur += dt; tMedia = horloge.t; murF = mur; dtF = dt; tFige = null; rendre = true; }
      else {
        if (tFige === null) tFige = horloge.t;
        tMedia = tFige; murF = mur; dtF = 0;
        rendre = sale || y !== yRendu || Math.abs(m - mRendu) > 1e-3;     // image fixe, recalée au scroll
      }
      if (opacite <= 0.0005 && !sale) rendre = false;                    // invisible : rien à dessiner
      if (rendre) {
        try {
          nuee.layout({ largeur: L.cw, hauteur: L.ch, dpr: L.dpr, rectImage: rect });
          nuee.frame({
            mur: murF, dt: dtF,
            media: { t: tMedia, enLecture: horloge.enLecture },
            piste: pisteActive(),
            direct: valeursDirect,
            scroll: { p, v: vLisse, frontiere: fr },
            params,
            figure: figureForcee(),
            reduit: REDUIT,
          });
        } catch (e) {
          nueeEnPanne = true;
          avertir('nuee : erreur de rendu, image seule', e);
        }
        sale = false; yRendu = y; mRendu = m;
      }
    }

    dern.p = p; dern.v = vLisse; dern.frontiere = fr; dern.poids = poids; dern.params = params;
    dern.opacite = opacite; dern.rect = rect; dern.direct = valeursDirect;
    if (panneau) panneau.etat({ poids, ecoute, libelle: libelleEcoute(), son: modeSon, ecouteDisponible: fond !== 'youtube' || !!ytLecteur });
    if (hud && !hud.hidden && now - tHud > 250) { tHud = now; majHud(); }
  }

  /* ---------- HUD ---------- */
  const f = (x, d) => (typeof x === 'number' && isFinite(x) ? x.toFixed(d === undefined ? 2 : d) : '—');
  function majHud() {
    const st = nuee ? sur('nuee.stats', () => nuee.stats(), null) : null;
    const t = gel ? gel.t : horloge.t;
    const pa = pisteActive();
    const pr = pa && typeof pa.at === 'function' ? sur('piste.at', () => pa.at(t), null) : null;
    const dv = dern.direct;
    const etatLecteur = lecteur ? lire(lecteur, 'etat') : null;
    const fig = st && st.figures ? ['traine', 'masse', 'voile', 'fil'].map((k) => k + ' ' + f(st.figures[k], 2)).join('  ') : '—';
    const w = dern.poids;
    hud.textContent = [
      'ips ' + f(st && typeof st.ips === 'number' ? st.ips : ips, 0) + '  (boucle ' + f(ips, 0) + ')   points ' +
        (st && isFinite(st.points) ? Math.round(st.points).toLocaleString('fr-FR') : '—') + '   ' + f(st && st.ms, 2) + ' ms',
      'horloge ' + horloge.source + ' ' + f(t, 2) + ' s' + (gel ? ' (figée)' : '') + (horloge.enLecture ? '  lecture' : '  arrêt') +
        '   écart ' + (horloge.ecartMs === null ? '—' : f(horloge.ecartMs, 1) + ' ms'),
      'figures ' + fig,
      'énergie ' + f(pr && pr.energie) + '/' + f(dv && dv.energie) + '  attaque ' + f(pr && pr.attaque) + '/' + f(dv && dv.attaque) +
        (direct ? '  direct : ' + (lire(direct, 'mode') || '?') + (typeof direct.retard === 'number' ? ', ' + f(direct.retard * 1000, 0) + ' ms' : '') : '  (piste/direct)'),
      'p ' + f(dern.p, 3) + '   v ' + f(dern.v, 0) + ' px/s   frontière ' + f(dern.frontiere, 2),
      'contextes vidéo ' + f(w.video) + '  ambiance ' + f(w.ambiance) + '  scroll ' + f(w.scroll),
      'opacité ' + Math.round(dern.opacite * 100) + ' %  fond ' + fond + (YT_PLEIN && fond === 'youtube' ? ' plein cadre' + (ytVisible ? ' (joue)' : ' (affiche)') : '') +
        (TRAITEMENT ? '  filtre ' + G().fondFiltre + ', voile ' + Math.round(G().fondAssombrir) + ' %' : '') + (nueeOk() ? '' : '  nuée inactive') +
        (fond === 'youtube' && !piste ? '  conduite ' + (conduiteActive ? 'active' : 'vide') : '  piste ' + (piste ? 'chargée' : '—')) +
        (figureForcee() ? '  figure ' + figureForcee() : ''),
      typeof etatLecteur === 'string' && etatLecteur ? etatLecteur.slice(0, 64) : '',
    ].join('\n');
  }

  /* ---------- API de test ---------- */
  function etat() {
    const st = nuee ? sur('nuee.stats', () => nuee.stats(), null) : null;
    const g = G();
    return {
      fond, variante: VAR.nom || null,
      traitement: TRAITEMENT ? { filtre: g.fondFiltre, teinte: g.fondTeinte, assombrir: g.fondAssombrir, saturation: g.fondSaturation,
        contraste: g.fondContraste, inversion: g.fondInversion, flou: g.fondFlou, teinteForce: g.fondTeinteForce,
        css: cadre ? cadre.style.getPropertyValue('--fond-filtre') : null } : null,
      ytPlein: fond === 'youtube' && YT_PLEIN ? { affiche: !ytVisible, rect: ytFond ? ytFond.getBoundingClientRect().toJSON() : null } : null,
      horloge: { t: gel ? gel.t : horloge.t, source: horloge.source, enLecture: horloge.enLecture, ecartMs: horloge.ecartMs, gelee: !!gel },
      mur: gel ? gel.mur : mur,
      p: dern.p, v: dern.v, frontiere: dern.frontiere, scrollY: window.scrollY,
      poids: Object.assign({}, dern.poids), contexte: contexteForce || 'auto',
      params: dern.params ? Object.assign({}, dern.params) : null,
      figure: figureForcee(),
      opacite: dern.opacite,
      rectImage: dern.rect ? Object.assign({}, dern.rect) : null,
      nuee: st, nueeOk: nueeOk(),
      piste: !!piste, conduite: fond === 'youtube' ? { presente: !!conduite, active: conduiteActive } : null,
      ecoute, son: modeSon, animation: G().animation !== false, reduit: REDUIT,
      youtube: fond === 'youtube' ? ytEtat : null,
      lenis: !!lenis, tactile: TACTILE,
    };
  }
  window.__h4 = {
    geler(t) {
      const x = Number(t);
      const v = isFinite(x) ? x : horloge.t;
      gel = { t: v, mur: v };
      sale = true;
      return v;
    },
    degeler() { gel = null; sale = true; dernier = performance.now(); },
    scroll(p) {
      const q = clamp01(Number(p) || 0);
      const s = q >= 1 ? 1 : 0.5 - Math.sin(Math.asin(1 - 2 * q) / 3);   // inverse de lisse()
      const y = Math.round(L.heroTop + s * L.heroH);
      if (lenis) sur('lenis.scrollTo', () => lenis.scrollTo(y, { immediate: true, force: true }));
      window.scrollTo(0, y);
      return y;
    },
    figure(nom) {
      const v = nom === null || nom === undefined || nom === '' ? 'auto' : String(nom);
      if (FIGURES.indexOf(v) < 0) return false;
      definirGlobal('figure', v, { sansSauver: true });
      return true;
    },
    // impose le contexte de l'ouverture ('video' = réglages « musique en cours », 'ambiance'),
    // 'auto' ou null : suit l'état réel de la lecture
    contexte(nom) {
      contexteForce = nom === 'video' || nom === 'ambiance' ? nom : null;
      sale = true;
      return contexteForce || 'auto';
    },
    etat,
    relayout,
    get nuee() { return nuee; },
    get lecteur() { return lecteur; },
    get piste() { return piste; },
    get conduite() { return conduite; },
    get youtube() { return yt; },
    get reglages() { return panneau; },
    get lenis() { return lenis; },
  };

  /* ---------- amorçage ---------- */
  if (!TACTILE && !SANS_LENIS && !REDUIT && typeof window.Lenis === 'function') {
    lenis = sur('Lenis : scroll natif', () => new window.Lenis({
      duration: 1.2,
      smoothWheel: true,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    }), null);
  }
  if (url.has('t')) {
    const x = parseFloat(url.get('t'));
    if (isFinite(x)) gel = { t: x, mur: x };
  }
  if (fond === 'youtube') construireFacade();
  appliquerMasque();
  appliquerReference();
  appliquerHud();
  appliquerAnimation();
  appliquerSuperposition();
  appliquerTraitement();
  majEcoute();
  if (!panneau && (P || RG)) avertir('panneau de réglages indisponible : valeurs par défaut');
  if (!nuee) avertir('nuée indisponible : image seule');
  else if (!nueeOk()) avertir('nuée inactive (WebGL2 refusé) : image seule');

  addEventListener('resize', planifier);
  addEventListener('load', planifier);
  addEventListener('pageshow', planifier);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(planifier, () => {});
  if (window.ResizeObserver) new ResizeObserver(planifier).observe(document.body);
  if (imgFond) imgFond.addEventListener('load', planifier);
  if (video) video.addEventListener('loadedmetadata', planifier);
  relayout();

  const aGsap = !!(window.gsap && window.gsap.ticker);
  const tickSur = () => { try { tick(); } catch (e) { avertir('boucle', e); } };
  if (aGsap) {
    window.gsap.ticker.lagSmoothing(0);
    window.gsap.ticker.add(tickSur);
  } else {
    avertir('GSAP absent : boucle requestAnimationFrame');
    const boucle = () => { tickSur(); requestAnimationFrame(boucle); };
    requestAnimationFrame(boucle);
  }

  if (url.get('panneau') === '1' && panneau) panneau.ouvrir();

  // Les textes de l'ouverture entrent quand les polices sont là (au plus tard après 1,2 s)
  const polices = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
  Promise.race([polices, new Promise((r) => setTimeout(r, 1200))]).then(() => {
    racineDoc.classList.add('h4-pret');
    racineDoc.classList.remove('js-timeout');
    planifier();
  });

  window.__h4Booted = true;
})();
