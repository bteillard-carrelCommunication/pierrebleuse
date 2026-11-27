/* ==========================================================================
   animations.js · registre des modules d'animation des blocs visuels
   → window.SiteAnimations

   Un bloc visuel peut montrer une image animée. L'animation est un module :
   du code du site, déclaré ici sous un identifiant stable. Pupitre ne fait que
   choisir un identifiant de ce registre et les valeurs de ses réglages
   (data/visuels.js : element.animation = { module, … }) ; aucun script ne
   vient de Pupitre. Le média, le type d'animation et les réglages sont trois
   choses distinctes.

   Un module se déclare ainsi :

     SiteAnimations.declarer('identifiant', {
       creer: function (section, options) { return scene; }
     });

   options : les réglages venus de Pupitre (preset…), plus « reduit » (mouvement
   réduit demandé) et « focus » ([x, y] de 0 à 1 : le point de cadrage de l'image).
   La scène rendue est conduite par js/main.js, image par image :

     scene.init()        → promesse, tenue quand la scène est prête
     scene.mesurer()     relit la place de la section dans la page
     scene.poser()       pose la scène à sa taille
     scene.maj(y, dt)    à chaque image : défilement y (px), temps écoulé dt (s)
     scene.respirer(t)   à chaque image, quand l'animation n'est pas en pause
     scene.section, scene.nom, scene.pret, scene.p, scene.cible, scene.visible, scene.cle
                         lus par le HUD et par les essais

   creer() rend null si le module ne peut pas servir ici : l'image reste seule.

   Ajouter une animation : écrire son module (un fichier js/ de plus, chargé
   avant celui-ci), le déclarer ci-dessous, puis le déclarer sous le même
   identifiant dans pupitre/inclus/animations.php pour qu'il soit proposé.
   ========================================================================== */
(function () {
  'use strict';

  var modules = {};

  function declarer(id, module) {
    if (!id || !module || typeof module.creer !== 'function') return false;
    modules[String(id)] = module;
    return true;
  }
  function connu(id) {
    return Object.prototype.hasOwnProperty.call(modules, String(id));
  }
  /* Crée la scène d'une section ; null si le module est inconnu ou s'il se récuse. */
  function creer(id, section, options) {
    if (!connu(id)) return null;
    return modules[String(id)].creer(section, options || {}) || null;
  }

  window.SiteAnimations = { declarer: declarer, connu: connu, creer: creer, modules: modules };

  /* ---------- Modules ---------------------------------------------------- */

  /* « lumiere » : les découpes de lumière (js/lumiere.js), telles quelles. Réglage : preset, l'un des
     préréglages de Lumiere.PRESETS (bleuse-1, bleuse-3, generique). */
  if (window.Lumiere && typeof window.Lumiere.creer === 'function') {
    declarer('lumiere', {
      creer: function (section, o) {
        return window.Lumiere.creer(section, { preset: o.preset, reduit: !!o.reduit, focus: o.focus || null });
      }
    });
  }
})();
