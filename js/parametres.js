/* Réglages de la nuée : source unique (noms, bornes, valeurs par défaut).
 *
 * Un seul mode de vol depuis le 12/11/2026 : plus de distinction entre « musique » et
 * « défilement », plus de mélange de contextes. Les valeurs par défaut sont les réglages
 * validés ce jour-là (jeu « En musique » du JSON fourni). Ce qui change d'une section à
 * l'autre n'est pas le vol mais la place de la nuée dans la profondeur de la page
 * (classes .nuee-premier-plan / .nuee-arriere-plan et .fond-N, voir css/site.css).
 *
 * « Réaction au volume » : le nombre de points, leur opacité, la dilatation du nuage et
 * l'effet de boule suivent le volume de la musique (js/nuee.js). L'opacité globale, elle,
 * ne varie jamais. Les valeurs « tactile » plafonnent le nombre de points sur téléphone,
 * l'alpha étant compensé par le moteur.
 *
 * La brume est retirée (12/11/2026) : elle vaut 0 tout le temps (FIXES), n'a plus de curseur
 * et un JSON de réglages qui en porte une autre valeur est ramené à 0. */
(function () {
  'use strict';

  var DEFS = [
    { id: 'opacite', label: 'Opacité globale', groupe: 'Rendu',
      min: 0, max: 100, pas: 1, unite: '%',
      aide: 'Opacité CSS du calque. Fixe : la musique ne la fait jamais varier.',
      defaut: 100 },
    { id: 'intensite', label: 'Opacité des particules', groupe: 'Rendu',
      min: 0.02, max: 1, pas: 0.01,
      aide: 'Lumière de chaque point au volume moyen. Les cœurs denses saturent vers le blanc.',
      defaut: 0.4 },
    { id: 'densite', label: 'Nombre de particules', groupe: 'Rendu',
      min: 2000, max: 250000, pas: 1000,
      aide: 'Points dessinés au volume moyen (ordinateur). Plafonné et compensé sur téléphone.',
      defaut: 23000 },
    { id: 'taille', label: 'Taille', groupe: 'Rendu',
      min: 0.4, max: 3, pas: 0.05,
      aide: '× la tache de référence (2,9 px à 1080 px de haut).',
      defaut: 0.55 },
    { id: 'trainees', label: 'Traînées', groupe: 'Rendu',
      min: 0, max: 1, pas: 0.01,
      aide: 'Part de points étirés en tirets selon leur vitesse.',
      defaut: 0 },

    { id: 'vitesse', label: 'Vitesse', groupe: 'Mouvement',
      min: 0, max: 3, pas: 0.05,
      aide: 'Vitesse de base du vol (la musique s\'y ajoute).',
      defaut: 1.8 },
    { id: 'amplitude', label: 'Amplitude', groupe: 'Mouvement',
      min: 0, max: 2, pas: 0.05,
      aide: 'Ampleur des déplacements et des déformations.',
      defaut: 0.6 },
    { id: 'sensMusique', label: 'Sensibilité à la musique', groupe: 'Mouvement',
      min: 0, max: 3, pas: 0.05,
      aide: 'Gain des caractéristiques musicales (énergie, attaques, ruptures).',
      defaut: 2 },
    { id: 'sensScroll', label: 'Sensibilité au défilement', groupe: 'Mouvement',
      min: 0, max: 2, pas: 0.05,
      aide: 'Cisaillement léger du nuage pendant qu\'on fait défiler la page.',
      defaut: 1.1 },
    { id: 'errance', label: 'Errance', groupe: 'Mouvement',
      min: 0, max: 1.5, pas: 0.05,
      aide: 'La nuée voyage d\'un côté à l\'autre du cadre (cycle de 36 s). 0 : elle reste à sa place.',
      defaut: 1.3 },

    { id: 'reacNombre', label: 'Nombre de particules', groupe: 'Réaction au volume',
      min: 0, max: 1.5, pas: 0.05,
      aide: 'Le nombre de points suit le volume. À 1 : un tiers des points dans le silence, trois fois plus au plus fort. 0 : nombre fixe.',
      defaut: 1 },
    { id: 'reacOpacite', label: 'Opacité des particules', groupe: 'Réaction au volume',
      min: 0, max: 1.5, pas: 0.05,
      aide: 'Chaque point s\'éteint dans le silence et s\'éclaire au plus fort (à 1 : de ×0,45 à ×2,2). 0 : opacité fixe.',
      defaut: 1 },
    { id: 'contraste', label: 'Contraction, dilatation', groupe: 'Réaction au volume',
      min: 0, max: 2, pas: 0.05,
      aide: 'Le nuage se resserre dans le silence et se dilate avec le volume (à 1 : de ×0,78 à ×1,35).',
      defaut: 1 },
    { id: 'boule', label: 'Effet de boule', groupe: 'Réaction au volume',
      min: 0, max: 1, pas: 0.05,
      aide: 'Au plus fort, les points quittent le vol pour une boule qui occupe toute la fenêtre. 0 : jamais.',
      defaut: 0.85 },
    { id: 'seuilBoule', label: 'Seuil de la boule', groupe: 'Réaction au volume',
      min: 0.3, max: 0.95, pas: 0.01,
      aide: 'Volume (0 à 1) à partir duquel la boule commence à se former.',
      defaut: 0.6 }
  ];

  // Valeurs imposées : passées au moteur, sans curseur, jamais lues dans un JSON.
  var FIXES = { brume: 0 };

  // Réglages globaux (outils de mise au point).
  var GLOBAUX = {
    figure: 'auto',               // 'auto' | 'traine' | 'masse' | 'voile' | 'fil'
    hud: false,
    animation: true,              // pause de l'animation (WCAG 2.2.2)
    aimant: true                  // aimantation des sections au défilement (js/main.js)
  };

  // Plafonds par classe d'appareil (le moteur compense l'alpha quand il réduit N).
  var APPAREILS = {
    ordinateur: { maxPoints: 250000, dprMax: 2 },
    tactile: { maxPoints: 60000, dprMax: 1.5 }
  };

  function defauts() {
    var v = {};
    DEFS.forEach(function (d) { v[d.id] = d.defaut; });
    for (var k in FIXES) v[k] = FIXES[k];
    return v;
  }

  function borner(id, x) {
    if (Object.prototype.hasOwnProperty.call(FIXES, id)) return FIXES[id];
    var d = DEFS.find(function (e) { return e.id === id; });
    if (!d || typeof x !== 'number' || !isFinite(x)) return d ? d.defaut : x;
    return Math.min(d.max, Math.max(d.min, x));
  }

  /* Valeurs lues dans un JSON de réglages. Deux formats acceptés :
     - version 2 (un seul mode) : { opacite: …, intensite: … } ;
     - version 1 (trois contextes : video, ambiance, scroll) : le jeu « video » (En musique)
       devient le mode unique, les deux autres sont ignorés. */
  function normaliser(src) {
    var v = defauts();
    if (!src || typeof src !== 'object') return v;
    if (src.video && typeof src.video === 'object') src = src.video;
    DEFS.forEach(function (d) {
      if (src[d.id] === undefined || src[d.id] === null || src[d.id] === '') return;
      var x = Number(src[d.id]);
      if (isFinite(x)) v[d.id] = borner(d.id, x);
    });
    for (var k in FIXES) v[k] = FIXES[k];
    return v;
  }

  window.H4Parametres = {
    DEFS: DEFS, FIXES: FIXES, GLOBAUX: GLOBAUX, APPAREILS: APPAREILS,
    defauts: defauts, borner: borner, normaliser: normaliser, version: 2
  };
})();
