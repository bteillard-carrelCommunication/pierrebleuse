/*
 * StripesHero « Rubato » — hypothèse 1 (le temps musical).
 *
 * Dérivé de ../../js/stripes-effect.js (l'original n'est pas modifié). Les
 * COMPOSITIONS du DA sont inchangées (presets bleuse-1 / bleuse-3 / bleuse-5,
 * desktop + portrait) : c'est la MANIÈRE D'ARRIVER qui change.
 *
 *  - phrase()            courbe asymétrique : anacrouse (0 → 10 % sur le premier
 *                        tiers, ease-in-sine) puis résolution longue (pente nulle
 *                        à la fin) — règles 1 et 3 ;
 *  - lumière → découpe   chaque strie, segment de bandeau ou voile est annoncé
 *                        par une variation de lumière large et diffuse, puis la
 *                        découpe (photo pivotée) monte, puis le bord se resserre
 *                        — règles 4 et 7 ;
 *  - pupitres            retards étalés sur ≥ 0,6 de la partition, du sujet vers
 *                        les bords, par paires ; bandeau en 1 → 3 → 2 → 4 — règle 2 ;
 *  - rubato interne      la photo pivotée glisse de ≤ 3 % de la hauteur avec une
 *                        courbe plus lente que le bord qui l'encadre — règle 5 ;
 *  - respiration         échelle du mesh ± 0,3 % sur 8 s + léger déplacement de
 *                        la lumière, hors scroll, coupée en mouvement réduit — règle 6 ;
 *  - graduation          la photo glisse vers une gradation chromatique du DA
 *                        (sépia / lavande nuit / crème) pendant la construction — règle 7 ;
 *  - synchronie          l'état est une fonction EXACTE de scrollY (pas d'inertie) ;
 *                        seule la respiration vit en temps réel — règle 11.
 *
 * La partition d'une composition est écrite en « temps » (retards + durées) puis
 * ramenée à 0 → 1 : le dernier élément se pose exactement quand uProgress = 1.
 * Les `spread` et retards ci-dessous sont donc choisis APRÈS normalisation : les
 * départs d'un même effet s'étalent sur ≥ 60 % de la course réelle (règle 2).
 *
 * Géométrie (measure) : la composition se pose (p1) quand la photo est alignée
 * en haut de l'écran (heros du milieu et de pied, ordinateur), et le texte posé
 * sur la photo n'entre QU'APRÈS p1 (règle 8). Le hero de pied réserve pour cela
 * un espace noir (`gap`) avant le colophon.
 */

// ---------- phrasé (règles 1 & 3) ----------
// 0 → 10 % sur le premier tiers (ease-in-sine), puis résolution longue : un
// polynôme dont la pente se raccorde à celle du tiers (C1, aucun à-coup) et
// s'annule à l'arrivée (comme un ease-out-quart).
const PHRASE_A = 1 / 3, PHRASE_K = 0.10;
const PHRASE_B = 3 - ((PHRASE_K * (Math.PI / 2)) / PHRASE_A) * ((1 - PHRASE_A) / (1 - PHRASE_K)); // ≈ 2,651
function phrase(t) {
  t = Math.min(1, Math.max(0, t));
  if (t < PHRASE_A) return PHRASE_K * (1 - Math.cos((Math.PI / 2) * (t / PHRASE_A)));
  const u = (t - PHRASE_A) / (1 - PHRASE_A), v = 1 - u;
  return PHRASE_K + (1 - PHRASE_K) * (1 - v * v * v * (1 + PHRASE_B * u));
}
const PHRASE_GLSL = `
float phrase(float t) {
  t = clamp(t, 0.0, 1.0);
  if (t < 0.33333) return 0.10 * (1.0 - cos(4.71239 * t));
  float u = (t - 0.33333) * 1.5;
  float v = 1.0 - u;
  return 0.10 + 0.90 * (1.0 - v * v * v * (1.0 + ${PHRASE_B.toFixed(4)} * u));
}`;

// ---------- pupitres (règle 2) ----------
// Trie les stries par distance au sujet et les fait entrer par petits groupes
// (paires), du sujet vers les bords, les retards étalés sur `spread` ; un léger
// décalage `jitter` dans chaque groupe évite l'entrée au cordeau.
function pupitres(items, focusX, { spread = 0.6, group = 2, jitter = 0.03 } = {}) {
  const sorted = items
    .map((it) => ({ it, d: Math.abs((it[0] + it[1]) / 2 - focusX) }))
    .sort((a, b) => a.d - b.d);
  const groups = Math.ceil(sorted.length / group);
  return sorted.map((o, i) => {
    const g = Math.floor(i / group), k = i % group;
    const delay = (groups > 1 ? g / (groups - 1) : 0) * spread + k * jitter;
    const [x0, x1, dir, behind] = o.it;
    return [x0, x1, delay, dir, behind];
  });
}

// Grille alignée sur la maquette (UV photo) → [x0, x1, sens, derrière le sujet], puis pupitres
function stripeGrid({ period, width, offset, count, skip = [], behind = [], focusX, spread }) {
  const items = [];
  for (let k = 0; k < count; k++) {
    if (skip.includes(k)) continue;
    const x0 = offset + k * period;
    items.push([x0, x0 + width, k % 2 ? -1 : 1, behind.includes(k)]);
  }
  return pupitres(items, focusX, { spread });
}

// Grille régulière en coordonnées ÉCRAN (portrait)
function screenGrid({ count, focusX = 0.5, spread = 0.6, behind = true }) {
  const period = 1 / count, half = period / 4, items = [];
  for (let k = 0; k < count; k++) {
    const cx = (k + 0.5) * period;
    items.push([cx - half, cx + half, k % 2 ? -1 : 1, behind]);
  }
  return pupitres(items, focusX, { spread });
}

// Gradations chromatiques du DA (duotone ombre → lumière), voir _ref/graduation-chromatique-*.jpg
const GRADE = {
  sepia:    { dark: [0x3B, 0x2A, 0x1E], light: [0xEF, 0xE6, 0xD3] },   // crème / sépia chaud
  lavande:  { dark: [0x10, 0x14, 0x2C], light: [0xB9, 0xBE, 0xDB] },   // lavande → bleu nuit froid
  creme:    { dark: [0x2A, 0x21, 0x18], light: [0xF3, 0xEA, 0xD8] },   // sépia doux sous les voiles
};

const STRIPES_PRESETS = {
  // Maquette bleuse-2.jpg : 4 stries à 0,5 + bandeau bas, sans teinte.
  // Partition : stries en deux paires (4 → 3 puis 2 → 1, de la plus proche du
  // sujet vers le bord), puis le bandeau en 1 → 3 → 2 → 4 (retards 0 / 0,18 / 0,32 / 0,5).
  'bleuse-1': {
    focus: [0.55, 0.45],
    mask:  '../../assets/bleuse-1-masque.png',
    desktop: {
      space:       'image',
      reveal:      'wipe',
      stripeAlpha: 0.5,
      tint:        [0, 0, 0],
      tintMix:     0,
      vignette:    0.06,
      light:       { color: [214, 178, 110], gain: 0.11 },   // lumière dorée du bokeh
      grade:       { ...GRADE.sepia, mix: 0.35, on: 'all' },
      stripeDuration: 0.70,
      bandDuration:   0.50,
      stripes: [
        [0.182, 0.217, 0.30,  1, false],
        [0.227, 0.260, 0.26, -1, false],
        [0.270, 0.304, 0.04,  1, false],
        [0.313, 0.353, 0.00, -1, false],
      ],
      band: {
        y0: 0.745,
        y1: 0.824,
        start: 0.45,
        // [x0, x1, centre source X (UV photo), décalage source Y, retard, sens du volet]
        segments: [
          [0.130, 0.355, 0.78, -0.10, 0.00,  1],
          [0.355, 0.445, 0.55,  0.12, 0.32, -1],
          [0.445, 0.690, 0.60,  0.10, 0.18,  1],
          [0.690, 1.000, 0.30, -0.04, 0.50, -1],
        ],
      },
    },
    portrait: {
      space:       'screen',
      reveal:      'wipe',
      stripeAlpha: 0.5,
      tint:        [0, 0, 0],
      tintMix:     0,
      vignette:    0,
      light:       { color: [214, 178, 110], gain: 0.11 },
      grade:       { ...GRADE.sepia, mix: 0.35, on: 'all' },
      stripeDuration: 0.70,
      bandDuration:   0.50,
      stripes: [
        [0.03, 0.10, 0.30,  1, true],
        [0.13, 0.18, 0.04, -1, true],
        [0.76, 0.83, 0.00,  1, true],
        [0.87, 0.93, 0.26, -1, true],
      ],
      band: {
        y0: 0.68,              // portrait : un peu plus haut que la maquette (0,745) pour
        y1: 0.76,              // rester au-dessus du dégradé sombre du texte en bas d'écran
        start: 0.45,
        segments: [
          [0.00, 0.30, 0.78, -0.10, 0.00,  1],
          [0.30, 0.45, 0.55,  0.12, 0.32, -1],
          [0.45, 0.72, 0.60,  0.10, 0.18,  1],
          [0.72, 1.00, 0.30, -0.04, 0.50, -1],
        ],
      },
    },
  },

  // Maquette bleuse-4.jpg : grille de stries de 30,5 px tous les 60,25 px,
  // vide autour du chef, remplissage = 50 % photo pivotée + 50 % lavande #5A638D.
  // Partition : paires du chef vers les bords, `spread` 1,15 en temps de partition
  // = 1,15 / (1,15 + 0,75) → les départs couvrent 61 % de la course réelle (règle 2).
  'bleuse-3': {
    focus: [0.61, 0.40],
    mask:  '../../assets/bleuse-3-masque.png',
    desktop: {
      space:       'image',
      reveal:      'open',
      stripeAlpha: 1.0,
      tint:        [90, 99, 142],
      tintMix:     0.5,
      vignette:    0,
      light:       { color: [140, 150, 210], gain: 0.10 },   // lumière de scène bleue
      grade:       { ...GRADE.lavande, mix: 0.40, on: 'outside' },
      stripeDuration: 0.75,
      stripes: stripeGrid({
        period: 60.25 / 992,
        width:  30.5 / 992,
        offset: -1.5 / 992,
        count:  17,
        skip:   [9, 10, 11],
        behind: [12, 13],
        focusX: 0.61,
        spread: 1.15,
      }),
    },
    portrait: {
      space:       'screen',
      reveal:      'open',
      stripeAlpha: 1.0,
      tint:        [90, 99, 142],
      tintMix:     0.5,
      vignette:    0,
      light:       { color: [140, 150, 210], gain: 0.10 },
      grade:       { ...GRADE.lavande, mix: 0.40, on: 'outside' },
      stripeDuration: 0.75,
      stripes:     screenGrid({ count: 8, focusX: 0.5, spread: 1.15 }),
    },
  },

  // Maquette bleuse-6.png : deux voiles pleine hauteur autour du chef accroupi.
  // Mesuré sur la maquette du DA (22/10) : blanc pur, alpha 0,30 (0,298 à 0,303
  // sur six zones), aucune gradation hors voile. Ici crème légèrement chaud
  // (#F3EAD8) au même alpha 0,30, et un sépia très doux (0,2) sous les voiles.
  // Partition : voile gauche (depuis le haut) puis, franchement en second, voile
  // droit (depuis le bas) : départs 0 et 0,75 sur une partition de 1,25 → 60 % de
  // la course (règle 2).
  'bleuse-5': {
    focus: [0.645, 0.45],
    desktop: {
      space: 'image',
      light: { color: [243, 234, 216], gain: 0.08 },
      grade: { ...GRADE.creme, mix: 0.2, on: 'veils' },
      textShade: 0.45,       // sous le bloc de texte (posé sur le voile droit) : la lumière du sol se calme (contraste ≥ 4,5:1)
      veilDuration: 0.5,
      veils: {
        color: [0xF3, 0xEA, 0xD8],
        alpha: 0.30,
        // [x0, x1, retard, sens] : voile gauche depuis le haut, voile droit depuis le bas
        items: [
          [0.0000, 0.5091, 0.00,  1],
          [0.7722, 1.0000, 0.75, -1],
        ],
      },
    },
    portrait: {
      space: 'screen',
      light: { color: [243, 234, 216], gain: 0.08 },
      grade: { ...GRADE.creme, mix: 0.2, on: 'veils' },
      veilDuration: 0.5,
      veils: {
        color: [0xF3, 0xEA, 0xD8],
        alpha: 0.30,
        items: [
          [0.00, 0.17, 0.00,  1],
          [0.83, 1.00, 0.75, -1],
        ],
      },
    },
  },
};

const f = (n) => Number(n).toFixed(4);

const STRIPES_VERTEX = `
precision highp float;
attribute vec2 aVertexPosition;
attribute vec2 aUvs;
uniform mat3 translationMatrix;
uniform mat3 projectionMatrix;
varying vec2 vUv;
void main() {
  vUv = aUvs;
  gl_Position = vec4((projectionMatrix * translationMatrix * vec3(aVertexPosition, 1.0)).xy, 0.0, 1.0);
}
`;

// Shader généré pour UNE composition.
//  opts.fade    : mouvement réduit → plus de lumière ni de volets, de simples fondus
//  opts.hasMask : le preset a un masque de détourage (sinon « sujet » = 0)
function buildStripesFragment(comp, opts = {}) {
  const fade = !!opts.fade;
  const P = comp.space === 'screen' ? 'sp' : 'uv';
  const W = comp.space === 'screen' ? ' * uView.z' : '';
  const ext = (x0, x1) => [x0 <= 0 ? -0.01 : x0, x1 >= 1 ? 1.01 : x1];

  const sd = comp.stripeDuration ?? 0.7;
  const bd = comp.bandDuration ?? 0.5;
  const vd = comp.veilDuration ?? 0.85;
  const bandStart = comp.band ? (comp.band.start ?? 0) : 0;

  // Durée totale de la partition : tout est ramené à 0 → 1
  let T = 0;
  (comp.stripes || []).forEach(([, , d]) => { T = Math.max(T, d + sd); });
  if (comp.band) comp.band.segments.forEach(([, , , , d]) => { T = Math.max(T, bandStart + d + bd); });
  if (comp.veils) comp.veils.items.forEach(([, , d]) => { T = Math.max(T, d + vd); });
  if (!T) T = 1;
  const n = (v) => f(v / T);

  // Plume initiale d'un bord (part de la largeur de l'élément), puis 1,5 px (uAA)
  const PLUME = 0.25;

  // 1. stries : lumière (qL), découpe (qC), rubato (qR), décalées dans le temps
  const stripes = (comp.stripes || []).map(([x0, x1, delay, dir, behind]) => {
    const cx = (x0 + x1) / 2, hw = (x1 - x0) / 2;
    const yExpr = dir > 0 ? `${P}.y` : `1.0 - ${P}.y`;
    let shape;
    if (fade)                      shape = `box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * qC`;
    else if (comp.reveal === 'open') shape = `box(${P}.x, ${f(cx)} - ${f(hw)} * qC, ${f(cx)} + ${f(hw)} * qC, aa) * smoothstep(0.0, 0.4, qC)`;
    else                           shape = `box(${P}.x, ${f(x0)}, ${f(x1)}, aa) * reveal(${yExpr}, qC, mix(0.22, 0.05, qC)) * smoothstep(0.0, 0.5, qC)`;
    return `
  tt = (uProgress - ${n(delay)}) / ${n(sd)};
  qL = phrase(tt / 0.7);
  qC = phrase((tt - 0.22) / 0.78);
  qR = phrase((tt - 0.30) / 0.70);
  aa = mix(${f(hw * 2 * PLUME)}, uAA.x, qC * qC);${fade ? '' : `
  lW = max(lW, box(lx, ${f(cx - hw * 2.2)}, ${f(cx + hw * 2.2)}, ${f(hw * 1.6)}) * qL * (1.0 - qC));`}
  m = ${shape};
  pick = step(sW, m);
  sOff = mix(sOff, (1.0 - qR) * 0.03 * uSlide * ${f(dir)}, pick);
  sBehind = mix(sBehind, ${behind ? '1.0' : '0.0'}, pick);
  sW = max(sW, m);`;
  }).join('');

  // 2. bandeau : segments en volet horizontal, lumière de bandeau avant les pièces
  const band = comp.band;
  let bandCode = '';
  if (band) {
    const segs = band.segments.map(([s0, s1, srcX, srcDy, delay, dir]) => {
      const [x0, x1] = ext(s0, s1);
      const lExpr = dir > 0 ? 'l' : '1.0 - l';
      const shape = fade
        ? `box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * bandMask * qC`
        : `box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * bandMask * reveal(${lExpr}, qC, mix(0.22, 0.05, qC)) * smoothstep(0.0, 0.5, qC)`;
      return `
  tt = (uProgress - ${n(bandStart + delay)}) / ${n(bd)};
  qC = phrase(tt);
  qR = phrase((tt - 0.25) / 0.75);
  l = (${P}.x - ${f(x0)}) / ${f(x1 - x0)};
  m = ${shape};
  pick = step(bW, m);
  bSrc = mix(bSrc, vec2(${f(srcX)} + (0.5 - l) * ${f(x1 - x0)}${W} + (1.0 - qR) * 0.02 * uSlide * ${f(dir)}, ${f(srcDy)}), pick);
  bW = min(1.0, bW + m);`;   // segments contigus : on additionne (pas de liseré aux jonctions)
    }).join('');
    const bh = band.y1 - band.y0;
    bandCode = `
  qB = phrase((uProgress - ${n(bandStart)}) / ${n(T - bandStart)});
  float bandMask = box(${P}.y, ${f(band.y0)}, ${f(band.y1)}, mix(${f(bh * PLUME)}, uAA.y, qB * qB));
  float bW = 0.0; vec2 bSrc = vec2(0.0);
  ${segs}${fade ? '' : `
  float bL = box(${P}.y, ${f(band.y0 - bh * 0.6)}, ${f(band.y1 + bh * 0.6)}, ${f(bh * 0.9)})
           * phrase((uProgress - ${n(bandStart - 0.10)}) / ${n(0.5)})
           * (1.0 - bW) * (1.0 - smoothstep(0.6, 1.0, qB));   // s'éteint sous les pièces, puis partout en fin de partition
  col = col * (1.0 + 0.06 * bL) + uLightColor * (uLightGain * bL);`}
  if (bW > 0.0) col = mix(col, tex(vec2(bSrc.x, uv.y + bSrc.y)), bW * uBandAlpha);`;
  }

  // 3. voiles : aplats translucides pleine hauteur, lumière d'abord
  const veilMasks = comp.veils ? comp.veils.items.map(([v0, v1, delay, dir]) => {
    const [x0, x1] = ext(v0, v1);
    const yExpr = dir > 0 ? 'sp.y' : '1.0 - sp.y';
    const shape = fade
      ? `box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * qC`
      : `box(${P}.x, ${f(x0)}, ${f(x1)}, mix(0.04, uAA.x, qC * qC)) * reveal(${yExpr}, qC, mix(0.22, 0.05, qC)) * smoothstep(0.0, 0.5, qC)`;
    return `
  tt = (uProgress - ${n(delay)}) / ${n(vd)};
  qL = phrase(tt / 0.7);
  qC = phrase((tt - 0.2) / 0.8);${fade ? '' : `
  lW = max(lW, box(lx, ${f(x0 - 0.05)}, ${f(x1 + 0.05)}, 0.08) * qL * (1.0 - qC));`}
  m = ${shape};
  vW = max(vW, m);`;
  }).join('') : '';

  // 4. gradation chromatique (duotone ombre → lumière), pondérée selon la composition
  const grade = comp.grade;
  const gradeLine = (weight) => grade ? `
  { vec3 graded = mix(uGradeDark, uGradeLight, dot(col, vec3(0.299, 0.587, 0.114)));
    col = mix(col, graded, uGradeMix * (${weight})); }` : '';
  const gradeMode = grade ? (grade.on || 'all') : 'none';

  return `
#ifdef GL_FRAGMENT_PRECISION_HIGH
precision highp float;
#else
precision mediump float;
#endif
varying vec2 vUv;
uniform sampler2D uImage;
uniform sampler2D uMask;
uniform vec2  uTexSize;
uniform float uBicubic;
uniform vec4  uView;        // zone visible de l'écran en UV photo : x0, y0, largeur, hauteur
uniform float uProgress;    // 0 = photo nue, 1 = composition posée
uniform float uStripeAlpha;
uniform float uBandAlpha;
uniform float uSlide;       // amplitude des glissements (0 = mouvement réduit)
uniform float uVignette;
uniform vec3  uTint;
uniform float uTintMix;
uniform vec3  uVeilColor;
uniform float uVeilAlpha;
uniform vec3  uLightColor;  // couleur de la lumière qui précède la découpe
uniform float uLightGain;
uniform vec3  uGradeDark;
uniform vec3  uGradeLight;
uniform float uGradeMix;
uniform float uBreath;      // -1 → 1, respiration (déplace légèrement la lumière)
uniform vec2  uAA;          // ~1,5 px dans l'espace de la composition
uniform vec4  uTextBox;     // bloc de texte posé sur la photo, en écran (x0, y0, x1, y1)
uniform float uTextShade;   // ombre douce sous ce bloc (0 = aucune)

// Catmull-Rom en 9 lectures bilinéaires : net quand la photo est agrandie
vec3 tex(vec2 p) {
  if (uBicubic < 0.5) return texture2D(uImage, clamp(p, 0.0, 1.0)).rgb;
  vec2 pos = p * uTexSize;
  vec2 t1  = floor(pos - 0.5) + 0.5;
  vec2 fr  = pos - t1;
  vec2 w0  = fr * (-0.5 + fr * (1.0 - 0.5 * fr));
  vec2 w1  = 1.0 + fr * fr * (-2.5 + 1.5 * fr);
  vec2 w2  = fr * (0.5 + fr * (2.0 - 1.5 * fr));
  vec2 w3  = fr * fr * (-0.5 + 0.5 * fr);
  vec2 w12 = w1 + w2;
  vec2 p0  = (t1 - 1.0) / uTexSize;
  vec2 p12 = (t1 + w2 / w12) / uTexSize;
  vec2 p3  = (t1 + 2.0) / uTexSize;
  vec3 c =
      (texture2D(uImage, vec2(p0.x,  p0.y)).rgb  * w0.x
     + texture2D(uImage, vec2(p12.x, p0.y)).rgb  * w12.x
     + texture2D(uImage, vec2(p3.x,  p0.y)).rgb  * w3.x) * w0.y
    + (texture2D(uImage, vec2(p0.x,  p12.y)).rgb * w0.x
     + texture2D(uImage, vec2(p12.x, p12.y)).rgb * w12.x
     + texture2D(uImage, vec2(p3.x,  p12.y)).rgb * w3.x) * w12.y
    + (texture2D(uImage, vec2(p0.x,  p3.y)).rgb  * w0.x
     + texture2D(uImage, vec2(p12.x, p3.y)).rgb  * w12.x
     + texture2D(uImage, vec2(p3.x,  p3.y)).rgb  * w3.x) * w3.y;
  return clamp(c, 0.0, 1.0);
}
${PHRASE_GLSL}

float box(float v, float a, float b, float aa) {
  return smoothstep(a - aa, a + aa, v) * (1.0 - smoothstep(b - aa, b + aa, v));
}

// volet : 0 → rien, 1 → couvert ; soft = largeur de la plume le long de t
float reveal(float t, float q, float soft) {
  float edge = q * (1.0 + soft);
  return 1.0 - smoothstep(edge - soft, edge, t);
}

void main() {
  vec2 uv  = vUv;
  vec2 sp  = (uv - uView.xy) / uView.zw;   // position à l'écran (0 → 1)
  vec3 col = tex(uv);
  float subject = ${opts.hasMask ? 'texture2D(uMask, uv).r' : '0.0'};
  float tt; float qL; float qC; float qR; float qB; float aa; float m; float pick; float l;
  float lW = 0.0;                           // lumière avant découpe (stries, voiles)
  float lx = ${P}.x - 0.006 * uBreath;      // la lumière respire un peu
  float vW = 0.0;

  // 1. masques des stries (photo pivotée de 180°, teinte éventuelle)
  ${stripes ? `float sW = 0.0; float sOff = 0.0; float sBehind = 0.0;
  ${stripes}` : ''}

  // 2. masques des voiles
  ${veilMasks}

  // 3. la lumière passe avant la forme
  col = col * (1.0 + 0.06 * lW) + uLightColor * (uLightGain * lW);

  ${stripes ? `if (sW > 0.0) {
    vec3 sc = mix(tex(vec2(1.0 - uv.x, 1.0 - uv.y + sOff)), uTint, uTintMix);
    float sCover = sW * uStripeAlpha * (1.0 - sBehind * subject);
    col = mix(col, sc, sCover);
  }` : ''}

  // 4. bandeau horizontal (dessus, opaque)
  ${bandCode}

  // 4 bis. ombre très plumée sous le bloc de texte : la photo s'y assombrit AVANT le
  //        voile (qui reste uniforme), pour que le texte blanc garde son contraste
  if (uTextShade > 0.0) {
    float tb = box(sp.x, uTextBox.x, uTextBox.z, 0.045) * box(sp.y, uTextBox.y, uTextBox.w, 0.06);
    col *= 1.0 - uTextShade * tb;
  }

  // 5. voiles translucides (sépia doux dessous)
  ${comp.veils ? `${gradeMode === 'veils' ? gradeLine('vW') : ''}
  col = mix(col, uVeilColor, vW * uVeilAlpha);` : ''}

  // 6. gradation chromatique pendant la construction
  ${gradeMode === 'all' ? gradeLine('phrase(uProgress)') : ''}
  ${gradeMode === 'outside' ? gradeLine('phrase(uProgress) * (1.0 - subject)') : ''}

  // 7. fondu des bords vers le noir de la section
  if (uVignette > 0.0) {
    col *= smoothstep(0.0, uVignette, uv.x) * smoothstep(0.0, uVignette, 1.0 - uv.x)
         * smoothstep(0.0, uVignette, uv.y) * smoothstep(0.0, uVignette, 1.0 - uv.y);
  }

  gl_FragColor = vec4(col, 1.0);
}
`;
}

const smooth01 = (t) => { t = Math.min(1, Math.max(0, t)); return t * t * (3 - 2 * t); };
const clamp01  = (t) => Math.min(1, Math.max(0, t));

class StripesHero {
  constructor(section, motion = {}) {
    this.section  = section;
    this.frame    = section.querySelector('[data-hero-frame]') || section;
    this.canvas   = section.querySelector('[data-hero-canvas]');
    this.img      = section.querySelector('[data-hero-img]');
    this.preset   = STRIPES_PRESETS[section.dataset.preset];
    if (!this.preset) console.warn(`[StripesHero] preset inconnu « ${section.dataset.preset} » : photo seule`);
    this.motion   = motion;
    this.progress = 0;
    this.textProgress = 0;
    this.y        = 0;
    this.breath   = 0;
    this.dirty    = true;
    this.ready    = false;
    this.shaders  = {};
    this.lastScrollY = null;
    this.lastBreathRender = 0;
    section.__hero = this;
    if (this.img && !this.img.complete) this.img.addEventListener('load', () => this.layout(), { once: true });

    const touch = !!motion.touch;
    this.params = {
      bandAlpha:       1.0,
      slide:           motion.reduced ? 0 : 1,      // rubato interne (0 si mouvement réduit)
      // Ralenti (ordinateur) : la photo absorbe `linger` de la vitesse de scroll
      // au plus fort, sur ± `lingerSpan` hauteurs d'écran. Adouci : 0,5 / 1,0
      // (SPEC, point 10). Baisser `linger` ferait partir la photo PLUS vite.
      linger:          motion.linger ? 0.5 : 0,
      lingerSpan:      1.0,
      // Fenêtre de construction, en hauteurs d'écran, autour du moment c où la
      // photo est alignée en haut de l'écran (règle 10 : 60 à 100 % d'un écran).
      // La composition se pose juste après c : le tableau achevé est vu entier.
      progressBefore:  touch ? 0.65 : 0.75,
      progressAfter:   touch ? 0.05 : 0.10,
      progressTop:     0.6,                         // hero en haut de page : fenêtre 0 → progressTop·vh
      progressMinSpan: 0.4,
      progressSpanBottom: 0.6,                      // hero de pied : fenêtre de composition (écrans)
      poseOffset:      touch ? 0.25 : 0.04,         // hero de pied : cadre encore à `poseOffset`·vh du haut à la pose
      // Texte de la photo : n'entre qu'APRÈS la pose de la composition (règle 8 :
      // une seule chose parle à la fois), sur `textSpan` écran de scroll ; le hero
      // de pied réserve l'espace nécessaire avant le colophon (`gap`)
      textGap:         0,
      textSpan:        touch ? 0.5 : 0.6,
      textSpanBottom:  touch ? 0.35 : 0.42,         // (fin de page : le cadre remonte de reserve − poseOffset·vh + ralenti, la tête du chef reste entière)
      // Respiration (règle 6) : amplitude d'échelle, période (s), cadence hors scroll (ms)
      breath:          motion.reduced ? 0 : 0.003,
      breathPeriod:    8,
      breathInterval:  33,
      maxPixels:       9e6,
      portraitBelow:   0.9,
    };
  }

  // Géométrie du scroll — appelée par main.js pour chaque hero, DANS L'ORDRE de la page
  measure() {
    const vh    = this.frame.offsetHeight || window.innerHeight;
    if (!vh) return;
    const top   = this.section.getBoundingClientRect().top + window.scrollY;
    const after = Math.max(0, document.documentElement.scrollHeight - (top + this.section.offsetHeight));
    const P     = this.params;
    const h     = P.lingerSpan * vh;
    const L     = P.linger > 0 ? (P.linger * h) / 0.75 : 0;
    const sMin  = -top;
    const atTop    = top < 1;
    const atBottom = !atTop && after < 0.5 * vh;
    const hasText  = !!this.section.querySelector('[data-reveal="hero"]');
    const textSpan = (atBottom ? P.textSpanBottom : P.textSpan) * vh;
    // Hero de pied : scroll réservé après la pose (la fenêtre du texte, ou 8 % d'écran)
    const reserve  = hasText ? textSpan + P.textGap * vh + 0.04 * vh : 0.08 * vh;
    let c, y0, extra, gap = 0;
    if (atTop) {
      c = sMin; y0 = L / 2; extra = L / 2;
    } else if (atBottom) {
      // On veut le cadre à `poseOffset`·vh du haut de l'écran quand la composition
      // se pose, à p1 = sEnd − reserve. Avec y(s) = L·smooth01((s − sEnd + h) / 2h)
      // et cadre(s) = y(s) − s :  sEnd = reserve − off + L·smooth01((h − reserve) / 2h).
      // L'espace manquant est réservé en noir sous la photo (`gap`), avant le colophon.
      const off = P.poseOffset * vh;
      const sEndWanted = reserve - off + L * smooth01((h - reserve) / (2 * h));
      gap = Math.max(0, Math.round(sEndWanted - L / 2 - after));
      extra = L / 2 + gap; c = extra + after; y0 = L * smooth01((sMin - c + h) / (2 * h));
    } else {
      c = L / 2; y0 = L * smooth01((sMin - c + h) / (2 * h)); extra = Math.max(0, L - y0);
    }
    const sEnd = vh + extra + after - window.innerHeight;
    let p0, p1;
    if (atTop) {
      p0 = 0;
      p1 = Math.max(0.2 * vh, Math.min(P.progressTop * vh, sEnd));
    } else if (atBottom) {
      p1 = sEnd - reserve;
      p0 = Math.max(sMin, p1 - P.progressSpanBottom * vh);
    } else {
      p1 = Math.max(c + P.progressAfter * vh, Math.max(sMin, c - P.progressBefore * vh) + P.progressMinSpan * vh);
      p1 = Math.min(p1, sEnd);
      p0 = Math.min(Math.max(sMin, c - P.progressBefore * vh), p1 - 0.2 * vh);
    }
    // Texte posé sur la photo : APRÈS la composition (règle 8), et toujours avant la fin de page
    let t0 = p1 + P.textGap * vh;
    let t1 = t0 + textSpan;
    if (t1 > sEnd) { t1 = sEnd; t0 = Math.max(p1, t1 - textSpan); }
    this.geo = { vh, top, h, L, c, y0, extra, gap, p0, p1, t0, t1, sEnd, atTop, atBottom };
    this.section.style.setProperty('--hero-extra', `${Math.round(extra)}px`);
  }

  // Position du cadre à l'écran pour un scroll s (relatif au haut de la section) — pour les tests
  frameTopAt(s) {
    const g = this.geo;
    if (!g) return 0;
    let y = g.L > 0 ? g.L * smooth01((s - g.c + g.h) / (2 * g.h)) - g.y0 : 0;
    y = Math.min(g.extra, Math.max(0, y));
    return y - s;
  }

  async init() {
    const p = this.preset;
    if (!p) throw new Error(`preset inconnu « ${this.section.dataset.preset} »`);
    this.app = new PIXI.Application({
      view:            this.canvas,
      width:           1,
      height:          1,
      autoStart:       false,
      antialias:       false,
      backgroundColor: 0x000000,
      resolution:      1,
      autoDensity:     true,
    });
    // Canvas décoratif : on retire les écouteurs de Pixi (sinon touch-action:none)
    this.app.renderer.events?.setTargetElement?.(null);
    this.canvas.style.touchAction = 'pan-y';

    this.canvas.addEventListener('webglcontextlost', () => this.section.classList.remove('is-ready'));
    this.canvas.addEventListener('webglcontextrestored', () => {
      requestAnimationFrame(() => {
        if (!this.ready) return;
        this.app.render();
        this.dirty = false;
        this.section.classList.add('is-ready');
      });
    });

    let texture;
    if (this.img) {
      await this.img.decode().catch(() => {});
      if (this.img.naturalWidth) texture = PIXI.Texture.from(this.img);
    }
    if (!texture) texture = await PIXI.Assets.load(this.section.dataset.image);
    const mask = p.mask ? await PIXI.Assets.load(p.mask) : PIXI.Texture.WHITE;

    texture.baseTexture.mipmap = PIXI.MIPMAP_MODES.OFF;
    this.texture = texture;
    this.mask    = mask;
    this.texSize = { w: texture.baseTexture.realWidth, h: texture.baseTexture.realHeight };

    const geometry = new PIXI.Geometry()
      .addAttribute('aVertexPosition', [0, 0, 1, 0, 1, 1, 0, 1], 2)
      .addAttribute('aUvs',            [0, 0, 1, 0, 1, 1, 0, 1], 2)
      .addIndex([0, 1, 2, 0, 2, 3]);

    this.compKey = this.pickComposition();
    this.shader  = this.makeShader(this.compKey);
    this.mesh    = new PIXI.Mesh(geometry, this.shader);
    this.app.stage.addChild(this.mesh);

    this.ready = true;
    try {
      this.layout();
      const r = this.app.renderer, gl = r.gl;
      const hp = gl.getShaderPrecisionFormat(gl.FRAGMENT_SHADER, gl.HIGH_FLOAT);
      if (!hp || hp.precision < 23) throw new Error('précision highp indisponible en fragment');
      // Préchauffage : compiler le shader et envoyer les textures maintenant
      this.app.render();
      this.dirty = false;
      const glp = this.shader.program.glPrograms[r.CONTEXT_UID];
      if (!glp || !gl.getProgramParameter(glp.program, gl.LINK_STATUS)) throw new Error('shader non compilé');
    } catch (err) {
      this.ready = false;
      throw err;
    }
    this.section.classList.add('is-ready');
  }

  pickComposition() {
    const w = this.frame.clientWidth, h = this.frame.clientHeight;
    return (this.preset.portrait && h > 0 && w / h < this.params.portraitBelow) ? 'portrait' : 'desktop';
  }

  makeShader(key) {
    if (this.shaders[key]) return this.shaders[key];
    const comp  = this.preset[key];
    const veils = comp.veils || { color: [255, 255, 255], alpha: 0 };
    const light = comp.light || { color: [255, 255, 255], gain: 0 };
    const grade = comp.grade || { dark: [0, 0, 0], light: [255, 255, 255], mix: 0 };
    const frag  = buildStripesFragment(comp, { fade: !!this.motion.reduced, hasMask: !!this.preset.mask });
    const shader = PIXI.Shader.from(STRIPES_VERTEX, frag, {
      uImage:       this.texture,
      uMask:        this.mask,
      uTexSize:     new Float32Array([this.texSize.w, this.texSize.h]),
      uBicubic:     1,
      uView:        new Float32Array([0, 0, 1, 1]),
      uProgress:    this.progress,
      uStripeAlpha: comp.stripeAlpha ?? 1,
      uBandAlpha:   this.params.bandAlpha,
      uSlide:       this.params.slide,
      uVignette:    comp.vignette ?? 0,
      uTint:        new Float32Array((comp.tint || [0, 0, 0]).map((c) => c / 255)),
      uTintMix:     comp.tintMix ?? 0,
      uVeilColor:   new Float32Array(veils.color.map((c) => c / 255)),
      uVeilAlpha:   veils.alpha,
      uLightColor:  new Float32Array(light.color.map((c) => c / 255)),
      uLightGain:   this.motion.reduced ? 0 : light.gain,
      uGradeDark:   new Float32Array(grade.dark.map((c) => c / 255)),
      uGradeLight:  new Float32Array(grade.light.map((c) => c / 255)),
      uGradeMix:    grade.mix,
      uBreath:      0,
      uAA:          new Float32Array([0.001, 0.001]),
      uTextBox:     new Float32Array([0, 0, 0, 0]),
      uTextShade:   comp.textShade || 0,
    });
    this.shaders[key] = shader;
    return shader;
  }

  // Cadrage cover centré sur le point focal, sans jamais découvrir de bord
  cover(w, h, tw, th) {
    const ratio = tw / th;
    let mw = w, mh = w / ratio;
    if (mh < h) { mh = h; mw = h * ratio; }
    const [fx, fy] = (this.preset && this.preset.focus) || [0.5, 0.5];
    const clampTo = (v, min) => Math.min(0, Math.max(min, v));
    return { mw, mh, x: clampTo(w / 2 - fx * mw, w - mw), y: clampTo(h / 2 - fy * mh, h - mh) };
  }

  layout() {
    const w = this.frame.clientWidth, h = this.frame.clientHeight;
    if (!w || !h) return;

    if (this.img && this.img.naturalWidth) {
      const c = this.cover(w, h, this.img.naturalWidth, this.img.naturalHeight);
      this.img.style.objectPosition = `${c.x}px ${c.y}px`;
    }
    if (!this.ready) return;

    const key = this.pickComposition();
    if (key !== this.compKey) {
      this.compKey = key;
      this.shader = this.makeShader(key);
      this.mesh.shader = this.shader;
    }

    const dpr = window.devicePixelRatio || 1;
    const res = Math.max(1, Math.min(dpr, 3, Math.sqrt(this.params.maxPixels / (w * h))));
    const r = this.app.renderer;
    let resized = false;
    if (!this.view || this.view.w !== w || this.view.h !== h || this.view.res !== res) {
      r.resolution = res;
      r.resize(w, h);
      this.view = { w, h, res };
      resized = true;
    }

    const { mw, mh, x, y } = this.cover(w, h, this.texSize.w, this.texSize.h);
    this.base = { mw, mh, x, y, w, h };
    const u = this.shader.uniforms;
    const screen = this.preset[this.compKey].space === 'screen';
    u.uAA[0] = 0.75 / ((screen ? w : mw) * res);
    u.uAA[1] = 0.75 / ((screen ? h : mh) * res);
    u.uBicubic = mw * res > this.texSize.w ? 1 : 0;
    u.uProgress = this.progress;
    // Bloc de texte posé sur la photo (en écran, élargi de 24 px) : pour l'ombre douce du shader
    const textEl = this.section.querySelector('.hero__text');
    if (textEl && u.uTextShade > 0) {
      const fr = this.frame.getBoundingClientRect(), tr = textEl.getBoundingClientRect();
      u.uTextBox[0] = (tr.left - fr.left - 24) / w;  u.uTextBox[1] = (tr.top - fr.top - 24) / h;
      u.uTextBox[2] = (tr.right - fr.left + 24) / w; u.uTextBox[3] = (tr.bottom - fr.top + 24) / h;
    }
    this.applyBreath(this.breath);        // pose le mesh et uView
    if (resized) { this.app.render(); this.dirty = false; }
  }

  // Respiration : échelle ± breath autour du centre de l'écran, uView suit
  applyBreath(b) {
    this.breath = b;
    if (!this.base || !this.shader) return;
    const { mw, mh, x, y, w, h } = this.base;
    const k  = 1 + this.params.breath * b;
    const sx = mw * k, sy = mh * k;
    const px = w / 2 - (w / 2 - x) * k, py = h / 2 - (h / 2 - y) * k;
    this.mesh.scale.set(sx, sy);
    this.mesh.position.set(px, py);
    const u = this.shader.uniforms;
    u.uView[0] = -px / sx; u.uView[1] = -py / sy; u.uView[2] = w / sx; u.uView[3] = h / sy;
    u.uBreath = b;
    this.dirty = true;
  }

  setBreath(on) {
    this.params.breath = (on && !this.motion.reduced) ? 0.003 : 0;
    this.applyBreath(on ? this.breath : 0);
  }

  setProgress(p) {
    this.progress = p;
    if (this.shader) this.shader.uniforms.uProgress = p;
    this.section.style.setProperty('--hero-progress', p.toFixed(3));
    this.dirty = true;
  }

  // Appelée à chaque frame par main.js, avec la position de scroll.
  update(scrollY) {
    const g = this.geo;
    if (!g) return;
    const s = scrollY - g.top;

    // 1. Ralenti progressif de la photo (ordinateur uniquement) — fonction de s
    if (g.L > 0) {
      let y = g.L * smooth01((s - g.c + g.h) / (2 * g.h)) - g.y0;
      y = Math.min(g.extra, Math.max(0, y));
      const dpr = window.devicePixelRatio || 1;
      y = Math.round(y * dpr) / dpr;
      if (y !== this.y || !this.frame.style.transform) {
        this.y = y;
        this.frame.style.transform = `translate3d(0, ${y}px, 0)`;
      }
    } else if (this.y) {
      this.y = 0;
      this.frame.style.transform = '';
    }

    // 2. Progression de l'effet : fonction exacte de la position (aller = retour)
    const target = clamp01((s - g.p0) / (g.p1 - g.p0));
    if (target !== this.progress) this.setProgress(target);

    // 3. Entrée du texte posé sur la photo (après la composition)
    const tp = clamp01((s - g.t0) / (g.t1 - g.t0));
    if (tp !== this.textProgress) {
      this.textProgress = tp;
      this.section.style.setProperty('--text-p', tp.toFixed(3));
    }
    if (g.atTop) {
      const hint = clamp01(1 - s / (0.12 * g.vh));
      if (hint !== this.hint) { this.hint = hint; this.section.style.setProperty('--hint', hint.toFixed(3)); }
    }

    // 4. Visibilité (équivalent d'un IntersectionObserver, sans latence)
    const topV = g.top + this.y - scrollY;
    const visible = topV < window.innerHeight && topV + g.vh > 0;

    // 5. Respiration : seulement visible, onglet actif ; ~30 i/s hors scroll
    if (this.ready && visible && this.params.breath > 0 && !document.hidden) {
      const now = performance.now();
      const scrolling = this.lastScrollY !== null && scrollY !== this.lastScrollY;
      if (scrolling || now - this.lastBreathRender >= this.params.breathInterval) {
        this.applyBreath(Math.sin((now / 1000) * 2 * Math.PI / this.params.breathPeriod));
        this.lastBreathRender = now;
      }
    }
    this.lastScrollY = scrollY;

    // 6. Rendu seulement si quelque chose a changé ET que la photo est visible
    if (this.ready && this.dirty && visible) {
      this.app.render();
      this.dirty = false;
    }
  }
}

window.StripesHero = StripesHero;
window.phrase = phrase;
