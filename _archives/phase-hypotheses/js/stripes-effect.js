/*
 * StripesHero — photo plein écran rendue par Pixi.js (Mesh + shader custom).
 * L'effet des maquettes du DA est recomposé en code depuis la photo d'origine.
 *
 * Une PRESET par photo (data-preset sur la section) décrit :
 *   - focus    : point de la photo gardé au centre quand l'écran recadre ;
 *   - mask     : détourage du sujet (blanc = sujet) pour faire passer des
 *                stries DERRIÈRE lui ;
 *   - desktop  : composition fidèle à la maquette, en UV PHOTO (0→1, y vers le bas) ;
 *   - portrait : composition pour écran vertical, en coordonnées ÉCRAN (0→1 sur
 *                la zone visible) — un smartphone ne montre qu'un tiers de la photo.
 *
 * Effet : stries verticales = photo pivotée de 180° (teintée ou non) vue à
 * travers des bandes ; bandeau horizontal optionnel = morceaux de photo
 * retournés, à 100 %. uProgress (0→1) suit le scroll : 0 = photo nue.
 *
 * Mouvement : AUCUN épinglage (un pin crée une butée : la page s'arrête net).
 * Sur ordinateur, la photo « s'attarde » : elle ralentit progressivement
 * pendant que l'effet se construit, puis reprend la vitesse du scroll, sans
 * discontinuité. Sur écran tactile, défilement natif pur (aucune position
 * recalculée en JS, donc aucune saccade sur iOS) : seul l'effet suit le scroll.
 *
 * Rendu à la demande : le canvas n'est redessiné que lorsque l'effet change et
 * que la photo est à l'écran (pas de boucle à 60 i/s, important sur mobile).
 */

// Grille alignée sur la maquette (UV photo) → [x0, x1, retard, sens, derrière le sujet]
function stripeGrid({ period, width, offset, count, skip = [], behind = [], focusX, spread, delay = 0 }) {
  const out = [];
  const maxDist = Math.max(focusX, 1 - focusX);
  for (let k = 0; k < count; k++) {
    if (skip.includes(k)) continue;
    const x0 = offset + k * period;
    const x1 = x0 + width;
    // les stries les plus proches du sujet partent en premier, puis vers les bords
    const d = Math.abs((x0 + x1) / 2 - focusX) / maxDist;
    out.push([x0, x1, delay + d * spread, k % 2 ? -1 : 1, behind.includes(k)]);
  }
  return out;
}

// Grille régulière en coordonnées ÉCRAN (portrait) : `count` stries d'une
// demi-période, le milieu de l'écran tombe entre deux stries (count pair).
function screenGrid({ count, focusX = 0.5, spread = 0.4, behind = true }) {
  const period = 1 / count;
  const half = period / 4;
  const maxDist = Math.max(focusX, 1 - focusX);
  const out = [];
  for (let k = 0; k < count; k++) {
    const cx = (k + 0.5) * period;
    const d = Math.abs(cx - focusX) / maxDist;
    out.push([cx - half, cx + half, d * spread, k % 2 ? -1 : 1, behind]);
  }
  return out;
}

const STRIPES_PRESETS = {
  // Maquette bleuse-2.jpg : bandeau bas + 4 stries à 0,5, sans teinte
  'bleuse-1': {
    focus: [0.55, 0.45],
    mask:  'assets/bleuse-1-masque.png',
    desktop: {
      space:       'image',
      reveal:      'wipe',   // volet vertical
      stripeAlpha: 0.5,
      tint:        [0, 0, 0],
      tintMix:     0,
      vignette:    0.06,
      stripes: [
        [0.182, 0.217, 0.00,  1, false],
        [0.227, 0.260, 0.12, -1, false],
        [0.270, 0.304, 0.06,  1, false],
        [0.313, 0.353, 0.18, -1, false],
      ],
      band: {
        y0: 0.745,
        y1: 0.824,
        // [x0, x1, centre source X (UV photo), décalage source Y, retard, sens du volet]
        segments: [
          [0.130, 0.355, 0.78, -0.10, 0.30,  1],
          [0.355, 0.445, 0.55,  0.12, 0.38, -1],
          [0.445, 0.690, 0.60,  0.10, 0.34,  1],
          [0.690, 1.000, 0.30, -0.04, 0.42, -1],
        ],
      },
    },
    // Écran vertical : le visage occupe presque toute la largeur visible.
    // Stries de part et d'autre, DERRIÈRE le sujet ; bandeau sur toute la largeur.
    portrait: {
      space:       'screen',
      reveal:      'wipe',
      stripeAlpha: 0.5,
      tint:        [0, 0, 0],
      tintMix:     0,
      vignette:    0,
      stripes: [
        [0.03, 0.10, 0.00,  1, true],
        [0.13, 0.18, 0.12, -1, true],
        [0.76, 0.83, 0.06,  1, true],
        [0.87, 0.93, 0.18, -1, true],
      ],
      band: {
        y0: 0.745,
        y1: 0.824,
        segments: [
          [0.00, 0.30, 0.78, -0.10, 0.30,  1],
          [0.30, 0.45, 0.55,  0.12, 0.38, -1],
          [0.45, 0.72, 0.60,  0.10, 0.34,  1],
          [0.72, 1.00, 0.30, -0.04, 0.42, -1],
        ],
      },
    },
  },

  // Maquette bleuse-4.jpg (relevée au pixel sur 992 × 619) :
  // grille de stries de 30,5 px tous les 60,25 px, vide autour du chef,
  // remplissage OPAQUE = 50 % photo pivotée 180° + 50 % lavande #5A638D,
  // les 2 stries juste à droite du chef passent derrière son bras.
  'bleuse-3': {
    focus: [0.61, 0.40],
    mask:  'assets/bleuse-3-masque.png',
    desktop: {
      space:       'image',
      reveal:      'open',   // la strie s'ouvre depuis son axe, façon persienne
      stripeAlpha: 1.0,
      tint:        [90, 99, 142],
      tintMix:     0.5,
      vignette:    0,
      stripes: stripeGrid({
        period: 60.25 / 992,
        width:  30.5 / 992,
        offset: -1.5 / 992,
        count:  17,
        skip:   [9, 10, 11],
        behind: [12, 13],
        focusX: 0.61,
        spread: 0.4,
      }),
    },
    // Écran vertical : le chef remplit le centre ; la grille couvre tout
    // l'écran et passe entièrement derrière lui (il reste au premier plan).
    portrait: {
      space:       'screen',
      reveal:      'open',
      stripeAlpha: 1.0,
      tint:        [90, 99, 142],
      tintMix:     0.5,
      vignette:    0,
      stripes:     screenGrid({ count: 8, focusX: 0.5, spread: 0.4 }),
    },
  },

  // Maquette bleuse-6.png : deux bandes blanches translucides, pleine hauteur,
  // qui encadrent le chef accroupi (relevé au pixel : zone nette x 0,509 → 0,772).
  // Opacité 0,5 (consigne) ; le rendu de la maquette correspond plutôt à ~0,32.
  'bleuse-5': {
    focus: [0.645, 0.45],   // centre du chef
    desktop: {
      space: 'image',
      veils: {
        color: [255, 255, 255],
        alpha: 0.5,
        // [x0, x1, retard, sens] : voile gauche depuis le haut, voile droit depuis le bas
        items: [
          [0.0000, 0.5091, 0.00,  1],
          [0.7722, 1.0000, 0.10, -1],
        ],
      },
    },
    // Écran vertical : le chef occupe le tiers central de la photo, soit
    // l'essentiel de l'écran ; les voiles s'arrêtent à ses épaules.
    portrait: {
      space: 'screen',
      veils: {
        color: [255, 255, 255],
        alpha: 0.5,
        items: [
          [0.00, 0.17, 0.00,  1],
          [0.83, 1.00, 0.10, -1],
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

// Shader généré pour UNE composition. Une seule lecture de texture par couche
// (photo, stries, bandeau) : chaque pixel retient la strie / le segment qui le
// couvre le plus, au lieu de lire la photo pour chacune (coût constant).
// Les couches absentes de la composition ne sont pas générées.
function buildStripesFragment(comp) {
  const P = comp.space === 'screen' ? 'sp' : 'uv';
  const W = comp.space === 'screen' ? ' * uView.z' : ''; // largeur écran → largeur photo
  // Un aplat qui touche le bord de l'image déborde un peu : sinon l'anti-crénelage
  // laisserait une colonne de pixels à demi couverte sur le bord.
  const ext = (x0, x1) => [x0 <= 0 ? -0.01 : x0, x1 >= 1 ? 1.01 : x1];

  const stripes = (comp.stripes || []).map(([x0, x1, delay, dir, behind]) => {
    const cx = (x0 + x1) / 2;
    const hw = (x1 - x0) / 2;
    // 'wipe' : pleine largeur, se déploie du haut ou du bas
    // 'open' : pleine hauteur, s'ouvre depuis son axe vertical
    const shape = comp.reveal === 'open'
      ? `box(${P}.x, ${f(cx)} - ${f(hw)} * q, ${f(cx)} + ${f(hw)} * q, uAA.x)`
      : `box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * reveal(${dir > 0 ? `${P}.y` : `1.0 - ${P}.y`}, q)`;
    return `
  q = ease((uProgress - ${f(delay)}) / uStripeDuration);
  m = ${shape} * smoothstep(0.0, 0.35, q);
  pick = step(sW, m);
  sOff = mix(sOff, (1.0 - q) * 0.22 * uSlide * ${f(dir)}, pick);
  sBehind = mix(sBehind, ${behind ? '1.0' : '0.0'}, pick);
  sW = max(sW, m);`;
  }).join('');

  const band = comp.band;
  const segments = band ? band.segments.map(([s0, s1, srcX, srcDy, delay, dir]) => {
    const [x0, x1] = ext(s0, s1);
    return `
  q = ease((uProgress - ${f(delay)}) / uBandDuration);
  l = (${P}.x - ${f(x0)}) / ${f(x1 - x0)};
  m = box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * bandMask * reveal(${dir > 0 ? 'l' : '1.0 - l'}, q);
  pick = step(bW, m);
  bSrc = mix(bSrc, vec2(${f(srcX)} + (0.5 - l) * ${f(x1 - x0)}${W} + (1.0 - q) * 0.10 * uSlide * ${f(dir)}, ${f(srcDy)}), pick);
  bW = min(1.0, bW + m);`;   // segments contigus : on additionne (pas de liseré aux jonctions)
  }).join('') : '';

  // Voiles : aplats de couleur translucides, pleine hauteur, qui se déploient
  // verticalement (sens 1 : depuis le haut de l'écran, -1 : depuis le bas).
  const veils = comp.veils ? comp.veils.items.map(([v0, v1, delay, dir]) => {
    const [x0, x1] = ext(v0, v1);
    return `
  q = ease((uProgress - ${f(delay)}) / uVeilDuration);
  m = box(${P}.x, ${f(x0)}, ${f(x1)}, uAA.x) * reveal(${dir > 0 ? 'sp.y' : '1.0 - sp.y'}, q) * smoothstep(0.0, 0.35, q);
  vW = max(vW, m);`;
  }).join('') : '';

  return `
#ifdef GL_FRAGMENT_PRECISION_HIGH
precision highp float;
#else
precision mediump float;
#endif
varying vec2 vUv;
uniform sampler2D uImage;
uniform sampler2D uMask;
uniform vec2  uTexSize;     // taille de la photo en pixels
uniform float uBicubic;     // 1 = filtrage bicubique (photo agrandie)
uniform vec4  uView;        // zone visible de l'écran en UV photo : x0, y0, largeur, hauteur
uniform float uProgress;
uniform float uStripeAlpha;
uniform float uBandAlpha;
uniform float uStripeDuration;
uniform float uBandDuration;
uniform float uSlide;       // amplitude des glissements (0 = mouvement réduit)
uniform float uVignette;
uniform vec3  uTint;
uniform float uTintMix;
uniform vec3  uVeilColor;
uniform float uVeilAlpha;
uniform float uVeilDuration;
uniform vec2  uAA;          // ~1 px dans l'espace de la composition (anti-crénelage)

// Catmull-Rom en 9 lectures bilinéaires : bien plus net que le bilinéaire
// quand la photo est agrandie (cas d'une source 992 px en plein écran).
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

float ease(float t) {       // easeInOutCubic
  t = clamp(t, 0.0, 1.0);
  return t < 0.5 ? 4.0 * t * t * t : 1.0 - pow(-2.0 * t + 2.0, 3.0) / 2.0;
}

float box(float v, float a, float b, float aa) {
  return smoothstep(a - aa, a + aa, v) * (1.0 - smoothstep(b - aa, b + aa, v));
}

// volet : 0 → rien, 1 → couvert, bord doux qui avance le long de t
float reveal(float t, float q) {
  float soft = 0.06;
  float edge = q * (1.0 + soft);
  return 1.0 - smoothstep(edge - soft, edge, t);
}

void main() {
  vec2 uv  = vUv;
  vec2 sp  = (uv - uView.xy) / uView.zw;   // position à l'écran (0→1)
  vec3 col = tex(uv);
  float q; float m; float l; float pick;

  // 1. stries verticales : photo pivotée de 180°, teinte éventuelle
  ${stripes ? `float sW = 0.0; float sOff = 0.0; float sBehind = 0.0;
  ${stripes}
  if (sW > 0.0) {   // la photo pivotée n'est lue que là où une strie couvre
    vec3 sc = mix(tex(vec2(1.0 - uv.x, 1.0 - uv.y + sOff)), uTint, uTintMix);
    float sCover = sW * uStripeAlpha * (1.0 - sBehind * texture2D(uMask, uv).r);
    col = mix(col, sc, sCover);
  }` : ''}

  // 2. bandeau horizontal (dessus, opaque)
  ${band ? `float bandMask = box(${P}.y, ${f(band.y0)}, ${f(band.y1)}, uAA.y);
  float bW = 0.0; vec2 bSrc = vec2(0.0);
  ${segments}
  if (bW > 0.0) col = mix(col, tex(vec2(bSrc.x, uv.y + bSrc.y)), bW * uBandAlpha);` : ''}

  // 3. voiles translucides
  ${veils ? `float vW = 0.0;
  ${veils}
  col = mix(col, uVeilColor, vW * uVeilAlpha);` : ''}

  // 4. fondu des bords vers le noir de la section
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
    this.y        = 0;
    this.dirty    = true;
    this.ready    = false;
    this.shaders  = {};
    if (this.img && !this.img.complete) this.img.addEventListener('load', () => this.layout(), { once: true });

    this.params = {
      bandAlpha:       1.0,   // opacité du bandeau horizontal (maquette : 100 %)
      stripeDuration:  0.55,  // part de la progression pour déployer une strie
      bandDuration:    0.45,  // idem pour un segment du bandeau
      veilDuration:    0.6,   // idem pour un voile
      slide:           motion.reduced ? 0 : 1, // glissements internes (0 si mouvement réduit)
      // Ralenti (ordinateur) : au plus fort, la photo absorbe `linger` de la
      // vitesse de scroll (0,6 → elle défile à 40 %), sur ±`lingerSpan` hauteurs
      // d'écran autour du moment où elle remplit l'écran. 0 = scroll normal.
      linger:          motion.linger ? 0.6 : 0,
      lingerSpan:      0.8,
      // Fenêtre de construction de l'effet, en hauteurs d'écran autour de ce moment
      progressBefore:  0.55,
      progressAfter:   motion.linger ? 0.25 : 0.05,
      progressMinSpan: 0.4,
      // Inertie de l'effet (s). 0 = l'effet est une fonction exacte de la position
      // du scroll, recalculée à chaque frame : il suit le scroll sans aucun retard,
      // vers le bas comme vers le haut (demande client du 16/10).
      smoothing:       0,
      maxPixels:       9e6,   // budget du canvas en pixels physiques (Retina 16 pouces à pleine densité)
      portraitBelow:   0.9,   // ratio largeur/hauteur sous lequel on passe en composition portrait
    };
  }

  // Géométrie du scroll — appelée par main.js pour chaque hero, DANS L'ORDRE
  // de la page (la position de chaque section dépend des hauteurs au-dessus).
  measure() {
    const vh    = this.frame.offsetHeight || window.innerHeight;
    if (!vh) return;                                         // fenêtre de hauteur nulle : on attend
    const top   = this.section.getBoundingClientRect().top + window.scrollY;
    const after = Math.max(0, document.documentElement.scrollHeight - (top + this.section.offsetHeight));
    const P     = this.params;
    const h     = P.lingerSpan * vh;
    const L     = P.linger > 0 ? (P.linger * h) / 0.75 : 0; // pente max de smoothstep = 1,5
    const sMin  = -top;                                      // s au tout début de la page
    // Point d'arrêt c : quand la photo remplit l'écran.
    //  - hero en haut de page : ralenti maximal dès le chargement (pas de départ sec) ;
    //  - hero en bas de page : le ralenti s'achève pile en fin de page, photo plein cadre ;
    //  - sinon : ralenti complet, centré sur le moment où la photo remplit l'écran.
    const atTop    = top < 1;
    const atBottom = !atTop && after < 0.5 * vh;
    let c, y0, extra;
    if (atTop) {
      c = sMin; y0 = L / 2; extra = L / 2;
    } else if (atBottom) {
      extra = L / 2; c = extra + after; y0 = L * smooth01((sMin - c + h) / (2 * h));
    } else {
      c = L / 2; y0 = L * smooth01((sMin - c + h) / (2 * h)); extra = Math.max(0, L - y0);
    }
    // Fenêtre de construction de l'effet, toujours atteignable avant la fin de page
    const sEnd = vh + extra + after - window.innerHeight;
    let p1 = Math.max(c + P.progressAfter * vh, Math.max(sMin, c - P.progressBefore * vh) + P.progressMinSpan * vh);
    p1 = Math.min(p1, sEnd);
    const p0 = Math.min(Math.max(sMin, c - P.progressBefore * vh), p1 - 0.2 * vh);
    this.geo = { vh, top, h, L, c, y0, extra, p0, p1 };
    this.section.style.setProperty('--hero-extra', `${Math.round(extra)}px`);
  }

  async init() {
    const p = this.preset;
    if (!p) throw new Error(`preset inconnu « ${this.section.dataset.preset} »`);
    this.app = new PIXI.Application({
      view:            this.canvas,
      width:           1,
      height:          1,
      autoStart:       false,  // pas de boucle continue : rendu à la demande
      antialias:       false,  // inutile (bords anti-crénelés dans le shader), coûteux sur mobile
      backgroundColor: 0x000000,
      resolution:      1,
      autoDensity:     true,
    });
    // Pixi pose touch-action:none sur son canvas (gestion des pointeurs) :
    // sur mobile, un glissé qui commence sur la photo ne défilait plus la page.
    // Le canvas est décoratif : on retire les écouteurs de Pixi.
    this.app.renderer.events?.setTargetElement?.(null);
    this.canvas.style.touchAction = 'pan-y';

    // Contexte WebGL perdu (iOS en arrière-plan, mémoire GPU) : on montre la
    // photo seule ; une fois le contexte restauré par Pixi, on redessine l'effet.
    this.canvas.addEventListener('webglcontextlost', () => this.section.classList.remove('is-ready'));
    this.canvas.addEventListener('webglcontextrestored', () => {
      requestAnimationFrame(() => {
        if (!this.ready) return;
        this.app.render();
        this.dirty = false;
        this.section.classList.add('is-ready');
      });
    });

    // Même fichier que l'<img> de la section : déjà téléchargé et décodé
    let texture;
    if (this.img) {
      await this.img.decode().catch(() => {});
      if (this.img.naturalWidth) texture = PIXI.Texture.from(this.img);
    }
    if (!texture) texture = await PIXI.Assets.load(this.section.dataset.image);
    const mask = p.mask ? await PIXI.Assets.load(p.mask) : PIXI.Texture.WHITE;

    // Pas de mipmaps : la photo est toujours agrandie, et le shader lit les
    // textures dans des branches (les mipmaps y donneraient des bords flous).
    texture.baseTexture.mipmap = PIXI.MIPMAP_MODES.OFF;
    this.texture = texture;
    this.mask    = mask;
    // taille réelle en pixels (une photo nommée « …@2x » aurait sinon une taille divisée par 2)
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
      // GPU sans précision haute en fragment (vieux Android) : le bicubique y serait
      // faux → on garde la photo seule plutôt qu'un rendu dégradé.
      const r = this.app.renderer, gl = r.gl;
      const hp = gl.getShaderPrecisionFormat(gl.FRAGMENT_SHADER, gl.HIGH_FLOAT);
      if (!hp || hp.precision < 23) throw new Error('précision highp indisponible en fragment');
      // Préchauffage : compiler le shader et envoyer les textures MAINTENANT, même
      // hors écran — sinon ce coût (jusqu'à plusieurs centaines de ms) tombe en
      // plein scroll, au moment précis où la photo arrive à l'écran.
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
    const comp = this.preset[key];
    const veils = comp.veils || { color: [255, 255, 255], alpha: 0 };
    const shader = PIXI.Shader.from(STRIPES_VERTEX, buildStripesFragment(comp), {
      uImage:          this.texture,
      uMask:           this.mask,
      uTexSize:        new Float32Array([this.texSize.w, this.texSize.h]),
      uBicubic:        1,
      uView:           new Float32Array([0, 0, 1, 1]),
      uProgress:       this.progress,
      uStripeAlpha:    comp.stripeAlpha ?? 1,
      uBandAlpha:      this.params.bandAlpha,
      uStripeDuration: this.params.stripeDuration,
      uBandDuration:   this.params.bandDuration,
      uVeilDuration:   this.params.veilDuration,
      uSlide:          this.params.slide,
      uVignette:       comp.vignette ?? 0,
      uTint:           new Float32Array((comp.tint || [0, 0, 0]).map((c) => c / 255)),
      uTintMix:        comp.tintMix ?? 0,
      uVeilColor:      new Float32Array(veils.color.map((c) => c / 255)),
      uVeilAlpha:      veils.alpha,
      uAA:             new Float32Array([0.001, 0.001]),
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

  // Taille du canvas, composition (paysage / portrait), cadrage cover.
  layout() {
    const w = this.frame.clientWidth, h = this.frame.clientHeight;
    if (!w || !h) return;

    // L'<img> reçoit le même cadrage que le canvas, avant même que WebGL soit
    // prêt (sinon elle sauterait pendant le fondu d'apparition du canvas).
    if (this.img && this.img.naturalWidth) {
      const c = this.cover(w, h, this.img.naturalWidth, this.img.naturalHeight);
      this.img.style.objectPosition = `${c.x}px ${c.y}px`;
      // bac à sable : la maquette du DA (même format) reçoit le même cadrage
      const ref = this.frame.querySelector('[data-hero-ref]');
      if (ref) ref.style.objectPosition = this.img.style.objectPosition;
    }
    if (!this.ready) return;

    const key = this.pickComposition();
    if (key !== this.compKey) {
      this.compKey = key;
      this.shader = this.makeShader(key);
      this.mesh.shader = this.shader;
    }

    // Pleine densité de l'écran (jusqu'à 3x) dans la limite du budget de pixels
    const dpr = window.devicePixelRatio || 1;
    const res = Math.max(1, Math.min(dpr, 3, Math.sqrt(this.params.maxPixels / (w * h))));
    const r = this.app.renderer;
    let resized = false;
    if (!this.view || this.view.w !== w || this.view.h !== h || this.view.res !== res) {
      r.resolution = res;
      r.resize(w, h);   // vide le canvas : on redessine aussitôt (voir fin de layout)
      this.view = { w, h, res };
      resized = true;
    }

    const { mw, mh, x, y } = this.cover(w, h, this.texSize.w, this.texSize.h);
    this.mesh.scale.set(mw, mh);
    this.mesh.position.set(x, y);

    const u = this.shader.uniforms;
    u.uView[0] = -x / mw; u.uView[1] = -y / mh; u.uView[2] = w / mw; u.uView[3] = h / mh;
    const screen = this.preset[this.compKey].space === 'screen';
    // bords anti-crénelés sur ~1,5 pixel physique (bords francs comme la maquette)
    u.uAA[0] = 0.75 / ((screen ? w : mw) * res);
    u.uAA[1] = 0.75 / ((screen ? h : mh) * res);
    u.uBicubic = mw * res > this.texSize.w ? 1 : 0;   // photo agrandie → bicubique
    u.uProgress = this.progress;
    this.dirty = true;
    if (resized) { this.app.render(); this.dirty = false; }   // pas d'image noire au redimensionnement
  }

  setProgress(p) {
    this.progress = p;
    if (this.shader) this.shader.uniforms.uProgress = p;
    this.section.style.setProperty('--hero-progress', p.toFixed(3));
    this.dirty = true;
  }

  // Appelée à chaque frame par main.js (après Lenis), avec la position de scroll.
  update(scrollY, dt) {
    const g = this.geo;
    if (!g) return;
    const s = scrollY - g.top;

    // 1. Ralenti progressif de la photo (ordinateur uniquement)
    if (g.L > 0) {
      let y = g.L * smooth01((s - g.c + g.h) / (2 * g.h)) - g.y0;
      y = Math.min(g.extra, Math.max(0, y));
      const dpr = window.devicePixelRatio || 1;
      y = Math.round(y * dpr) / dpr;            // jamais de position sous-pixel (netteté)
      if (y !== this.y || !this.frame.style.transform) {
        this.y = y;
        this.frame.style.transform = `translate3d(0, ${y}px, 0)`;
      }
    } else if (this.y) {
      this.y = 0;
      this.frame.style.transform = '';
    }

    // 2. Progression de l'effet (inertie optionnelle, désactivée par défaut)
    const target = clamp01((s - g.p0) / (g.p1 - g.p0));
    const k = this.params.smoothing > 0 ? 1 - Math.exp(-dt / this.params.smoothing) : 1;
    let p = this.progress + (target - this.progress) * k;
    if (Math.abs(target - p) < 1e-4) p = target;
    if (p !== this.progress) this.setProgress(p);

    // 3. Rendu seulement si quelque chose a changé ET que la photo est visible
    if (this.ready && this.dirty) {
      const top = g.top + this.y - scrollY;
      if (top < window.innerHeight && top + g.vh > 0) {
        this.app.render();
        this.dirty = false;
      }
    }
  }
}

window.StripesHero = StripesHero;
