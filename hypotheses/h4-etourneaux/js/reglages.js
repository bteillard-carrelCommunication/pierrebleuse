/* reglages.js — panneau de réglages de la nuée (vanilla, sans dépendance) → window.Reglages
 *
 * Construit depuis H4Parametres.DEFS (groupes, bornes, pas, unités, aides) :
 *   - trois contextes (vidéo, ambiance, scroll), un seul édité à la fois ; les onglets
 *     montrent le poids actuel de chaque contexte dans le mélange (fourni par main.js) ;
 *   - réglages globaux (H4Parametres.GLOBAUX) et écoute / son ;
 *   - copier / coller en JSON (repli : zone de texte), réinitialiser ;
 *   - mémorisation locale ('h4-reglages-v3'), sans erreur si le stockage est indisponible ;
 *   - section « Conduite » (mode YouTube) : on dirige au clavier pendant la lecture
 *     (1 à 5 = niveau, R = rupture, retour arrière = annuler), export / import JSON.
 *
 * Les paramètres d'URL (?fond=, ?figure=, ?hud=1) s'appliquent sans être mémorisés tant
 * qu'on ne les change pas dans le panneau. Ouverture : bouton « Réglages » ou touche P,
 * câblés par main.js ; Échap ferme.
 */
(function () {
  'use strict';

  const VERSION = 1;
  const NOMS_CONTEXTES = { video: 'vidéo', ambiance: 'ambiance', scroll: 'scroll' };
  const CHOIX = {
    fond: [['image', 'Image fixe'], ['video', 'Vidéo hébergée'], ['youtube', 'YouTube']],
    figure: [['auto', 'Automatique (musique)'], ['traine', 'Traîne (vol1)'], ['masse', 'Masse (vol2)'], ['voile', 'Voile (vol3)'], ['fil', 'Fil (scroll)']],
    reference: [['aucune', 'Aucune'], ['vol1', 'vol1'], ['vol2', 'vol2'], ['vol3', 'vol3']],
  };
  const SONS = [['coupe', 'coupé'], ['inaudible', 'inaudible'], ['audible', 'audible']];

  /* ---------- stockage (jamais d'exception : navigation privée, stockage bloqué) ---------- */
  function lireStockage(cle) {
    try { const s = window.localStorage.getItem(cle); return s ? JSON.parse(s) : null; } catch (e) { return null; }
  }
  function ecrireStockage(cle, obj) {
    try { window.localStorage.setItem(cle, JSON.stringify(obj)); return true; } catch (e) { return false; }
  }

  /* ---------- outils ---------- */
  function el(tag, attrs, enfants) {
    const n = document.createElement(tag);
    if (attrs) {
      for (const k of Object.keys(attrs)) {
        const v = attrs[k];
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (v === true) n.setAttribute(k, '');
        else n.setAttribute(k, String(v));
      }
    }
    if (enfants !== undefined && enfants !== null) {
      for (const c of [].concat(enfants)) {
        if (c === null || c === undefined || c === false) continue;
        n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
      }
    }
    return n;
  }

  const formats = {};
  function nombre(v, d) {
    if (!formats[d]) {
      try { formats[d] = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }); } catch (e) { formats[d] = null; }
    }
    return formats[d] ? formats[d].format(v) : Number(v).toFixed(d);
  }
  function decimales(pas) {
    const s = String(pas);
    const i = s.indexOf('.');
    return i < 0 ? 0 : s.length - i - 1;
  }
  function formater(def, v) {
    const txt = nombre(v, decimales(def.pas));
    return def.unite ? txt + ' ' + def.unite : txt;
  }
  function estSaisie(cible) {
    if (!cible || !cible.tagName) return false;
    const t = cible.tagName;
    if (t === 'TEXTAREA' || t === 'SELECT' || cible.isContentEditable) return true;
    if (t !== 'INPUT') return false;
    return !/^(range|checkbox|radio|button|submit|reset)$/i.test(cible.type || '');
  }

  function normaliserValeurs(P, src) {
    const v = P.defauts();
    if (!src || typeof src !== 'object') return v;
    for (const c of P.CONTEXTES) {
      const s = src[c];
      if (!s || typeof s !== 'object') continue;
      for (const d of P.DEFS) {
        if (s[d.id] === undefined || s[d.id] === null || s[d.id] === '') continue;
        const x = Number(s[d.id]);
        if (isFinite(x)) v[c][d.id] = P.borner(d.id, x);
      }
    }
    return v;
  }
  function globalValide(P, choix, id, x) {
    if (!Object.prototype.hasOwnProperty.call(P.GLOBAUX, id)) return false;
    const def = P.GLOBAUX[id];
    if (typeof def === 'boolean') return typeof x === 'boolean';
    if (choix[id]) return choix[id].some((c) => c[0] === x);
    if (typeof def === 'number') return typeof x === 'number' && isFinite(x);
    return typeof x === typeof def;
  }
  // Réglages numériques globaux (vidéo de fond) : bornes de H4Parametres.FOND
  function bornerGlobal(P, id, x) {
    const d = (P.FOND || []).find((e) => e.id === id);
    return d && typeof x === 'number' ? Math.min(d.max, Math.max(d.min, x)) : x;
  }

  /* Une conduite « a des données » ? Le contrat (SPEC §6) ne le dit pas : on accepte
     `vide` (booléen ou fonction), un tableau de repères, ou à défaut une durée > 0
     ou des ruptures. */
  function infoConduite(c) {
    const info = { donnees: false, n: null, ruptures: 0 };
    if (!c) return info;
    try {
      for (const k of ['reperes', 'points', 'niveaux', 'entrees', 'marques']) {
        if (Array.isArray(c[k])) { info.n = c[k].length; break; }
      }
      if (info.n === null) {
        for (const k of ['taille', 'nombre', 'longueur']) {
          const x = typeof c[k] === 'function' ? c[k]() : c[k];
          if (typeof x === 'number' && isFinite(x)) { info.n = x; break; }
        }
      }
      const ev = c.evenements;
      if (ev && Array.isArray(ev.ruptures)) info.ruptures = ev.ruptures.length;
      const vide = typeof c.vide === 'function' ? c.vide() : c.vide;
      if (typeof vide === 'boolean') info.donnees = !vide;
      else if (info.n !== null) info.donnees = info.n > 0 || info.ruptures > 0;
      else info.donnees = Number(c.duree) > 0 || info.ruptures > 0;
    } catch (e) { /* conduite incomplète : sans données */ }
    return info;
  }

  /* ---------- le panneau ---------- */
  function creer(opts) {
    opts = opts || {};
    const P = opts.parametres || window.H4Parametres;
    if (!P || !Array.isArray(P.DEFS)) throw new Error('H4Parametres manquant');
    const cle = opts.cle || 'h4-reglages-v3';
    // Variantes (H4-2, H4-3) : leurs fonds, des commandes cachées, le bloc « Vidéo de fond »
    const choix = Object.assign({}, CHOIX, opts.choix || {});
    if (P.FILTRES) choix.fondFiltre = Object.keys(P.FILTRES).map((k) => [k, P.FILTRES[k].label]).concat([['perso', 'Personnalisé']]);
    if (P.TEINTES) choix.fondTeinte = Object.keys(P.TEINTES).map((k) => [k, P.TEINTES[k].label]);
    const caches = new Set(opts.caches || []);
    const IDS_FOND = new Set((P.FOND || []).map((d) => d.id).concat(['fondTeinte']));
    const rappel = (nom, a, b, c) => {
      const f = opts[nom];
      if (typeof f !== 'function') return undefined;
      try { return f(a, b, c); } catch (e) { console.warn('[reglages]', nom, e); return undefined; }
    };

    /* état : défauts ← mémoire locale ← paramètres d'URL */
    const stocke = lireStockage(cle) || {};
    const etat = {
      valeurs: normaliserValeurs(P, stocke.valeurs),
      globaux: Object.assign({}, P.GLOBAUX),
      extra: { masquerNuee: false, onglet: P.CONTEXTES[0], son: 'audible' },
    };
    const globauxStockes = {};
    if (stocke.globaux && typeof stocke.globaux === 'object') {
      for (const id of Object.keys(stocke.globaux)) {
        if (globalValide(P, choix, id, stocke.globaux[id])) etat.globaux[id] = globauxStockes[id] = bornerGlobal(P, id, stocke.globaux[id]);
      }
    }
    if (stocke.extra && typeof stocke.extra === 'object') {
      if (typeof stocke.extra.masquerNuee === 'boolean') etat.extra.masquerNuee = stocke.extra.masquerNuee;
      if (P.CONTEXTES.indexOf(stocke.extra.onglet) >= 0) etat.extra.onglet = stocke.extra.onglet;
      if (SONS.some((s) => s[0] === stocke.extra.son)) etat.extra.son = stocke.extra.son;
    }
    const forces = {};
    const surURL = opts.forces || {};
    for (const id of Object.keys(surURL)) {
      if (globalValide(P, choix, id, surURL[id])) { etat.globaux[id] = bornerGlobal(P, id, surURL[id]); forces[id] = true; }
    }

    let minuterie = 0, enAttente = false;
    function sauverMaintenant() {
      clearTimeout(minuterie);
      enAttente = false;
      const g = {};
      for (const id of Object.keys(etat.globaux)) {
        if (forces[id]) { if (id in globauxStockes) g[id] = globauxStockes[id]; }
        else g[id] = etat.globaux[id];
      }
      return ecrireStockage(cle, { version: VERSION, valeurs: etat.valeurs, globaux: g, extra: etat.extra });
    }
    function sauver() {
      enAttente = true;
      clearTimeout(minuterie);
      minuterie = setTimeout(sauverMaintenant, 150);
    }
    window.addEventListener('pagehide', () => { if (enAttente) sauverMaintenant(); });

    /* structure */
    const racine = opts.element || el('aside');
    racine.classList.add('reglages');
    if (!racine.id) racine.id = 'reglages';
    racine.setAttribute('aria-label', 'Réglages de la nuée');
    racine.hidden = true;
    const titre = el('h2', { class: 'reglages__titre', tabindex: '-1', text: 'Réglages' });
    const boutonFermer = el('button', { type: 'button', class: 'reglages__fermer', 'aria-label': 'Fermer les réglages', text: '×' });
    const corps = el('div', { class: 'reglages__corps', 'data-lenis-prevent': true });
    racine.append(
      el('header', { class: 'reglages__tete' }, [
        el('div', { class: 'reglages__titres' }, [titre, el('p', { class: 'reglages__sous', text: opts.sousTitre || 'Nuée · hypothèse 4 · touche P' })]),
        boutonFermer,
      ]),
      corps
    );
    if (!racine.parentNode) document.body.appendChild(racine);

    const bloc = (nom, classe) => {
      const b = el('section', { class: 'rg-bloc' + (classe ? ' ' + classe : '') }, el('h3', { class: 'rg-bloc__titre', text: nom }));
      corps.appendChild(b);
      return b;
    };

    /* contextes : onglets + poids actuels */
    const blocCtx = bloc('Contexte édité');
    const onglets = el('div', { class: 'rg-onglets', role: 'tablist', 'aria-label': 'Contexte édité' });
    const ongletEls = {};
    for (const c of P.CONTEXTES) {
      const barre = el('i');
      const poids = el('span', { class: 'rg-onglet__poids', text: '—' });
      const b = el('button', { type: 'button', role: 'tab', class: 'rg-onglet', id: 'rg-onglet-' + c, 'aria-controls': 'rg-valeurs' }, [
        el('span', { class: 'rg-onglet__nom', text: NOMS_CONTEXTES[c] || c }),
        poids,
        el('span', { class: 'rg-onglet__barre', 'aria-hidden': 'true' }, barre),
      ]);
      b.addEventListener('click', () => choisirOnglet(c, false));
      ongletEls[c] = { b, poids, barre };
      onglets.appendChild(b);
    }
    onglets.addEventListener('keydown', (e) => {
      const i = P.CONTEXTES.indexOf(etat.extra.onglet);
      let j = -1;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') j = (i + 1) % P.CONTEXTES.length;
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') j = (i + P.CONTEXTES.length - 1) % P.CONTEXTES.length;
      else if (e.key === 'Home') j = 0;
      else if (e.key === 'End') j = P.CONTEXTES.length - 1;
      if (j < 0) return;
      e.preventDefault();
      choisirOnglet(P.CONTEXTES[j], true);
    });
    blocCtx.append(onglets, el('p', { class: 'rg-note', text: 'Les curseurs montrent le contexte édité. La nuée mélange les trois contextes selon leur poids actuel : vidéo quand la musique joue, ambiance sans musique, scroll sous l\'ouverture.' }));

    /* valeurs par groupe */
    const panneauValeurs = el('div', { id: 'rg-valeurs', role: 'tabpanel' });
    corps.appendChild(panneauValeurs);
    const champs = {};
    const groupes = [];
    for (const d of P.DEFS) {
      let g = groupes.find((x) => x.nom === d.groupe);
      if (!g) { g = { nom: d.groupe || 'Réglages', defs: [] }; groupes.push(g); }
      g.defs.push(d);
    }
    for (const g of groupes) {
      const b = el('section', { class: 'rg-bloc' }, el('h3', { class: 'rg-bloc__titre', text: g.nom }));
      for (const d of g.defs) { const c = champ(d); if (caches.has(d.id)) c.hidden = true; b.appendChild(c); }
      if (g.defs.every((d) => caches.has(d.id))) b.hidden = true;
      panneauValeurs.appendChild(b);
    }

    function champ(d) {
      const id = 'rg-' + d.id;
      const input = el('input', { type: 'range', id, min: d.min, max: d.max, step: d.pas, 'aria-describedby': d.aide ? id + '-aide' : null });
      const sortie = el('output', { for: id });
      const boite = el('div', { class: 'rg-champ' }, [
        el('label', { for: id, text: d.label }),
        sortie,
        input,
        d.aide ? el('p', { class: 'rg-aide', id: id + '-aide', text: d.aide }) : null,
      ]);
      input.addEventListener('input', () => {
        const c = etat.extra.onglet;
        const v = P.borner(d.id, Number(input.value));
        etat.valeurs[c][d.id] = v;
        majChamp(d);
        rappel('surValeur', c, d.id, v);
        sauver();
      });
      champs[d.id] = { d, input, sortie, boite };
      return boite;
    }
    function majChamp(d) {
      const f = champs[d.id];
      const c = etat.extra.onglet;
      const v = etat.valeurs[c][d.id];
      if (Number(f.input.value) !== v) f.input.value = String(v);
      const txt = formater(d, v);
      if (f.sortie.textContent !== txt) f.sortie.textContent = txt;
      f.input.setAttribute('aria-valuetext', txt);
      f.boite.classList.toggle('est-modifie', Math.abs(v - d.defaut[c]) > 1e-9);
    }
    function choisirOnglet(c, focus, initial) {
      if (P.CONTEXTES.indexOf(c) < 0) return;
      etat.extra.onglet = c;
      for (const k of P.CONTEXTES) {
        const o = ongletEls[k], on = k === c;
        o.b.setAttribute('aria-selected', on ? 'true' : 'false');
        o.b.tabIndex = on ? 0 : -1;
      }
      panneauValeurs.setAttribute('aria-labelledby', 'rg-onglet-' + c);
      if (focus) ongletEls[c].b.focus();
      for (const d of P.DEFS) majChamp(d);
      if (!initial) sauver();
    }

    /* écoute et son (miroir des commandes de l'ouverture) */
    const blocEcoute = bloc('Écoute');
    const boutonEcoute = el('button', { type: 'button', class: 'rg-bouton', 'aria-pressed': 'false', text: 'Écouter' });
    boutonEcoute.addEventListener('click', (e) => rappel('surAction', 'ecouter', e));
    const radiosSon = {};
    const pastSon = el('div', { class: 'rg-pastilles', role: 'radiogroup', 'aria-label': 'Son' });
    for (const [v, lib] of SONS) {
      const r = el('input', { type: 'radio', name: 'rg-son', value: v });
      r.addEventListener('change', () => { if (r.checked) { etat.extra.son = v; sauver(); rappel('surAction', 'son', v); } });
      radiosSon[v] = r;
      pastSon.appendChild(el('label', null, [r, el('span', { text: lib })]));
    }
    blocEcoute.append(
      el('div', { class: 'rg-ligne' }, boutonEcoute),
      el('p', { class: 'rg-libelle', style: 'margin-top:.8rem', text: 'Son' }),
      pastSon,
      el('p', { class: 'rg-note', text: 'La nuée suit la position de lecture : la synchronisation est la même son coupé, inaudible ou audible. Le son ne démarre que sur un clic ou une touche.' })
    );

    /* réglages globaux */
    const blocG = bloc('Commandes');
    const controles = {};
    function pastillesGlobal(id, libelle, choix) {
      const conteneur = el('div', { class: 'rg-pastilles', role: 'radiogroup', 'aria-label': libelle });
      const radios = {};
      for (const [v, lib] of choix) {
        const r = el('input', { type: 'radio', name: 'rg-' + id, value: v });
        r.addEventListener('change', () => { if (r.checked) definirGlobal(id, v); });
        radios[v] = r;
        conteneur.appendChild(el('label', null, [r, el('span', { text: lib })]));
      }
      controles[id] = { maj: () => { for (const v of Object.keys(radios)) radios[v].checked = etat.globaux[id] === v; } };
      return el('div', { class: 'rg-groupe' }, [el('p', { class: 'rg-libelle', text: libelle }), conteneur]);
    }
    function caseGlobal(id, libelle, aide, alerte) {
      const input = el('input', { type: 'checkbox', id: 'rg-g-' + id });
      input.addEventListener('change', () => definirGlobal(id, input.checked));
      controles[id] = { maj: () => { input.checked = !!etat.globaux[id]; } };
      return el('label', { class: 'rg-case' + (alerte ? ' rg-case--alerte' : ''), for: 'rg-g-' + id }, [input, el('span', null, [libelle, aide ? el('small', { text: aide }) : null])]);
    }
    function choixGlobal(id, libelle, choix) {
      const select = el('select', { class: 'rg-select', id: 'rg-g-' + id });
      for (const [v, lib] of choix) select.appendChild(el('option', { value: v, text: lib }));
      select.addEventListener('change', () => definirGlobal(id, select.value));
      controles[id] = { maj: () => { select.value = etat.globaux[id]; } };
      return el('div', { class: 'rg-groupe' }, [el('label', { class: 'rg-libelle', for: 'rg-g-' + id, text: libelle }), select]);
    }
    const caseMasquerNuee = el('input', { type: 'checkbox', id: 'rg-x-masquerNuee' });
    caseMasquerNuee.addEventListener('change', () => {
      etat.extra.masquerNuee = caseMasquerNuee.checked;
      sauver();
      rappel('surGlobal', 'masquerNuee', caseMasquerNuee.checked);
    });
    const cacher = (ids, n) => { if ([].concat(ids).every((id) => caches.has(id))) n.hidden = true; return n; };
    blocG.append(
      cacher('fond', pastillesGlobal('fond', 'Fond de l\'ouverture (recharge la page)', choix.fond)),
      cacher('superposerYouTube', caseGlobal('superposerYouTube', 'Aperçu superposé YouTube', 'Nuée par-dessus le lecteur : non conforme aux règles YouTube, test interne uniquement.', true)),
      choixGlobal('figure', 'Figure forcée', choix.figure),
      cacher('analyseDirecte', caseGlobal('analyseDirecte', 'Analyse en temps réel', 'Comparaison dans le HUD (image ou vidéo hébergée) ; démarre au prochain clic.')),
      cacher('masqueLecture', caseGlobal('masqueLecture', 'Masque ▶', 'Calque correctif du pictogramme incrusté dans le fond.')),
      cacher('reference', choixGlobal('reference', 'Référence A/B (volN_layerUp.png en « éclaircir »)', choix.reference)),
      cacher('reference', el('label', { class: 'rg-case', for: 'rg-x-masquerNuee' }, [caseMasquerNuee, el('span', null, ['Masquer la nuée pendant la comparaison'])])),
      caseGlobal('hud', 'HUD de mesure', 'Cadence, points, horloge, écart de synchronisation, figures.'),
      caseGlobal('animation', 'Animation', 'Décocher met la nuée en pause (même effet que « Pause de l\'animation »).')
    );

    /* vidéo de fond (H4-3) : préréglage, curseurs, teinte ; en tête du panneau */
    function curseurGlobal(d) {
      const id = 'rg-g-' + d.id;
      const input = el('input', { type: 'range', id, min: d.min, max: d.max, step: d.pas, 'aria-describedby': d.aide ? id + '-aide' : null });
      const sortie = el('output', { for: id });
      const boite = el('div', { class: 'rg-champ' }, [
        el('label', { for: id, text: d.label }),
        sortie,
        input,
        d.aide ? el('p', { class: 'rg-aide', id: id + '-aide', text: d.aide }) : null,
      ]);
      input.addEventListener('input', () => definirGlobal(d.id, Number(input.value)));
      controles[d.id] = {
        maj: () => {
          const v = etat.globaux[d.id];
          if (Number(input.value) !== v) input.value = String(v);
          const txt = formater(d, v);
          if (sortie.textContent !== txt) sortie.textContent = txt;
          input.setAttribute('aria-valuetext', txt);
          boite.classList.toggle('est-modifie', Math.abs(v - d.defaut) > 1e-9);
        },
      };
      return boite;
    }
    if (opts.fondVideo && Array.isArray(P.FOND)) {
      const blocF = bloc('Vidéo de fond', 'rg-fond');
      const defs = P.FOND.filter((d) => d.id !== 'fondTeinteForce');
      const force = P.FOND.find((d) => d.id === 'fondTeinteForce');
      blocF.append(...[
        choixGlobal('fondFiltre', 'Filtre', choix.fondFiltre),
        ...defs.map(curseurGlobal),
        choixGlobal('fondTeinte', 'Teinte', choix.fondTeinte),
        force ? curseurGlobal(force) : null,
        opts.noteFond ? el('p', { class: 'rg-note' + (opts.noteFondAlerte ? ' rg-note--alerte' : ''), text: opts.noteFond }) : null,
      ].filter(Boolean));
      corps.insertBefore(blocF, corps.firstChild);
    }

    /* conduite manuelle (mode YouTube) */
    const blocConduite = bloc('Conduite (YouTube)', 'rg-conduite');
    blocConduite.hidden = true;
    const boutonEnr = el('button', { type: 'button', class: 'rg-bouton', 'aria-pressed': 'false', text: 'Commencer l\'enregistrement' });
    const statutConduite = el('p', { class: 'rg-statut', role: 'status', 'aria-live': 'polite' });
    const boutonExporter = el('button', { type: 'button', class: 'rg-bouton', text: 'Exporter (JSON)' });
    const boutonImporter = el('button', { type: 'button', class: 'rg-bouton', text: 'Importer…' });
    const zoneConduite = el('textarea', { 'aria-label': 'Conduite au format JSON', spellcheck: 'false' });
    const boutonAppliquerConduite = el('button', { type: 'button', class: 'rg-bouton', text: 'Importer ce JSON' });
    const lienConduite = el('a', { class: 'rg-bouton', download: 'conduite-xqEzYw4YqRw.json', href: '#', text: 'Télécharger' });
    const echangeConduite = el('div', { class: 'rg-echange' }, [zoneConduite, el('div', { class: 'rg-ligne' }, [boutonAppliquerConduite, lienConduite])]);
    echangeConduite.hidden = true;
    blocConduite.append(
      el('p', { class: 'rg-touches' }, [
        'Pendant la lecture, dirigez au clavier : ', el('kbd', { text: '1' }), ' à ', el('kbd', { text: '5' }), ' niveau, ',
        el('kbd', { text: 'R' }), ' rupture, ', el('kbd', { text: '⌫' }), ' annuler. ',
        'Les temps viennent du lecteur YouTube ; rien n\'est capté du son.',
      ]),
      el('div', { class: 'rg-ligne' }, [boutonEnr, boutonExporter, boutonImporter]),
      statutConduite,
      echangeConduite
    );
    let conduite = null, tempsConduite = null, enregistre = false, nSession = 0, dernierRepere = '';
    let urlConduite = null;
    const fmtT = (t) => (isFinite(t) ? nombre(t, 1) + ' s' : '—');
    function majConduite() {
      if (!conduite) return;
      boutonEnr.setAttribute('aria-pressed', enregistre ? 'true' : 'false');
      boutonEnr.textContent = enregistre ? 'Arrêter l\'enregistrement' : 'Commencer l\'enregistrement';
      const info = infoConduite(conduite);
      const t = tempsConduite ? Number(safeAppel(tempsConduite)) : NaN;
      const morceaux = [
        enregistre ? '● enregistrement' : 'arrêté',
        info.donnees ? 'conduite active' + (info.n !== null ? ' (' + info.n + ' repères)' : '') : 'conduite vide',
        nSession ? nSession + ' action' + (nSession > 1 ? 's' : '') + ' ici' : null,
        dernierRepere ? 'dernier : ' + dernierRepere : null,
        'temps ' + fmtT(t),
      ];
      const txt = morceaux.filter(Boolean).join(' · ');
      if (statutConduite.textContent !== txt) statutConduite.textContent = txt;
    }
    function safeAppel(f) { try { return f(); } catch (e) { return NaN; } }
    boutonEnr.addEventListener('click', () => { enregistre = !enregistre && !!conduite; majConduite(); });
    boutonExporter.addEventListener('click', () => {
      if (!conduite) return;
      let txt;
      try { txt = conduite.exporter(); } catch (e) { statut('Export impossible : ' + e.message); return; }
      if (typeof txt !== 'string') txt = JSON.stringify(txt, null, 2);
      zoneConduite.value = txt;
      echangeConduite.hidden = false;
      boutonAppliquerConduite.hidden = true;
      lienConduite.hidden = false;
      try {
        if (urlConduite) URL.revokeObjectURL(urlConduite);
        urlConduite = URL.createObjectURL(new Blob([txt], { type: 'application/json' }));
        lienConduite.href = urlConduite;
      } catch (e) { lienConduite.hidden = true; }
      zoneConduite.focus();
      zoneConduite.select();
    });
    boutonImporter.addEventListener('click', () => {
      zoneConduite.value = '';
      echangeConduite.hidden = false;
      boutonAppliquerConduite.hidden = false;
      lienConduite.hidden = true;
      zoneConduite.focus();
    });
    boutonAppliquerConduite.addEventListener('click', () => {
      if (!conduite) return;
      try {
        conduite.importer(zoneConduite.value);
        dernierRepere = 'import';
        echangeConduite.hidden = true;
      } catch (e) { dernierRepere = 'import refusé (' + e.message + ')'; }
      majConduite();
    });
    document.addEventListener('keydown', (e) => {
      if (!enregistre || !conduite) return;
      if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.repeat) return;
      if (estSaisie(e.target)) return;
      const t = tempsConduite ? Number(safeAppel(tempsConduite)) : NaN;
      try {
        if (e.key >= '1' && e.key <= '5' && e.key.length === 1) {
          e.preventDefault();
          conduite.enregistrer(t, Number(e.key));
          nSession++;
          dernierRepere = 'niveau ' + e.key + ' à ' + fmtT(t);
        } else if (e.key === 'r' || e.key === 'R') {
          e.preventDefault();
          conduite.rupture(t);
          nSession++;
          dernierRepere = 'rupture à ' + fmtT(t);
        } else if (e.key === 'Backspace') {
          e.preventDefault();
          conduite.annuler();
          nSession++;
          dernierRepere = 'annulation';
        } else return;
      } catch (err) { dernierRepere = 'erreur : ' + err.message; }
      majConduite();
    });

    /* échange : copier / coller / réinitialiser */
    const blocActions = bloc('Échanger');
    const zone = el('textarea', { 'aria-label': 'Réglages au format JSON', spellcheck: 'false' });
    const boutonAppliquer = el('button', { type: 'button', class: 'rg-bouton', text: 'Appliquer' });
    const boutonFermerEchange = el('button', { type: 'button', class: 'rg-bouton', text: 'Fermer' });
    const echange = el('div', { class: 'rg-echange' }, [zone, el('div', { class: 'rg-ligne' }, [boutonAppliquer, boutonFermerEchange])]);
    echange.hidden = true;
    const ligneStatut = el('p', { class: 'rg-statut', role: 'status', 'aria-live': 'polite' });
    const boutonCopier = el('button', { type: 'button', class: 'rg-bouton', text: 'Copier les réglages' });
    const boutonColler = el('button', { type: 'button', class: 'rg-bouton', text: 'Coller' });
    const boutonReinit = el('button', { type: 'button', class: 'rg-bouton', text: 'Réinitialiser' });
    blocActions.append(el('div', { class: 'rg-ligne' }, [boutonCopier, boutonColler, boutonReinit]), echange, ligneStatut);

    function statut(txt) { ligneStatut.textContent = txt; }
    function texteReglages() {
      return JSON.stringify({ h4: 'reglages', version: VERSION, valeurs: etat.valeurs, globaux: etat.globaux }, null, 2);
    }
    function montrerEchange(texte, mode) {
      zone.value = texte;
      echange.hidden = false;
      boutonAppliquer.hidden = mode !== 'coller';
      zone.focus();
      if (texte) zone.select();
    }
    async function copier() {
      const txt = texteReglages();
      let ok = false;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) { await navigator.clipboard.writeText(txt); ok = true; }
      } catch (e) { ok = false; }
      if (ok) { echange.hidden = true; statut('Réglages copiés dans le presse-papiers (JSON).'); }
      else { montrerEchange(txt, 'copier'); statut('Copie automatique refusée : le texte est sélectionné, copiez-le (⌘C ou Ctrl+C).'); }
      return ok;
    }
    async function coller() {
      let txt = null;
      try {
        if (navigator.clipboard && navigator.clipboard.readText) txt = await navigator.clipboard.readText();
      } catch (e) { txt = null; }
      if (txt && appliquerTexte(txt, true)) return true;
      montrerEchange('', 'coller');
      statut(txt ? 'Le presse-papiers ne contient pas de réglages : collez le JSON ici, puis « Appliquer ».' : 'Lecture du presse-papiers refusée : collez le JSON ici, puis « Appliquer ».');
      return false;
    }
    function appliquerTexte(txt, silencieux) {
      let obj;
      try { obj = JSON.parse(txt); } catch (e) { if (!silencieux) statut('JSON illisible : ' + e.message); return false; }
      return importer(obj, silencieux);
    }
    function importer(obj, silencieux) {
      if (!obj || typeof obj !== 'object') { if (!silencieux) statut('Ce JSON ne contient pas de réglages.'); return false; }
      const src = obj.valeurs && typeof obj.valeurs === 'object' ? obj.valeurs : obj;
      const aDesValeurs = P.CONTEXTES.some((c) => src[c] && typeof src[c] === 'object');
      const g = obj.globaux && typeof obj.globaux === 'object' ? obj.globaux : null;
      if (!aDesValeurs && !g) { if (!silencieux) statut('Ce JSON ne contient pas de réglages.'); return false; }
      if (aDesValeurs) etat.valeurs = normaliserValeurs(P, src);
      if (g) for (const id of Object.keys(g)) { if (id !== 'fond') definirGlobal(id, g[id], { sansSauver: true }); }
      majTout();
      rappel('surValeur', '*', null, null);
      sauverMaintenant();
      echange.hidden = true;
      statut('Réglages appliqués' + (g && g.fond && g.fond !== etat.globaux.fond ? ' (le fond « ' + g.fond + ' » se choisit à part)' : '') + '.');
      return true;
    }
    function reinitialiser() {
      etat.valeurs = P.defauts();
      for (const id of Object.keys(P.GLOBAUX)) { if (id !== 'fond') definirGlobal(id, P.GLOBAUX[id], { sansSauver: true }); }
      if (etat.extra.masquerNuee) { etat.extra.masquerNuee = false; rappel('surGlobal', 'masquerNuee', false); }
      majTout();
      rappel('surValeur', '*', null, null);
      sauverMaintenant();
      echange.hidden = true;
      statut('Réglages par défaut rétablis (fond conservé).');
    }
    boutonCopier.addEventListener('click', copier);
    boutonColler.addEventListener('click', coller);
    boutonReinit.addEventListener('click', reinitialiser);
    boutonAppliquer.addEventListener('click', () => appliquerTexte(zone.value, false));
    boutonFermerEchange.addEventListener('click', () => { echange.hidden = true; statut(''); });

    /* globaux */
    function definirGlobal(id, v, o) {
      if (!globalValide(P, choix, id, v)) return false;
      v = bornerGlobal(P, id, v);
      const ancien = etat.globaux[id];
      etat.globaux[id] = v;
      forces[id] = false;
      if (controles[id]) controles[id].maj();
      if (ancien !== v && !(o && o.silencieux)) rappel('surGlobal', id, v, ancien);
      if (!(o && o.sansSauver)) sauver();
      // vidéo de fond : un préréglage pose ses curseurs ; un curseur touché le rend « personnalisé »
      if (!(o && o.brut) && P.FILTRES) {
        const o2 = Object.assign({}, o, { brut: true });
        if (id === 'fondFiltre' && P.FILTRES[v]) {
          const pr = P.FILTRES[v].v;
          for (const k of Object.keys(pr)) definirGlobal(k, pr[k], o2);
        } else if (IDS_FOND.has(id) && P.filtreDe) {
          const f = P.filtreDe(etat.globaux);
          if (f !== etat.globaux.fondFiltre) definirGlobal('fondFiltre', f, o2);
        }
      }
      return true;
    }
    function majTout() {
      for (const d of P.DEFS) majChamp(d);
      for (const id of Object.keys(controles)) controles[id].maj();
      caseMasquerNuee.checked = !!etat.extra.masquerNuee;
      for (const v of Object.keys(radiosSon)) radiosSon[v].checked = etat.extra.son === v;
    }

    /* ouverture / fermeture */
    let ouvert = false, focusAvant = null;
    function ouvrir() {
      if (ouvert) return;
      ouvert = true;
      focusAvant = document.activeElement;
      racine.hidden = false;
      dernierEtat = 0;
      rappel('surOuverture', true);
      try { titre.focus({ preventScroll: true }); } catch (e) { /* rien */ }
    }
    function fermer() {
      if (!ouvert) return;
      const dedans = racine.contains(document.activeElement);
      ouvert = false;
      racine.hidden = true;
      rappel('surOuverture', false);
      if (dedans && focusAvant && focusAvant.focus) { try { focusAvant.focus({ preventScroll: true }); } catch (e) { /* rien */ } }
    }
    boutonFermer.addEventListener('click', fermer);
    racine.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.stopPropagation(); fermer(); } });

    /* état en direct (poids des contextes, écoute, son, conduite) : quelques fois par seconde */
    let dernierEtat = 0;
    function majEtat(info) {
      if (!ouvert || !info) return;
      const now = performance.now();
      if (now - dernierEtat < 150) return;
      dernierEtat = now;
      if (info.poids) {
        for (const c of P.CONTEXTES) {
          const w = Math.max(0, Math.min(1, Number(info.poids[c]) || 0));
          const o = ongletEls[c];
          const txt = Math.round(w * 100) + ' %';
          if (o.poids.textContent !== txt) o.poids.textContent = txt;
          o.barre.style.width = (w * 100).toFixed(1) + '%';
          o.b.classList.toggle('est-actif', w > 0.005);
        }
      }
      if (typeof info.ecoute === 'boolean') {
        const lib = info.libelle || (info.ecoute ? 'Pause' : 'Écouter');
        if (boutonEcoute.textContent !== lib) boutonEcoute.textContent = lib;
        boutonEcoute.setAttribute('aria-pressed', info.ecoute ? 'true' : 'false');
      }
      if (typeof info.ecouteDisponible === 'boolean') boutonEcoute.disabled = !info.ecouteDisponible;
      if (info.son && radiosSon[info.son] && !radiosSon[info.son].checked) radiosSon[info.son].checked = true;
      if (conduite) majConduite();
    }

    choisirOnglet(etat.extra.onglet, false, true);
    majTout();

    return {
      element: racine,
      get valeurs() { return etat.valeurs; },
      get globaux() { return etat.globaux; },
      get extra() { return etat.extra; },
      get ouvert() { return ouvert; },
      get onglet() { return etat.extra.onglet; },
      get enregistrement() { return enregistre; },
      ouvrir, fermer,
      basculer() { if (ouvert) fermer(); else ouvrir(); },
      etat: majEtat,
      definirGlobal,
      definirValeur(c, id, v) {
        if (!etat.valeurs[c] || !(id in etat.valeurs[c])) return false;
        const x = Number(v);
        if (!isFinite(x)) return false;
        etat.valeurs[c][id] = P.borner(id, x);
        if (champs[id]) majChamp(champs[id].d);
        rappel('surValeur', c, id, etat.valeurs[c][id]);
        sauver();
        return true;
      },
      definirSon(v) {
        if (!SONS.some((s) => s[0] === v)) return;
        etat.extra.son = v;
        if (radiosSon[v]) radiosSon[v].checked = true;
        sauver();
      },
      choisirOnglet: (c) => choisirOnglet(c, false),
      exporter: texteReglages,
      importer: (x) => (typeof x === 'string' ? appliquerTexte(x, false) : importer(x, false)),
      copier, coller, reinitialiser,
      sauver: sauverMaintenant,
      conduite(c, o) {
        conduite = c || null;
        tempsConduite = o && typeof o.temps === 'function' ? o.temps : null;
        blocConduite.hidden = !conduite;
        if (!conduite) enregistre = false;
        majConduite();
      },
    };
  }

  window.Reglages = { creer, infoConduite, CHOIX, version: VERSION };
})();
