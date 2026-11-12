/* PRESSE · FAUX ARTICLES, POUR LA MAQUETTE SEULEMENT (12/11/2026).
 *
 * Huit articles inventés de toutes pièces (médias, auteurs, titres, citations, résumés, logos)
 * pour montrer le rendu final, chaque champ rempli. Aucun n'existe. Les liens mènent à
 * example.com (domaine réservé aux exemples).
 *
 * Chargé après data/presse.js, ce fichier remplace la liste : les vrais articles restent dans
 * window.PRESSE_REELLE. Pour revenir aux vrais articles : retirer la balise <script> de ce
 * fichier dans index.html et presse.html (et supprimer assets/presse/exemples/).
 * À RETIRER AVANT LA MISE EN LIGNE. */
window.PRESSE_REELLE = window.PRESSE;
window.PRESSE = [
  {
    id: 'exemple-contrepoint', exemple: true,
    media: 'Contrepoint', logo: 'assets/presse/exemples/contrepoint.svg',
    titre: { fr: 'Pierre Bleuse, la clarté comme manifeste', en: 'Pierre Bleuse: clarity as a manifesto' },
    auteur: 'Claire Duvernay', date: '2026-10-08',
    verbatim: '« Sous sa baguette, la partition la plus dense devient lisible comme une page d’écriture. »', langue: 'fr',
    resume: {
      fr: 'Rencontre avec le directeur musical de l’Ensemble intercontemporain, entre deux répétitions, sur la transmission du répertoire d’aujourd’hui.',
      en: 'An interview with the music director of the Ensemble intercontemporain, between two rehearsals, on passing on today’s repertoire.'
    },
    lien: 'https://example.com/contrepoint/pierre-bleuse'
  },
  {
    id: 'exemple-the-score', exemple: true,
    media: 'The Score Weekly', logo: 'assets/presse/exemples/the-score.svg',
    titre: { fr: 'Critique du disque *Ligeti*', en: 'Review of the *Ligeti* album' },
    auteur: 'Hannah Whitcombe', date: '2026-09-19',
    verbatim: '“Precise without ever being cold, this is Ligeti with a pulse.”', langue: 'en',
    resume: {
      fr: 'Le critique salue une lecture des concertos à la fois rigoureuse et chantante, servie par une prise de son d’une grande transparence.',
      en: 'The reviewer praises a reading of the concertos both rigorous and lyrical, served by a recording of great transparency.'
    },
    lien: 'https://example.com/the-score/ligeti-review'
  },
  {
    id: 'exemple-klangbild', exemple: true,
    media: 'Klangbild', logo: 'assets/presse/exemples/klangbild.svg',
    titre: { fr: 'Un chef français à Stuttgart', en: 'A French conductor in Stuttgart' },
    auteur: 'Stefan Kranich', date: '2026-07-02',
    verbatim: '„Ein Dirigent, der zuhört, bevor er gestaltet.“', langue: 'de',
    resume: {
      fr: 'Compte rendu d’un concert avec le SWR Symphonieorchester, où la *Symphonie fantastique* retrouve sa part de rêve et d’inquiétude.',
      en: 'A concert review with the SWR Symphonieorchester, in which the *Symphonie fantastique* regains its share of dream and unease.'
    },
    lien: 'https://example.com/klangbild/stuttgart'
  },
  {
    id: 'exemple-echo-lyrique', exemple: true,
    media: 'L’Écho lyrique', logo: 'assets/presse/exemples/l-echo-lyrique.svg',
    titre: { fr: 'Prades, un festival qui respire', en: 'Prades, a festival that breathes' },
    auteur: 'Julien Morel', date: '2026-08-14',
    verbatim: '« Le Festival Pablo Casals a trouvé en lui un hôte autant qu’un directeur. »', langue: 'fr',
    resume: {
      fr: 'Retour sur l’édition 2026 du Festival Pablo Casals, sa programmation entre musique de chambre et créations, et l’abbaye Saint-Michel-de-Cuxa comme écrin.',
      en: 'A look back at the 2026 Pablo Casals Festival, its programme between chamber music and premieres, set in the abbey of Saint-Michel-de-Cuxa.'
    },
    lien: 'https://example.com/echo-lyrique/prades-2026'
  },
  {
    id: 'exemple-mesures', exemple: true,
    media: 'Mesures', logo: 'assets/presse/exemples/mesures.svg',
    titre: { fr: '*Répons*, l’espace pour instrument', en: '*Répons*, with space as an instrument' },
    auteur: 'Margot Lefèvre', date: '2026-05-27',
    verbatim: '« Une architecture sonore qui tourne autour de l’auditeur, et le tient en haleine. »', langue: 'fr',
    resume: {
      fr: 'À la Philharmonie de Paris, l’Ensemble intercontemporain redonne l’œuvre emblématique de Pierre Boulez dans un dispositif spatialisé.',
      en: 'At the Philharmonie de Paris, the Ensemble intercontemporain revives Pierre Boulez’s emblematic work in a spatialised set-up.'
    },
    lien: 'https://example.com/mesures/repons'
  },
  {
    id: 'exemple-podium', exemple: true,
    media: 'Podium Review', logo: 'assets/presse/exemples/podium-review.svg',
    titre: { fr: 'Une soirée Unsuk Chin éblouissante', en: 'A dazzling Unsuk Chin evening' },
    auteur: 'Daniel Ashworth', date: '2026-03-11',
    verbatim: '“Every colour in its place, every gesture with a purpose.”', langue: 'en',
    resume: {
      fr: 'Le concert de sortie du disque consacré à Unsuk Chin, entre virtuosité des solistes et cohésion de l’ensemble.',
      en: 'The launch concert of the Unsuk Chin album, between the soloists’ virtuosity and the ensemble’s cohesion.'
    },
    lien: 'https://example.com/podium/unsuk-chin'
  },
  {
    id: 'exemple-gazette', exemple: true,
    media: 'La Gazette des salles', logo: 'assets/presse/exemples/la-gazette-des-salles.svg',
    titre: { fr: 'Ravel, d’un continent à l’autre', en: 'Ravel, from one continent to another' },
    auteur: 'Antoine Valcourt', date: '2026-01-23',
    verbatim: '« Un Ravel voyageur, d’une élégance qui n’exclut jamais le risque. »', langue: 'fr',
    resume: {
      fr: 'Le disque *Ravel Abroad* éclaire les échos de l’Amérique et de l’Espagne dans l’œuvre du compositeur.',
      en: 'The *Ravel Abroad* album sheds light on the echoes of America and Spain in the composer’s work.'
    },
    lien: 'https://example.com/gazette/ravel-abroad'
  },
  {
    id: 'exemple-nordisk', exemple: true,
    media: 'Nordisk Musik', logo: 'assets/presse/exemples/nordisk-musik.svg',
    titre: { fr: 'Cinq saisons à Odense', en: 'Five seasons in Odense' },
    auteur: 'Ida Halvorsen', date: '2025-12-05',
    verbatim: '“He leaves an orchestra that plays with one breath.”', langue: 'en',
    resume: {
      fr: 'Bilan du mandat de chef principal à l’Orchestre symphonique d’Odense, des grandes symphonies aux commandes passées aux compositeurs danois.',
      en: 'An assessment of his tenure as Chief Conductor of the Odense Symphony Orchestra, from the great symphonies to commissions from Danish composers.'
    },
    lien: 'https://example.com/nordisk/odense'
  }
];
