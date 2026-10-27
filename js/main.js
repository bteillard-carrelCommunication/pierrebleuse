/*
 * main.js — boot : scroll inertiel Lenis + ticker GSAP (une seule boucle),
 * puis un StripesHero par <section data-hero>.
 */

(function () {
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const touch   = matchMedia('(hover: none), (pointer: coarse)').matches;

  // 1. Scroll inertiel à la molette / au trackpad. Pas de Lenis sur écran tactile :
  //    il n'y lisse rien et y poserait des écouteurs tactiles non passifs.
  //    Si son CDN est indisponible, la page reste en défilement natif.
  const lenis = (!touch && window.Lenis) ? new Lenis({
    duration:    1.2,
    smoothWheel: !reduced,
    easing:      (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
  }) : null;

  // 2. Lenis piloté par le ticker GSAP. ScrollTrigger reste synchronisé pour
  //    les futures animations du site (les heroes n'en dépendent plus).
  gsap.registerPlugin(ScrollTrigger);
  ScrollTrigger.config({ ignoreMobileResize: true });
  if (lenis) {
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
  }
  gsap.ticker.lagSmoothing(0);

  // 3. ?photo=1 (bac à sable) : revoir la photo de l'itération précédente
  const PHOTOS = {
    1: { image: 'assets/bleuse-1.jpg', preset: 'bleuse-1', ref: 'assets/bleuse-2.jpg' },
    3: { image: 'assets/bleuse-3.jpg', preset: 'bleuse-3', ref: 'assets/bleuse-4.jpg' },
  };
  const photo = PHOTOS[new URLSearchParams(location.search).get('photo')];
  const first = document.querySelector('[data-hero]');
  if (photo && first && first.querySelector('[data-hero-ref]')) {
    first.dataset.image  = photo.image;
    first.dataset.preset = photo.preset;
    first.querySelector('[data-hero-img]').src = photo.image;
    first.querySelector('[data-hero-ref]').src = photo.ref;
  }

  // 4. Un hero par section. Ralenti de la photo seulement à la souris / au
  //    trackpad : en tactile, le défilement natif n'est jamais contrarié.
  const motion = { linger: !touch && !reduced, reduced };
  const heroes = window.heroes = [...document.querySelectorAll('[data-hero]')]
    .map((section) => new StripesHero(section, motion));

  // Géométrie dans l'ordre de la page : la position de chaque section dépend
  // de la hauteur des sections au-dessus (dont la marge de ralenti des heroes).
  // Un hero en erreur (preset inconnu…) ne doit pas bloquer les autres
  const safe = (fn) => { try { fn(); } catch (err) { console.warn('[StripesHero]', err); } };
  function relayout() {
    heroes.forEach((h) => safe(() => h.measure()));
    heroes.forEach((h) => safe(() => h.layout()));
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

  heroes.forEach((h) => {
    h.init().then(schedule, (err) => {
      // Pas de WebGL (ou échec) : la photo seule reste affichée par l'<img>
      console.warn('[StripesHero] effet désactivé, photo seule :', err);
      h.section.classList.add('is-fallback');
      if (h.app) { try { h.app.destroy(false); } catch (e) { /* déjà détruit */ } h.app = null; }
    });
  });

  // 5. Une frame = Lenis, puis chaque hero lit la position et se met à jour
  // Fenêtre glissée vers un écran d'une autre densité (1x ↔ Retina) : aucun
  // événement resize, on surveille donc devicePixelRatio (lecture gratuite).
  let lastDpr = window.devicePixelRatio;
  gsap.ticker.add((time, deltaTime) => {
    if (window.devicePixelRatio !== lastDpr) { lastDpr = window.devicePixelRatio; schedule(); }
    const y  = window.scrollY;
    const dt = Math.min(deltaTime, 100) / 1000;
    for (const h of heroes) h.update(y, dt);
  });

  // 6. Touche R (bac à sable) : superpose la maquette du DA pour comparer
  document.addEventListener('keydown', (e) => {
    if (e.key === 'r' || e.key === 'R') document.body.classList.toggle('show-ref');
  });
})();
