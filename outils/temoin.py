#!/usr/bin/env python3
"""temoin.py : prouve que le rendu du site n'a pas changé.

    python3 outils/temoin.py capturer <dossier>
    python3 outils/temoin.py comparer <dossier de référence> <dossier à comparer>

« capturer » ouvre l'accueil, concerts.html et presse.html en français et en anglais (Chrome
headless, 1440 × 900) et range dans le dossier, pour chaque page :
  - des captures PNG (accueil : une par section ; pages techniques : la fenêtre et la page entière) ;
  - le DOM rendu (*.dom.html) et le texte visible (*.texte.txt) ;
  - bilan.json : SiteContenu.ecarts et les erreurs de console.

« comparer » confronte deux dossiers : texte visible identique, DOM identique une fois retiré ce
qui ne se voit pas (commentaires, scripts, numéros de version, attributs de service), écarts et
erreurs de console vides. Les images ne sont pas comparées au pixel : la nuée et la vidéo bougent.

L'adresse est figée pour que le témoin soit rejouable : date de référence de l'agenda, nuée gelée,
pas de lecture automatique, pas d'aimantation. La référence est dans
docs/pupitre/captures/reference/ (captures converties en JPEG). Elle a été prise le 12/11/2026,
avant que les contenus ne passent dans data/, puis reprise le 13/11/2026 après les retours de ce
jour (logo, police, fiches, contacts) : pour la refaire après une évolution voulue du rendu,
« capturer » dans ce dossier.
"""
import json, os, re, sys
from html.parser import HTMLParser

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

BASE = 'http://localhost:8888/pierrebleuse/'
FIGE = 'date=2026-11-12&muet=1&aimant=0&t=104'
SECTIONS = ['ouverture', 'bio', 'visuel-1', 'agenda', 'visuel-2', 'disques', 'parcours', 'visuel-3',
            'presse', 'telechargement', 'suivre', 'contact', 'pied']
PAGES = [('accueil', ''), ('concerts', 'concerts.html'), ('presse', 'presse.html')]
LANGUES = ('fr', 'en')


def capturer(sortie):
    from cdp import Chrome
    os.makedirs(sortie, exist_ok=True)
    bilan = {}
    for nom, chemin in PAGES:
        for langue in LANGUES:
            cle = '%s-%s' % (nom, langue)
            url = '%s%s?lang=%s&%s' % (BASE, chemin, langue, FIGE)
            with Chrome(taille=(1440, 900)) as c:
                c.ouvrir(url, 3)
                ecarts = c.js('window.SiteContenu ? JSON.stringify(SiteContenu.ecarts) : "absent"')
                with open(os.path.join(sortie, cle + '.dom.html'), 'w') as f:
                    f.write(c.js('document.documentElement.outerHTML'))
                with open(os.path.join(sortie, cle + '.texte.txt'), 'w') as f:
                    f.write(c.js('document.body.innerText'))
                if nom == 'accueil':
                    for i, sec in enumerate(SECTIONS):
                        y = c.js('(function(){var e=document.getElementById(%s); return e ? Math.round(e.getBoundingClientRect().top + window.scrollY) : -1;})()' % json.dumps(sec))
                        if y < 0:
                            print('section introuvable :', sec)
                            continue
                        c.defiler(y, 3.2 if sec.startswith('visuel') else 1.8)
                        c.capture(os.path.join(sortie, '%s-%02d-%s.png' % (cle, i + 1, sec)))
                    with open(os.path.join(sortie, cle + '.dom-apres-defilement.html'), 'w') as f:
                        f.write(c.js('document.documentElement.outerHTML'))
                else:
                    c.capture(os.path.join(sortie, cle + '.png'))
                    c.capture(os.path.join(sortie, cle + '-pleine.png'), pleine=True)
                bilan[cle] = {'url': url, 'ecarts': ecarts, 'erreurs': list(c.erreurs),
                              'titre': c.js('document.title'), 'lang': c.js('document.documentElement.lang')}
                print(cle, '| écarts', ecarts, '| erreurs', len(c.erreurs), c.erreurs[:3])
    with open(os.path.join(sortie, 'bilan.json'), 'w') as f:
        json.dump(bilan, f, ensure_ascii=False, indent=1)
    return 0


# ---------- Comparaison ------------------------------------------------------------------------

# Balises sans effet sur le rendu comparé, ou dont le contenu bouge d'une image à l'autre.
BALISES_IGNOREES = {'script', 'link', 'style', 'noscript'}
# Attributs de service : repères du rendu, sans effet visuel.
ATTRIBUTS_IGNORES = {'data-t', 'data-t-attr', 'data-rendu', 'data-contacts', 'data-telechargements',
                     'data-parcours-liste', 'data-reseaux', 'data-reseau', 'tabindex'}
# Attributs que le moteur fait varier en continu (apparitions, canvas, lecture) : hors comparaison.
ATTRIBUTS_VIVANTS = {'style', 'width', 'height', 'data-etat', 'aria-pressed'}
# Classes posées ou retirées au fil du temps par le moteur.
CLASSES_VIVANTES = re.compile(r'^(est-|is-in|lumiere-|haut-visible|js-timeout|i18n-attente|animation-)')
VIDES = {'meta', 'img', 'br', 'wbr', 'input', 'col', 'source', 'hr', 'path', 'circle', 'rect', 'ellipse'}


class Canon(HTMLParser):
    """DOM réduit à ce qui se voit : balises, attributs utiles triés, texte aux espaces normalisés."""
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.lignes, self.ignore = [], 0

    def handle_starttag(self, balise, attrs):
        if balise in BALISES_IGNOREES:
            if balise not in ('link',):
                self.ignore += 1
            return
        if self.ignore:
            return
        garde = []
        for k, v in attrs:
            if k in ATTRIBUTS_IGNORES:
                continue
            if k in ATTRIBUTS_VIVANTS and not (k == 'style' and balise in ('img', 'video', 'col')):
                continue
            v = v or ''
            if k == 'class':
                v = ' '.join(sorted(c for c in v.split() if not CLASSES_VIVANTES.match(c)))
                if not v:
                    continue
            if k in ('href', 'src', 'poster'):
                v = re.sub(r'\?v=[0-9A-Za-z]+', '', v)
            garde.append('%s="%s"' % (k, v))
        self.lignes.append('<%s%s>' % (balise, (' ' + ' '.join(sorted(garde))) if garde else ''))

    def handle_endtag(self, balise):
        if balise in BALISES_IGNOREES:
            if balise != 'link':
                self.ignore = max(0, self.ignore - 1)
            return
        if not self.ignore and balise not in VIDES:
            self.lignes.append('</%s>' % balise)

    def handle_data(self, d):
        if self.ignore:
            return
        t = re.sub(r'\s+', ' ', d).strip()
        if t:
            self.lignes.append(t)


def canon(chemin):
    p = Canon()
    with open(chemin, encoding='utf-8') as f:
        p.feed(f.read())
    return p.lignes


def premiers_ecarts(a, b, n=6):
    import difflib
    sortie = []
    for l in difflib.unified_diff(a, b, 'référence', 'comparé', lineterm='', n=0):
        if l.startswith(('---', '+++', '@@')):
            continue
        sortie.append('      ' + l[:200])
        if len(sortie) >= n:
            sortie.append('      …')
            break
    return sortie


def comparer(ref, autre):
    ok = True
    with open(os.path.join(autre, 'bilan.json'), encoding='utf-8') as f:
        bilan = json.load(f)
    for nom, _ in PAGES:
        for langue in LANGUES:
            cle = '%s-%s' % (nom, langue)
            soucis = []
            b = bilan.get(cle, {})
            if b.get('ecarts') != '[]':
                soucis.append('SiteContenu.ecarts : %s' % b.get('ecarts'))
            if b.get('erreurs'):
                soucis.append('console : %s' % b.get('erreurs'))
            with open(os.path.join(ref, cle + '.texte.txt'), encoding='utf-8') as f: ta = f.read().splitlines()
            with open(os.path.join(autre, cle + '.texte.txt'), encoding='utf-8') as f: tb = f.read().splitlines()
            if ta != tb:
                soucis.append('texte visible différent')
                soucis += premiers_ecarts(ta, tb)
            suffixes = ['.dom.html'] + (['.dom-apres-defilement.html'] if nom == 'accueil' else [])
            for s in suffixes:
                da, db = canon(os.path.join(ref, cle + s)), canon(os.path.join(autre, cle + s))
                if da != db:
                    soucis.append('DOM différent (%s)' % s)
                    soucis += premiers_ecarts(da, db)
            print('%-12s %s' % (cle, 'identique' if not soucis else 'DIFFÉRENT'))
            for s in soucis:
                print('   ' + s)
            ok = ok and not soucis
    print('témoin : ' + ('rendu identique à la référence' if ok else 'des différences sont à examiner'))
    return 0 if ok else 1


if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == 'capturer':
        sys.exit(capturer(sys.argv[2]))
    if len(sys.argv) == 4 and sys.argv[1] == 'comparer':
        sys.exit(comparer(sys.argv[2], sys.argv[3]))
    print(__doc__)
    sys.exit(2)
