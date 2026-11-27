/* ==========================================================================
   agenda.js · règles et rendu de l'agenda

   Données : window.AGENDA_DONNEES (data/agenda.js, généré à partir de
   data/agenda.json, qui reste la source). Textes : window.SiteContenu.

   1. Règles et formats (réutilisables par d'autres pages, sans DOM) :
      SiteAgenda.dateReference(), calculer(donnees, { date }), formats de
      dates et d'heures.
   2. Rendu de l'accueil : SiteAgenda.rendreAccueil() remplit [data-agenda] :
      les quatre prochains concerts publiés, dans l'ordre des dates, en fiches,
      les suivants en tableau (« Les concerts »), le bouton « Tous les
      concerts ». « En savoir plus » mène à l'adresse externe du concert
      (billetterie ou page de l'organisateur) quand Pupitre en donne une ;
      sinon à la fiche du concert, sur concerts.html : jamais de lien mort. Le
      titre de la fiche mène toujours à cette fiche interne. Les concerts
      passés ne sont pas sur l'accueil : ils sont sur concerts.html. Rendu
      synchrone, avant les scripts du moteur, pour que les [data-revele]
      soient recensés par main.js.
   3. Rendu de concerts.html : SiteAgenda.rendrePage() remplit [data-concerts]
      (tous les concerts, à venir puis passés, en tableaux, la distinction se
      calculant sur la date du jour). Chaque ligne porte l'ancre c-<id> et se
      déplie en fiche du concert : dates, lieu, programme, participants,
      présentation, lien externe, et la revue de presse du concert quand des
      articles publiés lui sont associés. L'adresse concerts.html#c-<id>
      ouvre la fiche d'elle-même.

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

  /* Adresse de la fiche interne d'un concert : sa ligne dans le tableau de concerts.html, qui se
     déplie à l'arrivée. */
  function adresseFiche(ev) {
    return 'concerts.html#c-' + encodeURIComponent(ev.id);
  }
  /* « En savoir plus » d'une fiche : l'adresse externe du concert (billetterie, sinon page de
     l'organisateur) quand Pupitre en donne une ; sinon la fiche interne du concert (une tournée
     y renvoie toujours : ses dates portent déjà chacune leur lien). Jamais de lien inactif. */
  function lienPlus(ev) {
    var url = ev.lien || (ev.type === 'tournee' ? '' : lienDe(ev));
    if (url) return sortant(url, h(C.t('agenda.plus')), 'lien');
    return '<a class="lien" href="' + C.echapper(adresseFiche(ev)) + '">' + h(C.t('agenda.plus')) + '</a>';
  }

  /* Fiche détaillée (À l'affiche) : trois colonnes de la matrice. Dans l'ordre (13/11/2026) :
     titre, date, heure, lieu, salle, orchestre, programme, avec, mentions, lien. */
  function fiche(ev) {
    var tournee = (ev.type === 'tournee');
    var s = '<article class="fiche bloc-quart' + (ev.tempsFort ? ' fiche--fort' : '') + '" data-revele>';
    /* le titre mène à la fiche interne du concert, que « En savoir plus » mène ailleurs ou non */
    s += '<h4 class="fiche__titre"><a class="fiche__vers" href="' + C.echapper(adresseFiche(ev)) + '" title="' + C.echapper(C.brut(C.t('concerts.detail'))) + '">' + h(ev.titre) + '</a></h4>';
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
    if (ev.type === 'opera' && ev.reps.length > 1) {
      s += '<p class="fiche__serie">' + h(C.t('agenda.serie', { n: ev.reps.length, date: ecrire(ev.prochaine.date, OPT.courte) })) + '</p>';
    }
    if (tournee) {
      s += '<p class="fiche__serie">' + h(C.t('agenda.tournee', { n: ev.villes.length })) + '</p>';
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
  /* orchestre + infos : titre, ensemble, programme sur une ligne. $fiche (page des concerts) : le
     titre est un bouton qui déplie la fiche du concert, sous la ligne. */
  function celluleInfos(ev, fiche) {
    var titre = fiche
      ? '<button type="button" class="tableau__ouvrir" data-fiche="' + C.echapper(ev.id) + '" aria-expanded="false" aria-controls="f-' + C.echapper(ev.id) + '" title="' + C.echapper(C.brut(C.t('concerts.detail'))) + '">' + h(ev.titre) + '</button>'
      : titreLie(ev, ev.type === 'disque');
    var s = '<td><span class="tableau__titre">' + titre + '</span>';
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
  /* « En savoir plus » : vers la billetterie ou la salle, quand l'adresse est connue. $fiche (page
     des concerts) : pour un disque, vers sa page ; pour un concert passé qui a des articles publiés,
     « Revue de presse de ce concert », qui déplie sa fiche à cet endroit. */
  function celluleAction(ev, fiche) {
    var url = ev.type === 'disque' ? (fiche ? ev.lien : '') : lienDe(ev);
    var s = url ? sortant(url, h(C.t('agenda.plus')), 'lien') : '';
    if (fiche && ev.passe && articlesDe(ev).length) {
      s += '<button type="button" class="lien tableau__presse" data-fiche="' + C.echapper(ev.id) + '" data-fiche-vers="presse">' + h(C.t('concerts.presse')) + '</button>';
    }
    return '<td class="tableau__action">' + s + '</td>';
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
  /* Une barre de recherche, de filtres et de tri (13/11/2026), puis deux tableaux, à venir et
     passés : Date | Pays, ville | Salle | Orchestre, programme | En savoir plus.
     La recherche porte sur tous les champs : titre, dates, pays, ville, salle, orchestre,
     programme, solistes, mentions. Sans accents ni majuscules. */
  function sansAccent(s) {
    s = String(s || '').toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s;
  }
  function valeurs(liste) { return liste.filter(function (v, i) { return v && liste.indexOf(v) === i; }); }
  /* Ce qu'on cherche et ce qu'on trie dans un événement, calculé une fois */
  function indexer(ev) {
    if (ev.index) return ev.index;
    var src = ev.source;
    var morceaux = [ev.titre, ev.ensemble, ev.mentions, ecrirePlage(ev.premiere, ev.derniere, OPT.annee), ev.type === 'disque' ? C.t('agenda.disque') : ''];
    ev.jours.forEach(function (d) { morceaux.push(d.pays, d.ville, d.salle, ecrire(d.date, OPT.longue)); });
    (src.programme || []).forEach(function (p) { morceaux.push(C.choisir(p.compositeur), C.choisir(p.oeuvre), C.choisir(p.mention)); });
    (src.avec || []).forEach(function (a) { morceaux.push(a.nom, C.choisir(a.role)); });
    ev.index = {
      texte: sansAccent(morceaux.filter(Boolean).join(' · ')),
      pays: valeurs(ev.jours.map(function (d) { return d.pays; })),
      villes: valeurs(ev.jours.map(function (d) { return d.ville; })),
      salles: valeurs(ev.jours.map(function (d) { return d.salle; }))
    };
    return ev.index;
  }
  var filtres = { q: '', pays: '', ville: '', salle: '', ensemble: '', tri: 'date' };
  function retenu(ev) {
    var x = indexer(ev);
    if (filtres.pays && x.pays.indexOf(filtres.pays) < 0) return false;
    if (filtres.ville && x.villes.indexOf(filtres.ville) < 0) return false;
    if (filtres.salle && x.salles.indexOf(filtres.salle) < 0) return false;
    if (filtres.ensemble && ev.ensemble !== filtres.ensemble) return false;
    return sansAccent(filtres.q).split(/\s+/).filter(Boolean).every(function (mot) { return x.texte.indexOf(mot) >= 0; });
  }
  /* « date » garde l'ordre de l'agenda : à venir du plus proche au plus lointain, passés du plus
     récent au plus ancien. Les autres tris valent pour les deux tableaux. */
  function trier(evenements) {
    var cle = {
      lieu: function (ev) { var d = ev.jours[0] || {}; return [d.pays, d.ville].join(' '); },
      salle: function (ev) { return (ev.jours[0] || {}).salle || ''; },
      ensemble: function (ev) { return ev.ensemble; },
      titre: function (ev) { return ev.titre; }
    }[filtres.tri];
    var liste = evenements.slice();
    if (filtres.tri === 'inverse') return liste.reverse();
    if (!cle) return liste;
    var rang = {};
    liste.forEach(function (ev, i) { rang[ev.id] = i; });
    return liste.sort(function (a, b) {
      var x = sansAccent(cle(a)), y = sansAccent(cle(b));
      if (!x !== !y) return x ? -1 : 1;                       /* les champs vides à la fin */
      return x.localeCompare(y, C.locale) || rang[a.id] - rang[b.id];
    });
  }
  function choix(nom, cle, liste, tous) {
    var options = '<option value="">' + h(C.t(tous)) + '</option>' + liste.map(function (v) {
      return '<option value="' + C.echapper(v) + '">' + h(v) + '</option>';
    }).join('');
    return '<label class="filtres__champ"><span>' + h(C.t(cle)) + '</span><select data-filtre="' + nom + '">' + options + '</select></label>';
  }
  function barreFiltres(etat) {
    var tous = etat.aVenir.concat(etat.passes);
    var ordre = function (a, b) { return sansAccent(a).localeCompare(sansAccent(b), C.locale); };
    var dans = function (champ) { return valeurs([].concat.apply([], tous.map(function (ev) { return indexer(ev)[champ]; }))).sort(ordre); };
    var tris = ['date', 'inverse', 'lieu', 'salle', 'ensemble', 'titre'];
    return '<form class="filtres" data-filtres role="search" aria-label="' + C.echapper(C.brut(C.t('concerts.chercher'))) + '">' +
      '<label class="filtres__champ filtres__champ--large"><span>' + h(C.t('concerts.chercher')) + '</span>' +
      '<input type="search" data-filtre="q" autocomplete="off" placeholder="' + C.echapper(C.brut(C.t('concerts.chercher.indice'))) + '"></label>' +
      choix('pays', 'concerts.pays', dans('pays'), 'concerts.tous') +
      choix('ville', 'concerts.ville', dans('villes'), 'concerts.toutes') +
      choix('salle', 'agenda.col.salle', dans('salles'), 'concerts.toutes') +
      choix('ensemble', 'concerts.orchestre', valeurs(tous.map(function (ev) { return ev.ensemble; })).sort(ordre), 'concerts.tous') +
      '<label class="filtres__champ"><span>' + h(C.t('concerts.trier')) + '</span><select data-filtre="tri">' +
      tris.map(function (t) { return '<option value="' + t + '">' + h(C.t('concerts.tri.' + t)) + '</option>'; }).join('') + '</select></label>' +
      '<button type="reset" class="bouton filtres__effacer">' + h(C.t('concerts.effacer')) + '</button>' +
      '</form><p class="filtres__compte" data-compte role="status" aria-live="polite"></p>';
  }
  function tableaux(etat) {
    var aVenir = trier(etat.aVenir.filter(retenu));
    var passes = trier(etat.passes.filter(retenu));
    var s = '';
    if (aVenir.length) s += '<h2 class="rang__titre">' + h(C.t('concerts.avenir')) + '</h2>' + tableauComplet(aVenir, 'concerts.avenir', 'tableau--avenir');
    else if (!etat.aVenir.length) s += '<p class="agenda__vide">' + h(C.t('agenda.vide')) + '</p>';
    if (passes.length) s += '<h2 class="rang__titre">' + h(C.t('concerts.passes')) + '</h2>' + tableauComplet(passes, 'concerts.passes', 'tableau--passes');
    var n = aVenir.length + passes.length;
    if (!n && (etat.aVenir.length || etat.passes.length)) s += '<p class="agenda__vide">' + h(C.t('concerts.aucun')) + '</p>';
    return { html: s, compte: n };
  }
  function tableauComplet(evenements, cle, classe) {
    var s = '<table class="tableau ' + classe + '"><caption class="sr-only">' + h(C.t(cle)) + '</caption>' +
      colonnes(['17%', '15%', '19%', '', '11%']) +
      enTete(['agenda.col.date', 'agenda.col.lieu', 'agenda.col.salle', 'agenda.col.infos', 'agenda.col.lien']) + '<tbody>';
    evenements.forEach(function (ev) {
      s += '<tr id="c-' + C.echapper(ev.id) + '"' + (ev.tempsFort ? ' data-temps-fort' : '') + '><td class="tableau__date">' + h(ecrirePlage(ev.premiere, ev.derniere, OPT.annee)) + '</td>' +
        celluleLieu(ev) + celluleSalle(ev) + celluleInfos(ev, true) + celluleAction(ev, true) + '</tr>' +
        '<tr class="tableau__fiche" id="f-' + C.echapper(ev.id) + '" hidden><td colspan="5"></td></tr>';
    });
    return s + '</tbody></table>';
  }

  /* ---------- 3. Fiche interne d'un concert ------------------------------- */
  /* Articles de presse publiés associés à un concert : data/presse.js les porte (window.PRESSE,
     chaque article nomme ses concerts). Rien si le fichier n'est pas chargé. */
  function articlesDe(ev) {
    var P = Array.isArray(window.PRESSE) ? window.PRESSE : [];
    return P.filter(function (a) { return Array.isArray(a.concerts) && a.concerts.indexOf(ev.id) >= 0; });
  }
  /* Le contenu de la fiche : tout ce que Pupitre sait du concert et qui est publié. */
  function ficheConcert(ev) {
    var src = ev.source;
    var tournee = (ev.type === 'tournee');
    var s = '<div class="concert-fiche"><div class="concert-fiche__colonne">';
    s += '<ul class="fiche__dates">';
    ev.jours.forEach(function (d) {
      s += '<li' + (d.passee ? ' class="est-passee"' : '') + '><span class="fiche__jour">' + h(ecrire(d.date, OPT.longue)) + '</span>';
      if (d.heures.length) s += ' <span class="fiche__heure">' + d.heures.map(ecrireHeure).join(', ') + '</span>';
      if (tournee || ev.jours.length > 1) {
        var ou = lieu(d) + (d.salle ? ' · ' + d.salle : '');
        if (tournee && ou) s += '<span class="fiche__etape">' + h(ou) + '</span>';
        if (d.lien && !d.passee && d.lien !== ev.lien) s += lienInfos(d.lien);
      }
      s += '</li>';
    });
    s += '</ul>';
    if (!tournee && ev.type !== 'disque' && ev.jours[0]) {
      s += '<p class="fiche__lieu">' + h(lieu(ev.jours[0])) + (ev.jours[0].salle ? '<br>' + h(ev.jours[0].salle) : '') + '</p>';
    }
    if (ev.ensemble) s += '<p class="fiche__ensemble">' + h(ev.ensemble) + '</p>';
    var url = lienDe(ev);
    if (url && !ev.passe) s += '<p class="concert-fiche__lien">' + sortant(url, h(C.t('agenda.lien')), 'lien') + '</p>';
    s += '</div><div class="concert-fiche__colonne">';
    var prog = src.programme || [];
    if (prog.length) {
      s += '<p class="fiche__label">' + h(C.t('agenda.programme')) + '</p><ul class="fiche__liste">';
      prog.forEach(function (p) {
        var m = C.choisir(p.mention);
        s += '<li>' + h(C.choisir(p.compositeur)) + ', ' + h(C.choisir(p.oeuvre)) + (m ? ', <span class="fiche__creation">' + h(m) + '</span>' : '') + '</li>';
      });
      s += '</ul>';
    }
    var avec = src.avec || [];
    if (avec.length) {
      s += '<p class="fiche__label">' + h(C.t('agenda.avec')) + '</p><ul class="fiche__liste">';
      avec.forEach(function (a) { var r = C.choisir(a.role); s += '<li>' + h(a.nom) + (r ? ', ' + h(r) : '') + '</li>'; });
      s += '</ul>';
    }
    if (ev.mentions) s += '<p class="fiche__note">' + h(ev.mentions) + '</p>';
    s += '</div><div class="concert-fiche__colonne concert-fiche__colonne--large">';
    var description = C.choisir(src.description);
    if (description) {
      s += '<p class="fiche__label">' + h(C.t('concerts.description')) + '</p>' +
        description.split(/\n\s*\n/).map(function (p) { return '<p class="concert-fiche__texte">' + h(p.trim()).replace(/\n/g, '<br>') + '</p>'; }).join('');
    }
    /* revue de presse du concert : les articles publiés qui lui sont associés */
    var articles = articlesDe(ev);
    if (articles.length) {
      s += '<div class="concert-fiche__presse" data-fiche-partie="presse"><p class="fiche__label">' + h(C.t('concerts.presse')) + '</p><ul class="concert-fiche__articles">';
      articles.forEach(function (a) {
        var citation = (a.citations && a.citations[0]) ? C.choisir(a.citations[0].texte) : (a.verbatim || '');
        s += '<li><a class="concert-fiche__article" href="presse.html#a-' + encodeURIComponent(a.id) + '">' +
          '<span class="concert-fiche__media">' + h(a.media || '') + '</span>' +
          (citation ? '<span class="concert-fiche__citation">' + h(citation) + '</span>' : (C.choisir(a.titre) ? '<span class="concert-fiche__titre">' + h(C.choisir(a.titre)) + '</span>' : '')) +
          '</a></li>';
      });
      s += '</ul></div>';
    }
    return s + '</div></div>';
  }
  function rendrePage(options) {
    var donnees = window.AGENDA_DONNEES;
    var zone = document.querySelector('[data-concerts]');
    if (!donnees || !zone) return null;
    var etat = calculer(donnees, options);
    zone.innerHTML = barreFiltres(etat) + '<div data-resultats></div>';
    var resultats = zone.querySelector('[data-resultats]');
    var compte = zone.querySelector('[data-compte]');
    var formulaire = zone.querySelector('[data-filtres]');
    var parId = {};
    etat.aVenir.concat(etat.passes).forEach(function (ev) { parId[ev.id] = ev; });
    var ouvertes = {};                 /* fiches dépliées : elles le restent quand on filtre ou qu'on trie */
    function ligneFiche(id) { return document.getElementById('f-' + id); }
    /* Déplie ou replie la fiche d'un concert. vers : 'presse' pour amener sa revue de presse à l'écran. */
    function ouvrir(id, oui, vers) {
      var ligne = ligneFiche(id);
      var ev = parId[id];
      if (!ligne || !ev) return;
      if (oui && !ligne.firstChild.firstChild) ligne.firstChild.innerHTML = ficheConcert(ev);
      ligne.hidden = !oui;
      if (oui) ouvertes[id] = true; else delete ouvertes[id];
      var bouton = resultats.querySelector('.tableau__ouvrir[data-fiche="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]');
      if (bouton) bouton.setAttribute('aria-expanded', oui ? 'true' : 'false');
      var rang = document.getElementById('c-' + id);
      if (rang) rang.classList.toggle('est-ouverte', oui);
      if (oui && vers) {
        var partie = ligne.querySelector('[data-fiche-partie="' + vers + '"]');
        if (partie && partie.scrollIntoView) partie.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
    }
    function afficher() {
      var t = tableaux(etat);
      resultats.innerHTML = t.html;
      compte.textContent = C.brut(C.t(t.compte > 1 ? 'concerts.compte.n' : 'concerts.compte.un', { n: t.compte }));
      Object.keys(ouvertes).forEach(function (id) { ouvrir(id, true); });
    }
    resultats.addEventListener('click', function (e) {
      var b = e.target && e.target.closest ? e.target.closest('[data-fiche]') : null;
      if (!b) return;
      var id = b.getAttribute('data-fiche');
      var vers = b.getAttribute('data-fiche-vers');
      ouvrir(id, vers ? true : !ouvertes[id], vers);
    });
    /* concerts.html#c-<id> : la fiche de ce concert s'ouvre, sa ligne vient à l'écran */
    function ouvrirAncre() {
      var m = /^#c-(.+)$/.exec(window.location.hash || '');
      if (!m) return;
      var id = decodeURIComponent(m[1]);
      if (!parId[id]) return;
      if (!document.getElementById('c-' + id)) { formulaire.reset(); Object.keys(filtres).forEach(function (k) { filtres[k] = k === 'tri' ? 'date' : ''; }); afficher(); }
      ouvrir(id, true);
      var rang = document.getElementById('c-' + id);
      if (rang && rang.scrollIntoView) window.setTimeout(function () { rang.scrollIntoView({ block: 'start' }); }, 60);
    }
    window.addEventListener('hashchange', ouvrirAncre);
    function lire() {
      Array.prototype.forEach.call(formulaire.querySelectorAll('[data-filtre]'), function (c) { filtres[c.getAttribute('data-filtre')] = c.value; });
      afficher();
    }
    formulaire.addEventListener('input', lire);
    formulaire.addEventListener('change', lire);
    formulaire.addEventListener('submit', function (e) { e.preventDefault(); });
    formulaire.addEventListener('reset', function () { window.setTimeout(lire, 0); });
    afficher();
    ouvrirAncre();
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
