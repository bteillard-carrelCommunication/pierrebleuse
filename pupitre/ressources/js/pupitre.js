/* ==========================================================================
   pupitre.js · comportements de l'interface. Sans bibliothèque.

   Tout passe par des attributs data- posés dans le HTML ; aucun script n'est
   écrit dans les pages (la politique de sécurité du contenu l'interdit).

     data-ouvrir-menu      bouton du menu, sur écran étroit ; data-fermer-menu : le voile qui le referme
     data-replier-menu     réduit le menu à ses pictogrammes, ou le déplie (écrans larges) ; le choix est retenu
     data-section          section du menu (<details>) : celles que l'on laisse ouvertes sont retenues
     data-bascule          bouton qui ouvre ou ferme la fenêtre flottante désignée (notifications, compte) ;
                           un clic ailleurs ou Échap la referme
     data-recherche        recherche de la barre du haut : résultats au fil de la saisie (ajax/recherche.php),
                           flèches et Entrée pour choisir, « / » pour y aller ; sans script, elle mène à la page de résultats
     data-notifs           centre de notifications : filtres par niveau (data-notifs-filtre)
     data-fermer-message   masque un message
     data-liste            liste ordonnable : lignes [data-ligne], modèle <template data-modele>,
                           bouton [data-ajouter] ; dans une ligne : data-monter, data-descendre,
                           data-retirer
     data-confirmer        demande une confirmation avant d'envoyer
     data-auto             envoie le formulaire dès que le champ change (filtres)
     data-tache            suit une tâche de fond et recharge la page quand elle est finie
     data-focal            réglage du point focal d'une image, au clic
     data-cocher           coche toutes les cases du formulaire qui portent ce nom
     data-filtrer-cases    champ de recherche : ne montre, dans la liste désignée, que les lignes [data-texte] qui contiennent la saisie
     data-couleur          formulaire d'une couleur : sélecteur, hexadécimal, RVB et CMJN accordés
     data-carte            carte du monde : en édition (data-carte-edition), le point [data-carte-point] se place
                           au clic ou se fait glisser, accordé aux champs latitude et longitude du formulaire
                           [data-carte-champs]
   ========================================================================== */
(function () {
  'use strict';

  var base = (document.querySelector('meta[name="base"]') || {}).content || '';
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  function plusProche(n, selecteur) { return n && n.closest ? n.closest(selecteur) : null; }

  /* Un témoin de préférence d'interface : un an, limité à Pupitre. */
  function temoin(nom, valeur) {
    document.cookie = nom + '=' + encodeURIComponent(valeur) + '; path=' + (base || '/') + '; max-age=31536000; samesite=lax';
  }

  /* ---------- Menu : tiroir sur écran étroit, réduction sur écran large, sections retenues ---------- */
  var corps = document.body;
  var voile = document.querySelector('[data-fermer-menu]');
  var principal = document.querySelector('.principal');
  function menuOuvert(ouvert) {
    corps.classList.toggle('menu-ouvert', ouvert);
    if (voile) voile.hidden = !ouvert;
    if (principal && 'inert' in principal) principal.inert = ouvert;
    var b = document.querySelector('[data-ouvrir-menu]');
    if (b) b.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    if (ouvert) { var premier = document.querySelector('.menu a, .menu summary'); if (premier) premier.focus(); }
    else if (b && document.activeElement && plusProche(document.activeElement, '.lateral')) b.focus();
  }
  document.addEventListener('click', function (e) {
    if (plusProche(e.target, '[data-ouvrir-menu]')) { menuOuvert(!corps.classList.contains('menu-ouvert')); return; }
    if (plusProche(e.target, '[data-fermer-menu]')) { menuOuvert(false); return; }
    var replier = plusProche(e.target, '[data-replier-menu]');
    if (replier) {
      var reduit = corps.classList.toggle('menu-reduit');
      replier.setAttribute('aria-pressed', reduit ? 'true' : 'false');
      temoin('pupitre_lateral', reduit ? 'reduit' : '');
      return;
    }
    /* menu réduit : une section n'a pas la place de se déplier ; on rend d'abord sa largeur au menu */
    var section = plusProche(e.target, '.menu__section > summary');
    if (section && corps.classList.contains('menu-reduit') && window.matchMedia('(min-width: 64.0625rem)').matches) {
      e.preventDefault();
      corps.classList.remove('menu-reduit');
      temoin('pupitre_lateral', '');
      var r = document.querySelector('[data-replier-menu]');
      if (r) r.setAttribute('aria-pressed', 'false');
      section.parentNode.open = true;
      retenirSections();
    }
  });
  if (window.matchMedia) {
    var large = window.matchMedia('(min-width: 64.0625rem)');
    var surChangement = function () { if (large.matches && corps.classList.contains('menu-ouvert')) menuOuvert(false); };
    if (large.addEventListener) large.addEventListener('change', surChangement); else if (large.addListener) large.addListener(surChangement);
  }
  function retenirSections() {
    var ouvertes = Array.prototype.filter.call(document.querySelectorAll('[data-section]'), function (d) { return d.open; })
      .map(function (d) { return d.getAttribute('data-section'); });
    temoin('pupitre_menu', ouvertes.join('.'));
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-section]'), function (d) { d.addEventListener('toggle', retenirSections); });

  /* ---------- Fenêtres flottantes : une seule ouverte à la fois ---------- */
  function fermerFlottants(sauf) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-bascule][aria-expanded="true"]'), function (b) {
      if (b === sauf) return;
      var cible = document.querySelector(b.getAttribute('data-bascule'));
      if (cible) cible.hidden = true;
      b.setAttribute('aria-expanded', 'false');
    });
  }
  document.addEventListener('click', function (e) {
    var b = plusProche(e.target, '[data-bascule]');
    if (b) {
      var cible = document.querySelector(b.getAttribute('data-bascule'));
      if (!cible) return;
      var ouvrir = cible.hidden;
      fermerFlottants(b);
      cible.hidden = !ouvrir;
      b.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
      if (ouvrir) { var premier = cible.querySelector('a, button'); if (premier) premier.focus({ preventScroll: true }); }
      return;
    }
    if (!plusProche(e.target, '.flottant')) fermerFlottants(null);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var ouvert = document.querySelector('[data-bascule][aria-expanded="true"]');
    if (ouvert) { fermerFlottants(null); ouvert.focus(); return; }
    if (corps.classList.contains('menu-ouvert')) menuOuvert(false);
  });

  /* ---------- Messages ---------- */
  document.addEventListener('click', function (e) {
    var b = plusProche(e.target, '[data-fermer-message]');
    var m = b && plusProche(b, '.message');
    if (m) m.parentNode.removeChild(m);
  });

  /* ---------- Centre de notifications : filtres par niveau ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-notifs]'), function (centre) {
    var vide = centre.querySelector('[data-notifs-vide]');
    centre.addEventListener('click', function (e) {
      var f = plusProche(e.target, '[data-notifs-filtre]');
      if (!f) return;
      var niveau = f.getAttribute('data-notifs-filtre');
      var vues = 0;
      Array.prototype.forEach.call(centre.querySelectorAll('[data-notifs-filtre]'), function (x) { x.setAttribute('aria-pressed', x === f ? 'true' : 'false'); });
      Array.prototype.forEach.call(centre.querySelectorAll('[data-niveau]'), function (n) {
        n.hidden = niveau !== '' && n.getAttribute('data-niveau') !== niveau;
        if (!n.hidden) vues++;
      });
      if (vide) vide.hidden = vues > 0 || !centre.querySelector('[data-niveau]');
    });
  });

  /* ---------- Recherche de la barre du haut ---------- */
  (function () {
    var forme = document.querySelector('[data-recherche]');
    if (!forme) return;
    var champ = forme.querySelector('input[type="search"]');
    var boite = forme.querySelector('.recherche__resultats');
    var ouvrir = document.querySelector('[data-ouvrir-recherche]');
    var attente = 0, dernier = '', choisi = -1;
    function echapper(t) { var d = document.createElement('div'); d.textContent = String(t == null ? '' : t); return d.innerHTML; }
    function options() { return boite.querySelectorAll('[role="option"]'); }
    function fermer() { boite.hidden = true; champ.setAttribute('aria-expanded', 'false'); choisi = -1; }
    function choisir(i) {
      var o = options();
      if (!o.length) return;
      choisi = (i + o.length) % o.length;
      Array.prototype.forEach.call(o, function (x, n) { x.setAttribute('aria-selected', n === choisi ? 'true' : 'false'); });
      o[choisi].scrollIntoView({ block: 'nearest' });
      champ.setAttribute('aria-activedescendant', o[choisi].id);
    }
    function montrer(groupes, q) {
      var h = '', n = 0;
      groupes.forEach(function (g) {
        h += '<p class="recherche__groupe">' + echapper(g.titre) + '</p>';
        g.resultats.forEach(function (r) {
          h += '<a class="flottant__lien recherche__resultat" role="option" id="rech-' + (n++) + '" aria-selected="false" href="' + echapper(r.url) + '">'
            + (r.icone || '') + '<span>' + echapper(r.libelle) + (r.detail ? '<small>' + echapper(r.detail) + '</small>' : '') + '</span></a>';
        });
      });
      if (!n) h = '<p class="recherche__vide">Rien ne correspond à « ' + echapper(q) + ' ».</p>';
      var annonce = forme.querySelector('[data-recherche-annonce]');
      if (annonce) annonce.textContent = n ? n + (n > 1 ? ' résultats' : ' résultat') : 'Aucun résultat';
      boite.innerHTML = h;
      boite.hidden = false;
      champ.setAttribute('aria-expanded', 'true');
      champ.removeAttribute('aria-activedescendant');
      choisi = -1;
    }
    function chercher() {
      var q = champ.value.trim();
      if (q.length < 2) { fermer(); dernier = ''; return; }
      if (q === dernier) { boite.hidden = false; return; }
      dernier = q;
      fetch(base + '/ajax/recherche.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (champ.value.trim() === q) montrer(d.groupes || [], q); })
        .catch(function () { /* la touche Entrée mène toujours à la page de résultats */ });
    }
    champ.addEventListener('input', function () { window.clearTimeout(attente); attente = window.setTimeout(chercher, 180); });
    champ.addEventListener('focus', function () { if (champ.value.trim().length >= 2) chercher(); });
    champ.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (boite.hidden) chercher(); else choisir(choisi + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (!boite.hidden) choisir(choisi - 1); }
      else if (e.key === 'Enter' && choisi >= 0 && !boite.hidden) { e.preventDefault(); var o = options()[choisi]; if (o) window.location.href = o.getAttribute('href'); }
      else if (e.key === 'Escape') { if (!boite.hidden) { e.stopPropagation(); fermer(); } else champ.blur(); }
    });
    document.addEventListener('click', function (e) {
      if (plusProche(e.target, '[data-ouvrir-recherche]')) {
        var ouvert = corps.classList.toggle('recherche-ouverte');
        ouvrir.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        if (ouvert) champ.focus(); else fermer();
        return;
      }
      if (!plusProche(e.target, '[data-recherche]')) fermer();
    });
    /* « / » mène à la recherche, sauf pendant une saisie */
    document.addEventListener('keydown', function (e) {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
      var t = e.target;
      if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
      e.preventDefault();
      if (ouvrir && window.getComputedStyle(forme).display === 'none') { corps.classList.add('recherche-ouverte'); ouvrir.setAttribute('aria-expanded', 'true'); }
      champ.focus();
      champ.select();
    });
  })();

  /* ---------- Listes ordonnables ---------- */
  function numeroter(liste) {
    Array.prototype.forEach.call(liste.querySelectorAll(':scope > [data-lignes] > [data-ligne]'), function (l, i) {
      var r = l.querySelector('[data-rang]');
      if (r) r.textContent = String(i + 1);
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-liste]'), numeroter);
  document.addEventListener('click', function (e) {
    var ajouter = plusProche(e.target, '[data-ajouter]');
    if (ajouter) {
      var liste = plusProche(ajouter, '[data-liste]');
      var modele = liste && liste.querySelector(':scope > template[data-modele]');
      var zone = liste && liste.querySelector(':scope > [data-lignes]');
      if (!modele || !zone) return;
      var n = parseInt(liste.getAttribute('data-suivant') || '0', 10);
      liste.setAttribute('data-suivant', String(n + 1));
      var boite = document.createElement('div');
      boite.innerHTML = modele.innerHTML.replace(/__i__/g, 'n' + n);
      var ligne = boite.firstElementChild;
      zone.appendChild(ligne);
      numeroter(liste);
      var premier = ligne.querySelector('input:not([type="hidden"]), textarea, select');
      if (premier) premier.focus();
      return;
    }
    var outil = plusProche(e.target, '[data-monter], [data-descendre], [data-retirer]');
    if (!outil) return;
    var l = plusProche(outil, '[data-ligne]');
    var parent = l && l.parentNode;
    if (!parent) return;
    if (outil.hasAttribute('data-retirer')) {
      var suivant = l.nextElementSibling || l.previousElementSibling;
      parent.removeChild(l);
      var cible = suivant && suivant.querySelector('[data-retirer]');
      if (cible) cible.focus(); else { var a = plusProche(parent, '[data-liste]'); a = a && a.querySelector('[data-ajouter]'); if (a) a.focus(); }
    } else if (outil.hasAttribute('data-monter') && l.previousElementSibling) {
      parent.insertBefore(l, l.previousElementSibling);
      outil.focus();
    } else if (outil.hasAttribute('data-descendre') && l.nextElementSibling) {
      parent.insertBefore(l.nextElementSibling, l);
      outil.focus();
    }
    var li = plusProche(parent, '[data-liste]');
    if (li) numeroter(li);
  });

  /* ---------- Confirmation, envoi automatique ---------- */
  document.addEventListener('submit', function (e) {
    var b = e.submitter && e.submitter.getAttribute('data-confirmer') ? e.submitter : null;
    var message = b ? b.getAttribute('data-confirmer') : e.target.getAttribute('data-confirmer');
    if (message && !window.confirm(message)) e.preventDefault();
  });
  document.addEventListener('change', function (e) {
    if (e.target.hasAttribute && e.target.hasAttribute('data-auto') && e.target.form) e.target.form.submit();
  });

  /* ---------- Suivi d'une tâche de fond ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-tache]:not([data-finie])'), function (boite) {
    var id = boite.getAttribute('data-tache');
    var barre = boite.querySelector('.tache__barre span');
    var message = boite.querySelector('.tache__message');
    function suivre() {
      fetch(base + '/ajax/tache.php?id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'X-CSRF': csrf } })
        .then(function (r) { return r.json(); })
        .then(function (t) {
          if (t.erreur) { message.textContent = t.erreur; return; }
          barre.style.width = t.avancement + '%';
          message.textContent = t.message;
          if (t.statut === 'terminee' || t.statut === 'echec') { window.setTimeout(function () { window.location.reload(); }, 700); return; }
          window.setTimeout(suivre, 2000);
        })
        .catch(function () { window.setTimeout(suivre, 4000); });
    }
    window.setTimeout(suivre, 1200);
  });

  /* ---------- Tout cocher ---------- */
  document.addEventListener('click', function (e) {
    var b = plusProche(e.target, '[data-cocher]');
    if (!b || !b.form) return;
    var cases = b.form.querySelectorAll('input[type="checkbox"][name="' + b.getAttribute('data-cocher') + '"]');
    var tout = Array.prototype.every.call(cases, function (c) { return c.checked; });
    Array.prototype.forEach.call(cases, function (c) { c.checked = !tout; });
  });

  /* ---------- Chercher dans une liste de cases ---------- */
  document.addEventListener('input', function (e) {
    var champ = e.target && e.target.hasAttribute && e.target.hasAttribute('data-filtrer-cases') ? e.target : null;
    if (!champ) return;
    var liste = document.querySelector(champ.getAttribute('data-filtrer-cases'));
    if (!liste) return;
    var q = champ.value.trim().toLowerCase();
    Array.prototype.forEach.call(liste.querySelectorAll('[data-texte]'), function (li) {
      var coche = li.querySelector('input[type="checkbox"]');
      li.hidden = q !== '' && li.getAttribute('data-texte').indexOf(q) < 0 && !(coche && coche.checked);
    });
  });

  /* ---------- Couleur : sélecteur, hexadécimal, RVB et CMJN accordés ---------- */
  /* La valeur de référence est l'hexadécimal (sRVB). Le CMJN est converti sans profil
     colorimétrique : c'est une approximation, annoncée comme telle sur l'écran. */
  Array.prototype.forEach.call(document.querySelectorAll('[data-couleur]'), function (forme) {
    var hex = forme.querySelector('[data-couleur-hex]');
    var nuancier = forme.querySelector('[data-couleur-nuancier]');
    var rvb = forme.querySelectorAll('[data-couleur-rvb]');
    var cmjn = forme.querySelectorAll('[data-couleur-cmjn]');
    var apercu = document.querySelector('[data-couleur-apercu]');
    if (!hex) return;
    function borne(n, max) { n = Math.round(Number(n)); return isNaN(n) ? 0 : Math.max(0, Math.min(max, n)); }
    function lireHex(s) {
      s = String(s).trim().replace(/^#/, '').toLowerCase();
      if (/^[0-9a-f]{3}$/.test(s)) s = s[0] + s[0] + s[1] + s[1] + s[2] + s[2];
      return /^[0-9a-f]{6}$/.test(s) ? [parseInt(s.slice(0, 2), 16), parseInt(s.slice(2, 4), 16), parseInt(s.slice(4, 6), 16)] : null;
    }
    function versHex(c) { return '#' + c.map(function (n) { return ('0' + borne(n, 255).toString(16)).slice(-2); }).join(''); }
    function versCmjn(c) {
      var r = c[0] / 255, v = c[1] / 255, b = c[2] / 255, n = 1 - Math.max(r, v, b);
      if (n >= 1) return [0, 0, 0, 100];
      return [(1 - r - n) / (1 - n), (1 - v - n) / (1 - n), (1 - b - n) / (1 - n), n].map(function (x) { return Math.round(x * 100); });
    }
    function depuisCmjn(k) {
      var n = k[3] / 100;
      return [0, 1, 2].map(function (i) { return Math.round(255 * (1 - k[i] / 100) * (1 - n)); });
    }
    /* Pose une couleur partout, sauf dans le champ d'où elle vient (on n'écrase pas une saisie en cours). */
    function poser(c, source) {
      var h = versHex(c);
      if (source !== 'hex') hex.value = h;
      if (nuancier && source !== 'nuancier') nuancier.value = h;
      if (source !== 'rvb') Array.prototype.forEach.call(rvb, function (champ, i) { champ.value = c[i]; });
      if (source !== 'cmjn') { var k = versCmjn(c); Array.prototype.forEach.call(cmjn, function (champ, i) { champ.value = k[i]; }); }
      if (apercu) apercu.style.background = h;
      Array.prototype.forEach.call(forme.querySelectorAll('[data-couleur-choisir]'), function (b) {
        b.setAttribute('aria-pressed', b.getAttribute('data-couleur-choisir').toLowerCase() === h ? 'true' : 'false');
      });
    }
    hex.addEventListener('input', function () { var c = lireHex(hex.value); if (c) poser(c, 'hex'); });
    hex.addEventListener('change', function () { var c = lireHex(hex.value); if (c) poser(c, ''); });
    if (nuancier) nuancier.addEventListener('input', function () { var c = lireHex(nuancier.value); if (c) poser(c, 'nuancier'); });
    Array.prototype.forEach.call(rvb, function (champ) {
      champ.addEventListener('input', function () { poser(Array.prototype.map.call(rvb, function (x) { return borne(x.value, 255); }), 'rvb'); });
    });
    Array.prototype.forEach.call(cmjn, function (champ) {
      champ.addEventListener('input', function () { poser(depuisCmjn(Array.prototype.map.call(cmjn, function (x) { return borne(x.value, 100); })), 'cmjn'); });
    });
    forme.addEventListener('click', function (e) {
      var b = plusProche(e.target, '[data-couleur-choisir]');
      if (!b) return;
      var c = lireHex(b.getAttribute('data-couleur-choisir'));
      if (c) poser(c, '');
    });
    var depart = lireHex(hex.value);
    if (depart) poser(depart, 'hex');
  });

  /* ---------- Carte du monde : placer un point ---------- */
  /* La projection est celle du site (équirectangulaire, de 170° O à 190° E) : x et y du cadre
     ↔ longitude et latitude. Le point suit les champs, les champs suivent le point. */
  Array.prototype.forEach.call(document.querySelectorAll('[data-carte][data-carte-edition]'), function (boite) {
    var svg = boite.querySelector('svg');
    var point = boite.querySelector('[data-carte-point]');
    var forme = document.querySelector('[data-carte-champs]');
    if (!svg || !point || !forme) return;
    var lat = forme.querySelector('[name="latitude"]'), lon = forme.querySelector('[name="longitude"]');
    if (!lat || !lon) return;
    var d = boite.dataset;
    var lon0 = parseFloat(d.lon0), lon1 = parseFloat(d.lon1), lat0 = parseFloat(d.lat0), lat1 = parseFloat(d.lat1), L = parseFloat(d.l), H = parseFloat(d.h);
    function nombre(champ) { var x = parseFloat(String(champ.value).replace(',', '.')); return isFinite(x) ? x : null; }
    function placer() {
      var a = nombre(lat), o = nombre(lon);
      if (a === null || o === null) { point.setAttribute('hidden', ''); return; }
      if (o < lon0) o += 360;
      point.removeAttribute('hidden');
      point.setAttribute('transform', 'translate(' + ((o - lon0) / (lon1 - lon0) * L).toFixed(2) + ' ' + ((lat1 - a) / (lat1 - lat0) * H).toFixed(2) + ')');
    }
    function depuisEcran(e) {
      var r = svg.getBoundingClientRect();
      var x = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)), y = Math.max(0, Math.min(1, (e.clientY - r.top) / r.height));
      var o = lon0 + x * (lon1 - lon0), a = lat1 - y * (lat1 - lat0);
      if (o > 180) o -= 360;
      lat.value = a.toFixed(2);
      lon.value = o.toFixed(2);
      var v = forme.querySelector('[name="verifie"]');
      if (v) v.checked = false;                     /* un point déplacé reste à vérifier */
      placer();
    }
    var glisse = false;
    svg.addEventListener('pointerdown', function (e) {
      if (plusProche(e.target, 'a')) e.preventDefault();          /* en édition, un clic place le point : il n'ouvre pas une autre ligne */
      glisse = true;
      try { svg.setPointerCapture(e.pointerId); } catch (x) { /* sans importance */ }
      depuisEcran(e);
    });
    svg.addEventListener('pointermove', function (e) { if (glisse) depuisEcran(e); });
    ['pointerup', 'pointercancel'].forEach(function (ev) { svg.addEventListener(ev, function () { glisse = false; }); });
    svg.addEventListener('click', function (e) { if (plusProche(e.target, 'a')) e.preventDefault(); });
    lat.addEventListener('input', placer);
    lon.addEventListener('input', placer);
    placer();
  });

  /* ---------- Point focal d'une image ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-focal]'), function (zone) {
    var forme = plusProche(zone, 'form');
    if (!forme) return;
    function champ(nom) { return forme.querySelector('[name="' + zone.getAttribute('data-focal') + '[' + nom + ']"]'); }
    function cible() {
      var c = forme.querySelector('[name="' + zone.getAttribute('data-focal') + '[cible]"]:checked');
      return c && c.value === 'mobile' ? 'mobile' : 'large';
    }
    function placer() {
      [['large', 'position_x', 'position_y', '.focal__point--large', '.cadrage--large img'],
       ['mobile', 'mobile_x', 'mobile_y', '.focal__point--mobile', '.cadrage--etroit img']].forEach(function (d) {
        var x = champ(d[1]), y = champ(d[2]);
        var point = zone.querySelector(d[3]);
        var vide = !x || x.value === '' || !y || y.value === '';
        if (point) { point.hidden = vide; if (!vide) { point.style.left = x.value + '%'; point.style.top = y.value + '%'; } }
        var apercu = forme.querySelector('[data-cadrages="' + zone.getAttribute('data-focal') + '"] ' + d[4]);
        if (apercu) {
          // sur téléphone, le point focal propre l'emporte ; sinon celui de l'écran large
          var px = vide ? champ('position_x') : x, py = vide ? champ('position_y') : y;
          apercu.style.objectPosition = (px && px.value !== '' ? px.value : 50) + '% ' + (py && py.value !== '' ? py.value : 50) + '%';
        }
      });
    }
    zone.addEventListener('click', function (e) {
      var image = zone.querySelector('img');
      if (!image) return;
      var r = image.getBoundingClientRect();
      var x = Math.round(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * 100);
      var y = Math.round(Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) * 100);
      var mobile = cible() === 'mobile';
      var cx = champ(mobile ? 'mobile_x' : 'position_x'), cy = champ(mobile ? 'mobile_y' : 'position_y');
      if (cx && cy) { cx.value = x; cy.value = y; }
      placer();
    });
    forme.addEventListener('input', placer);
    placer();
  });
})();
