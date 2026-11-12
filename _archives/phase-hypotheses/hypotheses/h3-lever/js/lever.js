/*
 * lever.js — boot de l'hypothèse 3 « Lever » :
 *   - scènes de lumière (LightScene, une par photo) ;
 *   - la trace de la baguette : un seul <path> sur toute la page, tracé par le
 *     scroll (stroke-dashoffset = fonction exacte de scrollY), tête lumineuse ;
 *   - textes qui s'allument par lignes (règle 12) ;
 *   - rampe de lampes de l'agenda, allumées quand la trace les atteint.
 *
 * Tout état visuel est une fonction de window.scrollY (règle 11 : aller =
 * retour, aucune inertie ajoutée). Seules vivent en temps réel : la
 * respiration des photos (LightScene.tick) et celle du halo de la tête
 * (animation CSS), toutes deux coupées par prefers-reduced-motion.
 */
(function () {
'use strict';
window.__leverBooted = true;

const html    = document.documentElement;
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const touch   = matchMedia('(hover: none), (pointer: coarse)').matches;
const anac    = window.LightScene.anac;
const clamp01 = (t) => Math.min(1, Math.max(0, t));
const vh      = () => window.innerHeight;
const safe    = (fn) => { try { fn(); } catch (err) { console.warn('[lever]', err); } };
html.classList.add(touch ? 'is-touch' : 'is-pointer');

// Réglages exposés (voir LISEZ-MOI.md)
const SETTINGS = {
  lead:         0.60,   // la tête de la trace se tient à 60 % de la hauteur d'écran
  leadStart:    0.21,   // … et part de sous le logo (21 %) : elle descend sur la 1re hauteur d'écran
  headFade:     0.50,   // extinction de la tête sur la dernière demi-hauteur d'écran
  lineGap:      touch ? 44 : 66,   // décalage entre deux lignes, en px de scroll (≈ 80–120 ms d'équivalent-scroll)
  lineCourse:   0.26,   // course d'une ligne, en hauteur d'écran
  lineStart:    0.82,   // une ligne commence quand son bloc entre à 82 % de l'écran
  lampCourse:   0.36,   // allumage d'une lampe, en hauteur d'écran
  lampAhead:    0.08,   // la lampe commence à s'allumer un peu avant l'arrivée de la tête (anacrouse)
  traceMaxSpeed: 3,     // px de trait par px de scroll, au plus (sinon la trace « fouette » : 13,8 mesuré à l'entrée de l'agenda)
  sceneLineCourse: 0.12, sceneLineGap: 0.015,   // lignes d'un texte posé dans une photo : course et décalage en progression de la scène
};

// 1. Lenis à la souris / au trackpad seulement (jamais en tactile ni en mouvement réduit) + ticker GSAP
const lenis = (!touch && !reduced && window.Lenis) ? new Lenis({
  duration: 1.1,
  smoothWheel: true,
  easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
}) : null;
if (lenis) gsap.ticker.add((time) => lenis.raf(time * 1000));
gsap.ticker.lagSmoothing(0);

// 2. Une scène de lumière par photo
const scenes = [...document.querySelectorAll('[data-scene]')].map((el) => new LightScene(el, { touch, reduced }));

// 3. Textes par lignes — flou 2 px → net, montée 10 px, halo lumineux 0 → 18 px → 6 px (grands serifs)
function styleLine(el, q, glow) {
  if (el.__q === q) return;
  el.__q = q;
  const st = el.style;
  st.opacity = q.toFixed(3);
  if (reduced) return;                       // mouvement réduit : simple fondu
  const done = q >= 0.999;
  st.transform = done ? '' : `translate3d(0, ${((1 - q) * 10).toFixed(2)}px, 0)`;
  st.filter    = done ? '' : `blur(${((1 - q) * 2).toFixed(2)}px)`;
  if (glow) {
    const g = q < 0.6 ? 18 * (q / 0.6) : 18 - 12 * ((q - 0.6) / 0.4);
    st.textShadow = `0 0 ${g.toFixed(1)}px var(--glow)`;
  }
}

class TextBlock {
  constructor(el) {
    this.el    = el;
    this.scene = scenes.find((s) => s.section.contains(el)) || null;
    this.phase = el.dataset.phase ? el.dataset.phase.split(',').map(Number) : [0.40, 0.62];
    this.glow  = el.classList.contains('glow');
    this.src   = el.textContent.trim().replace(/\s+/g, ' ');
    this.lines = [];
    this.top   = 0;
  }
  // Découpe en lignes réelles : mots mesurés, regroupés par ligne, puis une <span class="line"> par ligne
  split() {
    const el = this.el;
    el.classList.remove('is-split');
    el.textContent = '';
    const spans = this.src.split(' ').map((w) => {
      const s = document.createElement('span');
      s.textContent = w;
      el.appendChild(s);
      el.appendChild(document.createTextNode(' '));
      return s;
    });
    const groups = [];
    let lastTop = null;
    for (const s of spans) {
      const t = s.offsetTop;
      if (lastTop === null || Math.abs(t - lastTop) > 1) { groups.push([]); lastTop = t; }
      groups[groups.length - 1].push(s.textContent);
    }
    el.textContent = '';
    this.lines = groups.map((ws) => {
      const l = document.createElement('span');
      l.className = 'line';
      l.textContent = ws.join(' ');
      l.style.opacity = '0';      // éteinte à la création ; restylée dans la même frame par updateAll() (jamais affichée entière)
      l.__q = -1;
      el.appendChild(l);
      return l;
    });
    el.classList.add('is-split');
  }
  measure() { if (!this.scene) this.top = this.el.getBoundingClientRect().top + window.scrollY; }
  update(y) {
    const n = this.lines.length;
    if (!n) return;
    if (this.scene) {
      // dans une photo : phase de la scène (data-phase="début,fin" en progression 0→1), règle 8 : après la lumière
      const [a, b] = this.phase, p = this.scene.progress, course = SETTINGS.sceneLineCourse;
      const gap = n > 1 ? Math.max(SETTINGS.sceneLineGap, (b - a - course) / (n - 1)) : 0;
      for (let i = 0; i < n; i++) styleLine(this.lines[i], anac((p - a - i * gap) / course), this.glow);
    } else {
      const H = vh(), s0 = this.top - SETTINGS.lineStart * H, course = SETTINGS.lineCourse * H;
      for (let i = 0; i < n; i++) styleLine(this.lines[i], anac((y - s0 - i * SETTINGS.lineGap) / course), this.glow);
    }
  }
}
const texts = [...document.querySelectorAll('[data-lines]')].map((el) => new TextBlock(el));

// 4. Lampes de l'agenda : `s` = position de scroll où la tête de la trace atteint la lampe (posé par buildTrace)
const lamps = [...document.querySelectorAll('[data-lamp]')].map((el) => ({
  el, light: el.querySelector('.lamp__light'), lines: [...el.querySelectorAll('.line')], s: 0, q: -1,
}));
function updateLamps(y) {
  const H = vh();
  for (const L of lamps) {
    const q = anac((y - (L.s - SETTINGS.lampAhead * H)) / (SETTINGS.lampCourse * H));
    if (q !== L.q) { L.q = q; L.el.style.setProperty('--lit', q.toFixed(3)); }
    L.lines.forEach((l, i) => styleLine(l, anac((y - (L.s - 0.04 * H) - i * 0.06 * H) / (0.30 * H)), i === 1));
  }
}

// 5. La trace de la baguette
const svg  = document.querySelector('.trace');
const path = svg.querySelector('.trace__path');
const head = svg.querySelector('.trace__head');
const T = { total: 0, anchors: [], sMax: 1, lastLen: -1 };
const sec = (name) => document.querySelector(`[data-section="${name}"]`);
function rectOf(el) {
  const r = el.getBoundingClientRect();
  const top = r.top + window.scrollY;
  return { top, h: r.height, left: r.left, w: r.width, cx: r.left + r.width / 2, cy: top + r.height / 2 };
}
// Catmull-Rom → Bézier cubiques : une courbe fluide qui passe par tous les temps.
// Retourne le `d` du path et les segments (pour la longueur d'arc analytique).
function catmullRom(P) {
  const segs = [];
  if (P.length < 2) return { d: '', segs };
  let d = `M ${P[0].x.toFixed(1)} ${P[0].y.toFixed(1)}`;
  for (let i = 0; i < P.length - 1; i++) {
    const p0 = P[i - 1] || P[i], p1 = P[i], p2 = P[i + 1], p3 = P[i + 2] || p2;
    const c1 = { x: p1.x + (p2.x - p0.x) / 6, y: p1.y + (p2.y - p0.y) / 6 };
    const c2 = { x: p2.x - (p3.x - p1.x) / 6, y: p2.y - (p3.y - p1.y) / 6 };
    d += ` C ${c1.x.toFixed(1)} ${c1.y.toFixed(1)} ${c2.x.toFixed(1)} ${c2.y.toFixed(1)} ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
    segs.push({ p0: p1, c1, c2, p3: p2 });
  }
  return { d, segs };
}
// Longueur d'une Bézier cubique : polyligne de 48 pas (écart < 0,05 % sur ces courbes),
// quelques microsecondes — contre 130 µs par getPointAtLength() du navigateur
function cubicLength({ p0, c1, c2, p3 }) {
  let len = 0, px = p0.x, py = p0.y;
  for (let i = 1; i <= 48; i++) {
    const t = i / 48, mt = 1 - t;
    const a = mt * mt * mt, b = 3 * mt * mt * t, c = 3 * mt * t * t, dd = t * t * t;
    const x = a * p0.x + b * c1.x + c * c2.x + dd * p3.x, y = a * p0.y + b * c1.y + c * c2.y + dd * p3.y;
    len += Math.hypot(x - px, y - py); px = x; py = y;
  }
  return len;
}

// Les temps de la trace (ordinateur). Schéma de direction à quatre temps :
// descente, gauche, droite, remontée — étiré sur la page ; tiers gauche dans
// les textes, traversée vers la droite au niveau des photos.
// {x, y} en px de page ; `s` (scroll où la tête y est) est déduit sauf indication.
function desktopBeats(S, lampPts, vw, H, after, docH) {
  const O = S.open, R = S.rest, P = S.photo2, A = S.agenda, F = S.foot;
  const b = (x, y, s) => ({ x: x * vw, y, s });
  const out = [
    // Ouverture : la baguette part de sous le logo, là où naît le spot
    b(0.075, 0.14 * O.h, 0),
    b(0.090, 0.43 * O.h),                 // 1. descente (le lever retombe)
    b(0.050, 0.56 * O.h),                 // 2. gauche
    b(0.230, 0.70 * O.h),                 // 3. droite, vers les mains
    b(0.300, 0.63 * O.h),                 // 4. petite remontée, souple
    b(0.340, 0.92 * O.h),                 // 1. descente, deuxième mesure
    b(0.620, O.top + O.h + 0.10 * H),     // 3. traverse vers la droite en sortant de la photo
    // Respiration : dans l'intervalle entre la citation (gauche) et le lorem (droite)
    b(0.700, R.top + 0.40 * R.h),
    b(0.660, R.top + 0.62 * R.h),
    b(0.720, R.top + 0.86 * R.h),
    // Deuxième photo : gauche, puis traversée vers la droite au niveau de la photo
    b(0.500, P.top + 0.15 * H),
    b(0.360, P.top + 0.50 * H),
    b(0.780, P.top + 0.92 * H),
    b(0.640, P.top + P.h - 0.12 * H),
  ];
  if (lampPts.length) {
    // Agenda : la trace quitte la photo en descendant vers la gauche, passe à droite du titre,
    // puis va de lampe en lampe — la baguette donne les départs. Les `s` sont explicites :
    // le trait n'avance jamais plus vite que 3 px par px de scroll (traceMaxSpeed), là où
    // l'ancienne traversée horizontale vers la première lampe faisait 13,8 px/px (fouet).
    const ly = lampPts[0].cy;
    out.push({ x: 0.52 * vw, y: ly - 0.42 * H, s: ly - 1.08 * H });
    out.push({ x: 0.30 * vw, y: ly - 0.24 * H, s: ly - 0.95 * H });
    out.push({ x: lampPts[0].cx + 0.05 * vw, y: ly - 0.11 * H, s: ly - 0.82 * H });
    lampPts.forEach((L, k) => {
      const s = ly - (0.70 - 0.12 * k) * H;        // la rampe remonte dans l'écran pendant que la tête la parcourt (0,12 H par lampe)
      out.push({ x: L.cx, y: L.cy, s, lamp: k });
      if (k < lampPts.length - 1) {
        const N = lampPts[k + 1];
        out.push({ x: (L.cx + N.cx) / 2, y: ly + (k % 2 ? -0.045 : 0.06) * H, s: s + 0.06 * H });
      }
    });
    out.push({ x: 0.78 * vw, y: ly + 0.30 * H, s: ly - 0.10 * H });   // sortie de la rampe, sans crochet
  }
  // Pied de page : la trace redescend vers le centre et se termine sur le chef accroupi
  out.push(b(0.550, F.top + 0.08 * H));
  out.push(b(0.420, F.top + 0.30 * H));
  out.push(b(0.540, F.top + 0.52 * H));
  out.push({ x: 0.62 * vw, y: docH - after - 0.56 * H, s: docH - H });
  return out;
}

// Téléphone : la trace reste sur le bord gauche et passe par chaque lampe (empilées), fin au centre
function mobileBeats(S, lampPts, vw, H, after, docH) {
  const O = S.open, R = S.rest, P = S.photo2, A = S.agenda, F = S.foot;
  const b = (x, y, s) => ({ x: x * vw, y, s });
  const out = [
    b(0.085, 0.17 * O.h, 0),
    b(0.090, 0.52 * O.h),
    b(0.055, 0.68 * O.h),
    b(0.150, 0.84 * O.h),
    b(0.110, 0.80 * O.h),
    b(0.080, O.top + O.h + 0.12 * H),
    b(0.060, R.top + 0.35 * R.h),
    b(0.120, R.top + 0.60 * R.h),
    b(0.070, R.top + 0.88 * R.h),
    b(0.090, P.top + 0.25 * H),
    b(0.050, P.top + 0.60 * H),
    b(0.130, P.top + P.h - 0.12 * H),
    b(0.070, A.top + 0.18 * H),
  ];
  lampPts.forEach((L, k) => {
    out.push({ x: L.cx, y: L.cy, lamp: k });
    if (k < lampPts.length - 1) {
      const N = lampPts[k + 1];
      out.push({ x: L.cx + (k % 2 ? -0.025 : 0.035) * vw, y: (L.cy + N.cy) / 2 });
    }
  });
  out.push(b(0.070, A.top + A.h - 0.12 * H));
  out.push(b(0.100, F.top + 0.22 * H));
  out.push(b(0.300, F.top + 0.46 * H));
  out.push({ x: 0.5 * vw, y: docH - after - 0.52 * H, s: docH - H });
  return out;
}

function buildTrace() {
  const vw = window.innerWidth, H = vh();
  const docH = document.documentElement.scrollHeight;
  const sMax = Math.max(1, docH - H);
  svg.setAttribute('viewBox', `0 0 ${vw} ${docH}`);
  svg.setAttribute('width', vw);
  svg.setAttribute('height', docH);
  const S = { open: rectOf(sec('open')), rest: rectOf(sec('rest')), photo2: rectOf(sec('photo2')), agenda: rectOf(sec('agenda')), foot: rectOf(sec('foot')) };
  const after = Math.max(0, docH - (S.foot.top + S.foot.h));
  const lampPts = lamps.map((L) => rectOf(L.light));
  const portrait = vw / H < 0.9;
  const rawBeats = portrait ? mobileBeats(S, lampPts, vw, H, after, docH) : desktopBeats(S, lampPts, vw, H, after, docH);

  // `s` manquant : la tête se tient à `lead` du haut de l'écran ; lead monte de
  // leadStart à lead sur la première hauteur d'écran. Puis monotonie et bornes.
  const lead = (y) => (SETTINGS.leadStart + (SETTINGS.lead - SETTINGS.leadStart) * clamp01(y / H - SETTINGS.leadStart)) * H;
  const beats = [];
  let prev = -Infinity;
  rawBeats.forEach((b, i) => {
    let s = b.s !== undefined ? b.s : b.y - lead(b.y);
    if (i === 0) s = 0;
    s = Math.min(sMax, Math.max(s, prev + 0.04 * H));
    prev = s;
    beats.push({ x: b.x, y: b.y, s, lamp: b.lamp });
  });
  beats[beats.length - 1].s = sMax;

  const { d, segs } = catmullRom(beats);
  path.setAttribute('d', d);
  const total = path.getTotalLength();
  path.style.strokeDasharray = `${total}`;
  // Longueur d'arc de chaque temps : chaque temps est la fin exacte d'un segment de Bézier,
  // donc la somme analytique des segments précédents, recalée sur la longueur SVG
  // (plus d'échantillonnage par getPointAtLength : 3 165 appels = 412 ms par relayout, mesuré).
  const cum = [0];
  for (const sg of segs) cum.push(cum[cum.length - 1] + cubicLength(sg));
  const scale = cum[cum.length - 1] > 0 ? total / cum[cum.length - 1] : 1;
  const anchors = beats.map((b, i) => ({ s: b.s, len: Math.min(total, cum[i] * scale), lamp: b.lamp }));
  // Vitesse de tracé plafonnée (traceMaxSpeed px de trait par px de scroll) : un temps trop
  // proche du précédent pour la longueur d'arc à parcourir est repoussé, et les suivants avec lui
  let capped = 0, maxSpeed = 0;
  for (let i = 1; i < anchors.length; i++) {
    const need = (anchors[i].len - anchors[i - 1].len) / SETTINGS.traceMaxSpeed;
    if (anchors[i].s - anchors[i - 1].s < need - 0.5) { anchors[i].s = Math.min(sMax, anchors[i - 1].s + need); capped++; }
    maxSpeed = Math.max(maxSpeed, (anchors[i].len - anchors[i - 1].len) / Math.max(1, anchors[i].s - anchors[i - 1].s));
  }
  anchors[anchors.length - 1].s = sMax;
  for (const a of anchors) if (a.lamp !== undefined && lamps[a.lamp]) lamps[a.lamp].s = a.s;
  Object.assign(T, { total, anchors, sMax, lastLen: -1, capped, maxSpeed });
}
function lenAt(s) {
  const A = T.anchors;
  if (!A.length) return 0;
  if (s <= A[0].s) return A[0].len;
  for (let i = 1; i < A.length; i++) {
    if (s <= A[i].s) {
      const a = A[i - 1], b = A[i];
      const t = b.s > a.s ? (s - a.s) / (b.s - a.s) : 1;
      return a.len + (b.len - a.len) * t;
    }
  }
  return A[A.length - 1].len;
}
function updateTrace(y) {
  if (!T.total) return;
  const len = lenAt(y);
  if (len === T.lastLen) return;
  T.lastLen = len;
  path.style.strokeDashoffset = `${T.total - len}`;
  const pt = path.getPointAtLength(len);
  head.setAttribute('transform', `translate(${pt.x.toFixed(2)} ${pt.y.toFixed(2)})`);
  const H = vh();
  head.style.opacity = (1 - anac((y - (T.sMax - SETTINGS.headFade * H)) / (SETTINGS.headFade * H))).toFixed(3);   // la tête s'éteint lentement à la fin
}

// 6. Indice de défilement : s'efface sur le premier tiers d'écran
const hint = document.querySelector('[data-hint]');
function updateHint(y) {
  if (!hint) return;
  const o = (1 - anac(y / (0.3 * vh()))).toFixed(3);
  if (hint.style.opacity !== o) hint.style.opacity = o;
}

// 7. Une frame complète : tout se relit sur scrollY (scènes, trace, textes, lampes, indice)
let lastY = -1;
function updateAll(y) {
  for (const s of scenes) safe(() => s.update(y));
  safe(() => updateTrace(y));
  for (const t of texts) t.update(y);
  updateLamps(y);
  updateHint(y);
  lastY = y;
}

// Géométrie, dans l'ordre de la page ; puis la mise à jour complète DANS LA MÊME FRAME :
// les lignes recréées par split() sont restylées avant la peinture (sinon elles
// s'affichaient entières le temps d'un tick, à chaque relayout : polices, scènes, resize).
function relayout() {
  texts.forEach((t) => safe(() => t.split()));
  scenes.forEach((s) => safe(() => s.measure()));
  scenes.forEach((s) => safe(() => s.layout()));
  texts.forEach((t) => t.measure());
  safe(buildTrace);
  updateAll(window.scrollY);
}
let pending = false;
function schedule() {
  if (pending) return;
  pending = true;
  requestAnimationFrame(() => { pending = false; relayout(); });
}
window.addEventListener('resize', schedule);
window.addEventListener('load', schedule);
if (document.fonts) document.fonts.ready.then(schedule);
if (window.ResizeObserver) new ResizeObserver(schedule).observe(document.body);
relayout();

scenes.forEach((h) => {
  h.init().then(schedule, (err) => {
    console.warn('[LightScene] effet désactivé, photo seule :', err);
    h.section.classList.add('is-fallback');
    if (h.app) { try { h.app.destroy(false); } catch (e) { /* déjà détruit */ } h.app = null; }
  });
});

// 8. Le ticker : Lenis (déjà dans le ticker), puis tout se relit sur scrollY si elle a changé
let lastDpr = window.devicePixelRatio;
gsap.ticker.add(() => {
  if (window.devicePixelRatio !== lastDpr) { lastDpr = window.devicePixelRatio; schedule(); }
  const y = window.scrollY, now = performance.now();
  if (y !== lastY) updateAll(y);
  for (const s of scenes) s.tick(now);
});

window.LEVER = { scenes, texts, lamps, trace: T, relayout, updateAll, lenis, anac, reduced, touch, settings: SETTINGS, lenAt };
})();
