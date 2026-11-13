/*
 * lumiere.js — visuels « lumière » : les découpes de l'hypothèse 3 (« Lever ») → window.Lumiere
 *
 * La photo d'un visuel est dans la pénombre ; c'est la lumière qui la révèle, et les « découpes »
 * (la photo pivotée de 180°) apparaissent à l'intérieur des faisceaux, jamais en rectangles durs :
 *
 *   couleur = photo × (ambiant + spot + colonnes + barre + voiles) + brume
 *
 *   - spot     : ellipse très douce, dont la position, le rayon et l'intensité suivent la progression ;
 *   - colonnes : bandes verticales de lumière à bords en dégradé (les stries), qui montent ou
 *                descendent ; à l'intérieur, la photo pivotée apparaît après la lumière ;
 *   - barre    : bandeau horizontal de lumière, avec ses fragments retournés ;
 *   - voiles   : colonnes à plume très large ;
 *   - brume    : la lumière se voit dans l'air, même là où la photo est noire.
 *
 * Moteur repris de _archives/phase-hypotheses/hypotheses/h3-lever/js/light-scene.js (shader et
 * préréglages identiques), sans Pixi : un canvas WebGL par visuel, un seul quadrilatère. Ce qui
 * change : pas de ralenti de la photo, et la progression n'est plus collée au défilement.
 *
 * Progression (0 → 1) : elle vise la position de la section dans la fenêtre. 1 quand la section
 * remplit l'écran ; 0 quand elle en est à FENETRE[0] écrans, au-dessus comme au-dessous. Les
 * découpes apparaissent donc à l'arrivée sur la section, s'effacent quand on la quitte, vers le
 * haut ou vers le bas, et reviennent quand on y retourne, d'où qu'on vienne (13/11/2026 :
 * auparavant, la lumière restait levée une fois la section dépassée vers le bas).
 * La progression ne rejoint jamais sa cible plus vite que VITESSE par seconde : après un
 * défilement automatique vers la section (aimantation, js/main.js), la lumière finit de se lever
 * sur l'image arrêtée.
 * Chaque phase a un retard et une durée et passe par anac() (anacrouse, puis résolution longue).
 * Seule la respiration vit en temps réel (8 images/s, section à l'écran, onglet visible).
 * Mouvement réduit : un seul fondu, ni glissement ni respiration.
 *
 * Usage (js/main.js) :
 *   const s = Lumiere.creer(section, { preset, reduit, focus });   // section.visuel[data-lumiere]
 *   s.init().then(…, …) ; s.mesurer() ; s.poser() ; s.maj(scrollY, dt) ; s.respirer(now)
 * Classes posées sur la section : lumiere-prete (le canvas remplace l'image), lumiere-echec
 * (WebGL refusé : l'image reste seule), lumiere-perdue (contexte perdu, en attente).
 *
 * Une photo nouvelle a besoin de son préréglage (positions des faisceaux, masque du sujet) ;
 * à défaut, « generique » pose un spot et des colonnes en coordonnées d'écran.
 */
(function () {
'use strict';

const VITESSE = 0.42;            // progression maximale par seconde (course complète : 2,4 s au moins)
const FENETRE = [0.75, 0];       // course de la lumière, en hauteurs d'écran : à l'approche de la section, et après son alignement

const f = (n) => Number(n).toFixed(4);
const clamp01  = (t) => Math.min(1, Math.max(0, t));
const smooth01 = (t) => { t = clamp01(t); return t * t * (3 - 2 * t); };

// Anacrouse puis résolution longue (pente maximale 2,32) : identique au GLSL plus bas.
function anac(t) {
  t = clamp01(t);
  const a = 0.10 * (1 - Math.cos(Math.min(t, 1 / 3) * 1.5 * Math.PI));
  const u = clamp01((t - 1 / 3) * 1.5);
  const b = 0.10 + 0.90 * (1 - Math.pow(1 - u, 1.6));
  return a + (b - a) * smooth01((t - 0.18) / 0.60);
}

// Grille de colonnes en UV de la photo, entrées en pupitres : du sujet vers les bords.
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
    const jitter = ((k2 * 7) % 3) * 0.012;
    out.push([x0, x1, delay0 + g * (spread / (groups - 1)) + jitter, dir, true]);
    k2++;
  }
  return out;
}

// Grille régulière en coordonnées d'écran (portrait, préréglage générique)
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

const GOLD    = [1.00, 0.86, 0.60];
const WARM    = [1.00, 0.80, 0.50];
const BLUE    = [0.50, 0.70, 1.00];
const CREAM   = [1.00, 0.95, 0.86];
const LAVANDE = [90 / 255, 99 / 255, 142 / 255];

const PRESETS = {
  // ------------------------------------------------------------------
  // bleuse-1 (mains ouvertes, bokeh doré) : spot doré, deux faisceaux, barre de lumière
  // ------------------------------------------------------------------
  'bleuse-1': {
    focus: [0.55, 0.45],
    mask:  'assets/img/bleuse-1-masque.png',
    ambient: 0.12, ambientColor: [1.0, 0.97, 0.92],
    haze: 0.07,
    desktop: {
      space: 'image',
      // 1. le spot naît en haut à gauche, petit et faible, grandit et descend vers le visage
      spot: {
        color: GOLD, delay: 0.0, duration: 0.70, geo: 'linear', icurve: 'linear', hazeFrom: 0.32, hazeTo: 0.05,
        from: { uv: [0.10, 0.12], radius: [0.15, 0.14], intensity: 0.22 },
        to:   { uv: [0.55, 0.44], radius: [0.38, 0.36], intensity: 1.00 },
      },
      // 2. le fond doré, pendant la descente du spot
      fill: { color: WARM, uv: [0.80, 0.26], radius: [0.50, 0.46], intensity: 0.30, ambientTo: 0.17, delay: 0.10, duration: 0.32 },
      // 3. deux faisceaux à la place des stries (x 0,18–0,35) : lumière d'abord, découpe ensuite
      beams: {
        color: [1.0, 0.92, 0.74], intensity: 0.80, plume: 0.38, fade: 0.55,
        cutAlpha: 0.35, cutTint: [0, 0, 0], cutTintMix: 0, cutInset: 0.14, cutAfter: 0.34, cutPlume: 0.28,
        duration: 0.48,
        items: [
          [0.182, 0.262, 0.46,  1, false],
          [0.272, 0.353, 0.52, -1, false],
        ],
      },
      // 4. le bandeau devient une barre de lumière, allumée en dernier, avec ses fragments retournés
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
  // bleuse-3 (en frac devant l'orchestre) : spot chaud sur le chef, colonnes de lumière bleue
  // ------------------------------------------------------------------
  'bleuse-3': {
    focus: [0.61, 0.40],
    mask:  'assets/img/bleuse-3-masque.png',
    ambient: 0.10, ambientColor: [0.80, 0.88, 1.0],
    haze: 0.075,
    desktop: {
      space: 'image',
      // le chef d'abord : un spot chaud sur lui
      spot: {
        color: [1.0, 0.88, 0.70], delay: 0.0, duration: 0.42, hazeFrom: 0.10, hazeTo: 0.04,
        from: { uv: [0.61, 0.36], radius: [0.05, 0.05], intensity: 0.20 },
        to:   { uv: [0.61, 0.40], radius: [0.23, 0.22], intensity: 0.95 },
      },
      // puis les colonnes de lumière bleue montent du bas, aux positions de la grille de l'identité :
      // profil entièrement en dégradé, plus vives à leur base ; la photo pivotée, teintée lavande, dedans
      beams: {
        color: BLUE, intensity: 0.70, plume: 0.5, fade: 0.30,
        cutAlpha: 0.55, cutTint: LAVANDE, cutTintMix: 0.5, cutInset: 0.20, cutAfter: 0.34, cutPlume: 0.30,
        duration: 0.42,
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
  // generique : une photo sans préréglage (point focal donné par le visuel) ; pas de masque
  // ------------------------------------------------------------------
  'generique': {
    focus: [0.5, 0.45],
    ambient: 0.12, ambientColor: [1.0, 0.98, 0.94],
    haze: 0.06,
    desktop: {
      space: 'screen',
      spot: {
        color: CREAM, delay: 0.0, duration: 0.45, hazeFrom: 0.10, hazeTo: 0.04,
        from: { pos: [0.5, 0.40], radius: [0.08, 0.08], intensity: 0.20 },
        to:   { pos: [0.5, 0.45], radius: [0.34, 0.32], intensity: 0.95 },
      },
      beams: {
        color: CREAM, intensity: 0.55, plume: 0.5, fade: 0.35,
        cutAlpha: 0.45, cutTint: [0, 0, 0], cutTintMix: 0, cutInset: 0.20, cutAfter: 0.34, cutPlume: 0.30,
        duration: 0.42,
        items: screenColumns({ count: 14, focusX: 0.5, delay0: 0.28, spread: 0.30 }),
      },
      vignette: [0.05, 0.10],
    },
    portrait: {
      space: 'screen',
      spot: {
        color: CREAM, delay: 0.0, duration: 0.45, hazeFrom: 0.10, hazeTo: 0.04,
        from: { pos: [0.5, 0.40], radius: [0.14, 0.14], intensity: 0.20 },
        to:   { pos: [0.5, 0.45], radius: [0.70, 0.66], intensity: 0.95 },
      },
      beams: {
        color: CREAM, intensity: 0.55, plume: 0.5, fade: 0.35,
        cutAlpha: 0.45, cutTint: [0, 0, 0], cutTintMix: 0, cutInset: 0.20, cutAfter: 0.34, cutPlume: 0.30,
        duration: 0.42,
        items: screenColumns({ count: 8, focusX: 0.5, delay0: 0.28, spread: 0.30 }),
      },
      vignette: [0.0, 0.08],
    },
  },
};

const VERTEX = `
precision highp float;
attribute vec2 aPos;          // coin du cadre, de (0,0) en haut à gauche à (1,1) en bas à droite
uniform vec4 uView;           // zone visible de l'écran en UV photo : x0, y0, largeur, hauteur
varying vec2 vUv;
void main() {
  vUv = uView.xy + aPos * uView.zw;
  gl_Position = vec4(aPos.x * 2.0 - 1.0, 1.0 - aPos.y * 2.0, 0.0, 1.0);
}
`;

// Shader généré pour UNE composition : les colonnes, la barre et les voiles sont déroulés en
// constantes ; une seule lecture de la photo pivotée, une seule du bandeau, quel que soit le
// nombre de bandes.
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
precision highp float;
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

// anacrouse + résolution longue — identique au JS : pente max 2,32
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
  // bords fondus dans le noir de la page (aucun bord dur)
  if (uVignette.x > 0.0) col *= smoothstep(0.0, uVignette.x, sp.x) * smoothstep(0.0, uVignette.x, 1.0 - sp.x);
  if (uVignette.y > 0.0) col *= smoothstep(0.0, uVignette.y, sp.y) * smoothstep(0.0, uVignette.y, 1.0 - sp.y);
  col = clamp(col, 0.0, 1.0) + (hash(gl_FragCoord.xy) - 0.5) / 255.0;   // tramage : pas de bandes dans la pénombre
  gl_FragColor = vec4(col, 1.0);
}
`;
}

/* ---------- WebGL : programme, uniformes, textures ---------- */

function compiler(gl, type, source) {
  const s = gl.createShader(type);
  gl.shaderSource(s, source);
  gl.compileShader(s);
  if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
    const journal = gl.getShaderInfoLog(s);
    gl.deleteShader(s);
    throw new Error('shader : ' + journal);
  }
  return s;
}
function programme(gl, fragment) {
  const p = gl.createProgram();
  const vs = compiler(gl, gl.VERTEX_SHADER, VERTEX), fs = compiler(gl, gl.FRAGMENT_SHADER, fragment);
  gl.attachShader(p, vs); gl.attachShader(p, fs);
  gl.bindAttribLocation(p, 0, 'aPos');
  gl.linkProgram(p);
  gl.deleteShader(vs); gl.deleteShader(fs);
  if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error('programme : ' + gl.getProgramInfoLog(p));
  // uniformes actifs seulement (le compilateur retire ceux que la composition n'utilise pas)
  const uniformes = [];
  const n = gl.getProgramParameter(p, gl.ACTIVE_UNIFORMS);
  for (let i = 0; i < n; i++) {
    const info = gl.getActiveUniform(p, i);
    uniformes.push({ nom: info.name.replace(/\[0\]$/, ''), type: info.type, lieu: gl.getUniformLocation(p, info.name) });
  }
  return { p, uniformes };
}
function texture(gl, source) {
  const t = gl.createTexture();
  gl.bindTexture(gl.TEXTURE_2D, t);
  gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
  gl.pixelStorei(gl.UNPACK_PREMULTIPLY_ALPHA_WEBGL, false);
  if (source) gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, source);
  else gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([255, 255, 255, 255]));
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
  return t;
}
function charger(src) {
  return new Promise((ok, ko) => {
    const i = new Image();
    i.decoding = 'async';
    i.onload = () => ok(i);
    i.onerror = () => ko(new Error('image introuvable : ' + src));
    i.src = src;
  });
}

/* ---------- une scène : un visuel ---------- */

class Scene {
  constructor(section, opts) {
    opts = opts || {};
    this.section = section;
    this.opts = opts;
    this.img = section.querySelector('img.visuel__media');
    this.nom = opts.preset || section.getAttribute('data-lumiere') || 'generique';
    let preset = PRESETS[this.nom];
    if (!preset) { console.warn('[lumière] préréglage inconnu « ' + this.nom + ' » : générique'); this.nom = 'generique'; preset = PRESETS.generique; }
    if (this.nom === 'generique' && opts.focus) preset = Object.assign({}, preset, { focus: opts.focus });
    this.preset = preset;
    this.params = {
      slide:        opts.reduit ? 0 : 1,
      maxPixels:    6e6,
      maxRes:       2,
      portraitBelow: 0.9,
      // Respiration : ±3 % d'intensité, ±0,4 % de rayon, période 8 s, 8 images/s
      breath:       opts.reduit ? 0 : 1,
      breathPeriod: 8,
      breathAmp:    0.03,
      breathRadius: 0.004,
      breathFps:    8,
    };
    this.canvas = document.createElement('canvas');
    this.canvas.className = 'visuel__lumiere';
    this.canvas.setAttribute('aria-hidden', 'true');
    if (this.img && this.img.parentNode === section) section.insertBefore(this.canvas, this.img.nextSibling);
    else section.insertBefore(this.canvas, section.firstChild);

    this.p = -1;                 // progression affichée (−1 : pas encore posée)
    this.cible = 0;              // progression visée (position de la section dans la fenêtre)
    this.haut = 0; this.h = 0;   // géométrie de la section dans la page
    this.sale = true;
    this.pret = false;
    this.visible = false;
    this.souffle = 0;
    this.programmes = {};
    this.valeurs = {};
    this.vue = null;
    this.gl = null;
  }

  init() {
    const p = this.preset;
    const source = this.img ? (this.img.currentSrc || this.img.src) : this.section.getAttribute('data-image');
    if (!source) return Promise.reject(new Error('pas d\'image'));
    return Promise.all([charger(source), p.mask ? charger(p.mask) : Promise.resolve(null)]).then(([photo, masque]) => {
      this.photo = photo;
      this.masque = masque;
      this.texSize = { w: photo.naturalWidth, h: photo.naturalHeight };
      const attributs = { alpha: false, antialias: false, depth: false, stencil: false, premultipliedAlpha: false, preserveDrawingBuffer: false };
      const gl = this.canvas.getContext('webgl', attributs) || this.canvas.getContext('experimental-webgl', attributs);
      if (!gl) throw new Error('WebGL indisponible');
      const hp = gl.getShaderPrecisionFormat(gl.FRAGMENT_SHADER, gl.HIGH_FLOAT);
      if (!hp || hp.precision < 23) throw new Error('précision highp indisponible en fragment');
      this.gl = gl;
      this.canvas.addEventListener('webglcontextlost', (e) => {
        e.preventDefault();
        this.pret = false;
        this.section.classList.remove('lumiere-prete');
        this.section.classList.add('lumiere-perdue');
      });
      this.canvas.addEventListener('webglcontextrestored', () => {
        try { this._construire(); this.pret = true; this.poser(); this._prechauffer(); } catch (e) { this._echec(e); return; }
        this.section.classList.remove('lumiere-perdue');
        this.section.classList.add('lumiere-prete');
      });
      this._construire();
      this.pret = true;
      try {
        this.mesurer();
        this.poser();
        this._prechauffer();
      } catch (e) {
        this.pret = false;
        throw e;
      }
      this.section.classList.add('lumiere-prete');
      return this;
    }).catch((e) => { this._echec(e); throw e; });
  }

  _echec() {
    this.pret = false;
    this.section.classList.remove('lumiere-prete', 'lumiere-perdue');
    this.section.classList.add('lumiere-echec');
    if (this.img) this.img.style.objectPosition = this.objetInitial || '';
    if (this.canvas.parentNode) this.canvas.parentNode.removeChild(this.canvas);
  }

  // Ressources du contexte (à refaire après une perte de contexte)
  _construire() {
    const gl = this.gl;
    this.programmes = {};
    this.texPhoto = texture(gl, this.photo);
    this.texMasque = texture(gl, this.masque);
    this.tampon = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, this.tampon);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([0, 0, 1, 0, 1, 1, 0, 1]), gl.STATIC_DRAW);
    gl.disable(gl.DEPTH_TEST);
    gl.disable(gl.BLEND);
  }

  // Compilation et envoi des textures maintenant, toutes branches du shader comprises (p = 1),
  // canvas encore invisible : rien à finir de compiler au premier défilement.
  _prechauffer() {
    const p = this.p < 0 ? 0 : this.p;
    this.appliquer(1); this._dessiner();
    this.appliquer(p); this._dessiner();
    this.sale = false;
  }

  composition() {
    const w = this.section.clientWidth, h = this.section.clientHeight;
    return (this.preset.portrait && h > 0 && w / h < this.params.portraitBelow) ? 'portrait' : 'desktop';
  }

  _programme(cle) {
    if (this.programmes[cle]) return this.programmes[cle];
    const P = this.preset, comp = P[cle];
    const prog = programme(this.gl, buildFragment(comp, !!P.mask));
    const beams = comp.beams || {}, band = comp.band || {}, veils = comp.veils || {};
    // valeurs des uniformes de cette composition (celles du défilement sont posées par appliquer())
    prog.valeurs = {
      uImage: 0, uMask: 1,
      uTexSize: [this.texSize.w, this.texSize.h],
      uBicubic: 1,
      uView: [0, 0, 1, 1],
      uAspect: this.texSize.h / this.texSize.w,
      uProgress: 0,
      uSimple: this.opts.reduit ? 1 : 0,
      uSlide: this.params.slide,
      uBreath: [1, 1],
      uAmbient: P.ambient ?? 0.12,
      uAmbientColor: P.ambientColor || [1, 1, 1],
      uSpotPos: [0.5, 0.5], uSpotRadius: [0.3, 0.3], uSpotI: 0,
      uSpotColor: (comp.spot && comp.spot.color) || [1, 1, 1],
      uFillPos: [0.5, 0.5], uFillRadius: [0.5, 0.5], uFillI: 0,
      uFillColor: (comp.fill && comp.fill.color) || [1, 1, 1],
      uHaze: P.haze ?? 0.06,
      uSpotHaze: P.haze ?? 0.06,
      uBeamColor: beams.color || [1, 1, 1],
      uBeamI: beams.intensity ?? 0,
      uCutAlpha: beams.cutAlpha ?? 0,
      uCutTint: beams.cutTint || [0, 0, 0],
      uCutTintMix: beams.cutTintMix ?? 0,
      uBandColor: band.color || [1, 1, 1],
      uBandI: band.intensity ?? 0,
      uBandCut: band.cut ?? 0.5,
      uVeilColor: veils.color || [1, 1, 1],
      uVeilI: veils.intensity ?? 0,
      uVignette: comp.vignette || [0, 0],
      uAA: [0.001, 0.001],
    };
    this.programmes[cle] = prog;
    return prog;
  }

  // Cadrage « cover » centré sur le point focal, sans jamais découvrir de bord
  cover(w, h, tw, th) {
    const ratio = tw / th;
    let mw = w, mh = w / ratio;
    if (mh < h) { mh = h; mw = h * ratio; }
    const [fx, fy] = this.preset.focus || [0.5, 0.5];
    const borne = (v, min) => Math.min(0, Math.max(min, v));
    return { mw, mh, x: borne(w / 2 - fx * mw, w - mw), y: borne(h / 2 - fy * mh, h - mh) };
  }

  // Géométrie dans la page (à appeler quand la mise en page change)
  mesurer() {
    this.haut = this.section.getBoundingClientRect().top + window.scrollY;
    this.h = this.section.offsetHeight;
  }

  // Taille du canvas, cadrage, composition (à appeler après mesurer())
  poser() {
    const w = this.section.clientWidth, h = this.section.clientHeight;
    if (!w || !h) return;
    if (this.img && this.img.naturalWidth && this.pret) {
      // l'image de repli prend exactement le cadrage du canvas : le fondu se fait sans saut
      if (this.objetInitial === undefined) this.objetInitial = this.img.style.objectPosition;
      const c = this.cover(w, h, this.img.naturalWidth, this.img.naturalHeight);
      this.img.style.objectPosition = c.x + 'px ' + c.y + 'px';
    }
    if (!this.pret) return;
    const cle = this.composition();
    this.cle = cle;
    const prog = this._programme(cle);
    const dpr = window.devicePixelRatio || 1;
    const res = Math.max(1, Math.min(dpr, this.params.maxRes, Math.sqrt(this.params.maxPixels / (w * h))));
    const W = Math.round(w * res), H = Math.round(h * res);
    if (this.canvas.width !== W || this.canvas.height !== H) { this.canvas.width = W; this.canvas.height = H; }
    const { mw, mh, x, y } = this.cover(w, h, this.texSize.w, this.texSize.h);
    const v = prog.valeurs;
    v.uView = [-x / mw, -y / mh, w / mw, h / mh];
    this.vue = v.uView;
    const ecran = this.preset[cle].space === 'screen';
    v.uAspect = ecran ? h / w : this.texSize.h / this.texSize.w;
    v.uAA = [0.75 / ((ecran ? w : mw) * res), 0.75 / ((ecran ? h : mh) * res)];
    v.uBicubic = mw * res > this.texSize.w ? 1 : 0;
    this.appliquer(this.p < 0 ? 0 : this.p);
    this.sale = true;
  }

  // Point donné en UV photo ou directement dans l'espace de la composition
  versComp(pt) {
    const comp = this.preset[this.cle];
    if (pt.uv && comp.space === 'screen' && this.vue) {
      const v = this.vue;
      return [(pt.uv[0] - v[0]) / v[2], (pt.uv[1] - v[1]) / v[3]];
    }
    return pt.pos || pt.uv;
  }

  // Spot et lumière de fond : interpolés ici (quelques uniformes), fonction exacte de p
  appliquer(p) {
    if (!this.pret || !this.cle) return;
    const comp = this.preset[this.cle], u = this._programme(this.cle).valeurs, simple = !!this.opts.reduit;
    u.uProgress = p;
    if (comp.spot) {
      const S = comp.spot, tr = simple ? p : clamp01((p - S.delay) / S.duration), q = anac(tr);
      const courbe = (nom) => (nom === 'linear' ? tr : nom === 'smooth' ? smooth01(tr) : q);
      const a = this.versComp(S.from), b = this.versComp(S.to);
      const k = simple ? 1 : courbe(S.geo), qi = simple ? q : courbe(S.icurve);
      u.uSpotPos = [a[0] + (b[0] - a[0]) * k, a[1] + (b[1] - a[1]) * k];
      u.uSpotRadius = [S.from.radius[0] + (S.to.radius[0] - S.from.radius[0]) * k, S.from.radius[1] + (S.to.radius[1] - S.from.radius[1]) * k];
      u.uSpotI = simple ? S.to.intensity * q : S.from.intensity + (S.to.intensity - S.from.intensity) * qi;
      const h0 = S.hazeFrom ?? (this.preset.haze ?? 0.06), h1 = S.hazeTo ?? (this.preset.haze ?? 0.06);
      u.uSpotHaze = h0 + (h1 - h0) * k;
    }
    if (comp.fill) {
      const F = comp.fill, q = anac(simple ? p : clamp01((p - F.delay) / F.duration));
      const c = this.versComp(F);
      u.uFillPos = [c[0], c[1]];
      u.uFillRadius = [F.radius[0], F.radius[1]];
      u.uFillI = F.intensity * q;
      const a0 = this.preset.ambient ?? 0.12;
      u.uAmbient = a0 + ((F.ambientTo ?? a0) - a0) * q;
    }
  }

  _dessiner() {
    const gl = this.gl, prog = this._programme(this.cle), v = prog.valeurs;
    gl.viewport(0, 0, this.canvas.width, this.canvas.height);
    gl.useProgram(prog.p);
    gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, this.texPhoto);
    gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, this.texMasque);
    for (const un of prog.uniformes) {
      const x = v[un.nom];
      if (x === undefined) continue;
      switch (un.type) {
        case gl.FLOAT: gl.uniform1f(un.lieu, x); break;
        case gl.FLOAT_VEC2: gl.uniform2f(un.lieu, x[0], x[1]); break;
        case gl.FLOAT_VEC3: gl.uniform3f(un.lieu, x[0], x[1], x[2]); break;
        case gl.FLOAT_VEC4: gl.uniform4f(un.lieu, x[0], x[1], x[2], x[3]); break;
        case gl.SAMPLER_2D: gl.uniform1i(un.lieu, x); break;
        default: break;
      }
    }
    gl.bindBuffer(gl.ARRAY_BUFFER, this.tampon);
    gl.enableVertexAttribArray(0);
    gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
    gl.drawArrays(gl.TRIANGLE_FAN, 0, 4);
  }

  rendre() {
    if (this.pret && this.sale && this.visible) { this._dessiner(); this.sale = false; }
  }

  // Fixer la progression (tests, mise au point) : la scène ne suit plus le défilement tant que
  // liberer() n'est pas appelé.
  figer(p) { this.fige = clamp01(p); this.p = this.fige; this.appliquer(this.p); this.sale = true; }
  liberer() { this.fige = undefined; }

  // À chaque image : progression visée (défilement), progression affichée (vitesse bornée), rendu
  maj(scrollY, dt) {
    const vh = window.innerHeight || 1;
    const p0 = this.haut - FENETRE[0] * vh;
    const p1 = Math.max(this.haut + FENETRE[1] * vh, p0 + 0.2 * vh);
    // la section alignée (ou, si elle est plus haute que l'écran, tant qu'elle le remplit), la
    // lumière est levée ; elle retombe sur la même course quand la section s'en va par le haut
    const q0 = Math.max(this.haut + this.h - vh, p1);
    const q1 = q0 + (p1 - p0);
    this.cible = clamp01(Math.min((scrollY - p0) / (p1 - p0), (q1 - scrollY) / (q1 - q0)));
    let p = this.p;
    if (this.fige !== undefined) p = this.fige;
    else if (p < 0 || !(dt > 0)) p = this.cible;                       // première image : pas d'animation
    else {
      const pas = VITESSE * dt;
      p += Math.max(-pas, Math.min(pas, this.cible - p));
    }
    if (p !== this.p) { this.p = p; this.appliquer(p); this.sale = true; }
    this.visible = this.haut < scrollY + vh && this.haut + this.h > scrollY;
    this.rendre();
  }

  // Respiration : hors défilement, en temps réel, seulement à l'écran et onglet visible
  respirer(now) {
    const P = this.params;
    if (!this.pret || !P.breath || !this.visible || document.hidden) return;
    if (now - this.souffle < 1000 / P.breathFps) return;
    this.souffle = now;
    const ph = (now / 1000) * 2 * Math.PI / P.breathPeriod;
    this._programme(this.cle).valeurs.uBreath = [1 + P.breathAmp * P.breath * Math.sin(ph), 1 + P.breathRadius * P.breath * Math.sin(ph + 1.3)];
    this.sale = true;
    this.rendre();
  }
}

window.Lumiere = {
  creer: (section, opts) => new Scene(section, opts),
  PRESETS: PRESETS,
  anac: anac,
  VITESSE: VITESSE,
  FENETRE: FENETRE,
};
})();
