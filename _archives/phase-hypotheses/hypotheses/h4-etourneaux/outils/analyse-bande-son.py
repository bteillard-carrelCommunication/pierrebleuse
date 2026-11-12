#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
analyse-bande-son.py : pré-analyse d'une bande-son pour l'hypothèse 4 « Vol d'étourneaux ».

Lit un média (audio ou vidéo) avec ffmpeg 8 et écrit les caractéristiques musicales que
js/musique.js charge (SPEC.md, section 6). Python 3.9, bibliothèque standard seulement.

Usage :
  python3 outils/analyse-bande-son.py <media> <sortie.json> [--offset S] [--fps 30]
          [--anticipation 0.25] [--anticipation-attaques 0.05]
          [--titre ...] [--interpretes ...] [--licence ...] [--source-url ...] [--trace T.json]
  python3 outils/analyse-bande-son.py --verifier
          (signaux synthétiques : contrôle des calages, voir verifier())

Canaux (30 Hz, entiers 0-255, échelonnés par centiles sur la pièce) :
  energie    sonie momentanée EBU R128 (fenêtre de 400 ms), linéaire en sones entre les bornes
             de echelles.energie (LUFS) : v/255 = (2^(L/10) − 2^(bas/10)) / (2^(haut/10) − 2^(bas/10))
  attaque    force d'attaque : flux spectral rectifié sur 18 bandes (log), pondéré par la sonie
  brillance  centroïde spectral, échelle logarithmique
  graves / mediums / aigus   énergie des bandes < 250 Hz, 250-2000 Hz, > 2000 Hz (dB)
  nouveaute  changement entre les 3 s qui précèdent et les 3 s qui suivent (sonie, centroïde,
             bandes, planéité, densité d'attaques)
Événements :
  ruptures   pics de nouveauté (espacés d'au moins 8 s), effondrements et entrées de tutti
  attaques   pics du flux (espacés d'au moins 0,15 s)

Calage temporel (corrige les défauts relevés sur les prototypes de recherche) :
  - chaque mesure est datée au centre de sa fenêtre : R128 M couvre [h − 0,3 ; h + 0,1] pour
    l'horodatage h de ffmpeg (centre h − 0,1 s, mesuré sur un échelon) ; astats, le centre de la
    trame ; aspectralstats, une fenêtre centrée sur h ;
  - tous les lissages sont à phase nulle (aller-retour) ;
  - puis une anticipation explicite : la valeur écrite au temps t est celle de la musique à
    t + anticipation (l'anacrouse : la nuée se rassemble avant le tutti). Les attaques ont leur
    propre anticipation, courte, qui compense seulement l'affichage : une avance de 0,25 s sur un
    accent net se verrait (ITU-R BT.1359 : image en avance détectable vers 125 ms).

Temps : la trame i correspond au temps média offset + i / fps. Dans le JSON, les temps des
événements sont comptés depuis la trame 0 ; Musique.chargerPiste y ajoute offset, si bien que
corriger offset décale d'un bloc canaux et événements.
"""
import argparse
import bisect
import json
import math
import os
import shutil
import subprocess
import sys
import tempfile
import time

VERSION_OUTIL = 1
SR = 48000                 # tout est rééchantillonné à 48 kHz
HOP = 480                  # trames d'analyse de 10 ms (100 Hz)
DT = HOP / SR
CANAUX = ['energie', 'attaque', 'brillance', 'graves', 'mediums', 'aigus', 'nouveaute']
NB_BANDES = 18             # banc de filtres du flux, de 55 Hz à 14 kHz
F_MIN, F_MAX = 55.0, 14000.0
PLANCHER_LUFS = -70.0
ASTATS = 'astats=metadata=1:reset=1:measure_perchannel=none:measure_overall=RMS_level'


# --------------------------------------------------------------------------- ffmpeg
def centres_bandes():
    r = (F_MAX / F_MIN) ** (1.0 / (NB_BANDES - 1))
    return [F_MIN * r ** k for k in range(NB_BANDES)]


def graphe():
    """Une seule passe : sonie R128 (stéréo), puis en mono, trames de 10 ms : centroïde,
    trois bandes larges, banc de filtres du flux. Chaque branche se termine par anullsink
    sauf la dernière, reliée à la sortie nulle (sinon ffmpeg n'écrit rien)."""
    n = 4 + NB_BANDES
    p = ['[0:a]aresample=%d,asplit=2[st][mo]' % SR,
         '[st]ebur128=metadata=1,ametadata=mode=print:file=r128.txt,anullsink',
         '[mo]aformat=channel_layouts=mono,asetnsamples=n=%d:p=0,asplit=%d%s'
         % (HOP, n, ''.join('[s%d]' % i for i in range(n))),
         '[s0]aspectralstats=win_size=2048:overlap=0.5:measure=centroid+flatness,'
         'ametadata=mode=print:file=spec.txt,anullsink',
         '[s1]lowpass=f=250:poles=2,lowpass=f=250:poles=2,%s,'
         'ametadata=mode=print:file=graves.txt,anullsink' % ASTATS,
         '[s2]highpass=f=250:poles=2,highpass=f=250:poles=2,lowpass=f=2000:poles=2,'
         'lowpass=f=2000:poles=2,%s,ametadata=mode=print:file=mediums.txt,anullsink' % ASTATS,
         '[s3]highpass=f=2000:poles=2,highpass=f=2000:poles=2,%s,'
         'ametadata=mode=print:file=aigus.txt,anullsink' % ASTATS]
    for k, f in enumerate(centres_bandes()):
        fin = '[sortie]' if k == NB_BANDES - 1 else ',anullsink'
        p.append('[s%d]bandpass=f=%.1f:width_type=q:width=2,bandpass=f=%.1f:width_type=q:width=2,'
                 '%s,ametadata=mode=print:file=b%02d.txt%s' % (4 + k, f, f, ASTATS, k, fin))
    return ';'.join(p)


def lancer_ffmpeg(media, dossier):
    cmd = ['ffmpeg', '-hide_banner', '-nostdin', '-loglevel', 'error', '-y', '-i', os.path.abspath(media),
           '-vn', '-filter_complex', graphe(), '-map', '[sortie]', '-f', 'null', '-']
    r = subprocess.run(cmd, cwd=dossier, capture_output=True, text=True)
    if r.returncode != 0:
        raise RuntimeError('ffmpeg a échoué : ' + r.stderr.strip()[-800:])


def duree_media(media):
    try:
        r = subprocess.run(['ffprobe', '-v', 'error', '-select_streams', 'a:0', '-show_entries',
                            'stream=duration:format=duration', '-of', 'json', media],
                           capture_output=True, text=True, check=True)
        d = json.loads(r.stdout)
        for s in d.get('streams', []):
            if s.get('duration'):
                return float(s['duration'])
        return float(d['format']['duration'])
    except Exception:
        return None


def lire(chemin, cle):
    """Liste (horodatage, valeur) d'un fichier ametadata ; None si la valeur manque ou n'est
    pas finie (silence numérique : -inf, nan)."""
    ts, vs = [], []
    with open(chemin) as f:
        for ligne in f:
            if ligne.startswith('frame:'):
                i = ligne.find('pts_time:')
                ts.append(float(ligne[i + 9:].split()[0]))
                vs.append(None)
            elif ts:
                k, _, v = ligne.strip().partition('=')
                if k.endswith(cle):
                    try:
                        x = float(v)
                    except ValueError:
                        continue
                    if math.isfinite(x):
                        vs[-1] = x
    return ts, vs


# --------------------------------------------------------------------------- outils numériques
def lisser(x, dt, tau):
    """Lissage à phase nulle : passe-bas du premier ordre aller puis retour (noyau symétrique)."""
    y = list(x)
    if tau <= 0 or len(y) < 2:
        return y
    a = 1.0 - math.exp(-dt / tau)
    for i in range(1, len(y)):
        y[i] = y[i - 1] + a * (y[i] - y[i - 1])
    for i in range(len(y) - 2, -1, -1):
        y[i] = y[i + 1] + a * (y[i] - y[i + 1])
    return y


def lisser_pondere(x, w, dt, tau):
    num = lisser([a * b for a, b in zip(x, w)], dt, tau)
    den = lisser(w, dt, tau)
    return [n / d if d > 0 else 0.0 for n, d in zip(num, den)]


def echantillonner(ts, vs, grille):
    """Interpolation linéaire de la série (ts croissants) aux temps de la grille ; valeurs de
    bord tenues hors de la série."""
    out, j, n = [], 0, len(ts)
    for g in grille:
        while j < n - 1 and ts[j + 1] <= g:
            j += 1
        if g <= ts[0]:
            out.append(vs[0])
        elif j >= n - 1:
            out.append(vs[-1])
        else:
            t0, t1 = ts[j], ts[j + 1]
            a = (g - t0) / (t1 - t0) if t1 > t0 else 0.0
            out.append(vs[j] + (vs[j + 1] - vs[j]) * a)
    return out


def echantillonner_max(ts, vs, grille, demi):
    """Échantillonnage qui préserve les pics : maximum de la série sur [g − demi ; g + demi]."""
    out = []
    for g in grille:
        i0 = bisect.bisect_left(ts, g - demi)
        i1 = bisect.bisect_right(ts, g + demi)
        out.append(max(vs[i0:i1]) if i1 > i0 else echantillonner(ts, vs, [g])[0])
    return out


def centile(x, p):
    s = sorted(x)
    if not s:
        return 0.0
    k = (len(s) - 1) * p
    i = int(k)
    return s[i] + (s[min(i + 1, len(s) - 1)] - s[i]) * (k - i)


def en_octets(x, bas, haut):
    e = (haut - bas) or 1e-9
    return [max(0, min(255, int(round(255.0 * (v - bas) / e)))) for v in x]


def db(ms, plancher=1e-14):
    return 10.0 * math.log10(ms + plancher)


def remplir(vs, defaut):
    """Remplace les valeurs manquantes par la précédente (ou defaut au début)."""
    out, prec = [], defaut
    for v in vs:
        prec = prec if v is None else v
        out.append(prec)
    return out


def parabole(y0, y1, y2):
    """Décalage sous-trame du sommet d'une parabole passant par trois points (-1, 0, 1)."""
    d = y0 - 2.0 * y1 + y2
    return 0.0 if d == 0 else max(-0.5, min(0.5, 0.5 * (y0 - y2) / d))


# --------------------------------------------------------------------------- analyse
def analyser(media, fps=30.0, anticipation=0.25, anticipation_attaques=0.05, journal=print):
    t0 = time.time()
    tmp = tempfile.mkdtemp(prefix='h4-analyse-')
    try:
        lancer_ffmpeg(media, tmp)
        journal('ffmpeg : %.1f s' % (time.time() - t0))
        r128 = lire(os.path.join(tmp, 'r128.txt'), 'r128.M')
        spec_c = lire(os.path.join(tmp, 'spec.txt'), '.centroid')
        spec_f = lire(os.path.join(tmp, 'spec.txt'), '.flatness')
        larges = {k: lire(os.path.join(tmp, k + '.txt'), 'RMS_level') for k in ('graves', 'mediums', 'aigus')}
        bandes = [lire(os.path.join(tmp, 'b%02d.txt' % k), 'RMS_level') for k in range(NB_BANDES)]
    finally:
        shutil.rmtree(tmp, ignore_errors=True)

    duree = duree_media(media) or (larges['graves'][0][-1] + DT)
    n = int(math.floor(duree * fps + 1e-9)) + 1
    grille = [i / fps for i in range(n)]
    tr = {}                                     # séries intermédiaires (option --trace)

    # Trames de 10 ms : centre = horodatage + DT/2.
    t10 = [t + DT / 2 for t in larges['graves'][0]]

    def ms_de(vs):
        return [10.0 ** (v / 10.0) if v is not None else 0.0 for v in vs]

    # --- energie : sonie momentanée R128, datée au centre de sa fenêtre, lissée en énergie, puis
    # portée sur l'échelle des sones (Stevens : deux fois plus fort tous les 10 LU), linéaire
    # entre les bornes de la pièce. En LU, le début calme valait 0,7 contre 1 à l'apogée.
    tM = [t - 0.1 for t in r128[0]]
    M = [max(PLANCHER_LUFS, v) if v is not None else PLANCHER_LUFS for v in r128[1]]
    eM = lisser([10.0 ** (v / 10.0) for v in M], 0.1, 0.12)
    Ml = [max(PLANCHER_LUFS, db(e)) for e in eM]
    energie_lu = echantillonner(tM, Ml, [g + anticipation for g in grille])
    haut = centile(energie_lu, 0.995)
    bas = max(centile(energie_lu, 0.02), haut - 45.0)
    echelles = {'energie': [round(bas, 2), round(haut, 2), 'LUFS, en sones']}  # bornes en LU (nouveauté, poids)
    sones = [2.0 ** ((v - haut) / 10.0) for v in energie_lu]
    q = {'energie': en_octets(sones, 2.0 ** ((bas - haut) / 10.0), 1.0)}
    tr['energie_lufs'] = (tM, Ml)

    # --- graves / mediums / aigus : moyenne quadratique lissée (phase nulle), puis dB.
    ms_tot = None
    ms_larges = {}
    for k in ('graves', 'mediums', 'aigus'):
        ms = lisser(ms_de(larges[k][1]), DT, 0.08)
        ms_larges[k] = ms
        ms_tot = ms if ms_tot is None else [a + b for a, b in zip(ms_tot, ms)]
        v = echantillonner(t10, [db(x) for x in ms], [g + anticipation for g in grille])
        haut = centile(v, 0.995)
        bas = max(centile(v, 0.02), haut - 50.0)
        echelles[k] = [round(bas, 2), round(haut, 2), 'dB']
        q[k] = en_octets(v, bas, haut)
        tr[k + '_db'] = (t10, [db(x) for x in ms])

    # --- brillance : log2 du centroïde, lissage pondéré par l'amplitude (le silence ne compte
    # presque pas), aspectralstats centré sur son horodatage.
    tC = spec_c[0]
    amp = [math.sqrt(x) for x in echantillonner(t10, ms_tot, tC)]
    lc = remplir([math.log2(v) if v and v > 1 else None for v in spec_c[1]], math.log2(1000.0))
    lcl = lisser_pondere(lc, amp, tC[1] - tC[0] if len(tC) > 1 else 0.02, 0.3)
    v = echantillonner(tC, lcl, [g + anticipation for g in grille])
    ponderes = [x for x, a in zip(echantillonner(tC, lcl, grille), echantillonner(tC, amp, grille))
                if a > 0.05 * max(amp)]
    bas, haut = centile(ponderes or v, 0.02), centile(ponderes or v, 0.995)
    echelles['brillance'] = [round(2 ** bas), round(2 ** haut), 'Hz']
    q['brillance'] = en_octets(v, bas, haut)
    planeite = lisser_pondere(remplir(spec_f[1], 0.0), amp, tC[1] - tC[0] if len(tC) > 1 else 0.02, 0.3)
    tr['centroide_log2'] = (tC, lcl)

    # --- attaque : flux spectral rectifié sur le banc de filtres (dB, plancher à −55 dB sous le
    # niveau fort de chaque bande). Passage en dB AVANT le lissage : un lissage linéaire étale
    # l'énergie avant l'attaque et le logarithme en fait une rampe (pic avancé de 29 ms, mesuré).
    # La différence entre deux trames est datée à leur frontière.
    L = []
    for k, f in enumerate(centres_bandes()):
        ms = ms_de(bandes[k][1])
        plancher = max(centile(ms, 0.995), 1e-12) * 10.0 ** -5.5
        L.append(lisser([db(x, plancher) for x in ms], DT, max(0.006, 0.8 / f)))
    m = min(len(b) for b in L)
    t_flux = [larges['graves'][0][i] for i in range(m)]        # frontière = horodatage de la trame
    flux = [0.0] * m
    for b in L:
        for i in range(1, m):
            d = b[i] - b[i - 1]
            if d > 0:
                flux[i] += d
    flux = [x / NB_BANDES for x in flux]
    flux = lisser(flux, DT, 0.01)
    poids = [0.3 + 0.7 * min(1.0, max(0.0, (x - echelles['energie'][0])
                                       / ((echelles['energie'][1] - echelles['energie'][0]) or 1e-9)))
             for x in echantillonner(tM, Ml, t_flux)]
    odf = [a * b for a, b in zip(flux, poids)]
    v = echantillonner_max(t_flux, odf, [g + anticipation_attaques for g in grille], 0.5 / fps)
    bas, haut = centile(v, 0.5), centile(v, 0.995)
    echelles['attaque'] = [round(bas, 4), round(haut, 4), 'dB/10 ms']
    q['attaque'] = en_octets(v, bas, haut)
    tr['flux'] = (t_flux, odf)

    # --- nouveaute : différence entre les moyennes des 3 s avant et des 3 s après (pondération
    # gaussienne large), sur les caractéristiques ramenées à l'échelle de leur canal (0-1,
    # bornées) : le bruit de fond des passages quasi silencieux ne compte plus.
    pas = 0.1
    g10 = [i * pas for i in range(int(duree / pas) + 1)]
    densite = lisser(odf, DT, 0.5)

    def borne(x, bas, haut):
        e = (haut - bas) or 1e-9
        return [min(1.0, max(0.0, (a - bas) / e)) for a in x]

    lb, lh = math.log2(echelles['brillance'][0]), math.log2(echelles['brillance'][1])
    dens = echantillonner(t_flux, densite, g10)
    Z = [
        (1.0, borne(echantillonner(tM, Ml, g10), *echelles['energie'][:2])),
        (0.6, borne(echantillonner(tC, lcl, g10), lb, lh)),
        (0.4, borne(echantillonner(t10, [db(x) for x in ms_larges['graves']], g10), *echelles['graves'][:2])),
        (0.4, borne(echantillonner(t10, [db(x) for x in ms_larges['mediums']], g10), *echelles['mediums'][:2])),
        (0.4, borne(echantillonner(t10, [db(x) for x in ms_larges['aigus']], g10), *echelles['aigus'][:2])),
        (0.2, borne(echantillonner(tC, planeite, g10), centile(planeite, 0.05), centile(planeite, 0.99))),
        (0.3, borne(dens, centile(dens, 0.05), centile(dens, 0.99))),
    ]
    W = int(round(3.0 / pas))
    poidsW = [math.exp(-0.5 * (j / float(W)) ** 2) for j in range(1, W + 1)]
    nov = []
    somme_c = sum(c for c, _ in Z)
    nz = len(g10)
    for k in range(nz):
        s = 0.0
        for c, z in Z:
            av = ap = pa = pb = 0.0
            for j, w in enumerate(poidsW, 1):
                if k - j >= 0:
                    av += w * z[k - j]; pa += w
                if k + j < nz:
                    ap += w * z[k + j]; pb += w
            if pa > 0 and pb > 0:
                s += c * (ap / pb - av / pa) ** 2
        # aux bords, une des fenêtres est tronquée : atténuation sur 3 s
        nov.append(math.sqrt(s / somme_c) * min(1.0, k / float(W), (nz - 1 - k) / float(W)))
    nov = lisser(nov, pas, 0.3)
    v = echantillonner(g10, nov, [g + anticipation for g in grille])
    bas, haut = centile(v, 0.25), centile(v, 0.995)
    echelles['nouveaute'] = [round(bas, 3), round(haut, 3), 'σ']
    q['nouveaute'] = en_octets(v, bas, haut)
    tr['nouveaute'] = (g10, nov)

    # --- ruptures : pics de nouveauté (≥ 8 s d'écart), effondrements et entrées de tutti ; aucune
    # dans les 2 premières et les 4 dernières secondes (pas la place d'une transition).
    ruptures = [r for r in detecter_ruptures(g10, nov, echantillonner(tM, Ml, g10), pas)
                if 2.0 <= r['t'] <= duree - 4.0]
    for r in ruptures:
        r['t'] = round(max(0.0, r['t'] - anticipation), 3)
    # --- attaques : pics du flux pondéré.
    attaques = detecter_attaques(t_flux, odf)
    for a in attaques:
        a['t'] = round(max(0.0, a['t'] - anticipation_attaques), 3)

    journal('analyse : %.1f s au total' % (time.time() - t0))
    return {
        'duree': duree, 'trames': n, 'q': q, 'echelles': echelles,
        'ruptures': ruptures, 'attaques': attaques, 'trace': tr,
    }


def detecter_ruptures(g, nov, lufs, pas):
    n = len(g)
    lo, hi = centile(nov, 0.5), max(nov) or 1e-9
    seuil = max(centile(nov, 0.8), lo + 0.25 * (hi - lo))
    demi = int(round(3.0 / pas))
    cands = []
    for k in range(n):
        if nov[k] >= seuil and nov[k] == max(nov[max(0, k - demi):k + demi + 1]):
            cands.append({'t': g[k], 'force': (nov[k] - lo) / ((hi - lo) or 1e-9), 'type': 'nouveaute'})
    cands.sort(key=lambda c: -c['force'])
    retenues = []
    for c in cands:
        if all(abs(c['t'] - r['t']) >= 8.0 for r in retenues):
            retenues.append(c)
    # Sonie : écart entre la moyenne avant et après t, à deux échelles (un effondrement
    # d'orchestre prend une à trois secondes) ; on garde l'écart le plus fort.
    p50, p60 = centile(lufs, 0.5), centile(lufs, 0.6)
    d = [None] * n
    for k in range(n):
        for a_s, b_s in ((0.3, 1.5), (0.6, 3.0)):
            a, b = int(round(a_s / pas)), int(round(b_s / pas))
            if k - b < 0 or k + b >= n:
                continue
            avant = sum(lufs[k - b:k - a + 1]) / (b - a + 1)
            apres = sum(lufs[k + a:k + b + 1]) / (b - a + 1)
            if d[k] is None or abs(apres - avant) > abs(d[k][0]):
                d[k] = (apres - avant, avant, apres)
    sonie = []
    demi = int(round(2.0 / pas))
    for k in range(n):
        if d[k] is None:
            continue
        ecart, avant, apres = d[k]
        # extremum local parmi les écarts de même signe (une remontée voisine ne masque pas la chute)
        voisins = [abs(x[0]) for x in d[max(0, k - demi):k + demi + 1] if x is not None and x[0] * ecart > 0]
        if not voisins or abs(ecart) < max(voisins):
            continue
        if ecart <= -8.0 and avant >= p50:
            sonie.append({'t': g[k], 'force': min(1.0, -ecart / 20.0), 'type': 'effondrement'})
        elif ecart >= 8.0 and apres >= p60:
            sonie.append({'t': g[k], 'force': min(1.0, ecart / 20.0), 'type': 'tutti'})
    # Un effondrement ou un tutti à moins de 5 s d'un pic de nouveauté le remplace (repère
    # musical plus net) ; sinon il s'ajoute s'il reste à 6 s des autres. Les plus forts d'abord.
    for s in sorted(sonie, key=lambda e: -e['force']):
        proche = [r for r in retenues if r['type'] == 'nouveaute' and abs(r['t'] - s['t']) < 5.0]
        if proche:
            r = min(proche, key=lambda e: abs(e['t'] - s['t']))
            r['t'], r['type'], r['force'] = s['t'], s['type'], max(r['force'], s['force'])
        elif all(abs(s['t'] - r['t']) >= 6.0 for r in retenues):
            retenues.append(s)
    retenues.sort(key=lambda r: r['t'])
    return [{'t': r['t'], 'force': round(min(1.0, max(0.0, r['force'])), 2), 'type': r['type']} for r in retenues]


def detecter_attaques(t, odf):
    """Pic retenu s'il est le maximum sur ± 0,15 s et dépasse la moyenne locale (± 0,5 s) d'un
    écart fixé par la pièce. Temps affiné par interpolation parabolique."""
    n = len(odf)
    cum = [0.0]
    for x in odf:
        cum.append(cum[-1] + x)
    w, demi = int(round(0.5 / DT)), int(round(0.15 / DT))
    moy = [(cum[min(n, i + w + 1)] - cum[max(0, i - w)]) / (min(n, i + w + 1) - max(0, i - w)) for i in range(n)]
    delta = 2.25 * (centile(odf, 0.75) - centile(odf, 0.5))     # ≈ 1 attaque/s en moyenne ici
    plancher = centile(odf, 0.7)
    pics = []
    for i in range(1, n - 1):
        x = odf[i]
        if x < plancher or x < moy[i] + delta:
            continue
        if x != max(odf[max(0, i - demi):i + demi + 1]):
            continue
        pics.append(i)
    if not pics:
        return []
    # force : intensité du pic (flux déjà pondéré par la sonie) entre le plancher et le 98e centile
    haut = centile([odf[i] for i in pics], 0.98)
    out, dernier = [], -1e9
    for i in pics:
        ti = t[i] + parabole(odf[i - 1], odf[i], odf[i + 1]) * DT
        if ti - dernier < 0.15:
            continue
        dernier = ti
        f = (odf[i] - plancher) / ((haut - plancher) or 1e-9)
        out.append({'t': ti, 'force': round(min(1.0, max(0.05, f)), 2)})
    return out


# --------------------------------------------------------------------------- sortie
def document(res, media, args):
    fichier = os.path.basename(media)
    evts = {'ruptures': res['ruptures'],
            'attaques': [{'t': round(a['t'], 3), 'force': a['force']} for a in res['attaques']]}
    return {
        'version': 1,
        'source': args.source_url or fichier,
        'titre': args.titre or fichier,
        'interpretes': args.interpretes or '',
        'licence': args.licence or '',
        'fichier': fichier,
        'offset': args.offset,
        'fps': args.fps,
        'duree': round(res['duree'], 3),
        'trames': res['trames'],
        'anticipation': args.anticipation,
        'anticipationAttaques': args.anticipation_attaques,
        'canaux': CANAUX,
        'echelles': res['echelles'],
        'donnees': {k: res['q'][k] for k in CANAUX},
        'evenements': evts,
        'outil': 'analyse-bande-son.py %d' % VERSION_OUTIL,
    }


def ecrire_trace(res, chemin):
    """Séries intermédiaires (flottants, temps non anticipés) pour tracer et vérifier."""
    out = {}
    for k, (ts, vs) in res['trace'].items():
        out[k] = {'t': [round(x, 3) for x in ts], 'v': [round(x, 4) for x in vs]}
    with open(chemin, 'w') as f:
        json.dump(out, f, separators=(',', ':'))


# --------------------------------------------------------------------------- vérification
def verifier():
    """Signal synthétique (44,1 kHz, comme le fichier de test, puis encodé en AAC) dont les
    instants sont connus : échelon de sonie à 2,0 s (montée) et 8,0 s (descente), clics à 10,0 ;
    10,5 ; 11,2 ; 12,0 s, saut de fréquence 300 → 3000 Hz à 17,0 s à niveau égal. Analyse sans
    anticipation, puis avec, et compare."""
    clic = 'if(gte(t,%.1f),exp(-(t-%.1f)*400),0)'
    # Bouffée de 3000 Hz (16,5 → 17,5 s, fondus de 20 ms) sur un 300 Hz continu : le log du
    # centroïde réagit plus vite à l'éclaircissement qu'à l'assombrissement, mais le milieu des
    # deux franchissements est sans biais (centre attendu : 17,0 s).
    bouffee = 'clip((t-16.49)/0.02,0,1)*clip((17.51-t)/0.02,0,1)'
    expr = ('if(between(t,2,8),0.1*(sin(2*PI*440*t)+0.5*sin(2*PI*880*t)+0.25*sin(2*PI*1320*t)),0)'
            '+0.6*(' + '+'.join(clic % (c, c) for c in (10.0, 10.5, 11.2, 12.0)) + ')*sin(2*PI*2500*t)'
            '+if(between(t,14,20),0.1*(sin(2*PI*300*t)+sin(2*PI*3000*t)*%s),0)' % bouffee)
    tmp = tempfile.mkdtemp(prefix='h4-verif-')
    ok = True
    try:
        wav = os.path.join(tmp, 'test.wav')
        m4a = os.path.join(tmp, 'test.m4a')
        subprocess.run(['ffmpeg', '-hide_banner', '-nostdin', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i',
                        "aevalsrc='%s|%s':s=44100:d=21" % (expr, expr), '-c:a', 'pcm_s16le', wav], check=True)
        subprocess.run(['ffmpeg', '-hide_banner', '-nostdin', '-loglevel', 'error', '-y', '-i', wav,
                        '-c:a', 'aac', '-b:a', '160k', m4a], check=True)
        for nom, src in (('wav', wav), ('aac', m4a)):
            for a, aa in ((0.0, 0.0), (0.25, 0.05)):
                res = analyser(src, 30.0, a, aa, journal=lambda *_: None)
                fps = 30.0
                bas, haut, _ = res['echelles']['energie']
                nb = 2.0 ** ((bas - haut) / 10.0)

                def lufs(i):        # canal linéaire en sones entre bas et haut (LUFS)
                    return haut + 10.0 * math.log2(nb + res['q']['energie'][i] / 255.0 * (1.0 - nb))

                def croisement(canal, t_lo, t_hi, conv):
                    i0, i1 = int(t_lo * fps), int(t_hi * fps)
                    vals = [conv(i) for i in range(i0, i1 + 1)]
                    v0, v1 = vals[0], vals[-1]
                    mi = 0.5 * (v0 + v1)
                    for j in range(1, len(vals)):
                        if (vals[j - 1] - mi) * (vals[j] - mi) <= 0 and vals[j] != vals[j - 1]:
                            return (i0 + j - 1 + (mi - vals[j - 1]) / (vals[j] - vals[j - 1])) / fps
                    return float('nan')

                energie = lambda i: 10.0 ** (lufs(i) / 10.0)
                brill = lambda i: res['q']['brillance'][i]

                def milieu_bouffee(ts, vs):
                    """Milieu des deux franchissements de mi-hauteur de la bouffée."""
                    v0, vh = echantillonner(ts, vs, [15.5, 17.0])
                    mi = 0.5 * (v0 + vh)
                    x = []
                    for j in range(1, len(ts)):
                        if 15.5 < ts[j] < 18.5 and (vs[j - 1] - mi) * (vs[j] - mi) < 0:
                            x.append(ts[j - 1] + (mi - vs[j - 1]) / (vs[j] - vs[j - 1]) * (ts[j] - ts[j - 1]))
                    return 0.5 * (x[0] + x[-1]) if len(x) >= 2 else float('nan')

                tC, lc = res['trace']['centroide_log2']
                g = [i / fps for i in range(len(res['q']['brillance']))]
                mesures = [
                    ('energie, montée à 2,0 s', 2.0 - a, croisement('energie', 1.0, 3.0, energie)),
                    ('energie, descente à 8,0 s', 8.0 - a, croisement('energie', 7.0, 9.0, energie)),
                    ('brillance, bouffée centrée sur 17,0 s', 17.0 - a,
                     milieu_bouffee(g, [float(x) for x in res['q']['brillance']])),
                    ('  (série avant anticipation)', 17.0, milieu_bouffee(tC, lc)),
                ]
                for tc in (10.0, 10.5, 11.2, 12.0):
                    proche = min(res['attaques'], key=lambda e: abs(e['t'] - (tc - aa)), default=None)
                    mesures.append(('attaque, clic à %.1f s' % tc, tc - aa, proche['t'] if proche else float('nan')))
                print('\n[%s] anticipation %.2f s, attaques %.2f s' % (nom, a, aa))
                for lib, attendu, obtenu in mesures:
                    err = (obtenu - attendu) * 1000.0
                    bon = abs(err) <= 20.0
                    ok = ok and bon
                    print('  %-36s attendu %7.3f  obtenu %7.3f  écart %+6.1f ms  %s'
                          % (lib, attendu, obtenu, err, 'ok' if bon else 'ÉCHEC'))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)
    print('\nVérification : %s' % ('réussie (écarts ≤ 20 ms)' if ok else 'ÉCHEC'))
    return 0 if ok else 1


# --------------------------------------------------------------------------- ligne de commande
def main(argv=None):
    ap = argparse.ArgumentParser(description='Pré-analyse d\'une bande-son (H4) : ffmpeg → JSON.')
    ap.add_argument('media', nargs='?')
    ap.add_argument('sortie', nargs='?')
    ap.add_argument('--offset', type=float, default=0.0, help='temps média de la trame 0 (s)')
    ap.add_argument('--fps', type=float, default=30.0)
    ap.add_argument('--anticipation', type=float, default=0.25, help='avance des canaux et des ruptures (s)')
    ap.add_argument('--anticipation-attaques', type=float, default=0.05, help='avance des attaques (s)')
    ap.add_argument('--titre')
    ap.add_argument('--interpretes')
    ap.add_argument('--licence')
    ap.add_argument('--source-url')
    ap.add_argument('--trace', help='écrit aussi les séries intermédiaires (flottants)')
    ap.add_argument('--verifier', action='store_true', help='contrôle des calages sur signaux synthétiques')
    args = ap.parse_args(argv)
    if args.verifier:
        return verifier()
    if not args.media or not args.sortie:
        ap.error('media et sortie sont requis')
    if not shutil.which('ffmpeg'):
        ap.error('ffmpeg introuvable dans le PATH')
    res = analyser(args.media, args.fps, args.anticipation, args.anticipation_attaques,
                   journal=lambda s: print(s, file=sys.stderr))
    doc = document(res, args.media, args)
    with open(args.sortie, 'w', encoding='utf-8') as f:
        json.dump(doc, f, ensure_ascii=False, separators=(',', ':'))
    if args.trace:
        ecrire_trace(res, args.trace)
    r = doc['evenements']['ruptures']
    print('%s : %d trames à %g Hz (%.1f s), %d ruptures, %d attaques, %d octets'
          % (args.sortie, doc['trames'], doc['fps'], doc['duree'], len(r),
             len(doc['evenements']['attaques']), os.path.getsize(args.sortie)), file=sys.stderr)
    for e in r:
        print('  rupture %6.2f s (%d:%05.2f)  force %.2f  %s'
              % (e['t'], int(e['t'] // 60), e['t'] % 60, e['force'], e['type']), file=sys.stderr)
    return 0


if __name__ == '__main__':
    sys.exit(main())
