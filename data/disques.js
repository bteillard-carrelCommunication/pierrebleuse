/* Disques : quatre fiches sur la page d'accueil, du plus récent au plus ancien.
 *
 *   titre       *…* = italique
 *   descriptif  interprètes, distinctions : chaîne ou { fr, en }, ou liste de lignes
 *   mot         le mot de Pierre Bleuse sur le disque ({ fr, en }) ; vide : rien ne s'affiche
 *   annee, maison
 *   commander   liens d'achat : [{ nom, url }]
 *   streaming   liens d'écoute : [{ nom, url }]
 *   lien        page du disque (maison de disques) : lien « En savoir plus » ; vide : pas de lien
 * Un champ vide ou absent ne s'affiche pas. Source : docs/site/CONTENU-SITE.md. */
window.DISQUES = [
  {
    id: 'ravel-abroad', titre: '*Ravel Abroad*', annee: 2026, maison: 'OUR Recordings',
    descriptif: { fr: 'Orchestre symphonique d\'Odense', en: 'Odense Symphony Orchestra' },
    mot: '',
    commander: [{ nom: 'OUR Recordings', url: 'https://www.ourrecordings.com/albums/ravel-abroad' }],
    streaming: []
  },
  {
    id: 'unsuk-chin', titre: '*Unsuk Chin*', annee: 2026, maison: 'Alpha Classics',
    descriptif: 'Ensemble intercontemporain',
    mot: '', commander: [], streaming: []
  },
  {
    id: 'viola-concertos', titre: '*Viola Concertos*', annee: 2026, maison: 'Dacapo',
    descriptif: { fr: 'Rafaell Altino, Orchestre symphonique d\'Odense', en: 'Rafaell Altino, Odense Symphony Orchestra' },
    mot: '', commander: [], streaming: []
  },
  {
    id: 'ligeti', titre: '*Ligeti*', annee: 2024, maison: 'Alpha Classics',
    descriptif: [
      'Ensemble intercontemporain',
      { fr: 'Diapason d\'or de l\'année, Choc de *Classica*', en: 'Diapason d\'or of the year, Choc de *Classica*' }
    ],
    mot: '', commander: [], streaming: []
  }
];
