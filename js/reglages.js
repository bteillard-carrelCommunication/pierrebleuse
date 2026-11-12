/* reglages.js — panneau de réglages de la nuée (vanilla, sans dépendance) → window.Reglages
 *
 * Outil de mise au point, construit depuis H4Parametres.DEFS (groupes, bornes, pas, unités,
 * aides). Un seul mode de vol depuis le 12/11/2026 : plus d'onglets ni de mélange de contextes.
 *   - « Lecture » : musique, pause de l'animation (WCAG 2.2.2) ;
 *   - un curseur par réglage, groupés : Rendu, Mouvement, Réaction au volume (avec un relevé en
 *     direct du volume, du nombre de points et de la boule, fourni par main.js) ;
 *   - « Commandes » : figure forcée, HUD, animation, aimantation des sections ;
 *   - « Échanger » : copier, coller (JSON), réinitialiser.
 * Mémorisation locale, sans erreur si le stockage est indisponible. Les paramètres d'adresse
 * (?figure=, ?hud=1) s'appliquent sans être mémorisés tant qu'on ne les change pas ici.
 * Ouverture : touche P ou lien « Réglages de la nuée » du pied de page (câblés par main.js) ;
 * Échap ferme.
 *
 * JSON : { h4: 'reglages', version: 2, valeurs: { opacite, … }, globaux }. Un JSON de la
 * version 1 (trois contextes video / ambiance / scroll) est accepté : son jeu « video » devient
 * le mode unique (H4Parametres.normaliser).
 */
(function () {
  'use strict';

  const VERSION = 2;
  const CHOIX = {
    figure: [['auto', 'Automatique (musique)'], ['traine', 'Traîne'], ['masse', 'Masse'], ['voile', 'Voile'], ['fil', 'Fil']],
  };

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
    return def.unite ? txt + ' ' + def.unite : txt;
  }
  function globalValide(P, id, x) {
    if (!Object.prototype.hasOwnProperty.call(P.GLOBAUX, id)) return false;
    const def = P.GLOBAUX[id];
    if (typeof def === 'boolean') return typeof x === 'boolean';
    if (CHOIX[id]) return CHOIX[id].some((c) => c[0] === x);
    return typeof x === typeof def;
  }

  /* ---------- le panneau ---------- */
  function creer(opts) {
    opts = opts || {};
    const P = opts.parametres || window.H4Parametres;
    if (!P || !Array.isArray(P.DEFS)) throw new Error('H4Parametres manquant');
    const cle = opts.cle || 'pb-nuee-reglages-v1';
    const rappel = (nom, a, b, c) => {
      const f = opts[nom];
      if (typeof f !== 'function') return undefined;
      try { return f(a, b, c); } catch (e) { console.warn('[reglages]', nom, e); return undefined; }
    };

    /* état : défauts ← mémoire locale ← paramètres d'adresse */
    const stocke = lireStockage(cle) || {};
    const etat = { valeurs: P.normaliser(stocke.valeurs), globaux: Object.assign({}, P.GLOBAUX) };
    const globauxStockes = {};
    if (stocke.globaux && typeof stocke.globaux === 'object') {
      for (const id of Object.keys(stocke.globaux)) {
        if (globalValide(P, id, stocke.globaux[id])) etat.globaux[id] = globauxStockes[id] = stocke.globaux[id];
      }
    }
    const forces = {};
    const surURL = opts.forces || {};
    for (const id of Object.keys(surURL)) {
      if (globalValide(P, id, surURL[id])) { etat.globaux[id] = surURL[id]; forces[id] = true; }
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
      return ecrireStockage(cle, { version: VERSION, valeurs: etat.valeurs, globaux: g });
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
    racine.setAttribute('lang', 'fr');
    racine.hidden = true;
    const titre = el('h2', { class: 'reglages__titre', tabindex: '-1', text: 'Réglages' });
    const boutonFermer = el('button', { type: 'button', class: 'reglages__fermer', 'aria-label': 'Fermer les réglages', text: '×' });
    const corps = el('div', { class: 'reglages__corps', 'data-lenis-prevent': true });
    racine.append(
      el('header', { class: 'reglages__tete' }, [
        el('div', { class: 'reglages__titres' }, [titre, el('p', { class: 'reglages__sous', text: opts.sousTitre || 'Nuée · touche P' })]),
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

    /* lecture : musique et animation */
    const blocLecture = bloc('Lecture', 'rg-lecture');
    const boutonMusique = el('button', { type: 'button', class: 'rg-bouton', 'aria-pressed': 'false', text: 'Lancer la musique' });
    boutonMusique.addEventListener('click', (e) => rappel('surAction', 'ecouter', e));
    const boutonAnimation = el('button', { type: 'button', class: 'rg-bouton', 'aria-pressed': 'false', text: 'Pause de l\'animation' });
    boutonAnimation.addEventListener('click', () => definirGlobal('animation', etat.globaux.animation === false));
    const ligneTitre = el('p', { class: 'rg-note', text: '' });
    blocLecture.append(
      el('div', { class: 'rg-ligne' }, [boutonMusique, boutonAnimation]),
      ligneTitre,
      el('p', { class: 'rg-note', text: 'Un seul mode de vol. Quand la musique joue, la nuée la suit ; quand elle s\'arrête, elle glisse en 2,5 s vers un mouvement autonome, lent et déterministe. Changer de titre fond l\'ancien dans le nouveau.' })
    );

    /* valeurs par groupe */
    const champs = {};
    const groupes = [];
    for (const d of P.DEFS) {
      let g = groupes.find((x) => x.nom === d.groupe);
      if (!g) { g = { nom: d.groupe || 'Réglages', defs: [] }; groupes.push(g); }
      g.defs.push(d);
    }
    const releve = el('p', { class: 'rg-statut rg-releve', 'aria-hidden': 'true', text: '' });
    for (const g of groupes) {
      const b = bloc(g.nom);
      if (g.nom === 'Réaction au volume') b.appendChild(releve);
      for (const d of g.defs) b.appendChild(champ(d));
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
        const v = P.borner(d.id, Number(input.value));
        etat.valeurs[d.id] = v;
        majChamp(d);
        rappel('surValeur', d.id, v);
        sauver();
      });
      champs[d.id] = { d, input, sortie, boite };
      return boite;
    }
    function majChamp(d) {
      const f = champs[d.id];
      const v = etat.valeurs[d.id];
      if (Number(f.input.value) !== v) f.input.value = String(v);
      const txt = formater(d, v);
      if (f.sortie.textContent !== txt) f.sortie.textContent = txt;
      f.input.setAttribute('aria-valuetext', txt);
      f.boite.classList.toggle('est-modifie', Math.abs(v - d.defaut) > 1e-9);
    }

    /* réglages globaux */
    const blocG = bloc('Commandes');
    const controles = {};
    function caseGlobal(id, libelle, aide) {
      const input = el('input', { type: 'checkbox', id: 'rg-g-' + id });
      input.addEventListener('change', () => definirGlobal(id, input.checked));
      controles[id] = { maj: () => { input.checked = !!etat.globaux[id]; } };
      return el('label', { class: 'rg-case', for: 'rg-g-' + id }, [input, el('span', null, [libelle, aide ? el('small', { text: aide }) : null])]);
    }
    function choixGlobal(id, libelle, choix) {
      const select = el('select', { class: 'rg-select', id: 'rg-g-' + id });
      for (const [v, lib] of choix) select.appendChild(el('option', { value: v, text: lib }));
      select.addEventListener('change', () => definirGlobal(id, select.value));
      controles[id] = { maj: () => { select.value = etat.globaux[id]; } };
      return el('div', { class: 'rg-groupe' }, [el('label', { class: 'rg-libelle', for: 'rg-g-' + id, text: libelle }), select]);
    }
    blocG.append(
      choixGlobal('figure', 'Figure forcée', CHOIX.figure),
      caseGlobal('hud', 'HUD de mesure', 'Cadence, points, horloge, sources, volume, figures.'),
      caseGlobal('animation', 'Animation', 'Décocher met la nuée en pause (même effet que « Pause de l\'animation »).'),
      caseGlobal('aimant', 'Aimantation des sections', 'Dès que 10 % d\'une section sont à l\'écran, la page défile seule jusqu\'à elle.')
    );
    const majCaseAnimation = controles.animation.maj;
    controles.animation.maj = () => {
      majCaseAnimation();
      const pause = etat.globaux.animation === false;
      boutonAnimation.textContent = pause ? 'Reprendre l\'animation' : 'Pause de l\'animation';
      boutonAnimation.setAttribute('aria-pressed', pause ? 'true' : 'false');
    };

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
      const ancien = !!(src.video && typeof src.video === 'object');          // version 1 : trois contextes
      const aDesValeurs = ancien || P.DEFS.some((d) => typeof src[d.id] === 'number');
      const g = obj.globaux && typeof obj.globaux === 'object' ? obj.globaux : null;
      if (!aDesValeurs && !g) { if (!silencieux) statut('Ce JSON ne contient pas de réglages.'); return false; }
      if (aDesValeurs) etat.valeurs = P.normaliser(src);
      if (g) for (const id of Object.keys(g)) definirGlobal(id, g[id], { sansSauver: true });   // les clés inconnues sont ignorées
      majTout();
      rappel('surValeur', '*', null);
      sauverMaintenant();
      echange.hidden = true;
      statut(ancien ? 'Réglages appliqués : JSON à trois modes, le jeu « En musique » (video) devient le mode unique.' : 'Réglages appliqués.');
      return true;
    }
    function reinitialiser() {
      etat.valeurs = P.defauts();
      for (const id of Object.keys(P.GLOBAUX)) definirGlobal(id, P.GLOBAUX[id], { sansSauver: true });
      majTout();
      rappel('surValeur', '*', null);
      sauverMaintenant();
      echange.hidden = true;
      statut('Réglages par défaut rétablis.');
    }
    boutonCopier.addEventListener('click', copier);
    boutonColler.addEventListener('click', coller);
    boutonReinit.addEventListener('click', reinitialiser);
    boutonAppliquer.addEventListener('click', () => appliquerTexte(zone.value, false));
    boutonFermerEchange.addEventListener('click', () => { echange.hidden = true; statut(''); });

    /* globaux */
    function definirGlobal(id, v, o) {
      if (!globalValide(P, id, v)) return false;
      const ancien = etat.globaux[id];
      etat.globaux[id] = v;
      forces[id] = false;
      if (controles[id]) controles[id].maj();
      if (ancien !== v) rappel('surGlobal', id, v, ancien);
      if (!(o && o.sansSauver)) sauver();
      return true;
    }
    function majTout() {
      for (const d of P.DEFS) majChamp(d);
      for (const id of Object.keys(controles)) controles[id].maj();
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

    /* état en direct (musique, titre, volume, points, boule) : quelques fois par seconde */
    let dernierEtat = 0;
    function majEtat(info) {
      if (!ouvert || !info) return;
      const now = performance.now();
      if (now - dernierEtat < 150) return;
      dernierEtat = now;
      if (typeof info.ecoute === 'boolean') {
        const lib = info.ecoute ? 'Pause de la musique' : 'Lancer la musique';
        if (boutonMusique.textContent !== lib) boutonMusique.textContent = lib;
        boutonMusique.setAttribute('aria-pressed', info.ecoute ? 'true' : 'false');
      }
      if (typeof info.titre === 'string' && ligneTitre.textContent !== info.titre) ligneTitre.textContent = info.titre;
      if (info.nuee) {
        const n = info.nuee;
        const txt = 'musique ' + Math.round((info.musique || 0) * 100) + ' %  ·  volume ' + nombre(n.volume || 0, 2) +
          '  ·  ' + nombre(n.points || 0, 0) + ' points  ·  opacité ×' + nombre((n.intensite || 0) / Math.max(1e-6, etat.valeurs.intensite), 2) +
          '  ·  boule ' + Math.round((n.boule || 0) * 100) + ' %';
        if (releve.textContent !== txt) releve.textContent = txt;
      }
    }

    majTout();

    return {
      element: racine,
      get valeurs() { return etat.valeurs; },
      get globaux() { return etat.globaux; },
      get ouvert() { return ouvert; },
      ouvrir, fermer,
      basculer() { if (ouvert) fermer(); else ouvrir(); },
      etat: majEtat,
      definirGlobal,
      definirValeur(id, v) {
        if (!(id in etat.valeurs)) return false;
        const x = Number(v);
        if (!isFinite(x)) return false;
        etat.valeurs[id] = P.borner(id, x);
        if (champs[id]) majChamp(champs[id].d);
        rappel('surValeur', id, etat.valeurs[id]);
        sauver();
        return true;
      },
      exporter: texteReglages,
      importer: (x) => (typeof x === 'string' ? appliquerTexte(x, false) : importer(x, false)),
      copier, coller, reinitialiser,
      sauver: sauverMaintenant,
    };
  }

  window.Reglages = { creer, CHOIX, version: VERSION };
})();
