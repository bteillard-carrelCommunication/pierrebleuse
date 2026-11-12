/*
 * Tutti — noyau. Un point de lumière par musicien (96 sur ordinateur, 64 sur
 * téléphone), quatre pupitres aux teintes de la graduation chromatique du DA.
 *
 * Tout l'état piloté par le scroll est une FONCTION de scrollY (règle 11) :
 * à chaque frame, la position de chaque point est l'interpolation entre la
 * formation précédente et la suivante, par la courbe « anacrouse + résolution
 * longue » (règles 1 et 3), avec le retard de son pupitre (règle 2).
 * Seules la dérive, la respiration, la scintillation et le souffle du pointeur
 * vivent en temps réel (règle 6).
 *
 * Réglages exposés : window.Tutti.CFG (modifiable avant l'amorçage).
 */
(function () {
  'use strict';

  const TOUCH   = matchMedia('(hover: none), (pointer: coarse)').matches;
  const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const CFG = {
    N: TOUCH ? 64 : 96,
    // Ordre des pupitres = ordre des départs (règle 2). delay = bande de retards
    // (part de la course) ; dans un pupitre, les plus proches du sujet partent en premier.
    families: [
      { key: 'cordes',      share: 56 / 96, color: 0xEFE6D3, delay: [0.00, 0.42] },
      { key: 'bois',        share: 16 / 96, color: 0xB59AC8, delay: [0.30, 0.64] },
      { key: 'cuivres',     share: 14 / 96, color: 0x7FD3E3, delay: [0.52, 0.84] },
      { key: 'percussions', share: 10 / 96, color: 0x6FA08A, delay: [0.70, 1.00] },
    ],
    group: 3,                                  // points qui partent ensemble (règle 2)
    spread: 0.6,                               // part de la course occupée par les retards
    ease: { a: 3.2, b: 3.5 },                  // 1 - (1 - t^a)^b : 10 % au premier tiers (règle 1), 99 % à 0,9
    radius: TOUCH ? [1.8, 4.0] : [2.0, 5.0],   // rayon des points (px CSS)
    alpha: 0.9,
    scint: { amp: 0.15, period: [2, 5] },      // scintillation (opacité ± 15 %)
    drift: { amp1: 16, period1: 24, amp2: 5, period2: 10 },  // dérive libre : vitesse crête ≤ 12 px/s (mesurée 11,3)
    sway:  { amp: 1.4, period: [6, 10] },      // respiration posée : ≤ 2 px avec le résidu de dérive (règle 6)
    breath: { radius: 140, force: 26, attack: 0.35, release: 2.6 }, // souffle du pointeur
    window: { r0: TOUCH ? 15 : 22, grow: 6 },  // fenêtres de l'ouverture (rayon, puis × 6)
    // course de l'ouverture, en hauteurs d'écran. En tactile (pas de ralenti), courte pour
    // que le visage soit révélé avant de sortir du cadre par le haut.
    opening: { course: TOUCH ? 0.34 : 0.72 },
    linger: (TOUCH || REDUCED) ? 0 : 0.66,     // ralenti de la première photo (ordinateur)
    lingerSpan: 0.8,
    maxPixels: TOUCH ? 4.5e6 : 9e6,            // budget de chaque canvas (pixels physiques)
    seed: 20261022,
  };

  const clamp01 = (t) => (t < 0 ? 0 : t > 1 ? 1 : t);
  const lerp = (a, b, t) => a + (b - a) * t;
  const smooth01 = (t) => { t = clamp01(t); return t * t * (3 - 2 * t); };

  // Courbe de la grammaire : attaque quasi invisible (règle 1), puis résolution
  // longue (règle 3). Continue et dérivable : aucun à-coup, aller = retour.
  function easeG(t) {
    t = clamp01(t);
    return 1 - Math.pow(1 - Math.pow(t, CFG.ease.a), CFG.ease.b);
  }

  // Générateur déterministe : la même disposition à chaque chargement.
  function rng(seed) {
    let a = seed >>> 0;
    return function () {
      a = (a + 0x6D2B79F5) >>> 0;
      let t = a;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  /* ---------- Particules ---------- */
  class ParticleSet {
    constructor(N) {
      this.N = N;
      const R = rng(CFG.seed);
      this.rand = R;
      this.fam    = new Uint8Array(N);
      this.color  = new Uint32Array(N);
      this.radius = new Float32Array(N);
      this.delay  = new Float32Array(N);     // 0 → 1 (retard de pupitre)
      this.scW    = new Float32Array(N); this.scPh = new Float32Array(N);
      this.d1Wx   = new Float32Array(N); this.d1Px = new Float32Array(N);
      this.d1Wy   = new Float32Array(N); this.d1Py = new Float32Array(N);
      this.d2Wx   = new Float32Array(N); this.d2Px = new Float32Array(N);
      this.d2Wy   = new Float32Array(N); this.d2Py = new Float32Array(N);
      this.swW    = new Float32Array(N); this.swPh = new Float32Array(N);
      this.dispX  = new Float32Array(N); this.dispY = new Float32Array(N);
      this.pos    = new Float32Array(2 * N);   // position courante, coordonnées PAGE
      this.posed  = new Float32Array(N);       // 0 = libre, 1 = en formation

      // Répartition par pupitre (arrondie pour tomber juste sur N)
      const counts = CFG.families.map((f) => Math.round(f.share * N));
      let diff = N - counts.reduce((a, b) => a + b, 0);
      for (let k = 0; diff !== 0; k = (k + 1) % counts.length) { counts[k] += Math.sign(diff); diff -= Math.sign(diff); }
      this.counts = counts;
      this.byFamily = counts.map(() => []);
      let i = 0;
      counts.forEach((n, f) => { for (let j = 0; j < n; j++) { this.fam[i] = f; this.byFamily[f].push(i); i++; } });

      const TAU = Math.PI * 2;
      const [r0, r1] = CFG.radius;
      for (let p = 0; p < N; p++) {
        this.color[p]  = CFG.families[this.fam[p]].color;
        this.radius[p] = lerp(r0, r1, Math.pow(R(), 1.6));           // plus de petits que de gros
        this.scW[p]    = TAU / lerp(CFG.scint.period[0], CFG.scint.period[1], R());
        this.scPh[p]   = R() * TAU;
        this.d1Wx[p] = TAU / (CFG.drift.period1 * lerp(0.8, 1.25, R())); this.d1Px[p] = R() * TAU;
        this.d1Wy[p] = TAU / (CFG.drift.period1 * lerp(0.8, 1.25, R())); this.d1Py[p] = R() * TAU;
        this.d2Wx[p] = TAU / (CFG.drift.period2 * lerp(0.8, 1.25, R())); this.d2Px[p] = R() * TAU;
        this.d2Wy[p] = TAU / (CFG.drift.period2 * lerp(0.8, 1.25, R())); this.d2Py[p] = R() * TAU;
        this.swW[p]  = TAU / lerp(CFG.sway.period[0], CFG.sway.period[1], R());
        this.swPh[p] = R() * TAU;
        this.delay[p] = lerp(...CFG.families[this.fam[p]].delay, R());  // provisoire, affiné par assignDelays
      }
    }

    // Retards de pupitre (règle 2) : dans chaque pupitre, par petits groupes
    // (CFG.group), du plus proche du sujet au plus éloigné, dans la bande du pupitre.
    assignDelays(local, focal) {
      const R = rng(CFG.seed ^ 0x5bd1e995);
      this.byFamily.forEach((list, f) => {
        const [b0, b1] = CFG.families[f].delay;
        const sorted = list.slice().sort((p, q) => {
          const dp = Math.hypot(local[2 * p] - focal.x, local[2 * p + 1] - focal.y);
          const dq = Math.hypot(local[2 * q] - focal.x, local[2 * q + 1] - focal.y);
          return dp - dq;
        });
        const G = Math.max(1, Math.ceil(sorted.length / CFG.group));
        sorted.forEach((p, idx) => {
          const g = Math.floor(idx / CFG.group);
          const u = G > 1 ? g / (G - 1) : 0;
          this.delay[p] = clamp01(lerp(b0, b1, u) + (R() - 0.5) * 0.03);
        });
      });
    }
  }

  /* ---------- Formations ----------
     local : Float32Array(2N) en coordonnées LOCALES de l'hôte (écran, cadre
     photo ou section) ; l'hôte fournit chaque frame son décalage en page. */
  class Formation {
    constructor(name, host, local, opts = {}) {
      this.name = name; this.host = host; this.local = local;
      this.isFree = !!opts.isFree;
      this.still  = opts.still || null;   // indices immobiles (le chef dans le plan de salle)
    }
  }

  /* ---------- Calendrier de scroll ----------
     transitions ordonnées, sans chevauchement : { from, to, s0, s1 } en px de scroll. */
  class Scheduler {
    constructor(particles) {
      this.P = particles;
      this.transitions = [];
      this.q = [];                        // q[k][i] : progression du point i dans la transition k
      this.k = -1; this.p = 0;            // transition courante et sa progression brute
    }

    setTransitions(list) {
      // jamais de chevauchement : une transition ne commence qu'une fois la précédente achevée
      let last = -Infinity;
      for (const tr of list) {
        tr.s0 = Math.max(tr.s0, last);
        tr.s1 = Math.max(tr.s1, tr.s0 + 1);
        last = tr.s1;
      }
      this.transitions = list;
      this.q = list.map(() => new Float32Array(this.P.N));
    }

    // Progression (0 → 1) d'une transition pour une position de scroll donnée
    progress(k, s) {
      const tr = this.transitions[k];
      return tr ? clamp01((s - tr.s0) / (tr.s1 - tr.s0)) : 0;
    }

    update(s, t, dt, offsets, pointer, live = true) {
      const P = this.P, N = P.N, T = this.transitions;
      let k = -1;
      for (let j = 0; j < T.length; j++) { if (s >= T[j].s0) k = j; else break; }
      this.k = k;
      this.p = k < 0 ? 0 : clamp01((s - T[k].s0) / (T[k].s1 - T[k].s0));
      const spread = CFG.spread, inv = 1 / (1 - spread);
      for (let j = 0; j < T.length; j++) {
        const q = this.q[j];
        if (j < k || (j === k && s >= T[j].s1)) q.fill(1);
        else if (j > k || k < 0) q.fill(0);
        else for (let i = 0; i < N; i++) q[i] = easeG((this.p - P.delay[i] * spread) * inv);
      }
      if (!T.length) return;
      const from = k < 0 ? T[0].from : T[k].from;
      const to   = k < 0 ? T[0].from : T[k].to;
      const qk   = k < 0 ? null : this.q[k];
      const oa = offsets[from.host], ob = offsets[to.host];
      const A = from.local, B = to.local;
      const D = CFG.drift, S = CFG.sway, Bz = CFG.breath;
      const liveAmp = live ? 1 : 0;
      const stillFrom = from.still, stillTo = to.still;
      const rAtt = 1 - Math.exp(-dt / Bz.attack), rRel = 1 - Math.exp(-dt / Bz.release);
      for (let i = 0; i < N; i++) {
        const q = qk ? qk[i] : 0;
        const ax = A[2 * i] + oa.x, ay = A[2 * i + 1] + oa.y;
        const bx = B[2 * i] + ob.x, by = B[2 * i + 1] + ob.y;
        let x = ax + (bx - ax) * q, y = ay + (by - ay) * q;
        const posed = from.isFree ? q : 1;
        P.posed[i] = posed;
        // points « immobiles » (le chef dans le plan de salle) : ni dérive, ni respiration
        const still = (q >= 1 && stillTo && stillTo.has(i)) || (q <= 0 && stillFrom && stillFrom.has(i));
        // dérive lente quand le point est libre, presque rien une fois posé (2 % : ≈ 0,5 px)
        const da = liveAmp * (1 - posed * 0.98);
        if (da > 0 && !still) {
          x += da * (D.amp1 * Math.sin(t * P.d1Wx[i] + P.d1Px[i]) + D.amp2 * Math.sin(t * P.d2Wx[i] + P.d2Px[i]));
          y += da * (D.amp1 * Math.sin(t * P.d1Wy[i] + P.d1Py[i]) + D.amp2 * Math.sin(t * P.d2Wy[i] + P.d2Py[i]));
        }
        // respiration posée (règle 6) : un cercle de 1,4 px en 6 à 10 s — sauf les points « immobiles »
        if (!still && liveAmp) {
          x += S.amp * posed * Math.sin(t * P.swW[i] + P.swPh[i]);
          y += S.amp * posed * Math.cos(t * P.swW[i] + P.swPh[i]);
        }
        // souffle du pointeur : les points s'écartent doucement, reviennent lentement
        if (pointer && liveAmp) {
          const dx = x - pointer.x, dy = y - pointer.y, d2 = dx * dx + dy * dy;
          if (d2 < Bz.radius * Bz.radius && d2 > 0.01) {
            const d = Math.sqrt(d2), f = Bz.force * Math.pow(1 - d / Bz.radius, 2);
            P.dispX[i] += (dx / d * f - P.dispX[i]) * rAtt;
            P.dispY[i] += (dy / d * f - P.dispY[i]) * rAtt;
          } else { P.dispX[i] *= 1 - rRel; P.dispY[i] *= 1 - rRel; }
        } else { P.dispX[i] *= 1 - rRel; P.dispY[i] *= 1 - rRel; }
        P.pos[2 * i] = x + P.dispX[i];
        P.pos[2 * i + 1] = y + P.dispY[i];
      }
    }
  }

  /* ---------- Échantillonnage d'images (au chargement, canvas 2D) ---------- */
  function readPixels(img, w = img.naturalWidth, h = img.naturalHeight) {
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, w, h);
    return { data: ctx.getImageData(0, 0, w, h).data, w, h };
  }

  // Points (UV) tirés dans le blanc d'un masque, plus denses près du point focal.
  function sampleMask(px, n, focal, focalRadius, focalWeight, seed) {
    const R = rng(seed);
    const { data, w, h } = px;
    const cand = [];
    const step = 3;
    for (let y = 1; y < h - 1; y += step) for (let x = 1; x < w - 1; x += step) {
      const v = data[(y * w + x) * 4];
      if (v < 128) continue;
      const u = x / w, vv = y / h;
      const d = Math.hypot((u - focal.x) * (w / h), vv - focal.y) / focalRadius;
      const wgt = 1 + focalWeight * Math.exp(-d * d);
      cand.push(u, vv, wgt);
    }
    return weightedPick(cand, n, R);
  }

  // Pixels clairs d'une photo, dans une zone [y0, y1] (UV), pondérés par la luminance.
  function sampleBright(px, n, y0, y1, threshold, seed) {
    const R = rng(seed);
    const { data, w, h } = px;
    const cand = [];
    for (let y = Math.floor(y0 * h); y < y1 * h; y += 3) for (let x = 2; x < w - 2; x += 3) {
      const k = (y * w + x) * 4;
      const l = (0.299 * data[k] + 0.587 * data[k + 1] + 0.114 * data[k + 2]) / 255;
      if (l < threshold) continue;
      cand.push(x / w, y / h, Math.pow((l - threshold) / (1 - threshold), 1.5) + 0.05);
    }
    return weightedPick(cand, n, R);
  }

  // Tirage pondéré sans remise, avec une distance minimale pour éviter les paquets.
  function weightedPick(cand, n, R) {
    const out = [];
    const m = cand.length / 3;
    if (!m) return out;
    let total = 0;
    const cum = new Float64Array(m);
    for (let i = 0; i < m; i++) { total += cand[3 * i + 2]; cum[i] = total; }
    const minD = 0.012;
    let guard = 0;
    while (out.length < 2 * n && guard++ < n * 60) {
      const r = R() * total;
      let lo = 0, hi = m - 1;
      while (lo < hi) { const mid = (lo + hi) >> 1; if (cum[mid] < r) lo = mid + 1; else hi = mid; }
      const u = cand[3 * lo], v = cand[3 * lo + 1];
      let ok = true;
      for (let j = 0; j < out.length; j += 2) if (Math.hypot(out[j] - u, out[j + 1] - v) < minD) { ok = false; break; }
      if (ok) out.push(u, v);
    }
    return out;
  }

  window.Tutti = { CFG, TOUCH, REDUCED, clamp01, lerp, smooth01, easeG, rng,
                   ParticleSet, Formation, Scheduler, readPixels, sampleMask, sampleBright };
})();
