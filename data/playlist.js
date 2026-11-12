/* Musiques de fond du site : la liste du lecteur (ouverture et barre du haut).
 *
 *   id       identifiant court
 *   src      fichier audio (AAC .m4a)
 *   piste    analyse de la bande-son que suit la nuée, produite par :
 *              python3 outils/analyse-bande-son.py assets/media/<fichier>.m4a data/<fichier>.json
 *   titre    libellé de la liste, en chaîne ou { fr, en } ; *…* = italique
 *   mention  seconde ligne, plus discrète (interprètes, source, droits)
 *
 * Le premier titre est celui de l'ouverture (lecture automatique). À la fin d'un titre, le
 * suivant s'enchaîne, puis la liste reprend au début. Ajouter un titre : déposer le fichier,
 * lancer l'analyse, ajouter une entrée ici. */
window.PLAYLIST = [
  {
    id: 'pathetique',
    src: 'assets/media/pathetique-iv-extrait.m4a',
    piste: 'data/pathetique-iv-extrait.json',
    titre: { fr: 'Tchaïkovski, *Pathétique*, IV', en: 'Tchaikovsky, *Pathétique*, IV' },
    mention: { fr: 'Extrait, domaine public', en: 'Excerpt, public domain' }
  },
  {
    id: 'berlioz',
    src: 'assets/media/berlioz-extrait.m4a',
    piste: 'data/berlioz-extrait.json',
    titre: 'Berlioz, *Symphonie fantastique*',
    mention: 'Pierre Bleuse, SWR Symphonieorchester · ARD Klassik'
  },
  {
    id: 'boulez',
    src: 'assets/media/boulez-extrait.m4a',
    piste: 'data/boulez-extrait.json',
    titre: 'The Centenary of Pierre Boulez',
    mention: 'Ensemble intercontemporain & Friends · ARTE Concert'
  }
];
