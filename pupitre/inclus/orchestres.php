<?php
/**
 * Orchestres : la collection, et la carte du monde qu'elle alimente.
 *
 * Un orchestre est une fiche de l'annuaire (catégories « orchestre » et « ensemble ») : nom
 * officiel, autres noms (alias, variantes), ville de référence, pays, coordonnées, site, date de
 * première collaboration, notes. Il n'y a qu'un référentiel : pas de table d'orchestres à part.
 *
 * La carte du monde est une liste administrée : une ligne associe un orchestre à une ville et à
 * ses coordonnées (table parcours_orchestres). On place un orchestre dans sa ville, pas dans celle
 * d'un concert donné ailleurs ; on peut ajouter autant de lignes qu'on veut, et un même orchestre
 * peut en avoir plusieurs. Une ligne a deux statuts indépendants : affichée ou non sur la carte,
 * position vérifiée ou à confirmer. Entrer dans la base n'est pas paraître sur la carte.
 *
 * Reconnaître un orchestre (orchestre_chercher) se fait d'abord sans IA : nom exact, nom
 * normalisé, alias, variante linguistique (« Orchestre de Paris » / « Paris Orchestra »), puis
 * nom approchant. L'IA ne sert qu'à lever une ambiguïté, à la demande.
 */

defined('PUPITRE') or exit;

const CATEGORIES_ORCHESTRE = ['orchestre', 'ensemble'];

/** Mots qui disent la même chose d'une langue à l'autre : ramenés à une racine pour comparer deux noms. */
const RACINES_ORCHESTRE = [
    'orch' => ['orchestre', 'orchestra', 'orchester', 'orquesta', 'orquestra', 'orkester', 'orkest', 'orchestr', 'orkiestra'],
    'symph' => ['symphonique', 'symphony', 'symphonic', 'sinfonie', 'symphonie', 'sinfonica', 'sinfonico', 'symfoni', 'symfonie', 'sinfonieorchester', 'symphonieorchester', 'symfoniorkester', 'sinfoniker', 'symphoniker'],
    'phil' => ['philharmonique', 'philharmonic', 'philharmonia', 'philharmonie', 'filarmonica', 'filharmoni', 'philharmoniker', 'filarmonika', 'filharmonia'],
    'nat' => ['national', 'nationale', 'nacional', 'nazionale', 'nationales', 'nacionales'],
    'radio' => ['radio', 'rundfunk', 'radiophonique', 'rundfunkorchester'],
    'royal' => ['royal', 'royale', 'real', 'kungliga', 'koninklijk', 'reale'],
    'chambre' => ['chambre', 'chamber', 'kammer', 'camera', 'camara', 'kammerorchester'],
    'ens' => ['ensemble'],
    'choeur' => ['choeur', 'choir', 'chor', 'coro', 'chorus'],
    'opera' => ['opera', 'oper', 'opéra'],
];

/** Mots vides, ignorés dans la comparaison de deux noms. */
const MOTS_VIDES_ORCHESTRE = ['de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'the', 'of', 'and', 'et', 'y', 'e', 'do', 'da', 'di', 'del', 'della', 'der', 'des', 'van', 'von', 'for', 'a', 'en', 'in'];

/** Villes dont le nom change avec la langue : pour reconnaître « Orchestre de Londres » et « London Orchestra ». */
const VILLES_VARIANTES = [
    'londres' => 'london', 'vienne' => 'wien', 'vienna' => 'wien', 'munich' => 'munchen', 'cologne' => 'koln', 'francfort' => 'frankfurt', 'hambourg' => 'hamburg',
    'geneve' => 'geneva', 'pekin' => 'beijing', 'singapour' => 'singapore', 'copenhague' => 'copenhagen', 'kobenhavn' => 'copenhagen', 'goteborg' => 'gothenburg',
    'lisbonne' => 'lisboa', 'lisbon' => 'lisboa', 'bucarest' => 'bucharest', 'bucuresti' => 'bucharest', 'varsovie' => 'warsaw', 'moscou' => 'moscow', 'rome' => 'roma',
    'bale' => 'basel', 'anvers' => 'antwerp', 'bruxelles' => 'brussels', 'athenes' => 'athens', 'seoul' => 'seoul', 'la haye' => 'hague',
];

/** Signature d'un nom d'orchestre : ses mots utiles, ramenés à leur racine, triés. Deux noms de même signature désignent le même orchestre dans deux langues. */
function orchestre_signature(string $nom): string
{
    $mots = [];
    foreach (explode(' ', normaliser($nom)) as $m) {
        if ($m === '' || in_array($m, MOTS_VIDES_ORCHESTRE, true)) continue;
        foreach (RACINES_ORCHESTRE as $racine => $formes) {
            if (in_array($m, $formes, true)) { $m = $racine; break; }
        }
        $mots[] = VILLES_VARIANTES[$m] ?? $m;
    }
    $mots = array_values(array_unique($mots));
    sort($mots);
    return implode(' ', $mots);
}

/**
 * Cherche un orchestre par son nom. Rend ['id' => fiche ou null, 'niveau' => exact | alias |
 * variante | proche | '', 'candidats' => [[id, nom, niveau], …]]. « exact », « alias » et
 * « variante » désignent une fiche sans ambiguïté ; « proche » n'est qu'une proposition.
 */
function orchestre_chercher(string $nom, string $autre = ''): array
{
    $noms = array_values(array_filter([trim($nom), trim($autre)], 'strlen'));
    $rien = ['id' => null, 'niveau' => '', 'candidats' => []];
    if (!$noms) return $rien;
    $fiches = bd_lignes("SELECT id, nom, autres_noms FROM annuaire WHERE categorie IN ('orchestre', 'ensemble') ORDER BY id");
    $candidats = [];
    foreach ($fiches as $f) {
        $principal = normaliser($f['nom']);
        $alias = array_values(array_filter(array_map('normaliser', lignes($f['autres_noms'] ?? '')), 'strlen'));
        $niveau = '';
        foreach ($noms as $n) {
            $x = normaliser($n);
            if ($x === '') continue;
            if ($x === $principal) { $niveau = 'exact'; break; }
            if (in_array($x, $alias, true)) { $niveau = $niveau === '' || $niveau !== 'exact' ? 'alias' : $niveau; continue; }
            if ($niveau === '' || $niveau === 'proche') {
                $signature = orchestre_signature($n);
                if ($signature !== '' && str_contains($signature, ' ') && in_array($signature, array_map('orchestre_signature', array_merge([$f['nom']], lignes($f['autres_noms'] ?? ''))), true)) { $niveau = 'variante'; continue; }
            }
            if ($niveau === '' && strlen($x) >= 8 && strlen($principal) >= 8) {
                similar_text($x, $principal, $pourcent);
                if (levenshtein(substr($x, 0, 250), substr($principal, 0, 250)) <= 2 || $pourcent >= 88) $niveau = 'proche';
            }
            if ($niveau === '') {
                // nom abrégé (« London Philharmonic ») : tous ses mots utiles sont dans un nom de la fiche, ou l'inverse
                $a = array_values(array_diff(explode(' ', orchestre_signature($n)), ['orch', '']));
                foreach (array_merge([$f['nom']], lignes($f['autres_noms'] ?? '')) as $nom_fiche) {
                    $b = array_values(array_diff(explode(' ', orchestre_signature($nom_fiche)), ['orch', '']));
                    if (min(count($a), count($b)) >= 2 && (!array_diff($a, $b) || !array_diff($b, $a))) { $niveau = 'proche'; break; }
                }
            }
        }
        if ($niveau !== '') $candidats[] = ['id' => (int) $f['id'], 'nom' => $f['nom'], 'niveau' => $niveau];
    }
    if (!$candidats) return $rien;
    $rangs = ['exact' => 0, 'alias' => 1, 'variante' => 2, 'proche' => 3];
    usort($candidats, fn($a, $b) => [$rangs[$a['niveau']], $a['id']] <=> [$rangs[$b['niveau']], $b['id']]);
    $surs = array_values(array_filter($candidats, fn($c) => $c['niveau'] !== 'proche'));
    // une seule fiche désignée sans ambiguïté : c'est elle ; sinon on propose, on ne tranche pas
    if ($surs && (count($surs) === 1 || $surs[0]['niveau'] !== $surs[1]['niveau'])) return ['id' => $surs[0]['id'], 'niveau' => $surs[0]['niveau'], 'candidats' => $candidats];
    return ['id' => null, 'niveau' => $surs ? 'ambigu' : 'proche', 'candidats' => $candidats];
}

// ---------- Orchestres d'un concert ----------

/** Orchestres associés à un concert : fiches de l'annuaire, dans l'ordre. */
function concert_orchestres(int $concert_id): array
{
    return bd_lignes('SELECT a.* FROM concerts_orchestres co JOIN annuaire a ON a.id = co.fiche_id WHERE co.concert_id = ? ORDER BY co.ordre, a.nom', [$concert_id]);
}

/** Pose la liste des orchestres d'un concert (identifiants de fiches, dans l'ordre). Rend vrai si elle a changé. */
function concert_orchestres_poser(int $concert_id, array $fiches, string $titre = ''): bool
{
    $fiches = array_values(array_unique(array_filter(array_map('intval', $fiches))));
    if ($fiches) {
        $marques = implode(',', array_fill(0, count($fiches), '?'));
        $connues = array_map('intval', bd_colonne("SELECT id FROM annuaire WHERE id IN ($marques) AND categorie IN ('orchestre', 'ensemble')", $fiches));
        $fiches = array_values(array_filter($fiches, fn($f) => in_array($f, $connues, true)));
    }
    $avant = array_map('intval', bd_colonne('SELECT fiche_id FROM concerts_orchestres WHERE concert_id = ? ORDER BY ordre, fiche_id', [$concert_id]));
    if ($avant === $fiches) return false;
    bd_requete('DELETE FROM concerts_orchestres WHERE concert_id = ?', [$concert_id]);
    foreach ($fiches as $i => $f) bd_requete('INSERT INTO concerts_orchestres (concert_id, fiche_id, ordre) VALUES (?, ?, ?)', [$concert_id, $f, $i]);
    $noms = fn(array $ids) => $ids ? bd_colonne('SELECT nom FROM annuaire WHERE id IN (' . implode(',', $ids) . ') ORDER BY nom') : [];
    journaliser('modification', 'concert', $concert_id, ($titre !== '' ? $titre . ', ' : '') . 'orchestres associés', ['orchestres' => $noms($avant)], ['orchestres' => $noms($fiches)]);
    return true;
}

/**
 * Après l'enregistrement d'un concert : son ensemble, s'il est reconnu sans ambiguïté, est associé
 * au concert. Rend ce que orchestre_chercher() a trouvé, pour que l'écran dise la suite.
 */
function concert_orchestre_reconnaitre(int $concert_id): array
{
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$concert_id]);
    if (!$c || $c['type'] === 'disque' || trim($c['ensemble_fr']) === '') return ['id' => null, 'niveau' => 'sans', 'candidats' => []];
    $r = orchestre_chercher($c['ensemble_fr'], (string) traduction_publiee('concerts', $concert_id, 'ensemble', 'en'));
    if ($r['id']) {
        $deja = array_map('intval', bd_colonne('SELECT fiche_id FROM concerts_orchestres WHERE concert_id = ? ORDER BY ordre', [$concert_id]));
        if (!in_array($r['id'], $deja, true)) concert_orchestres_poser($concert_id, array_merge([$r['id']], $deja), $c['titre_fr']);
        if ((int) $c['ensemble_id'] !== $r['id']) bd_modifier('concerts', ['ensemble_id' => $r['id']], ['id' => $concert_id]);
    }
    return $r;
}

/** Concerts dont l'ensemble n'est relié à aucun orchestre référencé : [[concert, résultat de la recherche], …]. */
function concerts_orchestre_inconnu(): array
{
    $l = [];
    $sql = "SELECT c.* FROM concerts c WHERE c.type <> 'disque' AND c.ensemble_fr <> '' AND NOT EXISTS (SELECT 1 FROM concerts_orchestres co WHERE co.concert_id = c.id) ORDER BY c.id";
    foreach (bd_lignes($sql) as $c) $l[] = [$c, orchestre_chercher($c['ensemble_fr'])];
    return $l;
}

/**
 * Crée la fiche d'un orchestre à partir d'un concert : son nom est celui de l'ensemble du concert,
 * sa ville et son pays ceux du concert, à titre de proposition à confirmer (un orchestre en tournée
 * n'est pas chez lui). Le concert lui est associé. Rend l'identifiant de la fiche.
 */
function orchestre_creer_depuis_concert(int $concert_id): ?int
{
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$concert_id]);
    if (!$c || trim($c['ensemble_fr']) === '') return null;
    $en = traduction_publiee('concerts', $concert_id, 'ensemble', 'en');
    $rep = bd_ligne('SELECT * FROM representations WHERE concert_id = ? ORDER BY date LIMIT 1', [$concert_id]) ?: [];
    $categorie = preg_match('/\b(ensemble|compagnie|ch[oœ]e?ur|choir|quatuor|quartet)\b/iu', $c['ensemble_fr']) ? 'ensemble' : 'orchestre';
    $id = (int) objet_enregistrer('annuaire', null, [
        'categorie' => $categorie, 'nom' => trim($c['ensemble_fr']), 'autres_noms' => ($en !== '' && normaliser($en) !== normaliser($c['ensemble_fr'])) ? $en : '',
        'ville_fr' => $c['ville_fr'] !== '' ? $c['ville_fr'] : (string) ($rep['ville_fr'] ?? ''),
        'pays_fr' => $c['pays_fr'] !== '' ? $c['pays_fr'] : (string) ($rep['pays_fr'] ?? ''),
        'verifie' => 0,
    ], 'fiche', trim($c['ensemble_fr']));
    $GLOBALS['annuaire_perime'] = true;
    bd_modifier('concerts', ['ensemble_id' => $id], ['id' => $concert_id]);
    concert_orchestres_poser($concert_id, [$id], $c['titre_fr']);
    return $id;
}

// ---------- La carte du monde ----------

/** Lignes de la carte : orchestre, ville, coordonnées, statuts, avec le nom de la fiche. */
function carte_lignes(): array
{
    return bd_lignes('SELECT p.*, a.nom, a.categorie, a.nom_provisoire FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id ORDER BY p.ordre, p.id');
}

/**
 * Coordonnées déjà connues pour une ville, sans rien demander à personne : celles d'une ligne de la
 * carte ou d'une fiche de l'annuaire qui porte ce nom de ville. Rend [latitude, longitude, source] ou null.
 */
function coordonnees_connues(string $ville): ?array
{
    $v = normaliser($ville);
    if ($v === '') return null;
    foreach (bd_lignes('SELECT id, ville_fr, latitude, longitude FROM parcours_orchestres WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY verifie DESC, id') as $l) {
        if (normaliser($l['ville_fr']) === $v || normaliser(traduction_publiee('parcours_orchestres', $l['id'], 'ville', 'en')) === $v) return [(float) $l['latitude'], (float) $l['longitude'], 'une ville déjà sur la carte'];
    }
    foreach (bd_lignes('SELECT id, ville_fr, latitude, longitude FROM annuaire WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY verifie DESC, id') as $l) {
        if (normaliser($l['ville_fr']) === $v || normaliser(traduction_publiee('annuaire', $l['id'], 'ville', 'en')) === $v) return [(float) $l['latitude'], (float) $l['longitude'], 'une fiche de l\'annuaire'];
    }
    return null;
}

/** Zone de la liste des orchestres, d'après la longitude (comme le site) ; $imposee l'emporte. */
function zone_carte(?float $longitude, string $imposee = ''): string
{
    if (in_array($imposee, ['ameriques', 'europe-afrique', 'asie-oceanie'], true)) return $imposee;
    if ($longitude === null) return '';
    return $longitude < -25 ? 'ameriques' : ($longitude > 60 ? 'asie-oceanie' : 'europe-afrique');
}

/**
 * Où en est un orchestre vis-à-vis de l'activité de l'artiste : « dirige » (au moins un concert
 * passé, ou déjà sur la carte reprise du site), « a_venir » (seulement des engagements futurs),
 * « aucun ». Rend [état, nombre de concerts passés, nombre à venir].
 */
function orchestre_activite(int $fiche_id): array
{
    $jour = aujourdhui();
    $l = bd_ligne("SELECT SUM(x.derniere < ?) AS passes, SUM(x.derniere >= ?) AS futurs FROM (
            SELECT co.concert_id, MAX(r.date) AS derniere FROM concerts_orchestres co JOIN representations r ON r.concert_id = co.concert_id
            WHERE co.fiche_id = ? AND r.statut <> 'annulee' GROUP BY co.concert_id) x", [$jour, $jour, $fiche_id]);
    $passes = (int) ($l['passes'] ?? 0);
    $futurs = (int) ($l['futurs'] ?? 0);
    $reprise = (bool) bd_valeur("SELECT COUNT(*) FROM parcours_orchestres WHERE fiche_id = ? AND origine = 'reprise'", [$fiche_id]);
    return [($passes || $reprise) ? 'dirige' : ($futurs ? 'a_venir' : 'aucun'), $passes, $futurs];
}

/** La carte du monde du site, pour Pupitre : cadre, pastilles des terres (tracé SVG), lue dans data/cartes.js. null si le fichier manque. */
function carte_monde(): ?array
{
    $racine = site_racine();
    $t = $racine !== '' ? (string) @file_get_contents($racine . '/data/cartes.js') : '';
    if (!preg_match("/monde:\\s*\\{[^}]*?lon:\\s*\\[(-?[\\d.]+),\\s*(-?[\\d.]+)\\][^}]*?lat:\\s*\\[(-?[\\d.]+),\\s*(-?[\\d.]+)\\][^}]*?l:\\s*([\\d.]+)[^}]*?h:\\s*([\\d.]+)[^}]*?r:\\s*([\\d.]+)[^}]*?points:\\s*'([^']*)'/s", $t, $m)) return null;
    $d = '';
    foreach (explode(' ', trim($m[8])) as $p) {
        if (strpos($p, ',') === false) continue;
        [$x, $y] = explode(',', $p);
        $d .= 'M' . $x . ' ' . $y . 'h0';
    }
    return ['lon' => [(float) $m[1], (float) $m[2]], 'lat' => [(float) $m[3], (float) $m[4]], 'l' => (float) $m[5], 'h' => (float) $m[6], 'r' => (float) $m[7], 'trace' => $d];
}

/** Position d'un point sur la carte (unités du cadre) : la projection du site, équirectangulaire. */
function carte_projeter(array $carte, float $lat, float $lon): array
{
    if ($lon < $carte['lon'][0]) $lon += 360;                 // la carte va de 170° O à 190° E
    return [
        round(($lon - $carte['lon'][0]) / ($carte['lon'][1] - $carte['lon'][0]) * $carte['l'], 2),
        round(($carte['lat'][1] - $lat) / ($carte['lat'][1] - $carte['lat'][0]) * $carte['h'], 2),
    ];
}
