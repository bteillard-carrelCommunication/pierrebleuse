/* ==========================================================================
   public.js · le peu de script des pages ouvertes par un lien, hors connexion
   (procès-verbal d'un aperçu, zone de dépôt). Sans bibliothèque ; rien n'en dépend :
   sans script, ces pages marchent pareil, avec moins de confort.

     data-imprimer        ouvre l'impression du navigateur
     data-depot           zone de dépôt : montre les fichiers choisis et leur poids avant l'envoi,
                          refuse ce qui dépasse la limite, dit que l'envoi est en cours
   ========================================================================== */
(function () {
  'use strict';

  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('[data-imprimer]') : null;
    if (b) window.print();
  });

  function poids(n) {
    if (n >= 1048576) return (Math.round(n / 104857.6) / 10).toLocaleString() + ' Mo';
    return Math.max(1, Math.round(n / 1024)).toLocaleString() + ' ko';
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-depot]'), function (forme) {
    var champ = forme.querySelector('input[type="file"]');
    var liste = forme.querySelector('[data-depot-liste]');
    var bouton = forme.querySelector('button[type="submit"]');
    var max = parseInt(forme.getAttribute('data-depot-max') || '0', 10);
    var maxTotal = parseInt(forme.getAttribute('data-depot-total') || '0', 10);
    if (!champ || !liste) return;
    function montrer() {
      liste.textContent = '';
      var total = 0, refus = false;
      Array.prototype.forEach.call(champ.files, function (f) {
        var li = document.createElement('li');
        var trop = max && f.size > max;
        total += f.size;
        refus = refus || trop;
        li.textContent = f.name + ' · ' + poids(f.size) + (trop ? ' · ' + (forme.getAttribute('data-depot-trop') || 'trop lourd') : '');
        if (trop) li.className = 'requis';
        liste.appendChild(li);
      });
      if (maxTotal && total > maxTotal) {
        refus = true;
        var li = document.createElement('li');
        li.className = 'requis';
        li.textContent = (forme.getAttribute('data-depot-trop-total') || 'Trop de fichiers à la fois') + ' (' + poids(total) + ')';
        liste.appendChild(li);
      }
      if (bouton) bouton.disabled = refus || !champ.files.length;
    }
    champ.addEventListener('change', montrer);
    forme.addEventListener('submit', function () {
      if (bouton) { bouton.disabled = true; bouton.textContent = forme.getAttribute('data-depot-envoi') || '…'; }
    });
    montrer();
  });
})();
