/* Visuels de la page d'accueil : ce que l'administration du site aura à écrire.
 *
 * Une entrée par emplacement (l'attribut data-visuel de la section). Au choix :
 *   type: 'image'    src, alt (chaîne ou { fr, en }), position (point focal CSS), positionMobile
 *                    lumiere : traitement « découpes » de l'image (la photo dans la pénombre, révélée
 *                              par des faisceaux où apparaît la photo retournée), js/lumiere.js.
 *                              Nom d'un préréglage : 'bleuse-1', 'bleuse-3' (réglés sur ces photos,
 *                              avec le masque du sujet) ou 'generique' (toute autre photo).
 *                              Absent ou vide : l'image telle quelle.
 *   type: 'video'    src (MP4 H.264), affiche (image d'attente), position ; en boucle.
 *                    Muette par défaut. Avec son: true, la vidéo part quand on arrive sur la section
 *                    et prend le relais de la musique (jamais les deux sons ensemble) ; quand on
 *                    quitte la section elle s'arrête et la musique reprend (js/main.js).
 *                    piste : analyse de sa bande-son, que la nuée suit pendant la lecture
 *                            (python3 outils/analyse-bande-son.py <vidéo> data/<nom>.json).
 *   type: 'youtube'  id de la vidéo (ou url) ; chargée après accord du visiteur, jamais voilée
 *   type: 'couleur'  couleur CSS
 * et, pour tous :
 *   opacite  0 à 100, par pas de 5 : 100 = le visuel plein, 0 = on ne voit que la nuée.
 *            C'est la classe .fond-N de la section (css/site.css). Un lecteur YouTube reste à 100.
 *   nuee     'arriere-plan' (par défaut) : le visuel passe devant la nuée ;
 *            'premier-plan' : la nuée vole par-dessus le visuel (classe .nuee-premier-plan).
 *
 * Le HTML porte les valeurs ci-dessous pour le repli sans JavaScript ; js/sections.js applique
 * ce fichier au chargement. */
window.VISUELS = {
  'visuel-1': {
    type: 'image', src: 'assets/img/bleuse-3.jpg', position: '60% 35%', lumiere: 'bleuse-3',
    alt: { fr: 'Pierre Bleuse dirigeant l\'orchestre en concert', en: 'Pierre Bleuse conducting the orchestra in concert' },
    opacite: 100
  },
  'visuel-2': {
    type: 'image', src: 'assets/img/bleuse-1.jpg', position: '52% 42%', positionMobile: '57% 50%', lumiere: 'bleuse-1',
    alt: '',
    opacite: 100
  },
  /* la captation, avec son : la vidéo est presque opaque (95 %), la nuée, qui suit sa bande-son,
     se devine à 5 % au travers. Nuée par-dessus une vidéo assombrie : nuee 'premier-plan', opacite 60. */
  'visuel-3': {
    type: 'video', src: 'assets/media/captation.mp4', affiche: 'assets/media/captation-affiche.jpg',
    son: true, piste: 'data/captation.json',
    alt: { fr: 'Pierre Bleuse en concert, extraits d\'une captation', en: 'Pierre Bleuse in concert, excerpts from a filmed performance' },
    nuee: 'arriere-plan', opacite: 95
  },
  'visuel-4': {
    type: 'image', src: 'assets/img/bleuse-5.jpg', position: '64% 50%',
    alt: { fr: 'Pierre Bleuse accroupi sur la scène, près du clavecin', en: 'Pierre Bleuse crouching on stage, beside the harpsichord' },
    opacite: 100
  }
};
