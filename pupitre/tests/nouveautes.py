#!/usr/bin/env python3
"""nouveautes.py : essai, dans un vrai navigateur, des évolutions du 28/11/2026 (Chrome headless, outils/cdp.py du site).

    python3 pupitre/tests/nouveautes.py [partie ...]        sans argument : toutes les parties

Parties :
  gestes      le glisser-déposer : composition de l'accueil de la revue de presse (ordre, numéro d'ordre,
              largeur, enregistrement au fil des gestes), mise en page du tableau de bord (par compte) ;
  album       la fiche album : label et orchestre créés d'ici, textes réduits à la présentation et à la note ;
  apercu      l'aperçu du site non publié : dossier figé, lien personnel, procès-verbal de validation,
              expiration ;
  depot       la zone de dépôt d'un concert : invitation, dépôt d'un fichier par le lien, refus des fichiers
              qui n'ont rien à y faire ;
  connexions  Metricool (marques, relevé des publications, bascule vers les comptes de test), veille (flux
              d'actualités), courrier (boîte lue par IMAP) : sur de faux services locaux (tests/faux-services.py),
              jamais sur les vrais ; passée d'elle-même si ces services sont réglés dans pupitre/.env.

Complète tests/parcours.py (contenus, publication) et tests/interface.py (cadre, accessibilité). Les gestes
sont de vrais événements de souris, en coordonnées réelles : un geste qui ne marcherait pas à la main ne
marche pas ici.

Trois comptes d'essai en .test sont créés puis retirés. Tout ce que l'essai modifie est remis comme avant
(composition de l'accueil, disposition du tableau de bord) ou supprimé (album, fiches, aperçu, dépôts
d'essai) ; le journal en garde la trace, comme pour les autres essais. Aucun mail ne part : les adresses
d'essai sont en .test, et Pupitre n'écrit jamais à une adresse d'essai.
"""
import json, os, secrets, subprocess, sys, urllib.error, urllib.parse, urllib.request

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


def centre(c, selecteur, dx=0.5, dy=0.5):
    """Un point d'un élément, en coordonnées de la fenêtre (l'élément est d'abord amené dans la vue)."""
    r = c.js('(function(){var e=document.querySelector(%s); if(!e) return null; e.scrollIntoView({block:"center"}); var r=e.getBoundingClientRect();'
             ' return [r.left + r.width*%f, r.top + r.height*%f];})()' % (json.dumps(selecteur), dx, dy))
    if not r:
        raise RuntimeError('introuvable : ' + selecteur)
    return r


def point(c, selecteur, dx=0.5, dy=0.5):
    """Le même point, sans faire défiler : pour l'arrivée d'un geste."""
    r = c.js('(function(){var e=document.querySelector(%s); if(!e) return null; var r=e.getBoundingClientRect(); return [r.left + r.width*%f, r.top + r.height*%f];})()'
             % (json.dumps(selecteur), dx, dy))
    if not r:
        raise RuntimeError('introuvable : ' + selecteur)
    return r


def glisser(c, depart, arrivee, pas=14):
    """Un vrai glissement : bouton enfoncé, déplacement pas à pas, bouton relâché."""
    (x0, y0), (x1, y1) = depart, arrivee
    c.cmd('Input.dispatchMouseEvent', type='mouseMoved', x=x0, y=y0)
    c.cmd('Input.dispatchMouseEvent', type='mousePressed', x=x0, y=y0, button='left', buttons=1, clickCount=1)
    for i in range(1, pas + 1):
        c.cmd('Input.dispatchMouseEvent', type='mouseMoved', x=x0 + (x1 - x0) * i / pas, y=y0 + (y1 - y0) * i / pas, button='left', buttons=1)
        c.attendre(0.03)
    c.cmd('Input.dispatchMouseEvent', type='mouseReleased', x=x1, y=y1, button='left', buttons=0, clickCount=1)
    c.attendre(0.9)


# ---------------------------------------------------------------------------------------------------------
def partie_gestes(c):
    print('Revue de presse : composition de l\'accueil')
    avant = json.loads(php('echo json_encode(bd_lignes("SELECT id, ordre_accueil, largeur FROM articles WHERE accueil = 1 ORDER BY ordre_accueil, id"));'))
    if len(avant) < 3:
        print('  (moins de trois articles sur l\'accueil : partie passée)')
    else:
        try:
            aller(c, 'presse', '#accueil')
            page_saine(c, 'écran « Revue de presse »')
            ordre = lambda: c.js('Array.prototype.map.call(document.querySelectorAll("[data-composition-grille] > [data-glisser-element]"), function(e){return parseInt(e.getAttribute("data-id"),10)})')
            rangs = lambda: c.js('Array.prototype.map.call(document.querySelectorAll("[data-composition-grille] [data-rang]"), function(e){return e.textContent.trim()})')
            en_base = lambda: json.loads(php('echo json_encode(array_map("intval", bd_colonne("SELECT id FROM articles WHERE accueil = 1 ORDER BY ordre_accueil, id")));'))
            depart = ordre()
            verifier(depart == [int(a['id']) for a in avant], 'la grille montre les articles de l\'accueil, dans leur ordre')
            # le troisième article est posé sur la moitié gauche du premier : il passe en tête
            c3 = '[data-composition-grille] > [data-glisser-element]:nth-child(3)'
            c1 = '[data-composition-grille] > [data-glisser-element]:nth-child(1)'
            a = centre(c, c3 + ' .grille-presse__media', 0.5, 0.5)
            b = point(c, c1, 0.12, 0.3)
            glisser(c, a, b)
            attendu = [depart[2], depart[0], depart[1]] + depart[3:]
            verifier(ordre() == attendu, 'glisser le troisième article sur le premier le met en tête (ordre de la grille : %s)' % ordre()[:4])
            verifier(rangs() == [str(i + 1) for i in range(len(depart))], 'les numéros d\'ordre suivent d\'eux-mêmes : 1, 2, 3…')
            c.attendre(1.0)
            verifier(en_base() == attendu, 'la nouvelle composition est enregistrée sans bouton (base : %s)' % en_base()[:4])
            verifier('nregistr' in (c.js('document.querySelector("[data-composition-etat]").textContent') or ''), 'l\'écran dit que c\'est enregistré')
            # la largeur : la case change de classe aussitôt, la base suit
            premier = attendu[0]
            largeur_avant = int(php('echo bd_valeur("SELECT largeur FROM articles WHERE id = %d");' % premier))
            nouvelle = 4 if largeur_avant != 4 else 2
            c.js('(function(){var s=document.querySelector("[data-composition-grille] > [data-glisser-element] [data-largeur]"); s.value="%d"; s.dispatchEvent(new Event("change",{bubbles:true}));})()' % nouvelle)
            c.attendre(1.2)
            verifier(c.js('document.querySelector("[data-composition-grille] > [data-glisser-element]").classList.contains("grille-presse__case--%d")' % nouvelle),
                     'changer la largeur redessine la grille aussitôt')
            verifier(int(php('echo bd_valeur("SELECT largeur FROM articles WHERE id = %d");' % premier)) == nouvelle, 'la largeur est enregistrée')
            # au clavier : la flèche « reculer » du premier article
            c.js('document.querySelector("[data-composition-grille] > [data-glisser-element] [data-glisser-apres]").click()')
            c.attendre(1.2)
            attendu2 = [attendu[1], attendu[0]] + attendu[2:]
            verifier(ordre() == attendu2 and en_base() == attendu2, 'la flèche « reculer » déplace l\'article d\'une place, et l\'enregistre')
            aller(c, 'presse', '#accueil')
            verifier(ordre() == attendu2, 'après rechargement, la grille montre la composition enregistrée')
        finally:
            # remise exacte : les rangs d'origine (pas seulement l'ordre), pour ne laisser aucun écart à publier
            for a in avant:
                php('bd_requete("UPDATE articles SET accueil = 1, ordre_accueil = %d, largeur = %d WHERE id = %d");' % (int(a['ordre_accueil']), int(a['largeur']), int(a['id'])))
            apres = json.loads(php('echo json_encode(bd_lignes("SELECT id, ordre_accueil, largeur FROM articles WHERE accueil = 1 ORDER BY ordre_accueil, id"));'))
            verifier(apres == avant, 'composition de l\'accueil remise exactement comme elle était')

    print('Tableau de bord : mise en page')
    try:
        aller(c, 'bord')
        page_saine(c, 'tableau de bord')
        zones = lambda: c.js('(function(){var d={}; Array.prototype.forEach.call(document.querySelectorAll("[data-bord] [data-zone]"), function(z){ d[z.getAttribute("data-zone")] = Array.prototype.map.call(z.querySelectorAll(":scope > [data-bloc]"), function(b){return b.getAttribute("data-bloc")}); }); return d;})()')
        d0 = zones()
        verifier(d0['haut'][:1] == ['raccourcis'], 'par défaut, le bloc « Raccourcis » est en haut du tableau de bord')
        verifier(c.js('!document.querySelector("[data-bord]").classList.contains("bord--edition") && getComputedStyle(document.querySelector(".bord__outils")).display === "none"'),
                 'hors mise en page, les blocs ne montrent ni poignée ni flèches')
        c.cliquer_sel('[data-bord-edition]')
        c.attendre(0.5)
        verifier(c.js('document.querySelector("[data-bord]").classList.contains("bord--edition") && !document.querySelector("[data-bord-aide]").hidden'), 'le bouton « Mise en page » ouvre le mode de mise en page')
        verifier(c.js('getComputedStyle(document.querySelector("[data-bloc=concerts] .liste-dates, [data-bloc=concerts] .vide")).display === "none"'),
                 'en mise en page, les blocs se replient à leur titre : toute la disposition tient à l\'écran')
        # « Prochaines échéances » (à droite) est posé au-dessus de « Prochains concerts » (à gauche)
        a = centre(c, '[data-bloc="echeances"] [data-poignee]')
        b = point(c, '[data-bloc="concerts"]', 0.4, 0.25)
        glisser(c, a, b, pas=18)
        d1 = zones()
        verifier('echeances' in d1['gauche'] and d1['gauche'].index('echeances') < d1['gauche'].index('concerts') and 'echeances' not in d1['droite'],
                 'un bloc se déplace par sa poignée, d\'une colonne à l\'autre (gauche : %s)' % d1['gauche'])
        c.attendre(1.0)
        pref = json.loads(php('echo (string) bd_valeur("SELECT preferences FROM comptes WHERE email = \'pilote@essai.pupitre.test\'");') or '{}')
        verifier(pref.get('bord', {}).get('gauche') == d1['gauche'], 'la disposition est enregistrée pour le compte')
        aller(c, 'bord')
        verifier(zones() == d1, 'au retour sur le tableau de bord, la disposition est celle du compte')
        # au clavier : la flèche « descendre » du premier bloc du haut
        c.cliquer_sel('[data-bord-edition]')
        c.attendre(0.3)
        c.js('document.querySelector("[data-zone=haut] > [data-bloc] [data-glisser-apres]").click()')
        c.attendre(1.0)
        d2 = zones()
        verifier(d2['haut'][:2] == [d1['haut'][1], d1['haut'][0]], 'les flèches déplacent un bloc au clavier')
        envoyer(c, '[data-bord-aide] button[value="bord_defaut"]')
        verifier(zones() == d0 and 'origine' in message(c), '« Rétablir la disposition d\'origine » remet le tableau de bord par défaut')
    finally:
        php('bd_requete("UPDATE comptes SET preferences = NULL WHERE email LIKE \'%@essai.pupitre.test\'");')


# ---------------------------------------------------------------------------------------------------------
def partie_apercu(c):
    print('Aperçu du site avant publication')
    fond = php('echo reglage("fond");')
    autre = '#0a1524' if fond.lower() != '#0a1524' else '#0b1a30'
    publie = php('echo couleur_fond_publiee();')
    ids = []
    try:
        php('reglage_ecrire("fond", "%s");' % autre)                      # une modification en attente : la couleur de fond
        aller(c, 'apercus')
        page_saine(c, 'écran « Aperçus et validations »')
        remplir(c, {'titre': 'Aperçu d\'essai de Pupitre', 'note': 'Une nouvelle couleur de fond, pour l\'essai.'})
        envoyer(c, 'form button[type="submit"].bouton--plein')
        verifier('Aperçu créé' in message(c) and 'page=apercu' in c.js('location.href'), 'le pilote crée un aperçu : ' + message(c)[:90])
        ids.append(int(php('echo (int) bd_valeur("SELECT MAX(id) FROM apercus");')))
        adresse = c.js('document.querySelector("#lien-apercu").value')
        jeton = adresse.rstrip('/').split('/')[-1]
        verifier(adresse.startswith(SITE + 'apercu/') and len(jeton) == 32, 'l\'aperçu a son adresse à partager, sous le site : …/apercu/<jeton>/')
        page_saine(c, 'fiche de l\'aperçu')
        verifier('publier exactement ce que montre cet aperçu' in (c.js('document.body.innerText') or ''), 'la fiche dit que l\'aperçu montre l\'état présent de Pupitre')

        # l'aperçu, vu par qui a le lien
        c.erreurs.clear()
        c.ouvrir(adresse, 3.0)
        verifier(not c.erreurs, 'l\'aperçu se charge sans une erreur (scripts, feuilles de style, médias)' + ('' if not c.erreurs else ' → ' + str(c.erreurs[:3])))
        verifier((c.js('getComputedStyle(document.documentElement).getPropertyValue("--bg").trim()') or '').lower() == autre, 'il montre la modification en attente (la nouvelle couleur de fond)')
        verifier(c.js('(function(){var b=document.getElementById("pp-apercu"); return !!b && !b.hidden && /Aperçu avant publication|Preview before publication/.test(b.textContent) && b.querySelector("[data-pp=valider]").hidden;})()'),
                 'son bandeau dit ce que c\'est et jusqu\'à quand ; sans lien personnel, pas de bouton « Valider »')
        verifier(c.js('document.querySelector("meta[name=robots]").content').startswith('noindex'), 'la copie n\'est pas indexable')
        verifier(c.js('Array.prototype.every.call(document.querySelectorAll("img"), function(i){ return !i.currentSrc || i.complete && i.naturalWidth > 0; })'), 'ses images viennent bien du site (aucune image cassée)')
        c.ouvrir(adresse + 'concerts.html', 2.0)
        verifier(not c.erreurs and c.js('!!document.getElementById("pp-apercu") && location.pathname.indexOf("/apercu/") >= 0'), 'les autres pages de l\'aperçu se consultent de la même façon')
        vrai = urllib.request.urlopen(SITE + 'css/reglages.css').read().decode()
        verifier(publie in vrai and autre not in vrai, 'le site publié, lui, n\'a pas changé')
        liste = urllib.request.urlopen(SITE + 'apercu/').read().decode()
        verifier(jeton not in liste, 'le dossier des aperçus ne se liste pas : on ne trouve un aperçu que par son adresse')

        # demande de validation : un lien personnel, pour un compte enregistré
        aller(c, 'apercu', id=ids[0])
        artiste = php('echo (int) bd_valeur("SELECT id FROM comptes WHERE email = \'artiste@essai.pupitre.test\'");')
        remplir(c, {'compte': artiste})
        envoyer(c, '#validations form button[type="submit"]')
        lien = c.js('(document.querySelector("#lien-perso")||{}).value') or ''
        verifier('Demande de validation envoyée' in message(c) and '/apercu.php?j=' in lien, 'le pilote envoie un lien personnel à un compte enregistré')
        verifier('non envoyé (adresse d\'essai)' in php('echo implode("", array_slice(file(STOCKAGE . "/journaux/mails.log"), -40));'), 'aucun mail ne part vers une adresse d\'essai (il est noté dans le journal des mails)')
        verifier(php('echo (int) bd_valeur("SELECT COUNT(*) FROM apercus_validations WHERE jeton = ?", [hash("sha256", "%s")]);' % lien.split('j=')[1]) == '1'
                 and php('echo (int) bd_valeur("SELECT COUNT(*) FROM apercus_validations WHERE jeton = ?", ["%s"]);' % lien.split('j=')[1]) == '0', 'le lien personnel n\'est gardé que sous forme d\'empreinte')

        # la personne ouvre son lien : l'aperçu, avec « Valider »
        envoyer(c, '.tete__compte button')                                 # hors connexion : le lien suffit
        c.erreurs.clear()
        c.ouvrir(lien, 3.0)
        verifier('/apercu/' + jeton in c.js('location.href') and 'v=' not in c.js('location.search'), 'le lien personnel mène à l\'aperçu (et ne reste pas dans la barre d\'adresse)')
        verifier(c.js('(function(){var v=document.querySelector("#pp-apercu [data-pp=valider]"); return !!v && !v.hidden && v.href.indexOf("apercu.php?pv=1&j=") > 0;})()'), 'le bandeau porte maintenant le bouton « Valider »')
        c.ouvrir(adresse + 'presse.html', 1.5)
        verifier(c.js('!document.querySelector("#pp-apercu [data-pp=valider]").hidden'), 'le bouton reste là de page en page')
        c.ouvrir(c.js('document.querySelector("#pp-apercu [data-pp=valider]").href'), 0.8)
        page_saine(c, 'page du procès-verbal')
        verifier('Validation de l\'aperçu' in (c.js('document.body.innerText') or '') and 'Je soussigné(e) Essai artiste' in (c.js('document.body.innerText') or ''), 'elle présente l\'aperçu et la déclaration à accepter')
        envoyer(c, 'button[value="validee"]')
        verifier('cochez la case' in message(c), 'valider sans cocher la case est refusé')
        envoyer(c, 'button[value="corrections"]')
        verifier('dites lesquelles' in message(c), 'demander des corrections sans dire lesquelles est refusé')
        c.js('document.querySelector("#accord").checked = true; document.querySelector("#remarques").value = "Très bien pour moi.";')
        envoyer(c, 'button[value="validee"]')
        texte = c.js('document.body.innerText') or ''
        verifier('votre validation est enregistrée' in texte and 'Procès-verbal de validation'.upper() in texte.upper() and 'Très bien pour moi.' in texte, 'la validation est enregistrée et le procès-verbal s\'affiche')
        v = json.loads(php('echo json_encode(bd_ligne("SELECT decision, declaration, ip, agent, remarques, vu_le, decide_le FROM apercus_validations WHERE apercu_id = %d"));' % ids[0]))
        verifier(v['decision'] == 'validee' and 'Essai artiste' in v['declaration'] and v['ip'] and 'Chrome' in v['agent'] and v['vu_le'] and v['decide_le'],
                 'la décision est tracée : déclaration, date, adresse IP, navigateur')
        c.ouvrir(lien, 0.8)
        verifier('votre validation est enregistrée' in (c.js('document.body.innerText') or '') and not c.js('!!document.querySelector("button[value=validee]")'), 'le même lien ne permet pas de décider une seconde fois')
        verifier(php('echo (int) bd_valeur("SELECT COUNT(*) FROM journal WHERE objet_type = \'apercu\' AND objet_id = %d AND action = \'validation\'");' % ids[0]) == '1', 'le journal garde la validation')

        # le pilote en est informé
        connecter(c, 'pilote')
        php('alertes_recalculer();')
        aller(c, 'notifications')
        verifier('a été validé par Essai artiste' in (c.js('document.body.innerText') or ''), 'le pilote reçoit une notification : l\'aperçu est validé, le site peut être publié')
        aller(c, 'apercu', id=ids[0])
        page_saine(c, 'fiche de l\'aperçu, après validation')
        verifier(c.js('!!document.querySelector("#validations .pv")') and 'Validé' in (c.js('document.querySelector("#validations").innerText') or ''), 'le procès-verbal est dans la fiche de l\'aperçu')
        vid = php('echo (int) bd_valeur("SELECT id FROM apercus_validations WHERE apercu_id = %d");' % ids[0])
        aller(c, 'apercu', id=ids[0], pv=vid)
        page_saine(c, 'procès-verbal à imprimer')
        aller(c, 'site')
        page_saine(c, 'écran « Le site »')
        verifier('a été validé par Essai artiste' in (c.js('document.body.innerText') or ''), 'l\'écran de publication dit que ce qui serait publié a été validé')
        php('reglage_ecrire("fond", "#04070d");')
        aller(c, 'site')
        verifier('Pupitre a changé depuis' in (c.js('document.body.innerText') or ''), 'si Pupitre change après la validation, l\'écran de publication le signale')

        # expiration : le lien ne mène plus à rien, le dossier disparaît
        php('bd_requete("UPDATE apercus SET expire_le = ? WHERE id = %d", [date("Y-m-d H:i:s", time() - 60)]);' % ids[0])
        aller(c, 'apercus')
        verifier(not os.path.isdir(os.path.join(RACINE, 'apercu', jeton)), 'à l\'expiration, la copie est retirée du site')
        # un serveur web répond 404 ; le serveur d'essai de PHP, lui, retombe sur la page vide du dossier des aperçus
        try:
            reste = urllib.request.urlopen(adresse + 'index.html').read().decode()
        except urllib.error.HTTPError as e:
            reste = ''
        verifier('pp-apercu' not in reste and '<body' not in reste, 'l\'adresse de l\'aperçu ne montre plus rien')
        verifier('expiré' in (c.js('document.body.innerText') or '') or 'retiré' in (c.js('document.body.innerText') or ''), 'la liste garde la trace de l\'aperçu expiré, et sa validation')
    finally:
        php('reglage_ecrire("fond", "%s");' % fond)
        for i in ids or [int(x) for x in php('echo implode(",", bd_colonne("SELECT id FROM apercus WHERE titre = \'Aperçu d\\\'essai de Pupitre\'"));').split(',') if x]:
            php('$a = bd_ligne("SELECT * FROM apercus WHERE id = %d"); if ($a) { apercu_dossier_supprimer($a["jeton"]); bd_supprimer("apercus_validations", ["apercu_id" => %d]); bd_supprimer("apercus", ["id" => %d]); }' % (i, i, i))
        php('bd_requete("DELETE FROM courriers WHERE correspondant_email LIKE \'%@essai.pupitre.test\'"); alertes_recalculer();')
        verifier(php('echo reglage("fond");') == fond and php('echo (int) bd_valeur("SELECT COUNT(*) FROM apercus WHERE titre LIKE \'Aperçu d%essai de Pupitre\'");') == '0', 'couleur de fond et aperçus d\'essai remis comme avant')


# ---------------------------------------------------------------------------------------------------------
def deposer(c, selecteur, chemins):
    """Choisit des fichiers dans un champ <input type="file">, comme le ferait la boîte de dialogue du navigateur."""
    racine = c.cmd('DOM.getDocument', depth=0)['root']['nodeId']
    noeud = c.cmd('DOM.querySelector', nodeId=racine, selector=selecteur)['nodeId']
    c.cmd('DOM.setFileInputFiles', files=chemins, nodeId=noeud)
    c.attendre(0.3)


def partie_depot(c):
    print('Zone de dépôt d\'un concert')
    import datetime, shutil, tempfile
    demain = (datetime.date.today() + datetime.timedelta(days=1)).isoformat()
    travail = tempfile.mkdtemp(prefix='depot-essai-')
    concert = 0
    try:
        concert = int(php('[$id, $e] = concert_enregistrer(null, ["titre_en" => "Concert d\'essai du dépôt", "ensemble_en" => "", "reps" => [["date" => "%s", "heure" => "20:00"]],'
                          ' "contact_com_nom" => "Camille Essai", "contact_com_email" => "com@essai.pupitre.test", "contact_com_langue" => "en"]); if ($e) { fwrite(STDERR, implode(" ", $e)); exit(1); } echo $id;' % demain))
        aller(c, 'concert', id=concert)
        page_saine(c, 'fiche d\'un concert avec son contact communication')
        texte = c.js('document.querySelector("#depot").innerText') or ''
        verifier('Camille Essai' in texte and 'partira d\'elle-même' in texte and 'la veille' in texte, 'la fiche du concert montre le contact et annonce l\'envoi de la veille')
        verifier(php('echo depots_inviter_veille(7);') == '0', 'rien ne part avant neuf heures du matin')
        verifier(php('echo depots_inviter_veille(10);') == '1', 'la veille du concert, l\'invitation part d\'elle-même')
        verifier(php('echo depots_inviter_veille(11);') == '0', 'elle ne part qu\'une fois')
        z = json.loads(php('echo json_encode(depot_de_concert(%d));' % concert))
        journal = php('echo implode("", array_slice(file(STOCKAGE . "/journaux/mails.log"), -30));')
        verifier(z['invite_a'] == 'com@essai.pupitre.test' and 'Photos and videos: Concert d\'essai du dépôt' in journal and 'depot.php?z=' + z['jeton'] in journal,
                 'le mail, en anglais, porte l\'adresse de la zone (et ne part pas vers une adresse d\'essai)')

        # le contact ouvre la zone, sans compte
        envoyer(c, '.tete__compte button')
        adresse = P + 'depot.php?z=' + z['jeton']
        c.erreurs.clear()
        c.ouvrir(adresse, 0.8)
        page_saine(c, 'page de dépôt, hors connexion')
        verifier('Photos and videos from the concert' in (c.js('document.body.innerText') or '') and c.js('document.documentElement.lang') == 'en' and c.js('document.querySelector("#deposant").value') == 'Camille Essai',
                 'la page est dans la langue du contact, à son nom')
        image = os.path.join(travail, 'Concert photo 1.jpg')
        source = next((os.path.join(RACINE, 'assets', 'img', n) for n in sorted(os.listdir(os.path.join(RACINE, 'assets', 'img'))) if n.lower().endswith('.jpg')), None)
        shutil.copy(source, image)
        piege = os.path.join(travail, 'piege.jpg')
        with open(piege, 'w') as f:
            f.write('<?php echo "pas une image"; ?>')
        remplir(c, {'credit': '© Camille Essai', 'legende': 'Applause at the end'})
        deposer(c, '#fichiers', [image, piege])
        verifier('Concert photo 1.jpg' in (c.js('document.querySelector("[data-depot-liste]").textContent') or ''), 'les fichiers choisis sont listés avec leur poids avant l\'envoi')
        envoyer(c, 'form[data-depot] button[type="submit"]')
        texte = c.js('document.body.innerText') or ''
        verifier('Thank you: 1 file received' in texte and 'piege.jpg' in texte and 'not accepted' in texte, 'la photo est reçue ; le fichier qui n\'est pas une image est refusé, quel que soit son nom')
        fichiers = json.loads(php('echo json_encode(depot_fichiers(%d));' % int(z['id'])))
        verifier(len(fichiers) == 1 and fichiers[0]['credit'] == '© Camille Essai' and fichiers[0]['type'] == 'image/jpeg'
                 and os.path.isfile(os.path.join(RACINE, 'pupitre', 'stockage', 'depots', str(z['id']), fichiers[0]['fichier'])), 'elle est rangée hors du web, avec son crédit')
        verifier('Concert photo 1.jpg' in texte and not c.js('!!document.querySelector("img[src*=depot-fichier]")'), 'la page liste ce qui a été déposé, sans rien en montrer')
        c.ouvrir(P + 'depot-fichier.php?id=%d' % int(fichiers[0]['id']), 0.6)
        verifier('connexion.php' in c.js('location.href'), 'hors connexion, un fichier déposé ne se consulte pas')
        c.ouvrir(P + 'depot.php?z=' + '0' * 40, 0.5)
        verifier('not valid' in (c.js('document.body.innerText') or ''), 'une adresse de zone inconnue ne donne accès à rien')
        c.erreurs.clear()                                     # le 404 de cette adresse inconnue était voulu

        # l'équipe en est avertie et retrouve le fichier
        connecter(c, 'pilote')
        php('alertes_recalculer();')
        aller(c, 'notifications')
        verifier('attend d\'être vu pour « Concert d\'essai du dépôt »' in (c.js('document.body.innerText') or ''), 'une notification signale le dépôt')
        aller(c, 'depots')
        page_saine(c, 'écran « Images des concerts »')
        aller(c, 'depot', concert=concert)
        page_saine(c, 'fichiers déposés d\'un concert')
        verifier(c.js('(function(){var i=document.querySelector(".vignette--depot img"); return !!i && i.complete && i.naturalWidth > 0;})()'), 'connecté, le pilote voit l\'image déposée')
        envoyer(c, '.vignette--depot button[value="retenir"]')
        media = php('echo (int) bd_valeur("SELECT media_id FROM depots_fichiers WHERE id = %d");' % int(fichiers[0]['id']))
        m = json.loads(php('echo json_encode(bd_ligne("SELECT type, credit, droits FROM medias WHERE id = %s"));' % (media or '0')) or 'null')
        verifier(bool(m) and m['type'] == 'image' and m['credit'] == '© Camille Essai' and 'Camille Essai' in m['droits'], '« Retenir » met la photo dans la médiathèque, avec son crédit et sa provenance')

        # fermer la zone
        aller(c, 'concert', id=concert)
        envoyer(c, '#depot button[value="depot_fermer"]')
        c.ouvrir(adresse, 0.6)
        verifier('This upload area is closed' in (c.js('document.body.innerText') or '') and not c.js('!!document.querySelector("form[data-depot]")'), 'une zone fermée ne reçoit plus rien')
    finally:
        if concert:
            php('$m = (int) bd_valeur("SELECT f.media_id FROM depots_fichiers f JOIN depots d ON d.id = f.depot_id WHERE d.concert_id = %d AND f.media_id IS NOT NULL"); $z = depot_de_concert(%d);'
                ' concert_supprimer(%d); if ($m) media_supprimer($m); if ($z) @rmdir(STOCKAGE . "/depots/" . (int) $z["id"]); alertes_recalculer();' % (concert, concert, concert))
        php('bd_requete("DELETE FROM courriers WHERE correspondant_email LIKE \'%@essai.pupitre.test\'");')
        shutil.rmtree(travail, ignore_errors=True)
        verifier(php('echo (int) bd_valeur("SELECT COUNT(*) FROM concerts WHERE titre_en = \'Concert d\\\'essai du dépôt\'") + (int) bd_valeur("SELECT COUNT(*) FROM depots d LEFT JOIN concerts c ON c.id = d.concert_id WHERE c.id IS NULL");') == '0',
                 'concert, zone et fichiers d\'essai retirés')


# ---------------------------------------------------------------------------------------------------------
def partie_album(c):
    print('Fiche album : label et orchestre créés d\'ici, textes allégés')
    try:
        aller(c, 'disque', id='nouveau')
        page_saine(c, 'nouvel album')
        noms = c.js('Array.prototype.map.call(document.querySelectorAll("#fiche textarea[name], #fiche input[name]"), function(e){return e.name})')
        verifier('presentation_en' in noms and 'mot_en' in noms and not any(n.startswith(('contexte', 'citations', 'infos')) for n in noms),
                 'les textes d\'un album se réduisent à la présentation et à la note de l\'artiste')
        remplir(c, {'titre': 'Album d\'essai de Pupitre', 'maison': 'Label d\'essai de Pupitre', 'ensemble': 'Orchestre d\'essai de Pupitre', 'annee': '2026'})
        etats = c.js('Array.prototype.map.call(document.querySelectorAll("[data-annuaire-etat]"), function(e){return e.textContent})')
        verifier(len(etats) == 2 and all('nouveau' in e or 'nouvel' in e for e in etats), 'un nom qui n\'est pas dans l\'annuaire est annoncé comme nouveau, avant même d\'enregistrer : %s' % etats)
        envoyer(c, '#fiche button[type="submit"]')
        m = message(c)
        verifier('Album créé' in m and 'Label d\'essai de Pupitre' in m and 'Orchestre d\'essai de Pupitre' in m and 'sa fiche est créée' in m, 'l\'album est créé, et les deux fiches avec lui')
        d = json.loads(php('echo json_encode(bd_ligne("SELECT d.id, d.maison_id, d.ensemble_id, m.categorie AS cm, o.categorie AS co FROM disques d LEFT JOIN annuaire m ON m.id = d.maison_id LEFT JOIN annuaire o ON o.id = d.ensemble_id WHERE d.titre = \'Album d\\\'essai de Pupitre\'"));'))
        verifier(bool(d) and d['cm'] == 'maison_de_disques' and d['co'] == 'orchestre', 'le label et l\'orchestre sont des fiches de l\'annuaire, reliées à l\'album')
        verifier(c.js('Array.prototype.every.call(document.querySelectorAll("[data-annuaire-etat]"), function(e){return e.textContent.indexOf("dans l\'annuaire") >= 0})'), 'de retour sur la fiche, les deux noms sont reconnus dans l\'annuaire')
        envoyer(c, '#fiche button[type="submit"]')
        verifier(php('echo (int) bd_valeur("SELECT COUNT(*) FROM annuaire WHERE nom IN (\'Label d\\\'essai de Pupitre\', \'Orchestre d\\\'essai de Pupitre\')");') == '2', 'enregistrer de nouveau ne crée pas de doublon')
    finally:
        php('$d = bd_ligne("SELECT id FROM disques WHERE titre = \'Album d\\\'essai de Pupitre\'"); if ($d) disque_supprimer((int) $d["id"]);'
            ' foreach (bd_colonne("SELECT id FROM annuaire WHERE nom IN (\'Label d\\\'essai de Pupitre\', \'Orchestre d\\\'essai de Pupitre\')") as $id) { bd_supprimer("annuaire_comptes", ["fiche_id" => $id]); bd_supprimer("annuaire", ["id" => $id]); }')
        verifier(php('echo (int) bd_valeur("SELECT COUNT(*) FROM disques WHERE titre = \'Album d\\\'essai de Pupitre\'") + (int) bd_valeur("SELECT COUNT(*) FROM annuaire WHERE nom LIKE \'%essai de Pupitre\'");') == '0', 'album et fiches d\'essai retirés')


# ---------------------------------------------------------------------------------------------------------
def partie_connexions(c):
    """Metricool, veille, courrier : sur de faux services locaux (tests/faux-services.py), en ligne de commande."""
    print('Connexions : Metricool, veille, courrier (faux services)')
    import socket, time
    regles = php('$e = env_lire(RACINE . "/.env"); echo implode(",", array_keys(array_filter(array_intersect_key($e, array_flip(["METRICOOL_JETON", "IMAP_HOTE", "VEILLE_FLUX_URL", "METRICOOL_URL"])), "strlen")));')
    if regles:
        print('  (%s réglé dans pupitre/.env : cette partie ne tourne que sur de faux services, elle est passée pour ne toucher à rien de réel)' % regles)
        return

    def port_libre():
        s = socket.socket(); s.bind(('127.0.0.1', 0)); p = s.getsockname()[1]; s.close(); return p
    web, imap = port_libre(), port_libre()
    faux = subprocess.Popen([sys.executable, os.path.join(RACINE, 'pupitre', 'tests', 'faux-services.py'), str(web), str(imap)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    env = {**os.environ, 'METRICOOL_JETON': 'jeton-essai', 'METRICOOL_UTILISATEUR': '42', 'METRICOOL_URL': 'http://127.0.0.1:%d/metricool' % web, 'VEILLE_FLUX_URL': 'http://127.0.0.1:%d/actualites/rss' % web,
           'IMAP_HOTE': '127.0.0.1', 'IMAP_PORT': str(imap), 'IMAP_UTILISATEUR': 'essai', 'IMAP_MOT_DE_PASSE': 'secret', 'IMAP_SECURITE': 'aucune'}

    def phpx(code):
        r = subprocess.run([PHP, '-r', 'require "%s/pupitre/inclus/amorce.php"; %s' % (RACINE, code)], capture_output=True, text=True, env=env)
        if r.returncode != 0:
            raise SystemExit('php : ' + r.stderr.strip() + r.stdout.strip())
        return r.stdout.strip()
    cles = ['metricool_marque_reelle', 'metricool_marque_reelle_nom', 'metricool_marque_test', 'metricool_marque_test_nom', 'metricool_mode', 'metricool_releve_le', 'veille_requetes', 'veille_editions', 'veille_le', 'courrier_uid', 'courrier_le']
    avant = json.loads(php('echo json_encode(array_intersect_key(reglages_tous(), array_flip(%s)));' % json.dumps(cles)))
    try:
        for _ in range(60):
            try:
                socket.create_connection(('127.0.0.1', web), timeout=0.5).close(); break
            except OSError:
                time.sleep(0.25)
        # ---- Metricool : marques, relevé, bascule vers les comptes de test, retour
        marques = json.loads(phpx('[$m, $e] = metricool_marques(); echo json_encode($m);'))
        verifier([m['id'] for m in marques] == ['111', '222'] and 'instagram' in marques[0]['reseaux'], 'Pupitre lit la liste des marques du compte Metricool, et les réseaux connectés à chacune')
        phpx('reglage_ecrire("metricool_marque_reelle", "111"); reglage_ecrire("metricool_marque_test", "222"); reglage_ecrire("metricool_mode", "reel");')
        b = json.loads(phpx('echo json_encode(metricool_relever(90));'))
        verifier(b['nouvelles'] == 5 and b['reseaux'] == {'instagram': 4, 'facebook': 1}, 'le relevé range les publications de la marque et leurs mesures (%s)' % b['reseaux'])
        verifier(json.loads(phpx('echo json_encode(metricool_relever(90));'))['nouvelles'] == 0, 'un second relevé met les mesures à jour sans rien doubler')
        posts = json.loads(phpx('echo json_encode(array_map(fn($p) => [$p["identifiant"], (int) $p["interactions"], $p["rapport"], $p["portee"]], posts_lister("111")));'))
        reel = next(p for p in posts if p[0] == 'reel-1')
        verifier(reel[1] == 925 and reel[2] and reel[2] > 2, 'chaque publication est rapportée à l\'habitude de son réseau (le reel : × %s)' % reel[2])
        # une action de Pupitre qui correspond à une publication relevée : elle est reconnue comme venue de Pupitre
        action = int(phpx('[$id, $e] = communication_enregistrer(null, ["canal" => "instagram", "titre" => "Essai de rapprochement", "texte" => "Tonight in Madrid with the Orquesta Nacional: Ravel and Debussy. #essai", "statut" => "programmee",'
                          ' "date_prevue" => date("Y-m-d", time() - 3 * 86400), "heure_prevue" => "18:00", "concerts" => []]); if ($e) { fwrite(STDERR, implode(" ", $e)); exit(1); } echo $id;'))
        verifier(phpx('echo posts_rapprocher("111");') == '1' and phpx('echo bd_valeur("SELECT statut FROM communications WHERE id = %d");' % action) == 'publiee',
                 'une publication qui correspond à une action de Pupitre lui est rattachée, et l\'action passe à « publiée »')
        phpx('reglage_ecrire("metricool_mode", "test");')
        verifier(phpx('echo metricool_marque()["id"];') == '222' and json.loads(phpx('echo json_encode(metricool_relever(30));'))['reseaux'].get('instagram') == 1, 'en mode test, Pupitre lit la marque des comptes de test')
        verifier(phpx('echo count(posts_lister("111")), "/", count(posts_lister("222"));') == '5/1', 'basculer ne mélange ni ne supprime rien : chaque publication reste avec sa marque')
        phpx('reglage_ecrire("metricool_mode", "reel");')
        verifier(phpx('echo metricool_marque()["id"];') == '111', 'retour aux vrais comptes')
        mauvais = subprocess.run([PHP, '-r', 'require "%s/pupitre/inclus/amorce.php"; [$m, $e] = metricool_marques(); echo $e;' % RACINE], capture_output=True, text=True, env={**env, 'METRICOOL_JETON': 'faux'}).stdout
        verifier('refuse l\'accès' in mauvais and 'faux' not in mauvais, 'un jeton refusé donne un message clair, sans jamais citer le jeton')

        # ---- veille : flux d'actualités
        phpx('reglage_ecrire("veille_requetes", "\\"Pierre Bleuse\\""); reglage_ecrire("veille_editions", "fr-FR,en-GB");')
        r = json.loads(phpx('echo json_encode(veille_actualites(30));'))
        verifier(r[0] == 2 and r[1] == 4, 'la veille lit les flux d\'actualités et note les nouveaux signalements (%d lus, %d nouveaux)' % (r[1], r[0]))
        verifier(json.loads(phpx('echo json_encode(veille_actualites(30));'))[0] == 0, 'un signalement déjà noté ne revient pas (deux éditions, deux collectes)')
        v = json.loads(phpx('echo json_encode(bd_lignes("SELECT id, titre, site, statut, source, paru_le FROM veille WHERE site LIKE \'%d\\\'essai\' ORDER BY id"));'))
        verifier(len(v) == 2 and v[0]['titre'] == 'Pierre Bleuse triomphe à Madrid' and v[0]['site'] == 'Le Journal d\'essai' and v[0]['statut'] == 'a_lire' and v[0]['paru_le'],
                 'chacun arrive « à lire », avec son titre, son média et sa date')
        phpx('veille_decider(%d, "retenue");' % int(v[0]['id']))
        verifier(phpx('echo bd_valeur("SELECT COUNT(*) FROM journal WHERE objet_type = \'veille\' AND objet_id = %d");' % int(v[0]['id'])) == '1', 'ce qu\'on en fait est tracé au journal')

        # ---- courrier : la boîte, par IMAP
        phpx('reglage_ecrire("courrier_uid", ""); courrier_noter_envoi("com@essai.pupitre.test", "Camille Essai", "Photos and videos: Concert d\'essai", "texte", "envoye", "", ["nature" => "depot", "objet" => ["concerts", 0]]);')
        r = json.loads(phpx('echo json_encode(courrier_relever());'))
        verifier(r == [2, 3, ''], 'la boîte est lue ; seuls les retours d\'un envoi de Pupitre et les avis de non-remise sont gardés (%s gardés sur %s lus)' % (r[0], r[1]))
        retours = json.loads(phpx('echo json_encode(bd_lignes("SELECT r.type_retour, r.sujet, r.texte, e.sujet AS envoi FROM courriers r LEFT JOIN courriers e ON e.id = r.reponse_a WHERE r.sens = \'recu\' AND r.correspondant_email LIKE \'%@essai.pupitre.test\' ORDER BY r.uid"));'))
        verifier(len(retours) == 2 and retours[0]['type_retour'] == 'reponse' and retours[0]['envoi'] == 'Photos and videos: Concert d\'essai' and 'Voici les photos' in retours[0]['texte'],
                 'une réponse est rattachée à l\'envoi auquel elle répond')
        verifier(retours[1]['type_retour'] == 'rejet', 'un avis de non-remise est reconnu comme tel')
        verifier(json.loads(phpx('echo json_encode(courrier_relever());'))[1] == 0, 'un message déjà vu n\'est pas relu')
        mauvais = subprocess.run([PHP, '-r', 'require "%s/pupitre/inclus/amorce.php"; echo courrier_relever()[2];' % RACINE], capture_output=True, text=True, env={**env, 'IMAP_MOT_DE_PASSE': 'mauvais-secret'}).stdout
        verifier('refuse l\'identifiant ou le mot de passe' in mauvais and 'mauvais-secret' not in mauvais, 'un mot de passe refusé donne un message clair, sans jamais le citer')

        # ---- mails : jamais vers une adresse d'essai ; le gabarit de la charte
        verifier(phpx('echo (int) mail_adresse_essai("x@essai.pupitre.test"), (int) mail_adresse_essai("y@example.com"), (int) mail_adresse_essai("contact@pierrebleuse.com");') == '110', 'une adresse d\'essai est reconnue : rien ne lui est jamais envoyé')
        gabarit = phpx('echo mail_gabarit("Titre d\'essai", "Bonjour,\\n\\nUn texte.\\n\\nhttps://exemple.test/lien", ["bouton" => ["Ouvrir", "https://exemple.test/lien"]]);')
        verifier('logo-email-inverse.png' in gabarit and 'background:#FF8B3D' in gabarit and 'color:#12263F' in gabarit and gabarit.count('https://exemple.test/lien') == 1,
                 'le mail suit la charte : bandeau marine et logo, bouton mandarine à texte marine')
        ids = json.loads(phpx('echo json_encode(["Chœur" => compte_identifiant("instagram", "https://www.instagram.com/choeur_elements/?hl=fr"), "num" => compte_identifiant("facebook", "https://www.facebook.com/100063708931727")]);'))
        verifier(ids == {'Chœur': '@choeur_elements', 'num': ''}, 'l\'identifiant d\'un compte se déduit de son adresse (et un compte désigné par un numéro n\'en a pas)')
    finally:
        faux.terminate()
        nettoyage = ('bd_requete("DELETE FROM posts_sociaux WHERE marque IN (\'111\', \'222\')"); foreach (bd_colonne("SELECT id FROM communications WHERE titre = \'Essai de rapprochement\'") as $id) communication_supprimer((int) $id);'
                     ' bd_requete("DELETE FROM veille WHERE site LIKE \'%d\\\'essai\'"); bd_requete("DELETE FROM courriers WHERE correspondant_email LIKE \'%@essai.pupitre.test\'");'
                     ' $avant = json_decode(AVANT, true); foreach ($avant as $k => $v) reglage_ecrire($k, (string) $v);'
                     ' foreach (array_diff(json_decode(CLES, true), array_keys($avant)) as $k) bd_requete("DELETE FROM reglages WHERE cle = ?", [$k]); alertes_recalculer();')
        php(nettoyage.replace('AVANT', json.dumps(json.dumps(avant))).replace('CLES', json.dumps(json.dumps(cles))))
        verifier(php('echo (int) bd_valeur("SELECT COUNT(*) FROM posts_sociaux WHERE marque IN (\'111\', \'222\')") + (int) bd_valeur("SELECT COUNT(*) FROM veille WHERE site LIKE \'%d\\\'essai\'") + (int) bd_valeur("SELECT COUNT(*) FROM courriers WHERE correspondant_email LIKE \'%@essai.pupitre.test\'");') == '0',
                 'publications, signalements et courriers d\'essai retirés, réglages remis')


PARTIES = {'gestes': partie_gestes, 'album': partie_album, 'apercu': partie_apercu, 'depot': partie_depot, 'connexions': partie_connexions}


def main():
    voulues = [p for p in sys.argv[1:] if p in PARTIES] or list(PARTIES)
    comptes('creer', env={'ESSAI_MDP': MDP})
    try:
        with Chrome(taille=(1440, 900)) as c:
            c.erreurs = []
            connecter(c, 'pilote')
            for nom in voulues:
                PARTIES[nom](c)
    finally:
        comptes('retirer')
    print('nouveautés : ' + ('tout est conforme' if not echecs else '%d échec(s)' % len(echecs)))
    return 1 if echecs else 0


if __name__ == '__main__':
    sys.exit(main())
