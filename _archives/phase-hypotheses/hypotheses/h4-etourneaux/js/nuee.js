/*
 * Nuee — Hypothèse 4 « Vol d'étourneaux » : moteur WebGL2 et chorégraphie.
 *
 * Des dizaines de milliers de points pâles, SANS ÉTAT : la position de chaque
 * point est une fonction pure de ses graines (hachage entier de gl_VertexID) et
 * d'un petit squelette calculé ici en double précision à chaque image :
 *
 *   point = épine(u, t − λ) + repère tordu × section (nappe / tube / filets)
 *         + bruit cohérent dans l'espace du corps + vagues d'attaque
 *
 *   - épine : 8 points de contrôle (Catmull-Rom) avec profils de largeur,
 *     d'épaisseur, de torsion et de division en filets ; un satellite (lobe ou
 *     aile, relié par un col) ; des filets ; 1 % d'étincelles ; un halo diffus ;
 *   - λ : retard le long du corps (la queue suit la tête) plus un retard étalé
 *     par plaques (entrées en pupitres, règle 2). Le squelette est évalué à
 *     4 instants t − λk et rangé dans une texture flottante 32 × 4 ; chaque
 *     point interpole selon son λ. Les vagues d'attaque parcourent le corps de
 *     la tête à la queue à vitesse finie ;
 *   - figures : traine (vol1), masse (vol2), voile (vol3), fil (scroll) = jeux
 *     de paramètres du même squelette, interpolés (jamais de fondu entre deux
 *     nuages sans rapport).
 *
 * Rendu : les points (gaussiennes, tirets orientés par la vitesse) s'additionnent
 * dans un tampon de luminance hors écran (R16F, sinon RGBA8), puis une passe
 * plein écran applique la rampe de couleur mesurée sur les références, la brume
 * au sol (voile) et un tramage léger. Noir opaque ailleurs : la page pose le
 * canvas en mix-blend-mode: lighten et règle l'opacité globale en CSS.
 *
 * Musique : avec une piste pré-analysée, la phase Φ = base·(t − offset) +
 * gain·∫énergie et le programme des figures (ruptures) sont des fonctions pures
 * du temps média (seek exact, images identiques à l'octet près). Ralentir
 * (vitesse, sensibilité) intègre une déformation du temps, jamais t × facteur.
 * Analyse directe seule : phase intégrée image par image. Sans rien : programme
 * autonome déterministe sur le même temps (horloge virtuelle).
 *
 * API : SPEC.md § 6. Repères (yeux, tête) : SPEC.md § 2.
 */
(function () {
  'use strict';

  // ------------------------------------------------------------------ constantes
  const IMG_W = 1920, IMG_H = 1080;      // repère des références (px image)
  const NOEUDS = 8;                      // points de contrôle de l'épine
  const TRANCHES = 4;                    // instantanés du squelette (retard)
  const TEX_W = 32;                      // texels par tranche
  const RETARD = 2.4;                    // s : retard de la queue sur la tête
  const DELTA_V = 0.1;                   // s : pas de la différence finie (vitesse)
  const LAM_MAX = RETARD + DELTA_V;      // λ de la dernière tranche
  const P_BRUIT = 400;                   // repli du temps du bruit (raccord de 30)
  const BASE = 0.55, GAIN = 0.9;         // Φ = BASE·t + GAIN·∫énergie
  const FLUX = 1 / 50;                   // tours de cycle le long du corps par unité de Φ
  const YEUX = [0.59, 0.315], R_YEUX = 0.06;
  const FIGS = ['traine', 'masse', 'voile', 'fil'];
  // Errance (réglage « errance ») : toute la figure voyage d'un côté à l'autre du cadre, s'attarde
  // sur les côtés et ne fait que traverser le centre, où sont les visages. Fonction de l'horloge
  // murale (continue quand le média boucle, figée avec elle), la queue suivant la tête de son
  // retard λ. ax, ay : demi-course en fraction de l'image ; periode, periodeY : s ; k : temps passé
  // sur les côtés (créneau adouci, tanh) ; depart : phase à t = 0 (côté gauche).
  const ERRANCE = { ax: 0.27, ay: 0.07, periode: 36, periodeY: 29, k: 2.5, depart: -Math.PI / 2 };
  function errance(mur, er) {
    if (!(er > 0)) return [0, 0];
    const s = Math.tanh(ERRANCE.k * Math.sin(2 * Math.PI * mur / ERRANCE.periode + ERRANCE.depart)) / Math.tanh(ERRANCE.k);
    return [er * ERRANCE.ax * IMG_W * s, er * ERRANCE.ay * IMG_H * Math.sin(2 * Math.PI * mur / ERRANCE.periodeY + 1.3)];
  }

  // Rampe mesurée sur les trois références (luminance cumulée → couleur, niveaux 0-255).
  // Reprise dans le shader de composition ; gardée ici pour la documentation et les tests.
  const RAMPE = [[0, 0, 0, 0], [12, 4, 13, 28], [20, 10, 21, 36], [40, 35, 45, 59], [64, 59, 69, 82],
    [128, 124, 133, 145], [200, 197, 205, 214], [236, 230, 237, 243], [255, 250, 253, 254]];

  /* Figures : coordonnées x, y en fraction de l'image (origine en haut à gauche),
   * z, largeur, épaisseur en px image (demi-mesures), torsion en radians.
   *   corps : 8 nœuds de la tête (u = 0) à la queue (u = 1) :
   *           [x, y, z, demi-largeur, demi-épaisseur, torsion, division en filets]
   *   rel   : densité relative par segment (la densité de base suit l'aire)
   *   sat   : 3 nœuds [x, y, z, demi-largeur, demi-épaisseur, torsion]
   *   col   : [x, y, z, demi-largeur, part des points du satellite]
   *   g     : bruit (fraction de la largeur), nappe (0 tube, 1 nappe à bords nets),
   *           bord (part de points au-delà du bord), serre (finesse des brins),
   *           alphas par groupe, filets (écart, début, finesse), satRond (lobe),
   *           mouvement (px image), sortie (gain de la couleur après la rampe : vol1 est
   *           composée à 54 % d'opacité, vol2 et vol3 à 100 %). */
  const FIGURES = {
    // vol1 : filets fins qui entrent par la gauche et s'enroulent (sens horaire) en tête large sur
    // le buste ; cœur dense en bas à gauche, coque en arc à droite (u = 0 : bout intérieur de la boucle)
    traine: {
      corps: [
        [0.605, 0.915, 20, 100, 45, 0.3, 0.00],
        [0.575, 0.790, 0, 170, 60, 0.2, 0.00],
        [0.630, 0.650, -10, 205, 65, 0.3, 0.00],
        [0.665, 0.510, -10, 195, 60, 0.2, 0.00],
        [0.625, 0.375, -20, 165, 55, 0.1, 0.00],
        [0.510, 0.290, -10, 120, 40, 0.0, 0.25],
        [0.290, 0.290, 0, 140, 30, 0.0, 0.80],
        [-0.060, 0.270, 0, 190, 20, 0.0, 1.00]
      ],
      rel: [1.4, 1.8, 1.4, 1.1, 0.8, 0.3, 0.07],
      sat: [[0.775, 0.370, 20, 70, 35, 0.3], [0.830, 0.620, 40, 110, 40, 0.4], [0.755, 0.870, 30, 70, 35, 0.3]],
      col: [0.62, 0.30, 0, 25, 0.0],
      g: { bruit: 0.12, nappe: 0.7, bord: 0.05, serre: 0.6, aCorps: 1.0, aSat: 0.75, aFil: 0.3, aEtinc: 0.6,
        filEcart: 1.15, filDebut: 0.6, filSerre: 0.5, satRond: 0.4, mouvement: 36, tordre: 0.25, onde: 0.6,
        eclat: 1.05, grain: 1.0, diffus: 0.06, portee: 90, sortie: 0.54 }
    },
    // vol2 : corps compact devant le chef, panache qui monte à gauche, satellite rond relié par un col fin
    masse: {
      corps: [
        [0.571, 0.015, 0, 40, 25, 0, 0],
        [0.543, 0.110, -20, 55, 35, 0, 0],
        [0.527, 0.220, -50, 85, 45, 0, 0],
        [0.545, 0.335, -80, 205, 60, 0, 0],
        [0.545, 0.460, -90, 262, 65, 0, 0],
        [0.537, 0.595, -90, 285, 65, 0, 0],
        [0.541, 0.735, -90, 245, 60, 0, 0],
        [0.555, 0.860, -80, 95, 40, 0, 0]
      ],
      rel: [1.4, 1.4, 1.2, 1.0, 1.0, 1.0, 1.0],
      sat: [[0.200, 0.390, -60, 0, 50, 0], [0.262, 0.380, -60, 110, 60, 0], [0.325, 0.365, -60, 0, 50, 0]],
      col: [0.42, 0.335, -80, 16, 0.07],
      g: { bruit: 0.04, nappe: 1.0, bord: 0.04, serre: 0.2, aCorps: 0.72, aSat: 0.12, aFil: 0.15, aEtinc: 0.5,
        filEcart: 0.15, filDebut: 0.22, filSerre: 0.8, satRond: 1.0, mouvement: 22, tordre: 0.06, onde: 0.25,
        eclat: 1.55, grain: 1.0, diffus: 0, portee: 0, sortie: 1 }
    },
    // vol3 : draperie de nappes repliées sur tout le cadre et voile clairsemé (halo) ; l'aile haute
    // droite est le satellite, fine et effilée vers sa pointe lumineuse
    voile: {
      corps: [
        [0.020, 0.070, 100, 270, 90, 0.35, 0],
        [0.210, 0.095, 60, 190, 80, 0.5, 0],
        [0.335, 0.275, 0, 115, 60, 0.75, 0],
        [0.400, 0.460, -40, 105, 50, 0.9, 0],
        [0.565, 0.590, 0, 130, 55, 0.5, 0],
        [0.845, 0.680, 30, 168, 60, 0.6, 0],
        [0.660, 0.810, 10, 220, 70, 0.3, 0],
        [0.360, 0.900, -50, 260, 75, 0.15, 0]
      ],
      rel: [0.9, 0.9, 1.4, 1.5, 1.2, 1.6, 1.8],
      sat: [[0.590, 0.255, 0, 95, 40, 0.4], [0.785, 0.170, 20, 78, 45, 0.7], [0.910, 0.145, 40, 34, 50, 0.9]],
      col: [0.60, 0.47, 0, 45, 0.10],
      g: { bruit: 0.14, nappe: 0.9, bord: 0.06, serre: 0.25, aCorps: 1.0, aSat: 0.42, aFil: 0.25, aEtinc: 0.3,
        filEcart: 2.0, filDebut: 0.6, filSerre: 0.7, satRond: 0.15, mouvement: 44, tordre: 0.35, onde: 0.7,
        eclat: 1.7, grain: 0.85, diffus: 0.18, portee: 110, sortie: 1 }
    },
    // forme discrète du scroll : quelques filets clairsemés et inégaux qui traversent la fenêtre
    fil: {
      corps: [
        [1.060, 0.520, 0, 170, 18, 0, 1],
        [0.880, 0.480, 0, 150, 18, 0, 1],
        [0.720, 0.440, 0, 120, 18, 0, 1],
        [0.560, 0.450, 0, 100, 18, 0, 1],
        [0.400, 0.420, 0, 115, 18, 0, 1],
        [0.250, 0.400, 0, 150, 18, 0, 1],
        [0.100, 0.370, 0, 175, 18, 0, 1],
        [-0.060, 0.350, 0, 190, 18, 0, 1]
      ],
      rel: [1, 1, 1, 1, 1, 1, 1],
      sat: [[-0.05, 0.66, 0, 14, 10, 0], [0.50, 0.58, 0, 22, 10, 0], [1.05, 0.70, 0, 14, 10, 0]],
      col: [0.5, 0.6, 0, 10, 0.0],
      g: { bruit: 0.10, nappe: 1.0, bord: 0.0, serre: 0.55, aCorps: 0.8, aSat: 0.45, aFil: 0.5, aEtinc: 0.35,
        filEcart: 1.6, filDebut: 0.0, filSerre: 0.5, satRond: 0, mouvement: 26, tordre: 0, onde: 0.8,
        eclat: 1.0, grain: 1.0, diffus: 0.06, portee: 60, sortie: 1 }
    }
  };
  const CLES_G = ['bruit', 'nappe', 'bord', 'serre', 'aCorps', 'aSat', 'aFil', 'aEtinc',
    'filEcart', 'filDebut', 'filSerre', 'satRond', 'mouvement', 'tordre', 'onde', 'eclat', 'grain',
    'diffus', 'portee', 'sortie'];

  // Mise à plat d'une figure (mélange = somme pondérée de vecteurs).
  const O_CORPS = 0, O_REL = 56, O_SAT = 63, O_COL = 81, O_G = 86, TAILLE_FIG = 86 + CLES_G.length;
  function aplatir(f) {
    const v = new Float64Array(TAILLE_FIG);
    f.corps.forEach((k, j) => { for (let i = 0; i < 7; i++) v[O_CORPS + j * 7 + i] = k[i]; });
    f.rel.forEach((r, j) => { v[O_REL + j] = r; });
    f.sat.forEach((k, j) => { for (let i = 0; i < 6; i++) v[O_SAT + j * 6 + i] = k[i]; });
    f.col.forEach((x, i) => { v[O_COL + i] = x; });
    CLES_G.forEach((c, i) => { v[O_G + i] = f.g[c]; });
    return v;
  }
  const PLATS = {};
  FIGS.forEach((n) => { PLATS[n] = aplatir(FIGURES[n]); });

  // ------------------------------------------------------------------ outils
  const clamp01 = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);
  const lisse = (a, b, x) => { const t = clamp01((x - a) / (b - a)); return t * t * (3 - 2 * t); };
  // Anacrouse puis résolution longue (règles 1 et 3), reprise de h3-lever.
  function anac(t) {
    t = clamp01(t);
    const a = 0.10 * (1 - Math.cos(Math.min(t, 1 / 3) * 1.5 * Math.PI));
    const u = clamp01((t - 1 / 3) * 1.5);
    const b = 0.10 + 0.90 * (1 - Math.pow(1 - u, 1.6));
    return a + (b - a) * lisse(0.18, 0.78, t);
  }
  function hash1(i) {
    let x = Math.imul((i | 0) ^ 0x9e3779b9, 0x85ebca6b);
    x ^= x >>> 13; x = Math.imul(x, 0xc2b2ae35); x ^= x >>> 16;
    return (x >>> 0) / 4294967296;
  }
  // Bruit de gradient 1D (longue période, déterministe).
  function bruit1(x) {
    const i = Math.floor(x), f = x - i;
    const g0 = hash1(i) * 2 - 1, g1 = hash1(i + 1) * 2 - 1;
    const s = f * f * (3 - 2 * f);
    return 2.2 * ((1 - s) * g0 * f + s * g1 * (f - 1));
  }
  const unAUn = (nom) => { const w = { traine: 0, masse: 0, voile: 0, fil: 0 }; if (nom in w) w[nom] = 1; return w; };
  const melangeW = (a, b, e) => { const w = {}; FIGS.forEach((n) => { w[n] = a[n] + (b[n] - a[n]) * e; }); return w; };
  const copieW = (a) => ({ traine: a.traine, masse: a.masse, voile: a.voile, fil: a.fil });
  const fmod = (x, p) => ((x % p) + p) % p;

  const PARAMS_DEFAUT = { intensite: 0.13, densite: 140000, taille: 1, trainees: 0.5, brume: 0.8,
    vitesse: 1, amplitude: 1, sensMusique: 1, sensScroll: 0.3, visage: 0.7, derriere: 0.5, errance: 0 };
  const MUS_NULLE = { energie: 0, attaque: 0, brillance: 0, graves: 0, mediums: 0, aigus: 0, nouveaute: 0 };

  // ------------------------------------------------------------------ shaders
  // Bruit simplex 3D (Ashima Arts / S. Gustavson, licence MIT).
  const GLSL_BRUIT = `
vec3 mod289(vec3 x){return x - floor(x*(1.0/289.0))*289.0;}
vec4 mod289(vec4 x){return x - floor(x*(1.0/289.0))*289.0;}
vec4 permute(vec4 x){return mod289(((x*34.0)+10.0)*x);}
vec4 taylorInvSqrt(vec4 r){return 1.79284291400159 - 0.85373472095314*r;}
float snoise(vec3 v){
  const vec2 C = vec2(1.0/6.0, 1.0/3.0);
  const vec4 D = vec4(0.0, 0.5, 1.0, 2.0);
  vec3 i = floor(v + dot(v, C.yyy));
  vec3 x0 = v - i + dot(i, C.xxx);
  vec3 g = step(x0.yzx, x0.xyz);
  vec3 l = 1.0 - g;
  vec3 i1 = min(g.xyz, l.zxy);
  vec3 i2 = max(g.xyz, l.zxy);
  vec3 x1 = x0 - i1 + C.xxx;
  vec3 x2 = x0 - i2 + C.yyy;
  vec3 x3 = x0 - D.yyy;
  i = mod289(i);
  vec4 p = permute(permute(permute(i.z + vec4(0.0, i1.z, i2.z, 1.0)) + i.y + vec4(0.0, i1.y, i2.y, 1.0)) + i.x + vec4(0.0, i1.x, i2.x, 1.0));
  float n_ = 0.142857142857;
  vec3 ns = n_ * D.wyz - D.xzx;
  vec4 j = p - 49.0 * floor(p * ns.z * ns.z);
  vec4 x_ = floor(j * ns.z);
  vec4 y_ = floor(j - 7.0 * x_);
  vec4 x = x_ * ns.x + ns.yyyy;
  vec4 y = y_ * ns.x + ns.yyyy;
  vec4 h = 1.0 - abs(x) - abs(y);
  vec4 b0 = vec4(x.xy, y.xy);
  vec4 b1 = vec4(x.zw, y.zw);
  vec4 s0 = floor(b0)*2.0 + 1.0;
  vec4 s1 = floor(b1)*2.0 + 1.0;
  vec4 sh = -step(h, vec4(0.0));
  vec4 a0 = b0.xzyw + s0.xzyw*sh.xxyy;
  vec4 a1 = b1.xzyw + s1.xzyw*sh.zzww;
  vec3 p0 = vec3(a0.xy, h.x); vec3 p1 = vec3(a0.zw, h.y);
  vec3 p2 = vec3(a1.xy, h.z); vec3 p3 = vec3(a1.zw, h.w);
  vec4 norm = taylorInvSqrt(vec4(dot(p0,p0), dot(p1,p1), dot(p2,p2), dot(p3,p3)));
  p0 *= norm.x; p1 *= norm.y; p2 *= norm.z; p3 *= norm.w;
  vec4 m = max(0.5 - vec4(dot(x0,x0), dot(x1,x1), dot(x2,x2), dot(x3,x3)), 0.0);
  m = m * m;
  return 105.0 * dot(m*m, vec4(dot(p0,x0), dot(p1,x1), dot(p2,x2), dot(p3,x3)));
}`;

  const VS_POINTS = `#version 300 es
precision highp float;
precision highp int;
uniform highp sampler2D uSq;     // squelette : ${TEX_W} × ${TRANCHES} texels RGBA32F
uniform highp sampler2D uMasque; // silhouette du chef (R8)
uniform vec4 uCdfA, uCdfB;       // densité le long du corps : cumul aux 8 nœuds
uniform vec4 uT;                 // temps du bruit (replié), poids de raccord, flux corps, flux satellite
uniform vec4 uTv;                // les mêmes, ${DELTA_V} s plus tôt (vitesse)
uniform vec4 uDiv;               // début des filets, scintillement (temps), aigus, retard Λ
uniform vec4 uCadre;             // px image -> px appareil : échelles x, y ; décalages x, y
uniform vec2 uEcran;             // taille du canvas (px appareil)
uniform vec4 uTirage;            // cadrage portrait : ancre x, centre x, facteur x, facteur y
uniform vec4 uTirageY;           // ancre y, centre y, -, -
uniform vec4 uPt;                // fwhm (px image), minimum (px appareil), intensité, part de tirets
uniform vec4 uChef;              // visage, derrière, alpha minimum, gain des tirets
uniform vec4 uAtt[8];            // vagues : âge (s), force
out float vA; out vec2 vDir; out float vR; out float vH;

const float PI2 = 6.28318530718;
const float ETINC = 0.01, SAT = 0.14, FIL = 0.08;
const float LAM_MAX = ${LAM_MAX.toFixed(4)};
const float P_BRUIT = ${P_BRUIT.toFixed(1)};
${GLSL_BRUIT}

uint hashu(uint x){ x ^= x >> 16; x *= 0x7feb352du; x ^= x >> 15; x *= 0x846ca68bu; x ^= x >> 16; return x; }
float rnd(inout uint s){ s = hashu(s); return float(s >> 8) * (1.0/16777216.0); }
float gauss(inout uint s){ float a = max(rnd(s), 1e-7), b = rnd(s); return sqrt(-2.0*log(a)) * cos(PI2*b); }
float h11(float x){ return float(hashu(uint(int(x) + 8192) * 2654435761u) >> 8) * (1.0/16777216.0); }

vec4 gT;   // temps courant (uT, ou uTv pour la vitesse)
// Bruit qui évolue avec le temps replié ; raccord en fondu sur les 30 dernières unités.
float nt(vec3 q, float s){
  float n = snoise(q + vec3(0.0, 0.0, s * gT.x));
  if (gT.y > 0.0) n = mix(n, snoise(q + vec3(0.0, 0.0, s * (gT.x - P_BRUIT))), gT.y);
  return n;
}
vec4 tx(int x, int k0, int k1, float f){
  return mix(texelFetch(uSq, ivec2(x, k0), 0), texelFetch(uSq, ivec2(x, k1), 0), f);
}
vec4 cr(vec4 p0, vec4 p1, vec4 p2, vec4 p3, float t){
  float t2 = t*t, t3 = t2*t;
  return 0.5*((2.0*p1) + (-p0+p2)*t + (2.0*p0-5.0*p1+4.0*p2-p3)*t2 + (-p0+3.0*p1-3.0*p2+p3)*t3);
}
float cdfInv(float c){
  float C[8] = float[8](uCdfA.x, uCdfA.y, uCdfA.z, uCdfA.w, uCdfB.x, uCdfB.y, uCdfB.z, uCdfB.w);
  float u = 1.0;
  for (int j = 0; j < 7; j++) {
    if (c < C[j+1]) { u = (float(j) + (c - C[j]) / max(C[j+1] - C[j], 1e-6)) / 7.0; break; }
  }
  return u;
}
// repère de section : normale dans le plan de l'image (n) et profondeur, tourné de th
// (th = π/2 : la nappe se présente par la tranche et s'éclaire d'elle-même)
void repere(vec2 n, float th, out vec3 N, out vec3 B){
  vec3 N0 = vec3(n, 0.0);
  N = cos(th) * N0 + vec3(0.0, 0.0, sin(th));
  B = -sin(th) * N0 + vec3(0.0, 0.0, cos(th));
}

struct Graine { float g, c0, ra, rb, rs, re, ge, rf, rv; vec3 g3; };
// part visible d'un groupe : les points s'éteignent un à un (fondu court), au-delà de 1 c'est un gain
float visible(float A, float rv){ return clamp((A - rv) * 20.0, 0.0, 1.0) * max(A, 1.0); }

// Position (px image, z vers le fond) et lumière relative d'un point.
// dLam : retard supplémentaire (différence finie de la vitesse).
vec4 position(Graine G, float dLam, out float uo, out float ech){
  bool etin = G.g < ETINC;
  bool sat = !etin && G.g < ETINC + SAT;
  bool fil = !etin && !sat && G.g < ETINC + SAT + FIL;
  float c = fract(G.c0 - (sat ? gT.w : gT.z));               // flux de la queue vers la tête
  float lum = smoothstep(0.0, 0.035, c) * smoothstep(1.0, 0.965, c);
  float u = sat ? 0.3 + 0.35 * c : (fil ? mix(uDiv.x, 1.0, c) : cdfInv(c));
  uo = u;
  // retard : position le long du corps + plaques étalées (bruit lisse, pas de couture)
  float stag = 0.5 + 0.5 * snoise(vec3(u * 2.7 + 1.3, G.ra * 1.9 + G.rb * 0.7, 3.7));
  float lam = sat ? uDiv.w * (0.25 + 0.5 * stag) : uDiv.w * (0.72 * u + 0.28 * stag);
  lam = clamp(lam + dLam, 0.0, LAM_MAX);
  float x = lam / LAM_MAX * 3.0;
  int k0 = min(int(x), 2), k1 = k0 + 1;
  float f = x - float(k0);
  vec4 G0 = tx(23, k0, k1, f);   // bruit, nappe, bord, serre
  vec4 G1 = tx(24, k0, k1, f);   // alphas : corps, satellite, filets, étincelles
  vec4 G2 = tx(25, k0, k1, f);   // filets : écart, finesse ; satellite : rondeur, part du col
  vec4 G3 = tx(26, k0, k1, f);   // gain des vagues, dispersion, éclat, grain
  ech = G3.w;

  // section : nappe uniforme (bords nets) ou disque (tube) ; quelques points au-delà du bord
  float aU = G.ra * 2.0 - 1.0, bU = G.rb * 2.0 - 1.0;
  vec2 disc = sqrt(G.ra) * vec2(cos(PI2 * G.rb), sin(PI2 * G.rb));
  float a = mix(disc.x, aU, G0.y);
  float b = mix(disc.y, bU, G0.y);
  if (G.re < G0.z) a = (aU < 0.0 ? -1.0 : 1.0) * (1.0 + abs(G.ge) * 0.1);

  vec3 p, Nn, Bn; float w, h;
  if (sat) {
    vec4 S0 = tx(16, k0, k1, f), S1 = tx(18, k0, k1, f), S2 = tx(20, k0, k1, f);
    vec4 R0 = tx(17, k0, k1, f), R1 = tx(19, k0, k1, f), R2 = tx(21, k0, k1, f);
    vec4 col = tx(22, k0, k1, f);
    if (G.rf < G2.w) {                                       // col fin vers le corps
      vec3 d = col.xyz - S1.xyz;
      vec3 n = normalize(vec3(-d.y, d.x, 0.0) + vec3(1e-4, 0.0, 0.0));
      float tc = 0.35 + 0.65 * G.rb;
      p = mix(S1.xyz, col.xyz, tc) + n * (G.g3.x * col.w * 0.5) + vec3(0.0, 0.0, G.g3.y * col.w);
      lum *= 0.8 * visible(G1.y, G.rv) * G3.z;
      return vec4(p, lum);
    }
    float us = c, ia = 1.0 - us;
    vec4 Cq = 2.0 * S1 - 0.5 * (S0 + S2);                     // la courbe passe par S1 en us = 0,5
    vec4 Sq = ia*ia*S0 + 2.0*us*ia*Cq + us*us*S2;
    vec3 dq = 2.0*ia*(Cq.xyz - S0.xyz) + 2.0*us*(S2.xyz - Cq.xyz);
    w = mix(max(Sq.w, 0.0), S1.w * sqrt(max(4.0*us*ia, 0.0)), G2.z);
    vec4 Rq = us < 0.5 ? mix(R0, R1, us * 2.0) : mix(R1, R2, us * 2.0 - 1.0);
    h = max(Rq.x, 1.0);
    vec2 tq = normalize(dq.xy + vec2(1e-4, 1e-5));
    repere(vec2(tq.y, -tq.x), Rq.y, Nn, Bn);
    p = Sq.xyz;
    lum *= visible(G1.y, G.rv);
  } else {
    float fj = u * 7.0;
    int j = min(int(fj), 6);
    float t = fj - float(j);
    vec4 A1 = tx(2*j, k0, k1, f), A2 = tx(2*(j+1), k0, k1, f);
    vec4 A0 = j > 0 ? tx(2*(j-1), k0, k1, f) : 2.0 * A1 - A2;     // bouts extrapolés : vitesse
    vec4 A3 = j < 6 ? tx(2*(j+2), k0, k1, f) : 2.0 * A2 - A1;     // constante, pas d'amas aux extrémités
    float st = smoothstep(0.0, 1.0, t);
    vec4 Bq = mix(tx(2*j+1, k0, k1, f), tx(2*(j+1)+1, k0, k1, f), st);
    // normales lissées par le squelette (texels 27-30) : la nappe ne se replie pas dans le plan
    vec4 Na = tx(27 + j / 2, k0, k1, f), Nb = tx(27 + (j + 1) / 2, k0, k1, f);
    vec2 na = (j % 2 == 0) ? Na.xy : Na.zw, nb = ((j + 1) % 2 == 0) ? Nb.xy : Nb.zw;
    vec4 S = cr(A0, A1, A2, A3, t);
    w = max(S.w, 0.0);
    h = max(Bq.x, 1.0);
    repere(normalize(mix(na, nb, st) + vec2(1e-5, 0.0)), Bq.y, Nn, Bn);
    p = S.xyz;
    // division en filets : brins irréguliers qui ondulent, avec des trous
    float split = fil ? 1.0 : Bq.z;
    if (split > 0.001) {
      float K = fil ? 6.0 : 5.0;
      float serre = fil ? G2.y : G0.w;
      float af = clamp(a * 0.5 + 0.5, 0.0, 0.9999) * K;
      float jb = floor(af);
      float sb = fil ? 17.0 : 3.0;
      float hb = h11(jb + sb), hb2 = h11(jb + sb + 31.0), hb3 = h11(jb + sb + 57.0);
      // centres irréguliers, épaisseur et lumière propres à chaque brin ; les brins se croisent
      float cen = mix((jb + 0.5) / K * 2.0 - 1.0, hb * 1.8 - 0.9, 0.55);
      float ond = 0.22 * nt(vec3(u * 1.6 + hb * 9.0, jb * 1.37, 0.5), 0.35);
      float ep = serre * (0.45 + 1.1 * hb2);
      float aS = (cen + ond + (af - jb - 0.5) * 2.0 / K * ep) * (fil ? G2.x : 1.0);
      a = mix(a, aS, split);
      b *= mix(1.0, 0.35, split);
      float trou = smoothstep(-0.45, 0.2, nt(vec3(u * 4.0, jb * 2.1 + 5.0, 1.3), 0.2));
      lum *= mix(1.0, (0.35 + 1.1 * hb3) * trou, split);
    }
    lum *= visible(fil ? G1.z : (etin ? G1.w : G1.x), G.rv);
  }

  // vagues d'attaque : de la tête à la queue, plus vite que le retard du corps
  float lw = uDiv.w * 0.45 * u + 0.12 * G.rs;
  float vg = 0.0;
  for (int i = 0; i < 8; i++) {
    float xa = (uAtt[i].x - lw) / 0.16;
    vg += xa > 0.0 ? uAtt[i].y * xa * exp(1.0 - xa) : 0.0;
  }
  vg *= G3.x;
  a *= 1.0 + 0.22 * vg;
  lum *= (1.0 + 0.35 * vg) * G3.z;

  // bord vivant : chaque côté de la section ondule lentement (l'intérieur reste homogène)
  w *= 1.0 + 0.12 * nt(vec3(u * 5.0, a < 0.0 ? 2.0 : 9.0, 0.3), 0.6);
  p += Nn * (a * w) + Bn * (b * h);
  // déplacement cohérent dans l'espace du corps (les voisins restent voisins), basse fréquence :
  // une fréquence plus haute marbre la densité (compression du nuage)
  vec3 q = vec3(u * 2.4, a * 0.8, b * 0.7);
  vec3 dd = vec3(nt(q, 1.0), nt(q + vec3(31.4, 7.7, 0.0), 1.0), nt(q + vec3(-11.3, 19.1, 5.5), 1.0));
  p += dd * G0.x * (1.0 + 1.5 * G3.y) * (0.5 * w + 22.0);
  // halo diffus (voile) : une part des points du corps, écartée de façon fixe autour de sa place
  vec4 G4 = tx(31, k0, k1, f);
  if (!sat && !fil && !etin && G.rf < G4.x) p += G.g3 * vec3(G4.y, 0.75 * G4.y, G4.y);
  // dispersion (rupture) : chaque point s'écarte un peu, puis tout se regroupe
  p += G.g3 * G3.y * vec3(55.0, 45.0, 80.0);
  if (etin && G.rf < 0.02) p += G.g3 * vec3(200.0, 150.0, 250.0);   // quelques étincelles détachées
  return vec4(p, lum);
}

// Visage dégagé : répulseur doux autour des yeux ; J = rapport des aires (densité compensée).
vec2 repousse(vec2 q, float fr, out float J){
  vec2 E = vec2(${(YEUX[0] * IMG_W).toFixed(2)}, ${(YEUX[1] * IMG_H).toFixed(2)});
  float R = ${(R_YEUX * IMG_W).toFixed(2)};
  vec2 e = q - E;
  float r = length(e);
  float Av = 0.95 * R * uChef.x * fr;
  float ex = exp(-(r * r) / (R * R));
  float r2 = r + Av * ex;
  J = (r2 / max(r, 1e-3)) * (1.0 - Av * 2.0 * r / (R * R) * ex);
  if (Av <= 0.0) { J = 1.0; return q; }
  return E + (r > 1e-3 ? e / r : vec2(1.0, 0.0)) * r2;
}

vec3 projette(vec3 p){
  float k = 1500.0 / max(1500.0 + p.z, 300.0);
  vec2 q = vec2(960.0, 540.0) + (p.xy - vec2(960.0, 540.0)) * k;
  q.x = uTirage.y + (q.x - uTirage.x) * uTirage.z;
  q.y = uTirageY.y + (q.y - uTirageY.x) * uTirage.w;
  return vec3(q, k);
}

void main(){
  uint id = uint(gl_VertexID);
  uint s = hashu(id * 747796405u + 2891336453u);
  Graine G;
  G.g = rnd(s); G.c0 = rnd(s); G.ra = rnd(s); G.rb = rnd(s); G.rs = rnd(s); G.re = rnd(s);
  G.ge = gauss(s); G.rf = rnd(s); G.rv = rnd(s);
  G.g3 = vec3(gauss(s), gauss(s), gauss(s));
  float zs = gauss(s), za = gauss(s);
  float rd = rnd(s), rang = rnd(s), rtw = rnd(s), rlen = rnd(s);
  bool etin = G.g < ETINC;

  gT = uT;
  float u, ech;
  vec4 P = position(G, 0.0, u, ech);
  vec3 sp = projette(P.xyz);
  float alpha = uPt.z * exp(0.35 * za) * P.w * pow(clamp(sp.z, 0.6, 1.4), 1.1);

  // visage dégagé : répulseur doux autour des yeux, densité compensée (pas de halo)
  float J;
  float fr = 0.55 + 0.9 * rtw;
  sp.xy = repousse(sp.xy, fr, J);
  alpha *= min(1.0, J);

  // masque du chef : haut de la silhouette (visage) et passage derrière
  vec2 uv = sp.xy / vec2(${IMG_W}.0, ${IMG_H}.0);
  float m = (uv.x > 0.0 && uv.x < 1.0 && uv.y > 0.0 && uv.y < 1.0) ? textureLod(uMasque, uv, 0.0).r : 0.0;
  float tete = (1.0 - smoothstep(0.43, 0.50, uv.y)) * smoothstep(0.50, 0.55, uv.x) * (1.0 - smoothstep(0.70, 0.74, uv.x));
  alpha *= 1.0 - 0.75 * uChef.x * m * tete;
  alpha *= 1.0 - uChef.y * m * smoothstep(0.0, 80.0, P.z);

  // taille : gaussienne de fwhm ≈ 2,9 px image, log-normale ; minimum d'empreinte
  vec2 dev = sp.xy * uCadre.xy + uCadre.zw;
  float fw = uPt.x * ech * exp(0.22 * zs) * clamp(sp.z, 0.6, 1.4) * uCadre.x;
  if (etin) {
    fw *= 1.35;
    alpha *= (2.2 + 1.5 * rlen) * (0.8 + 0.2 * sin(uDiv.y * (0.7 + rtw) + rtw * 50.0)) * (1.0 + 0.4 * uDiv.z);
  }
  if (fw < uPt.y) { alpha *= (fw / uPt.y) * (fw / uPt.y); fw = uPt.y; }

  // tirets : 30 à 40 % de points étirés, orientés par la vitesse quand elle compte
  vec2 dir = vec2(cos(PI2 * rang), sin(PI2 * rang));
  float L = 0.0;
  if (rd < uPt.w || etin) {
    gT = uTv;
    float u2, ech2;
    vec4 P2 = position(G, ${DELTA_V.toFixed(3)}, u2, ech2);
    float J2;
    vec2 dev2 = repousse(projette(P2.xyz).xy, fr, J2) * uCadre.xy + uCadre.zw;
    vec2 dv = dev - dev2;
    float vit = length(dv);
    // longueur du segment : 0,6 à 1,4 × fwhm (aspect visible 1,6 à 2,4, comme les références)
    L = fw * mix(0.6, 1.4, rlen) + min(vit * uChef.w, 0.6 * fw);
    if (vit > 1e-4) dir = normalize(mix(dir, dv / vit, smoothstep(0.25, 2.0, vit / fw)));
    alpha /= 1.0 + 0.35 * L / fw;
  }
  alpha = min(alpha, 0.9);
  if (alpha > 0.0005) alpha = max(alpha, uChef.z);
  float S = 2.2 * fw + L;
  vA = alpha; vDir = dir; vR = 2.2 * fw / S; vH = L / S;
  gl_PointSize = S;
  gl_Position = vec4(dev.x / uEcran.x * 2.0 - 1.0, 1.0 - dev.y / uEcran.y * 2.0, 0.0, 1.0);
  if (alpha < 0.0005 || S < 0.5) { gl_Position = vec4(2.0, 2.0, 2.0, 1.0); gl_PointSize = 0.0; }
}`;

  // Accumulation : luminance additive (une gaussienne étirée en capsule).
  const FS_POINTS = `#version 300 es
precision highp float;
in float vA; in vec2 vDir; in float vR; in float vH;
out vec4 o;
void main(){
  vec2 d = gl_PointCoord * 2.0 - 1.0;
  float t = clamp(dot(d, vDir), -vH, vH);
  vec2 e = d - vDir * t;
  float g = vA * exp(-3.355 * dot(e, e) / (vR * vR));
  o = vec4(g, g, g, g);
}`;

  // Repli sans tampon hors écran : sprites à cœur #DCE4EE et halo #0A1524, additifs.
  const FS_REPLI = `#version 300 es
precision highp float;
in float vA; in vec2 vDir; in float vR; in float vH;
out vec4 o;
void main(){
  vec2 d = gl_PointCoord * 2.0 - 1.0;
  float t = clamp(dot(d, vDir), -vH, vH);
  vec2 e = d - vDir * t;
  float r2 = dot(e, e) / (vR * vR);
  vec3 c = vec3(0.863, 0.894, 0.933) * exp(-6.0 * r2) + vec3(0.039, 0.082, 0.141) * 3.0 * exp(-2.0 * r2);
  o = vec4(c * vA * 1.2, 0.0);
}`;

  const VS_PLEIN = `#version 300 es
void main(){
  vec2 p = vec2(float((gl_VertexID << 1) & 2), float(gl_VertexID & 2));
  gl_Position = vec4(p * 2.0 - 1.0, 0.0, 1.0);
}`;

  // Composition : rampe mesurée + brume au sol (voile) + tramage ; noir opaque ailleurs.
  const FS_COMPO = `#version 300 es
precision highp float;
uniform highp sampler2D uAcc;
uniform vec4 uImg;     // rectangle de la nuée (px appareil, depuis le haut) : x, y, largeur, hauteur
uniform vec4 uBrume;   // niveau (luminance 0-255), temps lent, gain de sortie, hauteur du canvas
out vec4 o;
vec3 rampe(float y){
  vec3 c = mix(vec3(0.0), vec3(4.0, 13.0, 28.0), clamp(y / 12.0, 0.0, 1.0));
  c = mix(c, vec3(10.0, 21.0, 36.0), clamp((y - 12.0) / 8.0, 0.0, 1.0));
  c = mix(c, vec3(35.0, 45.0, 59.0), clamp((y - 20.0) / 20.0, 0.0, 1.0));
  c = mix(c, vec3(59.0, 69.0, 82.0), clamp((y - 40.0) / 24.0, 0.0, 1.0));
  c = mix(c, vec3(124.0, 133.0, 145.0), clamp((y - 64.0) / 64.0, 0.0, 1.0));
  c = mix(c, vec3(197.0, 205.0, 214.0), clamp((y - 128.0) / 72.0, 0.0, 1.0));
  c = mix(c, vec3(230.0, 237.0, 243.0), clamp((y - 200.0) / 36.0, 0.0, 1.0));
  c = mix(c, vec3(250.0, 253.0, 254.0), clamp((y - 236.0) / 19.0, 0.0, 1.0));
  return c;
}
float brume(vec2 uv, float t){
  float v = smoothstep(0.5, 0.85, clamp(uv.y, 0.0, 1.0));
  float ex = abs(clamp(uv.x, 0.0, 1.0) * 2.0 - 1.0);
  float bord = 0.9 + 0.6 * ex * ex;
  float n = 0.85 + 0.15 * sin(uv.x * 4.1 + t * 0.7 + 1.7 * sin(uv.y * 3.3 - t * 0.4)) * sin(uv.y * 2.3 + uv.x * 1.3 - t * 0.5);
  return v * bord * n;
}
void main(){
  ivec2 ij = ivec2(gl_FragCoord.xy);
  float L = texelFetch(uAcc, ij, 0).r * 255.0;
  float Y = L;
  if (uBrume.x > 0.0) {
    vec2 px = vec2(gl_FragCoord.x, uBrume.w - gl_FragCoord.y);
    Y += uBrume.x * brume((px - uImg.xy) / uImg.zw, uBrume.y);
  }
  // genou doux au-dessus de 200 : les cœurs plafonnent vers 230-250 comme sur les références
  if (Y > 200.0) Y = 200.0 + 55.0 * (1.0 - exp(-(Y - 200.0) / 55.0));
  vec3 c = rampe(Y) * uBrume.z;            // gain de sortie : opacité de la référence (vol1 ≈ 0,54)
  float tr = fract(52.9829189 * fract(dot(gl_FragCoord.xy, vec2(0.06711056, 0.00583715))));
  c += (tr - 0.5) * step(0.5, Y);
  o = vec4(clamp(c / 255.0, 0.0, 1.0), 1.0);
}`;

  // ------------------------------------------------------------------ moteur
  function create(canvas, opts) {
    opts = opts || {};
    const tactile = !!opts.tactile;
    const maxPoints = Math.max(1000, opts.maxPoints || (tactile ? 60000 : 250000));
    const dprMax = opts.dprMax || (tactile ? 1.5 : 2);
    const adaptatif = opts.adaptatif !== false;

    let gl = null, progPts = null, progCompo = null, progRepli = null, vao = null;
    let texSq = null, texMasque = null, texAcc = null, fbo = null, cible = 'aucune';
    let U = {}, Uc = {}, Ur = {};
    const sqData = new Float32Array(TEX_W * 4 * TRANCHES);
    const att = new Float32Array(32);

    const etat = {
      largeur: 0, hauteur: 0, dprDemande: 1, dpr: 1, rect: { x: 0, y: 0, w: 1, h: 1 }, W: 0, H: 0,
      D: 0, tPrec: null, murPrec: null,               // déformation du temps (vitesse, sensibilité)
      phiDirect: 0,                                   // phase intégrée (analyse directe seule)
      cis: 0,                                         // cisaillement de scroll lissé
      forcee: undefined, bascule: 1, depuis: null, affiche: null,
      direct: { hist: [], fig: 'traine', depuis: -1e9, attaques: [], prec: 0 },
      reduit: null, signature: '',
      plan: new WeakMap(),
      stats: { points: 0, ips: 0, ms: 0, figures: unAUn('traine'), phase: 0 },
      qualite: { n: 1, dprCap: dprMax, ema: 1 / 60, lent: 0, rapide: 0 }
    };

    const api = {
      ok: false,
      layout: layout,
      frame: frame,
      stats: () => Object.assign({}, etat.stats, { figures: copieW(etat.stats.figures) }),
      destroy: destroy,
      figures: FIGS.slice()
    };

    // -------------------------------------------------------------- GL
    function compile(type, src) {
      const sh = gl.createShader(type);
      gl.shaderSource(sh, src);
      gl.compileShader(sh);
      if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS) && !gl.isContextLost()) {
        throw new Error('Nuee : shader — ' + gl.getShaderInfoLog(sh));
      }
      return sh;
    }
    function programme(vs, fs) {
      const p = gl.createProgram();
      gl.attachShader(p, compile(gl.VERTEX_SHADER, vs));
      gl.attachShader(p, compile(gl.FRAGMENT_SHADER, fs));
      gl.linkProgram(p);
      if (!gl.getProgramParameter(p, gl.LINK_STATUS) && !gl.isContextLost()) {
        throw new Error('Nuee : programme — ' + gl.getProgramInfoLog(p));
      }
      return p;
    }
    function locs(p, noms) {
      const o = {};
      noms.forEach((n) => { o[n] = gl.getUniformLocation(p, n); });
      return o;
    }
    const NOMS_PTS = ['uSq', 'uMasque', 'uCdfA', 'uCdfB', 'uT', 'uTv', 'uDiv', 'uCadre', 'uEcran',
      'uTirage', 'uTirageY', 'uPt', 'uChef', 'uAtt'];

    function initGL() {
      gl = canvas.getContext('webgl2', {
        antialias: false, alpha: true, premultipliedAlpha: true, depth: false, stencil: false,
        preserveDrawingBuffer: !!opts.conserverTampon,
        failIfMajorPerformanceCaveat: !opts.logicielPermis,
        powerPreference: 'high-performance'
      });
      if (!gl) return false;
      const hp = gl.getShaderPrecisionFormat(gl.VERTEX_SHADER, gl.HIGH_FLOAT);
      if (!hp || hp.precision < 23) throw new Error('Nuee : highp indisponible dans le vertex shader');
      progPts = programme(VS_POINTS, FS_POINTS);
      progCompo = programme(VS_PLEIN, FS_COMPO);
      U = locs(progPts, NOMS_PTS);
      Uc = locs(progCompo, ['uAcc', 'uImg', 'uBrume']);
      vao = gl.createVertexArray();

      texSq = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, texSq);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA32F, TEX_W, TRANCHES, 0, gl.RGBA, gl.FLOAT, null);
      parametresTexture(gl.NEAREST);

      texMasque = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, texMasque);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.R8, 1, 1, 0, gl.RED, gl.UNSIGNED_BYTE, new Uint8Array([0]));
      parametresTexture(gl.LINEAR);
      chargerMasque();

      // tampon de luminance : R16F (EXT_color_buffer_float / _half_float), sinon RGBA8, sinon repli
      const f32 = gl.getExtension('EXT_color_buffer_float');
      const f16 = gl.getExtension('EXT_color_buffer_half_float');
      const essais = [], forcer = opts.cible;          // forcer : 'rgba8' | 'repli' (tests uniquement)
      if ((f32 || f16) && !forcer) essais.push(['demi-flottant', gl.R16F, gl.RED, gl.HALF_FLOAT], ['demi-flottant', gl.RGBA16F, gl.RGBA, gl.HALF_FLOAT]);
      if (forcer !== 'repli') essais.push(['rgba8', gl.RGBA8, gl.RGBA, gl.UNSIGNED_BYTE]);
      cible = 'repli';
      fbo = gl.createFramebuffer();
      for (const e of essais) {
        if (essaiCible(e[1], e[2], e[3])) { cible = e[0]; formatAcc = e; break; }
      }
      if (cible === 'repli') {
        gl.deleteFramebuffer(fbo); fbo = null;
        progRepli = programme(VS_POINTS, FS_REPLI);
        Ur = locs(progRepli, NOMS_PTS);
      }
      etat.W = etat.H = 0;                              // force la réallocation au prochain layout
      return true;
    }
    let formatAcc = null;
    function parametresTexture(filtre) {
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, filtre);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, filtre);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    }
    function essaiCible(fmt, base, type) {
      const t = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, t);
      gl.texImage2D(gl.TEXTURE_2D, 0, fmt, 4, 4, 0, base, type, null);
      parametresTexture(gl.NEAREST);
      gl.bindFramebuffer(gl.FRAMEBUFFER, fbo);
      gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, t, 0);
      const okc = gl.checkFramebufferStatus(gl.FRAMEBUFFER) === gl.FRAMEBUFFER_COMPLETE;
      gl.bindFramebuffer(gl.FRAMEBUFFER, null);
      gl.deleteTexture(t);
      while (gl.getError() !== gl.NO_ERROR) { /* vide la file d'erreurs */ }
      return okc;
    }
    function allouerCible(W, H) {
      if (!fbo) return;
      if (texAcc) gl.deleteTexture(texAcc);
      texAcc = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, texAcc);
      gl.texImage2D(gl.TEXTURE_2D, 0, formatAcc[1], W, H, 0, formatAcc[2], formatAcc[3], null);
      parametresTexture(gl.NEAREST);
      gl.bindFramebuffer(gl.FRAMEBUFFER, fbo);
      gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texAcc, 0);
      gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    }
    function chargerMasque() {
      const img = opts.masque;
      if (!img || !gl) return;
      const envoyer = () => {
        if (!gl || gl.isContextLost()) return;
        gl.bindTexture(gl.TEXTURE_2D, texMasque);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.R8, gl.RED, gl.UNSIGNED_BYTE, img);
        parametresTexture(gl.LINEAR);
        etat.signature = '';
      };
      if (img.complete && img.naturalWidth > 0) envoyer();
      else img.addEventListener('load', envoyer, { once: true });
    }

    function perdu(e) { e.preventDefault(); api.ok = false; if (opts.surEtat) opts.surEtat(false); }
    function retrouve() {
      try { api.ok = initGL(); } catch (err) { api.ok = false; console.error(err); }
      if (api.ok && etat.largeur) layout({ largeur: etat.largeur, hauteur: etat.hauteur, dpr: etat.dprDemande, rectImage: etat.rect });
      if (opts.surEtat) opts.surEtat(api.ok);
    }
    canvas.addEventListener('webglcontextlost', perdu, false);
    canvas.addEventListener('webglcontextrestored', retrouve, false);

    try { api.ok = initGL(); } catch (err) { api.ok = false; console.error(err); }

    // -------------------------------------------------------------- layout
    function layout(L) {
      etat.largeur = Math.max(1, L.largeur || canvas.clientWidth || 1);
      etat.hauteur = Math.max(1, L.hauteur || canvas.clientHeight || 1);
      etat.dprDemande = L.dpr || 1;
      const r = L.rectImage || { x: 0, y: 0, w: etat.largeur, h: etat.hauteur };
      etat.rect = { x: r.x, y: r.y, w: Math.max(1, r.w), h: Math.max(1, r.h) };
      if (!api.ok) return;
      let dpr = Math.min(etat.dprDemande, dprMax, etat.qualite.dprCap);
      // garde-fou mémoire : ~9 Mpx sur ordinateur, ~4,5 Mpx sur tactile (cf. h2)
      const maxPx = tactile ? 4.5e6 : 9e6;
      dpr = Math.max(0.5, Math.min(dpr, Math.sqrt(maxPx / (etat.largeur * etat.hauteur))));
      const W = Math.max(1, Math.round(etat.largeur * dpr)), H = Math.max(1, Math.round(etat.hauteur * dpr));
      if (W !== etat.W || H !== etat.H) {
        canvas.width = W; canvas.height = H;
        etat.W = W; etat.H = H;
        allouerCible(W, H);
        etat.signature = '';
      }
      etat.dpr = W / etat.largeur;
    }

    // -------------------------------------------------------------- musique
    function lirePiste(piste, t) {
      if (!piste || typeof piste.at !== 'function') return MUS_NULLE;
      const v = piste.at(t);
      return v || MUS_NULLE;
    }
    // Énergie autonome (sans piste) : lente, déterministe, et son intégrale exacte.
    const AUTO = [[0.25, 37], [0.15, 13.7], [0.08, 5.3]];
    function energieAuto(t) {
      let e = 0.38;
      AUTO.forEach(([a, p], i) => { e += a * Math.sin(2 * Math.PI * t / p + i * 1.3); });
      return clamp01(e);
    }
    function cumulAuto(t) {
      let c = 0.38 * t;
      AUTO.forEach(([a, p], i) => { c += a * p / (2 * Math.PI) * (Math.cos(i * 1.3) - Math.cos(2 * Math.PI * t / p + i * 1.3)); });
      return c;
    }
    function musAuto(t) {
      const e = energieAuto(t);
      return { energie: e, attaque: 0, brillance: 0.35 + 0.3 * e, graves: 0.3 + 0.4 * e, mediums: 0.4 + 0.2 * Math.sin(t / 11),
        aigus: 0.3, nouveaute: 0 };
    }
    function phaseM(src, t) {
      if (src.piste) return BASE * (t - (src.piste.offset || 0)) + GAIN * (src.piste.cumul ? src.piste.cumul(t) : 0);
      return BASE * t + GAIN * cumulAuto(t);
    }

    // Programme des figures : segments [t, figure, durée de transition], fonction pure de la piste.
    function choisir(src, t, prec, i) {
      let s = { traine: 0, masse: 0, voile: 0 };
      for (let k = 0; k < 16; k++) {
        const m = src.piste ? lirePiste(src.piste, t + k * 0.5) : musAuto(t + k * 0.5);
        s.masse += 0.6 * m.graves + 0.5 * m.energie + 0.2 * m.attaque;
        s.voile += 0.6 * m.mediums + 0.3 * m.aigus + 0.3 * m.energie + 0.2 * m.brillance;
        s.traine += 0.8 * (1 - m.energie) + 0.3 * m.brillance;
      }
      let best = null, bv = -1e9;
      ['traine', 'masse', 'voile'].forEach((n, j) => {
        const v = s[n] / 16 + 0.08 * (hash1(i * 7 + j) - 0.5);
        if (n !== prec && v > bv) { bv = v; best = n; }
      });
      return best;
    }
    function planPiste(piste) {
      const ev = piste.evenements || {};
      const R0 = ev.ruptures || [], A0 = ev.attaques || [];
      // la conduite manuelle change en cours de route : sa révision et l'offset entrent dans la clé
      const cle = R0.length + ':' + (R0.length ? R0[R0.length - 1].t : 0) + ':' + A0.length + ':' + piste.duree
        + ':' + (piste.offset || 0) + ':' + (piste.revision || 0);
      const c = etat.plan.get(piste);
      if (c && c.cle === cle) return c;
      const R = R0.slice().sort((a, b) => a.t - b.t);
      const src = { piste: piste };
      const t0 = piste.offset || 0, t1 = t0 + (piste.duree || 0);
      const segs = [{ t: -1e9, fig: choisir(src, t0, null, 0), d: 1 }];
      let der = t0;
      R.forEach((r) => {
        if (r.t - der < 9 || (r.force || 0) < 0.2 || r.t > t1) return;
        segs.push({ t: r.t, fig: choisir(src, r.t, segs[segs.length - 1].fig, segs.length), d: 2 + 4 * (1 - clamp01(r.force)) });
        der = r.t;
      });
      // aucune rupture exploitable : alternance douce
      if (segs.length === 1 && t1 - t0 > 40) {
        for (let t = t0 + 28; t < t1 - 8; t += 28) segs.push({ t: t, fig: choisir(src, t, segs[segs.length - 1].fig, segs.length), d: 5 });
      }
      const o = { cle: cle, segs: segs, ruptures: R, attaques: A0.slice().sort((a, b) => a.t - b.t) };
      etat.plan.set(piste, o);
      return o;
    }
    const ORDRE_AUTO = ['traine', 'voile', 'masse', 'traine', 'masse', 'voile'];
    function segAuto(t) {
      const P = 26;
      let k = Math.floor(t / P);
      const debut = (i) => i * P + 6 * (hash1(i * 13 + 5) - 0.5);
      if (t < debut(k)) k -= 1;
      else if (t >= debut(k + 1)) k += 1;
      return { k: k, t: debut(k), fig: ORDRE_AUTO[fmod(k, 6)], prec: ORDRE_AUTO[fmod(k - 1, 6)], d: 4.5 };
    }
    // Poids des figures au temps t (programme seul, avant forçage et scroll).
    function poidsProgramme(src, t) {
      if (src.direct && !src.piste) return unAUn(etat.direct.fig);
      if (src.piste) {
        const pl = planPiste(src.piste), S = pl.segs;
        let lo = 0, hi = S.length - 1;
        while (lo < hi) { const m = (lo + hi + 1) >> 1; if (S[m].t - 0.3 <= t) lo = m; else hi = m - 1; }
        const s = S[lo];
        if (lo === 0) return unAUn(s.fig);
        return melangeW(unAUn(S[lo - 1].fig), unAUn(s.fig), anac((t - (s.t - 0.3)) / s.d));
      }
      const s = segAuto(t);
      return melangeW(unAUn(s.prec), unAUn(s.fig), anac((t - (s.t - 0.3)) / s.d));
    }
    // Dispersion après une rupture : montée 1 s, regroupement sur 3 à 5 s.
    function dispersion(src, t) {
      const env = (x) => (x < -0.4 ? 0 : x < 0.6 ? lisse(-0.4, 0.6, x) : Math.exp(-(x - 0.6) / 1.6));
      let d = 0;
      if (src.piste) {
        const R = planPiste(src.piste).ruptures;
        for (let i = R.length - 1; i >= 0; i--) {
          const x = t - R[i].t;
          if (x > 9) break;
          d += clamp01(R[i].force || 0.5) * env(x);
        }
      } else if (!src.direct) {
        const s = segAuto(t);
        d = 0.5 * env(t - s.t) + 0.5 * env(t - (s.t + 26));
      } else {
        d = etat.direct.disp || 0;
      }
      return Math.min(1.2, d);
    }
    // Attaques récentes (âge en s par rapport à la tête), au plus 8.
    function attaques(src, t, sens) {
      att.fill(0);
      let liste = null;
      if (src.piste) liste = planPiste(src.piste).attaques;
      else if (src.direct) liste = etat.direct.attaques;
      if (liste && liste.length) {
        let lo = 0, hi = liste.length;
        while (lo < hi) { const m = (lo + hi) >> 1; if (liste[m].t <= t + 0.02) lo = m + 1; else hi = m; }
        let n = 0;
        for (let i = lo - 1; i >= 0 && n < 8; i--) {
          const age = t - liste[i].t;
          if (age > RETARD + 1.2) break;
          att[n * 4] = age; att[n * 4 + 1] = clamp01(liste[i].force == null ? 0.6 : liste[i].force) * sens; n++;
        }
      } else if (!src.piste && !src.direct) {
        // ambiance autonome : quelques vagues douces, déterministes
        let n = 0;
        for (let k = Math.floor(t / 4.3); k > Math.floor(t / 4.3) - 2 && n < 8; k--) {
          const ta = k * 4.3 + 1.5 * hash1(k * 3 + 1);
          const age = t - ta;
          if (age >= 0 && age < RETARD + 1.2) { att[n * 4] = age; att[n * 4 + 1] = 0.35 * sens; n++; }
        }
      }
    }
    // Analyse directe : historique (temps média) pour les tranches, attaques détectées, ruptures.
    function suivreDirect(direct, t, dtm) {
      const d = etat.direct;
      const v = (typeof direct.valeurs === 'function' ? direct.valeurs() : direct) || MUS_NULLE;
      if (dtm < 0 || dtm > 0.5) { d.hist.length = 0; d.attaques.length = 0; }
      d.hist.push({ t: t, v: Object.assign({}, MUS_NULLE, v) });
      while (d.hist.length > 2 && t - d.hist[0].t > LAM_MAX + 0.3) d.hist.shift();
      // attaque : maximum local au-dessus de 0,35, espacé de 0,15 s
      const a = v.attaque || 0;
      if (d.prec > a && d.prec > 0.35 && (!d.attaques.length || t - d.attaques[d.attaques.length - 1].t > 0.15)) {
        d.attaques.push({ t: t - dtm, force: d.prec });
        if (d.attaques.length > 16) d.attaques.shift();
      }
      d.prec = a;
      // nouveauté forte : changement de figure (au plus toutes les 12 s)
      d.disp = Math.max(0, (d.disp || 0) - Math.max(0, dtm) / 3.5);
      if ((v.nouveaute || 0) > 0.6 && t - d.depuis > 12) {
        d.fig = choisirDirect(v, d.fig);
        d.depuis = t;
        d.disp = 0.8;
      }
      return v;
    }
    function choisirDirect(m, prec) {
      const s = { masse: 0.6 * m.graves + 0.5 * m.energie, voile: 0.6 * m.mediums + 0.3 * m.aigus + 0.3 * m.energie,
        traine: 0.8 * (1 - m.energie) + 0.3 * m.brillance };
      let best = 'traine', bv = -1;
      Object.keys(s).forEach((n) => { if (n !== prec && s[n] > bv) { bv = s[n]; best = n; } });
      return best;
    }
    function musDirect(t) {
      const H = etat.direct.hist;
      if (!H.length) return MUS_NULLE;
      for (let i = H.length - 1; i >= 0; i--) if (H[i].t <= t) return H[i].v;
      return H[0].v;
    }

    // -------------------------------------------------------------- squelette
    const melange = new Float64Array(TAILLE_FIG);
    function melangerFigures(W) {
      melange.fill(0);
      FIGS.forEach((n) => {
        const w = W[n];
        if (w <= 1e-6) return;
        const v = PLATS[n];
        for (let i = 0; i < TAILLE_FIG; i++) melange[i] += w * v[i];
      });
      let s = 0;
      FIGS.forEach((n) => { s += W[n]; });
      if (s > 1e-6 && Math.abs(s - 1) > 1e-6) for (let i = 0; i < TAILLE_FIG; i++) melange[i] /= s;
      return melange;
    }
    const gIdx = {};
    CLES_G.forEach((c, i) => { gIdx[c] = O_G + i; });
    const cdf = new Float64Array(8);

    // Normales de section (dans le plan) : tangente lissée, rotation entre deux nœuds bornée par
    // 0,6 × distance / largeur, à partir du nœud le plus large : une nappe large ne se replie
    // jamais sur elle-même (sinon des caustiques en coin apparaissent sur les bords).
    const ang = new Float64Array(NOEUDS), lis = new Float64Array(NOEUDS);
    function normales(o) {
      for (let j = 0; j < NOEUDS; j++) {
        const a = o + Math.max(j - 1, 0) * 8, b = o + Math.min(j + 1, NOEUDS - 1) * 8;
        ang[j] = Math.atan2(sqData[b + 1] - sqData[a + 1], sqData[b] - sqData[a]);
        if (j > 0) {
          while (ang[j] - ang[j - 1] > Math.PI) ang[j] -= 2 * Math.PI;
          while (ang[j] - ang[j - 1] < -Math.PI) ang[j] += 2 * Math.PI;
        }
      }
      let jm = 0;
      for (let j = 0; j < NOEUDS; j++) {
        lis[j] = 0.25 * ang[Math.max(j - 1, 0)] + 0.5 * ang[j] + 0.25 * ang[Math.min(j + 1, NOEUDS - 1)];
        if (sqData[o + j * 8 + 3] > sqData[o + jm * 8 + 3]) jm = j;
      }
      const borne = (j, i) => {                       // i : nœud voisin déjà fixé
        const a = o + i * 8, b = o + j * 8;
        const ds = Math.hypot(sqData[b] - sqData[a], sqData[b + 1] - sqData[a + 1]);
        const lim = 0.6 * ds / Math.max(30, sqData[a + 3], sqData[b + 3]);
        const d = lis[j] - lis[i];
        if (d > lim) lis[j] = lis[i] + lim; else if (d < -lim) lis[j] = lis[i] - lim;
      };
      for (let j = jm + 1; j < NOEUDS; j++) borne(j, j - 1);
      for (let j = jm - 1; j >= 0; j--) borne(j, j + 1);
      for (let j = 0; j < NOEUDS; j++) {
        sqData[o + 108 + j * 2] = Math.sin(lis[j]);
        sqData[o + 108 + j * 2 + 1] = -Math.cos(lis[j]);
      }
    }

    // Remplit la tranche k du squelette (px image) : figure mélangée + mouvement propre + musique.
    function tranche(k, W, phi, m, disp, ctx) {
      const v = melangerFigures(W);
      const P = ctx.params, sens = ctx.sens;
      const E = m.energie || 0;
      const silence = 1 - lisse(0.04, 0.15, E);
      const amp = P.amplitude;
      const M = v[gIdx.mouvement] * amp * (0.75 + 0.5 * E * sens) * (1 - 0.45 * silence);
      const Me = v[gIdx.mouvement] * 0.5 * E * sens * amp;
      const larg = (0.88 + 0.24 * E * sens) * (1 + 0.18 * (m.mediums || 0) * sens) * (1 - 0.15 * silence * sens)
        * (1 + 0.3 * disp) * (1 - 0.3 * ctx.frontiere) * ctx.souffle;
      const epai = 1 + 0.25 * (m.graves || 0) * sens;
      const tx0 = 0.4 * M * Math.sin(0.21 * phi + 1.0) + ctx.errance[0], ty0 = 0.3 * M * Math.sin(0.17 * phi + 2.0) + ctx.errance[1];
      const o = k * TEX_W * 4;
      const cx = ctx.centre[0], cy = ctx.centre[1];
      const onde = v[gIdx.onde];
      for (let j = 0; j < NOEUDS; j++) {
        const b = O_CORPS + j * 7;
        const uj = j / 7;
        let x = v[b] * IMG_W, y = v[b + 1] * IMG_H;
        // respiration (règle 6) : échelle ±0,4 % autour du centre de la figure
        x = cx + (x - cx) * ctx.souffle; y = cy + (y - cy) * ctx.souffle;
        const kj = onde * j;
        x += tx0 + M * (0.6 * Math.sin(0.613 * phi - kj + 0.3) + 0.4 * bruit1(0.081 * phi - 0.3 * kj))
          + Me * Math.sin(1.37 * phi - kj);
        y += ty0 + 0.8 * M * (0.6 * Math.sin(0.471 * phi - kj + 1.1) + 0.4 * bruit1(0.074 * phi - 0.3 * kj + 50))
          + Me * Math.sin(1.11 * phi - kj + 2.0)
          + ctx.cis * (0.35 + 0.65 * uj) * 45;
        const z = v[b + 2] + 2.0 * M * Math.sin(0.389 * phi - kj + 2.0);
        const w = Math.max(0, v[b + 3] * larg * (1 + 0.07 * Math.sin(0.293 * phi - kj)));
        const h = Math.max(1, v[b + 4] * epai);
        const th = v[b + 5] + v[gIdx.tordre] * amp * Math.sin(0.331 * phi - kj + 0.5);
        const q = o + j * 8;
        sqData[q] = x; sqData[q + 1] = y; sqData[q + 2] = z; sqData[q + 3] = w;
        sqData[q + 4] = h; sqData[q + 5] = th; sqData[q + 6] = clamp01(v[b + 6]); sqData[q + 7] = 0;
      }
      normales(o);
      // satellite : lobe (masse) ou aile (voile) ; il orbite un peu autour de sa place
      const ox = 0.5 * M * Math.sin(0.27 * phi + 0.4), oy = 0.4 * M * Math.cos(0.23 * phi + 1.7);
      for (let i = 0; i < 3; i++) {
        const b = O_SAT + i * 6;
        let x = v[b] * IMG_W, y = v[b + 1] * IMG_H;
        x = cx + (x - cx) * ctx.souffle; y = cy + (y - cy) * ctx.souffle;
        x += tx0 + ox + 0.5 * M * Math.sin(0.57 * phi + 2.1 * i + 4.0);
        y += ty0 + oy + 0.4 * M * Math.sin(0.43 * phi + 1.7 * i + 5.0) + ctx.cis * 0.5 * 45;
        const q = o + 64 + i * 8;
        sqData[q] = x; sqData[q + 1] = y; sqData[q + 2] = v[b + 2] + M * Math.sin(0.35 * phi + i);
        sqData[q + 3] = Math.max(0, v[b + 3] * larg); sqData[q + 4] = Math.max(1, v[b + 4] * epai);
        sqData[q + 5] = v[b + 5] + v[gIdx.tordre] * amp * Math.sin(0.29 * phi + 1.3 * i); sqData[q + 6] = 0; sqData[q + 7] = 0;
      }
      let q = o + 88;
      sqData[q] = cx + (v[O_COL] * IMG_W - cx) * ctx.souffle + tx0;
      sqData[q + 1] = cy + (v[O_COL + 1] * IMG_H - cy) * ctx.souffle + ty0;
      sqData[q + 2] = v[O_COL + 2]; sqData[q + 3] = v[O_COL + 3] * larg;
      // globaux
      const B = m.brillance || 0;
      q = o + 92;
      sqData[q] = v[gIdx.bruit] * (0.6 + 0.4 * amp) * (1.1 - 0.2 * B * sens);
      sqData[q + 1] = clamp01(v[gIdx.nappe]); sqData[q + 2] = clamp01(v[gIdx.bord]); sqData[q + 3] = v[gIdx.serre];
      sqData[q + 4] = v[gIdx.aCorps]; sqData[q + 5] = v[gIdx.aSat]; sqData[q + 6] = v[gIdx.aFil]; sqData[q + 7] = v[gIdx.aEtinc];
      sqData[q + 8] = v[gIdx.filEcart]; sqData[q + 9] = v[gIdx.filSerre]; sqData[q + 10] = clamp01(v[gIdx.satRond]);
      sqData[q + 11] = clamp01(v[O_COL + 4]);
      sqData[q + 12] = sens; sqData[q + 13] = disp;
      sqData[q + 14] = v[gIdx.eclat] * (0.92 + 0.2 * B * sens); sqData[q + 15] = v[gIdx.grain] * (1.04 - 0.08 * B * sens);
      sqData[o + 124] = clamp01(v[gIdx.diffus]); sqData[o + 125] = v[gIdx.portee] * (1 + 0.5 * disp);
      if (k === 0) {
        // densité le long du corps : aire des segments × densité relative (tranche de tête)
        cdf[0] = 0;
        for (let j = 0; j < 7; j++) {
          const a = o + j * 8, b = a + 8;
          const len = Math.hypot(sqData[b] - sqData[a], sqData[b + 1] - sqData[a + 1]) + 20;
          const aire = len * (sqData[a + 3] + sqData[b + 3] + 30);
          cdf[j + 1] = cdf[j] + aire * Math.max(0.01, v[O_REL + j]);
        }
        for (let j = 1; j < 8; j++) cdf[j] /= cdf[7];
        ctx.filDebut = clamp01(v[gIdx.filDebut]);
      }
    }

    // -------------------------------------------------------------- image
    function frame(F) {
      if (!api.ok || !gl || gl.isContextLost()) return;
      const t0 = performance.now();
      F = F || {};
      if (!etat.W) layout({ largeur: canvas.clientWidth || canvas.width, hauteur: canvas.clientHeight || canvas.height, dpr: 1 });
      const P = Object.assign({}, PARAMS_DEFAUT, F.params || {});
      const media = F.media || { t: 0, enLecture: false };
      const scroll = F.scroll || { p: 0, v: 0, frontiere: 0 };
      const dt = Math.max(0, Math.min(0.25, F.dt || 0));
      const reduit = !!F.reduit;
      let mur = F.mur || 0, t = media.t || 0;
      const piste = F.piste || null, direct = (!piste && F.direct) ? F.direct : null;
      const src = { piste: piste, direct: direct };
      let fig = F.figure;
      if (fig === 'auto' || !(fig in FIGURES)) fig = null;

      // mouvement réduit : une image fixe ; on ne redessine que si le cadrage ou les réglages changent
      if (reduit) {
        if (!etat.reduit) etat.reduit = { mur: mur, t: t };
        mur = etat.reduit.mur; t = etat.reduit.t;
        // la piste arrive après la première image (chargement) : sa présence entre dans la signature
        const sig = [etat.W, etat.H, etat.rect.x, etat.rect.y, etat.rect.w, etat.rect.h, scroll.p, fig,
          piste ? 'p' + (piste.revision || 0) : direct ? 'd' : '-', JSON.stringify(P)].join('|');
        if (sig === etat.signature) return;
        etat.signature = sig;
      } else if (etat.reduit) { etat.reduit = null; etat.signature = ''; }

      const sens = P.sensMusique;
      // temps média écoulé (seek / boucle : pas d'intégration)
      const dtm = etat.tPrec == null ? 0 : t - etat.tPrec;
      const continu = dtm > 0 && dtm < 0.5;
      if (direct) suivreDirect(direct, t, dtm);
      const musT = (tt) => (piste ? lirePiste(piste, tt) : direct ? musDirect(tt) : musAuto(tt));
      const m0 = musT(t);

      // phase : Φ = Φm(t) + D ; D intègre la déformation (vitesse, sensibilité) et revient lentement à 0
      let phi;
      if (direct) {
        if (continu) etat.phiDirect += P.vitesse * (BASE + GAIN * sens * (m0.energie || 0)) * dtm;
        phi = etat.phiDirect;
      } else {
        if (continu && !reduit) {
          const dPm = phaseM(src, t) - phaseM(src, etat.tPrec);
          const dC = (dPm - BASE * dtm) / GAIN;
          etat.D += P.vitesse * (BASE * dtm + GAIN * sens * dC) - dPm;
          if (Math.abs(P.vitesse - 1) < 0.02 && Math.abs(sens - 1) < 0.02 && etat.D !== 0) {
            const pas = Math.min(Math.abs(etat.D), 0.08 * dtm);
            etat.D -= Math.sign(etat.D) * pas;
          }
        }
        phi = phaseM(src, t) + etat.D;
      }
      if (!reduit) etat.tPrec = t;
      const vitPhi = P.vitesse * (BASE + GAIN * sens * (m0.energie || 0));

      // cisaillement de scroll : suit la vitesse lissée, retombe dès l'arrêt
      if (dt > 0) {
        const cible = Math.max(-1, Math.min(1, (scroll.v || 0) / 2500)) * P.sensScroll;
        etat.cis += (cible - etat.cis) * (1 - Math.exp(-dt / 0.25));
      } else if (reduit) etat.cis = 0;

      // poids des figures par tranche : programme (temps t − λk), forçage, scroll vers « fil »
      const p = clamp01(scroll.p || 0);
      const versFil = lisse(0.02, 0.9, p);
      const lamK = [0, LAM_MAX / 3, 2 * LAM_MAX / 3, LAM_MAX];
      const prog = lamK.map((l) => poidsProgramme(src, t - l));
      // changement de figure forcée (ou de figure en analyse directe) : bascule de 3 s, étalée
      // de la tête à la queue ; instantanée quand l'horloge murale est gelée (captures de test)
      const cleProg = fig ? 'f:' + fig : direct ? 'd:' + etat.direct.fig : 'auto';
      if (cleProg !== etat.forcee) {
        etat.depuis = (etat.affiche || prog).map(copieW);
        etat.bascule = (dt > 0 && etat.forcee !== undefined) ? 0 : 1;
        etat.forcee = cleProg;
      }
      // en mouvement réduit on ne redessine qu'au changement : la bascule doit être immédiate
      if (etat.bascule < 1) etat.bascule = (dt > 0 && !reduit) ? Math.min(1, etat.bascule + dt / 3) : 1;
      const Wk = prog.map((w, k) => {
        const cibleW = fig ? unAUn(fig) : w;
        let r = cibleW;
        if (etat.bascule < 1) r = melangeW(etat.depuis[k], cibleW, anac(clamp01(etat.bascule * 1.6 - 0.6 * k / 3)));
        return r;
      });
      etat.affiche = Wk.map(copieW);
      const Wf = Wk.map((w, k) => melangeW(w, unAUn('fil'), anac(clamp01(versFil * 1.6 - 0.6 * k / 3))));

      // contexte commun aux tranches
      const souffle = reduit ? 1 : 1 + 0.004 * Math.sin(2 * Math.PI * mur / 8.3);
      const ctx = { params: P, sens: sens, souffle: souffle, cis: etat.cis, frontiere: clamp01(scroll.frontiere || 0) * versFil,
        centre: [0.6 * IMG_W, 0.5 * IMG_H], filDebut: 0, errance: [0, 0] };
      for (let k = 0; k < TRANCHES; k++) {
        const tk = t - lamK[k];
        ctx.errance = errance(mur - lamK[k], P.errance);
        const mk = k === 0 ? m0 : musT(tk);
        const phik = direct ? phi - vitPhi * lamK[k] : phaseM(src, tk) + etat.D;
        tranche(k, Wf[k], phik, mk, dispersion(src, tk) * sens, ctx);
      }
      attaques(src, t, sens);

      // temps du bruit (replié), flux le long du corps, scintillement
      const tau = 0.075 * phi + 0.035 * mur;
      const tauV = tau - (0.075 * vitPhi + 0.035) * DELTA_V;
      const tw = fmod(tau, P_BRUIT), twV = fmod(tauV, P_BRUIT);
      const rac = lisse(P_BRUIT - 30, P_BRUIT, tw), racV = lisse(P_BRUIT - 30, P_BRUIT, twV);
      const fl = phi * FLUX, flV = (phi - vitPhi * DELTA_V) * FLUX;

      // nombre de points : préfixe des identifiants (échantillon uniforme), alpha compensé
      const voulu = Math.max(0, Math.round(P.densite));
      const N = Math.min(voulu, maxPoints, Math.round(voulu * etat.qualite.n));
      const comp = N > 0 ? voulu / N : 1;

      // cadrage : rectangle de l'image dans l'ouverture, puis la fenêtre (ancrage par p)
      const r = etat.rect, Lc = etat.largeur, Hc = etat.hauteur;
      const sC = Math.max(Lc / IMG_W, Hc / IMG_H);
      const fx = r.w > Lc + 0.5 ? clamp01(-r.x / (r.w - Lc)) : 0.5;
      const rf = { w: IMG_W * sC, h: IMG_H * sC };
      rf.x = (Lc - rf.w) * fx; rf.y = (Hc - rf.h) * 0.5;
      const an = lisse(0.15, 0.85, p);
      const re = { x: r.x + (rf.x - r.x) * an, y: r.y + (rf.y - r.y) * an, w: r.w + (rf.w - r.w) * an, h: r.h + (rf.h - r.h) * an };
      // fenêtre visible (fraction de l'image) : la nuée s'y resserre quand le cadrage rogne
      const u0 = clamp01(-re.x / re.w), u1 = clamp01((Lc - re.x) / re.w);
      const v0 = clamp01(-re.y / re.h), v1 = clamp01((Hc - re.y) / re.h);
      const ww = Math.max(0.05, u1 - u0), wh = Math.max(0.05, v1 - v0);
      const kx = Math.min(1, ww * 1.7), ky = Math.min(1, wh * 1.7);
      const ancX = 0.6 * IMG_W, ancY = 0.5 * IMG_H;
      const cenX = ancX + ((u0 + u1) / 2 * IMG_W - ancX) * clamp01((1 - ww) * 2);
      const cenY = ancY + ((v0 + v1) / 2 * IMG_H - ancY) * clamp01((1 - wh) * 2);
      const dpr = etat.dpr;
      // lumière par point : compensée quand on dessine moins de points (tactile, qualité), et réduite
      // d'autant que le cadrage portrait resserre la figure (même densité apparente qu'à l'ordinateur)
      const intensite = Math.min(0.6, P.intensite * comp) * kx * ky;
      // gain de sortie des figures de tête (vol1 est composée à ~54 % d'opacité, les autres à 100 %)
      let sortie = 0, sw = 0;
      FIGS.forEach((n) => { sortie += Wf[0][n] * FIGURES[n].g.sortie; sw += Wf[0][n]; });
      sortie = sw > 1e-6 ? sortie / sw : 1;

      // passe 1 : accumulation des points
      const prog1 = cible === 'repli' ? progRepli : progPts;
      const Up = cible === 'repli' ? Ur : U;
      gl.bindVertexArray(vao);
      gl.useProgram(prog1);
      gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, texSq);
      gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, 0, TEX_W, TRANCHES, gl.RGBA, gl.FLOAT, sqData);
      gl.uniform1i(Up.uSq, 0);
      gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, texMasque);
      gl.uniform1i(Up.uMasque, 1);
      gl.uniform4f(Up.uCdfA, cdf[0], cdf[1], cdf[2], cdf[3]);
      gl.uniform4f(Up.uCdfB, cdf[4], cdf[5], cdf[6], 1);
      gl.uniform4f(Up.uT, tw, rac, fl - Math.floor(fl), fmod(0.6 * fl, 1));
      gl.uniform4f(Up.uTv, twV, racV, flV - Math.floor(flV), fmod(0.6 * flV, 1));
      gl.uniform4f(Up.uDiv, ctx.filDebut, fmod(mur, 1000), (m0.aigus || 0) * sens, RETARD);
      gl.uniform4f(Up.uCadre, re.w / IMG_W * dpr, re.h / IMG_H * dpr, re.x * dpr, re.y * dpr);
      gl.uniform2f(Up.uEcran, etat.W, etat.H);
      gl.uniform4f(Up.uTirage, ancX, cenX, kx, ky);
      gl.uniform4f(Up.uTirageY, ancY, cenY, 0, 0);
      gl.uniform4f(Up.uPt, 2.9 * P.taille, 1.5 * dpr, intensite * (cible === 'repli' ? sortie : 1), reduit ? 0.35 * P.trainees : 0.7 * P.trainees);
      const effacement = 1 - lisse(0.0, 0.08, p);         // effets calés au pixel : s'effacent au scroll
      gl.uniform4f(Up.uChef, P.visage * effacement, P.derriere * effacement,
        cible === 'rgba8' ? 4 / 255 : 0, reduit ? 0 : 0.8 * P.trainees);
      gl.uniform4fv(Up.uAtt, att);
      gl.enable(gl.BLEND);
      gl.blendEquation(gl.FUNC_ADD);
      gl.blendFunc(gl.ONE, gl.ONE);
      if (cible === 'repli') {
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        gl.viewport(0, 0, etat.W, etat.H);
        gl.colorMask(true, true, true, true);
        gl.clearColor(0, 0, 0, 1);
        gl.clear(gl.COLOR_BUFFER_BIT);
        gl.colorMask(true, true, true, false);           // alpha reste à 1 : canvas opaque
        if (N > 0) gl.drawArrays(gl.POINTS, 0, N);
        gl.colorMask(true, true, true, true);
      } else {
        gl.bindFramebuffer(gl.FRAMEBUFFER, fbo);
        gl.viewport(0, 0, etat.W, etat.H);
        gl.clearColor(0, 0, 0, 0);
        gl.clear(gl.COLOR_BUFFER_BIT);
        if (N > 0) gl.drawArrays(gl.POINTS, 0, N);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        // passe 2 : rampe, brume, tramage
        gl.disable(gl.BLEND);
        gl.useProgram(progCompo);
        gl.activeTexture(gl.TEXTURE2); gl.bindTexture(gl.TEXTURE_2D, texAcc);
        gl.uniform1i(Uc.uAcc, 2);
        gl.uniform4f(Uc.uImg, re.x * dpr, re.y * dpr, re.w * dpr, re.h * dpr);
        const wv = Wf[0].voile;
        const niveau = 42 * P.brume * wv;                // brume 0,8 : ≈ 26 niveaux au sol au centre, 40 au bord (vol3)
        gl.uniform4f(Uc.uBrume, niveau, fmod(0.02 * phi + 0.01 * mur, 600), sortie, etat.H);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
      }
      gl.disable(gl.BLEND);
      gl.bindVertexArray(null);

      // qualité adaptative : intervalle entre images > 22 ms → moins de points, puis moins de pixels
      const q = etat.qualite;
      if (adaptatif && !reduit && dt > 0 && dt < 0.25) {
        q.ema += (dt - q.ema) * 0.1;
        if (q.ema > 0.022) { q.lent += dt; q.rapide = 0; } else if (q.ema < 0.0145) { q.rapide += dt; q.lent = 0; } else { q.lent = q.rapide = 0; }
        if (q.lent > 1.5) {
          q.lent = 0;
          if (q.n > 0.45) q.n *= 0.8;
          else if (q.dprCap > 1) { q.dprCap = Math.max(1, q.dprCap - 0.25); relayout(); }
        } else if (q.rapide > 4) {
          q.rapide = 0;
          if (q.dprCap < dprMax) { q.dprCap = Math.min(dprMax, q.dprCap + 0.25); relayout(); } else if (q.n < 1) q.n = Math.min(1, q.n * 1.15);
        }
      }
      const st = etat.stats;
      st.points = N; st.voulu = voulu; st.phase = phi; st.figures = copieW(Wf[0]);
      st.figuresQueue = copieW(Wf[TRANCHES - 1]);
      st.ms = performance.now() - t0;
      if (dt > 0) st.ips = st.ips ? st.ips + (1 / dt - st.ips) * 0.1 : 1 / dt;
      st.cible = cible; st.dpr = dpr; st.qualite = { n: q.n, dprCap: q.dprCap };
      st.intensite = intensite; st.sortie = sortie; st.dispersion = dispersion(src, t) * sens; st.D = etat.D;
      st.source = piste ? 'piste' : direct ? 'direct' : 'autonome';
      st.errance = errance(mur, P.errance);       // déplacement de la tête (px image)
    }
    function relayout() {
      layout({ largeur: etat.largeur, hauteur: etat.hauteur, dpr: etat.dprDemande, rectImage: etat.rect });
    }

    function destroy() {
      canvas.removeEventListener('webglcontextlost', perdu);
      canvas.removeEventListener('webglcontextrestored', retrouve);
      if (gl && !gl.isContextLost()) {
        [texSq, texMasque, texAcc].forEach((t) => t && gl.deleteTexture(t));
        if (fbo) gl.deleteFramebuffer(fbo);
        [progPts, progCompo, progRepli].forEach((p) => p && gl.deleteProgram(p));
        if (vao) gl.deleteVertexArray(vao);
      }
      api.ok = false;
      gl = null;
    }

    return api;
  }

  window.Nuee = { create: create, FIGURES: FIGURES, RAMPE: RAMPE, version: 1 };
})();
