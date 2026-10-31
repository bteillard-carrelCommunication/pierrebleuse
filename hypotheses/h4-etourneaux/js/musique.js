/*
 * Musique : ce que la nuée sait de la pièce (SPEC.md, §§ 3.3, 3.4 et 6).
 *
 * Trois sources, une même interface (at, evenements, cumul, offset, duree, fps) :
 *   - piste pré-analysée (outils/analyse-bande-son.py) : canaux 30 Hz en uint8, décodés en
 *     Float32 0-1, interpolation linéaire, 0 hors plage ; cumul(t) = ∫ energie par sommes
 *     préfixées ; événements en temps média (offset + temps de trame) ;
 *   - conduite manuelle (YouTube sans fichier maître) : niveaux 1-5 et ruptures posés au
 *     clavier pendant la lecture, rendus en canaux comme une piste ; mémorisée localement,
 *     exportable en JSON lisible par chargerPiste ;
 *   - analyse en temps réel (comparaison seulement, après un geste) : AnalyserNode sur le
 *     média hébergé. Média coupé → zéros dans Chrome et Firefox ; retard de l'analyseur estimé
 *     et mesurable contre la piste.
 *
 * Rien ici ne lit le son d'un lecteur YouTube : la conduite ne reçoit que des temps.
 */
(function () {
  'use strict';

  const CANAUX = ['energie', 'attaque', 'brillance', 'graves', 'mediums', 'aigus', 'nouveaute'];
  const clamp01 = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);
  const lisse = (x) => { x = clamp01(x); return x * x * (3 - 2 * x); };

  function zeros(out) {
    out = out || {};
    for (const k of CANAUX) out[k] = 0;
    return out;
  }

  /* ---------- Piste ---------- */

  class Piste {
    // doc : objet JSON au format de SPEC.md § 6 (version 1).
    constructor(doc) {
      if (!doc || typeof doc !== 'object') throw new Error('Piste : document JSON attendu');
      if (doc.version !== undefined && doc.version !== 1) throw new Error('Piste : version ' + doc.version + ' inconnue');
      const fps = Number(doc.fps);
      if (!(fps > 0)) throw new Error('Piste : fps invalide');
      const donnees = doc.donnees || {};
      let n = 0;
      for (const k of CANAUX) if (Array.isArray(donnees[k])) n = Math.max(n, donnees[k].length);
      if (n < 2) throw new Error('Piste : pas de données');

      this.type = doc.type === 'conduite' ? 'conduite' : 'piste';
      this.version = 1;
      this.fps = fps;
      this.trames = n;
      this.offset = Number(doc.offset) || 0;      // modifiable : décale canaux et événements d'un bloc
      this.duree = Number(doc.duree) > 0 ? Number(doc.duree) : (n - 1) / fps;
      this.anticipation = Number(doc.anticipation) || 0;
      this.anticipationAttaques = Number(doc.anticipationAttaques ?? doc.anticipation) || 0;
      this.titre = doc.titre || '';
      this.interpretes = doc.interpretes || '';
      this.licence = doc.licence || '';
      this.source = doc.source || '';
      this.echelles = doc.echelles || null;
      this.canaux = CANAUX.slice();
      this.manquants = CANAUX.filter((k) => !Array.isArray(donnees[k]));

      // Canaux 0-255 → Float32 0-1 (un canal absent reste à 0).
      this._c = {};
      for (const k of CANAUX) {
        const a = new Float32Array(n), src = donnees[k];
        if (Array.isArray(src)) for (let i = 0; i < n; i++) a[i] = clamp01((+src[i] || 0) / 255);
        this._c[k] = a;
      }
      // Sommes préfixées de l'énergie (trapèzes) : C[i] = ∫ de la trame 0 à la trame i.
      const e = this._c.energie, C = new Float64Array(n);
      for (let i = 1; i < n; i++) C[i] = C[i - 1] + 0.5 * (e[i - 1] + e[i]) / fps;
      this._C = C;

      // Événements en temps de trame (secondes depuis la trame 0), triés.
      const ev = doc.evenements || {};
      const prendre = (liste) => (Array.isArray(liste) ? liste : [])
        .filter((x) => x && isFinite(x.t))
        .map((x) => {
          const o = { t: +x.t, force: clamp01(x.force === undefined ? 1 : +x.force) };
          if (x.type) o.type = String(x.type);
          return o;
        })
        .sort((a, b) => a.t - b.t);
      this._ev = { ruptures: prendre(ev.ruptures), attaques: prendre(ev.attaques) };
      this._evCache = null;
      this._evOffset = NaN;
    }

    // Temps média → indice de trame fractionnaire ; -1 hors plage.
    _x(t) {
      const x = (t - this.offset) * this.fps;
      return (x >= 0 && x <= this.duree * this.fps) ? x : -1;
    }

    // Caractéristiques au temps média t (0-1, interpolées ; 0 hors plage). out : objet réutilisé.
    at(t, out) {
      out = out || {};
      const x = this._x(t);
      if (x < 0) return zeros(out);
      const last = this.trames - 1;
      let i = Math.floor(x), a = x - i;
      if (i >= last) { i = last; a = 0; }      // entre la dernière trame et duree : valeur tenue
      for (const k of CANAUX) {
        const c = this._c[k];
        out[k] = a ? c[i] + (c[i + 1] - c[i]) * a : c[i];
      }
      return out;
    }

    // ∫ energie dτ de offset à t (cohérent avec at : tenue en fin de plage, 0 au-delà). O(1).
    cumul(t) {
      const fps = this.fps, last = this.trames - 1, e = this._c.energie, C = this._C;
      let x = (t - this.offset) * fps;
      if (!(x > 0)) return 0;
      const xmax = this.duree * fps;
      if (x > xmax) x = xmax;
      if (x >= last) return C[last] + e[last] * (x - last) / fps;
      const i = Math.floor(x), a = x - i;
      return C[i] + (e[i] * a + 0.5 * (e[i + 1] - e[i]) * a * a) / fps;
    }

    // Événements en temps média (recalculés si offset change).
    get evenements() {
      if (this._evCache && this._evOffset === this.offset) return this._evCache;
      const o = this.offset, dec = (l) => l.map((x) => Object.assign({}, x, { t: o + x.t }));
      this._evCache = { ruptures: dec(this._ev.ruptures), attaques: dec(this._ev.attaques) };
      this._evOffset = o;
      return this._evCache;
    }

    // Copie brute d'un canal (Float32 0-1), pour le HUD ou les tests.
    canal(nom) { return this._c[nom] ? this._c[nom].slice() : null; }
  }

  function chargerPiste(url) {
    return fetch(url, { cache: 'no-cache' }).then((r) => {
      if (!r.ok) throw new Error('Piste : HTTP ' + r.status + ' pour ' + url);
      return r.json();
    }).then((doc) => new Piste(doc));
  }

  /* ---------- Conduite manuelle ---------- */

  // Niveau 1-5 → énergie (espacement régulier en sones, comme le canal pré-analysé).
  const NIVEAUX = [0, 0.08, 0.28, 0.5, 0.74, 0.97];

  class Conduite {
    constructor(opts) {
      opts = opts || {};
      this.cle = opts.cle || null;
      this.type = 'conduite';
      this.fps = opts.fps || 30;
      this._offsetFixe = isFinite(opts.offset) ? +opts.offset : null;
      this._dureeFixe = opts.duree > 0 ? +opts.duree : null;
      this.reaction = opts.reaction ?? 0.2;            // retard estimé de la touche sur la musique (s)
      this.anticipation = opts.anticipation ?? 0.25;   // comme la piste pré-analysée
      this.anticipationAttaques = opts.anticipationAttaques ?? 0.05;
      this.rampe = opts.rampe ?? 1.0;                  // durée d'un changement de niveau (s)
      this.titre = opts.titre || 'Conduite manuelle';
      this.source = opts.source || '';
      this.interpretes = opts.interpretes || '';
      this.licence = opts.licence || '';
      this.stockage = 'aucun';                         // 'local' quand localStorage répond
      this.onchange = null;
      this.revision = 0;                               // +1 à chaque changement (le moteur replanifie)
      this._reperes = [];                              // { t, niveau } | { t, rupture: true }, triés
      this._actions = [];                              // pile d'annulation
      this._piste = null;
      this._charger();
      this._rendre();
    }

    get offset() { return this._piste ? this._piste.offset : (this._offsetFixe || 0); }
    set offset(v) { this._offsetFixe = +v || 0; this.revision++; this._rendre(); }
    get duree() { return this._piste ? this._piste.duree : 0; }
    get trames() { return this._piste ? this._piste.trames : 0; }
    get reperes() { return this._reperes.map((r) => Object.assign({}, r)); }
    get nombre() { return this._reperes.length; }
    get vide() { return !this._reperes.length; }      // sans repère : la nuée vole sans piste

    at(t, out) { return this._piste ? this._piste.at(t, out) : zeros(out); }
    cumul(t) { return this._piste ? this._piste.cumul(t) : 0; }
    get evenements() { return this._piste ? this._piste.evenements : { ruptures: [], attaques: [] }; }

    enregistrer(t, niveau) {
      t = +t;
      niveau = Math.round(+niveau);
      if (!isFinite(t) || !(niveau >= 1 && niveau <= 5)) throw new Error('Conduite : enregistrer(t, niveau 1-5)');
      return this._ajouter({ t: t, niveau: niveau });
    }

    rupture(t) {
      t = +t;
      if (!isFinite(t)) throw new Error('Conduite : rupture(t)');
      return this._ajouter({ t: t, rupture: true });
    }

    // Défait la dernière action (ajout, effacement ou import). Renvoie l'action défaite ou null.
    annuler() {
      const a = this._actions.pop();
      if (!a) return null;
      if (a.type === 'ajout') {
        const i = this._reperes.indexOf(a.repere);
        if (i >= 0) this._reperes.splice(i, 1);
      } else {
        this._reperes = a.avant;
      }
      this._changer();
      return a.type;
    }

    effacer() {
      const n = this._reperes.length;
      if (!n) return 0;
      this._actions.push({ type: 'effacer', avant: this._reperes });
      this._reperes = [];
      this._changer();
      return n;
    }

    // JSON au format piste (lisible par Musique.chargerPiste) + les repères pour importer().
    exporter() {
      const p = this._piste, doc = {
        version: 1, type: 'conduite', source: this.source, titre: this.titre,
        interpretes: this.interpretes, licence: this.licence,
        offset: p ? p.offset : this.offset, fps: this.fps, duree: p ? +p.duree.toFixed(3) : 0,
        anticipation: this.anticipation, anticipationAttaques: this.anticipationAttaques,
        canaux: CANAUX.slice(), donnees: {}, evenements: { ruptures: [], attaques: [] },
        conduite: { cle: this.cle, reaction: this.reaction, rampe: this.rampe, reperes: this.reperes },
      };
      if (p) {
        for (const k of CANAUX) doc.donnees[k] = Array.from(p._c[k], (v) => Math.round(v * 255));
        doc.evenements = {
          ruptures: p._ev.ruptures.map((e) => ({ t: +e.t.toFixed(3), force: e.force })),
          attaques: p._ev.attaques.map((e) => ({ t: +e.t.toFixed(3), force: e.force })),
        };
      }
      return JSON.stringify(doc);
    }

    // Remplace les repères par ceux d'un export (chaîne ou objet). Annulable. Renvoie leur nombre.
    importer(json) {
      const doc = typeof json === 'string' ? JSON.parse(json) : json;
      const src = doc && doc.conduite && Array.isArray(doc.conduite.reperes) ? doc.conduite.reperes
        : (doc && Array.isArray(doc.reperes) ? doc.reperes : null);
      if (!src) throw new Error('Conduite : JSON sans repères de conduite');
      const reperes = [];
      for (const r of src) {
        if (!r || !isFinite(r.t)) continue;
        if (r.rupture) reperes.push({ t: +r.t, rupture: true });
        else if (r.niveau >= 1 && r.niveau <= 5) reperes.push({ t: +r.t, niveau: Math.round(r.niveau) });
      }
      reperes.sort((a, b) => a.t - b.t);
      if (doc.conduite) {
        if (isFinite(doc.conduite.reaction)) this.reaction = +doc.conduite.reaction;
        if (doc.conduite.rampe > 0) this.rampe = +doc.conduite.rampe;
      }
      this._actions.push({ type: 'importer', avant: this._reperes });
      this._reperes = reperes;
      this._changer();
      return reperes.length;
    }

    _ajouter(r) {
      let i = this._reperes.length;
      while (i > 0 && this._reperes[i - 1].t > r.t) i--;
      this._reperes.splice(i, 0, r);
      this._actions.push({ type: 'ajout', repere: r });
      this._changer();
      return Object.assign({}, r);
    }

    _changer() {
      this.revision++;
      this._rendre();
      this._sauver();
      if (typeof this.onchange === 'function') {
        try { this.onchange(this); } catch (e) { /* l'appelant gère ses erreurs */ }
      }
    }

    // Repères → canaux à fps, sur [offset, offset + duree]. Temps musical estimé = touche − réaction.
    _rendre() {
      const R = this._reperes, fps = this.fps;
      if (!R.length) {
        this._piste = null;
        return;
      }
      const tMin = R[0].t, tMax = R[R.length - 1].t;
      const offset = this._offsetFixe !== null ? this._offsetFixe : Math.max(0, Math.floor(tMin - 5));
      const duree = this._dureeFixe !== null ? this._dureeFixe : Math.ceil(tMax - offset + 20);
      const n = Math.max(2, Math.floor(duree * fps) + 1);
      const ch = {};
      for (const k of CANAUX) ch[k] = new Float32Array(n);

      // Énergie : rampes douces (smoothstep) centrées sur le temps musical − anticipation ;
      // un nouveau niveau part de la valeur courante. Avant le premier niveau : ce niveau.
      const niv = R.filter((r) => r.niveau);
      const rampe = Math.max(0.05, this.rampe);
      const segs = niv.map((r) => ({ debut: r.t - this.reaction - this.anticipation - rampe / 2, cible: NIVEAUX[r.niveau], niveau: r.niveau }));
      let s = -1, depart = segs.length ? segs[0].cible : 0, valeur = depart;
      for (let i = 0; i < n; i++) {
        const t = offset + i / fps;
        while (s + 1 < segs.length && t >= segs[s + 1].debut) {
          s++;
          depart = valeur;              // valeur courante (éventuellement au milieu d'une rampe)
        }
        valeur = s < 0 ? depart : depart + (segs[s].cible - depart) * lisse((t - segs[s].debut) / rampe);
        ch.energie[i] = valeur;
      }

      // Attaques : pic à chaque montée de niveau (montée d'un cran → 0,25 ; de 4 crans → 1).
      const attaques = [];
      for (let j = 0; j < niv.length; j++) {
        const avant = j ? niv[j - 1].niveau : 0, d = niv[j].niveau - avant;
        if (d > 0) attaques.push({ t: niv[j].t - this.reaction - this.anticipationAttaques - offset, force: Math.min(1, d / 4) });
      }
      const ruptures = R.filter((r) => r.rupture)
        .map((r) => ({ t: r.t - this.reaction - this.anticipation - offset, force: 0.8 }));
      for (const a of attaques) {
        const i0 = Math.max(0, Math.floor((a.t - 0.04) * fps)), i1 = Math.min(n - 1, Math.ceil((a.t + 1.5) * fps));
        for (let i = i0; i <= i1; i++) {
          const dt = i / fps - a.t;
          const v = dt < 0 ? a.force * (1 + dt / 0.04) : a.force * Math.exp(-dt / 0.35);
          if (v > ch.attaque[i]) ch.attaque[i] = clamp01(v);
        }
      }
      // Nouveauté : bosse autour de chaque rupture (σ = 1,2 s).
      for (const r of ruptures) {
        const i0 = Math.max(0, Math.floor((r.t - 4) * fps)), i1 = Math.min(n - 1, Math.ceil((r.t + 4) * fps));
        for (let i = i0; i <= i1; i++) {
          const dt = i / fps - r.t, v = r.force * Math.exp(-0.5 * (dt / 1.2) * (dt / 1.2));
          if (v > ch.nouveaute[i]) ch.nouveaute[i] = v;
        }
      }
      // Pas d'analyse spectrale : brillance et bandes suivent l'énergie (à affiner à la main).
      for (let i = 0; i < n; i++) {
        const e = ch.energie[i];
        ch.brillance[i] = 0.15 + 0.7 * e;
        ch.graves[i] = Math.pow(e, 0.8);
        ch.mediums[i] = e;
        ch.aigus[i] = Math.pow(e, 1.3);
      }
      const donnees = {};
      for (const k of CANAUX) donnees[k] = Array.from(ch[k], (v) => Math.round(clamp01(v) * 255));
      this._piste = new Piste({
        version: 1, type: 'conduite', titre: this.titre, source: this.source, offset: offset, fps: fps,
        duree: (n - 1) / fps, anticipation: this.anticipation, anticipationAttaques: this.anticipationAttaques,
        canaux: CANAUX, donnees: donnees, evenements: { ruptures: ruptures, attaques: attaques },
      });
    }

    _charger() {
      if (!this.cle) return;
      try {
        const brut = window.localStorage.getItem(this.cle);
        this.stockage = 'local';
        if (!brut) return;
        const doc = JSON.parse(brut);
        if (doc && Array.isArray(doc.reperes)) {
          this._reperes = doc.reperes.filter((r) => r && isFinite(r.t) && (r.rupture || (r.niveau >= 1 && r.niveau <= 5)))
            .map((r) => (r.rupture ? { t: +r.t, rupture: true } : { t: +r.t, niveau: Math.round(r.niveau) }))
            .sort((a, b) => a.t - b.t);
        }
      } catch (e) {
        this.stockage = 'aucun';         // navigation privée, stockage bloqué ou JSON abîmé
      }
    }

    _sauver() {
      if (!this.cle) return;
      try {
        window.localStorage.setItem(this.cle, JSON.stringify({ version: 1, reperes: this._reperes }));
        this.stockage = 'local';
      } catch (e) {
        this.stockage = 'aucun';
      }
    }
  }

  /* ---------- Analyse en temps réel (comparaison) ---------- */

  const graphes = new WeakMap();      // un MediaElementAudioSourceNode par élément, pour toujours

  // Bandes log de 55 Hz à 14 kHz, comme l'outil hors ligne (flux spectral rectifié).
  function bandesLog(nb, fmin, fmax) {
    const r = Math.pow(fmax / fmin, 1 / (nb - 1)), out = [];
    for (let k = 0; k < nb; k++) out.push(fmin * Math.pow(r, k));
    return out;
  }

  class Direct {
    constructor(media, ctx, analyseur, gain) {
      this.media = media;
      this.contexte = ctx;
      this.analyseur = analyseur;
      this.gain = gain;
      const N = analyseur.fftSize, B = analyseur.frequencyBinCount, sr = ctx.sampleRate;
      this._temps = new Float32Array(N);
      this._spec = new Float32Array(B);
      // Regroupement des cases FFT : 18 bandes log (flux), trois bandes larges, centroïde.
      const centres = bandesLog(18, 55, 14000), r = Math.pow(14000 / 55, 1 / 17);
      this._bande = new Int8Array(B).fill(-1);
      this._large = new Int8Array(B).fill(-1);
      this._f = new Float32Array(B);
      for (let k = 1; k < B; k++) {
        const f = k * sr / N;
        this._f[k] = f;
        for (let b = 0; b < centres.length; b++) {
          if (f >= centres[b] / Math.sqrt(r) && f < centres[b] * Math.sqrt(r)) { this._bande[k] = b; break; }
        }
        if (f >= 20 && f < 16000) this._large[k] = f < 250 ? 0 : f < 2000 ? 1 : 2;
      }
      this._Lprec = null;
      this._ref = { db: null, flux: 0.02, fluxMoy: 0, cMin: null, cMax: null, bandes: [null, null, null] };
      this._lent = null;
      this._rapide = null;
      this._dernier = null;      // { tCtx, valeurs }
      this._wPrec = null;
      this._histo = [];          // [temps média, énergie, attaque mesurées] pour mesurerRetard
      this.retard = (N / 2 + 128) / sr;                                   // centre de la fenêtre + un quantum
      this.latenceSortie = (ctx.outputLatency || ctx.baseLatency || 0);
    }

    get etat() { return this.contexte.state; }

    // 'coupe' : élément muet → l'analyseur reçoit des zéros (Chrome, Firefox) ;
    // 'inaudible' : élément non muet, sortie à gain 0 ; 'audible' : gain 1.
    get mode() {
      const m = this.media;
      if (m.muted || m.volume === 0) return 'coupe';
      return this.gain.gain.value === 0 ? 'inaudible' : 'audible';
    }

    regler(mode) {
      const m = this.media, g = this.gain.gain, t = this.contexte.currentTime;
      if (mode === 'coupe') {
        m.muted = true;
      } else if (mode === 'inaudible') {
        g.cancelScheduledValues(t); g.setValueAtTime(0, t); g.value = 0;
        m.muted = false;
      } else if (mode === 'audible') {
        g.cancelScheduledValues(t); g.setValueAtTime(1, t); g.value = 1;
        m.muted = false;
      } else {
        throw new Error('Direct : mode inconnu ' + mode);
      }
      if (this.contexte.state === 'suspended') this.contexte.resume();
      return this.mode;
    }

    // Mêmes clés que piste.at(), mesurées sur le son qui sort de l'élément, avec contrôle
    // automatique de gain. tMedia (facultatif) alimente la mesure du retard.
    valeurs(tMedia) {
      const ctx = this.contexte, tc = ctx.currentTime;
      if (this._dernier && this._dernier.tCtx === tc) return this._dernier.valeurs;   // même bloc audio
      const out = zeros({});
      const w = performance.now() / 1000;
      const dt = this._wPrec === null ? 1 / 60 : Math.min(0.25, Math.max(1e-3, w - this._wPrec));
      this._wPrec = w;
      if (ctx.state !== 'running') { this._dernier = { tCtx: tc, valeurs: out }; return out; }

      const an = this.analyseur, x = this._temps, S = this._spec;
      an.getFloatTimeDomainData(x);
      an.getFloatFrequencyData(S);
      let s2 = 0;
      for (let i = 0; i < x.length; i++) s2 += x[i] * x[i];
      const rms = Math.sqrt(s2 / x.length);
      if (!(rms > 1e-6)) {                                  // silence numérique : zéros, rien n'est appris
        this._Lprec = null;
        this._dernier = { tCtx: tc, valeurs: out };
        if (isFinite(tMedia)) this._noter(tMedia, 0, 0);
        return out;
      }
      const dbRms = 20 * Math.log10(rms);

      // Spectre : puissances par bande, centroïde (amplitudes), flux rectifié (dB, plancher −55 dB).
      const P = new Float64Array(18), Pl = [0, 0, 0];
      let num = 0, den = 0;
      for (let k = 1; k < S.length; k++) {
        const d = S[k];
        if (!(d > -200)) continue;
        const a = Math.pow(10, d / 20), p = a * a;
        const b = this._bande[k];
        if (b >= 0) P[b] += p;
        const l = this._large[k];
        if (l >= 0) { Pl[l] += p; num += this._f[k] * a; den += a; }
      }
      let pmax = 0;
      for (let b = 0; b < 18; b++) if (P[b] > pmax) pmax = P[b];
      const plancher = pmax * 3.2e-6 + 1e-20;
      const L = new Float64Array(18);
      for (let b = 0; b < 18; b++) L[b] = 10 * Math.log10(P[b] + plancher);
      let flux = 0;
      if (this._Lprec) for (let b = 0; b < 18; b++) { const d = L[b] - this._Lprec[b]; if (d > 0) flux += d; }
      this._Lprec = L;
      flux /= 18;

      // Contrôle de gain : crêtes qui retombent lentement (0,5 dB/s), comme l'échelle par pièce.
      const ref = this._ref, chute = 0.5 * dt;
      ref.db = ref.db === null ? dbRms : Math.max(dbRms, ref.db - chute);
      // énergie : sones relatifs entre crête − 40 dB et crête (comme le canal pré-analysé)
      const nb = Math.pow(2, -4);
      out.energie = clamp01((Math.pow(2, (dbRms - ref.db) / 10) - nb) / (1 - nb));
      for (let l = 0; l < 3; l++) {
        const dbl = 10 * Math.log10(Pl[l] + 1e-20);
        ref.bandes[l] = ref.bandes[l] === null ? dbl : Math.max(dbl, ref.bandes[l] - chute);
        out[CANAUX[3 + l]] = clamp01(1 + (dbl - ref.bandes[l]) / 50);
      }
      ref.fluxMoy += (flux - ref.fluxMoy) * (1 - Math.exp(-dt / 2));
      ref.flux = Math.max(flux, ref.flux * Math.exp(-dt / 20), 1e-3);
      out.attaque = clamp01((flux - ref.fluxMoy) / Math.max(1e-3, ref.flux - ref.fluxMoy));
      if (den > 0) {
        const lc = Math.log2(Math.max(20, num / den));
        ref.cMin = ref.cMin === null ? lc - 0.5 : Math.min(lc, ref.cMin + 0.02 * dt);
        ref.cMax = ref.cMax === null ? lc + 0.5 : Math.max(lc, ref.cMax - 0.02 * dt);
        out.brillance = clamp01((lc - ref.cMin) / Math.max(0.5, ref.cMax - ref.cMin));
      }
      // nouveauté : écart entre moyennes rapide (1 s) et lente (6 s) de l'énergie et de la brillance
      const vec = [out.energie, out.brillance, out.graves, out.aigus];
      if (!this._lent) { this._lent = vec.slice(); this._rapide = vec.slice(); }
      let d2 = 0;
      for (let j = 0; j < vec.length; j++) {
        this._rapide[j] += (vec[j] - this._rapide[j]) * (1 - Math.exp(-dt / 1));
        this._lent[j] += (vec[j] - this._lent[j]) * (1 - Math.exp(-dt / 6));
        d2 += (this._rapide[j] - this._lent[j]) ** 2;
      }
      out.nouveaute = clamp01(Math.sqrt(d2 / vec.length) * 4);

      this._dernier = { tCtx: tc, valeurs: out };
      if (isFinite(tMedia)) this._noter(tMedia, out.energie, out.attaque);
      return out;
    }

    _noter(t, e, a) {
      const h = this._histo;
      if (h.length && t < h[h.length - 1][0] - 0.05) h.length = 0;     // saut arrière (seek, boucle)
      h.push([t, e, a]);
      if (h.length > 1800) h.splice(0, h.length - 1800);              // ~30 s à 60 i/s
    }

    // Retard mesuré : décalage r qui maximise la corrélation entre la force d'attaque mesurée au
    // temps média t et les attaques pré-analysées de la musique jouée en t − r (noyaux de 20 ms
    // à leur temps musical, anticipation retirée). Sans attaques, sur l'énergie (pic plus mou).
    mesurerRetard(piste, opts) {
      opts = opts || {};
      const h = this._histo;
      if (!piste || h.length < 240) return null;
      const t0 = h[0][0], t1 = h[h.length - 1][0];
      if (t1 - t0 < 6) return null;
      const lo = opts.min ?? -0.1, hi = opts.max ?? 0.3, pas = opts.pas ?? 0.0025;
      const ev = (piste.evenements && piste.evenements.attaques) || [];
      let ref, col;
      if (ev.length && !opts.energie) {
        const aa = piste.anticipationAttaques || 0;
        const te = ev.map((e) => e.t + aa), fe = ev.map((e) => e.force);
        ref = (tau) => {
          let a = 0, b = te.length;
          while (a < b) { const c = (a + b) >> 1; if (te[c] < tau - 0.08) a = c + 1; else b = c; }
          let s = 0;
          for (let i = a; i < te.length && te[i] <= tau + 0.08; i++) { const d = (tau - te[i]) / 0.02; s += fe[i] * Math.exp(-0.5 * d * d); }
          return s;
        };
        col = 2;
      } else {
        const A = piste.anticipation || 0, tmp = {};
        ref = (tau) => piste.at(tau - A, tmp).energie;
        col = 1;
      }
      const xs = h.map((p) => p[col]), mx = xs.reduce((a, b) => a + b, 0) / xs.length;
      let sx = 0;
      for (const v of xs) sx += (v - mx) * (v - mx);
      if (!(sx > 1e-9)) return null;
      let best = null;
      for (let r = lo; r <= hi + 1e-9; r += pas) {
        const ys = h.map((p) => ref(p[0] - r));
        const my = ys.reduce((a, b) => a + b, 0) / ys.length;
        let sxy = 0, sy = 0;
        for (let i = 0; i < ys.length; i++) { sxy += (xs[i] - mx) * (ys[i] - my); sy += (ys[i] - my) * (ys[i] - my); }
        const c = sy > 1e-9 ? sxy / Math.sqrt(sx * sy) : 0;
        if (!best || c > best.correlation) best = { retard: +r.toFixed(4), correlation: +c.toFixed(3) };
      }
      best.base = col === 2 ? 'attaques' : 'energie';
      best.echantillons = h.length;
      best.duree = +(t1 - t0).toFixed(2);
      return best;
    }
  }

  // À appeler DANS un gestionnaire de clic ou de touche : le contexte audio ne démarre qu'avec
  // une activation, et un élément branché sur un contexte suspendu devient muet.
  function analyseDirecte(media, opts) {
    if (!media) throw new Error('analyseDirecte : élément média requis');
    let d = graphes.get(media);
    if (d) {
      if (d.contexte.state === 'suspended') d.contexte.resume();
      return d;
    }
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) throw new Error('analyseDirecte : Web Audio indisponible');
    const ctx = new AC({ latencyHint: 'interactive' });
    if (ctx.state === 'suspended') ctx.resume();
    const source = ctx.createMediaElementSource(media);
    const an = ctx.createAnalyser();
    an.fftSize = (opts && opts.fftSize) || 2048;
    an.smoothingTimeConstant = 0;                      // nos propres lissages, pas de retard ajouté
    const gain = ctx.createGain();
    gain.gain.value = 1;
    source.connect(an);
    an.connect(gain);
    gain.connect(ctx.destination);
    d = new Direct(media, ctx, an, gain);
    graphes.set(media, d);
    return d;
  }

  window.Musique = {
    CANAUX: CANAUX.slice(),
    NIVEAUX: NIVEAUX.slice(1),
    chargerPiste: chargerPiste,
    piste: (doc) => new Piste(doc),                   // depuis un objet déjà chargé
    conduite: (opts) => new Conduite(opts),
    analyseDirecte: analyseDirecte,
    directPour: (media) => (media && graphes.get(media)) || null,
    version: 1,
  };
})();
