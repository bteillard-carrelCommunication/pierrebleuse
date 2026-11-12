#!/usr/bin/env python3
"""Régénère data/cartes.js : les terres émergées en pointillés des deux cartes
de la section « Parcours » (Monde et Europe).

    python3 outils/generer-cartes.py [dossier-de-cache]

1. Source : Natural Earth 110m land (domaine public), en GeoJSON. Elle est
   téléchargée une seule fois puis gardée en cache dans un dossier temporaire
   (par défaut <dossier temporaire du système>/pierrebleuse-cartes, ou le
   dossier passé en argument). Rien n'est téléchargé dans le dépôt.
2. Pour chaque carte, une grille de pastilles en quinconce (même pas en x et
   en y, une ligne sur deux décalée d'un demi-pas) est posée sur le cadre ;
   on ne garde que les pastilles dont le centre tombe sur la terre.
3. data/cartes.js est réécrit : script classique qui définit window.CARTES.

Projection (équirectangulaire), à refaire telle quelle côté site pour placer
une ville :

    x = (lon - lon[0]) / (lon[1] - lon[0]) * l
    y = (lat[1] - lat) / (lat[1] - lat[0]) * h

h respecte les proportions réelles à la latitude moyenne de la carte. La carte
Monde va de 170° O à 190° E : au-delà de 180°, c'est la terre située à
lon - 360 qui est dessinée (la Nouvelle-Zélande reste à droite du Japon).

Avant d'écrire, le script vérifie que chaque ville témoin (TEMOINS) a une
pastille à moins de 1,5 pas ; sinon il s'arrête sans toucher au fichier.

Pour changer la densité, modifier « pas » dans CARTES puis relancer. Les
coordonnées sont arrondies au demi-pixel : avec un pas entier le quinconce est
exact ; avec 6,5 le décalage d'une ligne sur deux vaut 3 au lieu de 3,25, ce
qui ne se voit pas. Repères pour le Monde : pas 6 = 3 285 pastilles mais
Auckland sort de la tolérance, pas 7 = 2 395 pastilles.

Python 3 standard, aucune dépendance.
"""
import json
import math
import sys
import tempfile
import urllib.request
from pathlib import Path

SITE = Path(__file__).resolve().parent.parent
SORTIE = SITE / 'data' / 'cartes.js'

FICHIER = 'ne_110m_land.geojson'
ADRESSES = [
    'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/' + FICHIER,
    'https://cdn.jsdelivr.net/gh/nvkelso/natural-earth-vector@v5.1.2/geojson/' + FICHIER,
]
SOURCE = 'Natural Earth 110m land (domaine public)'

LARGEUR = 1000   # largeur du cadre, en unités du viewBox
RAYON = 0.28     # rayon d'une pastille, en fraction du pas
TOLERANCE = 1.5  # distance maximale ville témoin → pastille, en pas

CARTES = [
    {'id': 'monde', 'lon': (-170, 190), 'lat': (-58, 78), 'pas': 6.5},
    {'id': 'europe', 'lon': (-12, 32), 'lat': (35, 66), 'pas': 16},
]

# (ville, latitude, longitude, cartes concernées)
TOUTES = ('monde', 'europe')
TEMOINS = [
    ('Amsterdam', 52.37, 4.90, TOUTES),
    ('Londres', 51.51, -0.13, TOUTES),
    ('Birmingham', 52.48, -1.90, TOUTES),
    ('Paris', 48.86, 2.35, TOUTES),
    ('Hambourg', 53.55, 9.99, TOUTES),
    ('Francfort', 50.11, 8.68, TOUTES),
    ('Stuttgart', 48.78, 9.18, TOUTES),
    ('Stockholm', 59.33, 18.07, TOUTES),
    ('Helsinki', 60.17, 24.94, TOUTES),
    ('Genève', 46.20, 6.14, TOUTES),
    ('Tokyo', 35.68, 139.69, ('monde',)),
    ('Singapour', 1.35, 103.82, ('monde',)),
    ('São Paulo', -23.55, -46.63, ('monde',)),
    ('Auckland', -36.85, 174.76, ('monde',)),
]


def polygones(geojson):
    """Liste de (boîte englobante, anneaux) ; anneaux = extérieur puis trous."""
    if geojson.get('type') != 'FeatureCollection':
        raise ValueError('ce n’est pas une FeatureCollection GeoJSON')
    terres = []
    for entite in geojson['features']:
        geometrie = entite['geometry']
        if geometrie['type'] == 'Polygon':
            lot = [geometrie['coordinates']]
        elif geometrie['type'] == 'MultiPolygon':
            lot = geometrie['coordinates']
        else:
            continue
        for anneaux in lot:
            xs = [p[0] for p in anneaux[0]]
            ys = [p[1] for p in anneaux[0]]
            terres.append(((min(xs), min(ys), max(xs), max(ys)), anneaux))
    if len(terres) < 100:
        raise ValueError(f'{len(terres)} polygones seulement, fichier incomplet')
    return terres


def charger(cache):
    """Les terres, depuis le cache ou, à défaut, depuis le réseau."""
    fichier = cache / FICHIER
    if fichier.exists():
        try:
            return polygones(json.loads(fichier.read_text(encoding='utf-8')))
        except (ValueError, KeyError, TypeError) as erreur:
            print(f'Cache illisible ({erreur}), nouveau téléchargement.', file=sys.stderr)
    echecs = []
    for adresse in ADRESSES:
        try:
            requete = urllib.request.Request(adresse, headers={'User-Agent': 'generer-cartes.py'})
            with urllib.request.urlopen(requete, timeout=60) as reponse:
                brut = reponse.read()
            terres = polygones(json.loads(brut.decode('utf-8')))
        except (OSError, ValueError, KeyError, TypeError) as erreur:
            echecs.append(f'  {adresse}\n    {erreur}')
            continue
        cache.mkdir(parents=True, exist_ok=True)
        fichier.write_bytes(brut)
        print(f'Source téléchargée : {fichier}')
        return terres
    sys.exit('Impossible de télécharger la source Natural Earth :\n' + '\n'.join(echecs)
             + f'\n{SORTIE.name} n’a pas été modifié. On peut aussi déposer {FICHIER} à la main dans {cache}.')


def sur_terre(lon, lat, terres):
    """Règle pair-impair sur tous les anneaux d'un polygone (extérieur et trous)."""
    lon = (lon + 180) % 360 - 180
    for (ouest, sud, est, nord), anneaux in terres:
        if not (ouest <= lon <= est and sud <= lat <= nord):
            continue
        dedans = False
        for anneau in anneaux:
            x0, y0 = anneau[-1][:2]
            for point in anneau:
                x1, y1 = point[:2]
                if (y0 > lat) != (y1 > lat) and lon < x0 + (lat - y0) * (x1 - x0) / (y1 - y0):
                    dedans = not dedans
                x0, y0 = x1, y1
        if dedans:
            return True
    return False


def demi(valeur):
    """Arrondi au demi-pixel, toujours dans le même sens (la grille reste régulière)."""
    return math.floor(valeur * 2 + 0.5) / 2


def tracer(carte, terres):
    """Complète la carte : l, h, r et la liste des pastilles posées sur la terre."""
    lon0, lon1 = carte['lon']
    lat0, lat1 = carte['lat']
    pas = carte['pas']
    moyenne = math.radians((lat0 + lat1) / 2)
    l = LARGEUR
    h = round(l * (lat1 - lat0) / ((lon1 - lon0) * math.cos(moyenne)))
    r = round(RAYON * pas, 2)
    points = []
    ligne = 0
    while (y := demi(pas / 2 + ligne * pas)) <= h - r:
        colonne = 0
        debut = pas / 2 if ligne % 2 == 0 else pas
        while (x := demi(debut + colonne * pas)) <= l - r:
            lon = lon0 + x / l * (lon1 - lon0)
            lat = lat1 - y / h * (lat1 - lat0)
            if sur_terre(lon, lat, terres):
                points.append((x, y))
            colonne += 1
        ligne += 1
    return dict(carte, l=l, h=h, r=r, points=points)


def projeter(carte, lat, lon):
    """La formule que le site applique pour poser une ville."""
    x = (lon - carte['lon'][0]) / (carte['lon'][1] - carte['lon'][0]) * carte['l']
    y = (carte['lat'][1] - lat) / (carte['lat'][1] - carte['lat'][0]) * carte['h']
    return x, y


def controler(carte):
    """Ville témoin la plus éloignée d'une pastille : (distance en px, nom)."""
    pire = (0.0, '')
    for nom, lat, lon, ou in TEMOINS:
        if carte['id'] not in ou:
            continue
        x, y = projeter(carte, lat, lon)
        distance = min(math.hypot(x - px, y - py) for px, py in carte['points'])
        pire = max(pire, (distance, nom))
    return pire


def ecrire(cartes):
    lignes = ['/* généré par outils/generer-cartes.py : ne pas modifier à la main */',
              'window.CARTES = {',
              f"  source: '{SOURCE}',"]
    for i, carte in enumerate(cartes):
        points = ' '.join(f'{x:g},{y:g}' for x, y in carte['points'])
        lignes.append(
            f"  {carte['id']}: {{ id: '{carte['id']}', "
            f"lon: [{carte['lon'][0]}, {carte['lon'][1]}], "
            f"lat: [{carte['lat'][0]}, {carte['lat'][1]}], "
            f"l: {carte['l']}, h: {carte['h']}, pas: {carte['pas']:g}, r: {carte['r']:g}, "
            f"points: '{points}' }}" + (',' if i < len(cartes) - 1 else ''))
    lignes.append('};')
    SORTIE.write_text('\n'.join(lignes) + '\n', encoding='utf-8')


def main():
    if len(sys.argv) > 1:
        cache = Path(sys.argv[1])
    else:
        cache = Path(tempfile.gettempdir()) / 'pierrebleuse-cartes'
    terres = charger(cache)
    cartes = [tracer(carte, terres) for carte in CARTES]
    refus = False
    for carte in cartes:
        distance, nom = controler(carte)
        limite = TOLERANCE * carte['pas']
        print(f"{carte['id']} : {len(carte['points'])} pastilles, {carte['l']} × {carte['h']}, "
              f"pas {carte['pas']:g}, r {carte['r']:g} ; ville témoin la plus loin d'une pastille : "
              f"{nom} ({distance:.1f} px, limite {limite:g})")
        refus = refus or distance > limite
    if refus:
        sys.exit(f'Une ville témoin est à plus de {TOLERANCE:g} pas de la terre : '
                 f'{SORTIE.name} n’a pas été modifié.')
    ecrire(cartes)
    print(f'{SORTIE} : {SORTIE.stat().st_size} octets')


if __name__ == '__main__':
    main()
