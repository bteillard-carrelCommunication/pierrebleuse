/* ==========================================================================
   agenda.js · règles et rendu de l'agenda

   Données : window.AGENDA_DONNEES (data/agenda.js, généré à partir de
   data/agenda.json, qui reste la source). Textes : window.SiteContenu.

   1. Règles et formats (réutilisables par d'autres pages, sans DOM) :
      SiteAgenda.dateReference(), calculer(donnees, { date }), formats de
      dates et d'heures.
   2. Rendu de l'accueil : SiteAgenda.rendreAccueil() remplit [data-agenda]
      et [data-prochain]. Rendu synchrone, avant les scripts du moteur, pour
      que les [data-revele] soient recensés par main.js.
   ========================================================================== */
(function () {
  'use strict';

  var C = window.SiteContenu;
  if (!C) return;

  var NBSP = ' ';
  var EN = (C.langue === 'en');
  var JOURS_AFFICHE = 30;   // fenêtre de « À l'affiche »
  var MIN_AFFICHE = 2;
  var MAX_AFFICHE = 4;
  var NB_DERNIERS = 3;
  var HEURE_SOIR = 17;      // « Ce soir » : seulement si la représentation commence à 17 h ou après

  /* ---------- 1. Dates ---------------------------------------------------- */

  var RE_DATE = /^\d{4}-\d{2}-\d{2}$/;

  function dateUTC(iso) {
    var p = iso.split('-');
    return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
  }
  function ajouterJours(iso, n) {
    var d = dateUTC(iso);
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
  }

  /* Date de référence : le jour du visiteur, ou ?date=AAAA-MM-JJ. */
  function dateReference() {
    var p = C.parametres();
    var d = p ? p.get('date') : null;
    if (d && RE_DATE.test(d) && !isNaN(dateUTC(d).getTime())) return d;
    var j = new Date();
    return j.getFullYear() + '-' + ('0' + (j.getMonth() + 1)).slice(-2) + '-' + ('0' + j.getDate()).slice(-2);
  }

  var formats = {};
  function format(options) {
    var cle = JSON.stringify(options);
    if (!formats[cle]) {
      var o = { timeZone: 'UTC' };
      for (var k in options) o[k] = options[k];
      formats[cle] = new Intl.DateTimeFormat(C.locale, o);
    }
    return formats[cle];
  }
  /* Assemble les morceaux d'Intl : « 1er » en français, pas de virgule. */
  function assembler(morceaux) {
    return morceaux.map(function (m) {
      if (m.type === 'day' && !EN && m.value === '1') return '1er';
      return m.value;
    }).join('').replace(/,/g, '');
  }
  function ecrire(iso, options) {
    return assembler(format(options).formatToParts(dateUTC(iso)));
  }
  function ecrirePlage(a, b, options) {
    if (a === b) return ecrire(a, options);
    var f = format(options);
    if (typeof f.formatRangeToParts === 'function') {
      return assembler(f.formatRangeToParts(dateUTC(a), dateUTC(b)));
    }
    return ecrire(a, options) + ' – ' + ecrire(b, options);
  }

  var OPT = {
    longue: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },  // samedi 14 novembre 2026
    jour: { weekday: 'long', day: 'numeric', month: 'long' },                     // samedi 14 novembre
    courte: { day: 'numeric', month: 'long' },                                    // 15 mars
    annee: { day: 'numeric', month: 'long', year: 'numeric' },                    // 7 novembre 2026
    mois: { month: 'long', year: 'numeric' }                                      // décembre 2026
  };

  /* Heure locale de la salle, sans conversion : « 20 h », « 19 h 30 » ; "8 pm", "7:30 pm". */
  function ecrireHeure(h) {
    var m = /^(\d{1,2}):(\d{2})$/.exec(h || '');
    if (!m) return '';
    var H = +m[1];
    var M = m[2];
    if (EN) return (H % 12 || 12) + (M === '00' ? '' : ':' + M) + NBSP + (H >= 12 ? 'pm' : 'am');
    return H + NBSP + 'h' + (M === '00' ? '' : NBSP + M);
  }

  /* ---------- 1. Règles --------------------------------------------------- */

  function unique(liste) {
    return liste.filter(function (v, i) { return v && liste.indexOf(v) === i; });
  }

  /* Un événement, prêt pour les règles : représentations triées, regroupées
     par jour, passées ou non à la date de référence. */
  function preparer(e, jour) {
    var reps = (e.representations || []).slice().sort(function (a, b) {
      return (a.date + (a.heure || '')).localeCompare(b.date + (b.heure || ''));
    }).map(function (r) {
      return {
        date: r.date,
        heure: r.heure || '',
        ville: C.choisir(r.ville || e.ville),
        pays: C.choisir(r.pays || e.pays),
        salle: C.choisir(r.salle || e.salle),
        lien: r.lien || '',
        passee: r.date < jour
      };
    });
    var jours = [];
    reps.forEach(function (r) {
      var d = jours[jours.length - 1];
      if (d && d.date === r.date && d.salle === r.salle) { if (r.heure) d.heures.push(r.heure); return; }
      jours.push({ date: r.date, heures: r.heure ? [r.heure] : [], ville: r.ville, pays: r.pays, salle: r.salle, lien: r.lien, passee: r.passee });
    });
    var aVenir = reps.filter(function (r) { return !r.passee; });
    return {
      source: e,
      id: e.id,
      type: e.type,
      titre: C.choisir(e.titre),
      ensemble: C.choisir(e.ensemble),
      tempsFort: !!e.tempsFort,
      lien: e.lien || '',
      mentions: C.choisir(e.mentions),
      reps: reps,
      jours: jours,
      villes: unique(reps.map(function (r) { return r.ville; })),
      premiere: reps.length ? reps[0].date : '',
      derniere: reps.length ? reps[reps.length - 1].date : '',
      prochaine: aVenir[0] || null,
      passe: aVenir.length === 0          // bascule le lendemain de la dernière représentation
    };
  }

  /* calculer(donnees, { date }) : tout ce qu'une page a besoin de savoir.
     { date, prochain, affiche[], saison[{ mois, evenements[] }], derniers[], vide } */
  function calculer(donnees, options) {
    var jour = (options && options.date) || dateReference();
    var limite = ajouterJours(jour, JOURS_AFFICHE);
    var tous = ((donnees && donnees.evenements) || []).map(function (e) { return preparer(e, jour); })
      .filter(function (ev) { return ev.reps.length; });

    var aVenir = tous.filter(function (ev) { return !ev.passe; }).sort(function (a, b) {
      return (a.prochaine.date + a.prochaine.heure + a.id).localeCompare(b.prochaine.date + b.prochaine.heure + b.id);
    });
    var passes = tous.filter(function (ev) { return ev.passe; }).sort(function (a, b) {
      return (b.derniere + b.id).localeCompare(a.derniere + a.id);
    });

    /* À l'affiche : une représentation dans les 30 jours ; entre 2 et 4 fiches. */
    var nb = aVenir.filter(function (ev) { return ev.prochaine.date <= limite; }).length;
    nb = Math.min(MAX_AFFICHE, Math.max(nb, Math.min(MIN_AFFICHE, aVenir.length)));
    var affiche = aVenir.slice(0, nb);

    /* La saison : le reste, groupé par mois de la prochaine représentation. */
    var saison = [];
    aVenir.slice(nb).forEach(function (ev) {
      var cle = ev.prochaine.date.slice(0, 7);
      var g = saison[saison.length - 1];
      if (!g || g.cle !== cle) { g = { cle: cle, mois: ecrire(cle + '-01', OPT.mois), evenements: [] }; saison.push(g); }
      g.evenements.push(ev);
    });

    /* Prochain concert : la prochaine représentation (un disque n'est pas un concert). */
    var prochain = null;
    var concerts = aVenir.filter(function (ev) { return ev.type !== 'disque'; });
    if (concerts.length) {
      var ev = concerts[0];
      var r = ev.prochaine;
      var soir = ev.reps.some(function (x) { return x.date === r.date && parseInt(x.heure, 10) >= HEURE_SOIR; });
      prochain = {
        evenement: ev,
        representation: r,
        ceSoir: r.date === jour && soir,
        demain: r.date === ajouterJours(jour, 1)
      };
    }

    return {
      date: jour,
      prochain: prochain,
      affiche: affiche,
      saison: saison,
      derniers: passes.slice(0, NB_DERNIERS),
      vide: aVenir.length === 0
    };
  }

  /* ---------- 2. Rendu de l'accueil -------------------------------------- */

  var h = C.html;

  function etiquettes(ev) {
    var s = '';
    if (ev.type === 'disque') s += '<span class="etiquette">' + h(C.t('agenda.disque')) + '</span>';
    if (ev.tempsFort) s += '<span class="etiquette">' + h(C.t('agenda.tempsFort')) + '</span>';
    return s;
  }

  function lienInfos(url) {
    return url ? '<a class="lien" href="' + C.echapper(url) + '">' + h(C.t('agenda.lien')) + '</a>' : '';
  }

  function titreLie(ev, lie) {
    return (lie && ev.lien) ? '<a href="' + C.echapper(ev.lien) + '">' + h(ev.titre) + '</a>' : h(ev.titre);
  }

  /* Ville(s) d'un événement pour une ligne compacte : « Paris », « Cologne, Bâle, Munich », « Disque ». */
  function villes(ev) {
    if (ev.type === 'disque') return C.t('agenda.disque');
    return ev.villes.join(', ');
  }

  function lieu(d) {
    return [d.ville, d.pays].filter(Boolean).join(', ');
  }

  /* Fiche détaillée (À l'affiche) */
  function fiche(ev) {
    var tournee = (ev.type === 'tournee');
    var s = '<article class="fiche' + (ev.tempsFort ? ' fiche--fort' : '') + '" data-revele>';
    var e = etiquettes(ev);
    if (e) s += '<p class="fiche__etiquettes">' + e + '</p>';

    s += '<ul class="fiche__dates">';
    ev.jours.forEach(function (d) {
      s += '<li' + (d.passee ? ' class="est-passee"' : '') + '><span class="fiche__jour">' + h(ecrire(d.date, OPT.longue)) + '</span>';
      if (d.heures.length) s += ' <span class="fiche__heure">' + d.heures.map(ecrireHeure).join(', ') + '</span>';
      if (tournee) {
        s += '<span class="fiche__etape">' + h(lieu(d)) + (d.salle ? ' · ' + h(d.salle) : '') + '</span>';
        if (d.lien && !d.passee) s += lienInfos(d.lien);
      }
      s += '</li>';
    });
    s += '</ul>';

    s += '<h4 class="fiche__titre">' + titreLie(ev, ev.type === 'disque') + '</h4>';

    if (ev.type === 'opera' && ev.reps.length > 1) {
      s += '<p class="fiche__serie">' + h(C.t('agenda.serie', { n: ev.reps.length, date: ecrire(ev.prochaine.date, OPT.courte) })) + '</p>';
    }
    if (tournee) {
      s += '<p class="fiche__serie">' + h(C.t('agenda.tournee', { n: ev.villes.length })) + '</p>';
    }

    if (!tournee && ev.type !== 'disque') {
      var premier = ev.jours[0];
      s += '<p class="fiche__lieu">' + h(lieu(premier)) + (premier.salle ? '<br>' + h(premier.salle) : '') + '</p>';
    }
    if (ev.ensemble && ev.titre.toLowerCase().indexOf(ev.ensemble.toLowerCase()) < 0) {
      s += '<p class="fiche__ensemble">' + h(ev.ensemble) + '</p>';
    }

    var prog = ev.source.programme || [];
    if (prog.length) {
      s += '<p class="fiche__label">' + h(C.t('agenda.programme')) + '</p><ul class="fiche__liste">';
      prog.forEach(function (p) {
        var m = C.choisir(p.mention);
        s += '<li>' + h(C.choisir(p.compositeur)) + ', ' + h(C.choisir(p.oeuvre)) +
          (m ? ', <span class="fiche__creation">' + h(m) + '</span>' : '') + '</li>';
      });
      s += '</ul>';
    }
    var avec = ev.source.avec || [];
    if (avec.length) {
      s += '<p class="fiche__label">' + h(C.t('agenda.avec')) + '</p><ul class="fiche__liste">';
      avec.forEach(function (a) {
        var r = C.choisir(a.role);
        s += '<li>' + h(a.nom) + (r ? ', ' + h(r) : '') + '</li>';
      });
      s += '</ul>';
    }
    if (ev.mentions) s += '<p class="fiche__note">' + h(ev.mentions) + '</p>';
    if (ev.type !== 'disque') s += lienInfos(ev.lien);
    return s + '</article>';
  }

  /* Ligne compacte (La saison, Derniers concerts) */
  function ligne(ev, options) {
    var fort = options.fort && ev.tempsFort;
    var quand = ecrirePlage(ev.premiere, ev.derniere, options.annee ? OPT.annee : OPT.courte);
    return '<li class="ligne' + (fort ? ' ligne--fort' : '') + '">' +
      '<span class="ligne__quand">' + h(quand) + '</span>' +
      '<span class="ligne__ville">' + h(villes(ev)) + '</span>' +
      '<span class="ligne__titre">' + titreLie(ev, options.lie || ev.type === 'disque') +
      (fort ? ' <span class="etiquette">' + h(C.t('agenda.tempsFort')) + '</span>' : '') + '</span>' +
      '</li>';
  }

  function htmlAgenda(etat) {
    var s = '';
    if (etat.vide) {
      s += '<p class="agenda__vide" data-revele>' + h(C.t('agenda.vide')) + '</p>';
    } else {
      s += '<div class="bloc bloc--affiche"><h3 class="bloc__titre" data-revele>' + h(C.t('agenda.affiche')) + '</h3>' +
        '<div class="fiches fiches--' + etat.affiche.length + '">' + etat.affiche.map(fiche).join('') + '</div></div>';
      if (etat.saison.length) {
        s += '<div class="bloc bloc--saison"><h3 class="bloc__titre" data-revele>' + h(C.t('agenda.saison')) + '</h3><div class="saison">';
        etat.saison.forEach(function (g) {
          s += '<div class="mois" data-revele><h4 class="mois__nom">' + h(g.mois) + '</h4><ul class="mois__liste">' +
            g.evenements.map(function (ev) { return ligne(ev, { fort: true, lie: true }); }).join('') + '</ul></div>';
        });
        s += '</div></div>';
      }
    }
    if (etat.derniers.length) {
      s += '<div class="bloc bloc--derniers"><h3 class="bloc__titre" data-revele>' + h(C.t('agenda.derniers')) + '</h3>' +
        '<ul class="derniers" data-revele>' +
        etat.derniers.map(function (ev) { return ligne(ev, { annee: true }); }).join('') + '</ul>' +
        '<p class="bloc__suite" data-revele><a class="inerte" role="link" aria-disabled="true">' + h(C.t('agenda.archives')) + '</a></p></div>';
    }
    return s;
  }

  /* Ligne « Prochain concert » de l'ouverture :
     date · ville, salle · titre (la salle : son premier segment). */
  function htmlProchain(etat) {
    var lien = '<a class="prochain__lien" href="#agenda">' + h(C.t('ouverture.voirAgenda')) + '</a>';
    var p = etat.prochain;
    if (!p) return lien;
    var r = p.representation;
    var quand = p.ceSoir ? C.t('ouverture.ceSoir') : p.demain ? C.t('ouverture.demain') : ecrire(r.date, OPT.jour);
    var ou = [r.ville, (r.salle || '').split(',')[0]].filter(Boolean).join(', ');
    return '<span class="prochain__label">' + h(C.t('ouverture.prochain')) + '</span>' +
      '<span class="prochain__ligne">' + [quand, ou, p.evenement.titre].filter(Boolean).map(h).join(' · ') + '</span>' + lien;
  }

  function rendreAccueil(options) {
    var donnees = window.AGENDA_DONNEES;
    if (!donnees) return null;
    var etat = calculer(donnees, options);
    var zone = document.querySelector('[data-agenda]');
    if (zone) zone.innerHTML = htmlAgenda(etat);
    var prochain = document.querySelector('[data-prochain]');
    if (prochain) prochain.innerHTML = htmlProchain(etat);
    return etat;
  }

  window.SiteAgenda = {
    dateReference: dateReference,
    calculer: calculer,
    ecrireDate: ecrire,
    ecrirePlage: ecrirePlage,
    ecrireHeure: ecrireHeure,
    formats: OPT,
    rendreAccueil: rendreAccueil,
    etat: null
  };
  /* rendreAccueil() garde le dernier état calculé (contrôles, autres scripts). */
  var rendre = rendreAccueil;
  window.SiteAgenda.rendreAccueil = function (options) {
    var etat = rendre(options);
    window.SiteAgenda.etat = etat;
    return etat;
  };
})();
