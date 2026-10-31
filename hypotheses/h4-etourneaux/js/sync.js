/*
 * Sync : horloges de la musique (SPEC.md, § 3.4 et § 6).
 *
 * Un lecteur donne à chaque image le temps musical (temps média, en secondes). Trois sources :
 *   - virtuelle : performance.now(), en boucle sur [début, fin] ; sert au chargement (sans son,
 *     sans consentement) et de repli quand le média ne peut pas jouer ;
 *   - media : currentTime d'un élément hébergé, lu à chaque image à travers un servo monotone ;
 *   - youtube : getCurrentTime() du lecteur intégré, même servo.
 *
 * Servo (media), monotone, selon ce que le navigateur donne :
 *   - currentTime frais à chaque image (Chrome, Safari) : l'horloge avance de ce dont le média a
 *     avancé (borné à deux fois le temps réel) plus une correction vers lui ; un démarrage
 *     hésitant ou un blocage la tiennent au lieu de la laisser filer ;
 *   - paliers de 40-50 ms (Firefox, détectés) : la dernière valeur nouvelle est datée au milieu
 *     de l'intervalle où elle a été publiée, extrapolée, et suivie par la vitesse
 *     (k = 1 + écart/τ, borné à [0, 2]) : l'horloge tient le haut de l'escalier, sans à-coup.
 * Si currentTime ne bouge plus depuis 1,5 intervalle de mise à jour (25 ms dans Chrome à 60 i/s,
 * ~65 ms sur les paliers de Firefox, 50 ms tant qu'il n'est pas mesuré) alors que le média joue
 * ou attend, la référence se fige à la dernière valeur vue. Recalage franc sur seeked, sur nos
 * propres sauts, en retard de plus de 0,3 s (média audible) ou en avance de plus de 2 s ; en
 * deçà, l'horloge se tient (pas de recul). YouTube : recalage au-delà de 250 ms dans les deux sens.
 *
 * Écouter (dans un clic ou une touche) : depuis l'horloge virtuelle, le média est placé à la
 * position de la nuée puis lancé avec le son ; l'horloge se tient le temps que le son démarre
 * (100 à 300 ms mesurés dans Chrome) puis repart avec lui, sans saut : la musique arrive sur la
 * nuée sans que l'image la précède. En lecture muette, on rend simplement le son.
 * Fin de l'écoute : relacher() arrête le média et rend la main à l'horloge virtuelle, qui
 * continue depuis la même position ; pause() fige l'horloge (le média et la nuée s'arrêtent).
 */
(function () {
  'use strict';

  const TAU = 0.2;          // constante de temps du servo (s)
  const SAUT = 0.3;         // horloge en retard de plus de 0,3 s : recalage franc
  const RECUL = 2;          // en avance de plus de 2 s : recalage (en deçà, l'horloge se tient)
  const GRACE = 0;          // à l'écoute, l'horloge se tient dès le clic jusqu'à ce que le média avance
  const GEL = 0.05;         // horizon d'extrapolation tant que le pas de currentTime n'est pas mesuré
  const BLOCAGE = 6;        // média qui n'avance plus depuis 6 s → horloge virtuelle
  const PLAYING = 1;

  const maintenant = () => performance.now() / 1000;
  const mediane = (a) => {
    if (!a.length) return NaN;
    const s = a.slice().sort((x, y) => x - y);
    return s[s.length >> 1];
  };
  const pousser = (a, v, n) => { a.push(v); if (a.length > n) a.shift(); };

  /* ---------- Base commune : horloge virtuelle, servo, boucle ---------- */

  class Base {
    constructor(boucle, debut, marche) {
      this.boucle = (boucle && boucle.length === 2 && boucle[1] > boucle[0]) ? [+boucle[0], +boucle[1]] : null;
      const t0 = isFinite(debut) ? +debut : (this.boucle ? this.boucle[0] : 0);
      const w = maintenant();
      this._v = { t: t0, w: w, marche: marche };   // horloge virtuelle : t(w) = v.t + (w − v.w)
      this._t = t0;                                // horloge affichée (dernière valeur rendue)
      this._w = w;
      this._src = 'virtuelle';
      this._son = 'coupe';
      this._pauseVoulue = false;
      this._saut = SAUT;
      this._recul = RECUL;
      this.etat = '';
    }

    get source() { return this._src; }
    get modeSon() { return this._son; }

    _virtuelle(w) {
      const v = this._v;
      let t = v.marche ? v.t + (w - v.w) : v.t;
      if (this.boucle && t >= this.boucle[1]) {
        const a = this.boucle[0], L = this.boucle[1] - a;
        t = a + ((t - a) % L);
        v.t = t; v.w = w;
      }
      return t;
    }

    // Un pas de servo vers la référence ref (qui avance si avance est vrai) ; jamais de recul.
    // L'écart se mesure à la position prédite (t + dt·rate), sinon l'équilibre est en avance
    // d'une image.
    _servo(t, ref, dt, rate, avance) {
      const err = ref - (avance ? t + dt * rate : t);
      if (err > this._saut || err < -this._recul) return ref;   // en avance de moins de 2 s : on tient
      if (avance) return t + dt * rate * Math.min(2, Math.max(0, 1 + err / TAU));
      if (err > 0) return t + err * (1 - Math.exp(-dt / 0.1));     // rejoindre par en dessous
      return t;                                                      // référence figée : on tient
    }

    _repliVirtuel(raison) {
      const w = maintenant();
      this._v = { t: this._t, w: w, marche: !this._pauseVoulue };
      this._src = 'virtuelle';
      this.etat = 'virtuelle : ' + raison;
    }
  }

  /* ---------- Lecteur d'un média hébergé ---------- */

  class Lecteur extends Base {
    constructor(opts) {
      opts = opts || {};
      super(opts.boucle, opts.debut, true);
      this.media = opts.media || null;
      this.autoplayMuet = !!opts.autoplayMuet;
      this._ct = NaN;            // dernière valeur de currentTime vue
      this._ctImage = NaN;       // currentTime lu à l'image précédente
      this._wCt = null;          // instant où elle est apparue
      this._wLu = null;          // instant de la lecture précédente
      this._ref = null;          // { t, w } : position estimée du média à l'instant w
      this._intervalles = [];    // écarts entre valeurs nouvelles de currentTime (palier)
      this._images = [];         // écarts entre lectures (images)
      this._gel = false;
      this._demarrage = false;
      this._grace = 0;
      this._seek = null;         // { cible, w } : notre dernier saut (pour reconnaître son 'seeking')
      this._releve = null;       // relève silencieuse en attente (lecture muette, reprise)
      this._wDemasque = -1;
      this._ecoute = false;
      this._ecouteurs = [];
      this.etat = this.media ? 'virtuelle : en attente d\'écoute' : 'virtuelle : pas de média';
      if (this.media) {
        this._brancher();
        if (this.autoplayMuet) this._lectureMuette();
      }
    }

    get enLecture() {
      if (this._src === 'media') return !this.media.paused && !this.media.ended;
      return this._v.marche;
    }

    _on(cible, type, fn) {
      cible.addEventListener(type, fn);
      this._ecouteurs.push([cible, type, fn]);
    }

    _brancher() {
      const m = this.media;
      this._on(m, 'seeking', () => {
        const s = this._seek;
        if (s && Math.abs(m.currentTime - s.cible) < 0.05 && maintenant() - s.w < 3) return;   // le nôtre
        if (this._src === 'media') this._recaler(m.currentTime, !m.paused);                    // contrôles natifs
      });
      this._on(m, 'seeked', () => {
        const s = this._seek;
        this._seek = null;
        if (this._src !== 'media') return;
        if (s && Math.abs(m.currentTime - s.cible) < 0.05 && this._demarrage) {
          // notre saut, déjà ancré : on confirme la référence sans faire reculer l'horloge
          const w = maintenant();
          this._ref = { t: m.currentTime, w: w };
          this._ct = this._ctImage = m.currentTime;
          this._wCt = w;
          return;
        }
        this._recaler(m.currentTime, !m.paused);
      });
      this._on(m, 'waiting', () => {
        if (this._src !== 'media') return;
        this._gel = true;
        this._majEtat();
      });
      const depart = () => {
        this._wCt = maintenant();          // le délai de blocage repart de la reprise
        if (this._src === 'media') { this._demarrage = true; this._gel = false; }
        this._majEtat();
      };
      this._on(m, 'play', depart);
      this._on(m, 'playing', depart);
      this._on(m, 'pause', () => this._surPause());
      this._on(m, 'ratechange', () => {
        if (this._ref) { const w = maintenant(); this._ref = { t: this._refA(w), w: w }; }
      });
      this._on(m, 'ended', () => {
        if (this._src !== 'media') return;
        if (this.boucle) {
          this._allerMedia(this.boucle[0]);
          this._jouer();
        } else {
          this._majEtat('fin du média');
        }
      });
      this._on(m, 'error', () => {
        if (this._src === 'media' || this._releve) this._repliVirtuel('erreur média' + (m.error ? ' (code ' + m.error.code + ')' : ''));
      });
      const relance = () => { if (!document.hidden) this._relancerMuette(); };
      this._on(document, 'visibilitychange', relance);
      this._on(window, 'pageshow', relance);
    }

    detruire() {
      for (const [c, type, fn] of this._ecouteurs) c.removeEventListener(type, fn);
      this._ecouteurs = [];
    }

    /* -- horloge -- */

    temps() {
      const w = maintenant();
      const dt = Math.max(0, w - this._w);
      let t;
      if (this._src === 'media') {
        t = this._horlogeMedia(w, dt);
      } else {
        t = this._virtuelle(w);
        if (this._releve) t = this._surveillerReleve(w, t);
      }
      this._t = t;
      this._w = w;
      return t;
    }

    _refA(w) {
      const m = this.media;
      if (!this._ref) return m.currentTime;
      const avance = !m.paused && !m.ended && !this._gel && !this._demarrage && !m.seeking;
      return avance ? this._ref.t + (w - this._ref.w) * (m.playbackRate || 1) : this._ref.t;
    }

    _escalier() {
      if (this._intervalles.length < 8 || this._images.length < 8) return false;
      return mediane(this._intervalles) > Math.max(0.025, 1.6 * mediane(this._images));
    }

    _horlogeMedia(w, dt) {
      const m = this.media;
      const ct = m.currentTime;
      const rate = m.playbackRate || 1;
      const joue = !m.paused && !m.ended;

      // 1. Observation : seule une valeur nouvelle de currentTime renseigne.
      if (ct !== this._ct) {
        const avance = ct > this._ct;
        if (joue && avance && !this._demarrage && !this._gel && this._wCt !== null) {
          const d = w - this._wCt;
          if (d > 0.002 && d < 0.5) pousser(this._intervalles, d, 32);
        }
        let obs = ct;
        if (joue && this._wLu !== null && this._escalier()) obs += 0.5 * (w - this._wLu) * rate;
        this._ref = { t: obs, w: w };
        this._ct = ct;
        this._wCt = w;
        if (joue && avance && !m.seeking) {
          this._gel = false;
          if (this._demarrage) { this._demarrage = false; this._grace = 0; this._majEtat(); }
        }
      }
      // Horizon d'extrapolation : 1,5 intervalle entre valeurs nouvelles (25 ms dans Chrome à
      // 60 i/s, ~65 ms sur les paliers de Firefox ; 50 ms tant qu'il n'est pas mesuré). Au-delà,
      // currentTime est figé (démarrage hésitant, attente, blocage) : la référence s'arrête.
      const H = this._intervalles.length >= 8 ? Math.max(0.012, 1.5 * mediane(this._intervalles)) : GEL;
      if (ct === this._ct && joue && !this._demarrage && !m.seeking && this._wCt !== null && w - this._wCt > H) {
        this._gel = true;
      }
      if (this._wLu !== null) {
        const f = w - this._wLu;
        if (f > 0.002 && f < 0.1) pousser(this._images, f, 32);
      }
      this._wLu = w;

      // 2. Média bloqué trop longtemps : repli sur l'horloge virtuelle.
      if (joue && !m.seeking && this._wCt !== null && w - this._wCt > BLOCAGE) {
        this._repliVirtuel('média bloqué depuis ' + BLOCAGE + ' s');
        this._releve = { ct: undefined };
        return this._t;
      }

      // 3. Servo.
      let t;
      const avance = joue && !this._gel && !this._demarrage && !m.seeking;
      const dObs = isFinite(this._ctImage) ? ct - this._ctImage : 0;
      this._ctImage = ct;
      if (this._demarrage && w < this._grace) {
        t = this._t + dt;                              // l'horloge continue en attendant le média
      } else if (!this._ref || !avance) {
        // figée (pause, démarrage, attente, blocage) : la référence reste à la dernière valeur vue ;
        // l'horloge la rejoint par en dessous ou se tient, jamais en avance sur le média
        t = this._servo(this._t, this._ref ? this._ref.t : ct, dt, rate, false);
      } else if (this._escalier()) {
        // paliers (Firefox) : extrapolation de la dernière valeur nouvelle, datée au milieu
        t = this._servo(this._t, this._ref.t + (w - this._ref.w) * rate, dt, rate, true);
      } else {
        // currentTime frais à chaque image (Chrome, Safari) : l'horloge avance de ce dont le
        // média a avancé (borné à deux fois le temps réel), plus une correction vers lui. Un
        // démarrage hésitant ne la fait donc jamais passer devant. En retard de plus de 0,3 s
        // sur un média audible : recalage ; muet : rattrapage à trois fois le temps réel au plus.
        const pred = this._t + Math.min(Math.max(0, dObs), 2 * dt * rate);
        const err = ct - pred;
        if (err < -this._recul || (err > this._saut && !m.muted)) t = ct;
        else t = Math.min(this._t + 3 * dt * rate, Math.max(this._t, pred + err * (1 - Math.exp(-dt / (err > 0 ? 0.08 : TAU)))));
      }

      // 4. Boucle.
      if (this.boucle && t >= this.boucle[1]) {
        this._allerMedia(this.boucle[0]);
        t = this.boucle[0];
      }
      return t;
    }

    // Relève silencieuse (lecture muette, reprise après blocage) : on reste sur l'horloge
    // virtuelle jusqu'à ce que le média avance, puis l'horloge passe au média sans sauter (elle
    // se tient s'il est derrière, le rattrape s'il est devant). Le média n'est pas replacé : une
    // vidéo de fond relancée se verrait.
    _surveillerReleve(w, tv) {
      const m = this.media, r = this._releve;
      if (!m || m.paused || m.ended || m.seeking) return tv;
      const ct = m.currentTime;
      if (r.ct === undefined || ct <= r.ct) { if (r.ct === undefined) r.ct = ct; return tv; }
      this._releve = null;
      this._src = 'media';
      this._ref = { t: ct, w: w };
      this._ct = this._ctImage = ct;
      this._wCt = w;
      this._gel = false;
      this._demarrage = false;
      this._majEtat();
      return tv;
    }

    _recaler(t, attendre) {
      const w = maintenant();
      this._t = t;
      this._w = w;
      this._ref = { t: t, w: w };
      this._ct = this._ctImage = this.media.currentTime;
      this._wCt = w;
      this._gel = false;
      this._demarrage = !!attendre;
      this._grace = 0;
    }

    _seekInterne(t) {
      this._seek = { cible: t, w: maintenant() };
      try { this.media.currentTime = t; } catch (e) { /* HAVE_NOTHING : position de départ */ }
    }

    _allerMedia(t) {
      this._seekInterne(t);
      this._recaler(t, !this.media.paused);
    }

    _jouer() {
      let p;
      try { p = this.media.play(); } catch (e) { p = Promise.reject(e); }
      return Promise.resolve(p);
    }

    /* -- commandes -- */

    // À appeler dans un gestionnaire de clic ou de touche (activation utilisateur).
    ecouter() {
      const m = this.media;
      this._ecoute = true;
      this._pauseVoulue = false;
      if (!m) {
        this.etat = 'virtuelle : pas de média à écouter';
        return Promise.resolve('virtuelle');
      }
      if (this._src === 'media' && !m.paused && !m.ended) {    // lecture muette en cours : le son
        this.son('audible');
        return Promise.resolve('son');
      }
      // Rejoindre la nuée : le média repart exactement à la position de l'horloge.
      const t = this.temps(), w = maintenant();
      this._releve = null;
      if (Math.abs(m.currentTime - t) > 0.005) this._seekInterne(t);
      this._src = 'media';
      this._ref = { t: t, w: w };
      this._ct = this._ctImage = m.currentTime;
      this._wCt = w;
      this._gel = false;
      this._demarrage = true;
      this._grace = w + GRACE;
      this.son('audible');
      this._majEtat('démarrage');
      return this._jouer().then(() => { this._majEtat(); return 'media'; },
        (e) => { this._repliVirtuel('lecture refusée (' + (e && e.name) + ')'); return 'virtuelle'; });
    }

    // Reprise sans changer le son (muet si le son est coupé).
    lire() {
      this._pauseVoulue = false;
      if (this.media && this._src === 'media') {
        return this._jouer().then(() => 'media',
          (e) => { this._repliVirtuel('lecture refusée (' + (e && e.name) + ')'); return 'virtuelle'; });
      }
      this._v = { t: this.temps(), w: maintenant(), marche: true };
      this._majEtat();
      return Promise.resolve('virtuelle');
    }

    pause() {
      this._pauseVoulue = true;
      const t = this.temps();
      this._v = { t: t, w: maintenant(), marche: false };
      if (this.media && this._src === 'media') this.media.pause();
      this._majEtat();
    }

    // Fin de l'écoute sans arrêter la nuée : le média s'arrête et l'horloge virtuelle repart de
    // la position courante, sans saut (la nuée continue de « jouer » l'extrait en silence) ;
    // Écouter reprendra le média à la position de l'horloge.
    relacher() {
      const t = this.temps();
      this._ecoute = false;
      this._releve = null;
      this._pauseVoulue = false;
      this._src = 'virtuelle';                 // avant pause() : l'événement 'pause' n'est plus le nôtre
      if (this.media && !this.media.paused) this.media.pause();
      this._v = { t: t, w: maintenant(), marche: true };
      this.etat = 'virtuelle : écoute arrêtée';
      return t;
    }

    aller(t) {
      t = +t;
      if (!isFinite(t)) return this._t;
      if (this.boucle) t = Math.min(this.boucle[1] - 1e-3, Math.max(this.boucle[0], t));
      const w = maintenant();
      if (this.media && this._src === 'media') {
        this._allerMedia(t);
      } else {
        this._v = { t: t, w: w, marche: this._v.marche };
        this._t = t;
        this._w = w;
      }
      return t;
    }

    // 'coupe' : élément muet ; 'inaudible' : non muet mais sortie à gain 0 (seulement avec le
    // graphe de Musique.analyseDirecte, sinon on coupe et l'état le dit) ; 'audible'.
    son(mode) {
      const m = this.media;
      if (!m) { this.etat = 'virtuelle : pas de média (son sans objet)'; return 'coupe'; }
      const direct = window.Musique && window.Musique.directPour ? window.Musique.directPour(m) : null;
      let effectif = mode, note = '';
      if (mode !== 'coupe' && mode !== 'inaudible' && mode !== 'audible') throw new Error('son : mode inconnu ' + mode);
      if (mode === 'inaudible' && !direct) {
        effectif = 'coupe';
        note = 'inaudible demande le graphe d\'analyse (Musique.analyseDirecte) : son coupé';
      }
      if (effectif !== 'coupe') {
        const ua = navigator.userActivation;
        this._wDemasque = ua && ua.isActive ? -1 : maintenant();     // démuté hors d'un geste ?
        if (m.volume === 0) m.volume = 1;
      }
      if (direct) direct.regler(effectif); else m.muted = (effectif === 'coupe');
      this._son = effectif;
      this._majEtat(note);
      return effectif;
    }

    // Horloge − temps brut du média (ms), pour le HUD ; null sur l'horloge virtuelle.
    ecartMs() {
      if (this._src !== 'media' || !this.media) return null;
      return (this._t - this.media.currentTime) * 1000;
    }

    diagnostic() {
      return {
        source: this._src, etat: this.etat, gel: this._gel, demarrage: this._demarrage,
        escalier: this._escalier(), palierMs: +(mediane(this._intervalles) * 1000).toFixed(1),
        imageMs: +(mediane(this._images) * 1000).toFixed(1), son: this._son, releve: !!this._releve,
      };
    }

    /* -- lecture muette, pauses imposées -- */

    _lectureMuette() {
      const m = this.media;
      if (!m || this._ecoute) return;
      m.muted = true;
      m.defaultMuted = true;
      m.playsInline = true;
      m.setAttribute('playsinline', '');
      this._son = 'coupe';
      const t = this.temps();
      if (Math.abs(m.currentTime - t) > 0.005) this._seekInterne(t);
      this._releve = { ct: undefined };
      this._jouer().then(() => {
        if (this._src === 'virtuelle') this.etat = 'virtuelle : lecture muette lancée, relève en cours';
      }, (e) => {
        this._releve = null;
        const balise = m.tagName === 'AUDIO' ? ' ; Chrome n\'accepte la lecture muette sans geste que pour <video>' : '';
        this._repliVirtuel('lecture muette refusée (' + (e && e.name) + ')' + balise);
      });
    }

    _relancerMuette() {
      const m = this.media;
      if (!m || !this.autoplayMuet || this._ecoute || this._pauseVoulue) return;
      if (this._src === 'virtuelle' && m.paused) this._lectureMuette();
    }

    _surPause() {
      const m = this.media;
      if (this._src !== 'media') return;
      // Les navigateurs mettent en pause un élément démuté sans activation : retour en muet.
      if (!this._pauseVoulue && !m.ended && !m.muted && this._wDemasque > 0 && maintenant() - this._wDemasque < 1.5) {
        m.muted = true;
        this._son = 'coupe';
        this._jouer().then(() => this._majEtat('son refusé sans geste : retour en lecture muette'),
          (e) => this._repliVirtuel('lecture refusée (' + (e && e.name) + ')'));
        return;
      }
      this._majEtat();
    }

    _majEtat(note) {
      if (this._src !== 'media') return;
      const m = this.media;
      let s;
      if (this._demarrage && !m.paused) s = 'média : démarrage (horloge tenue)';
      else if (m.paused) s = m.ended ? 'média : terminé' : 'média : en pause';
      else if (this._gel) s = 'média : attente de données (horloge tenue)';
      else s = 'média : lecture ' + (this._son === 'audible' ? 'avec son' : this._son === 'inaudible' ? 'inaudible (gain 0)' : 'muette');
      this.etat = note ? s + ' ; ' + note : s;
    }
  }

  /* ---------- Lecteur YouTube ---------- */

  const ERREURS_YT = {
    2: 'paramètre invalide', 5: 'erreur du lecteur HTML5', 100: 'vidéo introuvable ou privée',
    101: 'intégration refusée par le propriétaire', 150: 'intégration refusée par le propriétaire',
    153: 'référent HTTP manquant',
  };

  class LecteurYouTube extends Base {
    constructor(opts) {
      // L'horloge virtuelle attend le lecteur (rien ne joue encore) ; elle ne part qu'en repli.
      super([opts.debut, opts.fin], opts.debut, false);
      this._saut = this._recul = 0.25;   // recalage au-delà de 250 ms, dans les deux sens
      this.videoId = opts.videoId;
      this.player = null;
      this._etatYT = -1;
      this._rate = 1;
      this._fige = null;          // { cible, w } : notre seekTo, en attente de confirmation
      this._ecouterDemande = false;
      this._minuteur = null;
      this._wPret = null;
      this.etat = 'virtuelle : lecteur YouTube en chargement';
    }

    get enLecture() {
      return this._src === 'youtube' ? this._etatYT === PLAYING : this._v.marche;
    }

    _attacher(player) {
      this.player = player;
      this._wPret = maintenant();
      this._surEtat(player.getPlayerState());
      // Garde-fou : onAutoplayBlocked n'arrive pas toujours (constaté dans Chrome 156).
      this._minuteur = setInterval(() => this._surveiller(), 250);
      if (this._ecouterDemande) { this._ecouterDemande = false; this.ecouter(); }
    }

    _surveiller() {
      const p = this.player;
      if (!p || !p.getPlayerState) return;
      if (this._src !== 'youtube' && this._etatYT !== PLAYING && !this._pauseVoulue
          && maintenant() - this._wPret > 8 && this._v.marche === false) {
        this._repliVirtuel('lecture automatique bloquée (rien après 8 s)');
      }
      // Boucle même quand rAF est suspendu (onglet caché).
      if (this._src === 'youtube' && this._etatYT === PLAYING && !this._fige && p.getCurrentTime() >= this.boucle[1]) {
        this.aller(this.boucle[0]);
      }
    }

    _surEtat(st) {
      const p = this.player, w = maintenant();
      this._etatYT = st;
      if (!p) return;
      if (st === PLAYING) {
        this._rate = p.getPlaybackRate ? p.getPlaybackRate() || 1 : 1;
        if (this._src !== 'youtube') {
          // relève : l'horloge rejoint le lecteur (servo si l'écart est petit, recalage sinon)
          this._src = 'youtube';
        }
      } else if (st === 0 && this._src === 'youtube') {           // ENDED : on boucle
        this.aller(this.boucle[0]);
        p.playVideo();
      }
      this._wEtat = w;
      this._majEtat();
    }

    temps() {
      const w = maintenant(), dt = Math.max(0, w - this._w);
      let t;
      const p = this.player;
      if (this._src === 'youtube' && p && p.getCurrentTime) {
        let obs = p.getCurrentTime();
        if (this._fige) {
          // notre seekTo : on tient la cible jusqu'à ce que le lecteur la confirme
          if ((Math.abs(obs - this._fige.cible) < 0.5 && this._etatYT === PLAYING) || w - this._fige.w > 1.5) this._fige = null;
          else obs = this._fige.cible;
        }
        const joue = this._etatYT === PLAYING && !this._fige;
        t = this._servo(this._t, obs, dt, this._rate, joue);
        if (t >= this.boucle[1]) t = this.aller(this.boucle[0]);
      } else {
        t = this._virtuelle(w);
      }
      this._t = t;
      this._w = w;
      return t;
    }

    // Dans un gestionnaire de clic ou de touche : unMute + playVideo.
    ecouter() {
      const p = this.player;
      this._pauseVoulue = false;
      if (!p || !p.unMute) {
        this._ecouterDemande = true;
        this.etat = 'virtuelle : écoute demandée, lecteur YouTube pas encore prêt';
        return Promise.resolve('attente');
      }
      p.unMute();
      if (p.setVolume) p.setVolume(100);
      p.playVideo();
      this._son = 'audible';
      this._majEtat();
      return Promise.resolve('youtube');
    }

    lire() {
      this._pauseVoulue = false;
      if (this.player && this.player.playVideo) this.player.playVideo();
      else this._v = { t: this.temps(), w: maintenant(), marche: true };
      return Promise.resolve(this._src);
    }

    pause() {
      this._pauseVoulue = true;
      const t = this.temps();
      this._v = { t: t, w: maintenant(), marche: false };
      if (this.player && this.player.pauseVideo) this.player.pauseVideo();
      this._majEtat();
    }

    aller(t) {
      t = Math.min(this.boucle[1] - 1e-3, Math.max(this.boucle[0], +t));
      const w = maintenant();
      if (this._src === 'youtube' && this.player && this.player.seekTo) {
        this.player.seekTo(t, true);
        this._fige = { cible: t, w: w };          // ancrage immédiat, le lecteur confirme ~130 ms après
      } else {
        this._v = { t: t, w: w, marche: this._v.marche };
      }
      this._t = t;
      this._w = w;
      return t;
    }

    // Le son du lecteur n'est jamais capté : 'inaudible' n'existe pas ici (on coupe).
    son(mode) {
      const p = this.player;
      let effectif = mode, note = '';
      if (mode === 'inaudible') {
        effectif = 'coupe';
        note = 'inaudible impossible avec YouTube (aucune analyse du son du lecteur) : son coupé';
      }
      if (p && p.mute) {
        if (effectif === 'coupe') p.mute(); else { p.unMute(); if (p.setVolume) p.setVolume(100); }
      }
      this._son = effectif;
      this._majEtat(note);
      return effectif;
    }

    ecartMs() {
      const p = this.player;
      if (this._src !== 'youtube' || !p || !p.getCurrentTime) return null;
      return (this._t - p.getCurrentTime()) * 1000;
    }

    _erreur(code) {
      this._repliVirtuel('erreur YouTube ' + code + (ERREURS_YT[code] ? ' (' + ERREURS_YT[code] + ')' : ''));
    }

    detruire() {
      if (this._minuteur) clearInterval(this._minuteur);
      this._minuteur = null;
      try { if (this.player && this.player.destroy) this.player.destroy(); } catch (e) { /* déjà détruit */ }
    }

    diagnostic() {
      return { source: this._src, etat: this.etat, etatYT: this._etatYT, fige: !!this._fige, son: this._son };
    }

    _majEtat(note) {
      if (this._src !== 'youtube') return;
      const noms = { '-1': 'non démarré', 0: 'terminé', 1: 'lecture', 2: 'en pause', 3: 'chargement', 5: 'prêt' };
      let s = 'youtube : ' + (noms[this._etatYT] || ('état ' + this._etatYT));
      if (this._etatYT === PLAYING) s += this._son === 'audible' ? ' avec son' : ' muette';
      if (this._etatYT !== PLAYING) s += ' (horloge tenue)';
      this.etat = note ? s + ' ; ' + note : s;
    }
  }

  /* ---------- Chargement de l'API YouTube (une fois, après consentement) ---------- */

  let apiYT = null;
  let scriptYT = false;

  function chargerApiYouTube(delaiMs) {
    if (window.YT && window.YT.Player) return Promise.resolve(window.YT);
    if (apiYT) return apiYT;
    apiYT = new Promise((resolve, reject) => {
      const prec = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = function () {
        if (typeof prec === 'function') { try { prec(); } catch (e) { /* autre client */ } }
        resolve(window.YT);
      };
      if (!scriptYT) {
        scriptYT = true;
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        s.async = true;
        s.onerror = () => { scriptYT = false; reject(new Error('iframe_api non chargé')); };
        document.head.appendChild(s);
      }
      setTimeout(() => reject(new Error('iframe_api : délai dépassé')), delaiMs);
    });
    apiYT.catch(() => { apiYT = null; });
    return apiYT;
  }

  // À appeler seulement après le clic de consentement. Le lecteur occupe tout le conteneur
  // (16:9 entier, jamais recouvert ni rogné : c'est au conteneur d'en avoir la taille).
  // opts.playerVars complète ou remplace les paramètres du lecteur (H4-3 : controls 0…).
  function youtube(conteneur, opts) {
    opts = opts || {};
    const debut = +opts.debut || 0;
    const fin = opts.fin > debut ? +opts.fin : debut + 300;
    const lecteur = new LecteurYouTube({ videoId: opts.videoId, debut: debut, fin: fin });
    const delai = opts.delaiMs || 15000;
    return chargerApiYouTube(delai).then((YT) => new Promise((resolve) => {
      let fini = false, player = null;
      const finir = () => { if (!fini) { fini = true; resolve({ lecteur: lecteur, player: player }); } };
      const cible = document.createElement('div');
      conteneur.appendChild(cible);
      player = new YT.Player(cible, {
        videoId: opts.videoId,
        width: '100%',
        height: '100%',
        playerVars: Object.assign({ autoplay: 1, mute: 1, playsinline: 1, controls: 1, rel: 0, start: Math.floor(debut) }, opts.playerVars),
        events: {
          onReady: () => { lecteur._attacher(player); finir(); },
          onStateChange: (e) => lecteur._surEtat(e.data),
          onPlaybackRateChange: (e) => { lecteur._rate = +e.data || 1; },
          onError: (e) => { lecteur._erreur(e.data); finir(); },
          onAutoplayBlocked: () => { if (lecteur.source !== 'youtube') lecteur._repliVirtuel('lecture automatique bloquée'); },
        },
      });
      lecteur.player = player;
      setTimeout(() => {
        if (!fini) { lecteur._repliVirtuel('lecteur YouTube muet après ' + Math.round(delai / 1000) + ' s'); finir(); }
      }, delai);
    }), (err) => {
      lecteur._repliVirtuel('API YouTube indisponible (' + err.message + ')');
      return { lecteur: lecteur, player: null };
    });
  }

  window.Sync = {
    lecteur: (opts) => new Lecteur(opts),
    youtube: youtube,
    version: 1,
  };
})();
