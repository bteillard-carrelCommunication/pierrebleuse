/* ==========================================================================
   contenu.js · textes du site (français, anglais) et outils de langue

   Chargé dans le <head>, avant tout le reste. Deux parties :

   1. Langue, dictionnaire, typographie : réutilisable par toutes les pages.
      window.SiteContenu = { langue, t(), html(), typo(), choisir(), ... }
   2. Rendu : application du dictionnaire au DOM ([data-t], [data-t-attr]) et
      câblage de la navigation (bascule de langue, menu, ancres).

   Source des textes : docs/site/CONTENU-SITE.md. Rien n'est écrit ici qui n'y
   figure pas. Le HTML statique porte le français (repli sans JavaScript) ; en
   français, appliquer() compare ce repli au dictionnaire et consigne les
   différences dans SiteContenu.ecarts (contrôle de cohérence, doit rester vide).
   ========================================================================== */
(function () {
  'use strict';

  var LANGUES = ['fr', 'en'];
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

  var langue = 'fr';
  (function () {
    var p = parametres();
    var demandee = p ? p.get('lang') : null;
    if (LANGUES.indexOf(demandee) >= 0) { langue = demandee; ecrireMemoire(langue); return; }
    var memo = lireMemoire();
    if (LANGUES.indexOf(memo) >= 0) langue = memo;
  })();
  racineDoc.lang = langue;

  /* En anglais, les textes statiques (français) restent cachés jusqu'à leur
     remplacement, pour ne pas montrer une page en deux langues. Garde-fou de
     4 s, comme le moteur : si quelque chose casse, le français s'affiche. */
  if (langue !== 'fr') {
    racineDoc.classList.add('i18n-attente');
    setTimeout(function () { racineDoc.classList.remove('i18n-attente'); }, 4000);
  }

  /* ---------- 1. Dictionnaire -------------------------------------------- */
  /* clé : [français, anglais]. *…* = italique. Les espaces insécables sont
     posées par typo(), pas à la main. */

  var T = {
    'meta.titre': ['Pierre Bleuse, chef d\'orchestre', 'Pierre Bleuse, conductor'],
    'meta.description': [
      'Site officiel de Pierre Bleuse, chef d\'orchestre : agenda des concerts, biographie, disques, presse, contacts.',
      'Official website of conductor Pierre Bleuse: concert schedule, biography, recordings, press, contacts.'],

    'nav.agenda': ['Agenda', 'Concerts'],
    'nav.parcours': ['Parcours', 'Biography'],
    'nav.ecouter': ['Écouter, voir', 'Listen, watch'],
    'nav.presse': ['Presse', 'Press'],
    'nav.contact': ['Contact', 'Contact'],

    'ouverture.surtitre': ['Chef d\'orchestre', 'Conductor'],
    'ouverture.ligne1': ['Directeur musical de l\'Ensemble intercontemporain', 'Music Director, Ensemble intercontemporain'],
    'ouverture.ligne2': ['Directeur artistique du Festival Pablo Casals de Prades', 'Artistic Director, Pablo Casals Festival, Prades'],
    'ouverture.prochain': ['Prochain concert', 'Next concert'],
    'ouverture.ceSoir': ['Ce soir', 'Tonight'],
    'ouverture.demain': ['Demain', 'Tomorrow'],
    'ouverture.voirAgenda': ['Voir l\'agenda', 'See all concerts'],
    'ouverture.ecouter': ['Écouter', 'Listen'],
    'ouverture.credit': ['Tchaïkovski, *Pathétique*, IV, extrait, domaine public', 'Tchaikovsky, *Pathétique*, IV, excerpt, public domain'],
    'ouverture.son': ['Son', 'Sound'],
    'ouverture.son.coupe': ['coupé', 'muted'],
    'ouverture.son.inaudible': ['inaudible', 'inaudible'],
    'ouverture.son.audible': ['audible', 'audible'],
    'ouverture.defiler': ['Défiler', 'Scroll'],

    'presentation.p1': [
      'Pierre Bleuse est directeur musical de l\'Ensemble intercontemporain et directeur artistique du Festival Pablo Casals de Prades. Chef principal de l\'Orchestre symphonique d\'Odense de 2021 à 2026, il est l\'invité d\'orchestres comme le Royal Concertgebouw Orchestra, le London Philharmonic Orchestra, l\'Orchestre de Paris ou le Tokyo Symphony Orchestra.',
      'Pierre Bleuse is Music Director of the Ensemble intercontemporain and Artistic Director of the Pablo Casals Festival in Prades. Chief Conductor of the Odense Symphony Orchestra from 2021 to 2026, he is a guest of orchestras including the Royal Concertgebouw Orchestra, the London Philharmonic Orchestra, the Orchestre de Paris and the Tokyo Symphony Orchestra.'],
    'presentation.p2': [
      'Violoniste de formation, il dirige le grand répertoire symphonique et la musique d\'aujourd\'hui avec la même attention : faire dialoguer les œuvres plutôt que les opposer.',
      'A violinist by training, he brings the same attention to the great symphonic repertoire and to the music of today, letting works speak to one another rather than setting them apart.'],
    'citation.label': ['La presse', 'In the press'],
    'citation.texte': ['“A simply astonishing disc.”', '“A simply astonishing disc.”'],
    'citation.source': ['Colin Clarke, *Fanfare*, à propos de *Ravel Abroad*', 'Colin Clarke, *Fanfare*, on *Ravel Abroad*'],

    'photo1.alt': ['Pierre Bleuse dirigeant l\'orchestre en concert', 'Pierre Bleuse conducting the orchestra in concert'],
    'photo1.texte': [
      'Saison 2026-2027 : Mahler, Debussy et Ravel, et des créations de Du Yun, Bastien David et Joan Magrané Figuera.',
      '2026/27 season: Mahler, Debussy and Ravel, and premieres by Du Yun, Bastien David and Joan Magrané Figuera.'],

    'agenda.titre': ['Agenda', 'Concerts'],
    'agenda.mention': ['Saison 2026-2027', '2026/27 season'],
    'agenda.affiche': ['À l\'affiche', 'Coming up'],
    'agenda.saison': ['La saison', 'The season'],
    'agenda.derniers': ['Derniers concerts', 'Recent concerts'],
    'agenda.tempsFort': ['Temps fort', 'Highlight'],
    'agenda.serie': ['{n} représentations, prochaine le {date}', '{n} performances, next on {date}'],
    'agenda.tournee': ['Tournée, {n} villes', 'On tour, {n} cities'],
    'agenda.programme': ['Programme', 'Programme'],
    'agenda.avec': ['Avec', 'With'],
    'agenda.lien': ['Informations et billetterie', 'Details and tickets'],
    'agenda.disque': ['Disque', 'Recording'],
    'agenda.vide': ['Les prochaines dates seront annoncées ici.', 'Upcoming dates will be announced here.'],
    'agenda.archives': ['Toutes les archives', 'All past concerts'],

    'parcours.titre': ['Parcours', 'Biography'],
    'parcours.fonctions': ['Fonctions', 'Positions'],
    'parcours.fonction1': ['Directeur musical, Ensemble intercontemporain, depuis 2023', 'Music Director, Ensemble intercontemporain, since 2023'],
    'parcours.fonction2': ['Directeur artistique, Festival Pablo Casals de Prades, depuis 2021', 'Artistic Director, Pablo Casals Festival, Prades, since 2021'],
    'parcours.fonction3': ['Chef principal, Orchestre symphonique d\'Odense, 2021 à 2026', 'Chief Conductor, Odense Symphony Orchestra, 2021 to 2026'],
    'parcours.invites': ['Orchestres invités (sélection)', 'Guest conducting (selection)'],
    'parcours.opera': ['Opéra et création', 'Opera and new music'],
    'parcours.opera1': ['*Red Fox* de Du Yun, création mondiale, Dutch National Opera, 2027', '*Red Fox* by Du Yun, world premiere, Dutch National Opera, 2027'],
    'parcours.opera2': ['*Répons* de Pierre Boulez, ouverture du centenaire, Philharmonie de Paris, 2025', '*Répons* by Pierre Boulez, opening of the centenary, Philharmonie de Paris, 2025'],
    'parcours.opera3': ['BBC Proms avec l\'Ensemble intercontemporain, Royal Albert Hall, 2025', 'BBC Proms with the Ensemble intercontemporain, Royal Albert Hall, 2025'],
    'parcours.opera4': ['*Orgia* d\'Hèctor Parra, Teatro Arriaga de Bilbao, 2023, Gran Teatre del Liceu de Barcelone, 2024', '*Orgia* by Hèctor Parra, Teatro Arriaga, Bilbao, 2023, Gran Teatre del Liceu, Barcelona, 2024'],
    'parcours.formation': ['Formation', 'Training'],
    'parcours.formation.texte': [
      'Violon au Conservatoire national supérieur de musique et de danse de Paris, dans la classe de Patrice Fontanarosa, puis à l\'Université des arts de Berlin. Direction d\'orchestre auprès de Jorma Panula, puis à la Haute École de musique de Genève, dans la classe de Laurent Gay.',
      'Violin at the Paris Conservatoire (CNSMDP) with Patrice Fontanarosa, then at the Berlin University of the Arts. Conducting with Jorma Panula, then at the Haute École de musique de Genève with Laurent Gay.'],
    'parcours.bio': ['Lire la biographie complète', 'Read the full biography'],
    'parcours.telecharger': ['Télécharger la biographie (français, anglais)', 'Download the biography (French, English)'],
    'commun.aVenir': ['à venir', 'coming soon'],

    'ecouter.titre': ['Écouter, voir', 'Listen, watch'],
    'ecouter.disques': ['Disques', 'Recordings'],
    'ecouter.videos': ['Vidéos', 'Videos'],
    'disque1.titre': ['*Ravel Abroad*', '*Ravel Abroad*'],
    'disque1.texte': ['Orchestre symphonique d\'Odense, OUR Recordings, 2026', 'Odense Symphony Orchestra, OUR Recordings, 2026'],
    'disque2.titre': ['*Unsuk Chin*', '*Unsuk Chin*'],
    'disque2.texte': ['Ensemble intercontemporain, Alpha Classics, 2026', 'Ensemble intercontemporain, Alpha Classics, 2026'],
    'disque3.titre': ['*Viola Concertos*', '*Viola Concertos*'],
    'disque3.texte': ['Rafaell Altino, Orchestre symphonique d\'Odense, Dacapo, 2026', 'Rafaell Altino, Odense Symphony Orchestra, Dacapo, 2026'],
    'disque4.titre': ['*Ligeti*', '*Ligeti*'],
    'disque4.texte': ['Ensemble intercontemporain, Alpha Classics, 2024. Diapason d\'or de l\'année, Choc de *Classica*', 'Ensemble intercontemporain, Alpha Classics, 2024. Diapason d\'or of the year, Choc de *Classica*'],
    'video1.titre': ['Tchaïkovski, Symphonie n° 5', 'Tchaikovsky, Symphony No. 5'],
    'video2.titre': ['Saint-Saëns, Concerto pour violoncelle n° 2', 'Saint-Saëns, Cello Concerto No. 2'],

    'presse.titre': ['Presse', 'Press'],
    'presse.lien': ['Toute la revue de presse', 'All press'],
    'presse.ref1.media': ['*Le Monde*', '*Le Monde*'],
    'presse.ref1.texte': ['Portrait, Pierre Gervasoni, 22 juin 2024', 'Profile, Pierre Gervasoni, 22 June 2024'],
    'presse.ref2.media': ['*The Guardian*', '*The Guardian*'],
    'presse.ref2.texte': ['Critique du disque *Unsuk Chin*, Fiona Maddocks, 13 février 2026', 'Review of the *Unsuk Chin* album, Fiona Maddocks, 13 February 2026'],
    'presse.ref3.media': ['*Gramophone*', '*Gramophone*'],
    'presse.ref3.texte': ['Critique du disque *Ligeti*, Richard Whitehouse, juin 2024', 'Review of the *Ligeti* album, Richard Whitehouse, June 2024'],

    'pro.titre': ['Espace professionnel', 'For professionals'],
    'pro.texte': [
      'Biographies, photographies en haute définition et éléments graphiques à l\'usage des orchestres, des salles, des festivals et de la presse.',
      'Biographies, high-resolution photographs and graphic material for orchestras, venues, festivals and the press.'],
    'pro.item1': ['Biographies, français et anglais, versions courte et longue', 'Biographies, French and English, short and long versions'],
    'pro.item2': ['Photographies HD, avec crédits', 'High-resolution photographs, with credits'],
    'pro.item3': ['Éléments graphiques', 'Graphic material'],
    'pro.mention': ['À venir', 'Coming soon'],
    'contact.titre': ['Contact', 'Contact'],
    'contact.management': ['Management :', 'Management:'],
    'contact.management.nom': ['HarrisonParrott, François Guyard', 'HarrisonParrott, François Guyard'],
    'contact.communication': ['Communication et presse :', 'Communications and press:'],
    'contact.communication.nom': ['Carrel Communication', 'Carrel Communication'],

    'pied.alt': ['Pierre Bleuse accroupi sur la scène, près du clavecin', 'Pierre Bleuse crouching on stage, beside the harpsichord'],
    'lettre.titre': ['La lettre', 'Newsletter'],
    'lettre.texte': ['Les concerts du mois et les actualités, une fois par mois.', 'The month\'s concerts and news, once a month.'],
    'lettre.bouton': ['S\'inscrire (à venir)', 'Subscribe (coming soon)'],
    'pied.ligne': [
      'Pierre Bleuse · Mentions légales · Confidentialité · Crédits photographiques',
      'Pierre Bleuse · Legal notice · Privacy · Photo credits'],
    'pied.credit': [
      'Site en construction · bande-son de test : Tchaïkovski, Symphonie n° 6, IV, Musopen (domaine public)',
      'Website in progress · test soundtrack: Tchaikovsky, Symphony No. 6, IV, Musopen (public domain)']
  };

  /* Orchestres invités : liste identique dans les deux langues. */
  var ORCHESTRES = [
    'Royal Concertgebouw Orchestra', 'London Philharmonic Orchestra', 'BBC Symphony Orchestra',
    'City of Birmingham Symphony Orchestra', 'Orchestre de Paris', 'Orchestre national de France',
    'NDR Elbphilharmonie Orchester', 'hr-Sinfonieorchester Frankfurt', 'SWR Symphonieorchester',
    'Royal Stockholm Philharmonic Orchestra', 'Swedish Radio Symphony Orchestra',
    'Finnish Radio Symphony Orchestra', 'Orchestre de la Suisse Romande', 'Tokyo Symphony Orchestra',
    'Singapore Symphony Orchestra', 'Orquestra Sinfônica do Estado de São Paulo', 'Auckland Philharmonia'
  ];

  /* ---------- 1. Outils de texte ----------------------------------------- */

  /* Texte d'une clé dans la langue courante, avec remplacement de {n}, {date}… */
  function t(cle, vars) {
    var paire = T[cle];
    if (!paire) return '';
    var s = paire[langue === 'en' ? 1 : 0];
    if (vars) s = s.replace(/\{(\w+)\}/g, function (m, k) { return (k in vars) ? String(vars[k]) : m; });
    return s;
  }

  /* Champ de données écrit soit en chaîne, soit en { fr, en }. */
  function choisir(champ) {
    if (champ == null) return '';
    if (typeof champ === 'string') return champ;
    return champ[langue] || champ.fr || '';
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

  /* ---------- 2. Rendu : application au DOM ------------------------------ */

  var ecarts = [];

  function normaliser(h) {
    return String(h).replace(/[ \t\r\n]+/g, ' ').trim();
  }

  function appliquer(racine) {
    racine = racine || document;
    var fr = (langue === 'fr');
    var sonde = document.createElement('div');

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

    /* Liste des orchestres invités */
    racine.querySelectorAll('[data-orchestres]').forEach(function (n) {
      var h = ORCHESTRES.map(function (o) { return '<li>' + echapper(o) + '</li>'; }).join('');
      if (fr && normaliser(n.innerHTML).replace(/> </g, '><') !== h) ecarts.push('orchestres');
      n.innerHTML = h;
    });

    document.title = brut(t('meta.titre'));
    var desc = document.querySelector('meta[name="description"]');
    if (desc) desc.setAttribute('content', brut(t('meta.description')));

    /* Bascule de langue : deux liens qui rechargent la page dans l'autre
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

  /* Aller à une position : par Lenis s'il conduit le défilement (sinon son
     animation en cours reprendrait la main), nativement sinon. */
  function allerA(y) {
    var h4 = window.__h4;
    var lenis = h4 && h4.lenis;
    y = Math.max(0, Math.round(y));
    if (lenis && typeof lenis.scrollTo === 'function') lenis.scrollTo(y, { immediate: true, force: true });
    else window.scrollTo(0, y);
  }

  function allerAncre(id, majAdresse) {
    var cible = id ? document.getElementById(id) : null;
    if (id && !cible) return false;
    var y = 0;
    if (cible) {
      var marge = parseFloat(window.getComputedStyle(cible).scrollMarginTop) || 0;
      y = cible.getBoundingClientRect().top + window.pageYOffset - marge;
    }
    allerA(y);
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
      if (lien.hasAttribute('data-haut')) {            /* logo : haut de la page, sans recharger */
        e.preventDefault(); menu(false); allerAncre('', true); return;
      }
      if (href.charAt(0) === '#' && href.length > 1) {  /* ancre de la page */
        if (allerAncre(href.slice(1), true)) { e.preventDefault(); menu(false); }
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

  /* Voile dense de la navigation, une fois l'ouverture passée (css/site.css) */
  function majNav() {
    racineDoc.classList.toggle('nav-dense', window.pageYOffset > window.innerHeight * 0.5);
  }
  window.addEventListener('scroll', majNav, { passive: true });
  window.addEventListener('resize', majNav);
  majNav();

  /* Hauteur du bloc de l'ouverture, donnée à css/site.css (--ouverture-bloc-h) : le bloc
     remonte juste assez quand la fenêtre est basse, dans les deux langues. Sans JS, la
     feuille garde une hauteur forfaitaire. À appeler une fois le contenu en place. */
  function caler() {
    var bloc = document.querySelector('.ouverture__texte');
    if (bloc && bloc.offsetHeight) racineDoc.style.setProperty('--ouverture-bloc-h', bloc.offsetHeight + 'px');
  }
  window.addEventListener('resize', caler);
  window.addEventListener('load', caler);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(caler, function () {});

  window.SiteContenu = {
    langue: langue,
    langues: LANGUES.slice(),
    locale: langue === 'en' ? 'en-GB' : 'fr-FR',
    parametres: parametres,
    t: t,
    choisir: choisir,
    typo: typo,
    echapper: echapper,
    html: html,
    brut: brut,
    appliquer: appliquer,
    allerAncre: allerAncre,
    caler: caler,
    ecarts: ecarts
  };
})();
