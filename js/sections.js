/* ==========================================================================
   sections.js · rendu des sections pilotées par des données

   Chargé après contenu.js et les fichiers de data/, avant le moteur de la nuée
   (rendu synchrone : main.js recense les [data-revele] à son chargement).

     visuels    data/visuels.js     [data-visuel]      image (telle quelle ou en « lumière »), vidéo
                                                       (muette ou avec son), YouTube ou couleur ; opacité
                                                       et place de la nuée
     disques    data/disques.js     [data-disques]     fiches au format de l'agenda
     presse     data/presse.js      [data-presse]      quatre articles ; [data-articles] : le tableau de presse.html
     parcours   data/orchestres.js  [data-orchestres]  liste des orchestres
                data/cartes.js      [data-cartes]      carte du monde, points liés à la liste

   Chaque rendu remplace le repli statique du HTML (français, sans JavaScript).
   Un champ vide ne s'affiche pas : rien n'est inventé ici.
   ========================================================================== */
(function () {
  'use strict';

  var C = window.SiteContenu;
  if (!C) return;
  var h = C.html;
  var sortant = C.lienSortant;
  var racineDoc = document.documentElement;
  var REDUIT = (function () {
    var p = C.parametres();
    return (p && p.get('reduit') === '1') || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  })();

  /* ---------- Visuels ----------------------------------------------------- */

  var telephone = window.matchMedia ? window.matchMedia('(max-width: 720px)') : null;
  var videos = [];

  /* Opacité → classe .fond-N (de 5 en 5), la même que pour les sections de contenu ; place de la
     nuée → .nuee-premier-plan (elle vole par-dessus le visuel) ou .nuee-arriere-plan (par défaut). */
  function poserFond(section, opacite, nuee) {
    var n = Math.max(0, Math.min(100, Math.round((isFinite(opacite) ? opacite : 100) / 5) * 5));
    Array.prototype.slice.call(section.classList).forEach(function (c) { if (/^fond-\d+$/.test(c)) section.classList.remove(c); });
    section.classList.add('fond-' + n);
    var devant = (nuee === 'premier-plan');
    section.classList.toggle('nuee-premier-plan', devant);
    section.classList.toggle('nuee-arriere-plan', !devant);
  }
  /* « 60% 35% » → [0.6, 0.35] : point focal d'une image traitée en « lumière » sans préréglage */
  function focal(position) {
    var m = /^\s*([\d.]+)%\s+([\d.]+)%\s*$/.exec(position || '');
    return m ? [Math.min(1, +m[1] / 100), Math.min(1, +m[2] / 100)] : null;
  }
  function idYouTube(v) {
    if (v.id) return String(v.id);
    var m = /(?:v=|youtu\.be\/|embed\/|shorts\/)([\w-]{6,})/.exec(v.url || '');
    return m ? m[1] : '';
  }
  function mediaYouTube(v) {
    var boite = document.createElement('div');
    boite.className = 'visuel__yt';
    var id = idYouTube(v);
    boite.innerHTML = '<div class="visuel__consentement"><p>' + h(C.t('yt.texte')) + '</p>' +
      '<button type="button" class="bouton">' + h(C.t('yt.bouton')) + '</button></div>';
    var bouton = boite.querySelector('button');
    if (!id) { bouton.disabled = true; return boite; }
    bouton.addEventListener('click', function () {
      /* le lecteur n'est jamais recouvert ni voilé : la section passe devant la nuée, à 100 % */
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
      f.title = 'YouTube';
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.setAttribute('allowfullscreen', '');
      boite.textContent = '';
      boite.appendChild(f);
    });
    return boite;
  }
  function rendreVisuels() {
    var V = window.VISUELS || {};
    Array.prototype.forEach.call(document.querySelectorAll('[data-visuel]'), function (section) {
      var v = V[section.getAttribute('data-visuel')];
      if (!v || !v.type) return;
      var ancien = section.querySelector('.visuel__media');
      var n = null;
      if (v.type === 'image' && v.src) {
        n = document.createElement('img');
        n.src = v.src;
        n.alt = C.brut(C.choisir(v.alt));
        n.decoding = 'async';
      } else if (v.type === 'video' && v.src) {
        n = document.createElement('video');
        n.muted = true; n.defaultMuted = true; n.loop = true; n.playsInline = true;
        n.setAttribute('muted', ''); n.setAttribute('playsinline', '');
        n.setAttribute('disablepictureinpicture', '');
        n.preload = 'metadata';
        if (v.affiche) n.poster = v.affiche;
        n.src = v.src;
        if (v.son) {
          /* vidéo avec son : c'est js/main.js qui la lance, la fond avec la musique et l'arrête */
          n.setAttribute('data-video-son', '');
          if (v.piste) n.setAttribute('data-piste', v.piste);
          var libelle = C.brut(C.choisir(v.alt));
          if (libelle) n.setAttribute('aria-label', libelle);
        } else {
          n.setAttribute('aria-hidden', 'true');
          videos.push(n);
        }
      } else if (v.type === 'youtube') {
        n = mediaYouTube(v);
      } else if (v.type === 'couleur') {
        n = document.createElement('div');
        n.style.backgroundColor = v.couleur || '#000';
      }
      if (!n) return;
      n.classList.add('visuel__media');
      if (v.type === 'image' || v.type === 'video') {
        var poser = function () { n.style.objectPosition = (telephone && telephone.matches && v.positionMobile) || v.position || ''; };
        poser();
        if (telephone && telephone.addEventListener) telephone.addEventListener('change', poser);
      }
      ['image', 'video', 'youtube', 'couleur'].forEach(function (t) { section.classList.toggle('visuel--' + t, t === v.type); });
      /* image en « lumière » (les découpes) : js/main.js crée la scène (js/lumiere.js) sur [data-lumiere] */
      var lumiere = (v.type === 'image' && v.src && v.lumiere) ? String(v.lumiere) : '';
      section.classList.toggle('visuel--lumiere', !!lumiere);
      section.classList.remove('lumiere-prete', 'lumiere-echec', 'lumiere-perdue');
      if (lumiere) {
        section.setAttribute('data-lumiere', lumiere);
        var f = focal(v.position);
        if (f) section.setAttribute('data-focal', f.join(',')); else section.removeAttribute('data-focal');
      } else {
        section.removeAttribute('data-lumiere');
        section.removeAttribute('data-focal');
      }
      poserFond(section, v.type === 'youtube' ? 100 : v.opacite, v.type === 'youtube' ? '' : v.nuee);
      if (ancien) ancien.parentNode.replaceChild(n, ancien); else section.insertBefore(n, section.firstChild);
    });
    surveillerVideos();
  }
  /* Une vidéo muette de visuel ne joue que dans la fenêtre, jamais en mouvement réduit ni quand
     l'animation est en pause (classe animation-en-pause posée par main.js). Une vidéo avec son
     (son: true) est conduite par main.js. */
  function surveillerVideos() {
    if (!videos.length || REDUIT || !('IntersectionObserver' in window)) return;
    var visibles = [];
    var maj = function () {
      var pause = racineDoc.classList.contains('animation-en-pause');
      videos.forEach(function (v) {
        if (!pause && visibles.indexOf(v) >= 0) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
        else v.pause();
      });
    };
    var io = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) {
        var i = visibles.indexOf(e.target);
        if (e.isIntersecting && i < 0) visibles.push(e.target);
        else if (!e.isIntersecting && i >= 0) visibles.splice(i, 1);
      });
      maj();
    }, { threshold: 0.05 });
    videos.forEach(function (v) { io.observe(v); });
    if ('MutationObserver' in window) new MutationObserver(maj).observe(racineDoc, { attributes: true, attributeFilter: ['class'] });
  }

  /* ---------- Disques ----------------------------------------------------- */

  function lignes(champ) {
    var l = Array.isArray(champ) ? champ : [champ];
    return l.map(C.choisir).filter(Boolean);
  }
  function liens(cle, liste) {
    liste = (liste || []).filter(function (l) { return l && l.url; });
    if (!liste.length) return '';
    return '<p class="fiche__label">' + h(C.t(cle)) + '</p><ul class="fiche__liens">' +
      liste.map(function (l) { return '<li>' + sortant(l.url, h(l.nom || l.url), 'lien') + '</li>'; }).join('') + '</ul>';
  }
  function rendreDisques() {
    var zone = document.querySelector('[data-disques]');
    var D = window.DISQUES;
    if (!zone || !Array.isArray(D)) return;
    zone.innerHTML = D.map(function (d) {
      var s = '<article class="fiche bloc-quart disque" data-revele>';
      var tete = [d.annee, C.choisir(d.maison)].filter(Boolean);                 /* année, maison de disques */
      if (tete.length) s += '<p class="fiche__dates">' + tete.map(h).join(' · ') + '</p>';
      s += '<h3 class="fiche__titre">' + h(C.choisir(d.titre)) + '</h3>';
      lignes(d.descriptif).forEach(function (l) { s += '<p class="fiche__texte">' + h(l) + '</p>'; });
      var mot = C.choisir(d.mot);                                                /* le mot de Pierre Bleuse */
      if (mot) s += '<p class="fiche__label">' + h(C.t('disques.mot')) + '</p><p class="fiche__mot">' + h(mot) + '</p>';
      s += liens('disques.commander', d.commander) + liens('disques.streaming', d.streaming);
      if (d.lien) s += sortant(d.lien, h(C.t('agenda.plus')), 'lien');          /* « En savoir plus » */
      return s + '</article>';
    }).join('');
  }

  /* ---------- Presse ------------------------------------------------------ */

  var formatsDate = {};
  function ecrireDate(iso) {
    var m = /^(\d{4})-(\d{2})(?:-(\d{2}))?$/.exec(iso || '');
    if (!m) return '';
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
  /* Un article : logo (en blanc, s'il est fourni) et nom du média, titre de l'article, verbatim clé,
     court résumé, date, auteur, lien. Un champ vide ne s'affiche pas. */
  function article(a) {
    var s = '<article class="fiche bloc-quart article" data-revele>';
    s += '<p class="article__media">' +
      (a.logo ? '<img class="article__logo" src="' + C.echapper(a.logo) + '" alt="" decoding="async">' : '') +
      '<span class="article__nom">' + h(a.media) + '</span></p>';
    var titre = C.choisir(a.titre);
    if (titre) s += '<h3 class="article__titre">' + h(titre) + '</h3>';
    if (a.verbatim) s += '<blockquote class="article__verbatim" lang="' + C.echapper(a.langue || C.langue) + '">' + h(a.verbatim) + '</blockquote>';
    var resume = C.choisir(a.resume);
    if (resume) s += '<p class="article__resume">' + h(resume) + '</p>';
    var date = ecrireDate(a.date);
    if (date || a.auteur) {
      s += '<p class="article__date">' + (date ? '<span>' + h(date) + '</span>' : '') +
        (a.auteur ? '<span class="article__auteur">' + h(a.auteur) + '</span>' : '') + '</p>';
    }
    if (a.lien) s += sortant(a.lien, h(C.t('presse.lire')), 'lien');
    return s + '</article>';
  }
  function rendrePresse() {
    var zone = document.querySelector('[data-presse]');
    var A = window.PRESSE;
    if (!zone || !Array.isArray(A)) return;
    zone.innerHTML = A.slice(0, 4).map(article).join('');
  }
  /* presse.html : tous les articles, en tableau */
  function rendreArticles() {
    var zone = document.querySelector('[data-articles]');
    var A = window.PRESSE;
    if (!zone || !Array.isArray(A)) return;
    var cols = ['presse.col.media', 'presse.col.article', 'presse.col.auteur', 'agenda.col.date', 'presse.col.extrait', 'agenda.col.lien'];
    var s = '<table class="tableau tableau--presse"><caption class="sr-only">' + h(C.t('presse.tous')) + '</caption>' +
      '<colgroup><col style="width:15%"><col style="width:24%"><col style="width:15%"><col style="width:13%"><col><col style="width:12%"></colgroup>' +
      '<thead><tr>' + cols.map(function (c) {
        return '<th scope="col">' + (c === 'agenda.col.lien' ? '<span class="sr-only">' + h(C.t(c)) + '</span>' : h(C.t(c))) + '</th>';
      }).join('') + '</tr></thead><tbody>';
    A.forEach(function (a) {
      s += '<tr><th scope="row"><span class="tableau__titre"><i>' + h(a.media) + '</i></span></th>' +
        '<td>' + h(C.choisir(a.titre)) + (C.choisir(a.resume) ? '<span class="tableau__programme">' + h(C.choisir(a.resume)) + '</span>' : '') + '</td>' +
        '<td class="tableau__lieu">' + h(a.auteur || '') + '</td>' +
        '<td class="tableau__date">' + h(ecrireDate(a.date)) + '</td>' +
        '<td>' + (a.verbatim ? '<q lang="' + C.echapper(a.langue || C.langue) + '">' + h(a.verbatim) + '</q>' : '') + '</td>' +
        '<td class="tableau__action">' + (a.lien ? sortant(a.lien, h(C.t('presse.lire')), 'lien') : '') + '</td></tr>';
    });
    zone.innerHTML = s + '</tbody></table>';
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
    },
    ecrireDate: ecrireDate
  };
})();
