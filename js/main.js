/*
 * main.js — amorçage de la nuée et du lecteur de musique → window.__h4
 *
 * Un seul mode de vol (12/11/2026). Une seule boucle : le ticker GSAP (lagSmoothing(0)) avec
 * Lenis dedans, ou requestAnimationFrame si GSAP manque. À chaque image :
 *   horloge du titre en cours (Sync) → sources de la nuée (titre en cours, titre sortant,
 *   programme autonome) et leurs poids → réglages du panneau → nuee.layout + nuee.frame.
 *
 * La nuée est un calque fixe : elle ne défile pas avec la page. Sa place dans la profondeur
 * (devant ou derrière une section) est une affaire de CSS (css/site.css). Le défilement ne
 * donne plus qu'une vitesse lissée (cisaillement léger), l'apparition des blocs [data-revele]
 * (achevée par le temps une fois le bloc dans la fenêtre) et la barre du haut (--barre : 0 en
 * haut de page, 1 dès qu'on a défilé).
 *
 * Musique : la liste est window.PLAYLIST (data/playlist.js). Un seul élément <audio>, dont on
 * change la source (un élément déjà lancé par un geste peut enchaîner les titres sans nouveau
 * geste, y compris dans Safari). Lecture automatique à l'ouverture ; si le navigateur la refuse,
 * elle part au premier clic ou à la première touche. Quand la musique s'arrête, la nuée glisse
 * en FONDU secondes vers le programme autonome du moteur (déterministe, sans hasard à
 * l'exécution) ; quand on change de titre, l'ancien se fond dans le nouveau en FONDU_TITRE.
 *
 * Vidéo avec son (visuel 3, data/visuels.js, son: true) : elle part quand la section occupe la
 * moitié de la fenêtre et prend le relais de la musique en FONDU_VIDEO (jamais les deux sons
 * ensemble) ; la nuée suit alors sa bande-son. Quand on quitte la section, la vidéo s'arrête et la
 * musique reprend où elle en était. Le bouton Play / Pause reste le maître du son : musique en
 * pause, la vidéo joue muette. Si le navigateur refuse le son de la vidéo, elle joue muette et
 * la musique continue.
 *
 * Visuels « lumière » (les découpes, js/lumiere.js) : une scène par section [data-lumiere],
 * mesurée et rendue ici.
 *
 * Aimantation des sections (réglage « aimant », à travailler) : dès que 10 % d'une section sont
 * à l'écran dans le sens du défilement, la page défile seule jusqu'à elle. Avec Lenis, au moment
 * où le seuil est franchi (défilement verrouillé le temps du trajet) ; en défilement natif
 * (tactile), à l'arrêt du geste. Suspendue pendant les trajets programmés (menu, logo, retour en
 * haut : événement pb:trajet de contenu.js), après Tab, en mouvement réduit.
 *
 * API de test (window.__h4) : geler(t), degeler(), figure(nom), titre(i), ecouter(), etat(),
 * relayout() ; paramètres d'adresse : figure, hud, t, nolenis, reduit, panneau, muet, aimant.
 *
 * Chaque module est optionnel : sans Nuee la page reste lisible, sans Sync ou sans Musique la
 * nuée vole en mode autonome, sans Reglages les valeurs par défaut s'appliquent. Le drapeau
 * d'amorçage n'est posé qu'à la toute fin (filet js-timeout de index.html).
 */
(function () {
  'use strict';

  const racineDoc = document.documentElement;
  const P = window.H4Parametres || null;
  const RG = window.Reglages || null;
  const S = window.Sync || null;
  const M = window.Musique || null;
  const N = window.Nuee || null;
  const C = window.SiteContenu || null;

  const url = new URLSearchParams(location.search);
  const REDUIT = url.get('reduit') === '1' || matchMedia('(prefers-reduced-motion: reduce)').matches;
  const TACTILE = matchMedia('(hover: none), (pointer: coarse)').matches;
  const SANS_LENIS = url.get('nolenis') === '1';
  racineDoc.classList.add(TACTILE ? 'is-touch' : 'is-pointer');
  if (REDUIT) racineDoc.classList.add('reduit');

  const IMAGE = { l: 1920, h: 1080 };       // repère de la nuée (les figures sont dessinées en 16:9)
  const CLE_REGLAGES = 'pb-nuee-reglages-v1';
  const CLE_MUSIQUE = 'pb-musique';         // sessionStorage : '0' si le visiteur a mis la musique en pause
  const FONDU = 2.5;                        // s : musique ↔ programme autonome
  const FONDU_TITRE = 2;                    // s : ancien titre → nouveau
  const FONDU_VIDEO = 1.5;                  // s : musique ↔ son de la vidéo (visuel 3)
  const SEUIL_AIMANT = 0.10;                // part d'une section à l'écran qui déclenche l'aimantation
  const FIGURES = ['auto', 'traine', 'masse', 'voile', 'fil'];
  const PLAYLIST = Array.isArray(window.PLAYLIST) ? window.PLAYLIST : [];

  /* ---------- outils ---------- */
  const avertis = new Set();
  function avertir(ctx, err) {
    if (avertis.has(ctx)) return;
    avertis.add(ctx);
    console.warn('[nuée] ' + ctx, err === undefined ? '' : err);
  }
  function sur(ctx, fn, defaut) {
    try { return fn(); } catch (e) { avertir(ctx, e); return defaut; }
  }
  const clamp01 = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);
  const lisse = (x) => { x = clamp01(x); return x * x * (3 - 2 * x); };
  // Anacrouse puis résolution longue (grammaire de mouvement du site)
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
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const memoire = {
    lire(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } },
    ecrire(k, v) { try { if (v === null) window.sessionStorage.removeItem(k); else window.sessionStorage.setItem(k, v); } catch (e) { /* stockage indisponible */ } },
  };
  const texte = (champ) => (C ? C.choisir(champ) : (typeof champ === 'string' ? champ : (champ && champ.fr) || ''));
  const enHtml = (s) => (C ? C.html(s) : String(s));
  const brut = (s) => (C ? C.brut(s) : String(s));
  const t = (cle, repli) => (C && C.t(cle)) || repli;

  /* ---------- éléments ---------- */
  const canvas = $('[data-nuee]');
  const hero = $('[data-ouverture]');
  const hint = $('[data-hint]');
  const hud = $('[data-hud]');
  const audio = $('[data-audio]');
  const boutonsEcouter = $$('[data-ecouter]');
  const listesTitres = $$('[data-titres]');
  const titresCourants = $$('[data-titre-courant]');
  const boutonPlaylist = $('[data-playlist]');
  const voletPlaylist = $('[data-playlist-volet]');
  const boutonsReglages = $$('[data-ouvrir-reglages]');
  const boutonsPause = $$('[data-pause-animation]');
  const videoSon = $('video[data-video-son]');

  /* ---------- réglages (panneau, ou valeurs par défaut s'il manque) ---------- */
  const forces = {};
  if (FIGURES.indexOf(url.get('figure')) >= 0) forces.figure = url.get('figure');
  if (url.get('hud') === '1') forces.hud = true;
  if (url.get('aimant') === '0') forces.aimant = false;
  const secours = {
    valeurs: P ? P.defauts() : null,
    globaux: Object.assign({ figure: 'auto', hud: false, animation: true, aimant: true }, P ? P.GLOBAUX : {}, forces),
  };
  let panneau = null;
  const G = () => (panneau ? panneau.globaux : secours.globaux);
  const V = () => (panneau ? panneau.valeurs : secours.valeurs);
  function definirGlobal(id, v, o) {
    if (panneau) return panneau.definirGlobal(id, v, o);
    const ancien = secours.globaux[id];
    secours.globaux[id] = v;
    if (ancien !== v) surGlobal(id, v);
    return true;
  }

  /* ---------- état ---------- */
  let lenis = null, nuee = null, nueeEnPanne = false;
  let gel = null;                  // { t, mur, auto } : horloges figées (tests, ?t=)
  let mur = 0, dernier = performance.now(), sale = true;
  let vLisse = 0, yPrec = null, dprVu = window.devicePixelRatio || 1;
  let nImages = 0, tIps = performance.now(), ips = 0, tHud = 0, opaciteVue = -1, canvasVisible = null, sansNueeVu = null, hintVu = '', barreVue = -1;
  const L = { vw: innerWidth, vh: innerHeight, cw: innerWidth, ch: innerHeight, dpr: 1, rect: { x: 0, y: 0, w: innerWidth, h: innerHeight } };
  const dern = { v: 0, params: null, opacite: 0, sources: [], barre: 0 };

  if (RG && P) {
    panneau = sur('Reglages.creer', () => RG.creer({
      parametres: P, cle: CLE_REGLAGES, forces,
      sousTitre: 'Nuée · un seul mode de vol · touche P',
      surGlobal: (id, v) => surGlobal(id, v),
      surValeur: () => { sale = true; },
      surAction: (nom) => { if (nom === 'ecouter') basculerEcoute(); },
      surOuverture: (ouvert) => surOuverture(ouvert),
    }), null);
  }

  /* ---------- la nuée ---------- */
  const appareil = (P && P.APPAREILS && P.APPAREILS[TACTILE ? 'tactile' : 'ordinateur']) || { maxPoints: 60000, dprMax: 1.5 };
  if (N && P && canvas && typeof N.create === 'function') {
    nuee = sur('Nuee.create', () => N.create(canvas, { maxPoints: appareil.maxPoints, dprMax: appareil.dprMax, tactile: TACTILE }), null) || null;
  }
  const nueeOk = () => !!nuee && !nueeEnPanne && nuee.ok !== false;

  /* ---------- musique : titres, lecteur, pistes pré-analysées ---------- */
  const titres = PLAYLIST.map((d, i) => ({ d, i, piste: null, charge: false }));
  let courant = 0;                 // indice du titre en cours
  let lecteur = null;              // horloge du titre en cours (Sync.lecteur)
  let ecoute = false;              // le visiteur veut la musique (ou la lecture automatique est en cours)
  let attenteGeste = false;        // lecture automatique refusée : elle partira au premier geste
  let m = 0;                       // 0 : programme autonome, 1 : musique (glisse en FONDU)
  let sortant = null;              // { piste, t, x } : titre qu'on quitte, x de 1 à 0 en FONDU_TITRE
  let attenteLecture = 0;
  const horloge = { t: 0, source: 'virtuelle', enLecture: false };

  function chargerPiste(T) {
    if (!T || T.charge || !T.d.piste || !M || typeof M.chargerPiste !== 'function') return;
    T.charge = true;
    sur('Musique.chargerPiste', () => {
      Promise.resolve(M.chargerPiste(T.d.piste)).then(
        (p) => { T.piste = p || null; if (p && !p.titre) p.titre = brut(texte(T.d.titre)); sale = true; },
        (e) => avertir('piste pré-analysée indisponible (' + T.d.id + ')', e)
      );
    });
  }
  // Horloge de secours (Sync absent) : le temps du média quand il joue, figé sinon.
  function lecteurSecours(media) {
    const joue = () => !!media && !media.paused && !media.ended && media.readyState >= 2;
    return {
      get source() { return joue() ? 'media' : 'virtuelle'; },
      get enLecture() { return joue(); },
      temps() { return media ? media.currentTime || 0 : 0; },
      ecouter() {
        if (!media) return Promise.resolve('virtuelle');
        media.muted = false;
        let p;
        try { p = media.play(); } catch (e) { p = Promise.reject(e); }
        return Promise.resolve(p).then(() => 'media', () => 'virtuelle');
      },
      pause() { if (media) media.pause(); },
      aller(x) { if (media) { try { media.currentTime = x; } catch (e) { /* pas encore de métadonnées */ } } },
      detruire() {},
    };
  }
  // Un lecteur neuf par titre, arrêté au début : rien n'est téléchargé avant la lecture.
  function installerTitre(i) {
    const T = titres[i];
    if (!T || !audio) return;
    if (lecteur && typeof lecteur.detruire === 'function') sur('lecteur.detruire', () => lecteur.detruire());
    const src = T.d.src;
    if (audio.getAttribute('src') !== src) { audio.pause(); audio.setAttribute('src', src); sur('audio.load', () => audio.load()); }
    audio.loop = false;
    let l = null;
    if (S && typeof S.lecteur === 'function') l = sur('Sync.lecteur', () => S.lecteur({ media: audio, boucle: null, debut: 0 }), null);
    if (!l || typeof l.temps !== 'function') { if (S) avertir('Sync.lecteur indisponible : horloge de secours'); l = lecteurSecours(audio); }
    lecteur = l;
    sur('lecteur.pause', () => l.pause());         // l'horloge attend au début du titre
    sur('lecteur.aller', () => l.aller(0));
    courant = i;
    chargerPiste(T);
    majLecteur();
  }

  // Lancer la lecture du titre en cours. À appeler dans un geste, sauf pour la tentative de
  // lecture automatique (auto), qui arme le premier geste si le navigateur la refuse.
  function lire(auto) {
    const l = lecteur;
    if (!l) return;
    ecoute = true;
    attenteLecture = 0;
    majLecteur();
    // sur la vidéo du visuel 3 : c'est elle qui prend le son, la musique attend qu'on la quitte
    if (scene && scene.actif) { sonVideo(true); return; }
    Promise.resolve(sur('lecteur.ecouter', () => l.ecouter(), 'virtuelle')).then((r) => {
      if (lecteur !== l || !ecoute || r !== 'virtuelle') return;
      // lecture refusée : l'horloge revient au début du titre et attend
      ecoute = false;
      sur('lecteur.pause', () => l.pause());
      if (auto) armerGeste();
      majLecteur();
    }, () => {});
  }
  function arreter() {
    ecoute = false;
    if (lecteur) sur('lecteur.pause', () => lecteur.pause());
    if (scene && !REDUIT) sonVideo(false);           // la vidéo continue, muette
    majLecteur();
  }
  // Bouton Play / Pause : un geste du visiteur. Son choix est mémorisé pour la session.
  function basculerEcoute() {
    desarmerGeste();
    if (ecoute) { arreter(); memoire.ecrire(CLE_MUSIQUE, '0'); }
    else { lire(false); memoire.ecrire(CLE_MUSIQUE, null); }
  }
  // Changer de titre : l'ancien continue de conduire la nuée le temps du fondu (son horloge est
  // prolongée image par image), le nouveau part du début.
  function changerTitre(i, o) {
    o = o || {};
    if (!titres[i]) return;
    desarmerGeste();
    if (i === courant) { if (!ecoute) { lire(false); memoire.ecrire(CLE_MUSIQUE, null); } return; }
    const A = titres[courant];
    if (A && A.piste && m > 0.01) sortant = { piste: A.piste, t: horloge.t, x: 1 };
    const jouer = o.jouer !== false;
    installerTitre(i);
    if (jouer) { lire(false); if (!o.auto) memoire.ecrire(CLE_MUSIQUE, null); }
    fermerPlaylist();
  }
  if (audio) {
    audio.addEventListener('ended', () => {        // fin d'un titre : le suivant, en boucle sur la liste
      if (!ecoute || !titres.length) return;
      changerTitre((courant + 1) % titres.length, { auto: true });
    });
  }

  // Premier geste : la lecture automatique refusée part au premier clic ou à la première touche
  // (le défilement n'est pas un geste pour le navigateur). Les commandes du lecteur et le panneau
  // s'occupent d'elles-mêmes.
  const TOUCHES_MUETTES = ['Tab', 'Shift', 'Control', 'Alt', 'Meta', 'Escape', 'CapsLock'];
  function surGeste(e) {
    if (!attenteGeste) return;
    if (e.type === 'keydown' && (TOUCHES_MUETTES.indexOf(e.key) >= 0 || estSaisie(e.target))) return;
    const c = e.target && e.target.closest ? e.target : null;
    if (c && (c.closest('[data-lecteur]') || c.closest('.reglages'))) return;
    desarmerGeste();
    lire(true);
  }
  function armerGeste() {
    if (attenteGeste) return;
    attenteGeste = true;
    for (const ev of ['click', 'keydown', 'touchend']) document.addEventListener(ev, surGeste, true);
  }
  function desarmerGeste() {
    if (!attenteGeste) return;
    attenteGeste = false;
    for (const ev of ['click', 'keydown', 'touchend']) document.removeEventListener(ev, surGeste, true);
  }

  /* ---------- lecteur : boutons, liste des titres (ouverture et barre du haut) ---------- */
  function construireTitres() {
    for (const ul of listesTitres) {
      ul.textContent = '';
      for (const T of titres) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'titre';
        b.setAttribute('data-titre', String(T.i));
        const mention = texte(T.d.mention);
        b.innerHTML = '<span class="titre__nom">' + enHtml(texte(T.d.titre)) + '</span>' +
          (mention ? '<span class="titre__mention">' + enHtml(mention) + '</span>' : '');
        b.addEventListener('click', () => changerTitre(T.i));
        const li = document.createElement('li');
        li.appendChild(b);
        ul.appendChild(li);
      }
    }
  }
  function majLecteur() {
    const lib = ecoute ? t('lecteur.pause', 'Pause') : t('lecteur.play', 'Play');
    for (const b of boutonsEcouter) {
      b.setAttribute('data-etat', ecoute ? 'lecture' : 'arret');
      b.setAttribute('aria-label', lib);
      b.setAttribute('aria-pressed', ecoute ? 'true' : 'false');
      const s = b.querySelector('[data-ecouter-libelle]');
      if (s && s.textContent !== lib) s.textContent = lib;
    }
    const T = titres[courant];
    for (const ul of listesTitres) {
      for (const b of ul.querySelectorAll('[data-titre]')) {
        const on = Number(b.getAttribute('data-titre')) === courant;
        b.classList.toggle('est-courant', on);
        if (on) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
      }
    }
    if (T) for (const n of titresCourants) { const h = enHtml(texte(T.d.titre)); if (n.innerHTML !== h) n.innerHTML = h; }
    racineDoc.classList.toggle('musique-en-cours', ecoute);
  }
  function ouvrirPlaylist(ouvrir) {
    if (!boutonPlaylist || !voletPlaylist) return;
    voletPlaylist.hidden = !ouvrir;
    boutonPlaylist.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
  }
  function fermerPlaylist() { ouvrirPlaylist(false); }
  for (const b of boutonsEcouter) b.addEventListener('click', basculerEcoute);
  if (boutonPlaylist && voletPlaylist) {
    boutonPlaylist.addEventListener('click', () => ouvrirPlaylist(voletPlaylist.hidden));
    document.addEventListener('click', (e) => {
      if (voletPlaylist.hidden) return;
      const c = e.target && e.target.closest ? e.target : null;
      if (!c || (!c.closest('[data-playlist-volet]') && !c.closest('[data-playlist]'))) fermerPlaylist();
    });
  }

  /* ---------- vidéo avec son (visuel 3) : relais de la musique ---------- */
  // x : part de la vidéo dans le son et dans la conduite de la nuée (0 → 1 en FONDU_VIDEO, quand
  // elle joue vraiment) ; son : elle porte le son ; relais : la musique s'est tue pour elle et
  // reprendra quand on quittera la section.
  const scene = videoSon ? { v: videoSon, el: videoSon.closest('[data-section]') || videoSon.parentNode, piste: null, haut: 0, h: 0, actif: false, x: 0, son: false, relais: false, vue: 0 } : null;
  if (scene) {
    const adresse = videoSon.getAttribute('data-piste');
    if (adresse && M && typeof M.chargerPiste === 'function') {
      sur('Musique.chargerPiste (vidéo)', () => {
        Promise.resolve(M.chargerPiste(adresse)).then((p) => { scene.piste = p || null; }, (e) => avertir('piste de la vidéo indisponible', e));
      });
    }
    videoSon.loop = true;
    if (REDUIT) {
      // mouvement réduit : pas de lecture automatique ; le visiteur lance la vidéo lui-même, avec
      // les commandes du navigateur, et la musique se tait à ce moment-là
      videoSon.controls = true;
      videoSon.muted = false;
      videoSon.classList.add('est-commandee');
      videoSon.addEventListener('play', () => {
        if (!ecoute) return;
        ecoute = false;
        if (lecteur) sur('lecteur.pause', () => lecteur.pause());
        majLecteur();
      });
    }
  }
  function jouerVideo() {
    let p;
    try { p = scene.v.play(); } catch (e) { p = Promise.reject(e); }
    return Promise.resolve(p);
  }
  // Donner ou retirer le son à la vidéo. Refusé hors d'un geste : elle joue muette et la musique
  // continue (ou reprend).
  function sonVideo(oui) {
    const S = scene, v = S.v;
    if (!oui) { v.muted = true; S.son = false; S.relais = false; return; }
    v.muted = false;
    v.volume = Math.max(0.001, lisse(S.x));
    S.son = true;
    jouerVideo().then(() => {}, () => {
      if (!S.son) return;
      v.muted = true; S.son = false;
      jouerVideo().then(() => {}, () => {});
      if (S.relais) { S.relais = false; reprendreMusique(); }
    });
  }
  function reprendreMusique() {
    if (!ecoute || !lecteur) return;
    if (audio) audio.volume = Math.max(0.001, 1 - lisse(scene ? scene.x : 0));
    const l = lecteur;
    Promise.resolve(sur('lecteur.ecouter', () => l.ecouter(), 'virtuelle')).then((r) => {
      if (lecteur !== l || !ecoute || r !== 'virtuelle') return;
      ecoute = false;                                // reprise refusée : le bouton revient à Play
      sur('lecteur.pause', () => l.pause());
      majLecteur();
    }, () => {});
  }
  function entrerScene() {
    const S = scene;
    if (ecoute) sonVideo(true);
    else { S.v.muted = true; S.son = false; jouerVideo().then(() => {}, () => {}); }
  }
  function quitterScene() {
    const S = scene;
    if (S.relais) { S.relais = false; reprendreMusique(); }
  }
  function majScene(dt, y) {
    const S = scene, v = S.v;
    S.vue = clamp01((Math.min(y + L.vh, S.haut + S.h) - Math.max(y, S.haut)) / Math.max(1, Math.min(L.vh, S.h)));
    const veut = S.vue >= 0.5 && G().animation !== false && !REDUIT && !gel;
    if (veut !== S.actif) { S.actif = veut; if (veut) entrerScene(); else quitterScene(); }
    const joue = !v.paused && !v.ended && v.readyState >= 2;
    const cible = S.actif && joue ? 1 : 0;
    if (S.x !== cible) S.x = cible > S.x ? Math.min(1, S.x + dt / FONDU_VIDEO) : Math.max(0, S.x - dt / FONDU_VIDEO);
    const k = lisse(S.x);
    if (S.son) {
      // fondu croisé : la musique descend pendant que la vidéo monte, puis se met en pause
      const vol = Math.max(0, Math.min(1, k));
      if (Math.abs(v.volume - vol) > 0.004) v.volume = vol;
      if (audio) { const va = Math.max(0, Math.min(1, 1 - k)); if (Math.abs(audio.volume - va) > 0.004) audio.volume = va; }
      if (S.actif && S.x >= 1 && !S.relais && ecoute) {
        S.relais = true;
        if (lecteur) sur('lecteur.pause', () => lecteur.pause());
      }
    } else if (audio && audio.volume !== 1 && !S.relais) audio.volume = 1;
    if (!S.actif && S.x <= 0) {
      if (!v.paused) v.pause();                      // sortie terminée : la vidéo s'arrête
      if (S.son) { S.son = false; v.muted = true; }
      if (audio && audio.volume !== 1) audio.volume = 1;
    }
  }

  /* ---------- visuels « lumière » (les découpes) : une scène par section [data-lumiere] ---------- */
  const LM = window.Lumiere || null;
  const lumieres = [];
  for (const sec of $$('[data-lumiere]')) {
    const foc = (sec.getAttribute('data-focal') || '').split(',').map(Number);
    const sc = LM ? sur('Lumiere.creer', () => LM.creer(sec, {
      preset: sec.getAttribute('data-lumiere'), reduit: REDUIT,
      focus: foc.length === 2 && foc.every((x) => isFinite(x)) ? foc : null,
    }), null) : null;
    if (!sc) { sec.classList.add('lumiere-echec'); continue; }          // l'image reste seule
    lumieres.push(sc);
    sc.init().then(() => planifier(), (e) => avertir('visuel « lumière » indisponible, image seule (' + (sec.id || '?') + ')', e));
  }
  if (!LM && $('[data-lumiere]')) avertir('lumiere.js absent : visuels en image seule');

  /* ---------- aimantation des sections ---------- */
  // Une zone par section de la page, pied compris. auto : défilement automatique en cours ;
  // trajet : défilement programmé par contenu.js (menu, logo, retour en haut) ; silence : fin d'un
  // trajet automatique, la queue d'inertie du pavé tactile est avalée jusqu'au geste suivant.
  const zones = $$('main > section, footer.pied').map((el) => ({ el, haut: 0, h: 0 }));
  const aimant = { auto: null, trajet: null, dir: 0, y: null, aVoir: false, repos: 0, pause: 0, roue: -1e9, silence: 0, doigt: false, ascenseur: false, n: 0 };
  const aimantActif = () => G().aimant !== false && !REDUIT && !gel && zones.length > 1;
  // Section à rejoindre depuis y dans le sens dir, ou null. Vers le bas : la première section dont
  // le haut est entré dans la fenêtre d'au moins SEUIL_AIMANT (de sa hauteur, plafonnée à l'écran),
  // on s'aligne sur son haut. Vers le haut : la dernière section dont le bas est entré d'autant,
  // on s'aligne sur son haut si elle tient dans l'écran, sinon sur son bas (on y arrive par la fin).
  function cibleAimant(y, dir) {
    const vh = L.vh, max = Math.max(0, racineDoc.scrollHeight - vh);
    if (dir > 0) {
      for (const z of zones) {
        if (z.haut <= y + 1) continue;
        if (z.haut >= y + vh) break;
        const c = Math.min(z.haut, max);
        return (y + vh - z.haut >= SEUIL_AIMANT * Math.min(z.h, vh) && c > y + 1) ? c : null;
      }
    } else if (dir < 0) {
      for (let i = zones.length - 1; i >= 0; i--) {
        const z = zones[i], bas = z.haut + z.h;
        if (bas >= y + vh - 1) continue;
        if (bas <= y) break;
        const c = Math.max(0, z.h <= vh + 1 ? z.haut : bas - vh);
        return (bas - y >= SEUIL_AIMANT * Math.min(z.h, vh) && c < y - 1) ? c : null;
      }
    }
    return null;
  }
  function finAimant(now) {
    aimant.auto = null;
    aimant.dir = 0;
    aimant.aVoir = false;
    aimant.silence = now - aimant.roue < 140 ? now + 1000 : 0;
  }
  function lancerAimant(cible, y, now) {
    const duree = Math.min(1.6, 0.85 + Math.abs(cible - y) / 1500);
    aimant.auto = { cible, fin: now + duree * 1000 + 500 };
    aimant.n++;
    if (lenis) {
      // départ franc (la page est déjà en mouvement), résolution longue ; verrouillé jusqu'à l'arrivée
      sur('lenis.scrollTo', () => lenis.scrollTo(cible, {
        duration: duree, easing: (x) => 1 - Math.pow(1 - x, 2.4), lock: true, force: true,
        onComplete: () => finAimant(performance.now()),
      }));
    } else {
      window.scrollTo({ top: cible, behavior: 'smooth' });
    }
  }
  function majAimant(y, now) {
    const A = aimant;
    const dy = A.y === null ? 0 : y - A.y;
    A.y = y;
    if (A.trajet) {                                  // trajet programmé : on attend qu'il soit arrivé
      if (Math.abs(y - A.trajet.cible) < 2 || now > A.trajet.fin) { A.trajet = null; A.dir = 0; A.aVoir = false; }
      return;
    }
    if (A.auto) {
      if (now > A.auto.fin || (!lenis && Math.abs(y - A.auto.cible) < 1.5)) finAimant(now);
      return;
    }
    if (!aimantActif() || now < A.pause || A.ascenseur) { A.dir = 0; A.aVoir = false; return; }
    if (dy !== 0) { A.dir = dy > 0 ? 1 : -1; A.aVoir = true; A.repos = now; }
    if (!A.aVoir) return;
    let c = null;
    if (lenis) {                                     // défilement lissé : dès que le seuil est franchi
      if (dy !== 0) c = cibleAimant(y, A.dir);
    } else if (dy === 0 && !A.doigt && now - A.repos > 140) {
      A.aVoir = false;                               // défilement natif : à l'arrêt du geste
      c = cibleAimant(y, A.dir);
    }
    if (c !== null) lancerAimant(c, y, now);
  }
  if (zones.length > 1) {
    addEventListener('wheel', (e) => {
      const now = performance.now();
      const calme = now - aimant.roue > 140;         // la molette (ou l'inertie du pavé tactile) s'était tue
      aimant.roue = now;
      if (aimant.auto && !lenis) { e.preventDefault(); return; }      // trajet natif : la molette ne l'interrompt pas
      if (!aimant.silence) return;
      if (calme || now > aimant.silence) aimant.silence = 0;          // nouveau geste : il passe
      else { e.preventDefault(); e.stopPropagation(); }               // queue d'inertie du geste précédent
    }, { passive: false, capture: true });
    addEventListener('touchstart', () => { aimant.doigt = true; aimant.auto = null; }, { passive: true, capture: true });
    for (const ev of ['touchend', 'touchcancel']) addEventListener(ev, (e) => { aimant.doigt = !!(e.touches && e.touches.length); aimant.repos = performance.now(); }, { passive: true, capture: true });
    // ascenseur tenu à la souris : on ne le lui arrache pas
    addEventListener('mousedown', (e) => { if (e.target === racineDoc) aimant.ascenseur = true; }, true);
    addEventListener('mouseup', () => { aimant.ascenseur = false; }, true);
    // navigation au clavier par Tab : le navigateur amène l'élément actif à l'écran, on le laisse faire
    addEventListener('keydown', (e) => { if (e.key === 'Tab') aimant.pause = performance.now() + 1500; }, true);
    addEventListener('pb:trajet', (e) => {
      const d = (e && e.detail) || {};
      aimant.trajet = { cible: Number(d.y) || 0, fin: performance.now() + (Number(d.duree) || 0) * 1000 + 500 };
      aimant.auto = null;
      aimant.silence = 0;
    });
  }

  /* ---------- apparitions (blocs sous l'ouverture) ---------- */
  // Un bloc apparaît avec le défilement (fonction de scrollY) ; une fois entré dans la fenêtre, il
  // finit d'apparaître tout seul en une seconde environ : avec l'aimantation, la page s'arrête
  // sur une section et le bas de l'écran ne doit pas rester à demi révélé.
  const revelations = $$('[data-revele]')
    .filter((el) => !hero || !hero.contains(el))
    .map((el) => {
      const freres = Array.from(el.parentNode.children).filter((x) => x.hasAttribute('data-revele'));
      return { el, haut: 0, q: -1, t: 0, decalage: Math.min(4, Math.max(0, freres.indexOf(el))) * 0.08 };
    });
  function majRevelations(y, force, dt) {
    for (const r of revelations) {
      if (r.haut < y + L.vh * 0.98) r.t = Math.min(1.4, r.t + (dt > 0 ? dt : 0) / 0.9);
      else r.t = 0;
      const q = Math.max(anac((y + L.vh * 0.92 - r.haut) / (0.32 * L.vh) - r.decalage), anac(r.t - r.decalage));
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
  // Rectangle de la nuée : le repère 16:9 en « cover » dans le calque fixe, point focal lu dans
  // le CSS (--focal-x, --focal-y du canvas : le cadrage validé de l'ouverture).
  function mesurer() {
    const sy = window.scrollY;
    L.vw = window.innerWidth;
    L.vh = window.innerHeight;
    L.cw = (canvas && canvas.clientWidth) || L.vw;
    L.ch = (canvas && canvas.clientHeight) || L.vh;
    L.dpr = Math.min(window.devicePixelRatio || 1, appareil.dprMax || 2);
    let fx = 0.6, fy = 0.4;
    if (canvas) {
      const cs = getComputedStyle(canvas);
      const lireF = (nom, d) => { const x = parseFloat(cs.getPropertyValue(nom)); return isFinite(x) ? x : d; };
      fx = lireF('--focal-x', fx); fy = lireF('--focal-y', fy);
    }
    const s = Math.max(L.cw / IMAGE.l, L.ch / IMAGE.h);
    const w = IMAGE.l * s, h = IMAGE.h * s;
    L.rect = { x: (L.cw - w) * fx, y: (L.ch - h) * fy, w, h };
    for (const r of revelations) r.el.style.transform = '';
    for (const r of revelations) r.haut = r.el.getBoundingClientRect().top + sy;
    for (const z of zones) { z.haut = z.el.getBoundingClientRect().top + sy; z.h = z.el.offsetHeight; }
    if (scene) { scene.haut = scene.el.getBoundingClientRect().top + sy; scene.h = scene.el.offsetHeight; }
  }
  function relayout() {
    sur('mise en page', mesurer);
    for (const s of lumieres) sur('lumière : mise en page', () => { s.mesurer(); s.poser(); });
    majRevelations(window.scrollY, true, 0);
    sale = true;
  }
  let attente = false;
  function planifier() {
    if (attente) return;
    attente = true;
    requestAnimationFrame(() => { attente = false; relayout(); });
  }

  /* ---------- globaux ---------- */
  function appliquerHud() {
    if (!hud) return;
    hud.hidden = !G().hud;
    tHud = 0;
  }
  function appliquerAnimation() {
    const on = G().animation !== false;
    for (const b of boutonsPause) {
      b.setAttribute('aria-pressed', on ? 'false' : 'true');
      const s = b.querySelector('[data-pause-libelle]') || b;
      s.textContent = on ? t('pied.pause', 'Pause de l\'animation') : t('pied.reprise', 'Reprendre l\'animation');
    }
    racineDoc.classList.toggle('animation-en-pause', !on);
    if (on) dernier = performance.now();
    sale = true;
  }
  function surGlobal(id) {
    if (id === 'hud') { appliquerHud(); planifier(); }
    else if (id === 'animation') appliquerAnimation();
    else if (id === 'aimant') { aimant.dir = 0; aimant.aVoir = false; }
    sale = true;
  }
  function surOuverture(ouvert) {
    for (const b of boutonsReglages) b.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    racineDoc.classList.toggle('panneau-ouvert', !!ouvert);
    planifier();
  }
  function basculerPanneau() { if (panneau) panneau.basculer(); }

  /* ---------- commandes ---------- */
  for (const b of boutonsPause) b.addEventListener('click', () => definirGlobal('animation', G().animation === false));
  for (const b of boutonsReglages) {
    if (panneau) b.addEventListener('click', basculerPanneau);
    else b.hidden = true;
  }
  document.addEventListener('keydown', (e) => {
    if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || estSaisie(e.target)) return;
    if (e.key === 'p' || e.key === 'P') { e.preventDefault(); basculerPanneau(); }
    else if (e.key === 'Escape') {
      if (voletPlaylist && !voletPlaylist.hidden) { fermerPlaylist(); if (boutonPlaylist) boutonPlaylist.focus(); }
      else if (panneau && panneau.ouvert) panneau.fermer();
    }
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) { dernier = performance.now(); sale = true; }   // reprise à l'instant courant
  });

  /* ---------- boucle ---------- */
  function figureForcee() {
    const f = G().figure;
    return f && f !== 'auto' ? f : null;
  }
  function lireHorloge() {
    const l = lecteur;
    if (!l) { horloge.enLecture = false; horloge.source = 'virtuelle'; return; }
    const x = Number(sur('lecteur.temps', () => l.temps(), NaN));
    if (isFinite(x)) horloge.t = x;
    horloge.source = sur('lecteur.source', () => l.source, 'virtuelle') || 'virtuelle';
    horloge.enLecture = !!sur('lecteur.enLecture', () => l.enLecture, false);
  }
  // Sources de la nuée et leurs poids : programme autonome (1 − m), titre en cours, titre sortant.
  // Une piste pas encore chargée laisse sa part au programme autonome.
  function composerSources(tMur) {
    const out = [];
    const T = titres[courant];
    if (gel) {
      if (!gel.auto && T && T.piste) return [{ piste: T.piste, t: gel.t, poids: 1 }];
      return [{ piste: null, t: gel.t, poids: 1 }];
    }
    // la vidéo du visuel 3, quand elle joue : sa bande-son conduit la nuée, le reste s'efface d'autant
    const wv = scene && scene.piste && scene.x > 0.001 ? lisse(scene.x) : 0;
    const part = 1 - wv;
    const me = lisse(m);
    const xs = sortant ? lisse(sortant.x) : 0;
    let reste = part;
    if (me > 0.001 && part > 0.001) {
      if (sortant && xs > 0.001) { out.push({ piste: sortant.piste, t: sortant.t, poids: part * me * xs }); reste -= part * me * xs; }
      if (T && T.piste) { out.push({ piste: T.piste, t: horloge.t, poids: part * me * (1 - xs) }); reste -= part * me * (1 - xs); }
    }
    if (wv > 0.001) out.push({ piste: scene.piste, t: scene.v.currentTime || 0, poids: wv });
    if (reste > 0.001) out.push({ piste: null, t: tMur, poids: reste });
    return out;
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

    // défilement : vitesse lissée, barre du haut, apparitions
    const y = window.scrollY;
    const vBrut = yPrec === null || dt <= 0 ? 0 : (y - yPrec) / dt;
    yPrec = y;
    vLisse += (vBrut - vLisse) * (1 - Math.exp(-dt / 0.2));
    if (Math.abs(vLisse) < 0.5) vLisse = 0;
    // barre du haut : invisible en haut de page, elle apparaît sur le premier cinquième d'écran ;
    // le bloc de l'ouverture s'efface d'autant (css/site.css lit --barre)
    const barre = anac(y / Math.max(1, 0.2 * L.vh));
    if (Math.abs(barre - barreVue) > 0.002 || (barre === 0 || barre === 1) !== (barreVue === 0 || barreVue === 1)) {
      barreVue = barre;
      racineDoc.style.setProperty('--barre', barre.toFixed(3));
      racineDoc.classList.toggle('barre-visible', barre > 0.5);
      racineDoc.classList.toggle('barre-pleine', barre > 0.98);
      racineDoc.classList.toggle('barre-nulle', barre < 0.02);
      if (barre <= 0.5 && voletPlaylist && !voletPlaylist.hidden) fermerPlaylist();
    }
    if (hint) {
      const o = (1 - anac(y / (0.3 * L.vh))).toFixed(3);
      if (o !== hintVu) { hintVu = o; hint.style.opacity = o; hint.style.visibility = Number(o) < 0.02 ? 'hidden' : ''; }
    }
    majRevelations(y, false, dt);
    sur('aimantation', () => majAimant(y, now));
    for (const s of lumieres) sur('lumière', () => { s.maj(y, dt); if (G().animation !== false) s.respirer(now); });

    // musique : un média joue vraiment → la nuée glisse vers lui ; sinon vers le programme autonome
    lireHorloge();
    const musique = horloge.enLecture && horloge.source !== 'virtuelle';
    const cible = musique ? 1 : 0;
    if (m !== cible) m = cible > m ? Math.min(1, m + dt / FONDU) : Math.max(0, m - dt / FONDU);
    if (sortant) {
      sortant.t += dt;
      sortant.x -= dt / FONDU_TITRE;
      if (sortant.x <= 0 || m <= 0) sortant = null;
    }
    if (scene) sur('vidéo', () => majScene(dt, y));
    const relais = !!scene && (scene.relais || (scene.actif && scene.son));   // la vidéo porte le son à la place de la musique
    if (ecoute && !musique && !relais) {   // lecture qui ne démarre pas ou s'arrête d'elle-même : on rend la main
      attenteLecture += dt;
      if (attenteLecture > 8) { ecoute = false; majLecteur(); }
    } else attenteLecture = 0;

    // calque : opacité CSS = réglage « Opacité globale », fixe ; à 0 le canvas n'est plus composé
    const params = V() ? Object.assign({}, V()) : null;
    const ok = nueeOk();
    if (ok !== sansNueeVu) { sansNueeVu = ok; racineDoc.classList.toggle('sans-nuee', !ok); }
    const opacite = params && isFinite(params.opacite) ? clamp01(params.opacite / 100) : 0;
    if (canvas && Math.abs(opacite - opaciteVue) > 0.0005) {
      const visible = opacite > 0.0005;
      if (visible !== canvasVisible) { canvasVisible = visible; canvas.style.visibility = visible ? '' : 'hidden'; }
      opaciteVue = opacite;
      canvas.style.opacity = opacite.toFixed(4);
    }

    // la nuée
    let sources = dern.sources;
    if (ok && !document.hidden && params) {
      const anime = G().animation !== false && !REDUIT;
      let murF, dtF, rendre;
      if (gel) { murF = gel.mur; dtF = 0; rendre = true; }
      else if (anime) { mur += dt; murF = mur; dtF = dt; rendre = true; }
      else { murF = mur; dtF = 0; rendre = sale; }                       // image fixe
      if (opacite <= 0.0005 && !sale) rendre = false;                    // invisible : rien à dessiner
      if (rendre) {
        sources = composerSources(murF);
        try {
          nuee.layout({ largeur: L.cw, hauteur: L.ch, dpr: L.dpr, rectImage: L.rect });
          nuee.frame({ mur: murF, dt: dtF, sources, scroll: { v: vLisse }, params, figure: figureForcee(), reduit: REDUIT });
        } catch (e) {
          nueeEnPanne = true;
          avertir('nuée : erreur de rendu', e);
        }
        sale = false;
      }
    }

    dern.v = vLisse; dern.params = params; dern.opacite = opacite; dern.sources = sources; dern.barre = barre;
    if (panneau && panneau.ouvert) {
      const T = titres[courant];
      panneau.etat({ ecoute, musique: lisse(m), titre: T ? 'Titre : ' + brut(texte(T.d.titre)) + (T.piste ? '' : ' (analyse en cours de chargement)') : '', nuee: nuee ? sur('nuee.stats', () => nuee.stats(), null) : null });
    }
    if (hud && !hud.hidden && now - tHud > 250) { tHud = now; majHud(); }
  }

  /* ---------- HUD ---------- */
  const f = (x, d) => (typeof x === 'number' && isFinite(x) ? x.toFixed(d === undefined ? 2 : d) : '—');
  function majHud() {
    const st = nuee ? sur('nuee.stats', () => nuee.stats(), null) : null;
    const fig = st && st.figures ? ['traine', 'masse', 'voile', 'fil'].map((k) => k + ' ' + f(st.figures[k], 2)).join('  ') : '—';
    const T = titres[courant];
    hud.textContent = [
      'ips ' + f(st && typeof st.ips === 'number' ? st.ips : ips, 0) + '  (boucle ' + f(ips, 0) + ')   points ' +
        (st && isFinite(st.points) ? Math.round(st.points).toLocaleString('fr-FR') : '—') + ' / ' + (st ? Math.round(st.voulu).toLocaleString('fr-FR') : '—') + '   ' + f(st && st.ms, 2) + ' ms',
      'titre ' + (T ? T.d.id : '—') + '  ' + f(gel ? gel.t : horloge.t, 2) + ' s' + (gel ? ' (figé)' : '') + '  ' + horloge.source + (horloge.enLecture ? ' lecture' : ' arrêt') +
        '  piste ' + (T && T.piste ? 'chargée' : '—'),
      'sources ' + (st && st.sources ? st.sources.map((s) => (s.piste === 'autonome' ? 'autonome' : 'piste') + ' ' + f(s.poids, 2)).join('  ') : '—') + '   musique ' + f(lisse(m), 2),
      'énergie ' + f(st && st.energie) + '  volume ' + f(st && st.volume) + '  puissance ' + f(st && st.puissance) + '  attaque ' + f(st && st.attaque),
      'nombre ×' + f(st && st.population) + '  opacité ' + f(st && st.intensite, 3) + '  dilatation ×' + f(st && st.dilatation) + '  boule ' + f(st && st.boule),
      'figures ' + fig,
      'opacité globale ' + Math.round(dern.opacite * 100) + ' %  v ' + f(dern.v, 0) + ' px/s  barre ' + f(dern.barre) + (nueeOk() ? '' : '  nuée inactive') +
        (figureForcee() ? '  figure ' + figureForcee() : '') + (attenteGeste ? '  lecture auto en attente d\'un geste' : ''),
      'vidéo ' + (scene ? (scene.actif ? 'active' : 'hors champ') + '  part ' + f(lisse(scene.x)) + (scene.son ? '  son' : '  muette') + (scene.relais ? '  relais de la musique' : '') : '—') +
        '   aimant ' + (aimantActif() ? (aimant.auto ? 'trajet → ' + Math.round(aimant.auto.cible) : aimant.trajet ? 'suspendu (trajet programmé)' : 'prêt') : 'coupé') +
        '   lumière ' + (lumieres.length ? lumieres.map((s) => f(s.p < 0 ? 0 : s.p)).join(' ') : '—'),
    ].join('\n');
  }

  /* ---------- API de test ---------- */
  function etat() {
    const st = nuee ? sur('nuee.stats', () => nuee.stats(), null) : null;
    const T = titres[courant];
    return {
      horloge: { t: gel ? gel.t : horloge.t, source: horloge.source, enLecture: horloge.enLecture, gelee: !!gel },
      mur: gel ? gel.mur : mur,
      titre: T ? T.d.id : null, piste: !!(T && T.piste), ecoute, attenteGeste, musique: lisse(m),
      sources: dern.sources.map((s) => ({ piste: s.piste ? (s.piste.titre || 'piste') : 'autonome', t: s.t, poids: s.poids })),
      params: dern.params ? Object.assign({}, dern.params) : null,
      figure: figureForcee(), opacite: dern.opacite, rect: Object.assign({}, L.rect), barre: dern.barre,
      v: dern.v, scrollY: window.scrollY,
      nuee: st, nueeOk: nueeOk(),
      animation: G().animation !== false, reduit: REDUIT, lenis: !!lenis, tactile: TACTILE,
      video: scene ? { actif: scene.actif, vue: scene.vue, x: scene.x, son: scene.son, relais: scene.relais, muette: scene.v.muted, enPause: scene.v.paused, t: scene.v.currentTime, volume: scene.v.volume, piste: !!scene.piste } : null,
      volumeMusique: audio ? audio.volume : null,
      aimant: { actif: aimantActif(), auto: aimant.auto ? aimant.auto.cible : null, trajet: aimant.trajet ? aimant.trajet.cible : null, dir: aimant.dir, n: aimant.n, zones: zones.map((z) => ({ id: z.el.id, haut: Math.round(z.haut), h: Math.round(z.h) })) },
      lumieres: lumieres.map((s) => ({ id: s.section.id, preset: s.nom, pret: s.pret, p: s.p, cible: s.cible, visible: s.visible, composition: s.cle })),
    };
  }
  window.__h4 = {
    // fige les horloges : geler(96) lit la piste du titre en cours à 96 s ; geler(40, 'auto') le
    // programme autonome à 40 s
    geler(x, mode) {
      const v = isFinite(Number(x)) ? Number(x) : horloge.t;
      gel = { t: v, mur: v, auto: mode === 'auto' };
      sale = true;
      return v;
    },
    degeler() { gel = null; sale = true; dernier = performance.now(); },
    figure(nom) {
      const v = nom === null || nom === undefined || nom === '' ? 'auto' : String(nom);
      if (FIGURES.indexOf(v) < 0) return false;
      definirGlobal('figure', v, { sansSauver: true });
      return true;
    },
    titre(i) { changerTitre(Number(i)); return courant; },
    ecouter() { basculerEcoute(); return ecoute; },
    etat,
    relayout,
    get nuee() { return nuee; },
    get lecteur() { return lecteur; },
    get piste() { const T = titres[courant]; return T ? T.piste : null; },
    get reglages() { return panneau; },
    get lenis() { return lenis; },
    get lumieres() { return lumieres; },
    get video() { return scene; },
  };

  /* ---------- amorçage ---------- */
  if (!TACTILE && !SANS_LENIS && !REDUIT && typeof window.Lenis === 'function') {
    lenis = sur('Lenis : scroll natif', () => new window.Lenis({
      duration: 1.2,
      smoothWheel: true,
      easing: (x) => Math.min(1, 1.001 - Math.pow(2, -10 * x)),
    }), null);
  }
  if (url.has('t')) {
    const x = parseFloat(url.get('t'));
    if (isFinite(x)) gel = { t: x, mur: x, auto: false };
  }
  appliquerHud();
  appliquerAnimation();
  construireTitres();
  if (titres.length && audio) installerTitre(0); else majLecteur();
  if (!panneau && (P || RG)) avertir('panneau de réglages indisponible : valeurs par défaut');
  if (!nuee) avertir('nuée indisponible');
  else if (!nueeOk()) avertir('nuée inactive (WebGL2 refusé)');

  addEventListener('resize', planifier);
  addEventListener('load', planifier);
  addEventListener('pageshow', planifier);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(planifier, () => {});
  if (window.ResizeObserver) new ResizeObserver(planifier).observe(document.body);
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

  // Lecture automatique à l'ouverture, sauf si le visiteur a mis la musique en pause pendant la
  // session (ou ?muet=1). Refusée par le navigateur : elle partira au premier geste.
  if (titres.length && lecteur && url.get('muet') !== '1' && memoire.lire(CLE_MUSIQUE) !== '0' && !gel) lire(true);

  // Les textes de l'ouverture entrent quand les polices sont là (au plus tard après 1,2 s)
  const polices = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
  Promise.race([polices, new Promise((r) => setTimeout(r, 1200))]).then(() => {
    racineDoc.classList.add('h4-pret');
    racineDoc.classList.remove('js-timeout');
    planifier();
  });

  window.__h4Booted = true;
})();
