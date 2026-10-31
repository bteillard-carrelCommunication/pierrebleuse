/* Réglages de la nuée : source unique (noms, bornes, valeurs par défaut).
 * Trois contextes, chacun avec ses valeurs :
 *   video    : ouverture, musique en cours
 *   ambiance : ouverture sans musique
 *   scroll   : sous l'ouverture (forme discrète)
 * Le mélange entre contextes suit l'état réel (musique lancée ou non, progression p
 * du scroll) ; voir main.js. Les valeurs « tactile » réduisent la densité sur
 * téléphone, l'alpha étant compensé par le moteur. */
(function () {
  'use strict';

  var CONTEXTES = ['video', 'ambiance', 'scroll'];

  var DEFS = [
    { id: 'opacite', label: 'Opacité globale', groupe: 'Rendu',
      min: 0, max: 100, pas: 1, unite: '%',
      aide: 'Opacité CSS du calque : 0 rend l\'image intacte.',
      defaut: { video: 90, ambiance: 90, scroll: 45 } },
    { id: 'intensite', label: 'Opacité des particules', groupe: 'Rendu',
      min: 0.02, max: 1, pas: 0.01,
      aide: 'Lumière de chaque point (pic médian). Les cœurs denses saturent vers le blanc.',
      defaut: { video: 0.14, ambiance: 0.12, scroll: 0.10 } },
    { id: 'densite', label: 'Nombre de particules', groupe: 'Rendu',
      min: 2000, max: 250000, pas: 1000,
      aide: 'Points dessinés (ordinateur). Réduit et compensé automatiquement sur téléphone.',
      defaut: { video: 19000, ambiance: 110000, scroll: 35000 } },
    { id: 'taille', label: 'Taille', groupe: 'Rendu',
      min: 0.4, max: 3, pas: 0.05,
      aide: '× la tache de référence (2,9 px à 1080 px de haut).',
      defaut: { video: 0.4, ambiance: 1, scroll: 0.8 } },
    { id: 'trainees', label: 'Traînées', groupe: 'Rendu',
      min: 0, max: 1, pas: 0.01,
      aide: 'Part de points étirés en tirets selon leur vitesse.',
      defaut: { video: 1, ambiance: 0.4, scroll: 0.6 } },
    { id: 'brume', label: 'Brume', groupe: 'Rendu',
      min: 0, max: 1, pas: 0.01,
      aide: 'Brouillard ardoise au sol (surtout avec la figure « voile »).',
      defaut: { video: 1, ambiance: 0.6, scroll: 0 } },

    { id: 'vitesse', label: 'Vitesse', groupe: 'Mouvement',
      min: 0, max: 3, pas: 0.05,
      aide: 'Vitesse de base du vol (la musique s\'y ajoute).',
      defaut: { video: 0.4, ambiance: 0.6, scroll: 0.35 } },
    { id: 'amplitude', label: 'Amplitude', groupe: 'Mouvement',
      min: 0, max: 2, pas: 0.05,
      aide: 'Ampleur des déplacements et des déformations.',
      defaut: { video: 0.4, ambiance: 0.8, scroll: 0.5 } },
    { id: 'sensMusique', label: 'Sensibilité à la musique', groupe: 'Mouvement',
      min: 0, max: 2, pas: 0.05,
      aide: 'Gain des caractéristiques musicales (énergie, attaques, ruptures).',
      defaut: { video: 1.15, ambiance: 0, scroll: 0.4 } },
    { id: 'sensScroll', label: 'Sensibilité au scroll', groupe: 'Mouvement',
      min: 0, max: 2, pas: 0.05,
      aide: 'Effet de la vitesse de défilement (cisaillement léger).',
      defaut: { video: 1.75, ambiance: 0.3, scroll: 1 } },
    { id: 'errance', label: 'Errance', groupe: 'Mouvement',
      min: 0, max: 1.5, pas: 0.05,
      aide: 'La nuée voyage d\'un côté à l\'autre du cadre (cycle de 36 s) et ne fait que traverser le centre, où sont les visages. 0 : elle reste à sa place.',
      defaut: { video: 1, ambiance: 1, scroll: 0 } },

    { id: 'visage', label: 'Visage dégagé', groupe: 'Rapport au chef',
      min: 0, max: 1, pas: 0.01,
      aide: 'La nuée contourne les yeux et le visage.',
      defaut: { video: 0.91, ambiance: 0.7, scroll: 0 } },
    { id: 'derriere', label: 'Passage derrière le chef', groupe: 'Rapport au chef',
      min: 0, max: 1, pas: 0.01,
      aide: 'Les points éloignés passent derrière la silhouette (masque).',
      defaut: { video: 0.83, ambiance: 0.5, scroll: 0 } }
  ];

  // Traitement de la vidéo de fond (H4-3) : réglages globaux posés en CSS sur le média de
  // l'ouverture, sous la nuée (filtre → teinte en « couleur » → voile noir).
  var FOND = [
    { id: 'fondAssombrir', label: 'Assombrir', min: 0, max: 95, pas: 1, unite: '%',
      aide: 'Voile noir sur la vidéo : la nuée ressort d\'autant mieux (mélange « éclaircir »).', defaut: 40 },
    { id: 'fondSaturation', label: 'Saturation', min: 0, max: 150, pas: 1, unite: '%', defaut: 100 },
    { id: 'fondContraste', label: 'Contraste', min: 50, max: 150, pas: 1, unite: '%', defaut: 100 },
    { id: 'fondInversion', label: 'Négatif', min: 0, max: 100, pas: 1, unite: '%',
      aide: 'Inversion des valeurs, façon solarisations des graduations chromatiques du DA. Le fond devient clair : assombrir, sinon la nuée ne se voit plus.', defaut: 0 },
    { id: 'fondFlou', label: 'Flou', min: 0, max: 16, pas: 0.5, unite: 'px', defaut: 0 },
    { id: 'fondTeinteForce', label: 'Force de la teinte', min: 0, max: 100, pas: 1, unite: '%',
      aide: 'La teinte garde la lumière de la vidéo et lui donne sa couleur.', defaut: 0 }
  ];
  var TEINTES = {                 // couleurs des planches du DA
    bleu: { label: 'Bleu de scène', couleur: '#1747c9' },
    lavande: { label: 'Lavande', couleur: '#5a638d' },
    cyan: { label: 'Cyan', couleur: '#2fb7c2' },
    nuit: { label: 'Bleu nuit', couleur: '#0a1524' }
  };
  // Préréglages : posent les curseurs d'un coup (l'assombrissement reste à part, sauf pour le
  // négatif) ; toucher un curseur ensuite fait passer le filtre en « personnalisé ».
  var FILTRES = {
    aucun: { label: 'Aucun', v: { fondSaturation: 100, fondContraste: 100, fondInversion: 0, fondFlou: 0, fondTeinteForce: 0 } },
    nb: { label: 'Noir et blanc', v: { fondSaturation: 0, fondContraste: 115, fondInversion: 0, fondFlou: 0, fondTeinteForce: 0 } },
    scene: { label: 'Scène bleue', v: { fondSaturation: 0, fondContraste: 110, fondInversion: 0, fondFlou: 0, fondTeinteForce: 60, fondTeinte: 'bleu' } },
    lavande: { label: 'Lavande', v: { fondSaturation: 0, fondContraste: 105, fondInversion: 0, fondFlou: 0, fondTeinteForce: 70, fondTeinte: 'lavande' } },
    // le négatif éclaircit le fond : il assombrit aussi, sinon la nuée (« éclaircir ») disparaît
    negatif: { label: 'Négatif (graduation)', v: { fondSaturation: 80, fondContraste: 100, fondInversion: 100, fondFlou: 0, fondTeinteForce: 0, fondAssombrir: 75 } },
    reve: { label: 'Rêve (flou bleuté)', v: { fondSaturation: 40, fondContraste: 95, fondInversion: 0, fondFlou: 6, fondTeinteForce: 45, fondTeinte: 'nuit' } }
  };

  // Réglages globaux (non liés à un contexte).
  var GLOBAUX = {
    fond: 'image',                // 'image' | 'video' | 'youtube' ; variantes : 'noir' (H4-2)
    superposerYouTube: false,     // aperçu non conforme (test interne)
    figure: 'auto',               // 'auto' | 'traine' | 'masse' | 'voile' | 'fil'
    analyseDirecte: false,        // comparaison avec l'analyse temps réel
    masqueLecture: true,          // calque correctif du pictogramme ▶
    reference: 'aucune',          // 'aucune' | 'vol1' | 'vol2' | 'vol3'
    hud: false,
    animation: true,              // pause de l'animation (WCAG 2.2.2)
    fondFiltre: 'aucun',          // préréglage de FILTRES, ou 'perso'
    fondTeinte: 'bleu'            // clé de TEINTES
  };
  FOND.forEach(function (d) { GLOBAUX[d.id] = d.defaut; });

  // Plafonds par classe d'appareil (le moteur compense l'alpha quand il réduit N).
  var APPAREILS = {
    ordinateur: { maxPoints: 250000, dprMax: 2 },
    tactile: { maxPoints: 60000, dprMax: 1.5 }
  };

  function defauts() {
    var v = {};
    CONTEXTES.forEach(function (c) {
      v[c] = {};
      DEFS.forEach(function (d) { v[c][d.id] = d.defaut[c]; });
    });
    return v;
  }

  // Mélange pondéré de contextes : poids = { video, ambiance, scroll } (somme 1).
  function melanger(valeurs, poids) {
    var out = {};
    DEFS.forEach(function (d) {
      var s = 0;
      CONTEXTES.forEach(function (c) { s += (poids[c] || 0) * valeurs[c][d.id]; });
      out[d.id] = s;
    });
    return out;
  }

  function borner(id, x) {
    var d = DEFS.find(function (e) { return e.id === id; });
    if (!d || typeof x !== 'number' || !isFinite(x)) return d ? d.defaut.video : x;
    return Math.min(d.max, Math.max(d.min, x));
  }

  // Préréglage dont les valeurs sont exactement celles de g (sinon 'perso').
  function filtreDe(g) {
    for (var k in FILTRES) {
      var v = FILTRES[k].v, ok = true;
      for (var id in v) { if (g[id] !== v[id]) { ok = false; break; } }
      if (ok) return k;
    }
    return 'perso';
  }

  window.H4Parametres = {
    CONTEXTES: CONTEXTES, DEFS: DEFS, GLOBAUX: GLOBAUX, APPAREILS: APPAREILS,
    FOND: FOND, TEINTES: TEINTES, FILTRES: FILTRES,
    defauts: defauts, melanger: melanger, borner: borner, filtreDe: filtreDe, version: 1
  };
})();
