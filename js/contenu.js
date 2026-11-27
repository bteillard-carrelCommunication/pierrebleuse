/* ==========================================================================
   contenu.js · textes du site, dans chacune de ses langues, et outils de langue

   Chargé dans le <head>, avant tout le reste, sur toutes les pages. Deux parties :

   1. Langue, dictionnaire, typographie.
      window.SiteContenu = { langue, t(), html(), typo(), choisir(), ... }
   2. Rendu : application du dictionnaire au DOM ([data-t], [data-t-attr],
      blocs [data-langue-bloc] des pages rédigées en entier dans les deux
      langues), navigation (bascule de langue, menu, ancres en défilement
      doux, bouton de retour en haut de page), liens sortants ouverts dans un
      nouvel onglet.

   Trois fichiers écrits par Pupitre sont chargés avant celui-ci :
   - data/site.js (window.SITE) : les langues du site, la langue par défaut,
     la langue de référence (celle de rédaction, qui sert de repli quand une
     traduction manque), la couleur de fond, les blocs visibles ;
   - data/libelles.js (window.LIBELLES) : les libellés d'interface (menu,
     boutons, intitulés de colonnes), dans chaque langue ;
   - data/textes.js (window.TEXTES) : les textes éditables (citation,
     biographie, mention de saison, texte de la lettre, crédit du pied de
     page, description de la page).
   Le dictionnaire ci-dessous n'est plus qu'un secours, en français et en
   anglais, si data/libelles.js manque : Pupitre fait foi.

   Ajouter une langue ne demande aucun changement ici : elle se déclare dans
   Pupitre (Langues), qui l'écrit dans data/site.js.

   Le HTML statique porte la langue par défaut du site (repli sans
   JavaScript) ; dans cette langue, appliquer() compare ce repli au
   dictionnaire et consigne les différences dans SiteContenu.ecarts (contrôle
   de cohérence, doit rester vide).
   ========================================================================== */
(function () {
  'use strict';

  /* Réglages du site : data/site.js. Sans ce fichier, le site d'avant : français et anglais. */
  var SITE = window.SITE || {};
  var REGLAGE_LANGUES = SITE.langues || {};
  var LISTE_LANGUES = (Array.isArray(REGLAGE_LANGUES.liste) && REGLAGE_LANGUES.liste.length)
    ? REGLAGE_LANGUES.liste
    : [{ code: 'fr', nom: 'Français', locale: 'fr-FR' }, { code: 'en', nom: 'English', locale: 'en-GB' }];
  var LANGUES = LISTE_LANGUES.map(function (l) { return l.code; });
  var REFERENCE = LANGUES.indexOf(REGLAGE_LANGUES.reference) >= 0 ? REGLAGE_LANGUES.reference : LANGUES[0];
  var DEFAUT = LANGUES.indexOf(REGLAGE_LANGUES.defaut) >= 0 ? REGLAGE_LANGUES.defaut : REFERENCE;
  var CLE_LANGUE = 'pb-langue';
  var NBSP = ' ';
  var racineDoc = document.documentElement;

  /* ---------- 1. Langue -------------------------------------------------- */

  function parametres() {
    try { return new URLSearchParams(window.location.search); } catch (e) { return null; }
  }
  function lireMemoire() {
    try { return window.localStorage.getItem(CLE_LANGUE); } catch (e) { return null; }
  }
  function ecrireMemoire(l) {
    try { window.localStorage.setItem(CLE_LANGUE, l); } catch (e) { /* stockage indisponible */ }
  }

  /* La langue : celle que demande l'adresse (?lang=), sinon celle que le visiteur a choisie à une
     visite précédente, sinon la langue par défaut du site. La langue du navigateur n'est pas
     consultée. */
  var langue = DEFAUT;
  (function () {
    var p = parametres();
    var demandee = p ? p.get('lang') : null;
    if (LANGUES.indexOf(demandee) >= 0) { langue = demandee; ecrireMemoire(langue); return; }
    var memo = lireMemoire();
    if (LANGUES.indexOf(memo) >= 0) langue = memo;
  })();
  /* La langue du HTML statique, telle que Pupitre l'a écrite : <html lang>. */
  var langueStatique = racineDoc.getAttribute('lang') || DEFAUT;
  racineDoc.lang = langue;

  /* Dans une autre langue que celle du HTML statique, les textes statiques restent cachés
     jusqu'à leur remplacement, pour ne pas montrer une page en deux langues. Garde-fou de
     4 s, comme le moteur : si quelque chose casse, le texte statique s'affiche. */
  if (langue !== langueStatique) {
    racineDoc.classList.add('i18n-attente');
    setTimeout(function () { racineDoc.classList.remove('i18n-attente'); }, 4000);
  }

  /* ---------- 1. Dictionnaire -------------------------------------------- */
  /* Secours si data/libelles.js manque. clé : [français, anglais]. *…* = italique. Les espaces
     insécables sont posées par typo(), pas à la main. */

  var T = {
    'meta.titre': ['Pierre Bleuse, chef d\'orchestre', 'Pierre Bleuse, conductor'],

    'nav.menu': ['Menu', 'Menu'],
    'nav.agenda': ['Agenda', 'Concerts'],
    'nav.disques': ['Discographie', 'Discography'],
    'nav.parcours': ['Parcours', 'Biography'],
    'nav.presse': ['Presse', 'Press'],
    'nav.telechargement': ['Téléchargement', 'Downloads'],
    'nav.contact': ['Contact', 'Contact'],

    'ouverture.defiler': ['Faire défiler la page', 'Scroll down'],
    'ouverture.scroll': ['Scroll', 'Scroll'],
    'video.lire': ['Lire la vidéo', 'Play the video'],
    'video.pause': ['Mettre la vidéo en pause', 'Pause the video'],
    'video.suivante': ['Vidéo suivante', 'Next video'],
    'video.precedente': ['Vidéo précédente', 'Previous video'],
    'video.position': ['Vidéo {n} sur {total}', 'Video {n} of {total}'],
    'lecteur.play': ['Play', 'Play'],
    'lecteur.pause': ['Pause', 'Pause'],
    'lecteur.titres': ['Choisir la musique', 'Choose the music'],

    'agenda.titre': ['Les prochains concerts', 'Upcoming concerts'],
    'agenda.affiche': ['À l\'affiche', 'Coming up'],
    'agenda.saison': ['Les concerts', 'The concerts'],
    'agenda.tempsFort': ['Temps fort', 'Highlight'],      /* plus affiché : gardé avec le drapeau tempsFort des données */
    'agenda.serie': ['{n} représentations, prochaine le {date}', '{n} performances, next on {date}'],
    'agenda.tournee': ['Tournée, {n} villes', 'On tour, {n} cities'],
    'agenda.programme': ['Programme', 'Programme'],
    'agenda.avec': ['Avec', 'With'],
    'agenda.lien': ['Informations et billetterie', 'Details and tickets'],
    'agenda.plus': ['En savoir plus', 'Find out more'],
    'agenda.disque': ['Disque', 'Recording'],
    'agenda.vide': ['Les prochaines dates seront annoncées ici.', 'Upcoming dates will be announced here.'],
    'agenda.tous': ['Tous les concerts', 'All concerts'],
    'agenda.col.mois': ['Mois', 'Month'],
    'agenda.col.date': ['Date', 'Date'],
    'agenda.col.lieu': ['Pays, ville', 'Country, city'],
    'agenda.col.salle': ['Salle', 'Venue'],
    'agenda.col.infos': ['Orchestre, programme', 'Orchestra, programme'],
    'agenda.col.lien': ['Lien', 'Link'],
    'concerts.avenir': ['À venir', 'Upcoming'],
    'concerts.passes': ['Concerts passés', 'Past concerts'],
    'concerts.chercher': ['Chercher', 'Search'],
    'concerts.chercher.indice': ['titre, ville, salle, œuvre, soliste…', 'title, city, venue, work, soloist…'],
    'concerts.pays': ['Pays', 'Country'],
    'concerts.ville': ['Ville', 'City'],
    'concerts.orchestre': ['Orchestre', 'Orchestra'],
    'concerts.tous': ['Tous', 'All'],
    'concerts.toutes': ['Toutes', 'All'],
    'concerts.trier': ['Trier par', 'Sort by'],
    'concerts.tri.date': ['Date', 'Date'],
    'concerts.tri.inverse': ['Date, ordre inverse', 'Date, reversed'],
    'concerts.tri.lieu': ['Pays, ville', 'Country, city'],
    'concerts.tri.salle': ['Salle', 'Venue'],
    'concerts.tri.ensemble': ['Orchestre', 'Orchestra'],
    'concerts.tri.titre': ['Titre', 'Title'],
    'concerts.effacer': ['Tout afficher', 'Show all'],
    'concerts.compte.un': ['{n} concert', '{n} concert'],
    'concerts.compte.n': ['{n} concerts', '{n} concerts'],
    'concerts.aucun': ['Aucun concert ne correspond à cette recherche.', 'No concert matches this search.'],
    'concerts.detail': ['Détail du concert', 'Concert details'],
    'concerts.fermer': ['Refermer', 'Close'],
    'concerts.presse': ['Revue de presse de ce concert', 'Press reviews'],
    'concerts.description': ['Présentation', 'About'],

    'disques.titre': ['Discographie', 'Discography'],
    'disques.mot': ['Le mot de Pierre Bleuse', 'A word from Pierre Bleuse'],
    'disques.commander': ['Commander', 'Order'],
    'disques.streaming': ['Écouter', 'Listen'],
    'disques.plus': ['En savoir plus', 'Find out more'],
    'disques.presentation': ['Présentation', 'About the recording'],
    'disques.contexte': ['Contexte artistique', 'Artistic context'],
    'disques.oeuvres': ['Œuvres', 'Works'],
    'disques.compositeurs': ['Compositeurs', 'Composers'],
    'disques.interpretes': ['Interprètes', 'Performers'],
    'disques.infos': ['À propos des œuvres', 'About the works'],
    'disques.distinctions': ['Distinctions', 'Awards'],
    'disques.critiques': ['La critique', 'Reviews'],
    'disques.page': ['Page de l\'album', 'Album page'],
    'disques.sortie': ['Sortie', 'Released'],
    'disques.image': ['Image {n} sur {total}', 'Image {n} of {total}'],
    'disques.image.suivante': ['Image suivante', 'Next image'],
    'disques.image.precedente': ['Image précédente', 'Previous image'],
    'panneau.fermer': ['Refermer', 'Close'],

    'parcours.titre': ['Parcours', 'Biography'],
    'parcours.fonctions': ['Fonctions', 'Positions'],
    'parcours.opera': ['Opéra et création', 'Opera and new music'],      /* plus affiché : la rubrique est remplacée par parcours.collaborations */
    'parcours.collaborations': ['Grands débuts et collaborations', 'Major debuts and collaborations'],
    'parcours.depuis': ['depuis {annee}', 'since {annee}'],
    'parcours.periode': ['{debut} à {fin}', '{debut} to {fin}'],
    'parcours.invites': ['Orchestres invités (sélection)', 'Guest conducting (selection)'],
    'parcours.zone.ameriques': ['Amériques', 'Americas'],
    'parcours.zone.europe-afrique': ['Europe et Afrique', 'Europe and Africa'],
    'parcours.zone.asie-oceanie': ['Asie, Russie et Océanie', 'Asia, Russia and Oceania'],
    'parcours.carte.monde': ['Carte du monde des orchestres invités', 'World map of guest orchestras'],
    'commun.aVenir': ['à venir', 'coming soon'],

    'presse.titre': ['Presse', 'Press'],
    'presse.lire': ['Lire l\'article', 'Read the article'],
    'presse.plus': ['En savoir plus', 'Read more'],
    'presse.traduction': ['Traduction', 'Translation'],
    'presse.concerts': ['Concerts associés', 'Related concerts'],
    'presse.albums': ['Albums associés', 'Related albums'],
    'presse.citations': ['Autres citations', 'More quotes'],
    'presse.auteur': ['Par {auteur}', 'By {auteur}'],
    'presse.type.critique_concert': ['Critique de concert', 'Concert review'],
    'presse.type.critique_album': ['Critique d\'album', 'Album review'],
    'presse.type.portrait': ['Portrait', 'Profile'],
    'presse.type.interview': ['Interview', 'Interview'],
    'presse.type.actualite': ['Actualité', 'News'],
    'presse.type.autre': ['Publication', 'Publication'],
    'presse.tous': ['Tous les articles', 'All articles'],
    'presse.col.media': ['Média', 'Publication'],
    'presse.col.article': ['Article', 'Article'],
    'presse.col.auteur': ['Auteur', 'Author'],
    'presse.col.extrait': ['Extrait', 'Quote'],

    'tel.titre': ['Téléchargement', 'Downloads'],

    'suivre.titre': ['Restons en contact', 'Stay in touch'],
    'lettre.titre': ['La lettre', 'Newsletter'],
    'lettre.champ': ['Adresse e-mail', 'Email address'],
    'lettre.bouton': ['S\'inscrire', 'Subscribe'],
    'lettre.note': ['Inscription bientôt ouverte', 'Sign-up opening soon'],

    'contact.titre': ['Contacts', 'Contacts'],

    'pied.mentions': ['Mentions légales', 'Legal notice'],
    'pied.confidentialite': ['Confidentialité', 'Privacy'],
    'pied.credits': ['Crédits', 'Credits'],
    'pied.reglages': ['Réglages de la nuée', 'Cloud settings'],
    'pied.pause': ['Pause de l\'animation', 'Pause the animation'],
    'pied.reprise': ['Reprendre l\'animation', 'Resume the animation'],

    'page.retour': ['Retour à l\'accueil', 'Back to home'],
    'page.haut': ['Haut de page', 'Back to top'],
    'legal.mentions': ['Mentions légales', 'Legal notice'],
    'legal.confidentialite': ['Politique de confidentialité', 'Privacy policy'],
    'legal.credits': ['Crédits', 'Credits'],
    'yt.texte': [
      'Cette vidéo est hébergée par YouTube. La charger dépose des cookies de YouTube (Google) sur votre appareil. Rien n\'est chargé avant votre accord.',
      'This video is hosted by YouTube. Loading it places YouTube (Google) cookies on your device. Nothing is loaded without your consent.'],
    'yt.bouton': ['Charger la vidéo', 'Load the video']
  };

  /* Le dictionnaire de secours devient { fr, en } par clé, comme les données de Pupitre. */
  Object.keys(T).forEach(function (cle) { T[cle] = { fr: T[cle][0], en: T[cle][1] }; });

  /* Une entrée de Pupitre : une chaîne (même texte dans toutes les langues) ou { fr, en, … }. */
  function completer(cle, champ) {
    if (champ == null) return;
    if (typeof champ === 'string') { var o = {}; o[REFERENCE] = champ; T[cle] = o; return; }
    if (typeof champ === 'object') T[cle] = champ;
  }

  /* Libellés d'interface : data/libelles.js (window.LIBELLES). */
  (function () {
    var L = window.LIBELLES || {};
    Object.keys(L).forEach(function (cle) { completer(cle, L[cle]); });
  })();

  /* Textes éditables : data/textes.js (window.TEXTES), chaque champ en chaîne ou en { fr, en, … }.
     La biographie est une liste de paragraphes : bio.p1, bio.p2… Sans ce fichier, les clés
     manquent et le texte du HTML reste en place. */
  (function () {
    var D = window.TEXTES || {};
    completer('meta.description', D.description);
    completer('ouverture.citation', D.citation);
    completer('agenda.mention', D.saison);
    completer('lettre.texte', D.lettre);
    completer('pied.credit', D.credit);
    (Array.isArray(D.biographie) ? D.biographie : []).forEach(function (p, i) { completer('bio.p' + (i + 1), p); });
  })();

  /* ---------- 1. Outils de texte ----------------------------------------- */

  /* Texte d'une clé dans la langue courante, avec remplacement de {n}, {date}… Traduction
     manquante : le texte de la langue de référence. */
  function t(cle, vars) {
    var entree = T[cle];
    if (!entree) return '';
    var s = entree[langue] || entree[REFERENCE] || entree.fr || '';
    if (vars) s = s.replace(/\{(\w+)\}/g, function (m, k) { return (k in vars) ? String(vars[k]) : m; });
    return s;
  }

  /* Champ de données écrit soit en chaîne, soit en { fr, en, … }. Traduction manquante : le
     texte de la langue de référence. */
  function choisir(champ) {
    if (champ == null) return '';
    if (typeof champ === 'string' || typeof champ === 'number') return String(champ);
    return champ[langue] || champ[REFERENCE] || champ.fr || '';
  }

  /* Typographie : espaces insécables avant : ; ? ! », après « et après n° / No. */
  function typo(s) {
    return String(s)
      .replace(/ ([:;?!»])/g, NBSP + '$1')
      .replace(/« /g, '«' + NBSP)
      .replace(/(n°|No\.) (?=\d)/g, '$1' + NBSP);
  }

  function echapper(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* Texte prêt à insérer : échappé, typographié, *italique* rendu en <i>. */
  function html(s) {
    return typo(echapper(s)).replace(/\*([^*]+)\*/g, '<i>$1</i>');
  }

  /* Texte brut (attributs alt, title…) : typographié, sans les astérisques. */
  function brut(s) {
    return typo(String(s)).replace(/\*([^*]+)\*/g, '$1');
  }

  /* Lien sortant : toujours dans un nouvel onglet. */
  function lienSortant(url, contenuHtml, classe) {
    return '<a' + (classe ? ' class="' + classe + '"' : '') + ' href="' + echapper(url) + '" target="_blank" rel="noopener">' + contenuHtml + '</a>';
  }

  /* ---------- 2. Rendu : application au DOM ------------------------------ */

  var ecarts = [];

  function normaliser(h) {
    return String(h).replace(/[ \t\r\n]+/g, ' ').trim();
  }

  /* Blocs masqués dans Pupitre (data/site.js) : la section quitte la page, avec son entrée de
     menu. Elle n'occupe aucune place, ne compte ni dans l'aimantation ni dans le parcours du
     bouton Scroll, et ses médias ne sont pas chargés. Le <head> porte déjà la règle CSS qui la
     cache dès le premier affichage. */
  function retirerBlocsMasques(racine) {
    var blocs = SITE.blocs || {};
    Object.keys(blocs).forEach(function (cle) {
      if (blocs[cle] !== false) return;
      var n = document.getElementById(cle);
      if (n && n.parentNode) n.parentNode.removeChild(n);
      racine.querySelectorAll('[data-menu-liste] a[href="#' + cle + '"]').forEach(function (a) {
        var li = a.closest('li') || a;
        if (li.parentNode) li.parentNode.removeChild(li);
      });
    });
  }
  function blocVisible(cle) {
    return !(SITE.blocs && SITE.blocs[cle] === false);
  }

  /* Bascule de langue : un lien par langue du site. Le HTML en porte déjà la liste (zone écrite
     par Pupitre) ; elle n'est refaite que si elle ne dit pas la même chose. */
  function poserLangues(racine) {
    racine.querySelectorAll('[data-langues]').forEach(function (boite) {
      var presentes = Array.prototype.map.call(boite.querySelectorAll('[data-langue]'), function (a) { return a.getAttribute('data-langue'); });
      if (presentes.join(',') === LANGUES.join(',')) return;
      boite.innerHTML = LANGUES.map(function (l) {
        return '<a href="?lang=' + l + '" data-langue="' + l + '" lang="' + l + '" hreflang="' + l + '">' + l.toUpperCase() + '</a>';
      }).join('<span class="sep" aria-hidden="true">|</span>');
    });
  }

  function appliquer(racine) {
    racine = racine || document;
    var fr = (langue === langueStatique);          /* la langue du HTML statique : le repli se compare au dictionnaire */
    var sonde = document.createElement('div');

    retirerBlocsMasques(racine);
    poserLangues(racine);

    racine.querySelectorAll('[data-t]').forEach(function (n) {
      var cle = n.getAttribute('data-t');
      if (!T[cle]) { ecarts.push('clé inconnue : ' + cle); return; }
      var h = html(t(cle));
      if (fr) {
        sonde.innerHTML = h;
        if (normaliser(n.innerHTML) !== normaliser(sonde.innerHTML)) ecarts.push(cle);
      }
      n.innerHTML = h;
    });

    /* data-t-attr="alt:photo1.alt; aria-label:nav.agenda" */
    racine.querySelectorAll('[data-t-attr]').forEach(function (n) {
      n.getAttribute('data-t-attr').split(';').forEach(function (paire) {
        var i = paire.indexOf(':');
        if (i < 0) return;
        var attr = paire.slice(0, i).trim();
        var cle = paire.slice(i + 1).trim();
        if (!T[cle]) { ecarts.push('clé inconnue : ' + cle); return; }
        var v = brut(t(cle));
        if (fr && n.getAttribute(attr) !== v) ecarts.push(cle + ' (' + attr + ')');
        n.setAttribute(attr, v);
      });
    });

    /* Pages rédigées en entier dans plusieurs langues (mentions légales, confidentialité,
       crédits) : un bloc par langue, seul celui de la langue courante reste affiché ; si la page
       n'est pas rédigée dans cette langue, celui de la langue par défaut, sinon de référence. */
    var blocsLangue = racine.querySelectorAll('[data-langue-bloc]');
    var redigees = Array.prototype.map.call(blocsLangue, function (n) { return n.getAttribute('data-langue-bloc'); });
    var langueBloc = redigees.indexOf(langue) >= 0 ? langue : (redigees.indexOf(DEFAUT) >= 0 ? DEFAUT : (redigees.indexOf(REFERENCE) >= 0 ? REFERENCE : redigees[0]));
    blocsLangue.forEach(function (n) {
      n.hidden = (n.getAttribute('data-langue-bloc') !== langueBloc);
    });

    /* Titre de la page : <html data-titre="clé"> sur les pages secondaires. */
    var cleTitre = racineDoc.getAttribute('data-titre');
    document.title = cleTitre && T[cleTitre] ? brut(t(cleTitre)) + ' · Pierre Bleuse' : brut(t('meta.titre'));
    var desc = document.querySelector('meta[name="description"]');
    if (desc && T['meta.description']) desc.setAttribute('content', brut(t('meta.description')));

    /* Bascule de langue : des liens qui rechargent la page dans une autre
       langue, en gardant les autres paramètres (?date=, ?reduit=…). */
    racine.querySelectorAll('[data-langue]').forEach(function (a) {
      var l = a.getAttribute('data-langue');
      var p = parametres();
      if (p) { p.set('lang', l); a.setAttribute('href', '?' + p.toString()); }
      var actif = (l === langue);
      a.classList.toggle('is-on', actif);
      if (actif) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
    });

    racineDoc.classList.remove('i18n-attente');
  }

  /* Tous les liens sortants s'ouvrent dans un nouvel onglet. À appeler une
     fois le contenu en place (les rendus qui écrivent des liens le font déjà). */
  function liensSortants(racine) {
    (racine || document).querySelectorAll('a[href]').forEach(function (a) {
      var href = a.getAttribute('href');
      if (!/^https?:\/\//i.test(href)) return;
      try { if (new URL(href, window.location.href).host === window.location.host) return; } catch (e) { return; }
      a.setAttribute('target', '_blank');
      var rel = (a.getAttribute('rel') || '').split(/\s+/).filter(Boolean);
      if (rel.indexOf('noopener') < 0) rel.push('noopener');
      a.setAttribute('rel', rel.join(' '));
    });
  }

  /* ---------- 2. Rendu : navigation (menu, ancres, logo) ----------------- */
  /* Délégation sur le document : posée dès le <head>, elle ne dépend ni de
     l'ordre des scripts ni du moteur. */

  racineDoc.classList.add('menu-pret');

  /* focus : au clavier, le premier lien reçoit le focus à l'ouverture (la
     liste précède le bouton dans la page, Tab n'y entrerait pas). */
  function menu(ouvrir, focus) {
    var b = document.querySelector('[data-menu]');
    racineDoc.classList.toggle('menu-ouvert', !!ouvrir);
    if (b) b.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
    if (ouvrir && focus) {
      var premier = document.querySelector('[data-menu-liste] a');
      if (premier) premier.focus();
    }
  }
  function menuOuvert() { return racineDoc.classList.contains('menu-ouvert'); }

  function mouvementReduit() {
    var p = parametres();
    return (p && p.get('reduit') === '1') ||
      (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  /* Aller à une position, en défilement doux (accélération puis décélération,
     d'autant plus long que le trajet l'est). Par Lenis s'il conduit le
     défilement (sinon son animation en cours reprendrait la main), nativement
     sinon. Mouvement réduit : saut immédiat. */
  function allerA(y, doux) {
    var h4 = window.__h4;
    var lenis = h4 && h4.lenis;
    y = Math.max(0, Math.round(y));
    doux = doux && !mouvementReduit();
    if (lenis && typeof lenis.scrollTo === 'function') {
      if (!doux) { signalerTrajet(y, 0); lenis.scrollTo(y, { immediate: true, force: true }); return; }
      var trajet = Math.abs(y - window.pageYOffset);
      var duree = Math.min(2.4, 0.9 + trajet / 5000);
      signalerTrajet(y, duree);
      lenis.scrollTo(y, {
        duration: duree,
        easing: function (x) { return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2; },
        force: true
      });
    } else if (doux && 'scrollBehavior' in racineDoc.style) {
      signalerTrajet(y, 2);
      window.scrollTo({ top: y, behavior: 'smooth' });
    } else {
      signalerTrajet(y, 0);
      window.scrollTo(0, y);
    }
  }
  /* Prévient la page qu'un défilement automatique part vers y, pour `duree` secondes : main.js
     suspend l'aimantation des sections le temps du trajet. */
  function signalerTrajet(y, duree) {
    try { window.dispatchEvent(new CustomEvent('pb:trajet', { detail: { y: y, duree: duree } })); }
    catch (e) { /* sans importance */ }
  }

  function allerAncre(id, majAdresse, doux) {
    var cible = id ? document.getElementById(id) : null;
    if (id && !cible) return false;
    var y = 0;
    if (cible) {
      var marge = parseFloat(window.getComputedStyle(cible).scrollMarginTop) || 0;
      y = cible.getBoundingClientRect().top + window.pageYOffset - marge;
    }
    allerA(y, doux);
    if (cible) {
      if (!cible.hasAttribute('tabindex')) cible.setAttribute('tabindex', '-1');
      try { cible.focus({ preventScroll: true }); } catch (e) { /* sans importance */ }
    }
    if (majAdresse) {
      try { window.history.replaceState(null, '', id ? '#' + id : window.location.pathname + window.location.search); }
      catch (e) { /* sans importance */ }
    }
    return true;
  }

  document.addEventListener('click', function (e) {
    var cible = e.target && e.target.closest ? e.target : null;
    if (!cible) return;

    var bouton = cible.closest('[data-menu]');
    if (bouton) { menu(!menuOuvert(), e.detail === 0); return; }   /* detail 0 : clic venu du clavier */

    var lien = cible.closest('a[href]');
    if (lien && !e.defaultPrevented && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey && e.button === 0) {
      var href = lien.getAttribute('href');
      if (lien.hasAttribute('data-haut')) {            /* logo de l'accueil : haut de la page, sans recharger */
        e.preventDefault(); menu(false); allerAncre('', true, true); return;
      }
      if (href.charAt(0) === '#' && href.length > 1) {  /* ancre de la page : défilement doux */
        if (allerAncre(href.slice(1), true, true)) { e.preventDefault(); menu(false); }
        return;
      }
    }

    if (menuOuvert() && !cible.closest('[data-menu-liste]')) menu(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menuOuvert()) {
      menu(false);
      var b = document.querySelector('[data-menu]');
      if (b) b.focus();
    }
  });

  /* ---------- 2. Rendu : retour en haut de page -------------------------- */
  /* Un bouton fixe, en bas à droite, sur toutes les pages : il apparaît quand on a parcouru le
     quart du défilement de la page (.haut-visible sur <html>), et remonte en défilement doux
     (attribut data-haut, traité plus haut). Posé par le script : sans JavaScript, la position de
     défilement n'est pas connue. */
  var SEUIL_HAUT = 0.25;
  function installerRetourHaut() {
    if (!document.body || document.querySelector('.retour-haut')) return;
    var a = document.createElement('a');
    a.className = 'retour-haut';
    a.href = '#';
    a.setAttribute('data-haut', '');
    a.setAttribute('aria-label', brut(t('page.haut')));
    a.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 14.5l5.5-5.5 5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.body.appendChild(a);
    var attente = false;
    function maj() {
      attente = false;
      var course = racineDoc.scrollHeight - window.innerHeight;
      racineDoc.classList.toggle('haut-visible', course > 0.5 * window.innerHeight && window.pageYOffset >= SEUIL_HAUT * course);
    }
    function planifier() {
      if (attente) return;
      attente = true;
      window.requestAnimationFrame(maj);
    }
    window.addEventListener('scroll', planifier, { passive: true });
    window.addEventListener('resize', planifier);
    window.addEventListener('load', planifier);
    maj();
  }
  if (document.body) installerRetourHaut();
  else document.addEventListener('DOMContentLoaded', installerRetourHaut);

  var LOCALES_CONNUES = { fr: 'fr-FR', en: 'en-GB' };
  var locale = (LISTE_LANGUES.filter(function (l) { return l.code === langue; })[0] || {}).locale || LOCALES_CONNUES[langue] || langue;

  window.SiteContenu = {
    langue: langue,
    langues: LANGUES.slice(),
    reference: REFERENCE,
    defaut: DEFAUT,
    statique: langueStatique,
    locale: locale,
    site: SITE,
    blocVisible: blocVisible,
    parametres: parametres,
    t: t,
    choisir: choisir,
    typo: typo,
    echapper: echapper,
    html: html,
    brut: brut,
    lienSortant: lienSortant,
    appliquer: appliquer,
    liensSortants: liensSortants,
    allerAncre: allerAncre,
    ecarts: ecarts
  };
})();
