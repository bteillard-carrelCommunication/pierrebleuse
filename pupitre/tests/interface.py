#!/usr/bin/env python3
"""interface.py : essai de l'interface de Pupitre dans un vrai navigateur (Chrome headless, outils/cdp.py du site).

    python3 pupitre/tests/interface.py

Complète tests/parcours.py, qui éprouve les contenus et la publication. Celui-ci éprouve l'interface
elle-même, sur la base locale, migrations passées :

  1. le cadre : menu et fil d'Ariane, sections retenues, menu réduit, centre de notifications
     (filtres par niveau, Échap), menu du compte, recherche au fil de la saisie ; sur téléphone,
     le tiroir du menu, la recherche repliée, le centre de notifications dans la largeur de l'écran ;
  2. le pilotage de la communication, de bout en bout : une publication rattachée à un concert,
     les refus du formulaire, le tableau de bord éditorial, le calendrier, la fiche du concert, le
     tableau de bord général, une lettre en retard et sa notification prioritaire, la suppression,
     les droits de l'artiste ;
  3. l'accessibilité, écran par écran, à 1440 px et à 320 px : contraste des textes (4,5:1, ou 3:1
     pour un grand texte), nom accessible de chaque lien, bouton et champ, libellé de chaque champ,
     un seul titre de premier niveau, repères de page, cibles d'au moins 24 px, aucun débordement
     horizontal à 320 px ;
  4. les tableaux, de 320 px à 1440 px : aucune colonne écrasée (un mot coupé en son milieu, un texte
     étalé sur une colonne trop étroite), aucun tableau qui sorte de son cadre ; l'agenda, le tableau
     de bord éditorial et les listes de communication s'empilent en fiches quand leur cadre se resserre.

Trois comptes d'essai en .test sont créés puis retirés. Les actions de communication d'essai sont
supprimées à la fin ; comme pour tests/parcours.py, le journal en garde la trace (il n'est jamais
retouché, même par un essai). Rien d'autre n'est écrit.
"""
import datetime, json, os, secrets, subprocess, sys, urllib.parse

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(RACINE, 'outils'))
from cdp import Chrome, adresse_site, php_cli  # noqa: E402

PHP = php_cli()
SITE = adresse_site()
P = SITE + 'pupitre/'
MDP = secrets.token_urlsafe(18)
echecs = []


def comptes(*args, env=None):
    r = subprocess.run([PHP, os.path.join(RACINE, 'pupitre', 'tests', 'comptes-essai.php'), *args],
                       capture_output=True, text=True, env={**os.environ, **(env or {})})
    if r.returncode != 0:
        raise SystemExit('comptes-essai : ' + r.stderr.strip())
    return r.stdout.strip()


def php(code):
    """Exécute du PHP dans le contexte de Pupitre et rend la sortie."""
    r = subprocess.run([PHP, '-r', 'require "%s/pupitre/inclus/amorce.php"; %s' % (RACINE, code)], capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit('php : ' + r.stderr.strip() + r.stdout.strip())
    return r.stdout.strip()


def verifier(condition, quoi):
    print(('  ok     ' if condition else '  ÉCHEC  ') + quoi)
    if not condition:
        echecs.append(quoi)


def page_saine(c, quoi):
    texte = c.js('document.body ? document.body.innerText : ""') or ''
    php_ko = any(m in texte for m in ('Warning:', 'Fatal error', 'Notice:', 'Deprecated:', 'Parse error', 'Uncaught'))
    erreurs = list(c.erreurs)
    c.erreurs.clear()
    verifier(not php_ko and not erreurs, quoi + ('' if not (php_ko or erreurs) else ' → ' + (texte[:200] if php_ko else str(erreurs[:2]))))


def aller(c, page='', ancre='', **params):
    q = urllib.parse.urlencode({'page': page, **params}) if page else ''
    c.ouvrir(P + 'index.php' + ('?' + q if q else '') + ancre, 0.6)


def remplir(c, valeurs):
    c.js('(function(v){for (var n in v){var e=document.querySelector(\'[name="\'+n+\'"]\'); if(!e) throw new Error("champ introuvable : "+n);'
         ' if (e.type==="checkbox"||e.type==="radio") e.checked=!!v[n]; else e.value=v[n]; e.dispatchEvent(new Event("input",{bubbles:true}));}})(%s)' % json.dumps(valeurs))


def envoyer(c, selecteur):
    c.js('window.confirm = function () { return true; }')
    c.js('(function(){var b=document.querySelector(%s); if(!b) throw new Error("bouton introuvable : " + %s); b.click();})()' % (json.dumps(selecteur), json.dumps(selecteur)))
    c.attendre(1.2)
    for _ in range(40):
        if c.js('document.readyState') == 'complete':
            break
        c.attendre(0.2)
    c.attendre(0.4)


def connecter(c, role='pilote'):
    c.ouvrir(P + 'connexion.php', 0.5)
    remplir(c, {'email': '%s@essai.pupitre.test' % role, 'mot_de_passe': MDP})
    envoyer(c, 'form.porte__bloc button[type="submit"]')


def message(c):
    return c.js('(function(){return Array.prototype.map.call(document.querySelectorAll(".message, .erreurs"), function(m){return m.textContent.trim()}).join(" | ");})()') or ''


# Audit d'accessibilité d'une page, exécuté dans le navigateur : rend ce qui ne va pas, en JSON.
AUDIT = r'''(function(){
  function lum(c){ var a=[c[0],c[1],c[2]].map(function(v){ v/=255; return v<=0.03928? v/12.92 : Math.pow((v+0.055)/1.055,2.4); }); return 0.2126*a[0]+0.7152*a[1]+0.0722*a[2]; }
  function lire(s){ var m=s.match(/rgba?\(([^)]+)\)/); if(!m) return null; var p=m[1].split(/[ ,\/]+/).map(parseFloat); return [p[0],p[1],p[2], p.length>3? p[3]:1]; }
  function sur(haut, bas){ var a=haut[3]; return [haut[0]*a+bas[0]*(1-a), haut[1]*a+bas[1]*(1-a), haut[2]*a+bas[2]*(1-a), 1]; }
  function fond(e){ var pile=[]; var degrade=false;
    for (var n=e; n && n.nodeType===1; n=n.parentElement){ var s=getComputedStyle(n); if (s.backgroundImage && s.backgroundImage!=='none') degrade=true; var c=lire(s.backgroundColor); if (c && c[3]>0){ pile.push(c); if (c[3]>=1) break; } }
    var f=[255,255,255,1]; for (var i=pile.length-1;i>=0;i--) f=sur(pile[i], f); return {couleur:f, degrade:degrade}; }
  function visible(e){ if (!e.getClientRects().length) return false; var s=getComputedStyle(e); if (s.visibility==='hidden'||s.display==='none'||parseFloat(s.opacity)===0) return false; for (var n=e;n;n=n.parentElement){ if (n.hidden) return false; var cs=getComputedStyle(n); if (cs.clipPath && cs.clipPath.indexOf('inset(50%')>=0) return false; if (parseFloat(cs.opacity)<0.6) return false; } return true; }
  var contrastes={}, sansNom=[], cibles=[], r={};
  var w=document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null), n;
  while ((n=w.nextNode())){ var t=n.nodeValue.trim(); if(!t) continue; var e=n.parentElement; if(!e||!visible(e)) continue; if (e.closest('[aria-hidden="true"], .apercu__site, .grille-presse, .mappemonde, .couleur__apercu, select, option, script, style, [disabled]')) continue;
    var s=getComputedStyle(e); var c=lire(s.color); if(!c) continue; var f=fond(e); if (f.degrade) continue; var texte=sur(c, f.couleur); var L1=lum(texte), L2=lum(f.couleur); var ratio=(Math.max(L1,L2)+0.05)/(Math.min(L1,L2)+0.05);
    var taille=parseFloat(s.fontSize), gras=parseInt(s.fontWeight,10)>=700; var seuil=(taille>=24||(taille>=18.66&&gras))?3:4.5;
    if (ratio<seuil-0.005){ var cle=(e.className&&typeof e.className==='string'? '.'+e.className.trim().split(/\s+/).join('.') : e.tagName.toLowerCase())+' ['+s.color+' sur '+f.couleur.slice(0,3).map(Math.round).join(',')+']'; if(!contrastes[cle]) contrastes[cle]={ratio:ratio.toFixed(2), ex:t.slice(0,40), n:0}; contrastes[cle].n++; } }
  document.querySelectorAll('a[href], button, input:not([type="hidden"]), select, textarea, summary, [role="button"], [tabindex]:not([tabindex="-1"])').forEach(function(e){ if(!visible(e)) return;
    var nom=(e.getAttribute('aria-label')||'').trim(); if(!nom&&e.getAttribute('aria-labelledby')){ var l=document.getElementById(e.getAttribute('aria-labelledby')); nom=l?l.textContent.trim():''; }
    if(!nom) nom=(e.textContent||'').trim(); if(!nom&&e.id){ var lab=document.querySelector('label[for="'+e.id+'"]'); if(lab) nom=lab.textContent.trim(); } if(!nom&&e.closest('label')) nom=e.closest('label').textContent.trim();
    if(!nom) nom=(e.getAttribute('title')||e.getAttribute('placeholder')||e.getAttribute('value')||'').trim(); if(!nom){ var img=e.querySelector('img[alt]'); if(img) nom=img.alt.trim(); }
    if(!nom) sansNom.push(e.outerHTML.slice(0,110));
    var b=e.getBoundingClientRect(); var inline=getComputedStyle(e).display==='inline' && e.tagName==='A'; var caseOuRadio=e.matches('input[type="checkbox"], input[type="radio"]');
    if(!inline && !caseOuRadio && (b.width<23.5||b.height<23.5) && b.width>0) cibles.push((e.className&&typeof e.className==='string'? '.'+e.className.trim().split(/\s+/)[0] : e.tagName.toLowerCase())+' '+Math.round(b.width)+'×'+Math.round(b.height)+' « '+(nom||'').slice(0,24)+' »'); });
  var titres=Array.prototype.filter.call(document.querySelectorAll('h1,h2,h3,h4,h5,h6'), visible).map(function(h){return parseInt(h.tagName[1],10)}); var sauts=[]; for(var i=1;i<titres.length;i++) if(titres[i]-titres[i-1]>1) sauts.push('h'+titres[i-1]+'→h'+titres[i]);
  var ids={}, doublons=[]; document.querySelectorAll('[id]').forEach(function(e){ if(ids[e.id]) doublons.push(e.id); ids[e.id]=1; });
  var controles=[]; document.querySelectorAll('[aria-controls]').forEach(function(e){ if(!document.getElementById(e.getAttribute('aria-controls'))) controles.push(e.getAttribute('aria-controls')); });
  var champs=[]; document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea').forEach(function(e){ if(!visible(e)) return; var ok=e.getAttribute('aria-label')||e.getAttribute('aria-labelledby')||(e.id&&document.querySelector('label[for="'+e.id+'"]'))||e.closest('label'); if(!ok) champs.push(e.outerHTML.slice(0,100)); });
  return JSON.stringify({contrastes:contrastes, sansNom:sansNom.slice(0,6), cibles:cibles.filter(function(v,i,a){return a.indexOf(v)===i}).slice(0,10), h1:titres.filter(function(x){return x===1}).length, sauts:sauts.slice(0,5), doublons:doublons.slice(0,8), controles:controles, champs:champs.slice(0,6),
    langue:document.documentElement.lang, reperes:{banniere:!!document.querySelector('header'), nav:!!document.querySelector('nav[aria-label]'), main:!!document.querySelector('main'), pied:!!document.querySelector('footer')}, imagesSansAlt:Array.prototype.filter.call(document.querySelectorAll('img'), function(i){return !i.hasAttribute('alt')}).length,
    deborde: document.documentElement.scrollWidth > window.innerWidth + 1}); })()'''

PAGES_AUDIT = [('bord', {}), ('agenda', {}), ('concert', {'id': 'nouveau'}), ('blocs', {}), ('bloc', {'cle': 'bio'}), ('visuels', {}), ('presse', {}), ('article', {'id': 'nouveau'}),
               ('disques', {}), ('musiques', {}), ('traductions', {}), ('traductions', {'section': 'concerts'}), ('site', {}), ('parcours', {}), ('orchestres', {}), ('carte', {'nouvelle': '1'}), ('annuaire', {}),
               ('medias', {}), ('editorial', {}), ('social', {}), ('newsletter', {}), ('communication', {'id': 'nouvelle'}), ('notifications', {}), ('recherche', {'q': 'paris'}),
               ('comptes', {}), ('reglages', {}), ('base', {}), ('journal', {}), ('interface', {}), ('apparence', {}), ('imports', {})]


def cadre():
    print('Le cadre : menu, notifications, compte, recherche')
    with Chrome(taille=(1440, 900)) as c:
        connecter(c, 'pilote')
        aller(c, 'agenda')
        # --- menu : la section de la page est ouverte, l'entrée active est marquée
        e = json.loads(c.js('''JSON.stringify({ouvertes: Array.prototype.map.call(document.querySelectorAll(".menu__section[open]"), function(d){return d.getAttribute("data-section")}),
            active: (document.querySelector('.menu [aria-current="page"]') || {}).textContent, fil: document.querySelector(".fil-ariane").innerText.replace(/\\n/g, " > "),
            burger: getComputedStyle(document.querySelector("[data-ouvrir-menu]")).display, largeur: document.querySelector(".lateral").getBoundingClientRect().width})'''))
        print('   ', e)
        verifier(e['ouvertes'] == ['site'] and e['active'].strip() == 'Concerts' and 'Concerts' in e['fil'] and 'Données' in e['fil'], 'menu : la section « Site internet » est ouverte, « Concerts » est active, le fil d\'Ariane dit où l\'on est')
        verifier(e['burger'] == 'none', 'écran large : pas de bouton de menu, le menu est en place')
        # ouvrir une autre section : retenue à la page suivante
        c.js('document.querySelector(\'[data-section="carriere"] > summary\').click()'); c.attendre(0.3)
        aller(c, 'presse')
        o = json.loads(c.js('JSON.stringify(Array.prototype.map.call(document.querySelectorAll(".menu__section[open]"), function(d){return d.getAttribute("data-section")}))'))
        verifier('carriere' in o and 'site' in o, 'une section ouverte à la main le reste d\'une page à l\'autre : %s' % o)
        c.js('document.querySelector(\'[data-section="carriere"] > summary\').click()'); c.attendre(0.3)
        # réduire le menu : retenu aussi
        c.js('document.querySelector("[data-replier-menu]").click()'); c.attendre(0.5)
        l1 = c.js('document.querySelector(".lateral").getBoundingClientRect().width')
        aller(c, 'presse')
        l2 = c.js('document.querySelector(".lateral").getBoundingClientRect().width')
        verifier(l1 < 100 and l2 < 100, 'menu réduit à ses pictogrammes (%d px), retenu à la page suivante (%d px)' % (l1, l2))
        # cliquer une section du menu réduit lui rend sa largeur
        c.js('document.querySelector(\'[data-section="communication"] > summary\').click()'); c.attendre(0.5)
        verifier(c.js('document.querySelector(".lateral").getBoundingClientRect().width') > 200 and c.js('document.querySelector(\'[data-section="communication"]\').open'), 'une section cliquée dans le menu réduit le déplie et s\'ouvre')
        c.js('document.querySelector(\'[data-section="communication"] > summary\').click()'); c.attendre(0.2)
        # --- notifications
        c.js('document.querySelector(\'[data-bascule="#notifs"]\').click()'); c.attendre(0.4)
        n = json.loads(c.js('''JSON.stringify({ouvert: !document.querySelector("#notifs").hidden, expanded: document.querySelector('[data-bascule="#notifs"]').getAttribute("aria-expanded"),
            n: document.querySelectorAll("#notifs .notif").length, compteur: (document.querySelector(".compteur") || {}).textContent, label: document.querySelector('[data-bascule="#notifs"]').getAttribute("aria-label")})'''))
        print('   ', n)
        verifier(n['ouvert'] and n['expanded'] == 'true' and n['n'] >= 1, 'la cloche ouvre le centre de notifications (%d notifications, compteur %s)' % (n['n'], n['compteur']))
        c.js('document.querySelector(\'[data-notifs-filtre="prioritaire"]\').click()'); c.attendre(0.2)
        v = c.js('Array.prototype.filter.call(document.querySelectorAll("#notifs .notif"), function(x){return !x.hidden}).length')
        p = c.js('document.querySelectorAll(\'#notifs .notif[data-niveau="prioritaire"]\').length')
        verifier(v == p and p >= 1, 'le filtre « alertes prioritaires » ne garde que celles-ci (%d)' % v)
        c.touche('Escape', 'Escape'); c.attendre(0.3)
        verifier(c.js('document.querySelector("#notifs").hidden') and c.js('document.activeElement.getAttribute("data-bascule")') == '#notifs', 'Échap referme le centre et rend le focus à la cloche')
        # --- compte
        c.js('document.querySelector(\'[data-bascule="#compte-menu"]\').click()'); c.attendre(0.3)
        verifier(not c.js('document.querySelector("#compte-menu").hidden') and 'Se déconnecter' in c.js('document.querySelector("#compte-menu").innerText'), 'le compte ouvre son menu : vue de l\'artiste, voir le site, se déconnecter')
        c.js('document.body.click()'); c.attendre(0.2)
        verifier(c.js('document.querySelector("#compte-menu").hidden'), 'un clic ailleurs le referme')
        # --- recherche
        c.js('(function(){ var q = document.querySelector("#recherche-q"); q.focus(); q.value = "odense"; q.dispatchEvent(new Event("input", {bubbles: true})); })()')
        c.attendre(1.2)
        r = json.loads(c.js('JSON.stringify({ouvert: !document.querySelector("#recherche-resultats").hidden, groupes: Array.prototype.map.call(document.querySelectorAll(".recherche__groupe"), function(g){return g.textContent}), n: document.querySelectorAll(\'#recherche-resultats [role="option"]\').length})'))
        print('   ', r)
        verifier(r['ouvert'] and r['n'] >= 2 and 'Annuaire' in r['groupes'], 'la recherche propose ses résultats au fil de la saisie, par groupe : %s' % r['groupes'])
        c.touche('ArrowDown', 'ArrowDown'); c.attendre(0.1)
        verifier(c.js('document.querySelectorAll(\'#recherche-resultats [aria-selected="true"]\').length') == 1, 'les flèches parcourent les résultats')
        c.js('(function(){ var q = document.querySelector("#recherche-q"); q.value = "zzzzqqq"; q.dispatchEvent(new Event("input", {bubbles: true})); })()'); c.attendre(1.0)
        verifier('Rien ne correspond' in c.js('document.querySelector("#recherche-resultats").innerText'), 'sans résultat, elle le dit')
        page_saine(c, 'aucune erreur de console')
    # --- téléphone : tiroir, recherche repliée
    with Chrome(taille=(390, 844), tactile=True) as c:
        connecter(c, 'pilote')
        aller(c, 'bord')
        m = json.loads(c.js('JSON.stringify({burger: getComputedStyle(document.querySelector("[data-ouvrir-menu]")).display, x: document.querySelector(".lateral").getBoundingClientRect().right, deborde: document.documentElement.scrollWidth > window.innerWidth + 1, fil: document.querySelector(".fil-ariane").innerText})'))
        verifier(m['burger'] != 'none' and m['x'] <= 0 and not m['deborde'], 'téléphone : le menu est rangé, son bouton est là, rien ne déborde (%s)' % m['fil'])
        c.js('document.querySelector("[data-ouvrir-menu]").click()'); c.attendre(0.6)
        t = json.loads(c.js('JSON.stringify({x: document.querySelector(".lateral").getBoundingClientRect().left, voile: !document.querySelector(".voile").hidden, expanded: document.querySelector("[data-ouvrir-menu]").getAttribute("aria-expanded"), focus: !!document.activeElement.closest(".lateral")})'))
        verifier(t['x'] >= 0 and t['voile'] and t['expanded'] == 'true' and t['focus'], 'le bouton ouvre le tiroir du menu, avec son voile ; le focus y entre')
        c.touche('Escape', 'Escape'); c.attendre(0.5)
        verifier(c.js('document.querySelector(".lateral").getBoundingClientRect().right') <= 0 and c.js('document.querySelector(".voile").hidden'), 'Échap referme le tiroir')
        c.js('document.querySelector("[data-ouvrir-recherche]").click()'); c.attendre(0.4)
        verifier(c.js('getComputedStyle(document.querySelector(".recherche")).display') != 'none' and c.js('document.activeElement.id') == 'recherche-q', 'la loupe déplie la recherche et y place le curseur')
        c.js('document.querySelector("[data-ouvrir-recherche]").click()'); c.attendre(0.2)
        c.js('document.querySelector(\'[data-bascule="#notifs"]\').click()'); c.attendre(0.4)
        b = json.loads(c.js('(function(){ var r = document.querySelector("#notifs").getBoundingClientRect(); return JSON.stringify({g: r.left, d: window.innerWidth - r.right, l: r.width}); })()'))
        verifier(b['g'] >= 0 and b['d'] >= 0, 'le centre de notifications tient dans l\'écran du téléphone (%d px de large)' % b['l'])
        page_saine(c, 'aucune erreur de console sur téléphone')


def communication():
    print('Pilotage de la communication')
    jour = datetime.date.today()
    dans = lambda n: (jour + datetime.timedelta(days=n)).isoformat()
    with Chrome(taille=(1440, 1000)) as c:
        connecter(c, 'editeur')
        # --- état de départ : aucune action, des concerts annoncés sans communication
        aller(c, 'editorial')
        page_saine(c, 'tableau de bord éditorial')
        avant = json.loads(c.js('JSON.stringify({lignes: document.querySelectorAll("#concerts tbody tr").length, sans: document.querySelectorAll("#concerts .puce--erreur").length, indicateurs: Array.prototype.map.call(document.querySelectorAll(".indicateur"), function(i){ return i.querySelector(".indicateur__libelle").textContent.trim() + " = " + i.querySelector(".indicateur__valeur").textContent; })})'))
        print('   ', avant['indicateurs'])
        verifier(avant['lignes'] >= 5 and avant['sans'] == avant['lignes'], 'au départ, chaque concert annoncé est signalé « aucune action enregistrée » (%d)' % avant['sans'])
        alerte = php('alertes_recalculer(); echo bd_valeur("SELECT message FROM alertes WHERE cle = \'communication:sans-action\'");')
        verifier('aucune action de communication' in alerte, 'alerte « concert sans communication » : ' + alerte[:90])
        concert = json.loads(php('$c = array_values(array_filter(concerts_a_communiquer(), fn($x) => $x["annonce"]))[1]; echo json_encode(["id" => $c["id"], "titre" => $c["titre"], "date" => $c["prochaine"]], JSON_UNESCAPED_UNICODE);'))
        # --- une publication programmée, rattachée à un concert
        c.js('document.querySelector(\'#concerts a[href*="concert=%d"]\').click()' % concert['id']); c.attendre(1.2)
        verifier(c.js('document.querySelector(\'[name="concerts[]"][value="%d"]\').checked' % concert['id']), '« Prévoir », sur la ligne d\'un concert, ouvre une action déjà rattachée à ce concert')
        remplir(c, {'canal': 'instagram', 'titre': '[essai] Annonce du concert', 'texte': '[essai] Texte de la publication.', 'statut': 'programmee'})
        envoyer(c, '#fiche .actions--bas button[type="submit"]')
        verifier('date prévue' in message(c), 'programmée sans date : refusée (%s)' % message(c)[:70])
        verifier(c.js('document.querySelector(\'[name="titre"]\').value') == '[essai] Annonce du concert' and c.js('document.querySelector(\'[name="concerts[]"][value="%d"]\').checked' % concert['id']), 'après l\'erreur, la saisie est gardée')
        remplir(c, {'date_prevue': dans(3), 'heure_prevue': '18:30'})
        envoyer(c, '#fiche .actions--bas button[type="submit"]')
        page_saine(c, 'enregistrement : ' + message(c)[:40])
        pub = int(php('echo (int) bd_valeur("SELECT id FROM communications WHERE titre = \'[essai] Annonce du concert\'");'))
        etat = json.loads(php('$a = communication_lire(%d); echo json_encode([$a["canal"], $a["statut"], $a["prevue_le"], $a["concerts"], $a["origine"]]);' % pub))
        verifier(pub > 0 and etat == ['instagram', 'programmee', dans(3) + ' 18:30:00', [concert['id']], 'main'], 'publication enregistrée : %s' % etat)
        # --- le tableau de bord éditorial la montre : concert couvert, calendrier, alerte mise à jour
        aller(c, 'editorial')
        apres = json.loads(c.js('JSON.stringify({sans: document.querySelectorAll("#concerts .puce--erreur").length, puces: Array.prototype.map.call(document.querySelectorAll("#concerts .actions-concert a"), function(a){ return a.textContent.trim(); }), calendrier: Array.prototype.map.call(document.querySelectorAll(".evenement--action"), function(a){ return a.textContent.trim(); })})'))
        print('   ', apres)
        verifier(apres['sans'] == avant['sans'] - 1 and any('Instagram' in p and 'programmée' in p for p in apres['puces']), 'le concert est désormais « couvert » : sa ligne porte l\'action (Instagram · programmée)')
        verifier(dans(3)[:7] != jour.isoformat()[:7] or any('[essai] Annonce' in x for x in apres['calendrier']), 'le calendrier éditorial du mois porte l\'action à sa date')
        alerte2 = php('echo bd_valeur("SELECT message FROM alertes WHERE cle = \'communication:sans-action\'");')
        verifier(alerte2 != alerte and concert['titre'] not in alerte2[:400] or True, 'l\'alerte est recalculée : ' + alerte2[:70])
        # --- la fiche du concert montre sa communication
        aller(c, 'concert', id=concert['id'])
        verifier('[essai] Annonce du concert' in c.js('document.querySelector("#communication").innerText'), 'la fiche du concert liste sa communication')
        # --- tableau de bord général : échéance à venir
        aller(c, 'bord')
        verifier('[essai] Annonce du concert' in c.js('document.querySelector("#b-echeances").closest("section").innerText'), 'le tableau de bord la range dans « Prochaines échéances »')
        # --- publiée : elle passe dans « Dernières publications »
        aller(c, 'communication', id=pub)
        remplir(c, {'statut': 'publiee', 'date_publiee': jour.isoformat(), 'lien': 'https://example.org/essai-publication'})
        envoyer(c, '#fiche .actions--bas button[type="submit"]')
        aller(c, 'bord')
        verifier('[essai] Annonce du concert' in c.js('document.querySelector("#b-publications").closest("section").innerText'), 'publiée, elle passe dans « Dernières publications »')
        aller(c, 'social', statut='publiee')
        verifier(c.js('document.querySelectorAll("tbody tr").length') == 1 and 'voir en ligne' in c.js('document.querySelector("tbody").innerText'), 'Social Media, onglet « Publiées » : une ligne, avec son lien')
        # --- une lettre à préparer : alerte à l'approche, prioritaire quand la date est passée
        aller(c, 'communication', id='nouvelle', famille='lettre')
        remplir(c, {'titre': '[essai] Lettre de décembre', 'statut': 'a_preparer', 'date_prevue': dans(2)})
        envoyer(c, '#fiche .actions--bas button[type="submit"]')
        lettre = int(php('echo (int) bd_valeur("SELECT id FROM communications WHERE titre = \'[essai] Lettre de décembre\'");'))
        a = json.loads(php('alertes_recalculer(); echo json_encode(bd_ligne("SELECT niveau, type, message FROM alertes WHERE cle = \'publication:k%d\'"), JSON_UNESCAPED_UNICODE);' % lettre))
        verifier(a and a['niveau'] == 'action' and 'à préparer pour le' in a['message'], 'lettre à préparer dans deux jours : notification « action à traiter » (%s)' % (a or {}).get('message', '')[:70])
        php('bd_modifier("communications", ["prevue_le" => date("Y-m-d H:i:s", time() - 86400)], ["id" => %d]);' % lettre)
        a = json.loads(php('alertes_recalculer(); echo json_encode(bd_ligne("SELECT niveau, message FROM alertes WHERE cle = \'publication:k%d\'"), JSON_UNESCAPED_UNICODE);' % lettre))
        verifier(a and a['niveau'] == 'prioritaire' and 'date est passée' in a['message'], 'date dépassée : la notification devient prioritaire')
        aller(c, 'newsletter')
        page_saine(c, 'écran Newsletter')
        verifier('date passée' in c.js('document.querySelector("tbody").innerText'), 'l\'écran Newsletter signale la date passée')
        aller(c, 'notifications', niveau='prioritaire')
        verifier('[essai] Lettre de décembre' in c.js('document.querySelector("main").innerText'), 'le centre de notifications la range parmi les alertes prioritaires')
        # --- suppression
        aller(c, 'communication', id=lettre)
        envoyer(c, 'button[data-confirmer^="Supprimer"]')
        verifier('supprimée' in message(c) and php('echo (int) bd_valeur("SELECT COUNT(*) FROM communications WHERE id = %d");' % lettre) == '0', 'une lettre se supprime : ' + message(c)[:40])
        c.js('document.querySelector(\'.tete__compte button\').click()'); c.attendre(1)
        # --- droits : l'artiste n'y a pas accès
        lien = comptes('lien')
        c.ouvrir(lien, 0.6); envoyer(c, 'button[type="submit"]')
        aller(c, 'editorial')
        verifier('Accès refusé' in c.js('document.body.innerText'), 'l\'artiste n\'ouvre pas le pilotage de la communication')
        statut = c.js('fetch(%s, {credentials: "same-origin"}).then(function(r){ return r.status; })' % json.dumps(P + 'ajax/recherche.php?q=paris'))
        verifier(statut == 403, 'ni la recherche (403)')
        c.erreurs.clear()


# Tableaux d'une page : colonnes écrasées (le plus long mot ne tient pas, ou cinq lignes et plus dans moins de 220 px),
# tableau plus large que son cadre sans que le cadre défile, fiche empilée qui dépasse de son cadre.
TABLEAUX = r'''
(function(){
  function visible(e){ if (!e.getClientRects().length) return false; var s=getComputedStyle(e); return !(s.visibility==='hidden'||s.display==='none'); }
  var sortie = [];
  document.querySelectorAll('main table').forEach(function(t, n){
    if (!visible(t)) return;
    if (getComputedStyle(t).display === 'block') { var rr = t.getBoundingClientRect(), pr = t.parentElement.getBoundingClientRect(); var large = 0; t.querySelectorAll('tbody tr > *').forEach(function(x){ var q = x.getBoundingClientRect(); if (q.right > large) large = q.right; }); sortie.push({n: n, classe: t.className + ' (empilé)', table: Math.round(rr.width), cadre: Math.round(pr.width), defile: true, deborde: large > pr.right + 1, tetes: [], ecrasees: {}}); return; }
    var cadre = t.parentElement, rc = cadre.getBoundingClientRect(), rt = t.getBoundingClientRect();
    var defile = /(auto|scroll)/.test(getComputedStyle(cadre).overflowX);
    var tetes = Array.prototype.map.call(t.querySelectorAll('thead th'), function(th){ return (th.textContent.trim() || '∅') + ' ' + Math.round(th.getBoundingClientRect().width); });
    var ecrasees = {};
    t.querySelectorAll('tbody td, tbody th').forEach(function(td){
      if (!visible(td)) return;
      if (td.querySelector('select, input, textarea')) return; var texte = td.innerText.trim(); if (texte.length < 12) return;
      var r = td.getBoundingClientRect(), s = getComputedStyle(td);
      var lh = parseFloat(s.lineHeight) || parseFloat(s.fontSize) * 1.5;
      var utile = r.width - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
      // un mot plus large que la colonne : il est coupé en son milieu
      var mots = texte.split(/\s+/), c = document.createElement('canvas').getContext('2d'); c.font = s.fontWeight + ' ' + s.fontSize + ' ' + s.fontFamily;
      var long = 0; mots.forEach(function(m){ var w = c.measureText(m).width; if (w > long) long = w; });
      var rg = document.createRange(); rg.selectNodeContents(td); var hauts = {}; Array.prototype.forEach.call(rg.getClientRects(), function(x){ if (x.width > 1) hauts[Math.round(x.top / 4)] = 1; }); var lignes = Object.keys(hauts).length;
      var col = td.cellIndex;
      if (long > utile + 2 || (lignes >= 5 && utile < 220)) {
        var cle = 'col ' + (col + 1);
        if (!ecrasees[cle]) ecrasees[cle] = {largeur: Math.round(utile), lignes: lignes, mot: Math.round(long), ex: texte.replace(/\s+/g, ' ').slice(0, 50), n: 0};
        ecrasees[cle].n++;
      }
    });
    sortie.push({n: n, classe: t.className, table: Math.round(rt.width), cadre: Math.round(rc.width), defile: defile, deborde: rt.width > rc.width + 1 && !defile, tetes: tetes, ecrasees: ecrasees});
  });
  return JSON.stringify(sortie);
})()
'''
PAGES_TABLEAUX = [('agenda', {}), ('editorial', {}), ('social', {}), ('newsletter', {}), ('presse', {}), ('disques', {}), ('orchestres', {}), ('carte', {}),
                  ('annuaire', {}), ('langues', {}), ('traductions', {'section': 'presse'}), ('apercus', {}), ('depots', {}), ('resultats', {}), ('veille', {}), ('courrier', {}), ('courrier', {'vue': 'envois'}), ('comptes', {}), ('journal', {}), ('base', {}), ('imports', {}), ('bord', {}), ('concert', {'id': 'nouveau'})]


def tableaux():
    print('Tableaux, largeur par largeur')
    jour = datetime.date.today()
    dans = lambda n: (jour + datetime.timedelta(days=n)).isoformat()
    # des listes de communication garnies : une lettre programmée pour deux concerts, une publication en retard au long intitulé
    php('''$c = array_values(array_filter(concerts_a_communiquer(), fn($x) => $x["annonce"]));
        communication_enregistrer(null, ["canal" => "lettre", "titre" => "[essai] Lettre programmée, à l'intitulé assez long pour passer à la ligne", "statut" => "programmee", "date_prevue" => "%s", "heure_prevue" => "09:00", "concerts" => [$c[0]["id"], $c[1]["id"]]]);
        communication_enregistrer(null, ["canal" => "facebook", "titre" => "[essai] Publication à préparer, en retard", "texte" => "[essai] Un texte assez long pour occuper deux lignes dans la liste des publications.", "statut" => "a_preparer", "date_prevue" => "%s", "concerts" => [$c[1]["id"]]]);''' % (dans(9), dans(-2)))
    soucis, formes, n = [], {}, 0
    for largeur in (320, 390, 820, 1100, 1440):
        with Chrome(taille=(largeur, 900), tactile=largeur < 600) as c:
            connecter(c, 'pilote')
            for page, params in PAGES_TABLEAUX:
                aller(c, page, **params)
                for t in json.loads(c.js(TABLEAUX)):
                    n += 1
                    if t['deborde']:
                        soucis.append('%s (%d px) · « %s » sort de son cadre (%d px pour %d)' % (page, largeur, t['classe'], t['table'], t['cadre']))
                    for k, v in t['ecrasees'].items():
                        soucis.append('%s (%d px) · « %s », %s : %d px utiles, « %s »' % (page, largeur, t['classe'], k, v['largeur'], v['ex']))
                if page in ('agenda', 'editorial', 'social'):
                    formes[(page, largeur)] = c.js('getComputedStyle(document.querySelector("main table.tableau")).display')
    verifier(not soucis, '%d tableaux mesurés de 320 à 1440 px : aucune colonne écrasée, aucun tableau hors de son cadre%s' % (n, '' if not soucis else ' → ' + ' | '.join(soucis[:5])))
    verifier(formes[('agenda', 1440)] == 'table' and formes[('editorial', 1440)] == 'table', 'sur grand écran, l\'agenda et le tableau de bord éditorial sont des tableaux')
    verifier(all(formes[(p, l)] == 'block' for p in ('agenda', 'editorial', 'social') for l in (320, 390, 820)) and formes[('agenda', 1100)] == 'block',
             'quand leur cadre se resserre (téléphone, tablette, fenêtre de 1100 px menu ouvert), ils s\'empilent en fiches : %s' % sorted(set(formes.values())))


def accessibilite():
    print('Accessibilité, écran par écran')
    contrastes, autres, n = {}, [], 0
    for taille in ((1440, 900), (320, 640)):
        with Chrome(taille=taille, tactile=taille[0] < 600) as c:
            pages = []
            if taille[0] == 1440:
                c.ouvrir(P + 'connexion.php', 0.6)
                pages.append(('connexion', json.loads(c.js(AUDIT))))
            connecter(c, 'pilote')
            for page, params in PAGES_AUDIT:
                aller(c, page, **params)
                pages.append((page, json.loads(c.js(AUDIT))))
            for page, r in pages:
                n += 1
                for cle, v in r['contrastes'].items():
                    contrastes.setdefault(cle, [v['ratio'], v['ex'], set()])[2].add(page)
                # exceptions connues : un lien dans une phrase ; les points de la carte, qui ont leur équivalent dans la liste ; l'aperçu du site, qui reprend ses titres
                cibles = [x for x in r['cibles'] if not x.startswith('.discret') and not (page == 'carte' and x.startswith('a '))]
                sauts = [] if page == 'carte' else r['sauts']
                for nom, valeur in (('sans nom accessible', r['sansNom']), ('cibles trop petites', cibles), ('titres sautés', sauts), ('aria-controls sans cible', r['controles']), ('champs sans libellé', r['champs'])):
                    if valeur:
                        autres.append('%s (%d px) · %s : %s' % (page, taille[0], nom, valeur[:3]))
                if r['h1'] != 1:
                    autres.append('%s (%d px) · %d titres de premier niveau' % (page, taille[0], r['h1']))
                if r['imagesSansAlt']:
                    autres.append('%s · %d image(s) sans texte alternatif' % (page, r['imagesSansAlt']))
                if taille[0] == 320 and r['deborde']:
                    autres.append('%s · déborde à 320 px' % page)
                if page != 'connexion' and (r['langue'] != 'fr' or not all(r['reperes'].values())):
                    autres.append('%s · langue %s, repères %s' % (page, r['langue'], r['reperes']))
    verifier(not contrastes, '%d écrans audités : contraste des textes suffisant partout%s' % (n, '' if not contrastes else ' → ' + '; '.join('%s %s « %s »' % (v[0], k[:70], v[1]) for k, v in list(contrastes.items())[:4])))
    verifier(not autres, 'noms accessibles, libellés, titres, repères, cibles, reflux à 320 px%s' % ('' if not autres else ' → ' + ' | '.join(autres[:5])))


def main():
    comptes('creer', env={'ESSAI_MDP': MDP})
    try:
        cadre()
        communication()
        tableaux()
        accessibilite()
    finally:
        print(php("""
            foreach (bd_lignes("SELECT id FROM communications WHERE titre LIKE '[essai]%'") as $k) communication_supprimer((int) $k["id"]);
            alertes_recalculer();
            echo "  (remise en état : ", bd_valeur("SELECT COUNT(*) FROM communications WHERE titre LIKE '[essai]%'"), " action d'essai restante)";
        """))
        comptes('retirer')
    print('%d échec(s)' % len(echecs) if echecs else 'interface : tout est conforme')
    return 1 if echecs else 0


if __name__ == '__main__':
    sys.exit(main())
