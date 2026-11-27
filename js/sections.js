/* ==========================================================================
   sections.js · rendu des sections pilotées par des données

   Chargé après contenu.js et les fichiers de data/, avant le moteur de la nuée
   (rendu synchrone : main.js recense les [data-revele] à son chargement).

     visuels    data/visuels.js     [data-visuel]      blocs à médias : image (telle quelle ou animée par un
                                                       module du registre js/animations.js), une ou
                                                       plusieurs vidéos (fichier, adresse, YouTube)
     disques    data/disques.js     [data-disques]     fiches au format de l'agenda
     presse     data/presse.js      [data-presse]      quatre articles ; [data-articles] : le tableau de presse.html
     parcours   data/orchestres.js  [data-orchestres]  liste des orchestres
                data/cartes.js      [data-cartes]      carte du monde, points liés à la liste
                data/parcours.js    [data-parcours-liste]   fonctions, opéra et création
     téléchargements  data/telechargements.js  [data-telechargements]  un fichier, ou « à venir »
     contacts   data/contacts.js    [data-contacts]    groupes de contacts
     réseaux    data/reseaux.js     [data-reseaux]     adresses des réseaux (barre du haut, « Restons en contact »)

   Chaque rendu remplace le repli statique du HTML (français, sans JavaScript),
   que Pupitre réécrit à chaque publication entre deux repères (…-STATIQUE:DEBUT
   et …-STATIQUE:FIN). Pour les listes du parcours, les téléchargements, les
   contacts et les réseaux, le repli et le rendu ont la même forme : en français,
   une différence est consignée dans SiteContenu.ecarts.
   Un champ vide ne s'affiche pas : rien n'est inventé ici.
   ========================================================================== */
(function () {
  'use strict';

  var C = window.SiteContenu;
  if (!C) return;
  var h = C.html;
  var sortant = C.lienSortant;
  var racineDoc = document.documentElement;

  /* ---------- Blocs à médias (blocs visuels, fond de « Restons en contact ») ---------- */
  /* data/visuels.js : { "<clé du bloc>": { elements: [ … ] } }. Un bloc montre une image (telle
     quelle, ou animée par un module du registre js/animations.js), ou une ou plusieurs vidéos
     (fichier, adresse directe, YouTube) que le visiteur parcourt une à une. Un seul lecteur vidéo
     par bloc : une seule vidéo est chargée à la fois.

     Règle des blocs visuels : la nuée n'y est pas visible. La section est opaque (fond-100) et
     passe devant la nuée (nuee-arriere-plan), qui continue de voler derrière et reparaît d'elle-même
     à la section suivante. Cela vaut pour toute section [data-visuel], quel que soit son identifiant. */

  var telephone = window.matchMedia ? window.matchMedia('(max-width: 720px)') : null;
  var blocsVideo = {};        /* identifiant de la section → { section, elements } : lus par js/main.js */

  /* « 60% 35% » → [0.6, 0.35] : point de cadrage transmis au module d'animation */
  function focal(position) {
    var m = /^\s*([\d.]+)%\s+([\d.]+)%\s*$/.exec(position || '');
    return m ? [Math.min(1, +m[1] / 100), Math.min(1, +m[2] / 100)] : null;
  }
  function idYouTube(v) {
    if (v.id) return String(v.id);
    var m = /(?:v=|youtu\.be\/|embed\/|shorts\/|live\/)([\w-]{6,})/.exec(v.url || '');
    return m ? m[1] : '';
  }
  /* Vidéo YouTube : rien n'est chargé avant l'accord du visiteur. Au clic, la section le fait savoir
     (événement pb:youtube) : js/main.js suspend la musique. */
  function mediaYouTube(v, section) {
    var boite = document.createElement('div');
    boite.className = 'visuel__yt';
    var id = idYouTube(v);
    boite.innerHTML = '<div class="visuel__consentement"><p>' + h(C.t('yt.texte')) + '</p>' +
      '<button type="button" class="bouton">' + h(C.t('yt.bouton')) + '</button></div>';
    var bouton = boite.querySelector('button');
    if (!id) { bouton.disabled = true; return boite; }
    bouton.addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
      f.title = C.brut(C.choisir(v.titre)) || 'YouTube';
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.setAttribute('allowfullscreen', '');
      boite.textContent = '';
      boite.appendChild(f);
      try { section.dispatchEvent(new CustomEvent('pb:youtube', { bubbles: true })); } catch (e) { /* sans importance */ }
    });
    return boite;
  }
  function cadrer(n, v) {
    var poser = function () { n.style.objectPosition = (telephone && telephone.matches && v.positionMobile) || v.position || ''; };
    poser();
    if (telephone && telephone.addEventListener) telephone.addEventListener('change', poser);
  }
  /* Le média d'un élément : <img>, <video> (jamais lancée ici : js/main.js la conduit) ou la boîte YouTube. */
  function mediaElement(v, section) {
    var n = null;
    if (v.type === 'image' && v.src) {
      n = document.createElement('img');
      n.src = v.src;
      n.alt = C.brut(C.choisir(v.alt));
      n.decoding = 'async';
      cadrer(n, v);
    } else if (v.type === 'video' && v.src) {
      n = document.createElement('video');
      n.muted = true; n.defaultMuted = true; n.playsInline = true;
      n.setAttribute('muted', ''); n.setAttribute('playsinline', '');
      n.setAttribute('disablepictureinpicture', '');
      n.setAttribute('data-video-bloc', '');
      n.preload = 'metadata';
      poserVideo(n, v);
    } else if (v.type === 'youtube') {
      n = mediaYouTube(v, section);
    }
    if (n) n.classList.add('visuel__media');
    return n;
  }
  /* Charge une vidéo dans le lecteur du bloc : source, image d'attente, cadrage, libellé. */
  function poserVideo(n, v) {
    if (v.affiche) n.poster = v.affiche; else n.removeAttribute('poster');
    if (n.getAttribute('src') !== v.src) n.src = v.src;
    if (v.son) { n.setAttribute('data-video-son', ''); } else n.removeAttribute('data-video-son');
    if (v.son && v.piste) n.setAttribute('data-piste', v.piste); else n.removeAttribute('data-piste');
    var libelle = C.brut(C.choisir(v.alt)) || C.brut(C.choisir(v.titre));
    if (libelle) { n.setAttribute('aria-label', libelle); n.removeAttribute('aria-hidden'); }
    else { n.removeAttribute('aria-label'); n.setAttribute('aria-hidden', 'true'); }
    cadrer(n, v);
  }
  /* Commandes d'un bloc vidéo, en bas à gauche, loin du bouton Scroll (au centre) : lecture / pause ;
     s'il y a plusieurs vidéos, précédente, position, suivante ; titre et description s'ils existent.
     js/main.js leur donne vie. */
  function commandesVideo(elements) {
    var plusieurs = elements.length > 1;
    var fleche = function (d) { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; };
    var boite = document.createElement('div');
    boite.className = 'visuel__commandes';
    boite.setAttribute('data-video-commandes', '');
    boite.innerHTML =
      '<p class="visuel__legende" data-video-legende hidden></p>' +
      '<div class="visuel__boutons">' +
        '<button type="button" class="visuel__bouton" data-video-lecture data-etat="arret" aria-label="' + C.echapper(C.brut(C.t('video.lire'))) + '"><span class="ecoute__icone" aria-hidden="true"></span></button>' +
        (plusieurs
          ? '<button type="button" class="visuel__bouton visuel__bouton--fleche" data-video-precedente aria-label="' + C.echapper(C.brut(C.t('video.precedente'))) + '">' + fleche('M14.5 6.5L9 12l5.5 5.5') + '</button>' +
            '<span class="visuel__position" data-video-position></span>' +
            '<button type="button" class="visuel__bouton visuel__bouton--fleche" data-video-suivante aria-label="' + C.echapper(C.brut(C.t('video.suivante'))) + '">' + fleche('M9.5 6.5L15 12l-5.5 5.5') + '</button>'
          : '') +
      '</div>';
    return boite;
  }
  function rendreVisuels() {
    var V = window.VISUELS || {};
    Array.prototype.forEach.call(document.querySelectorAll('[data-visuel]'), function (section) {
      var bloc = V[section.getAttribute('data-visuel')];
      var elements = (bloc && Array.isArray(bloc.elements)) ? bloc.elements.filter(function (e) { return e && e.type; }) : [];
      var plein = !section.querySelector('.visuel__contenu');       /* bloc visuel en pleine page, sans texte */
      var ancien = section.querySelector('.visuel__media');
      if (!elements.length) {
        /* rien à montrer : un bloc visuel vide quitte la page (pas d'écran noir) ; un fond de bloc
           de texte s'efface seul */
        if (plein) { if (section.parentNode) section.parentNode.removeChild(section); }
        else if (ancien) ancien.parentNode.removeChild(ancien);
        return;
      }
      var v = elements[0];
      var n = mediaElement(v, section);
      if (!n) return;
      ['image', 'video', 'youtube'].forEach(function (t) { section.classList.toggle('visuel--' + t, t === v.type); });
      /* image animée : js/main.js crée la scène par le registre (js/animations.js) sur [data-animation] */
      var animation = (v.type === 'image' && v.animation && v.animation.module) ? v.animation : null;
      section.classList.remove('lumiere-prete', 'lumiere-echec', 'lumiere-perdue');
      section.classList.toggle('visuel--anime', !!animation);
      section.classList.toggle('visuel--lumiere', !!animation && animation.module === 'lumiere');
      if (animation) {
        section.setAttribute('data-animation', String(animation.module));
        section.setAttribute('data-animation-reglages', JSON.stringify(animation));
        if (animation.module === 'lumiere') section.setAttribute('data-lumiere', String(animation.preset || 'generique'));
        var f = focal(v.position);
        if (f) section.setAttribute('data-focal', f.join(',')); else section.removeAttribute('data-focal');
      } else {
        ['data-animation', 'data-animation-reglages', 'data-lumiere', 'data-focal'].forEach(function (a) { section.removeAttribute(a); });
      }
      if (plein) section.setAttribute('data-bloc-visuel', '');
      if (ancien) ancien.parentNode.replaceChild(n, ancien); else section.insertBefore(n, section.firstChild);
      /* une ou plusieurs vidéos : un lecteur, ses commandes ; js/main.js le conduit */
      var videosBloc = elements.filter(function (e) { return e.type === 'video' || e.type === 'youtube'; });
      if (plein && (v.type === 'video' || v.type === 'youtube') && videosBloc.length) {
        if (!section.id) section.id = 'bloc-' + section.getAttribute('data-visuel');
        var vieilles = section.querySelector('[data-video-commandes]');
        if (vieilles) vieilles.parentNode.removeChild(vieilles);
        section.appendChild(commandesVideo(videosBloc));
        section.setAttribute('data-video-section', '');
        blocsVideo[section.id] = { section: section, elements: videosBloc, poserVideo: poserVideo, mediaYouTube: mediaYouTube };
      }
    });
  }

  /* ---------- Outils communs aux albums et aux articles ---------------------- */

  function lignes(champ) {
    var l = Array.isArray(champ) ? champ : [champ];
    return l.map(C.choisir).filter(Boolean);
  }
  /* Texte en paragraphes : séparés par une ligne vide ; un retour simple reste un retour. */
  function paragraphes(texte, classe) {
    return String(texte || '').split(/\n\s*\n/).map(function (p) { return p.trim(); }).filter(Boolean)
      .map(function (p) { return '<p class="' + classe + '">' + h(p).replace(/\n/g, '<br>') + '</p>'; }).join('');
  }
  var formatsDate = {};
  /* « 13 février 2026 », « juin 2024 », « 2024 » : selon ce que dit la date (AAAA-MM-JJ, AAAA-MM, AAAA). */
  function ecrireDate(iso) {
    var m = /^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/.exec(iso || '');
    if (!m) return '';
    if (!m[2]) return m[1];
    var avecJour = !!m[3];
    var cle = avecJour ? 'j' : 'm';
    if (!formatsDate[cle]) {
      formatsDate[cle] = new Intl.DateTimeFormat(C.locale, avecJour
        ? { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }
        : { month: 'long', year: 'numeric', timeZone: 'UTC' });
    }
    var s = formatsDate[cle].format(new Date(Date.UTC(+m[1], +m[2] - 1, avecJour ? +m[3] : 1)));
    return C.langue === 'fr' ? s.replace(/^1 /, '1er ') : s;
  }
  function parId(liste) {
    var index = {};
    (Array.isArray(liste) ? liste : []).forEach(function (x) { if (x && x.id) index[x.id] = x; });
    return index;
  }

  /* ---------- Panneaux détaillés (albums, articles) -------------------------- */
  /* Un accordéon, pas une fenêtre modale : « En savoir plus » déplie un panneau sous la rangée de
     la vignette, sur toute la largeur de la grille ; fermé, il n'existe pas dans la page. Un seul
     panneau ouvert à la fois. Il ne dépasse pas la hauteur de l'écran : au-delà, son contenu
     défile à l'intérieur (data-lenis-prevent : le défilement de la page ne s'en mêle pas), et le
     bouton de fermeture reste en place. Échap le referme et rend le focus au bouton d'origine.
     Le panneau vit dans sa section, devant la nuée comme elle. */
  var panneau = null;                 /* { noeud, grille, carte, bouton } */

  function placerPanneau(p) {
    /* après la dernière vignette de la rangée de la vignette ouverte */
    if (p.noeud.parentNode) p.noeud.parentNode.removeChild(p.noeud);
    var haut = p.carte.offsetTop, derniere = p.carte;
    Array.prototype.forEach.call(p.grille.children, function (c) {
      if (c !== p.noeud && Math.abs(c.offsetTop - haut) < 6) derniere = c;
    });
    derniere.insertAdjacentElement('afterend', p.noeud);
  }
  function fermerPanneau(rendreFocus) {
    var p = panneau;
    if (!p) return;
    panneau = null;
    if (p.noeud.parentNode) p.noeud.parentNode.removeChild(p.noeud);
    p.carte.classList.remove('est-ouverte');
    p.bouton.setAttribute('aria-expanded', 'false');
    if (rendreFocus) { try { p.bouton.focus({ preventScroll: true }); } catch (e) { p.bouton.focus(); } }
  }
  /* Déplie le panneau d'une vignette (ou le replie s'il l'est déjà). contenu : son HTML. */
  function basculerPanneau(grille, carte, bouton, contenu, libelle) {
    if (panneau && panneau.carte === carte) { fermerPanneau(true); return; }
    fermerPanneau(false);
    var n = document.createElement('div');
    n.className = 'panneau';
    n.setAttribute('role', 'region');
    n.setAttribute('aria-label', libelle);
    n.tabIndex = -1;
    n.innerHTML = '<button type="button" class="panneau__fermer" data-panneau-fermer aria-label="' + C.echapper(C.brut(C.t('panneau.fermer'))) + '">' +
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 6.5l11 11M17.5 6.5l-11 11" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></button>' +
      '<div class="panneau__corps" data-lenis-prevent>' + contenu + '</div>';
    panneau = { noeud: n, grille: grille, carte: carte, bouton: bouton };
    placerPanneau(panneau);
    carte.classList.add('est-ouverte');
    bouton.setAttribute('aria-expanded', 'true');
    installerDiaporamas(n);
    C.liensSortants(n);
    try { n.focus({ preventScroll: true }); } catch (e) { n.focus(); }
    /* le panneau vient à l'écran, sous la barre du haut */
    var r = n.getBoundingClientRect();
    var barre = parseFloat(getComputedStyle(racineDoc).getPropertyValue('--barre-h')) * 16 || 107;
    if (r.top < barre + 8 || r.bottom > window.innerHeight) {
      var y = window.pageYOffset + r.top - Math.max(barre + 16, Math.min(window.innerHeight * 0.3, window.innerHeight - r.height - 16));
      try { window.dispatchEvent(new CustomEvent('pb:trajet', { detail: { y: y, duree: 0.9 } })); } catch (e) { /* sans importance */ }
      var lenis = window.__h4 && window.__h4.lenis;
      if (lenis && typeof lenis.scrollTo === 'function') lenis.scrollTo(Math.max(0, y), { duration: 0.9, force: true });
      else window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
    }
  }
  document.addEventListener('click', function (e) {
    if (e.target && e.target.closest && e.target.closest('[data-panneau-fermer]')) fermerPanneau(true);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && panneau && !e.defaultPrevented) { e.preventDefault(); fermerPanneau(true); }
  });
  var replacement = 0;
  window.addEventListener('resize', function () {
    if (!panneau) return;
    window.clearTimeout(replacement);
    replacement = window.setTimeout(function () { if (panneau) placerPanneau(panneau); }, 120);
  });

  /* ---------- Petit diaporama (visuels d'un album, d'un article) ------------ */
  /* De dimensions réduites, à sa place dans le panneau : une image à la fois, deux flèches et une
     position quand il y en a plusieurs. Ni plein écran, ni galerie. Une seule image est chargée. */
  function diaporama(visuels) {
    visuels = (visuels || []).filter(function (v) { return v && v.src; });
    if (!visuels.length) return '';
    var v = visuels[0];
    var fleche = function (d) { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; };
    var s = '<figure class="diapo" data-diapo data-visuels="' + C.echapper(JSON.stringify(visuels.map(function (x) { return { src: x.src, alt: C.brut(C.choisir(x.alt)), credit: x.credit || '' }; }))) + '">' +
      '<img class="diapo__image" src="' + C.echapper(v.src) + '" alt="' + C.echapper(C.brut(C.choisir(v.alt))) + '" loading="lazy" decoding="async">';
    if (visuels.length > 1) {
      s += '<div class="diapo__commandes">' +
        '<button type="button" class="diapo__bouton" data-diapo-vers="-1" aria-label="' + C.echapper(C.brut(C.t('disques.image.precedente'))) + '">' + fleche('M14.5 6.5L9 12l5.5 5.5') + '</button>' +
        '<span class="diapo__position" data-diapo-position>1 / ' + visuels.length + '</span>' +
        '<button type="button" class="diapo__bouton" data-diapo-vers="1" aria-label="' + C.echapper(C.brut(C.t('disques.image.suivante'))) + '">' + fleche('M9.5 6.5L15 12l-5.5 5.5') + '</button></div>';
    }
    return s + '<figcaption class="diapo__credit" data-diapo-credit' + (v.credit ? '' : ' hidden') + '>' + h(v.credit || '') + '</figcaption></figure>';
  }
  function installerDiaporamas(racine) {
    Array.prototype.forEach.call(racine.querySelectorAll('[data-diapo]'), function (boite) {
      var visuels = JSON.parse(boite.getAttribute('data-visuels') || '[]');
      var i = 0;
      var image = boite.querySelector('.diapo__image'), position = boite.querySelector('[data-diapo-position]'), credit = boite.querySelector('[data-diapo-credit]');
      boite.addEventListener('click', function (e) {
        var b = e.target && e.target.closest ? e.target.closest('[data-diapo-vers]') : null;
        if (!b || visuels.length < 2) return;
        i = (i + Number(b.getAttribute('data-diapo-vers')) + visuels.length) % visuels.length;
        image.src = visuels[i].src;
        image.alt = visuels[i].alt || '';
        if (position) { position.textContent = (i + 1) + ' / ' + visuels.length; position.setAttribute('aria-label', C.brut(C.t('disques.image', { n: i + 1, total: visuels.length }))); }
        if (credit) { credit.textContent = visuels[i].credit || ''; credit.hidden = !visuels[i].credit; }
      });
    });
  }

  /* ---------- Plateformes d'écoute, liens de commande ------------------------ */
  /* Pictogrammes des plateformes : l'intérieur d'un <svg viewBox="0 0 24 24">. La clé est celle de
     Pupitre (inclus/discographie.php, PLATEFORMES). Une plateforme sans pictogramme à elle reçoit
     celui de sa zone : la note pour « Écouter », le sac pour « Commander ». Pour en ajouter une :
     une ligne ici, une ligne dans Pupitre. */
  var TRAIT = ' fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';
  var PICTOS_PLATEFORME = {
    'spotify': '<circle cx="12" cy="12" r="9.2"' + TRAIT + '/><path d="M7.4 9.6c3.2-.9 6.6-.6 9.4.9M8 12.6c2.6-.7 5.3-.5 7.6.8M8.7 15.4c1.9-.5 3.8-.3 5.5.6"' + TRAIT + '/>',
    'apple-music': '<rect x="3.2" y="3.2" width="17.6" height="17.6" rx="4.6"' + TRAIT + '/><path d="M10.2 15.8V8.6l5.6-1.2v6.9"' + TRAIT + '/><ellipse cx="8.8" cy="15.9" rx="1.5" ry="1.2" fill="currentColor"/><ellipse cx="14.4" cy="14.4" rx="1.5" ry="1.2" fill="currentColor"/>',
    'deezer': '<path d="M4.5 18.5v-3M8.25 18.5v-6M12 18.5v-9M15.75 18.5v-6M19.5 18.5V6"' + TRAIT + ' stroke-width="2.4"/>',
    'youtube': '<path fill="currentColor" fill-rule="evenodd" d="M21.6 7.2a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.84.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.84-.43a2.5 2.5 0 0 0 1.76-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3-5.2 3z"/>',
    'qobuz': '<circle cx="11.4" cy="11.4" r="7.6"' + TRAIT + '/><circle cx="11.4" cy="11.4" r="2.1" fill="currentColor"/><path d="M16.2 16.2l4 4"' + TRAIT + '/>',
    'tidal': '<path fill="currentColor" d="M4 9.5L7.5 6 11 9.5 7.5 13zM11 9.5L14.5 6 18 9.5 14.5 13zM18 9.5L21.5 6 25 9.5 21.5 13zM11 16.5L14.5 13 18 16.5 14.5 20z" transform="translate(-2.5 -1)"/>',
    'note': '<ellipse cx="9" cy="17" rx="3.2" ry="2.4" fill="currentColor" transform="rotate(-20 9 17)"/><path d="M12 16.4V4.6c.4 2.6 2.2 3.6 3.6 4.7 1.5 1.2 2.1 3 1.3 5.1.4-1.9-.6-3.2-2-4-.9-.5-1.9-.8-2.9-1"' + TRAIT + '/>',
    'sac': '<path d="M5.5 8.5h13l-1 11h-11z"' + TRAIT + '/><path d="M9 8.5V7a3 3 0 0 1 6 0v1.5"' + TRAIT + '/>'
  };
  function liensPlateformes(cleTitre, liste, defaut) {
    liste = (liste || []).filter(function (l) { return l && l.url; });        /* jamais de pictogramme inactif */
    if (!liste.length) return '';
    return '<div class="album__zone"><p class="fiche__label">' + h(C.t(cleTitre)) + '</p><ul class="plateformes">' +
      liste.map(function (l) {
        var picto = PICTOS_PLATEFORME[l.plateforme] || PICTOS_PLATEFORME[defaut];
        return '<li>' + sortant(l.url, '<svg viewBox="0 0 24 24" aria-hidden="true">' + picto + '</svg><span>' + h(l.nom || l.url) + '</span>', 'plateforme') + '</li>';
      }).join('') + '</ul></div>';
  }

  /* ---------- Citations de presse -------------------------------------------- */
  /* Une citation reste dans sa langue et sa formulation d'origine. Sa traduction, quand elle
     existe pour la langue de la page, vient dessous, annoncée comme telle : jamais à sa place. */
  function citationPrincipale(a) {
    var l = a.citations || [];
    for (var i = 0; i < l.length; i++) if (l[i].principale) return l[i];
    return l[0] || null;
  }
  function citation(c, classe, avecTraduction) {
    if (!c || !c.texte) return '';
    var s = '<blockquote class="' + classe + '" lang="' + C.echapper(c.langue || C.langue) + '">' + h(c.texte) + '</blockquote>';
    var tr = (avecTraduction && c.traduction && c.langue !== C.langue) ? c.traduction[C.langue] : '';
    if (tr) s += '<p class="article__traduction"><span class="article__traduction-mention">' + h(C.t('presse.traduction')) + '</span> ' + h(tr) + '</p>';
    return s;
  }
  /* Le média d'un article : son logo s'il en a un (le nom n'est alors pas répété à l'écran, il
     reste lu : texte alternatif), sinon son nom, dans le caractère choisi dans Pupitre. */
  function media(a) {
    if (a.logo) return '<p class="article__media"><img class="article__logo" src="' + C.echapper(a.logo) + '" alt="' + C.echapper(a.media || '') + '" decoding="async"></p>';
    return '<p class="article__media"><span class="article__nom' + (a.typo ? ' article__nom--' + C.echapper(a.typo) : '') + '">' + h(a.media || '') + '</span></p>';
  }

  /* ---------- Disques ----------------------------------------------------- */

  /* Vignette d'un album, sobre : année et label, titre, descriptif, distinctions mises en avant,
     puis « En savoir plus », qui déplie son panneau. */
  function vignetteAlbum(d) {
    var s = '<article class="fiche bloc-quart disque" data-revele id="d-' + C.echapper(d.id) + '">';
    var tete = [d.annee, C.choisir(d.maison)].filter(Boolean);                 /* année, maison de disques */
    if (tete.length) s += '<p class="fiche__dates">' + tete.map(h).join(' · ') + '</p>';
    s += '<h3 class="fiche__titre">' + h(C.choisir(d.titre)) + '</h3>';
    lignes(d.descriptif).forEach(function (l) { s += '<p class="fiche__texte">' + h(l) + '</p>'; });
    var unes = (d.distinctions || []).filter(function (x) { return x.une; }).map(function (x) { return C.choisir(x.intitule); }).filter(Boolean);
    if (unes.length) s += '<p class="fiche__texte fiche__distinctions">' + h(unes.join(', ')) + '</p>';
    s += '<button type="button" class="lien fiche__plus" data-album="' + C.echapper(d.id) + '" aria-expanded="false">' + h(C.t('disques.plus')) + '</button>';
    return s + '</article>';
  }
  /* Panneau d'un album : seulement ce qui est renseigné, aucune zone vide. */
  function panneauAlbum(d) {
    var articles = parId(window.PRESSE);
    var s = '<div class="album">';
    s += '<div class="album__tete">' + diaporama(d.visuels) + '<div class="album__identite">';
    s += '<h3 class="album__titre">' + h(C.choisir(d.titre)) + '</h3>';
    var sous = C.choisir(d.sousTitre);
    if (sous) s += '<p class="album__sous-titre">' + h(sous) + '</p>';
    var meta = [C.choisir(d.maison), d.sortie ? C.t('disques.sortie') + ' ' + ecrireDate(d.sortie) : (d.annee ? String(d.annee) : '')].filter(Boolean);
    if (meta.length) s += '<p class="fiche__dates">' + meta.map(h).join(' · ') + '</p>';
    if (d.ensemble) s += '<p class="fiche__ensemble">' + h(d.ensemble) + '</p>';
    if (d.compositeurs && d.compositeurs.length) s += '<p class="fiche__label">' + h(C.t('disques.compositeurs')) + '</p><p class="album__texte">' + d.compositeurs.map(h).join(', ') + '</p>';
    s += '</div></div>';
    /* deux colonnes : les textes, puis les distinctions, la critique et les liens. Une colonne vide ne garde pas sa place. */
    var tete = s;
    s = '';
    var presentation = C.choisir(d.presentation);
    if (presentation) s += '<p class="fiche__label">' + h(C.t('disques.presentation')) + '</p>' + paragraphes(presentation, 'album__texte');
    var contexte = C.choisir(d.contexte);
    if (contexte) s += '<p class="fiche__label">' + h(C.t('disques.contexte')) + '</p>' + paragraphes(contexte, 'album__texte');
    /* les notes personnelles de l'artiste, distinctes de la présentation de la rédaction */
    var mot = C.choisir(d.mot);
    var paroles = lignes(d.citations || []);
    if (mot || paroles.length) {
      s += '<div class="album__mot"><p class="fiche__label">' + h(C.t('disques.mot')) + '</p>' + paragraphes(mot, 'fiche__mot') +
        paroles.map(function (c) { return '<blockquote class="album__parole">' + h(c) + '</blockquote>'; }).join('') + '</div>';
    }
    var oeuvres = lignes(d.oeuvres || []);
    if (oeuvres.length) s += '<p class="fiche__label">' + h(C.t('disques.oeuvres')) + '</p><ul class="fiche__liste">' + oeuvres.map(function (o) { return '<li>' + h(o) + '</li>'; }).join('') + '</ul>';
    var infos = C.choisir(d.infos);
    if (infos) s += '<p class="fiche__label">' + h(C.t('disques.infos')) + '</p>' + paragraphes(infos, 'album__texte');
    var interpretes = lignes(d.interpretes || []);
    if (interpretes.length) s += '<p class="fiche__label">' + h(C.t('disques.interpretes')) + '</p><ul class="fiche__liste">' + interpretes.map(function (o) { return '<li>' + h(o) + '</li>'; }).join('') + '</ul>';
    var gauche = s;
    s = '';
    /* distinctions : une présentation sobre */
    if (d.distinctions && d.distinctions.length) {
      s += '<p class="fiche__label">' + h(C.t('disques.distinctions')) + '</p><ul class="distinctions">';
      d.distinctions.forEach(function (x) {
        var intitule = h(C.choisir(x.intitule));
        var detail = [x.organisme, x.annee].filter(Boolean).map(h).join(', ');
        var description = C.choisir(x.description);
        s += '<li class="distinction' + (x.une ? ' distinction--une' : '') + (x.logo ? ' distinction--logo' : '') + '">' +
          (x.logo ? '<img class="distinction__logo" src="' + C.echapper(x.logo) + '" alt="" decoding="async" loading="lazy">' : '') +
          '<span class="distinction__intitule">' + (x.url ? sortant(x.url, intitule) : intitule) + '</span>' +
          (detail ? '<span class="distinction__detail">' + detail + '</span>' : '') +
          (description ? '<span class="distinction__description">' + h(description) + '</span>' : '') + '</li>';
      });
      s += '</ul>';
    }
    /* la critique : les articles publiés de la revue de presse associés à l'album */
    var critiques = (d.articles || []).map(function (id) { return articles[id]; }).filter(Boolean);
    if (critiques.length) {
      s += '<p class="fiche__label">' + h(C.t('disques.critiques')) + '</p><ul class="critiques">';
      critiques.forEach(function (a) {
        var c = citationPrincipale(a);
        var signature = [a.media, a.auteur, ecrireDate(a.date)].filter(Boolean).map(h).join(' · ');
        var resume = C.choisir(a.chapeau) || C.choisir(a.resume);
        s += '<li class="critique">' + (c ? citation(c, 'critique__citation', true) : '<p class="critique__titre">' + h(C.choisir(a.titre)) + '</p>') +
          '<p class="critique__signature">' + signature + '</p>' +
          (resume && c ? '<p class="critique__resume">' + h(resume) + '</p>' : '') +
          '<p class="critique__liens"><a class="lien" href="presse.html#a-' + encodeURIComponent(a.id) + '">' + h(C.t('presse.plus')) + '</a>' +
          (a.lien ? sortant(a.lien, h(C.t('presse.lire')), 'lien') : '') + '</p></li>';
      });
      s += '</ul>';
    }
    s += liensPlateformes('disques.streaming', d.ecouter, 'note') + liensPlateformes('disques.commander', d.commander, 'sac');
    if (d.lien) s += '<p class="album__page">' + sortant(d.lien, h(C.t('disques.page')), 'lien') + '</p>';
    var droite = s;
    var colonnes = [gauche, droite].filter(Boolean);
    return tete + (colonnes.length ? '<div class="album__colonnes' + (colonnes.length === 1 ? ' album__colonnes--une' : '') + '">' +
      colonnes.map(function (c) { return '<div class="album__colonne">' + c + '</div>'; }).join('') + '</div>' : '') + '</div>';
  }
  function rendreDisques() {
    var zone = document.querySelector('[data-disques]');
    var D = window.DISQUES;
    if (!zone || !Array.isArray(D)) return;
    var albums = parId(D);
    zone.innerHTML = D.map(vignetteAlbum).join('');
    zone.addEventListener('click', function (e) {
      var b = e.target && e.target.closest ? e.target.closest('[data-album]') : null;
      var d = b ? albums[b.getAttribute('data-album')] : null;
      if (d) basculerPanneau(zone, b.closest('.fiche'), b, panneauAlbum(d), C.brut(C.choisir(d.titre)));
    });
    /* adresse …#d-<id> : le panneau de cet album s'ouvre (lien venu d'un article) */
    function ouvrirAncre() {
      var m = /^#d-(.+)$/.exec(window.location.hash || '');
      var d = m ? albums[decodeURIComponent(m[1])] : null;
      var carte = d ? document.getElementById('d-' + d.id) : null;
      if (!carte || (panneau && panneau.carte === carte)) return;
      var b = carte.querySelector('[data-album]');
      window.setTimeout(function () { basculerPanneau(zone, carte, b, panneauAlbum(d), C.brut(C.choisir(d.titre))); }, 400);
    }
    window.addEventListener('hashchange', ouvrirAncre);
    ouvrirAncre();
  }

  /* ---------- Presse ------------------------------------------------------ */

  /* Articles de l'accueil : ceux que Pupitre a sélectionnés, dans leur rang. */
  function articlesAccueil(A) {
    return A.filter(function (a) { return a.accueil; }).sort(function (x, y) {
      return ((x.rang || 0) - (y.rang || 0)) || String(x.id).localeCompare(String(y.id));
    });
  }
  /* Vignette d'un article, dans l'ordre demandé : la citation (ou, faute de citation, le titre),
     le média (logo ou nom, jamais les deux), le chapeau ou le résumé, la date, « En savoir plus »,
     « Lire l'article ». Sa largeur (1, 2 ou 4 colonnes) vient de Pupitre. Un champ vide ne
     s'affiche pas. */
  function article(a) {
    var largeur = [1, 2, 4].indexOf(a.largeur) >= 0 ? a.largeur : 1;
    var s = '<article class="fiche article article--l' + largeur + '" data-revele id="a-' + C.echapper(a.id) + '">';
    var c = citationPrincipale(a);
    s += c ? citation(c, 'article__verbatim', true) : '';
    s += media(a);
    var texte = C.choisir(a.chapeau) || C.choisir(a.resume);
    if (!texte && !c) texte = C.choisir(a.titre);              /* ni citation ni résumé : le titre tient lieu de texte */
    if (texte) s += '<p class="article__resume">' + h(texte) + '</p>';
    var date = ecrireDate(a.date);
    if (date) s += '<p class="article__date"><span>' + h(date) + '</span></p>';
    s += '<p class="article__liens"><button type="button" class="lien" data-article="' + C.echapper(a.id) + '" aria-expanded="false">' + h(C.t('presse.plus')) + '</button>' +
      (a.lien ? sortant(a.lien, h(C.t('presse.lire')), 'lien') : '') + '</p>';
    return s + '</article>';
  }
  /* Détail d'un article : le même contenu dans le panneau de l'accueil et dans la ligne dépliée de
     presse.html. Seulement ce qui est renseigné. */
  function detailArticle(a) {
    var concerts = parId(window.AGENDA_DONNEES && window.AGENDA_DONNEES.evenements);
    var albums = parId(window.DISQUES);
    var surAccueil = !!document.querySelector('[data-disques]');
    var s = '<div class="article-detail"><div class="article-detail__colonne">';
    var type = a.type ? C.t('presse.type.' + a.type) : '';
    if (type) s += '<p class="etiquette">' + h(type) + '</p>';
    var titre = C.choisir(a.titre);
    if (titre) s += '<h3 class="article-detail__titre">' + h(titre) + '</h3>';
    var signature = [a.media, a.auteur ? C.t('presse.auteur', { auteur: a.auteur }) : '', ecrireDate(a.date)].filter(Boolean).map(h).join(' · ');
    if (signature) s += '<p class="article__date">' + signature + '</p>';
    var c = citationPrincipale(a);
    s += citation(c, 'article__verbatim', true);
    var autres = (a.citations || []).filter(function (x) { return x !== c && x.texte; });
    if (autres.length) {
      s += '<p class="fiche__label">' + h(C.t('presse.citations')) + '</p>' + autres.map(function (x) { return citation(x, 'article-detail__citation', true); }).join('');
    }
    s += '</div><div class="article-detail__colonne">';
    s += diaporama(a.visuels);
    var chapeau = C.choisir(a.chapeau), resume = C.choisir(a.resume), infos = C.choisir(a.infos);
    if (chapeau) s += '<p class="article-detail__chapeau">' + h(chapeau) + '</p>';
    if (resume) s += paragraphes(resume, 'album__texte');
    if (infos) s += paragraphes(infos, 'album__texte');
    /* objets associés, s'ils sont publiés : les concerts mènent à leur fiche, les albums à leur panneau */
    var lies = (a.concerts || []).map(function (id) { return concerts[id]; }).filter(Boolean);
    if (lies.length) {
      s += '<p class="fiche__label">' + h(C.t('presse.concerts')) + '</p><ul class="fiche__liste">' +
        lies.map(function (e) { return '<li><a href="concerts.html#c-' + encodeURIComponent(e.id) + '">' + h(C.choisir(e.titre)) + '</a></li>'; }).join('') + '</ul>';
    }
    lies = (a.albums || []).map(function (id) { return albums[id]; }).filter(Boolean);
    if (lies.length) {
      s += '<p class="fiche__label">' + h(C.t('presse.albums')) + '</p><ul class="fiche__liste">' +
        lies.map(function (d) { return '<li><a href="' + (surAccueil ? '' : './') + '#d-' + encodeURIComponent(d.id) + '">' + h(C.choisir(d.titre)) + '</a></li>'; }).join('') + '</ul>';
    }
    if (a.lien) s += '<p class="article-detail__lire">' + sortant(a.lien, h(C.t('presse.lire')), 'lien') + '</p>';
    return s + '</div></div>';
  }
  function rendrePresse() {
    var zone = document.querySelector('[data-presse]');
    var A = window.PRESSE;
    if (!zone || !Array.isArray(A)) return;
    var articles = parId(A);
    zone.innerHTML = articlesAccueil(A).map(article).join('');
    zone.addEventListener('click', function (e) {
      var b = e.target && e.target.closest ? e.target.closest('[data-article]') : null;
      var a = b ? articles[b.getAttribute('data-article')] : null;
      if (a) basculerPanneau(zone, b.closest('.fiche'), b, detailArticle(a), C.brut(C.choisir(a.titre) || a.media || ''));
    });
  }
  /* presse.html : tous les articles publiés, en tableau, la même collection que l'accueil. Deux
     actions distinctes : « En savoir plus » déplie le détail de l'article sous sa ligne ; « Lire
     l'article » mène à l'article d'origine, et n'existe que si son adresse est renseignée.
     L'adresse presse.html#a-<id> déplie l'article d'elle-même. */
  function rendreArticles() {
    var zone = document.querySelector('[data-articles]');
    var A = window.PRESSE;
    if (!zone || !Array.isArray(A)) return;
    var articles = parId(A);
    var cols = ['presse.col.media', 'presse.col.article', 'presse.col.auteur', 'agenda.col.date', 'presse.col.extrait', 'agenda.col.lien'];
    var s = '<table class="tableau tableau--presse"><caption class="sr-only">' + h(C.t('presse.tous')) + '</caption>' +
      '<colgroup><col style="width:14%"><col style="width:24%"><col style="width:13%"><col style="width:12%"><col><col style="width:13%"></colgroup>' +
      '<thead><tr>' + cols.map(function (c) {
        return '<th scope="col">' + (c === 'agenda.col.lien' ? '<span class="sr-only">' + h(C.t(c)) + '</span>' : h(C.t(c))) + '</th>';
      }).join('') + '</tr></thead><tbody>';
    A.forEach(function (a) {
      var c = citationPrincipale(a);
      var resume = C.choisir(a.chapeau) || C.choisir(a.resume);
      s += '<tr id="a-' + C.echapper(a.id) + '"><th scope="row"><span class="tableau__titre"><i>' + h(a.media) + '</i></span></th>' +
        '<td>' + h(C.choisir(a.titre)) + (resume ? '<span class="tableau__programme">' + h(resume) + '</span>' : '') + '</td>' +
        '<td class="tableau__lieu">' + h(a.auteur || '') + '</td>' +
        '<td class="tableau__date">' + h(ecrireDate(a.date)) + '</td>' +
        '<td>' + (c ? '<q lang="' + C.echapper(c.langue || C.langue) + '">' + h(c.texte) + '</q>' : '') + '</td>' +
        '<td class="tableau__action"><button type="button" class="lien" data-article="' + C.echapper(a.id) + '" aria-expanded="false" aria-controls="ad-' + C.echapper(a.id) + '">' + h(C.t('presse.plus')) + '</button>' +
        (a.lien ? sortant(a.lien, h(C.t('presse.lire')), 'lien') : '') + '</td></tr>' +
        '<tr class="tableau__fiche" id="ad-' + C.echapper(a.id) + '" hidden><td colspan="6"></td></tr>';
    });
    zone.innerHTML = s + '</tbody></table>';
    function ouvrir(id, oui) {
      var a = articles[id], ligne = document.getElementById('ad-' + id), rang = document.getElementById('a-' + id);
      if (!a || !ligne) return;
      if (oui && !ligne.firstChild.firstChild) { ligne.firstChild.innerHTML = detailArticle(a); installerDiaporamas(ligne); C.liensSortants(ligne); }
      ligne.hidden = !oui;
      if (rang) rang.classList.toggle('est-ouverte', oui);
      var b = zone.querySelector('[data-article="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]');
      if (b) b.setAttribute('aria-expanded', oui ? 'true' : 'false');
    }
    zone.addEventListener('click', function (e) {
      var b = e.target && e.target.closest ? e.target.closest('[data-article]') : null;
      if (b) ouvrir(b.getAttribute('data-article'), b.getAttribute('aria-expanded') !== 'true');
    });
    function ouvrirAncre() {
      var m = /^#a-(.+)$/.exec(window.location.hash || '');
      if (!m) return;
      var id = decodeURIComponent(m[1]);
      ouvrir(id, true);
      var rang = document.getElementById('a-' + id);
      if (rang && rang.scrollIntoView) window.setTimeout(function () { rang.scrollIntoView({ block: 'start' }); }, 60);
    }
    window.addEventListener('hashchange', ouvrirAncre);
    ouvrirAncre();
  }

  /* ---------- Parcours : orchestres et cartes ----------------------------- */

  /* Même projection que outils/generer-cartes.py. La carte du monde est coupée à 170° O :
     les longitudes à l'ouest de la coupure passent à droite. */
  function projeter(c, lat, lon) {
    var L = lon;
    if (L < c.lon[0]) L += 360;
    return [(L - c.lon[0]) / (c.lon[1] - c.lon[0]) * c.l, (c.lat[1] - lat) / (c.lat[1] - c.lat[0]) * c.h];
  }
  function dedans(c, p) { return p[0] >= 0 && p[0] <= c.l && p[1] >= 0 && p[1] <= c.h; }
  function arrondir(x) { return Math.round(x * 10) / 10; }

  function svgCarte(c, villes, cadre) {
    var grand = c.id === 'europe';
    var r = grand ? 9 : 3.4;                       /* rayon du point, en unités de la carte */
    var police = grand ? 30 : 13;
    var d = c.points.split(' ').map(function (p) { return 'M' + p + 'h0'; }).join('');
    var s = '<figure class="carte carte--' + c.id + '">' +
      '<svg viewBox="0 0 ' + c.l + ' ' + c.h + '" role="group" aria-label="' + C.echapper(C.brut(C.t('parcours.carte.' + c.id))) + '">' +
      '<path class="carte__terre" d="' + d + '" stroke-width="' + (2 * c.r) + '"/>';
    if (cadre) {                                   /* cadre d'une autre carte, plus détaillée (inutilisé) */
      var a = projeter(c, cadre.lat[1], cadre.lon[0]), b = projeter(c, cadre.lat[0], cadre.lon[1]);
      s += '<rect class="carte__cadre" x="' + arrondir(a[0]) + '" y="' + arrondir(a[1]) + '" width="' + arrondir(b[0] - a[0]) + '" height="' + arrondir(b[1] - a[1]) + '"/>';
    }
    villes.forEach(function (v, k) {
      var p = projeter(c, v.lat, v.lon);
      if (!dedans(c, p)) return;
      var x = arrondir(p[0]), y = arrondir(p[1]);
      var ancre = x > c.l * 0.88 ? 'end' : x < c.l * 0.12 ? 'start' : 'middle';
      var haut = y < police * 2.4;                  /* près du bord haut : l'étiquette passe dessous */
      s += '<g class="carte__ville" data-ville="' + k + '" tabindex="0" role="img" aria-label="' + C.echapper(v.nom + (v.orchestres.length ? ' : ' + v.orchestres.join(', ') : '')) + '">' +
        '<circle cx="' + x + '" cy="' + y + '" r="' + arrondir(r * 2.6) + '" fill="transparent"/>' +
        '<circle class="carte__halo" cx="' + x + '" cy="' + y + '" r="' + arrondir(r * 2.6) + '"/>' +
        '<circle class="carte__point" cx="' + x + '" cy="' + y + '" r="' + r + '"/>' +
        '<text class="carte__etiquette" x="' + x + '" y="' + arrondir(haut ? y + r * 2.6 + police : y - r * 2.6 - police * 0.35) + '" font-size="' + police +
        '" stroke-width="' + arrondir(police * 0.28) + '" text-anchor="' + ancre + '">' + h(v.nom) + '</text></g>';
    });
    return s + '</svg></figure>';
  }

  function rendreParcours() {
    var O = window.ORCHESTRES;
    var K = window.CARTES;
    var liste = document.querySelector('[data-orchestres]');
    var zone = document.querySelector('[data-cartes]');
    if (!Array.isArray(O) || !liste) return;

    /* une ville = un point, partagé par les orchestres qui s'y trouvent. Une entrée sans nom
       d'orchestre a aussi sa ligne : la ville y tient lieu de nom (chaque point a sa ligne, chaque
       ligne son point). */
    var villes = [], index = {};
    O.forEach(function (o) {
      var cle = (+o.lat).toFixed(2) + ',' + (+o.lon).toFixed(2);
      if (!(cle in index)) { index[cle] = villes.length; villes.push({ nom: C.choisir(o.ville), lat: +o.lat, lon: +o.lon, orchestres: [] }); }
      o.pointVille = index[cle];
      if (o.nom) villes[index[cle]].orchestres.push(o.nom);
    });

    /* la liste, en trois colonnes égales : Amériques, Europe et Afrique, Asie, Russie et Océanie.
       La zone d'un orchestre est son champ zone, sinon elle se déduit de la longitude de sa ville
       (à l'ouest de 25° O : Amériques ; à l'est de 60° E : Asie et Océanie). La Russie se
       déclare avec zone: 'asie-oceanie', Moscou et Saint-Pétersbourg étant à l'ouest de 60° E. */
    var ZONES = ['ameriques', 'europe-afrique', 'asie-oceanie'];
    function zoneDe(o) {
      if (ZONES.indexOf(o.zone) >= 0) return o.zone;
      return +o.lon < -25 ? 'ameriques' : (+o.lon > 60 ? 'asie-oceanie' : 'europe-afrique');
    }
    liste.innerHTML = ZONES.map(function (z) {
      var lignes = O.filter(function (o) { return zoneDe(o) === z; }).map(function (o) {
        var ville = C.choisir(o.ville);
        if (!o.nom) return '<li tabindex="0" data-ville="' + o.pointVille + '">' + h(ville) + '</li>';
        return '<li tabindex="0" data-ville="' + o.pointVille + '">' + h(o.nom) +
          (ville ? '<span class="orchestres__ville">' + h(ville) + '</span>' : '') + '</li>';
      }).join('');
      return '<div class="orchestres-zone">' +
        '<h4 class="orchestres-zone__titre">' + h(C.t('parcours.zone.' + z)) + '</h4>' +
        '<ul class="orchestres">' + lignes + '</ul></div>';
    }).join('');

    /* une seule carte : le monde (data/cartes.js contient aussi l'Europe, qui n'est plus affichée) */
    if (zone && K && K.monde) zone.innerHTML = svgCarte(K.monde, villes, null);

    /* survol ou focus d'une ligne : son point s'allume ; survol ou focus d'un point : ses lignes */
    var portee = liste.closest('[data-parcours]') || document;
    function activer(k) {
      Array.prototype.forEach.call(portee.querySelectorAll('[data-ville]'), function (n) {
        n.classList.toggle('est-actif', k !== null && n.getAttribute('data-ville') === String(k));
      });
    }
    function cible(e) {
      var n = e.target && e.target.closest ? e.target.closest('[data-ville]') : null;
      return n ? n.getAttribute('data-ville') : null;
    }
    [liste, zone].forEach(function (z) {
      if (!z) return;
      z.addEventListener('mouseover', function (e) { var k = cible(e); if (k !== null) activer(k); });
      z.addEventListener('mouseleave', function () { activer(null); });
      z.addEventListener('focusin', function (e) { var k = cible(e); if (k !== null) activer(k); });
      z.addEventListener('focusout', function () { activer(null); });
      z.addEventListener('click', function (e) { activer(cible(e)); });       /* tactile : un toucher */
    });
  }

  /* ---------- Parcours (listes), téléchargements, contacts, réseaux -------- */

  /* Contrôle de cohérence, en français : le repli statique doit être ce que le rendu produit. */
  var sonde = document.createElement('div');
  function forme(htmlTexte) {
    sonde.innerHTML = htmlTexte;
    return sonde.innerHTML.replace(/<!--[\s\S]*?-->/g, '').replace(/\s+/g, ' ').replace(/> </g, '><').trim();
  }
  function controler(nom, statique, rendu) {
    if (C.langue === C.statique && forme(statique) !== forme(rendu)) C.ecarts.push('repli : ' + nom);
  }

  /* Fonctions, puis grands débuts et collaborations : deux listes de data/parcours.js.
     Une fonction tient sur une ligne : intitulé, institution, période (« depuis 2023 »,
     « 2021 à 2026 »). Une collaboration : intitulé, orchestre s'il n'y figure pas, année ; elle
     mène à la fiche de son concert quand il est sur le site. « À la une » : la ligne est mise en
     avant. Sans collaboration publiée, la rubrique quitte la page. */
  function periode(f) {
    if (f.debut && f.fin) return C.t('parcours.periode', { debut: f.debut, fin: f.fin });
    if (f.debut && f.actuelle) return C.t('parcours.depuis', { annee: f.debut });
    return f.debut || f.fin || '';
  }
  function ligneFonction(f) {
    return [C.choisir(f.intitule), C.choisir(f.institution), periode(f)].filter(Boolean).join(', ');
  }
  function ligneCollaboration(c) {
    var intitule = C.choisir(c.intitule);
    var orchestre = c.orchestre || '';
    return [intitule, (orchestre && intitule.indexOf(orchestre) < 0) ? orchestre : '', c.annee || ''].filter(Boolean).join(', ');
  }
  function rendreListesParcours() {
    var P = window.PARCOURS;
    if (!P) return;
    Array.prototype.forEach.call(document.querySelectorAll('[data-parcours-liste]'), function (ul) {
      var nom = ul.getAttribute('data-parcours-liste');
      if (!Array.isArray(P[nom])) return;
      var s = P[nom].map(function (x) {
        var t = nom === 'fonctions' ? ligneFonction(x) : ligneCollaboration(x);
        if (!t) return '';
        var contenu = h(t);
        if (nom === 'collaborations' && x.concert) contenu = '<a href="concerts.html#c-' + encodeURIComponent(x.concert) + '">' + contenu + '</a>';
        return '<li' + (x.une ? ' class="liste__une"' : '') + '>' + contenu + '</li>';
      }).join('');
      controler('parcours, ' + nom, ul.innerHTML, s);
      ul.innerHTML = s;
      /* une rubrique sans rien à montrer ne laisse pas son intitulé seul */
      if (!s) { var bloc = ul.closest('.grille'); if (bloc && bloc.parentNode) bloc.parentNode.removeChild(bloc); }
    });
  }

  /* Téléchargements : le fichier (ou son adresse, s'il est hébergé ailleurs) quand il est fourni,
     sinon « à venir ». Description, crédits et conditions d'utilisation : une ligne discrète,
     seulement s'il y a quelque chose à dire. */
  function rendreTelechargements() {
    var ul = document.querySelector('[data-telechargements]');
    var D = window.TELECHARGEMENTS;
    if (!ul || !Array.isArray(D)) return;
    var s = D.map(function (d) {
      var libelle = h(C.choisir(d.libelle));
      var hreflang = d.langue ? ' hreflang="' + C.echapper(d.langue) + '"' : '';
      var info = [C.choisir(d.description), d.credit || '', C.choisir(d.conditions)].filter(Boolean).map(h).join(' · ');
      var suite = info ? ' <small class="tel__info">' + info + '</small>' : '';
      var li = info ? '<li class="tel--detail">' : '<li>';
      if (!d.fichier) return li + '<span>' + libelle + '</span> <span class="avenir">' + h(C.t('commun.aVenir')) + '</span>' + suite + '</li>';
      if (/^https?:\/\//i.test(d.fichier)) return li + '<a href="' + C.echapper(d.fichier) + '"' + hreflang + ' target="_blank" rel="noopener">' + libelle + '</a>' + suite + '</li>';
      return li + '<a href="' + C.echapper(d.fichier) + '"' + hreflang + ' download>' + libelle + '</a>' + suite + '</li>';
    }).join('');
    controler('téléchargements', ul.innerHTML, s);
    ul.innerHTML = s;
  }

  /* Contacts : des groupes. Dans l'ordre (13/11/2026) : la mention, la structure, les personnes.
     `langue` : la mention et les fonctions du groupe sont écrites dans cette langue et ne se
     traduisent pas (attribut lang). */
  function courriel(adresse) {
    var i = adresse.indexOf('@');
    if (i < 0) return h(adresse);
    return h(adresse.slice(0, i + 1)) + '<wbr>' + h(adresse.slice(i + 1));   /* coupure permise après l'arobase */
  }
  function rendreContacts() {
    var zone = document.querySelector('[data-contacts]');
    var G = window.CONTACTS;
    if (!zone || !Array.isArray(G)) return;
    var s = G.map(function (g) {
      var lang = g.langue ? ' lang="' + C.echapper(g.langue) + '"' : '';
      var b = '<div class="contacts__groupe" data-revele>';
      var mention = C.choisir(g.mention);
      if (mention) b += '<p class="mention"' + lang + '>' + h(mention) + '</p>';
      if (g.structure) b += '<p class="contacts__agence">' + (g.lien ? sortant(g.lien, h(g.structure)) : h(g.structure)) + '</p>';
      (g.personnes || []).forEach(function (p) {
        b += '<div class="contact">';
        if (p.nom) b += '<p class="contact__nom">' + h(p.nom) + '</p>';
        var role = C.choisir(p.role);
        if (role) b += '<p class="contact__role"' + lang + '>' + h(role) + '</p>';
        if (p.telephone) b += '<p><a href="tel:' + C.echapper(String(p.telephone).replace(/[^\d+]/g, '')) + '">' + h(p.telephone) + '</a></p>';
        if (p.email) b += '<p><a href="mailto:' + C.echapper(p.email) + '">' + courriel(p.email) + '</a></p>';
        b += '</div>';
      });
      return b + '</div>';
    }).join('');
    var anciens = Array.prototype.slice.call(zone.querySelectorAll('.contacts__groupe'));
    controler('contacts', anciens.map(function (n) { return n.outerHTML; }).join(''), s);
    anciens.forEach(function (n) { n.parentNode.removeChild(n); });
    zone.insertAdjacentHTML('beforeend', s);
  }

  /* Réseaux : les pictogrammes sont dans le HTML (écrits par Pupitre), data/reseaux.js donne les
     adresses. Un réseau sans adresse reste affiché, inerte. */
  function rendreReseaux() {
    var R = window.RESEAUX;
    if (!Array.isArray(R)) return;
    var parId = {};
    R.forEach(function (r) { parId[r.id] = r; });
    Array.prototype.forEach.call(document.querySelectorAll('[data-reseaux]'), function (ul) {
      var barre = ul.getAttribute('data-reseaux') === 'barre';
      var attendus = R.filter(function (r) { return !barre || r.barre; }).map(function (r) { return r.id; });
      var presents = [];
      Array.prototype.forEach.call(ul.querySelectorAll('[data-reseau]'), function (li) {
        var r = parId[li.getAttribute('data-reseau')];
        var a = li.querySelector('a');
        if (!r || !a || (barre && !r.barre)) { li.parentNode.removeChild(li); return; }
        presents.push(r.id);
        if ((a.getAttribute('href') || '') !== (r.url || '')) presents.push('adresse');
        if (r.url) {
          a.setAttribute('href', r.url); a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener');
          a.classList.remove('inerte'); a.removeAttribute('role'); a.removeAttribute('aria-disabled');
        } else {
          a.removeAttribute('href'); a.removeAttribute('target'); a.removeAttribute('rel');
          a.classList.add('inerte'); a.setAttribute('role', 'link'); a.setAttribute('aria-disabled', 'true');
        }
        a.setAttribute('aria-label', r.nom || r.id);
      });
      if (C.langue === C.statique && presents.join() !== attendus.join()) C.ecarts.push('repli : réseaux');
    });
  }

  /* ---------- Tout rendre -------------------------------------------------- */

  function tenter(nom, fn) {
    try { fn(); } catch (e) { if (window.console) console.error('[sections] ' + nom, e); }
  }
  window.SiteSections = {
    rendre: function () {
      tenter('visuels', rendreVisuels);
      tenter('disques', rendreDisques);
      tenter('presse', rendrePresse);
      tenter('articles', rendreArticles);
      tenter('parcours', rendreParcours);
      tenter('listes du parcours', rendreListesParcours);
      tenter('téléchargements', rendreTelechargements);
      tenter('contacts', rendreContacts);
      tenter('réseaux', rendreReseaux);
    },
    ecrireDate: ecrireDate,
    videos: blocsVideo
  };
})();
