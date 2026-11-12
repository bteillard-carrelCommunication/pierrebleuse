/*
 * choreo.js — ce qui bouge en dehors du shader, toujours en fonction de la
 * position de scroll (règle 11) :
 *  - textes par lignes (règle 12) : flou 2 px → net, montée 10 px, retards par
 *    ligne (≈ 0,09 écran, soit 80 à 120 ms d'équivalent-scroll), courbe phrase() ;
 *  - agenda-partition : la portée se trace, les temps forts entrent en pupitres
 *    (lumière = halo, puis la note, puis le texte par lignes) ;
 *  - le bloc de l'ouverture entre une seule fois au chargement (CSS), pas au scroll ;
 *  - la nav s'efface devant un texte qui passe dessous (--nav) et se pose sur un
 *    voile sombre une fois la page entamée (--nav-veil) : deux fonctions de la
 *    position, sans chronomètre, aller = retour.
 *
 * Un bloc = [data-reveal] ; chaque élément [data-lines] dedans est découpé en
 * lignes (<span class="line"><span class="line__in">…</span></span>). Les lignes
 * d'un bloc sont numérotées à la suite : le retard court d'un paragraphe à l'autre.
 */
(function () {
  // Sans le moteur (script en panne), une courbe de secours : les textes entrent quand même
  const phrase  = window.phrase || ((t) => { t = Math.min(1, Math.max(0, t)); return t * t * (3 - 2 * t); });
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const clamp01 = (t) => Math.min(1, Math.max(0, t));

  // ---- réglages (hauteurs d'écran, sauf mention) ----
  const T = {
    stagger:    0.09,  // entre deux lignes
    duration:   0.30,  // flou → net pour une ligne
    maxSpan:    1.0,   // un bloc n'occupe jamais plus d'un écran de scroll
    startAt:    0.88,  // le bloc commence quand son haut atteint 88 % de l'écran
    rise:       10,    // px (règle 12 : 8 à 12 px)
    blur:       2,     // px
    staffStart: 0.85,  // la portée commence à se tracer quand son haut atteint 85 % de l'écran
    staffSpan:  1.0,   // … et se trace sur un écran de scroll
    noteDot:    0.22,  // durée d'entrée d'une note (point)
    noteHalo:   0.16,  // durée de montée du halo (lumière avant la note)
    noteText:   0.06,  // retard du texte après la note
  };

  // ---- nav : s'efface devant les textes qui passent dessous ----
  const NAV = {
    zones:    '.hero__text, .rest__a, .rest__b, .quote, .agenda__head, .staff',
    margin:   20,     // px de garde autour du logo et de FR | EN
    fade:     48,     // px d'approche sur lesquels la nav s'efface (puis revient, symétrique)
    veilFrom: 0.05,   // écrans : le voile sombre derrière la nav apparaît entre 5 %…
    veilSpan: 0.15,   // … et 20 % d'écran de scroll (jamais sur l'ouverture au repos)
  };

  // Les balises inline sont redistribuées mot à mot pour survivre à la découpe
  function prepare(html) {
    return html.replace(/<(em|i|strong|b)>([\s\S]*?)<\/\1>/g, (_, tag, inner) =>
      inner.trim().split(/\s+/).map((w) => `<${tag}>${w}</${tag}>`).join(' '));
  }

  // Découpe un élément en lignes (mesure réelle des retours à la ligne)
  function splitLines(el) {
    if (!el.dataset.text) el.dataset.text = prepare(el.innerHTML.trim().replace(/[ \t\r\n]+/g, ' '));
    const words = el.dataset.text.split(' ');
    el.innerHTML = words.map((w) => `<span class="w">${w}</span>`).join(' ');
    const lines = []; let top = null;
    for (const s of el.querySelectorAll('.w')) {
      const t = s.offsetTop;
      if (top === null || Math.abs(t - top) > 2) { lines.push([]); top = t; }
      lines[lines.length - 1].push(s.innerHTML);
    }
    el.innerHTML = lines.map((ws) => `<span class="line"><span class="line__in">${ws.join(' ')}</span></span>`).join('');
    return [...el.querySelectorAll('.line__in')];
  }

  const blocks = [];
  let staff = null;
  let lastWidth = 0;
  const nav = { el: document.querySelector('.nav'), parts: [], zones: [], last: null, lastVeil: null };

  function collect() {
    blocks.length = 0;
    document.querySelectorAll('[data-reveal]').forEach((el) => {
      const mode  = el.dataset.reveal || 'scroll';
      const parts = el.hasAttribute('data-lines') ? [el] : [...el.querySelectorAll('[data-lines]')];
      const lines = parts.flatMap(splitLines);
      lines.forEach((ln, i) => ln.parentElement.style.setProperty('--i', i));
      el.classList.add('reveal');
      if (mode === 'load') {
        el.classList.add('reveal--load');
        // Polices arrivées après le rideau : les lignes recréées restent posées (pas de seconde entrée)
        if (document.body.classList.contains('is-loaded')) lines.forEach((ln) => ln.classList.add('is-set'));
      }
      const heroEl = el.closest('[data-hero]');
      const natural = (lines.length - 1) * T.stagger + T.duration;
      const b = {
        el, mode, lines, natural, heroEl,
        // le hero est résolu à chaque measure() : collect() peut tourner avant que
        // main.js ait créé les heros (document.fonts.ready déjà résolu → micro-tâche)
        hero:  heroEl ? heroEl.__hero || null : null,
        scale: Math.min(1, T.maxSpan / natural),
        start: 0, last: null,
      };
      if (mode === 'note') {
        b.note = { delay: parseFloat(el.dataset.delay) || 0, dot: el.querySelector('.note__dot'), halo: el.querySelector('.note__halo'), stem: el.querySelector('.note__stem'), lastKey: null };
      }
      blocks.push(b);
    });
    const staffEl = document.querySelector('[data-staff]');
    staff = staffEl ? { el: staffEl, lines: [...staffEl.querySelectorAll('.staff__line')], start: 0, span: 1, last: null } : null;
  }

  function measure() {
    const vh = window.innerHeight;
    for (const b of blocks) {
      if (b.heroEl && !b.hero) b.hero = b.heroEl.__hero || null;
      if (b.mode === 'scroll' || (b.mode === 'hero' && !(b.hero && b.hero.geo))) {
        // (un texte de photo sans moteur entre comme un bloc ordinaire, à sa position)
        const top = b.el.getBoundingClientRect().top + window.scrollY;
        b.start = top - T.startAt * vh;
      } else if (b.mode === 'hero') {
        // le bloc tient dans la fenêtre de texte du hero (t0 → t1), retards resserrés si besoin
        const W = (b.hero.geo.t1 - b.hero.geo.t0) / vh;
        b.scale = Math.max(0.4, Math.min(1, W / b.natural));
      }
    }
    if (staff) {
      const top = staff.el.getBoundingClientRect().top + window.scrollY;
      staff.start = top - T.staffStart * vh;
      staff.span  = T.staffSpan * vh;
      for (const b of blocks) if (b.mode === 'note') b.start = staff.start + b.note.delay * staff.span;
    }
    measureNav();
  }

  // Boîtes de la nav (fixe : coordonnées écran constantes) et des zones de texte
  // (position dans le document, la translation du cadre d'un hero retirée)
  function measureNav() {
    if (!nav.el) return;
    nav.parts = [...nav.el.children].map((p) => {
      const r = p.getBoundingClientRect();
      return { el: p, x0: r.left - 12, x1: r.right + 12, y0: r.top, y1: r.bottom };
    });
    nav.zones = [...document.querySelectorAll(NAV.zones)].map((el) => {
      const r = el.getBoundingClientRect();
      const heroEl = el.closest('[data-hero]');
      const hero = heroEl ? heroEl.__hero : null;
      return { x0: r.left, x1: r.right, docTop: r.top + window.scrollY - (hero ? hero.y : 0), h: r.height, hero, block: blocks.find((b) => b.el === el) || null };
    });
  }

  // Redécoupe seulement si la largeur a changé (les retours à la ligne aussi)
  function relayout() {
    const w = document.documentElement.clientWidth;
    if (w !== lastWidth) { lastWidth = w; collect(); }
    measure();
  }

  function setLine(ln, q) {
    if (q >= 1) {
      if (!ln.classList.contains('is-set')) { ln.classList.add('is-set'); ln.style.cssText = ''; }
      return;
    }
    ln.classList.remove('is-set');
    if (q <= 0) { if (ln.style.cssText) ln.style.cssText = ''; return; }
    if (reduced) { ln.style.cssText = `opacity:${q.toFixed(3)}`; return; }
    ln.style.cssText = `opacity:${q.toFixed(3)};transform:translate3d(0,${(T.rise * (1 - q)).toFixed(2)}px,0);filter:blur(${(T.blur * (1 - q)).toFixed(2)}px)`;
  }

  // x : avancée du bloc en hauteurs d'écran depuis son début
  function applyBlock(b, x) {
    const key = Math.round(x * 400);
    if (key === b.last) return;
    b.last = key;
    const st = T.stagger * b.scale, d = T.duration * b.scale;
    for (let i = 0; i < b.lines.length; i++) setLine(b.lines[i], phrase((x - i * st) / d));
  }

  // Chaque partie de la nav (logo, FR | EN) s'efface séparément, sur 48 px
  // d'approche, quand un texte visible arrive dessous, et revient dès qu'il est
  // passé : fonction de la position. --nav (le minimum) pilote le voile sombre.
  function updateNav(scrollY, vh) {
    if (!nav.el || !nav.parts.length) return;
    const o = nav.parts.map(() => 1);
    for (const z of nav.zones) {
      // un texte de photo pas encore entré est invisible : il ne compte pas
      if (z.block && z.block.mode === 'hero' && z.hero && z.hero.textProgress <= 0) continue;
      const top = z.docTop + (z.hero ? z.hero.y : 0) - scrollY, bottom = top + z.h;
      nav.parts.forEach((p, i) => {
        if (z.x1 < p.x0 || z.x0 > p.x1) return;                                // pas l'un sous l'autre
        const d = Math.max(top - (p.y1 + NAV.margin), (p.y0 - NAV.margin) - bottom); // distance (< 0 : recouvrement)
        o[i] = Math.min(o[i], clamp01(d / NAV.fade));
      });
    }
    const keys = o.map((v) => Math.round(v * 200));
    const sig = keys.join(',');
    if (sig !== nav.last) {
      nav.last = sig;
      nav.parts.forEach((p, i) => { p.el.style.opacity = keys[i] === 200 ? '' : (keys[i] / 200).toFixed(3); });
      document.documentElement.style.setProperty('--nav', (Math.min(...keys) / 200).toFixed(3));
    }
    const veil = clamp01((scrollY - NAV.veilFrom * vh) / (NAV.veilSpan * vh));
    const vk = Math.round(veil * 100);
    if (vk !== nav.lastVeil) { nav.lastVeil = vk; document.documentElement.style.setProperty('--nav-veil', (vk / 100).toFixed(2)); }
  }

  function update(scrollY) {
    const vh = window.innerHeight;
    for (const b of blocks) {
      if (b.mode === 'load') continue;
      let x;
      if (b.mode === 'hero' && b.hero && b.hero.geo) {
        const h = b.hero;
        x = (scrollY - h.geo.top - h.geo.t0) / vh;
      } else if (b.mode === 'note') {
        if (!staff) continue;
        const xn = (scrollY - b.start) / vh;
        const qD = phrase(xn / T.noteDot);
        const qH = phrase(xn / T.noteHalo) * (1 - qD);
        const key = Math.round(xn * 400);
        if (key !== b.note.lastKey) {
          b.note.lastKey = key;
          if (b.note.dot)  b.note.dot.style.cssText  = `opacity:${qD.toFixed(3)};transform:scale(${(0.6 + 0.4 * qD).toFixed(3)})`;
          if (b.note.halo) b.note.halo.style.cssText = `opacity:${qH.toFixed(3)}`;
          if (b.note.stem) b.note.stem.style.cssText = `transform:scaleY(${qD.toFixed(3)})`;   // la hampe pousse depuis la note
        }
        x = xn - T.noteText;
      } else {
        x = (scrollY - b.start) / vh;
      }
      applyBlock(b, x);
    }
    if (staff) {
      const p = clamp01((scrollY - staff.start) / staff.span);
      const drawn = phrase(p);
      const key = Math.round(drawn * 1000);
      if (key !== staff.last) {
        staff.last = key;
        const off = (1 - drawn).toFixed(4);
        for (const l of staff.lines) l.style.strokeDashoffset = off;
      }
    }
    updateNav(scrollY, vh);
  }

  // Les polices web changent les retours à la ligne : on redécoupe quand elles arrivent
  const invalidate = () => { lastWidth = 0; relayout(); };
  if (document.fonts) {
    document.fonts.ready.then(invalidate);
    document.fonts.addEventListener('loadingdone', invalidate);
  }

  window.Choreo = { relayout, measure, update, invalidate, splitLines, T, NAV, blocks, nav };
})();
