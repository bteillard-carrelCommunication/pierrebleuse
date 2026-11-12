/* Presse : les quatre premiers articles sont sur la page d'accueil, tous sur presse.html.
 *
 * Une fiche contient, dans cet ordre :
 *   logo      fichier du logo (SVG ou PNG, affiché en blanc) ; vide : seul le nom s'affiche
 *   media     nom du titre de presse
 *   titre     titre de l'article : chaîne ou { fr, en } ; *…* = italique
 *   verbatim  un verbatim clé, cité dans sa langue (langue : code de la langue de l'extrait)
 *   resume    court résumé de l'article : chaîne ou { fr, en }
 *   date      AAAA-MM-JJ ou AAAA-MM ; vide si inconnue
 *   auteur
 *   lien      adresse de l'article
 * Un champ vide ne s'affiche pas. Source : docs/site/CONTENU-SITE.md. */
window.PRESSE = [
  {
    id: 'fanfare', media: 'Fanfare', logo: '',
    titre: { fr: 'À propos de *Ravel Abroad*', en: 'On *Ravel Abroad*' },
    auteur: 'Colin Clarke', date: '',
    verbatim: '“A simply astonishing disc.”', langue: 'en',
    resume: '',
    lien: ''
  },
  {
    id: 'le-monde', media: 'Le Monde', logo: '',
    titre: { fr: 'Portrait', en: 'Profile' },
    auteur: 'Pierre Gervasoni', date: '2024-06-22',
    verbatim: '', langue: 'fr',
    resume: '',
    lien: 'https://www.lemonde.fr/culture/article/2024/06/22/pierre-bleuse-directeur-musical-de-l-ensemble-intercontemporain-veut-faire-entendre-tout-ce-qui-se-passe-sur-la-planete_6242263_3246.html'
  },
  {
    id: 'the-guardian', media: 'The Guardian', logo: '',
    titre: { fr: 'Critique du disque *Unsuk Chin*', en: 'Review of the *Unsuk Chin* album' },
    auteur: 'Fiona Maddocks', date: '2026-02-13',
    verbatim: '', langue: 'en',
    resume: '',
    lien: 'https://www.theguardian.com/music/2026/feb/13/ensemble-intercontemporain-unsuk-chin-album-review-pierre-bleuse-samuel-favre-dimitri-vassilakis-gougalon-double-concerto'
  },
  {
    id: 'gramophone', media: 'Gramophone', logo: '',
    titre: { fr: 'Critique du disque *Ligeti*', en: 'Review of the *Ligeti* album' },
    auteur: 'Richard Whitehouse', date: '2024-06',
    verbatim: '', langue: 'en',
    resume: '',
    lien: 'https://gramophone.co.uk/review/ligeti-concertos-and-chamber-music-bleuse'
  }
];
