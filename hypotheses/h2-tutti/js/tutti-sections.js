/*
 * Tutti — rendu des cinq sections (Pixi 7) et textes par lignes.
 *
 * Chaque section a son PIXI.Application (canvas décoratif, pointer-events:none,
 * EventSystem retiré). Les photos sont des PIXI.Mesh à shader ; les points sont
 * des sprites additifs ; une RenderTexture reçoit les « fenêtres » (ouverture)
 * ou les barres de colonnes (deuxième photo) qui servent de masque à la photo.
 * Le rendu n'a lieu que si la section est à l'écran.
 */
(function () {
  'use strict';
  const T = window.Tutti;
  const { CFG, TOUCH, REDUCED, clamp01, lerp, smooth01, easeG } = T;

  /* ---------- textures générées (partagées entre les renderers) ---------- */
  function radialTexture(size, fn) {
    const c = document.createElement('canvas');
    c.width = c.height = size;
    const ctx = c.getContext('2d');
    const img = ctx.createImageData(size, size);
    const d = img.data, half = size / 2;
    for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
      const u = (x + 0.5 - half) / half, v = (y + 0.5 - half) / half;
      const a = clamp01(fn(u, v));
      const k = (y * size + x) * 4;
      d[k] = d[k + 1] = d[k + 2] = 255; d[k + 3] = Math.round(a * 255);
    }
    ctx.putImageData(img, 0, 0);
    const tex = PIXI.Texture.from(c);
    tex.baseTexture.mipmap = PIXI.MIPMAP_MODES.ON;
    return tex;
  }
  const TEX = {};
  function textures() {
    if (TEX.dot) return TEX;
    // point : cœur net, halo doux (le rayon « visible » ≈ 1/4 de la largeur du sprite)
    TEX.dot = radialTexture(64, (u, v) => { const r = Math.hypot(u, v); return Math.pow(Math.max(0, 1 - r), 2.6) + 0.9 * Math.exp(-r * r * 22); });
    // fenêtre : gaussienne large (bord diffus, règle 4)
    TEX.win = radialTexture(128, (u, v) => { const r = Math.hypot(u, v); return Math.exp(-r * r * 4.2) - Math.exp(-4.2); });
    // halo d'ambiance : très doux
    TEX.halo = radialTexture(256, (u, v) => { const r = Math.hypot(u, v); return Math.exp(-r * r * 3.0) - Math.exp(-3.0); });
    // barre de colonne (photo 2) : « niveau de lumière » d'une colonne à une hauteur donnée.
    // Profil horizontal PLAT (la largeur et la plume de la colonne sont calculées dans le
    // shader, pas dans cette texture : aucune capsule), extrémités verticales en S.
    // Dessinées en ADD à hauteur constante : les barres des points posés se complètent et
    // saturent à 1 → la colonne « se remplit » par segments, puis devient une colonne droite.
    TEX.bar = radialTexture(128, (u, v) => {
      const hx = Math.abs(u), hy = Math.abs(v);
      const px = 1 - smooth01(clamp01((hx - 0.8) / 0.2)); // plat, fondu seulement aux bords de la cellule
      const py = smooth01(clamp01((1 - hy) / 0.5));        // plateau au centre, rampe en S aux extrémités
      return px * py;
    });
    return TEX;
  }

  /* ---------- shaders ---------- */
  const VERT = `
precision highp float;
attribute vec2 aVertexPosition; attribute vec2 aUvs;
uniform mat3 translationMatrix; uniform mat3 projectionMatrix;
varying vec2 vUv;
void main() { vUv = aUvs; gl_Position = vec4((projectionMatrix * translationMatrix * vec3(aVertexPosition, 1.0)).xy, 0.0, 1.0); }`;

  const HEAD = `
#ifdef GL_FRAGMENT_PRECISION_HIGH
precision highp float;
#else
precision mediump float;
#endif
varying vec2 vUv;
uniform sampler2D uImage; uniform sampler2D uMask; uniform sampler2D uWin;
uniform vec2 uTexSize; uniform float uBicubic; uniform vec4 uView; uniform vec2 uAA;
uniform float uReveal; uniform float uSlide;
vec3 tex(vec2 p) {
  p = clamp(p, 0.0, 1.0);
  if (uBicubic < 0.5) return texture2D(uImage, p).rgb;
  vec2 pos = p * uTexSize; vec2 t1 = floor(pos - 0.5) + 0.5; vec2 fr = pos - t1;
  vec2 w0 = fr * (-0.5 + fr * (1.0 - 0.5 * fr)); vec2 w1 = 1.0 + fr * fr * (-2.5 + 1.5 * fr);
  vec2 w2 = fr * (0.5 + fr * (2.0 - 1.5 * fr)); vec2 w3 = fr * fr * (-0.5 + 0.5 * fr); vec2 w12 = w1 + w2;
  vec2 p0 = (t1 - 1.0) / uTexSize; vec2 p12 = (t1 + w2 / w12) / uTexSize; vec2 p3 = (t1 + 2.0) / uTexSize;
  vec3 c = (texture2D(uImage, vec2(p0.x, p0.y)).rgb * w0.x + texture2D(uImage, vec2(p12.x, p0.y)).rgb * w12.x + texture2D(uImage, vec2(p3.x, p0.y)).rgb * w3.x) * w0.y
         + (texture2D(uImage, vec2(p0.x, p12.y)).rgb * w0.x + texture2D(uImage, vec2(p12.x, p12.y)).rgb * w12.x + texture2D(uImage, vec2(p3.x, p12.y)).rgb * w3.x) * w12.y
         + (texture2D(uImage, vec2(p0.x, p3.y)).rgb * w0.x + texture2D(uImage, vec2(p12.x, p3.y)).rgb * w12.x + texture2D(uImage, vec2(p3.x, p3.y)).rgb * w3.x) * w3.y;
  return clamp(c, 0.0, 1.0);
}
float box(float v, float a, float b, float aa) { return smoothstep(a - aa, a + aa, v) * (1.0 - smoothstep(b - aa, b + aa, v)); }
float lum(vec3 c) { return dot(c, vec3(0.299, 0.587, 0.114)); }
`;

  // 1. Ouverture : fenêtres (RenderTexture) → sujet entier → fond, puis 4 colonnes
  const FRAG_OUVERTURE = HEAD + `
uniform float uSubject; uniform float uBg; uniform float uCut; uniform float uWinGain;
uniform vec3 uLightCol; uniform vec4 uCol[4]; uniform float uColSpace; uniform float uColAlpha; uniform float uColBehind;
void main() {
  vec2 uv = vUv; vec2 sp = (uv - uView.xy) / uView.zw;
  // détourage adouci : moyenne de 5 prélèvements (bord d'environ 8 px, règle 4)
  vec2 o1 = vec2(3.0, 3.0) / uTexSize, o2 = vec2(3.0, -3.0) / uTexSize;
  float m = (texture2D(uMask, uv).r + texture2D(uMask, uv + o1).r + texture2D(uMask, uv - o1).r
           + texture2D(uMask, uv + o2).r + texture2D(uMask, uv - o2).r) / 5.0;
  float subj = smoothstep(0.08, 0.92, m);
  vec2 puv = uv + vec2(0.0, (1.0 - uSubject) * 0.02 * uSlide);       // rubato interne (règle 5)
  vec3 photo = tex(puv);
  float win = clamp(texture2D(uWin, sp).r * uWinGain, 0.0, 1.0);
  vec3 light = uLightCol * (0.22 + 1.15 * lum(photo));                  // lumière avant découpe (règle 7)
  float cut = max(uCut, uSubject);
  vec3 content = mix(light, photo, cut);
  float vis = max(win, uSubject) * subj;
  vec3 col = content * vis;
  col += tex(uv) * uBg * (1.0 - subj);                                   // le fond monte doucement
  float x = mix(uv.x, sp.x, uColSpace);
  float aa = mix(uAA.x, uAA.x / uView.z, uColSpace);
  for (int k = 0; k < 4; k++) {
    float q = uCol[k].z;
    if (q <= 0.0) continue;
    float cx = uCol[k].x, hw = uCol[k].y * (0.3 + 0.7 * q);
    float feather = mix(0.05, 0.0, q) + aa;                               // bords vivants (règle 4)
    float c = box(x, cx - hw, cx + hw, feather);
    vec3 rot = tex(vec2(1.0 - uv.x, 1.0 - uv.y + (1.0 - q) * 0.03 * uSlide * uCol[k].w));
    vec3 rotLight = uLightCol * (0.18 + 0.95 * lum(rot));
    vec3 cc = mix(rotLight, rot, smoothstep(0.1, 0.8, q));
    col = mix(col, cc, c * uColAlpha * smoothstep(0.0, 0.4, q) * (1.0 - uColBehind * subj));
  }
  gl_FragColor = vec4(col, 1.0);
}`;

  // 2. Deuxième photo : colonnes remplies par les points (RenderTexture = niveau de lumière
  //    par colonne et par hauteur), largeur et plume de chaque colonne calculées ici
  //    (uCols : centre, demi-largeur, finesse q, plume maximale), photo 180° teintée lavande,
  //    chef devant. Lumière avant découpe (règle 7) et plume large → 1-2 px (règle 4), par colonne.
  const FRAG_ORCHESTRE = HEAD + `
uniform float uSettle; uniform vec3 uTint; uniform float uTintMix;
uniform vec4 uCols[14]; uniform float uNCols; uniform float uColSpace;
void main() {
  vec2 uv = vUv; vec2 sp = (uv - uView.xy) / uView.zw;
  float chef = smoothstep(0.25, 0.75, texture2D(uMask, uv).r);
  vec3 photo = tex(uv + vec2(0.0, (1.0 - uReveal) * 0.015 * uSlide));
  vec3 warm = vec3(lum(photo)) * vec3(1.0, 0.95, 0.88);                   // la photo arrive par la lumière
  vec3 base = mix(warm, photo, smoothstep(0.35, 1.0, uReveal)) * smoothstep(0.0, 0.75, uReveal);
  float fill = smoothstep(0.0, 1.0, texture2D(uWin, sp).r);              // remplissage de la colonne à cette hauteur
  vec3 rot = tex(vec2(1.0 - uv.x, 1.0 - uv.y + (1.0 - uSettle) * 0.03 * uSlide));
  vec3 strip = mix(rot, uTint, uTintMix);
  vec3 stripLight = mix(uTint, vec3(1.0), 0.45) * (0.25 + 0.95 * lum(rot));
  float x = mix(uv.x, sp.x, uColSpace);
  float aa = mix(uAA.x, uAA.x / uView.z, uColSpace);
  vec3 col = base;
  for (int k = 0; k < 14; k++) {
    if (float(k) >= uNCols) break;
    vec4 c = uCols[k];
    float q = c.z;
    if (q <= 0.0 || fill <= 0.0) continue;
    float hw = c.y * mix(0.7, 1.0, q);                                    // la colonne s'élargit un peu en se posant
    float feather = c.w * (1.0 - q) + aa;                                 // plume large, puis 1-2 px (règle 4)
    float lat = box(x, c.x - hw, c.x + hw, feather);
    vec3 sc = mix(stripLight, strip, smoothstep(0.3, 0.95, q));           // lumière, puis découpe (règle 7)
    col = mix(col, sc, lat * fill * (1.0 - chef));
  }
  gl_FragColor = vec4(col, 1.0);
}`;

  // 3. Pied de page : photo N&B, deux voiles de lumière (gauche depuis le haut, droite depuis le bas)
  const FRAG_PIED = HEAD + `
uniform vec4 uVeil[2]; uniform float uVeilSpace; uniform vec3 uVeilCol; uniform float uVeilAlpha;
void main() {
  vec2 uv = vUv; vec2 sp = (uv - uView.xy) / uView.zw;
  vec3 photo = tex(uv + vec2(0.0, (1.0 - uReveal) * 0.015 * uSlide));
  vec3 base = photo * smoothstep(0.0, 0.8, uReveal);
  base = mix(base * vec3(1.0, 0.93, 0.84), base, smoothstep(0.3, 1.0, uReveal));
  float x = mix(uv.x, sp.x, uVeilSpace);
  float vW = 0.0;
  for (int k = 0; k < 2; k++) {
    float q = uVeil[k].z;
    if (q <= 0.0) continue;
    float feather = mix(0.10, 0.012, q);
    float c = box(x, uVeil[k].x, uVeil[k].y, feather);
    float ty = uVeil[k].w > 0.0 ? sp.y : 1.0 - sp.y;
    float soft = 0.4, edge = q * (1.0 + soft);
    float r = 1.0 - smoothstep(edge - soft, edge, ty);
    vW = max(vW, c * r * smoothstep(0.0, 0.5, q));
  }
  gl_FragColor = vec4(mix(base, uVeilCol, vW * uVeilAlpha), 1.0);
}`;

  const loadImage = (src) => new Promise((res, rej) => {
    const im = new Image(); im.onload = () => res(im); im.onerror = () => rej(new Error('image introuvable : ' + src)); im.src = src;
  });

  /* ---------- section (base) ---------- */
  class Section {
    constructor(el, N) {
      this.el = el; this.id = el.dataset.tutti;
      this.canvas = el.querySelector('[data-canvas]');
      this.frame  = el.querySelector('[data-frame]');
      this.img    = el.querySelector('[data-img]');
      this.isPhoto = !!this.frame;
      this.N = N;
      this.ready = false; this.failed = false;
      this.top = 0; this.height = 0; this.w = 0; this.h = 0;
      this.y = 0;                              // translation du cadre (ralenti)
      this.L = 0; this.extra = 0; this.hSpan = 0;
      this.focus = { ouverture: [0.55, 0.45], orchestre: [0.61, 0.40], pied: [0.645, 0.45] }[this.id] || [0.5, 0.5];
      this.sizeMul = new Float32Array(N).fill(1);
      this.alphaMul = new Float32Array(N).fill(1);
    }

    pageTop(s) { return this.top + this.lingerY(s); }
    lingerY(s) {
      if (!this.L) return 0;
      // ouverture (haut de page) : ralenti maximal dès le chargement, sans départ sec
      const y = this.L * smooth01((s + this.hSpan) / (2 * this.hSpan)) - this.L / 2;
      return Math.min(this.extra, Math.max(0, y));
    }
    isVisible(s, vh) { const t = this.pageTop(s); return t < s + vh && t + this.h > s; }

    measure(vh) {
      this.top = this.el.getBoundingClientRect().top + window.scrollY;
      if (this.isPhoto && this.id === 'ouverture' && CFG.linger > 0) {
        this.hSpan = CFG.lingerSpan * vh;
        this.L = (CFG.linger * this.hSpan) / 0.75;
        this.extra = this.L / 2;
      } else { this.hSpan = 0; this.L = 0; this.extra = 0; }
      this.el.style.setProperty('--extra', `${Math.round(this.extra)}px`);
      this.height = this.el.offsetHeight;
      this.frameH = this.isPhoto ? this.frame.clientHeight : this.height;
    }

    cover(w, h) {
      const tw = this.texSize.w, th = this.texSize.h, ratio = tw / th;
      let mw = w, mh = w / ratio;
      if (mh < h) { mh = h; mw = h * ratio; }
      let [fx, fy] = this.focus;
      // Portrait (téléphone) : l'ouverture est agrandie de 25 % et cadrée sur le visage, qui
      // descend à mi-hauteur ; la révélation (course courte en tactile) se joue dans le cadre.
      if (this.id === 'ouverture' && w / h < 0.9) { mw *= 1.25; mh *= 1.25; fy = 0.40; }
      const clampTo = (v, min) => Math.min(0, Math.max(min, v));
      return { mw, mh, x: clampTo(w / 2 - fx * mw, w - mw), y: clampTo(h / 2 - fy * mh, h - mh) };
    }
    uvToLocal(u, v) { const c = this.cov; return { x: c.x + u * c.mw, y: c.y + v * c.mh }; }
    portrait() { return this.w / this.h < 0.9; }

    async init() {
      if (!PIXI.utils.isWebGLSupported()) throw new Error('WebGL indisponible');
      const host = this.isPhoto ? this.frame : this.el;
      this.app = new PIXI.Application({
        view: this.canvas, width: 2, height: 2, autoStart: false, antialias: false,
        backgroundAlpha: this.isPhoto ? 1 : 0, backgroundColor: 0x000000,
        resolution: 1, autoDensity: true,
      });
      this.app.renderer.events?.setTargetElement?.(null);   // canvas décoratif : le doigt défile la page
      this.canvas.style.touchAction = 'pan-y';
      this.canvas.addEventListener('webglcontextlost', () => this.el.classList.add('is-fallback'));
      this.canvas.addEventListener('webglcontextrestored', () => { this.el.classList.remove('is-fallback'); this.dirty = true; });

      const tx = textures();
      if (this.isPhoto) {
        const im = this.img.complete && this.img.naturalWidth ? this.img : await loadImage(this.img.src);
        await (im.decode ? im.decode().catch(() => {}) : Promise.resolve());
        this.imgEl = im;
        const texture = PIXI.Texture.from(im);
        texture.baseTexture.mipmap = PIXI.MIPMAP_MODES.OFF;
        this.texture = texture;
        this.texSize = { w: texture.baseTexture.realWidth, h: texture.baseTexture.realHeight };
        if (this.el.dataset.mask) {
          this.maskImg = await loadImage(this.el.dataset.mask);
          this.maskTex = PIXI.Texture.from(this.maskImg);
          this.maskPx  = T.readPixels(this.maskImg);
        } else this.maskTex = PIXI.Texture.WHITE;
        if (this.id === 'pied') this.photoPx = T.readPixels(im);
        this.winRT = PIXI.RenderTexture.create({ width: 2, height: 2, resolution: 1 });
        this.winLayer = new PIXI.Container();
        this.winSprites = [];
        const winTex = this.id === 'orchestre' ? tx.bar : tx.win;
        for (let i = 0; i < this.N; i++) {
          const sp = new PIXI.Sprite(winTex); sp.anchor.set(0.5); sp.visible = false;
          sp.blendMode = PIXI.BLEND_MODES.ADD;
          this.winLayer.addChild(sp); this.winSprites.push(sp);
        }
        const frag = { ouverture: FRAG_OUVERTURE, orchestre: FRAG_ORCHESTRE, pied: FRAG_PIED }[this.id];
        const uniforms = {
          uImage: this.texture, uMask: this.maskTex, uWin: this.winRT,
          uTexSize: new Float32Array([this.texSize.w, this.texSize.h]), uBicubic: 1,
          uView: new Float32Array([0, 0, 1, 1]), uAA: new Float32Array([0.001, 0.001]),
          uReveal: 1, uSlide: REDUCED ? 0 : 1,
          // ouverture
          uSubject: 0, uBg: 0, uCut: 0, uWinGain: 1, uLightCol: new Float32Array([0.96, 0.9, 0.78]),
          uCol: new Float32Array(16), uColSpace: 0, uColAlpha: 0.5, uColBehind: 0,
          // orchestre
          uSettle: 0, uTint: new Float32Array([90 / 255, 99 / 255, 142 / 255]), uTintMix: 0.5,
          uCols: new Float32Array(4 * 14), uNCols: 0,
          // pied
          uVeil: new Float32Array(8), uVeilSpace: 0, uVeilCol: new Float32Array([0xF3 / 255, 0xEA / 255, 0xD8 / 255]), uVeilAlpha: 0.45,
        };
        const geometry = new PIXI.Geometry()
          .addAttribute('aVertexPosition', [0, 0, 1, 0, 1, 1, 0, 1], 2)
          .addAttribute('aUvs', [0, 0, 1, 0, 1, 1, 0, 1], 2)
          .addIndex([0, 1, 2, 0, 2, 3]);
        this.shader = PIXI.Shader.from(VERT, frag, uniforms);
        this.mesh = new PIXI.Mesh(geometry, this.shader);
        this.app.stage.addChild(this.mesh);
      }
      // halo d'ambiance (ADD, très faible, respire en temps réel)
      this.halo = new PIXI.Sprite(tx.halo); this.halo.anchor.set(0.5); this.halo.blendMode = PIXI.BLEND_MODES.ADD;
      this.halo.alpha = 0; this.app.stage.addChild(this.halo);
      // points
      this.dots = new PIXI.Container();
      this.sprites = [];
      for (let i = 0; i < this.N; i++) {
        const sp = new PIXI.Sprite(tx.dot); sp.anchor.set(0.5); sp.blendMode = PIXI.BLEND_MODES.ADD;
        this.dots.addChild(sp); this.sprites.push(sp);
      }
      this.app.stage.addChild(this.dots);
      this.ready = true;
      this.layout();
      this.app.render();          // préchauffage : shader compilé, textures envoyées
      const gl = this.app.renderer.gl;
      if (this.shader) {
        const glp = this.shader.program.glPrograms[this.app.renderer.CONTEXT_UID];
        if (!glp || !gl.getProgramParameter(glp.program, gl.LINK_STATUS)) throw new Error('shader non compilé');
      }
      this.el.classList.add('is-ready');
    }

    layout() {
      const host = this.isPhoto ? this.frame : this.el;
      const w = host.clientWidth, h = host.clientHeight;
      if (!w || !h) return;
      this.w = w; this.h = h;
      if (this.isPhoto && this.texSize) {
        const c = this.cover(w, h); this.cov = c;
        if (this.img) {   // repli : exactement le même cadrage que le canvas (y compris l'agrandissement portrait)
          const st = this.img.style;
          st.left = `${c.x}px`; st.top = `${c.y}px`; st.width = `${c.mw}px`; st.height = `${c.mh}px`;
          st.right = 'auto'; st.bottom = 'auto'; st.objectFit = 'fill';
        }
      }
      if (!this.ready) return;
      const dpr = window.devicePixelRatio || 1;
      const res = Math.max(1, Math.min(dpr, 3, Math.sqrt(CFG.maxPixels / (w * h))));
      const r = this.app.renderer;
      if (!this.view || this.view.w !== w || this.view.h !== h || this.view.res !== res) {
        r.resolution = res; r.resize(w, h);
        this.view = { w, h, res };
        if (this.winRT) { this.winRT.baseTexture.setResolution(Math.max(0.5, res * 0.5)); this.winRT.resize(w, h, true); }
      }
      if (this.isPhoto) {
        const c = this.cov;
        this.mesh.scale.set(c.mw, c.mh); this.mesh.position.set(c.x, c.y);
        const u = this.shader.uniforms;
        u.uView[0] = -c.x / c.mw; u.uView[1] = -c.y / c.mh; u.uView[2] = w / c.mw; u.uView[3] = h / c.mh;
        u.uAA[0] = 0.75 / (c.mw * res); u.uAA[1] = 0.75 / (c.mh * res);
        u.uBicubic = c.mw * res > this.texSize.w ? 1 : 0;
        // préchauffage de la RenderTexture (allocation GPU faite ici, pas au premier scroll)
        if (this.winRT) this.app.renderer.render(this.winLayer, { renderTexture: this.winRT, clear: true });
      }
      this.dirty = true;
    }

    // Points : positions page → locales ; taille, teinte, scintillation (temps réel)
    drawDots(P, t, offY, live) {
      const sc = CFG.scint, texW = 64;
      for (let i = 0; i < this.N; i++) {
        const sp = this.sprites[i];
        const x = P.pos[2 * i], y = P.pos[2 * i + 1] - offY;
        const a = this.alphaMul[i];
        if (a <= 0.001 || x < -40 || x > this.w + 40 || y < -40 || y > this.h + 40) { sp.visible = false; continue; }
        sp.visible = true;
        sp.position.set(x, y);
        const r = P.radius[i] * this.sizeMul[i];
        sp.scale.set((r * 5.7) / texW);
        const tw = live ? 1 + sc.amp * Math.sin(t * P.scW[i] + P.scPh[i]) : 1;
        sp.alpha = CFG.alpha * a * tw;
        sp.tint = P.color[i];
      }
    }

    render() { if (this.ready && !this.failed) this.app.render(); }
  }

  /* ---------- textes par lignes (règle 12) ---------- */
  class Lines {
    constructor(el) {
      this.el = el;
      this.orig = el.innerHTML;
      this.lines = [];
      this.last = [];
      this.progress = null;   // fonction s → 0..1 (posée par main.js)
      this.split();
    }
    split() {
      const el = this.el;
      el.innerHTML = this.orig;
      // 1. mots (blancs ASCII seulement : l'espace insécable reste collée à son mot)
      const words = [];
      const walk = (node, em) => {
        for (const ch of Array.from(node.childNodes)) {
          if (ch.nodeType === 3) {
            // une ponctuation qui suit directement un élément (« <em>Red Fox</em>, ») reste collée au mot précédent
            const glue = words.length > 0 && !/^[ \t\n\r]/.test(ch.textContent);
            ch.textContent.split(/([ \t\n\r]+)/).forEach((tok, idx) => {
              if (!tok.trim()) return;
              if (glue && idx === 0 && /^[,.;:!?»)\]…]/.test(tok)) words[words.length - 1].text += tok;
              else words.push({ text: tok, em });
            });
          } else if (ch.nodeType === 1) walk(ch, em || ch.tagName === 'EM' || ch.tagName === 'I');
        }
      };
      walk(el, false);
      el.innerHTML = words.map((w, i) => `<span data-w="${i}">${w.text}</span>`).join(' ');
      // 2. lignes par position
      const spans = Array.from(el.querySelectorAll('[data-w]'));
      const groups = [];
      let lastTop = null;
      spans.forEach((sp, i) => {
        const top = sp.offsetTop;
        if (lastTop === null || Math.abs(top - lastTop) > 2) { groups.push([]); lastTop = top; }
        groups[groups.length - 1].push(words[i]);
      });
      // 3. reconstruction : une ligne = un bloc
      el.innerHTML = groups.map((g) => {
        let html = '', open = false;
        g.forEach((w, i) => {
          if (w.em && !open) { html += '<em>'; open = true; }
          if (!w.em && open) { html += '</em>'; open = false; }
          html += (i ? ' ' : '') + w.text;
        });
        if (open) html += '</em>';
        return `<span class="ln">${html}</span>`;
      }).join(' ');   // l'espace entre deux blocs ne se rend pas mais reste dans textContent (lecture d'écran, copie)
      this.lines = Array.from(el.querySelectorAll('.ln'));
      this.last = this.lines.map(() => -1);
      this.lines.forEach((ln) => { ln.style.opacity = '0'; });
    }
    update(s) {
      const p = this.progress ? this.progress(s) : 1;
      const n = this.lines.length, L = 0.5, step = n > 1 ? (1 - L) / (n - 1) : 0;
      for (let j = 0; j < n; j++) {
        const lp = clamp01((p - j * step) / L);
        const e = REDUCED ? smooth01(lp) : easeG(lp);
        const k = Math.round(e * 200);
        if (k === this.last[j]) continue;
        this.last[j] = k;
        const ln = this.lines[j], st = ln.style;
        if (k >= 200) { st.opacity = '1'; st.transform = ''; st.filter = ''; continue; }
        st.opacity = (e * 1.0).toFixed(3);
        st.transform = REDUCED ? '' : `translate3d(0, ${((1 - e) * 10).toFixed(2)}px, 0)`;
        st.filter = REDUCED ? '' : `blur(${((1 - e) * 2).toFixed(2)}px)`;
      }
    }
  }

  window.TuttiSections = { Section, Lines, textures, loadImage };
})();
