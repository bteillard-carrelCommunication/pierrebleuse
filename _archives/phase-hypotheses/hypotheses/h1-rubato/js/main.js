/*
 * main.js — boot : Lenis (molette / trackpad seulement) + ticker GSAP (une seule
 * boucle), un StripesHero « Rubato » par <section data-hero>, puis la
 * chorégraphie des textes et de l'agenda. Tout lit window.scrollY à chaque
 * frame : l'état visuel est une fonction de la position, sans inertie ajoutée.
 *
 * Robustesse : si une bibliothèque manque (CDN en panne), la page reste lisible —
 * sans GSAP la boucle passe par requestAnimationFrame, sans Pixi les photos
 * restent seules (is-fallback), sans moteur les textes entrent comme des blocs
 * ordinaires. Le rideau (is-loaded) se lève dans tous les cas ; et si ce script
 * lui-même échoue, index.html pose `js-timeout` après 4 s (tout devient visible).
 */
(function () {
  const root    = document.documentElement;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const touch   = matchMedia('(hover: none), (pointer: coarse)').matches;
  root.classList.add(touch ? 'is-touch' : 'is-pointer');

  const hasGsap = typeof window.gsap !== 'undefined';
  const hasPixi = typeof window.PIXI !== 'undefined';
  const Hero    = window.StripesHero || null;
  const Choreo  = window.Choreo || { relayout() {}, update() {} };
  if (!hasGsap || !hasPixi || !Hero || !window.Choreo) {
    console.warn('[main] mode dégradé — bibliothèques présentes :', JSON.stringify({ gsap: hasGsap, pixi: hasPixi, moteur: !!Hero, choreo: !!window.Choreo }));
  }

  // Une seule boucle : le ticker GSAP, ou requestAnimationFrame s'il manque (temps en secondes)
  const ticker = hasGsap
    ? { add: (fn) => gsap.ticker.add(fn) }
    : { add: (fn) => { const loop = (t) => { try { fn(t / 1000); } catch (err) { console.warn('[main]', err); } requestAnimationFrame(loop); }; requestAnimationFrame(loop); } };
  if (hasGsap) gsap.ticker.lagSmoothing(0);

  // 1. Scroll inertiel à la molette. Jamais sur écran tactile (défilement natif pur).
  let lenis = null;
  if (!touch && window.Lenis) {
    try {
      lenis = new Lenis({
        duration:    1.2,
        smoothWheel: !reduced,
        easing:      (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      });
    } catch (err) { console.warn('[main] Lenis indisponible, scroll natif :', err); }
  }
  window.lenis = lenis;
  if (lenis) ticker.add((time) => lenis.raf(time * 1000));

  // 2. Un hero par section (sans moteur : photo seule, textes en blocs ordinaires)
  const motion = { linger: !touch && !reduced, reduced, touch };
  const heroes = window.heroes = [];
  for (const section of document.querySelectorAll('[data-hero]')) {
    if (!Hero) { section.classList.add('is-fallback'); continue; }
    try { heroes.push(new Hero(section, motion)); } catch (err) { console.warn('[StripesHero]', err); section.classList.add('is-fallback'); }
  }

  const safe = (fn) => { try { fn(); } catch (err) { console.warn('[main]', err); } };
  function relayout() {
    heroes.forEach((h) => safe(() => h.measure()));
    heroes.forEach((h) => safe(() => h.layout()));
    safe(() => Choreo.relayout());
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

  const inits = heroes.map((h) => h.init().then(schedule, (err) => {
    console.warn('[StripesHero] effet désactivé, photo seule :', err);
    h.section.classList.add('is-fallback');
    if (h.app) { try { h.app.destroy(false); } catch (e) { /* déjà détruit */ } h.app = null; }
  }));

  // 3. Le rideau se lève : logo + texte d'ouverture entrent une fois, quand les
  //    polices et la première photo sont là (au plus tard après 1,5 s).
  const ready = Promise.all([document.fonts ? document.fonts.ready : Promise.resolve(), inits[0] || Promise.resolve()]);
  const timeout = new Promise((r) => setTimeout(r, 1500));
  Promise.race([ready, timeout]).then(() => {
    schedule();
    requestAnimationFrame(() => { document.body.classList.add('is-loaded'); root.classList.remove('js-timeout'); });
  });

  // 4. Une frame = Lenis, puis chaque hero et la chorégraphie lisent la position
  let lastDpr = window.devicePixelRatio;
  ticker.add(() => {
    if (window.devicePixelRatio !== lastDpr) { lastDpr = window.devicePixelRatio; schedule(); }
    const y = window.scrollY;
    for (const h of heroes) h.update(y);
    Choreo.update(y);
  });
})();
