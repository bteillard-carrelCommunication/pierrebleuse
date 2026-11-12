/* ==========================================================================
   agenda.js · règles et rendu de l'agenda

   Données : window.AGENDA_DONNEES (data/agenda.js, généré à partir de
   data/agenda.json, qui reste la source). Textes : window.SiteContenu.

   1. Règles et formats (réutilisables par d'autres pages, sans DOM) :
      SiteAgenda.dateReference(), calculer(donnees, { date }), formats de
      dates et d'heures.
   2. Rendu de l'accueil : SiteAgenda.rendreAccueil() remplit [data-agenda] :
      les quatre prochaines dates en fiches (chacune avec « En savoir plus »),
      les suivantes en tableau (« Les concerts »), le bouton « Tous les
      concerts ». Les concerts passés ne sont plus sur l'accueil : ils sont
      sur concerts.html. Rendu synchrone, avant les scripts du moteur, pour
      que les [data-revele] soient recensés par main.js.
   3. Rendu de concerts.html : SiteAgenda.rendrePage() remplit [data-concerts]
      (tous les concerts, à venir puis passés, en tableaux ; chaque ligne porte
      l'ancre c-<id> que visent les « En savoir plus » sans adresse connue).

   « Temps fort » : le drapeau tempsFort reste dans les données et dans l'état
   calculé (ev.tempsFort, classe fiche--fort, attribut data-temps-fort), mais
   il n'a plus d'affichage : ni étiquette ni titre agrandi.
   ========================================================================== */
(function () {
  'use strict';

  var C = window.SiteContenu;
  if (!C) return;

  var NBSP = ' ';
  var EN = (C.langue === 'en');
  var NB_AFFICHE = 4;       // « À l'affiche » : les quatre prochaines dates, de la prochaine vers les suivantes
  var NB_DERNIERS = 4;      // derniers concerts gardés dans l'état calculé (plus affichés sur l'accueil)
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
     { date, prochain, affiche[], saison[{ mois, evenements[] }], derniers[], aVenir[], passes[], vide } */
  function calculer(donnees, options) {
    var jour = (options && options.date) || dateReference();
    var tous = ((donnees && donnees.evenements) || []).map(function (e) { return preparer(e, jour); })
      .filter(function (ev) { return ev.reps.length; });

    var aVenir = tous.filter(function (ev) { return !ev.passe; }).sort(function (a, b) {
      return (a.prochaine.date + a.prochaine.heure + a.id).localeCompare(b.prochaine.date + b.prochaine.heure + b.id);
    });
    var passes = tous.filter(function (ev) { return ev.passe; }).sort(function (a, b) {
      return (b.derniere + b.id).localeCompare(a.derniere + a.id);
    });

    /* À l'affiche : les quatre prochaines dates. */
    var nb = Math.min(NB_AFFICHE, aVenir.length);
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
      aVenir: aVenir,
      passes: passes,
      vide: aVenir.length === 0
    };
  }

  /* ---------- 2. Rendu de l'accueil -------------------------------------- */

  var h = C.html;
  var sortant = C.lienSortant;

  /* Étiquettes d'une fiche. « Temps fort » n'est plus affiché (le drapeau reste dans les données). */
  function etiquettes(ev) {
    var s = '';
    if (ev.type === 'disque') s += '<span class="etiquette">' + h(C.t('agenda.disque')) + '</span>';
    return s;
  }

  function lienInfos(url) {
    return url ? sortant(url, h(C.t('agenda.lien')), 'lien') : '';
  }

  function titreLie(ev, lie) {
    return (lie && ev.lien) ? sortant(ev.lien, h(ev.titre)) : h(ev.titre);
  }

  function lieu(d) {
    return [d.ville, d.pays].filter(Boolean).join(', ');
  }

  /* Lien d'un événement : le sien, sinon celui de sa prochaine date (tournée). */
  function lienDe(ev) {
    if (ev.lien) return ev.lien;
    var avec = ev.jours.filter(function (d) { return d.lien && !d.passee; })[0] || ev.jours.filter(function (d) { return d.lien; })[0];
    return avec ? avec.lien : '';
  }

  /* « En savoir plus » d'une fiche : la billetterie ou la salle quand l'adresse est connue ;
     sinon la ligne de l'événement dans le tableau de concerts.html (une tournée y renvoie
     toujours : ses dates portent déjà chacune leur lien). */
  function lienPlus(ev) {
    var url = ev.lien || (ev.type === 'tournee' ? '' : lienDe(ev));
    if (url) return sortant(url, h(C.t('agenda.plus')), 'lien');
    return '<a class="lien" href="concerts.html#c-' + C.echapper(ev.id) + '">' + h(C.t('agenda.plus')) + '</a>';
  }

  /* Fiche détaillée (À l'affiche) : trois colonnes de la matrice */
  function fiche(ev) {
    var tournee = (ev.type === 'tournee');
    var s = '<article class="fiche bloc-quart' + (ev.tempsFort ? ' fiche--fort' : '') + '" data-revele>';
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

    s += '<h4 class="fiche__titre">' + h(ev.titre) + '</h4>';

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
    s += lienPlus(ev);
    return s + '</article>';
  }

  /* Cellules des tableaux */
  function celluleLieu(ev) {
    if (ev.type === 'disque') return '<td class="tableau__lieu">' + h(C.t('agenda.disque')) + '</td>';
    var vus = [];
    ev.jours.forEach(function (d) {
      var l = [d.pays, d.ville].filter(Boolean).join(', ');        /* pays, puis ville */
      if (l && vus.indexOf(l) < 0) vus.push(l);
    });
    return '<td class="tableau__lieu">' + vus.map(h).join('<br>') + '</td>';
  }
  function celluleSalle(ev) {
    var vus = [];
    ev.jours.forEach(function (d) { if (d.salle && vus.indexOf(d.salle) < 0) vus.push(d.salle); });
    return '<td class="tableau__lieu">' + vus.map(h).join('<br>') + '</td>';
  }
  /* orchestre + infos : titre, ensemble, programme sur une ligne */
  function celluleInfos(ev) {
    var s = '<td><span class="tableau__titre">' + titreLie(ev, ev.type === 'disque') + '</span>';
    if (ev.ensemble && ev.titre.toLowerCase().indexOf(ev.ensemble.toLowerCase()) < 0) {
      s += '<span class="tableau__ensemble">' + h(ev.ensemble) + '</span>';
    }
    var prog = (ev.source.programme || []).map(function (p) {
      var m = C.choisir(p.mention);
      return h(C.choisir(p.compositeur)) + ', ' + h(C.choisir(p.oeuvre)) + (m ? ' (' + h(m) + ')' : '');
    });
    if (prog.length) s += '<span class="tableau__programme">' + prog.join(' · ') + '</span>';
    else if (ev.mentions) s += '<span class="tableau__programme">' + h(ev.mentions) + '</span>';
    return s + '</td>';
  }
  /* « En savoir plus » : vers la billetterie ou la salle, quand l'adresse est connue */
  function celluleAction(ev) {
    var url = ev.type === 'disque' ? '' : lienDe(ev);
    return '<td class="tableau__action">' + (url ? sortant(url, h(C.t('agenda.plus')), 'lien') : '') + '</td>';
  }
  function enTete(cles) {
    return '<thead><tr>' + cles.map(function (c) {
      return '<th scope="col">' + (c === 'agenda.col.lien' ? '<span class="sr-only">' + h(C.t(c)) + '</span>' : h(C.t(c))) + '</th>';
    }).join('') + '</tr></thead>';
  }
  function colonnes(largeurs) {
    return '<colgroup>' + largeurs.map(function (l) { return l ? '<col style="width:' + l + '">' : '<col>'; }).join('') + '</colgroup>';
  }

  /* Les dates suivantes : Mois | Date | Pays, ville | Orchestre, programme | En savoir plus */
  function tableauSaison(groupes) {
    var s = '<table class="tableau tableau--saison bloc-plein" data-revele><caption class="sr-only">' + h(C.t('agenda.saison')) + '</caption>' +
      colonnes(['15%', '16%', '18%', '', '12%']) +
      enTete(['agenda.col.mois', 'agenda.col.date', 'agenda.col.lieu', 'agenda.col.infos', 'agenda.col.lien']) + '<tbody>';
    groupes.forEach(function (g) {
      g.evenements.forEach(function (ev, i) {
        var quand = ev.premiere === ev.derniere ? ecrire(ev.premiere, OPT.jour) : ecrirePlage(ev.premiere, ev.derniere, OPT.courte);
        s += '<tr' + (ev.tempsFort ? ' data-temps-fort' : '') + '>' + (i === 0 ? '<th scope="rowgroup" rowspan="' + g.evenements.length + '" class="tableau__mois">' + h(g.mois) + '</th>' : '') +
          '<td class="tableau__date">' + h(quand) + '</td>' + celluleLieu(ev) + celluleInfos(ev) + celluleAction(ev) + '</tr>';
      });
    });
    return s + '</tbody></table>';
  }

  function rang(cle, contenu, classe) {
    return '<div class="grille grille--titre"><h3 class="rang__titre bloc-plein" data-revele>' + h(C.t(cle)) + '</h3></div>' +
      '<div class="grille' + (classe ? ' ' + classe : '') + '">' + contenu + '</div>';
  }

  function htmlAgenda(etat) {
    var s = '';
    if (etat.vide) {
      s += '<div class="grille"><p class="agenda__vide bloc-plein" data-revele>' + h(C.t('agenda.vide')) + '</p></div>';
    } else {
      s += rang('agenda.affiche', etat.affiche.map(fiche).join(''), 'fiches');
      if (etat.saison.length) s += rang('agenda.saison', tableauSaison(etat.saison));
    }
    s += '<div class="grille"><p class="rang__suite bloc-droite" data-revele><a class="bouton" href="concerts.html">' + h(C.t('agenda.tous')) + '</a></p></div>';
    return s;
  }

  function rendreAccueil(options) {
    var donnees = window.AGENDA_DONNEES;
    if (!donnees) return null;
    var etat = calculer(donnees, options);
    var zone = document.querySelector('[data-agenda]');
    if (zone) zone.innerHTML = htmlAgenda(etat);
    return etat;
  }

  /* ---------- 3. Rendu de concerts.html : tous les concerts ---------------- */
  /* Date | Pays, ville | Salle | Orchestre, programme | En savoir plus */
  function tableauComplet(evenements, cle, classe) {
    var s = '<table class="tableau ' + classe + '"><caption class="sr-only">' + h(C.t(cle)) + '</caption>' +
      colonnes(['17%', '15%', '19%', '', '11%']) +
      enTete(['agenda.col.date', 'agenda.col.lieu', 'agenda.col.salle', 'agenda.col.infos', 'agenda.col.lien']) + '<tbody>';
    evenements.forEach(function (ev) {
      s += '<tr id="c-' + C.echapper(ev.id) + '"' + (ev.tempsFort ? ' data-temps-fort' : '') + '><td class="tableau__date">' + h(ecrirePlage(ev.premiere, ev.derniere, OPT.annee)) + '</td>' +
        celluleLieu(ev) + celluleSalle(ev) + celluleInfos(ev) + celluleAction(ev) + '</tr>';
    });
    return s + '</tbody></table>';
  }
  function rendrePage(options) {
    var donnees = window.AGENDA_DONNEES;
    var zone = document.querySelector('[data-concerts]');
    if (!donnees || !zone) return null;
    var etat = calculer(donnees, options);
    var s = '';
    if (etat.aVenir.length) s += '<h2 class="rang__titre">' + h(C.t('concerts.avenir')) + '</h2>' + tableauComplet(etat.aVenir, 'concerts.avenir', 'tableau--avenir');
    else s += '<p class="agenda__vide">' + h(C.t('agenda.vide')) + '</p>';
    if (etat.passes.length) s += '<h2 class="rang__titre">' + h(C.t('concerts.passes')) + '</h2>' + tableauComplet(etat.passes, 'concerts.passes', 'tableau--passes');
    zone.innerHTML = s;
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
    rendrePage: rendrePage,
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
