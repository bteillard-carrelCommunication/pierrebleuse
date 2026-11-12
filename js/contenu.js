/* ==========================================================================
   contenu.js · textes du site (français, anglais) et outils de langue

   Chargé dans le <head>, avant tout le reste, sur toutes les pages. Deux parties :

   1. Langue, dictionnaire, typographie.
      window.SiteContenu = { langue, t(), html(), typo(), choisir(), ... }
   2. Rendu : application du dictionnaire au DOM ([data-t], [data-t-attr],
      blocs [data-langue-bloc] des pages rédigées en entier dans les deux
      langues), navigation (bascule de langue, menu, ancres en défilement
      doux, bouton de retour en haut de page), liens sortants ouverts dans un
      nouvel onglet.

   Source des textes : docs/site/CONTENU-SITE.md. Le HTML statique porte le
   français (repli sans JavaScript) ; en français, appliquer() compare ce repli
   au dictionnaire et consigne les différences dans SiteContenu.ecarts
   (contrôle de cohérence, doit rester vide).
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

    'nav.menu': ['Menu', 'Menu'],
    'nav.agenda': ['Agenda', 'Concerts'],
    'nav.disques': ['Discographie', 'Discography'],
    'nav.parcours': ['Parcours', 'Biography'],
    'nav.presse': ['Presse', 'Press'],
    'nav.telechargement': ['Téléchargement', 'Downloads'],
    'nav.contact': ['Contact', 'Contact'],

    'ouverture.citation': ['« Plus je progresse, plus je me sens libre »', '“The further I progress, the freer I feel”'],
    'ouverture.defiler': ['Faire défiler la page', 'Scroll down'],
    'lecteur.play': ['Play', 'Play'],
    'lecteur.pause': ['Pause', 'Pause'],
    'lecteur.titres': ['Choisir la musique', 'Choose the music'],

    'bio.p1': [
      'Pierre Bleuse est directeur musical de l\'Ensemble intercontemporain et directeur artistique du Festival Pablo Casals de Prades. Chef principal de l\'Orchestre symphonique d\'Odense de 2021 à 2026, il est l\'invité d\'orchestres comme le Royal Concertgebouw Orchestra, le London Philharmonic Orchestra, l\'Orchestre de Paris ou le Tokyo Symphony Orchestra.',
      'Pierre Bleuse is Music Director of the Ensemble intercontemporain and Artistic Director of the Pablo Casals Festival in Prades. Chief Conductor of the Odense Symphony Orchestra from 2021 to 2026, he is a guest of orchestras including the Royal Concertgebouw Orchestra, the London Philharmonic Orchestra, the Orchestre de Paris and the Tokyo Symphony Orchestra.'],
    'bio.p2': [
      'Violoniste de formation, il dirige le grand répertoire symphonique et la musique d\'aujourd\'hui avec la même attention : faire dialoguer les œuvres plutôt que les opposer.',
      'A violinist by training, he brings the same attention to the great symphonic repertoire and to the music of today, letting works speak to one another rather than setting them apart.'],

    'agenda.titre': ['Agenda', 'Concerts'],
    'agenda.mention': ['Saison 2026-2027', '2026/27 season'],
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

    'disques.titre': ['Discographie', 'Discography'],
    'disques.mot': ['Le mot de Pierre Bleuse', 'A word from Pierre Bleuse'],
    'disques.commander': ['Commander', 'Order'],
    'disques.streaming': ['Écouter', 'Listen'],

    'parcours.titre': ['Parcours', 'Biography'],
    'parcours.fonctions': ['Fonctions', 'Positions'],
    'parcours.fonction1': ['Directeur musical, Ensemble intercontemporain, depuis 2023', 'Music Director, Ensemble intercontemporain, since 2023'],
    'parcours.fonction2': ['Directeur artistique, Festival Pablo Casals de Prades, depuis 2021', 'Artistic Director, Pablo Casals Festival, Prades, since 2021'],
    'parcours.fonction3': ['Chef principal, Orchestre symphonique d\'Odense, 2021 à 2026', 'Chief Conductor, Odense Symphony Orchestra, 2021 to 2026'],
    'parcours.opera': ['Opéra et création', 'Opera and new music'],
    'parcours.opera1': ['*Red Fox* de Du Yun, création mondiale, Dutch National Opera, 2027', '*Red Fox* by Du Yun, world premiere, Dutch National Opera, 2027'],
    'parcours.opera2': ['*Répons* de Pierre Boulez, ouverture du centenaire, Philharmonie de Paris, 2025', '*Répons* by Pierre Boulez, opening of the centenary, Philharmonie de Paris, 2025'],
    'parcours.opera3': ['BBC Proms avec l\'Ensemble intercontemporain, Royal Albert Hall, 2025', 'BBC Proms with the Ensemble intercontemporain, Royal Albert Hall, 2025'],
    'parcours.opera4': ['*Orgia* d\'Hèctor Parra, Teatro Arriaga de Bilbao, 2023, Gran Teatre del Liceu de Barcelone, 2024', '*Orgia* by Hèctor Parra, Teatro Arriaga, Bilbao, 2023, Gran Teatre del Liceu, Barcelona, 2024'],
    'parcours.invites': ['Orchestres invités (sélection)', 'Guest conducting (selection)'],
    'parcours.zone.ameriques': ['Amériques', 'Americas'],
    'parcours.zone.europe-afrique': ['Europe et Afrique', 'Europe and Africa'],
    'parcours.zone.asie-oceanie': ['Asie, Russie et Océanie', 'Asia, Russia and Oceania'],
    'parcours.carte.monde': ['Carte du monde des orchestres invités', 'World map of guest orchestras'],
    'commun.aVenir': ['à venir', 'coming soon'],

    'presse.titre': ['Presse', 'Press'],
    'presse.lire': ['Lire l\'article', 'Read the article'],
    'presse.tous': ['Tous les articles', 'All articles'],
    'presse.col.media': ['Média', 'Publication'],
    'presse.col.article': ['Article', 'Article'],
    'presse.col.auteur': ['Auteur', 'Author'],
    'presse.col.extrait': ['Extrait', 'Quote'],

    'tel.titre': ['Téléchargement', 'Downloads'],
    'tel.bioFr': ['Biographie, en français', 'Biography, in French'],
    'tel.bioEn': ['Biographie, en anglais', 'Biography, in English'],
    'tel.photos': ['Photographies', 'Photographs'],
    'tel.identite': ['Identité visuelle', 'Visual identity'],

    'suivre.titre': ['Restons en contact', 'Stay in touch'],
    'lettre.titre': ['La lettre', 'Newsletter'],
    'lettre.texte': ['Les concerts du mois et les actualités, une fois par mois.', 'The month\'s concerts and news, once a month.'],
    'lettre.champ': ['Adresse e-mail', 'Email address'],
    'lettre.bouton': ['S\'inscrire', 'Subscribe'],
    'lettre.note': ['Inscription bientôt ouverte', 'Sign-up opening soon'],

    'contact.titre': ['Contacts', 'Contacts'],
    'contact.communication': ['Communication', 'Communications'],

    'pied.mentions': ['Mentions légales', 'Legal notice'],
    'pied.confidentialite': ['Confidentialité', 'Privacy'],
    'pied.credits': ['Crédits', 'Credits'],
    'pied.credit': [
      'Site en construction · musiques : Tchaïkovski, Symphonie n° 6, IV, Musopen (domaine public) ; Berlioz, *Symphonie fantastique*, SWR Symphonieorchester (ARD Klassik) ; *The Centenary of Pierre Boulez*, Ensemble intercontemporain & Friends (ARTE Concert)',
      'Website in progress · music: Tchaikovsky, Symphony No. 6, IV, Musopen (public domain); Berlioz, *Symphonie fantastique*, SWR Symphonieorchester (ARD Klassik); *The Centenary of Pierre Boulez*, Ensemble intercontemporain & Friends (ARTE Concert)'],
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
    if (typeof champ === 'string' || typeof champ === 'number') return String(champ);
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

  /* Lien sortant : toujours dans un nouvel onglet. */
  function lienSortant(url, contenuHtml, classe) {
    return '<a' + (classe ? ' class="' + classe + '"' : '') + ' href="' + echapper(url) + '" target="_blank" rel="noopener">' + contenuHtml + '</a>';
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

    /* Pages rédigées en entier dans les deux langues (mentions légales, confidentialité,
       crédits) : un bloc par langue, seul celui de la langue courante reste affiché. */
    racine.querySelectorAll('[data-langue-bloc]').forEach(function (n) {
      n.hidden = (n.getAttribute('data-langue-bloc') !== langue);
    });

    /* Titre de la page : <html data-titre="clé"> sur les pages secondaires. */
    var cleTitre = racineDoc.getAttribute('data-titre');
    document.title = cleTitre && T[cleTitre] ? brut(t(cleTitre)) + ' · Pierre Bleuse' : brut(t('meta.titre'));
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
    lienSortant: lienSortant,
    appliquer: appliquer,
    liensSortants: liensSortants,
    allerAncre: allerAncre,
    ecarts: ecarts
  };
})();
