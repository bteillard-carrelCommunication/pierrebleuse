#!/usr/bin/env python3
"""parcours.py : essai de Pupitre dans un vrai navigateur (Chrome headless, outils/cdp.py du site).

    python3 pupitre/tests/parcours.py [dossier des captures]

À lancer sur la base locale, migrations passées (php pupitre/sql/migrer.php). L'essai :
  1. crée trois comptes d'essai (pilote, éditeur, artiste), retirés à la fin ;
  2. ouvre chaque écran en pilote (site, carrière, communication, administration) : aucune erreur
     PHP, aucune erreur de console ;
  3. fait le parcours complet : modification de la citation (bloc « Hero ») et d'un album, ajout
     d'un concert annoncé, publication, vérification sur le site, retour à la publication précédente ;
  4. vérifie les droits : l'éditeur ne publie pas et n'ouvre pas les réglages, l'artiste ne
     modifie rien et ne voit que sa vue, où il laisse une note ;
  5. vérifie la vue de l'artiste à 390 px : pas de débordement, utilisable au clavier ;
  6. range les captures demandées (tableau de bord, fiche concert, « Le site », vue de l'artiste).

Le site est modifié par la publication, puis remis dans son état par le retour en arrière :
l'essai vérifie avec git que rien n'a changé. Les contenus modifiés dans Pupitre sont remis
comme ils étaient ; le concert d'essai est supprimé.
"""
import json, os, secrets, subprocess, sys, urllib.parse

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(RACINE, 'outils'))
from cdp import Chrome, adresse_site, php_cli  # noqa: E402

PHP = php_cli()
SITE = adresse_site()
P = SITE + 'pupitre/'
CAPTURES = sys.argv[1] if len(sys.argv) > 1 else os.path.join(RACINE, 'docs', 'pupitre', 'captures')
MDP = secrets.token_urlsafe(18)
echecs = []


SANS_TRANSITION = '*,*::before,*::after{transition:none!important}'      # voir « Captures », plus bas


def php(*args, env=None):
    r = subprocess.run([PHP, os.path.join(RACINE, 'pupitre', 'tests', 'comptes-essai.php'), *args],
                       capture_output=True, text=True, env={**os.environ, **(env or {})})
    if r.returncode != 0:
        raise SystemExit('comptes-essai : ' + r.stderr.strip())
    return r.stdout.strip()


def verifier(condition, quoi):
    print(('  ok     ' if condition else '  ÉCHEC  ') + quoi)
    if not condition:
        echecs.append(quoi)


def page_saine(c, quoi):
    """Ni erreur PHP dans la page, ni erreur de console."""
    texte = c.js('document.body ? document.body.innerText : ""') or ''
    php_ko = any(m in texte for m in ('Warning:', 'Fatal error', 'Notice:', 'Deprecated:', 'Parse error', 'Uncaught'))
    erreurs = list(c.erreurs)
    c.erreurs.clear()
    verifier(not php_ko and not erreurs, quoi + ('' if not (php_ko or erreurs) else ' → ' + (texte[:160] if php_ko else str(erreurs[:2]))))


def aller(c, page='', **params):
    q = urllib.parse.urlencode({'page': page, **params}) if page else ''
    c.ouvrir(P + 'index.php' + ('?' + q if q else ''), 0.6)


def remplir(c, valeurs):
    """Remplit des champs par leur attribut name."""
    c.js('(function(v){for (var n in v){var e=document.querySelector(\'[name="\'+n+\'"]\'); if(!e) throw new Error("champ introuvable : "+n);'
         ' if (e.type==="checkbox") e.checked=!!v[n]; else e.value=v[n]; e.dispatchEvent(new Event("input",{bubbles:true}));}})(%s)' % json.dumps(valeurs))


def envoyer(c, selecteur):
    """Clique un bouton d'envoi (les confirmations sont acceptées) et attend la page suivante."""
    c.js('window.confirm = function () { return true; }')
    c.js('(function(){var b=document.querySelector(%s); if(!b) throw new Error("bouton introuvable : " + %s); b.click();})()' % (json.dumps(selecteur), json.dumps(selecteur)))
    c.attendre(1.2)
    for _ in range(40):
        if c.js('document.readyState') == 'complete':
            break
        c.attendre(0.2)
    c.attendre(0.4)


def ecarts_en_attente():
    """Les fichiers du site que Pupitre modifierait s'il publiait maintenant."""
    code = ('require "%s/pupitre/inclus/amorce.php"; $e = publication_etat();'
            ' echo json_encode(array_merge(array_keys(array_filter($e["fichiers"], fn($f) => in_array($f["etat"], ["modifie", "nouveau"], true))), array_values($e["medias"])));' % RACINE)
    r = subprocess.run([PHP, '-r', code], capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit('écarts : ' + r.stderr.strip() + r.stdout.strip())
    return json.loads(r.stdout)


def connecter(c, role):
    c.ouvrir(P + 'connexion.php', 0.5)
    remplir(c, {'email': '%s@essai.pupitre.test' % role, 'mot_de_passe': MDP})
    envoyer(c, 'form.porte__bloc button[type="submit"]')


def deconnecter(c):
    aller(c)
    envoyer(c, '.tete__compte button')


def message(c):
    return c.js('(function(){var m=document.querySelector(".message"); return m ? m.textContent : "";})()') or ''


def git_site():
    r = subprocess.run(['git', 'status', '--short', '--', 'data', '*.html', 'assets'], cwd=RACINE, capture_output=True, text=True)
    return r.stdout.strip()


def main():
    os.makedirs(CAPTURES, exist_ok=True)
    depart_git = git_site()
    php('creer', env={'ESSAI_MDP': MDP})
    try:
        with Chrome(taille=(1440, 900)) as c:
            print('Sans connexion')
            c.ouvrir(P + 'index.php?page=agenda', 0.5)
            verifier('connexion.php' in c.js('location.href'), 'une page demandée sans connexion renvoie à l\'écran de connexion')
            remplir(c, {'email': 'pilote@essai.pupitre.test', 'mot_de_passe': 'mauvais-mot-de-passe'})
            envoyer(c, 'form.porte__bloc button[type="submit"]')
            verifier('incorrect' in message(c), 'un mauvais mot de passe est refusé')

            print('Pilote : chaque écran')
            ecarts_depart = ecarts_en_attente()
            connecter(c, 'pilote')
            verifier('index.php' in c.js('location.href') and 'Tableau de bord' in c.js('document.title'), 'le pilote entre et arrive sur le tableau de bord')
            verifier(c.js('(function(){var l=document.querySelector(".lateral .marque__logo"); return !!l && l.alt === "Pupitre" && l.naturalWidth > 0;})()')
                     and c.js('(document.querySelector(".marque__sous-titre")||{}).textContent') == 'Pierre Bleuse',
                     'logotype de Pupitre (image chargée, texte de remplacement « Pupitre »), sous-titre lu dans les réglages')
            ecrans = [('bord', {}), ('apercus', {}), ('depots', {}), ('resultats', {}), ('veille', {}), ('courrier', {}), ('courrier', {'vue': 'envois'}), ('agenda', {}), ('agenda', {'periode': 'tout', 'statut': 'publiee'}), ('concert', {'id': 'nouveau'}), ('annuaire', {}),
                      ('annuaire', {'categorie': 'salle'}), ('fiche', {'id': 'nouvelle'}), ('imports', {}),
                      ('blocs', {}), ('bloc', {'cle': 'general'}), ('bloc', {'cle': 'ouverture'}), ('bloc', {'cle': 'bio'}), ('bloc', {'cle': 'agenda'}), ('bloc', {'cle': 'disques'}),
                      ('bloc', {'cle': 'parcours-fonctions'}), ('bloc', {'cle': 'parcours-collaborations'}), ('bloc', {'cle': 'parcours-monde'}), ('bloc', {'cle': 'presse'}),
                      ('bloc', {'cle': 'telechargement'}), ('bloc', {'cle': 'suivre'}), ('bloc', {'cle': 'contact'}), ('bloc', {'cle': 'pied'}), ('visuels', {}),
                      ('orchestres', {}), ('orchestres', {'nouvelle': '1'}), ('disques', {}), ('disque', {'id': 1}), ('disque', {'id': 'nouveau'}), ('distinctions', {}), ('parcours', {}),
                      ('presse', {}), ('article', {'id': 1}), ('article', {'id': 'nouveau'}), ('musiques', {}),
                      ('contenu', {'type': 'telechargements'}), ('contenu', {'type': 'contacts'}), ('medias', {}), ('media', {'id': 1}), ('site', {}),
                      ('apparence', {}), ('langues', {}), ('traductions', {}), ('traductions', {'section': 'concerts'}), ('langues', {'onglet': 'libelles'}), ('base', {}),
                      ('journal', {}), ('comptes', {}), ('reglages', {}), ('artiste', {}), ('carte', {}), ('carte', {'nouvelle': '1'}),
                      ('editorial', {}), ('social', {}), ('newsletter', {}), ('communication', {'id': 'nouvelle'}), ('communication', {'id': 'nouvelle', 'famille': 'lettre'}),
                      ('notifications', {}), ('recherche', {'q': 'paris'}), ('interface', {}), ('avenir', {'quoi': 'publications'})]
            for page, params in ecrans:
                aller(c, page, **params)
                page_saine(c, 'écran ' + page + (' ' + json.dumps(params, ensure_ascii=False) if params else ''))
            aller(c, 'concert', id=7)
            premier = c.js('(function(){return document.title})()')
            page_saine(c, 'fiche concert (' + premier.split(' · ')[0] + ')')
            aller(c, 'fiche', id=1)
            page_saine(c, 'fiche d\'annuaire')

            print('Captures')
            # Pour une capture de la page entière : rien de collé au bord de la fenêtre (la barre du haut et la barre
            # d'actions reprennent leur place dans la page, le menu latéral court sur toute sa hauteur), et aucune
            # transition : pour photographier au-delà de la fenêtre, Chrome la réduit un instant à 1 px puis lui rend
            # sa taille, et la photographie saisirait le menu au milieu de sa transition de largeur.
            a_plat = ('(function(){var s=document.createElement("style"); s.textContent="' + SANS_TRANSITION +
                      ' .tete,.actions--bas{position:static!important} .lateral{position:absolute!important} body{position:relative}";'
                      ' document.head.appendChild(s);})()')
            aller(c, 'bord')
            c.js(a_plat)
            c.capture(os.path.join(CAPTURES, 'tableau-de-bord.png'), pleine=True)
            aller(c, 'concert', id=7)
            c.js(a_plat)
            c.capture(os.path.join(CAPTURES, 'fiche-concert.png'), pleine=True)

            print('Parcours complet')
            aller(c, 'bloc', cle='ouverture')
            citation = c.js('document.querySelector(\'[name="citation_en"]\').value')
            remplir(c, {'citation_en': '« Citation d\'essai de Pupitre »'})
            envoyer(c, 'form.carte .actions--bas button[type="submit"]')
            verifier('enregistr' in message(c).lower(), 'la citation est modifiée (bloc « Hero »)')

            aller(c, 'disque', id=1)
            annee_avant = c.js('document.querySelector(\'[name="annee"]\').value')
            remplir(c, {'annee': '1987'})
            envoyer(c, 'form .actions--bas button[type="submit"]')
            verifier('nregistr' in message(c), 'un album est modifié (son année)')

            aller(c, 'concert', id='nouveau')
            remplir(c, {'titre_en': 'Concert d\'essai de Pupitre', 'ensemble_en': 'Ensemble intercontemporain', 'salle_en': 'Salle d\'essai',
                        'ville_en': 'Paris', 'pays_en': 'France', 'reps[0][date]': '2027-06-30', 'reps[0][heure]': '20:00'})
            envoyer(c, '#fiche button[type="submit"]')
            concert = urllib.parse.parse_qs(urllib.parse.urlparse(c.js('location.href')).query).get('id', [''])[0]
            verifier(concert.isdigit() and 'connues' in message(c), 'un concert est créé, sa représentation est « connue »')
            verifier(c.js('!!document.querySelector(".statut--connue")'), 'la fiche montre le statut « Connue »')
            # annoncer sans lien source : refusé par le serveur (le champ « required » du navigateur est contourné)
            c.js('(function(){var f=document.querySelector(\'input[name="statut"][value="annoncee"]\').form; f.querySelector(\'[name="lien_source"]\').value=""; f.noValidate=true; f.submit();})()')
            c.attendre(1.5)
            verifier('lien source est obligatoire' in message(c), 'annoncer sans lien source est refusé')
            c.js('(function(){var f=document.querySelector(\'input[name="statut"][value="annoncee"]\').form; f.querySelector(\'[name="lien_source"]\').value="https://exemple.test/annonce"; f.submit();})()')
            c.attendre(1.5)
            verifier(c.js('!!document.querySelector(".statut--annoncee")'), 'avec un lien source, la représentation passe à « Annoncée »')
            page_saine(c, 'fiche du concert d\'essai')

            aller(c, 'site')
            c.js('(function(){var d=document.querySelector("details.ecart"); if (d) d.open = true;})()')
            c.js(a_plat)
            c.capture(os.path.join(CAPTURES, 'le-site.png'), pleine=True)
            ecarts = c.js('Array.prototype.map.call(document.querySelectorAll(".ecart code"), function(n){return n.textContent}).join(" ")')
            verifier(all(f in ecarts for f in ('data/textes.js', 'data/disques.js', 'data/agenda.js', 'index.html')), 'l\'écran « Le site » liste les écarts, fichier par fichier')
            verifier('Concert d\'essai de Pupitre' in c.js('document.body.innerText'), 'il annonce la représentation qui passera à « publiée »')
            envoyer(c, 'form button[data-confirmer^="Publier"]')
            verifier('Publié' in message(c), 'la publication se fait : ' + message(c)[:90])
            aller(c, 'site')
            verifier('aucun écart' in c.js('document.body.innerText'), 'après publication, plus aucun écart')
            aller(c, 'concert', id=concert)
            verifier(c.js('!!document.querySelector(".statut--publiee")'), 'la représentation annoncée est passée à « Publiée »')

            print('Le site, après publication')
            # les textes se rédigent en anglais : c'est le site anglais qui change ; le français garde sa traduction, « à revoir »
            c.ouvrir(SITE + '?lang=en&muet=1&aimant=0&date=2026-11-12', 2.5)
            verifier('Citation d\'essai de Pupitre' in c.js('document.querySelector(".ouverture__citation").textContent'), 'la nouvelle citation est sur le site, en anglais (langue de rédaction)')
            verifier(c.js('JSON.stringify(SiteContenu.ecarts)') == '[]' and not c.erreurs, 'SiteContenu.ecarts vide, console sans erreur')
            verifier(c.js('Array.prototype.map.call(document.querySelectorAll("[data-langues] [data-langue]"), function (a) { return a.getAttribute("data-langue"); }).join(",")').startswith('en'), 'le sélecteur de langue du site commence par l\'anglais')
            c.erreurs.clear()
            c.ouvrir(SITE + '?lang=fr&muet=1&aimant=0&date=2026-11-12', 2.5)
            verifier('Citation d\'essai' not in c.js('document.querySelector(".ouverture__citation").textContent') and c.js('document.querySelector(".ouverture__citation").textContent.trim().length') > 0, 'en français, la citation garde sa traduction')
            verifier('1987' in c.js('document.querySelector("[data-disques]").textContent'), 'l\'année de l\'album est sur le site')
            c.erreurs.clear()
            c.ouvrir(SITE + 'concerts.html?lang=fr&date=2026-11-12', 1.2)
            verifier('Concert d\'essai de Pupitre' in c.js('document.body.innerText') and '30 juin 2027' in c.js('document.body.innerText').lower(), 'le concert annoncé est dans concerts.html')
            c.erreurs.clear()

            print('Retour à la publication précédente')
            aller(c, 'site')
            envoyer(c, 'form button[data-confirmer^="Remettre"]')
            verifier('revenu' in message(c), 'le retour en arrière se fait : ' + message(c)[:80])
            verifier(git_site() == depart_git, 'les fichiers du site sont exactement ceux d\'avant la publication (git)')
            c.ouvrir(SITE + '?lang=en&muet=1&aimant=0&date=2026-11-12', 2)
            verifier('Citation d\'essai' not in c.js('document.querySelector(".ouverture__citation").textContent'), 'le site a retrouvé sa citation')
            c.erreurs.clear()
            aller(c, 'concert', id=concert)
            verifier(c.js('!!document.querySelector(".statut--annoncee")'), 'la représentation est repassée à « Annoncée »')
            aller(c, 'site')
            verifier('data/textes.js' in c.js('document.body.innerText'), 'les écarts réapparaissent : Pupitre n\'a pas changé')

            print('Remise en état des contenus')
            aller(c, 'bloc', cle='ouverture')
            remplir(c, {'citation_en': citation})
            envoyer(c, 'form.carte .actions--bas button[type="submit"]')
            aller(c, 'disque', id=1)
            remplir(c, {'annee': annee_avant})
            envoyer(c, 'form .actions--bas button[type="submit"]')
            aller(c, 'concert', id=concert)
            envoyer(c, 'button[data-confirmer^="Supprimer ce concert"]')
            aller(c, 'site')
            # ce qui attendait d'être publié avant l'essai (des modifications du pilote, par exemple) attend toujours : ni plus, ni moins
            verifier(ecarts_en_attente() == ecarts_depart, 'contenus remis comme ils étaient : les écarts sont ceux d\'avant l\'essai (%s)' % (', '.join(ecarts_depart) or 'aucun'))

            print('Dépôt d\'un extrait et tâche de fond')
            if php('ia') == 'oui':
                print('  (la clé de l\'API est renseignée : ce passage, qui attend l\'erreur « clé absente », est sauté)')
            else:
                aller(c, 'imports')
                remplir(c, {'texte': 'EXTRAIT FICTIF pour l\'essai : 02/10/2026 20:00, Ensemble fictif, Salle fictive, Ville-fictive'})
                envoyer(c, 'form[enctype] button[type="submit"]')
                verifier('page=import' in c.js('location.href'), 'le dépôt d\'un texte collé mène à l\'écran de suivi')
                for _ in range(20):                    # la tâche tourne en arrière-plan ; la page se recharge seule
                    c.attendre(1)
                    if 'a échoué' in c.js('document.body.innerText'):
                        break
                corps = c.js('document.body.innerText')
                verifier('La lecture a échoué' in corps and 'ANTHROPIC_API_KEY' in corps, 'sans clé, la tâche de fond s\'arrête sur une erreur lisible, et l\'écran la montre')
                verifier('Rien n\'a été retenu' in corps, 'rien n\'est retenu, l\'agenda ne change pas')
                page_saine(c, 'écran de l\'import en échec')

            deconnecter(c)

            print('Éditeur')
            connecter(c, 'editeur')
            aller(c, 'reglages')
            verifier('Accès refusé' in c.js('document.body.innerText'), 'l\'éditeur n\'ouvre pas les réglages')
            aller(c, 'comptes')
            verifier('Accès refusé' in c.js('document.body.innerText'), 'l\'éditeur n\'ouvre pas les comptes')
            aller(c, 'site')
            verifier(not c.js('!!document.querySelector(\'form button[data-confirmer^="Publier"]\')'), 'l\'éditeur voit « Le site », sans bouton « Publier »')
            statut = c.js('fetch(%s, {method:"POST", credentials:"same-origin", headers:{"Content-Type":"application/x-www-form-urlencoded"},'
                          ' body:"action=publier&csrf=" + encodeURIComponent(document.querySelector(\'meta[name="csrf"]\').content)}).then(function(r){return r.status})' % json.dumps(P + 'index.php?page=site'))
            verifier(statut == 403, 'une publication envoyée par l\'éditeur est refusée par le serveur (403)')
            c.erreurs.clear()                      # les refus ci-dessus, attendus, sont notés par la console
            aller(c, 'agenda')
            page_saine(c, 'l\'éditeur ouvre l\'agenda')
            deconnecter(c)

        print('Artiste, à 390 px')
        with Chrome(taille=(390, 844), tactile=False) as c:
            lien = php('lien')
            c.ouvrir(lien, 0.6)
            verifier('Entrer dans Pupitre' in c.js('document.body.innerText'), 'le lien de connexion ouvre l\'écran d\'entrée')
            envoyer(c, 'button[type="submit"]')
            verifier('page=' not in c.js('location.search') and c.js('!!document.querySelector(".artiste")'), 'l\'artiste entre et arrive sur sa vue')
            page_saine(c, 'vue de l\'artiste')
            verifier(not c.js('document.documentElement.scrollWidth > window.innerWidth + 1'), 'aucun débordement horizontal à 390 px')
            verifier(not c.js('!!document.querySelector(".menu")'), 'pas de menu : la vue est sa seule page')
            corps = c.js('document.body.innerText')
            verifier(all(t in corps for t in ('PROCHAIN CONCERT', 'CETTE SEMAINE', 'LA SAISON À VENIR', 'Site mis à jour')) or all(t in corps for t in ('Prochain concert', 'Cette semaine', 'La saison à venir', 'Site mis à jour')),
                     'prochain concert, cette semaine, la saison par mois, date de mise à jour du site')
            verifier(c.js('(function(){var d=document.querySelectorAll(".mois-artiste .concert"); return d.length > 0 && d.length === document.querySelectorAll(".mois-artiste .concert .statut").length;})()'), 'chaque date de la saison porte son statut')
            c.capture(os.path.join(CAPTURES, 'vue-artiste-390.png'))
            c.js('(function(){var s=document.createElement("style"); s.id="essai-fige"; s.textContent="' + SANS_TRANSITION + '"; document.head.appendChild(s);})()')
            c.capture(os.path.join(CAPTURES, 'vue-artiste-390-pleine.png'), pleine=True)
            c.js('(function(){var s=document.getElementById("essai-fige"); if (s) s.remove();})()')
            # au clavier : la tabulation atteint « Laisser une note », Entrée l'ouvre, on écrit, on envoie
            atteint = False
            for _ in range(12):
                c.touche('Tab', 'Tab')
                c.attendre(0.05)
                if c.js('document.activeElement && document.activeElement.tagName') == 'SUMMARY':
                    atteint = True
                    break
            verifier(atteint, 'au clavier, la tabulation atteint « Laisser une note »')
            visible = c.js('(function(){var s=getComputedStyle(document.activeElement); return s.outlineStyle !== "none" && parseFloat(s.outlineWidth) >= 2;})()')
            verifier(bool(visible), 'l\'élément qui a le focus est entouré d\'un contour visible')
            c.js('document.activeElement.click()')
            c.attendre(0.2)
            c.js('(function(){var d=document.activeElement.closest("details"); var t=d.querySelector("textarea"); t.focus(); t.value="Note d\'essai : merci de vérifier l\'heure."; })()')
            c.js('document.activeElement.form.requestSubmit()')
            c.attendre(1.5)
            verifier('transmise' in message(c), 'l\'artiste laisse une note sur un concert')
            # l'artiste ne peut rien d'autre
            aller(c, 'agenda')
            verifier('Accès refusé' in c.js('document.body.innerText'), 'l\'artiste n\'ouvre pas l\'agenda')
            statut = c.js('fetch(%s, {method:"POST", credentials:"same-origin", headers:{"Content-Type":"application/x-www-form-urlencoded"},'
                          ' body:"action=contenus&citation_en=x&csrf=" + encodeURIComponent(document.querySelector(\'meta[name="csrf"]\').content)}).then(function(r){return r.status})' % json.dumps(P + 'index.php?page=bloc&cle=ouverture'))
            verifier(statut == 403, 'une modification envoyée par l\'artiste est refusée par le serveur (403)')
            statut = c.js('fetch(%s, {credentials:"same-origin"}).then(function(r){return r.status})' % json.dumps(P + 'media.php?id=1'))
            verifier(statut == 403, 'l\'artiste n\'a pas accès aux fichiers de la médiathèque (403)')
            deconnecter(c)
            c.ouvrir(lien, 0.6)
            verifier('plus valable' in c.js('document.body.innerText'), 'le lien de connexion ne sert qu\'une fois')

        print('La note de l\'artiste arrive dans « À valider »')
        with Chrome(taille=(1440, 900)) as c:
            connecter(c, 'pilote')
            verifier('Note d\'essai' in c.js('document.querySelector("#b-valider").closest("section").innerText'), 'elle figure dans le bloc « À valider » du tableau de bord')
            page_saine(c, 'tableau de bord avec une note à valider')
            deconnecter(c)
    finally:
        php('retirer')
        if echecs:
            print('  (en cas d\'arrêt en cours de route : vérifier dans Pupitre la citation du bloc « Hero », l\'année du premier album et l\'absence du « Concert d\'essai de Pupitre »)')
    print('%d échec(s)' % len(echecs) if echecs else 'parcours : tout est conforme')
    return 1 if echecs else 0


if __name__ == '__main__':
    sys.exit(main())
