/* ==========================================================================
   pupitre.js · comportements de l'interface. Sans bibliothèque.

   Tout passe par des attributs data- posés dans le HTML ; aucun script n'est
   écrit dans les pages (la politique de sécurité du contenu l'interdit).

     data-ouvrir-menu      bouton du menu, sur téléphone
     data-liste            liste ordonnable : lignes [data-ligne], modèle <template data-modele>,
                           bouton [data-ajouter] ; dans une ligne : data-monter, data-descendre,
                           data-retirer
     data-confirmer        demande une confirmation avant d'envoyer
     data-auto             envoie le formulaire dès que le champ change (filtres)
     data-tache            suit une tâche de fond et recharge la page quand elle est finie
     data-focal            réglage du point focal d'une image, au clic
   ========================================================================== */
(function () {
  'use strict';

  var base = (document.querySelector('meta[name="base"]') || {}).content || '';
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  function plusProche(n, selecteur) { return n && n.closest ? n.closest(selecteur) : null; }

  /* ---------- Menu ---------- */
  document.addEventListener('click', function (e) {
    var b = plusProche(e.target, '[data-ouvrir-menu]');
    if (b) {
      var ouvert = document.body.classList.toggle('menu-ouvert');
      b.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      return;
    }
    if (document.body.classList.contains('menu-ouvert') && !plusProche(e.target, '.menu')) {
      document.body.classList.remove('menu-ouvert');
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.body.classList.remove('menu-ouvert');
  });

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
