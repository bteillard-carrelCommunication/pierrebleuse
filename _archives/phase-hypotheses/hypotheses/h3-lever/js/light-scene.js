/*
 * LightScene — Hypothèse 3 « Lever » (la lumière et le silence).
 *
 * Chaque photo est dans la pénombre ; c'est la LUMIÈRE qui la révèle, jamais
 * la découpe. Rendu Pixi (Mesh + shader généré pour une composition) :
 *
 *   couleur = photo × (ambiant + spot + colonnes + barre + voiles) + brume
 *
 *   - ambiant  : 0,10 à 0,14, la photo est devinée, pas vue ;
 *   - spot     : ellipse très douce (chute gaussienne, plume ≥ 40 % de la
 *                largeur), position / rayon / intensité fonction du scroll ;
 *   - colonnes : bandes verticales de lumière à bords en dégradé (plume 25 à
 *                40 % de leur largeur), qui montent ou descendent avec un bord
 *                vivant (large puis serré). À l'intérieur, la photo pivotée de
 *                180° apparaît APRÈS la lumière (règle 7), avec une plume qui se
 *                resserre (règle 4) et un glissement ≤ 3 % (règle 5) ;
 *   - barre    : le bandeau du DA devenu barre de lumière horizontale ;
 *   - voiles   : colonnes crème à plume très large (pied de page) ;
 *   - brume    : la lumière est visible dans l'air (comme les faisceaux bleus
 *                de la scène), même là où la photo est noire.
 *
 * Toute la temporalité est une fonction exacte de uProgress (donc du scroll) :
 * chaque phase a un retard et une durée, et passe par `anac()` — anacrouse
 * (0 → 10 % sur le premier tiers) puis résolution longue (pente max 2,32).
 * Seule la respiration (uBreath) vit en temps réel, à 8 images/s, quand la
 * photo est à l'écran et l'onglet visible ; coupée si prefers-reduced-motion
 * (où toutes les phases deviennent un seul fondu, uSimple).
 *
 * Hérité du moteur ../../js/stripes-effect.js : cadrage cover sur un point
 * focal, composition portrait en coordonnées écran, bicubique, rendu à la
 * demande, ralenti sans épinglage, canvas décoratif (pointer-events none).
 */
(function () {
'use strict';

const f = (n) => Number(n).toFixed(4);
const clamp01  = (t) => Math.min(1, Math.max(0, t));
const smooth01 = (t) => { t = clamp01(t); return t * t * (3 - 2 * t); };

// Anacrouse (règle 1) + asymétrie (règle 3) : ease-in-sine jusqu'à 10 % au
// premier tiers, puis résolution longue (ease-out d'exposant 1,6) ; raccord très
// large (0,18 → 0,78) : pente maximale 2,32 (mesurée). Un ease-out-quart raccordé
// court donnait une pente de 5,4 et un ease-out-cubic 4,1 : les deux faisaient
// « arriver en bloc » spot, barre et voiles sur la planche-contact (82 px par image).
function anac(t) {
  t = clamp01(t);
  const a = 0.10 * (1 - Math.cos(Math.min(t, 1 / 3) * 1.5 * Math.PI));
  const u = clamp01((t - 1 / 3) * 1.5);
  const b = 0.10 + 0.90 * (1 - Math.pow(1 - u, 1.6));
  return a + (b - a) * smooth01((t - 0.18) / 0.60);
}

// Grille de colonnes alignée sur la maquette bleuse-4 (UV photo), entrées en
// pupitres (règle 2) : groupes de 2-3 colonnes, du sujet vers les bords, avec
// un petit décalage dans le groupe pour ne jamais balayer régulièrement.
// Sens d'une colonne : 1 = le front part du haut, -1 = il monte du bas.
function columnGrid({ period, width, offset, count, skip = [], focusX, delay0, spread, groups = 5, dir = -1 }) {
  const out = [];
  const maxDist = Math.max(focusX, 1 - focusX);
  let k2 = 0;
  for (let k = 0; k < count; k++) {
    if (skip.includes(k)) continue;
    const x0 = offset + k * period, x1 = x0 + width;
    const d = Math.abs((x0 + x1) / 2 - focusX) / maxDist;
    const g = Math.min(groups - 1, Math.floor(d * groups));
    const jitter = ((k2 * 7) % 3) * 0.012;           // 0, 0,012 ou 0,024 dans le groupe
    out.push([x0, x1, delay0 + g * (spread / (groups - 1)) + jitter, dir, true]);
    k2++;
  }
  return out;
}

// Grille régulière en coordonnées écran (portrait)
function screenColumns({ count, focusX = 0.5, delay0, spread, dir = -1 }) {
  const period = 1 / count, half = period / 4;
  const maxDist = Math.max(focusX, 1 - focusX);
  const out = [];
  for (let k = 0; k < count; k++) {
    const cx = (k + 0.5) * period;
    const d = Math.abs(cx - focusX) / maxDist;
    const g = Math.min(2, Math.floor(d * 3));
    out.push([cx - half, cx + half, delay0 + g * (spread / 2) + (k % 2) * 0.03, dir, true]);
  }
  return out;
}

const GOLD   = [1.00, 0.86, 0.60];
const WARM   = [1.00, 0.80, 0.50];
const BLUE   = [0.50, 0.70, 1.00];
const CREAM  = [1.00, 0.95, 0.86];
const LAVANDE = [90 / 255, 99 / 255, 142 / 255];

const PRESETS = {
  // ------------------------------------------------------------------
  // Ouverture — bleuse-1 (mains ouvertes, bokeh doré)
  // ------------------------------------------------------------------
  'bleuse-1': {
    focus: [0.55, 0.45],
    mask:  '../../assets/bleuse-1-masque.png',
    ambient: 0.12, ambientColor: [1.0, 0.97, 0.92],
    haze: 0.07,
    // Fenêtre de l'ouverture : exactement 1 écran de scroll (ordinateur), avec un ralenti fort
    // (la photo défile à 16 % au plus fort, sur ± 1,7 écran) : le visage reste à l'écran
    // jusqu'au bout de la barre (haut du visage à +110 px au début de la barre, −7 px à p = 1).
    params: { progressMinSpan: 1.0, progressAfter: 0.05, linger: 0.84, lingerSpan: 1.7 },
    touch:  { progressMinSpan: 0.55, linger: 0 },      // tactile : la photo défile à 100 %, fenêtre 0,55 écran (la barre finit à 144 px du haut)
    desktop: {
      space: 'image',
      // 1. le spot naît en haut à gauche (sous le logo), petit et faible (visible dans la brume),
      //    grandit et descend vers le visage à vitesse constante (geo/icurve 'linear' : voir applyProgress)
      spot: {
        color: GOLD, delay: 0.0, duration: 0.70, geo: 'linear', icurve: 'linear', hazeFrom: 0.32, hazeTo: 0.05,
        from: { uv: [0.10, 0.12], radius: [0.15, 0.14], intensity: 0.22 },
        to:   { uv: [0.55, 0.44], radius: [0.38, 0.36], intensity: 1.00 },
      },
      // 2. le fond doré, pendant la descente du spot (posé avant le texte, règle 8) : l'ambiant monte, une lumière chaude sur le bokeh
      fill: { color: WARM, uv: [0.80, 0.26], radius: [0.50, 0.46], intensity: 0.30, ambientTo: 0.17, delay: 0.10, duration: 0.32 },
      // 3. deux faisceaux à la place des stries du DA (x 0,18–0,35) : lumière d'abord, découpe ensuite ;
      //    courses de 0,48 (43 % d'écran), retards 0,46 / 0,52 ; la barre entre à 0,48 : les trois
      //    pupitres du final s'étalent sur 0,46 → 0,72 (segments), soit 54 % de la course
      beams: {
        color: [1.0, 0.92, 0.74], intensity: 0.80, plume: 0.38, fade: 0.55,
        cutAlpha: 0.35, cutTint: [0, 0, 0], cutTintMix: 0, cutInset: 0.14, cutAfter: 0.34, cutPlume: 0.28,
        duration: 0.48,
        items: [
          [0.182, 0.262, 0.46,  1, false],
          [0.272, 0.353, 0.52, -1, false],
        ],
      },
      // 4. le bandeau devient une barre de lumière, allumée en dernier (course 0,52 = 47 % d'écran) :
      //    `lag` = retard local du balayage (0 au sujet, 0,30 aux bords : la lumière part du sujet),
      //    fragments retournés à 0,5 qui apparaissent après la lumière (segDuration 0,26)
      band: {
        y0: 0.745, y1: 0.824, color: [1.0, 0.90, 0.72], intensity: 0.62, plume: 0.40, from: 0.55, lag: 0.30,
        delay: 0.48, duration: 0.52, cut: 0.5, segDuration: 0.26, segPlume: 0.02,
        segments: [     // [x0, x1, centre source X, décalage source Y, retard, sens] — du sujet vers les bords
          [0.355, 0.445, 0.55,  0.12, 0.62, -1],
          [0.445, 0.690, 0.60,  0.10, 0.64,  1],
          [0.130, 0.355, 0.78, -0.10, 0.68,  1],
          [0.690, 1.000, 0.30, -0.04, 0.72, -1],
        ],
      },
      vignette: [0.05, 0.10],
    },
    portrait: {
      space: 'screen',
      spot: {
        color: GOLD, delay: 0.0, duration: 0.70, geo: 'linear', icurve: 'linear', hazeFrom: 0.32, hazeTo: 0.05,
        from: { pos: [0.18, 0.10], radius: [0.26, 0.24], intensity: 0.22 },
        to:   { uv: [0.55, 0.44], radius: [0.95, 0.88], intensity: 1.00 },
      },
      fill: { color: WARM, pos: [0.95, 0.20], radius: [1.1, 1.0], intensity: 0.28, ambientTo: 0.17, delay: 0.10, duration: 0.32 },
      beams: {
        color: [1.0, 0.92, 0.74], intensity: 0.80, plume: 0.38, fade: 0.55,
        cutAlpha: 0.35, cutTint: [0, 0, 0], cutTintMix: 0, cutInset: 0.14, cutAfter: 0.34, cutPlume: 0.28,
        duration: 0.48,
        items: [
          [0.03, 0.17, 0.46,  1, true],
          [0.81, 0.95, 0.52, -1, true],
        ],
      },
      // barre un peu plus haute qu'à l'écran large (0,72–0,80 du cadre) : à p = 1 elle finit à 144–211 px
      // du haut de l'écran, sous le logo et au-dessus de la première ligne du lorem (686 px du cadre)
      band: {
        y0: 0.72, y1: 0.80, color: [1.0, 0.90, 0.72], intensity: 0.62, plume: 0.40, from: 0.5, lag: 0.30,
        delay: 0.48, duration: 0.52, cut: 0.5, segDuration: 0.26, segPlume: 0.03,
        segments: [
          [0.30, 0.45, 0.55,  0.12, 0.62, -1],
          [0.45, 0.72, 0.60,  0.10, 0.64,  1],
          [0.00, 0.30, 0.78, -0.10, 0.68,  1],
          [0.72, 1.00, 0.30, -0.04, 0.72, -1],
        ],
      },
      vignette: [0.0, 0.08],
    },
  },

  // ------------------------------------------------------------------
  // Deuxième photo — bleuse-3 (en frac devant l'orchestre) : lumière froide
  // ------------------------------------------------------------------
  'bleuse-3': {
    focus: [0.61, 0.40],
    mask:  '../../assets/bleuse-3-masque.png',
    ambient: 0.10, ambientColor: [0.80, 0.88, 1.0],
    haze: 0.075,
    params: { progressBefore: 0.75, progressAfter: 0.35 },
    touch:  { progressBefore: 0.60, progressAfter: 0.10 },
    desktop: {
      space: 'image',
      // le chef d'abord : un spot chaud sur lui
      spot: {
        color: [1.0, 0.88, 0.70], delay: 0.0, duration: 0.42, hazeFrom: 0.10, hazeTo: 0.04,
        from: { uv: [0.61, 0.36], radius: [0.05, 0.05], intensity: 0.20 },
        to:   { uv: [0.61, 0.40], radius: [0.23, 0.22], intensity: 0.95 },
      },
      // puis les colonnes de lumière bleue montent du bas, aux positions de la grille du DA :
      // profil entièrement en dégradé (plume 0,5 = aucun aplat), bien plus vives à leur base
      beams: {
        color: BLUE, intensity: 0.70, plume: 0.5, fade: 0.30,
        cutAlpha: 0.55, cutTint: LAVANDE, cutTintMix: 0.5, cutInset: 0.20, cutAfter: 0.34, cutPlume: 0.30,
        duration: 0.42,     // course d'une colonne ; retards étalés sur 0,30 (71 % de la course), 5 pupitres
        items: columnGrid({
          period: 60.25 / 992, width: 30.5 / 992, offset: -1.5 / 992, count: 17,
          skip: [9, 10, 11], focusX: 0.61, delay0: 0.28, spread: 0.30, groups: 5,
        }),
      },
      vignette: [0.05, 0.10],
    },
    portrait: {
      space: 'screen',
      spot: {
        color: [1.0, 0.88, 0.70], delay: 0.0, duration: 0.42, hazeFrom: 0.10, hazeTo: 0.04,
        from: { uv: [0.61, 0.36], radius: [0.12, 0.12], intensity: 0.20 },
        to:   { uv: [0.61, 0.40], radius: [0.55, 0.52], intensity: 0.95 },
      },
      beams: {
        color: BLUE, intensity: 0.70, plume: 0.5, fade: 0.30,
        cutAlpha: 0.55, cutTint: LAVANDE, cutTintMix: 0.5, cutInset: 0.20, cutAfter: 0.34, cutPlume: 0.30,
        duration: 0.42,
        items: screenColumns({ count: 8, focusX: 0.5, delay0: 0.28, spread: 0.30 }),
      },
      vignette: [0.0, 0.08],
    },
  },

  // ------------------------------------------------------------------
  // Pied de page — bleuse-5 (accroupi, noir et blanc) : deux voiles de lumière crème
  // ------------------------------------------------------------------
  'bleuse-5': {
    focus: [0.645, 0.45],
    ambient: 0.11, ambientColor: [1.0, 0.98, 0.94],
    haze: 0.05,
    // Fenêtre du pied de page : le dernier écran de scroll entier (1,0 écran) : les voiles
    // courent chacun sur 0,62 (56 % d'écran), retards 0 / 0,38 (61 % de la course, règle 2)
    params: { progressBefore: 1.0, progressAfter: 0 },
    touch:  { progressBefore: 0.70, progressAfter: 0 },
    desktop: {
      space: 'image',
      veils: {
        color: CREAM, intensity: 0.72, plume: 0.42, duration: 0.62,
        items: [            // [x0, x1, retard, sens] : gauche depuis le haut, droite depuis le bas
          [0.000, 0.509, 0.00,  1],
          [0.772, 1.000, 0.38, -1],
        ],
      },
      // le chef, dans la pénombre entre les deux voiles, s'éclaire doucement en dernier (0,60 → 1,0)
      spot: {
        color: [1.0, 0.94, 0.84], delay: 0.60, duration: 0.40, hazeFrom: 0.04, hazeTo: 0.04,
        from: { uv: [0.645, 0.42], radius: [0.06, 0.06], intensity: 0.0 },
        to:   { uv: [0.645, 0.42], radius: [0.16, 0.15], intensity: 0.70 },
      },
      vignette: [0.05, 0.10],
    },
    portrait: {
      space: 'screen',
      veils: {
        color: CREAM, intensity: 0.72, plume: 0.5, duration: 0.62,
        items: [
          [0.00, 0.24, 0.00,  1],
          [0.76, 1.00, 0.38, -1],
        ],
      },
      spot: {
        color: [1.0, 0.94, 0.84], delay: 0.60, duration: 0.40, hazeFrom: 0.04, hazeTo: 0.04,
        from: { uv: [0.645, 0.42], radius: [0.15, 0.15], intensity: 0.0 },
        to:   { uv: [0.645, 0.42], radius: [0.45, 0.43], intensity: 0.70 },
      },
      vignette: [0.0, 0.08],
    },
  },
};

const VERTEX = `
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

// Shader généré pour UNE composition : les colonnes, la barre et les voiles
// sont déroulés en constantes ; une seule lecture de la photo pivotée, une
// seule du bandeau, quel que soit le nombre de bandes.
function buildFragment(comp, hasMask) {
  const P = comp.space === 'screen' ? 'sp' : 'uv';
  const W = comp.space === 'screen' ? ' * uView.z' : '';
  const ext = (x0, x1) => [x0 <= 0 ? -0.02 : x0, x1 >= 1 ? 1.02 : x1];

  let beams = '';
  if (comp.beams) {
    const B = comp.beams;
    B.items.forEach(([x0, x1, delay, dir, behind]) => {
      const w = x1 - x0;
      beams += `
  r = raw(${f(delay)}, ${f(B.duration)});
  t = (${P}.x - ${f(x0)}) / ${f(w)};
  if (r > 0.0 && t > 0.0 && t < 1.0) {
    ql = anac(r);
    v  = ${dir > 0 ? `${P}.y` : `1.0 - ${P}.y`};
    m  = bump(t, ${f(B.plume)}) * front(v, ql, 0.45, 0.10) * mix(1.0, ${f(B.fade ?? 0.6)}, v);
    beamL += m;${behind ? ' beamB += m;' : ''}
    qc = anac((r - ${f(B.cutAfter ?? 0.34)}) / ${f(1 - (B.cutAfter ?? 0.34))});
    fe = mix(${f(B.cutPlume ?? 0.25)}, ${f(2 / w)} * uAA.x, qc);
    c  = smoothstep(${f(B.cutInset)} - fe, ${f(B.cutInset)} + fe, t) * (1.0 - smoothstep(${f(1 - B.cutInset)} - fe, ${f(1 - B.cutInset)} + fe, t))
       * front(v, qc, 0.35, 0.03) * qc;
    cutOff = mix(cutOff, (1.0 - qc) * 0.03 * uSlide * ${f(dir)}, step(0.0005, c));
    cutW += c;${behind ? ' cutB += c;' : ''}
  }`;
    });
  }

  let band = '';
  if (comp.band) {
    const Bd = comp.band, h = Bd.y1 - Bd.y0;
    band += `
  r = raw(${f(Bd.delay)}, ${f(Bd.duration)});
  if (r > 0.0) {
    ql = anac(r);
    by = box(${P}.y, ${f(Bd.y0)}, ${f(Bd.y1)}, ${f(h * (Bd.plume ?? 0.35))});
    reach = ${f(Bd.lag ?? 0.30)} * abs(${P}.x - ${f(Bd.from)}) / ${f(Math.max(Bd.from, 1 - Bd.from))};
    sweep = clamp((ql - reach) / (1.0 - reach), 0.0, 1.0);
    bandL = by * sweep;
  }`;
    Bd.segments.forEach(([s0, s1, srcX, srcDy, delay, dir]) => {
      const [x0, x1] = ext(s0, s1);
      band += `
  r = raw(${f(delay)}, ${f(Bd.segDuration)});
  if (r > 0.0) {
    qs = anac(r);
    fe = mix(${f(Bd.segPlume ?? 0.02)}, uAA.x, qs);
    l  = (${P}.x - ${f(x0)}) / ${f(x1 - x0)};
    m  = smoothstep(${f(x0)} - fe, ${f(x0)} + fe, ${P}.x) * (1.0 - smoothstep(${f(x1)} - fe, ${f(x1)} + fe, ${P}.x))
       * box(${P}.y, ${f(Bd.y0)}, ${f(Bd.y1)}, mix(${f(h * 0.3)}, ${f(h * 0.08)}, qs)) * qs;
    pick = step(bW, m) * step(0.0005, m);
    bSrc = mix(bSrc, vec2(${f(srcX)} + (0.5 - l) * ${f(x1 - x0)}${W}, ${f(srcDy)} + (1.0 - qs) * 0.03 * uSlide * ${f(dir)}), pick);
    bW = min(1.0, bW + m);
  }`;
    });
  }

  let veils = '';
  if (comp.veils) {
    const V = comp.veils;
    V.items.forEach(([v0, v1, delay, dir]) => {
      const pl = (v1 - v0) * (V.plume ?? 0.5);
      const left  = v0 <= 0 ? '1.0' : `smoothstep(${f(v0)}, ${f(v0 + pl)}, ${P}.x)`;
      const right = v1 >= 1 ? '1.0' : `(1.0 - smoothstep(${f(v1 - pl)}, ${f(v1)}, ${P}.x))`;
      veils += `
  r = raw(${f(delay)}, ${f(V.duration)});
  if (r > 0.0) {
    qv = anac(r);
    v  = ${dir > 0 ? 'sp.y' : '1.0 - sp.y'};
    veilL += ${left} * ${right} * front(v, qv, 0.60, 0.25) * qv;
  }`;
    });
  }

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
uniform float uAspect;      // hauteur / largeur de l'espace de composition
uniform float uProgress;
uniform float uSimple;      // 1 = mouvement réduit : un seul fondu
uniform float uSlide;       // amplitude des glissements internes (0 = mouvement réduit)
uniform vec2  uBreath;      // respiration : facteur d'intensité, facteur de rayon
uniform float uAmbient;
uniform vec3  uAmbientColor;
uniform vec2  uSpotPos;
uniform vec2  uSpotRadius;
uniform float uSpotI;
uniform vec3  uSpotColor;
uniform vec2  uFillPos;
uniform vec2  uFillRadius;
uniform float uFillI;
uniform vec3  uFillColor;
uniform float uHaze;
uniform float uSpotHaze;    // brume propre au spot (forte à sa naissance)
uniform vec3  uBeamColor;
uniform float uBeamI;
uniform float uCutAlpha;
uniform vec3  uCutTint;
uniform float uCutTintMix;
uniform vec3  uBandColor;
uniform float uBandI;
uniform float uBandCut;
uniform vec3  uVeilColor;
uniform float uVeilI;
uniform vec2  uVignette;
uniform vec2  uAA;          // ~1 px dans l'espace de la composition

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

// anacrouse + résolution longue (règles 1 et 3) — identique au JS : pente max 2,32
float anac(float t) {
  t = clamp(t, 0.0, 1.0);
  float a = 0.10 * (1.0 - cos(min(t, 0.33333) * 4.71239));
  float u = clamp((t - 0.33333) * 1.5, 0.0, 1.0);
  float b = 0.10 + 0.90 * (1.0 - pow(1.0 - u, 1.6));
  return mix(a, b, smoothstep(0.18, 0.78, t));
}
// avancement brut d'une phase (0 → 1) ; en mouvement réduit, toutes les phases = un seul fondu
float raw(float delay, float dur) {
  return uSimple > 0.5 ? clamp(uProgress, 0.0, 1.0) : clamp((uProgress - delay) / dur, 0.0, 1.0);
}
// lampe : chute gaussienne, rayons en unités de largeur
float lamp(vec2 p, vec2 c, vec2 r) {
  vec2 d = (p - c) / max(r, vec2(0.0001));
  d.y *= uAspect;
  return exp(-2.2 * dot(d, d));
}
// front qui avance le long de v (0 → 1), bord vivant : large au départ (s0), serré à l'arrivée (s1)
float front(float v, float q, float s0, float s1) {
  float soft = mix(s0, s1, q);
  float e = q * (1.0 + soft);
  return 1.0 - smoothstep(e - soft, e, v);
}
// profil d'une colonne : plein au centre, bords en dégradé sur \`plume\` de sa largeur
float bump(float t, float plume) { return smoothstep(0.0, plume, t) * smoothstep(0.0, plume, 1.0 - t); }
float box(float v, float a, float b, float aa) {
  return smoothstep(a - aa, a + aa, v) * (1.0 - smoothstep(b - aa, b + aa, v));
}
float hash(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453); }

void main() {
  vec2 uv = vUv;
  vec2 sp = (uv - uView.xy) / uView.zw;
  vec3 photo = tex(uv);
  float msk = ${hasMask ? 'texture2D(uMask, uv).r' : '0.0'};
  vec3 light = uAmbient * uAmbientColor;
  vec3 glow  = vec3(0.0);
  float r, t, v, m, c, ql, qc, qs, qv, fe, l, pick, by, reach, sweep;
  float beamL = 0.0, beamB = 0.0, cutW = 0.0, cutB = 0.0, cutOff = 0.0, bandL = 0.0, veilL = 0.0;
  float bW = 0.0; vec2 bSrc = vec2(0.0);

  // spot et lumière de fond
  if (uSpotI > 0.0) {
    float s = lamp(${P}, uSpotPos, uSpotRadius * uBreath.y) * uSpotI * uBreath.x;
    light += uSpotColor * s;
    glow  += uSpotColor * s * uSpotHaze;
  }
  if (uFillI > 0.0) {
    float s2 = lamp(${P}, uFillPos, uFillRadius) * uFillI;
    light += uFillColor * s2;
    glow  += uFillColor * s2 * uHaze * 0.5;
  }
  ${beams}
  ${band}
  ${veils}

  // la photo pivotée de 180° n'est lue que là où une découpe la montre
  if (cutW > 0.0) {
    vec3 inv = mix(tex(vec2(1.0 - uv.x, 1.0 - uv.y + cutOff)), uCutTint, uCutTintMix);
    photo = mix(photo, inv, min(cutW, 1.0) * uCutAlpha * (1.0 - min(cutB, 1.0) * msk));
  }
  if (bW > 0.0) photo = mix(photo, tex(vec2(bSrc.x, uv.y + bSrc.y)), bW * uBandCut);

  // le sujet devant les colonnes reçoit moins leur lumière (il a son propre spot)
  float bl = (min(beamL, 1.0) - min(beamB, 1.0) * msk * 0.6) * uBeamI * uBreath.x;
  light += uBeamColor * bl;
  glow  += uBeamColor * min(beamL, 1.0) * uBeamI * uHaze;
  light += uBandColor * bandL * uBandI;
  glow  += uBandColor * bandL * uBandI * uHaze * 0.5;
  light += uVeilColor * min(veilL, 1.0) * uVeilI * uBreath.x;
  glow  += uVeilColor * min(veilL, 1.0) * uVeilI * uHaze * 0.4;

  vec3 col = photo * light + glow;
  // bords fondus dans le noir de la page (aucun bord dur, règle 4)
  if (uVignette.x > 0.0) col *= smoothstep(0.0, uVignette.x, sp.x) * smoothstep(0.0, uVignette.x, 1.0 - sp.x);
  if (uVignette.y > 0.0) col *= smoothstep(0.0, uVignette.y, sp.y) * smoothstep(0.0, uVignette.y, 1.0 - sp.y);
  col = clamp(col, 0.0, 1.0) + (hash(gl_FragCoord.xy) - 0.5) / 255.0;   // tramage : pas de bandes dans la pénombre
  gl_FragColor = vec4(col, 1.0);
}
`;
}

class LightScene {
  constructor(section, opts = {}) {
    this.section = section;
    this.frame   = section.querySelector('[data-frame]') || section;
    // Calques qui suivent le ralenti du cadre (textes posés au-dessus de la trace)
    this.follow  = [...section.querySelectorAll('[data-follow]')];
    this.canvas  = section.querySelector('[data-canvas]');
    this.img     = section.querySelector('[data-img]');
    this.preset  = PRESETS[section.dataset.preset];
    if (!this.preset) console.warn(`[LightScene] preset inconnu « ${section.dataset.preset} » : photo seule`);
    this.opts    = opts;
    this.progress = 0;
    this.y        = 0;
    this.dirty    = true;
    this.ready    = false;
    this.visible  = false;
    this.breathAt = 0;
    this.shaders  = {};
    if (this.img && !this.img.complete) this.img.addEventListener('load', () => this.layout(), { once: true });

    const P = this.preset || {};
    this.params = Object.assign({
      // Ralenti sans épinglage (ordinateur) : au plus fort la photo absorbe `linger`
      // de la vitesse de scroll (0,6 → elle défile à 40 %), sur ± lingerSpan écrans.
      linger:          (opts.touch || opts.reduced) ? 0 : 0.6,
      lingerSpan:      1.2,
      // Fenêtre de l'effet, en hauteurs d'écran autour du moment où la photo remplit l'écran
      progressBefore:  0.55,
      progressAfter:   0.25,
      progressMinSpan: 0.4,
      slide:           opts.reduced ? 0 : 1,
      maxPixels:       6e6,
      maxRes:          2,
      portraitBelow:   0.9,
      // Respiration (règle 6) : ±3 % d'intensité, ±0,4 % de rayon, période 8 s, 8 images/s
      // (64 pas par période : chaque pas change l'intensité de 0,3 %, invisible ; 8 rendus/s au repos au lieu de 17)
      breath:          opts.reduced ? 0 : 1,
      breathPeriod:    8,
      breathAmp:       0.03,
      breathRadius:    0.004,
      breathFps:       8,
    }, P.params || {}, opts.touch ? (P.touch || {}) : {});
    // Tactile (défilement natif pur) et mouvement réduit : jamais de ralenti, quel que soit le preset
    if (opts.touch || opts.reduced) this.params.linger = 0;
  }

  // Géométrie du scroll — appelée DANS L'ORDRE de la page (chaque position dépend des hauteurs au-dessus)
  measure() {
    const vh = this.frame.offsetHeight || window.innerHeight;
    if (!vh) return;
    const top   = this.section.getBoundingClientRect().top + window.scrollY;
    const after = Math.max(0, document.documentElement.scrollHeight - (top + this.section.offsetHeight));
    const P = this.params;
    const h = P.lingerSpan * vh;
    const L = P.linger > 0 ? (P.linger * h) / 0.75 : 0;
    const sMin = -top;
    const atTop    = top < 1;
    const atBottom = !atTop && after < 0.5 * vh;
    let c, y0, extra;
    if (atTop)         { c = sMin; y0 = L / 2; extra = L / 2; }
    else if (atBottom) { extra = L / 2; c = extra + after; y0 = L * smooth01((sMin - c + h) / (2 * h)); }
    else               { c = L / 2; y0 = L * smooth01((sMin - c + h) / (2 * h)); extra = Math.max(0, L - y0); }
    const sEnd = vh + extra + after - window.innerHeight;
    let p1 = Math.max(c + P.progressAfter * vh, Math.max(sMin, c - P.progressBefore * vh) + P.progressMinSpan * vh);
    p1 = Math.min(p1, sEnd);
    const p0 = Math.min(Math.max(sMin, c - P.progressBefore * vh), p1 - 0.2 * vh);
    this.geo = { vh, top, h, L, c, y0, extra, p0, p1, after };
    this.section.style.setProperty('--extra', `${Math.round(extra)}px`);
  }

  async init() {
    const p = this.preset;
    if (!p) throw new Error(`preset inconnu « ${this.section.dataset.preset} »`);
    this.app = new PIXI.Application({
      view: this.canvas, width: 1, height: 1,
      autoStart: false, antialias: false, backgroundColor: 0x000000, resolution: 1, autoDensity: true,
    });
    // Canvas décoratif : Pixi pose touch-action:none → on retire ses écouteurs
    this.app.renderer.events?.setTargetElement?.(null);
    this.canvas.style.touchAction = 'pan-y';

    this.canvas.addEventListener('webglcontextlost', () => this.section.classList.remove('is-ready'));
    this.canvas.addEventListener('webglcontextrestored', () => {
      requestAnimationFrame(() => {
        if (!this.ready) return;
        this.app.render(); this.dirty = false;
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
      this.app.render();          // préchauffage : compilation + envoi des textures maintenant
      // … et toutes les branches du shader (faisceaux, découpes, barre, voiles) une fois à p = 1,
      // canvas encore invisible : pas de pipeline à finir de compiler au premier défilement
      this.applyProgress(1); this.app.render();
      this.applyProgress(this.progress); this.app.render();
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
    const p = this.preset, comp = p[key];
    const v3 = (c) => new Float32Array(c);
    const beams = comp.beams || {}, band = comp.band || {}, veils = comp.veils || {};
    const shader = PIXI.Shader.from(VERTEX, buildFragment(comp, !!p.mask), {
      uImage:       this.texture,
      uMask:        this.mask,
      uTexSize:     new Float32Array([this.texSize.w, this.texSize.h]),
      uBicubic:     1,
      uView:        new Float32Array([0, 0, 1, 1]),
      uAspect:      this.texSize.h / this.texSize.w,
      uProgress:    0,
      uSimple:      this.opts.reduced ? 1 : 0,
      uSlide:       this.params.slide,
      uBreath:      new Float32Array([1, 1]),
      uAmbient:     p.ambient ?? 0.12,
      uAmbientColor: v3(p.ambientColor || [1, 1, 1]),
      uSpotPos:     new Float32Array([0.5, 0.5]),
      uSpotRadius:  new Float32Array([0.3, 0.3]),
      uSpotI:       0,
      uSpotColor:   v3((comp.spot && comp.spot.color) || [1, 1, 1]),
      uFillPos:     new Float32Array([0.5, 0.5]),
      uFillRadius:  new Float32Array([0.5, 0.5]),
      uFillI:       0,
      uFillColor:   v3((comp.fill && comp.fill.color) || [1, 1, 1]),
      uHaze:        p.haze ?? 0.06,
      uSpotHaze:    p.haze ?? 0.06,
      uBeamColor:   v3(beams.color || [1, 1, 1]),
      uBeamI:       beams.intensity ?? 0,
      uCutAlpha:    beams.cutAlpha ?? 0,
      uCutTint:     v3(beams.cutTint || [0, 0, 0]),
      uCutTintMix:  beams.cutTintMix ?? 0,
      uBandColor:   v3(band.color || [1, 1, 1]),
      uBandI:       band.intensity ?? 0,
      uBandCut:     band.cut ?? 0.5,
      uVeilColor:   v3(veils.color || [1, 1, 1]),
      uVeilI:       veils.intensity ?? 0,
      uVignette:    new Float32Array(comp.vignette || [0, 0]),
      uAA:          new Float32Array([0.001, 0.001]),
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
    const res = Math.max(1, Math.min(dpr, this.params.maxRes, Math.sqrt(this.params.maxPixels / (w * h))));
    const r = this.app.renderer;
    let resized = false;
    if (!this.view || this.view.w !== w || this.view.h !== h || this.view.res !== res) {
      r.resolution = res;
      r.resize(w, h);
      this.view = { w, h, res };
      resized = true;
    }
    const { mw, mh, x, y } = this.cover(w, h, this.texSize.w, this.texSize.h);
    this.mesh.scale.set(mw, mh);
    this.mesh.position.set(x, y);

    const u = this.shader.uniforms;
    u.uView[0] = -x / mw; u.uView[1] = -y / mh; u.uView[2] = w / mw; u.uView[3] = h / mh;
    this.viewUV = [u.uView[0], u.uView[1], u.uView[2], u.uView[3]];
    const screen = this.preset[this.compKey].space === 'screen';
    u.uAspect = screen ? h / w : this.texSize.h / this.texSize.w;
    u.uAA[0] = 0.75 / ((screen ? w : mw) * res);
    u.uAA[1] = 0.75 / ((screen ? h : mh) * res);
    u.uBicubic = mw * res > this.texSize.w ? 1 : 0;
    this.applyProgress(this.progress);
    this.dirty = true;
    if (resized) { this.app.render(); this.dirty = false; }
  }

  // Point donné en UV photo ou directement dans l'espace de la composition
  toComp(pt) {
    const comp = this.preset[this.compKey];
    if (pt.uv && comp.space === 'screen' && this.viewUV) {
      const v = this.viewUV;
      return [(pt.uv[0] - v[0]) / v[2], (pt.uv[1] - v[1]) / v[3]];
    }
    return pt.pos || pt.uv;
  }

  // Spot et lumière de fond : interpolés en JS (quelques uniformes), fonction exacte de p
  applyProgress(p) {
    if (!this.shader) return;
    const comp = this.preset[this.compKey], u = this.shader.uniforms, simple = this.opts.reduced;
    u.uProgress = p;
    if (comp.spot) {
      // Courbes du spot : `geo` (position, rayon, brume) et `icurve` (intensité) valent 'anac'
      // (défaut), 'linear' ou 'smooth'. Un spot qui VOYAGE vers le visage l'allume d'un coup à
      // l'arrivée si sa course suit l'anacrouse (mesuré : +55 puis +44 de luminance en deux
      // images) ; une descente à vitesse constante et une intensité linéaire étalent le lever
      // du visage sur quatre images.
      const S = comp.spot, tr = simple ? p : clamp01((p - S.delay) / S.duration), q = anac(tr);
      const curve = (name) => (name === 'linear' ? tr : name === 'smooth' ? smooth01(tr) : q);
      const a = this.toComp(S.from), b = this.toComp(S.to);
      const k = simple ? 1 : curve(S.geo), qi = simple ? q : curve(S.icurve);
      u.uSpotPos[0] = a[0] + (b[0] - a[0]) * k;
      u.uSpotPos[1] = a[1] + (b[1] - a[1]) * k;
      u.uSpotRadius[0] = S.from.radius[0] + (S.to.radius[0] - S.from.radius[0]) * k;
      u.uSpotRadius[1] = S.from.radius[1] + (S.to.radius[1] - S.from.radius[1]) * k;
      u.uSpotI = simple ? S.to.intensity * q : S.from.intensity + (S.to.intensity - S.from.intensity) * qi;
      const h0 = S.hazeFrom ?? (this.preset.haze ?? 0.06), h1 = S.hazeTo ?? (this.preset.haze ?? 0.06);
      u.uSpotHaze = h0 + (h1 - h0) * k;
    }
    if (comp.fill) {
      const F = comp.fill, q = anac(simple ? p : clamp01((p - F.delay) / F.duration));
      const c = this.toComp(F);
      u.uFillPos[0] = c[0]; u.uFillPos[1] = c[1];
      u.uFillRadius[0] = F.radius[0]; u.uFillRadius[1] = F.radius[1];
      u.uFillI = F.intensity * q;
      u.uAmbient = (this.preset.ambient ?? 0.12) + ((F.ambientTo ?? this.preset.ambient) - (this.preset.ambient ?? 0.12)) * q;
    }
  }

  setProgress(p) {
    this.progress = p;
    this.applyProgress(p);
    this.section.style.setProperty('--p', p.toFixed(4));
    this.dirty = true;
  }

  render() {
    if (this.ready && this.dirty && this.visible) { this.app.render(); this.dirty = false; }
  }

  // À chaque frame, avec la position de scroll : ralenti, progression, rendu si besoin
  update(scrollY) {
    const g = this.geo;
    if (!g) return;
    const s = scrollY - g.top;
    if (g.L > 0) {
      let y = g.L * smooth01((s - g.c + g.h) / (2 * g.h)) - g.y0;
      y = Math.min(g.extra, Math.max(0, y));
      const dpr = window.devicePixelRatio || 1;
      y = Math.round(y * dpr) / dpr;
      if (y !== this.y || !this.frame.style.transform) {
        this.y = y;
        const t = `translate3d(0, ${y}px, 0)`;
        this.frame.style.transform = t;
        for (const el of this.follow) el.style.transform = t;
      }
    } else if (this.y) {
      this.y = 0;
      this.frame.style.transform = '';
      for (const el of this.follow) el.style.transform = '';
    }
    const p = clamp01((s - g.p0) / (g.p1 - g.p0));
    if (p !== this.progress) this.setProgress(p);
    const top = g.top + this.y - scrollY;
    this.visible = top < window.innerHeight && top + g.vh > 0;
    this.render();
  }

  // Respiration (règle 6) : hors scroll, en temps réel, seulement à l'écran et onglet visible
  tick(now) {
    const P = this.params;
    if (!this.ready || !P.breath || !this.visible || document.hidden) return;
    if (now - this.breathAt < 1000 / P.breathFps) return;
    this.breathAt = now;
    const ph = (now / 1000) * 2 * Math.PI / P.breathPeriod;
    const u = this.shader.uniforms;
    u.uBreath[0] = 1 + P.breathAmp * P.breath * Math.sin(ph);
    u.uBreath[1] = 1 + P.breathRadius * P.breath * Math.sin(ph + 1.3);
    this.dirty = true;
    this.render();
  }
}

window.LightScene = LightScene;
window.LightScene.PRESETS = PRESETS;
window.LightScene.anac = anac;
})();
