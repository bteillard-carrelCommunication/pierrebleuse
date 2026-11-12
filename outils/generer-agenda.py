#!/usr/bin/env python3
"""Régénère ce qui dépend de data/agenda.json (la source de l'agenda).

    python3 outils/generer-agenda.py

1. data/agenda.js : les mêmes données, en script classique
   (window.AGENDA_DONNEES), chargé avant le moteur pour un rendu synchrone.
2. Dans index.html, entre les deux repères AGENDA-STATIQUE, la liste
   simple en français qui sert de repli sans JavaScript (agenda complet).

Python 3 standard, aucune dépendance. À relancer après chaque modification
de agenda.json, puis vérifier la page.
"""
import html
import json
import re
import sys
from pathlib import Path

SITE = Path(__file__).resolve().parent.parent
SOURCE = SITE / 'data' / 'agenda.json'
SORTIE = SITE / 'data' / 'agenda.js'
PAGE = SITE / 'index.html'
DEBUT = '<!-- AGENDA-STATIQUE:DEBUT'
FIN = '<!-- AGENDA-STATIQUE:FIN -->'

MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
        'août', 'septembre', 'octobre', 'novembre', 'décembre']
TYPES = {'concert', 'opera', 'tournee', 'disque'}
NBSP = ' '


def fr(champ):
    """Champ écrit en chaîne ou en {fr, en} : la version française."""
    if champ is None:
        return ''
    if isinstance(champ, str):
        return champ
    return champ.get('fr', '')


def typo(s):
    """Espaces insécables : avant : ; ? ! », après « et après n°."""
    s = re.sub(r' ([:;?!»])', NBSP + r'\1', s)
    s = s.replace('« ', '«' + NBSP)
    return re.sub(r'(n°) (?=\d)', r'\1' + NBSP, s)


def texte(s):
    return html.escape(typo(s), quote=False).replace(NBSP, '&nbsp;')


def jour(d):
    return '1er' if d == 1 else str(d)


def plage(a, b):
    """« 14 novembre 2026 », « 27–29 novembre 2026 », « 30 avril – 2 mai 2027 »."""
    (ya, ma, da), (yb, mb, db) = [tuple(int(x) for x in d.split('-')) for d in (a, b)]
    fin = f'{jour(db)} {MOIS[mb - 1]} {yb}'
    if a == b:
        return fin
    if (ya, ma) == (yb, mb):
        return f'{jour(da)}–{fin}'
    if ya == yb:
        return f'{jour(da)} {MOIS[ma - 1]} – {fin}'
    return f'{jour(da)} {MOIS[ma - 1]} {ya} – {fin}'


def controler(donnees):
    erreurs = []
    vus = set()
    for e in donnees.get('evenements', []):
        ident = e.get('id', '?')
        if ident in vus:
            erreurs.append(f'{ident} : identifiant en double')
        vus.add(ident)
        if e.get('type') not in TYPES:
            erreurs.append(f'{ident} : type inconnu ({e.get("type")})')
        if not fr(e.get('titre')):
            erreurs.append(f'{ident} : titre manquant')
        reps = e.get('representations') or []
        if not reps:
            erreurs.append(f'{ident} : aucune représentation')
        for r in reps:
            if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', r.get('date', '')):
                erreurs.append(f'{ident} : date invalide ({r.get("date")})')
            if 'heure' in r and not re.fullmatch(r'\d{1,2}:\d{2}', r['heure']):
                erreurs.append(f'{ident} : heure invalide ({r["heure"]})')
    return erreurs


def ecrire_js(donnees):
    lignes = [json.dumps(e, ensure_ascii=False, separators=(',', ':')) for e in donnees['evenements']]
    tete = {k: v for k, v in donnees.items() if k != 'evenements'}
    corps = json.dumps(tete, ensure_ascii=False, separators=(',', ':'))[:-1]
    js = ('/* Généré par outils/generer-agenda.py à partir de data/agenda.json. Ne pas modifier à la main. */\n'
          'window.AGENDA_DONNEES = ' + corps + ',"evenements":[\n' + ',\n'.join(lignes) + '\n]};\n')
    SORTIE.write_text(js, encoding='utf-8')
    return len(lignes)


def liste_statique(donnees, retrait):
    """Une ligne par événement, dans l'ordre des dates : date ou plage, ville(s), titre."""
    evts = sorted(donnees['evenements'], key=lambda e: min(r['date'] for r in e['representations']))
    lignes = []
    for e in evts:
        dates = sorted(r['date'] for r in e['representations'])
        if e['type'] == 'disque':
            ou = 'Disque'
        else:
            villes = []
            for r in e['representations']:
                v = fr(r.get('ville') or e.get('ville'))
                if v and v not in villes:
                    villes.append(v)
            ou = ', '.join(villes)
        lignes.append(
            f'{retrait}  <li class="ligne"><span class="ligne__quand">{texte(plage(dates[0], dates[-1]))}</span> '
            f'<span class="ligne__ville">{texte(ou)}</span> '
            f'<span class="ligne__titre">{texte(fr(e["titre"]))}</span></li>')
    return (f'{retrait}<ol class="agenda__simple bloc-plein" data-revele>\n' + '\n'.join(lignes) + f'\n{retrait}</ol>')


def ecrire_page(donnees):
    page = PAGE.read_text(encoding='utf-8')
    i = page.find(DEBUT)
    j = page.find(FIN)
    if i < 0 or j < 0 or j < i:
        return False
    fin_repere = page.index('-->', i) + 3
    retrait = re.search(r'([ \t]*)$', page[:i]).group(1)
    nouveau = page[:fin_repere] + '\n' + liste_statique(donnees, retrait) + '\n' + retrait + page[j:]
    if nouveau != page:
        PAGE.write_text(nouveau, encoding='utf-8')
    return True


def main():
    donnees = json.loads(SOURCE.read_text(encoding='utf-8'))
    erreurs = controler(donnees)
    if erreurs:
        print('agenda.json : erreurs\n  ' + '\n  '.join(erreurs), file=sys.stderr)
        return 1
    n = ecrire_js(donnees)
    print(f'data/agenda.js : {n} événements')
    if ecrire_page(donnees):
        print('index.html : liste statique mise à jour')
    else:
        print('index.html : repères AGENDA-STATIQUE introuvables, liste statique non mise à jour', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
