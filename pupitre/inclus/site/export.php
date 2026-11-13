<?php
/**
 * Adaptateur du site, première moitié : de la base aux fichiers de data/.
 *
 * Chaque fonction export_…() rend les données d'un fichier, dans la forme exacte que lit le
 * site (voir LISEZ-MOI.md du site, « Données »). site_fichiers() en fait le texte des fichiers.
 * Le site ne reçoit que les représentations annoncées par leur organisateur : statuts
 * « annoncee » et « publiee », et rien d'autre (export_agenda).
 *
 * C'est ici, et dans repli.php, que Pupitre connaît la forme du site. Si le site change de
 * gabarit, ce sont ces deux fichiers qu'on adapte.
 */

defined('PUPITRE') or exit;

/** Statuts qui sortent sur le site. */
const STATUTS_SUR_LE_SITE = ['annoncee', 'publiee'];

const NOTE_AGENDA = 'Uniquement des représentations annoncées par leur organisateur. Tout champ texte est soit une chaîne, soit un objet {fr, en}. Une tournée porte ville, pays, salle et lien dans chaque représentation. Dates et heures locales de la salle.';

/** Champ en deux langues : une chaîne si l'anglais est vide ou identique, sinon { fr, en }. */
function bi(?string $fr, ?string $en)
{
    $fr = trim((string) $fr);
    $en = trim((string) $en);
    return ($en === '' || $en === $fr) ? $fr : ['fr' => $fr, 'en' => $en];
}

/** Lit un champ du site (chaîne ou { fr, en }) : [fr, en], l'anglais vide s'il est identique. */
function bi_lire($champ): array
{
    if ($champ === null) return ['', ''];
    if (is_array($champ)) {
        $fr = trim((string) ($champ['fr'] ?? ''));
        $en = trim((string) ($champ['en'] ?? ''));
        return [$fr, $en === $fr ? '' : $en];
    }
    return [trim((string) $champ), ''];
}

/** Chemin d'un média dans le site : celui qu'il a déjà, sinon celui qu'il aura une fois copié. */
function media_chemin_site(?array $m): string
{
    if (!$m) return '';
    if (!empty($m['chemin_site'])) return $m['chemin_site'];
    return empty($m['fichier']) ? '' : 'assets/pupitre/' . $m['fichier'];
}

function medias_par_id(): array
{
    static $m = null;
    if ($m === null || !empty($GLOBALS['medias_perimes'])) {
        $m = [];
        foreach (bd_lignes('SELECT * FROM medias') as $l) $m[(int) $l['id']] = $l;
        $GLOBALS['medias_perimes'] = false;
    }
    return $m;
}

// ---------- Un fichier, une fonction ----------

function export_textes(): array
{
    $t = [];
    foreach (bd_lignes('SELECT * FROM textes') as $l) $t[$l['cle']] = $l;
    $champ = fn(string $cle) => bi($t[$cle]['fr'] ?? '', $t[$cle]['en'] ?? '');
    $fr = paragraphes($t['biographie']['fr'] ?? '');
    $en = paragraphes($t['biographie']['en'] ?? '');
    $bio = [];
    foreach ($fr as $i => $p) $bio[] = bi($p, $en[$i] ?? '');
    return [
        'description' => $champ('description'),
        'citation' => $champ('citation'),
        'biographie' => $bio,
        'saison' => $champ('saison'),
        'lettre' => $champ('lettre'),
        'credit' => $champ('credit'),
    ];
}

/**
 * L'agenda du site. $statuts : par défaut ceux qui sortent sur le site ; la vue de l'artiste
 * et les contrôles ne passent jamais par ici pour écrire un fichier.
 */
function export_agenda(array $statuts = STATUTS_SUR_LE_SITE): array
{
    $statuts = array_values(array_intersect($statuts, ['connue', 'embargo', 'annoncee', 'publiee', 'annulee']));
    $maisons = [];
    $cles_maisons = [];
    foreach (bd_lignes('SELECT * FROM maisons ORDER BY ordre, id') as $m) {
        $maisons[$m['cle']] = bi($m['nom_fr'], $m['nom_en']);
        $cles_maisons[(int) $m['id']] = $m['cle'];
    }
    $evenements = [];
    $version = '';
    if ($statuts) {
        $marques = implode(',', array_fill(0, count($statuts), '?'));
        $reps = bd_lignes("SELECT * FROM representations WHERE statut IN ($marques) ORDER BY date, heure, id", $statuts);
        $par_concert = [];
        foreach ($reps as $r) $par_concert[(int) $r['concert_id']][] = $r;
        $concerts = $par_concert
            ? bd_lignes('SELECT * FROM concerts WHERE id IN (' . implode(',', array_keys($par_concert)) . ')')
            : [];
        // dans l'ordre de la première représentation qui sort
        usort($concerts, fn($a, $b) => [$par_concert[$a['id']][0]['date'], $a['id']] <=> [$par_concert[$b['id']][0]['date'], $b['id']]);
        foreach ($concerts as $c) {
            $id = (int) $c['id'];
            $e = [
                'id' => $c['cle'],
                'type' => $c['type'],
                'titre' => bi($c['titre_fr'], $c['titre_en']),
                'ensemble' => bi($c['ensemble_fr'], $c['ensemble_en']),
                'maison' => $cles_maisons[(int) $c['maison_id']] ?? '',
            ];
            foreach (['ville', 'pays', 'salle'] as $k) {
                if ($c[$k . '_fr'] !== '') $e[$k] = bi($c[$k . '_fr'], $c[$k . '_en']);
            }
            $e['representations'] = [];
            foreach ($par_concert[$id] as $r) {
                $l = ['date' => $r['date']];
                if ($r['heure']) $l['heure'] = $r['heure'];
                foreach (['ville', 'pays', 'salle'] as $k) {
                    if ($r[$k . '_fr'] !== '') $l[$k] = bi($r[$k . '_fr'], $r[$k . '_en']);
                }
                if ($r['lien_billetterie'] !== '') $l['lien'] = $r['lien_billetterie'];
                $e['representations'][] = $l;
                $version = max($version, substr($r['modifie_le'], 0, 10));
            }
            $avec = [];
            foreach (bd_lignes('SELECT * FROM participants WHERE concert_id = ? ORDER BY ordre, id', [$id]) as $p) {
                $avec[] = ['nom' => $p['nom'], 'role' => bi($p['role_fr'], $p['role_en'])];
            }
            if ($avec) $e['avec'] = $avec;
            $programme = [];
            foreach (bd_lignes('SELECT * FROM programme WHERE concert_id = ? ORDER BY ordre, id', [$id]) as $p) {
                $l = ['compositeur' => bi($p['compositeur_fr'], $p['compositeur_en']), 'oeuvre' => bi($p['oeuvre_fr'], $p['oeuvre_en'])];
                if ($p['mention_fr'] !== '') $l['mention'] = bi($p['mention_fr'], $p['mention_en']);
                $programme[] = $l;
            }
            if ($programme) $e['programme'] = $programme;
            if (trim((string) $c['mentions_fr']) !== '') $e['mentions'] = bi($c['mentions_fr'], $c['mentions_en']);
            $e['tempsFort'] = (bool) $c['temps_fort'];
            $e['lien'] = $c['lien_billetterie'] !== '' ? $c['lien_billetterie'] : $c['page_organisateur'];
            $evenements[] = $e;
            $version = max($version, substr($c['modifie_le'], 0, 10));
        }
    }
    return [
        'version' => $version ?: date('Y-m-d'),
        'note' => NOTE_AGENDA,
        'maisons' => (object) $maisons,
        'evenements' => $evenements,
    ];
}

function export_playlist(): array
{
    $m = medias_par_id();
    $liste = [];
    foreach (bd_lignes('SELECT * FROM musiques ORDER BY ordre, id') as $t) {
        $src = media_chemin_site($m[(int) $t['media_id']] ?? null);
        if ($src === '') continue;                       // pas de fichier : le titre ne sort pas
        $liste[] = [
            'id' => $t['cle'],
            'src' => $src,
            'piste' => $t['piste'],
            'titre' => bi($t['titre_fr'], $t['titre_en']),
            'mention' => bi($t['mention_fr'], $t['mention_en']),
        ];
    }
    return $liste;
}

function export_visuels(): object
{
    $m = medias_par_id();
    $position = fn($x, $y) => ($x === null || $y === null) ? '' : ((int) $x) . '% ' . ((int) $y) . '%';
    $v = [];
    foreach (bd_lignes('SELECT * FROM visuels ORDER BY ordre, cle') as $l) {
        $e = ['type' => $l['type']];
        if ($l['type'] === 'image' || $l['type'] === 'video') {
            $e['src'] = media_chemin_site($m[(int) $l['media_id']] ?? null);
            if ($l['type'] === 'video') {
                $affiche = media_chemin_site($m[(int) $l['affiche_id']] ?? null);
                if ($affiche !== '') $e['affiche'] = $affiche;
            }
            if (($p = $position($l['position_x'], $l['position_y'])) !== '') $e['position'] = $p;
            if (($p = $position($l['mobile_x'], $l['mobile_y'])) !== '') $e['positionMobile'] = $p;
            if ($l['type'] === 'image' && $l['lumiere'] !== '') $e['lumiere'] = $l['lumiere'];
            if ($l['type'] === 'video' && $l['son']) {
                $e['son'] = true;
                if ($l['piste'] !== '') $e['piste'] = $l['piste'];
            }
            $e['alt'] = bi($l['alt_fr'], $l['alt_en']);
        } elseif ($l['type'] === 'youtube') {
            $e[preg_match('#^https?://#', $l['source']) ? 'url' : 'id'] = $l['source'];
        } elseif ($l['type'] === 'couleur') {
            $e['couleur'] = $l['source'];
        }
        if ($l['nuee'] !== '') $e['nuee'] = $l['nuee'];
        $e['opacite'] = (int) $l['opacite'];
        $v[$l['cle']] = $e;
    }
    return (object) $v;
}

/** « Nom | adresse », un par ligne → [{ nom, url }]. */
function liens_lire(?string $texte): array
{
    $liens = [];
    foreach (lignes($texte) as $l) {
        $p = array_map('trim', explode('|', $l, 2));
        $url = $p[1] ?? $p[0];
        if (!adresse_valide($url) || $url === '') continue;
        $liens[] = ['nom' => isset($p[1]) ? $p[0] : (string) parse_url($url, PHP_URL_HOST), 'url' => $url];
    }
    return $liens;
}

function export_disques(): array
{
    $liste = [];
    foreach (bd_lignes('SELECT d.*, a.nom AS maison FROM disques d LEFT JOIN annuaire a ON a.id = d.maison_id ORDER BY d.ordre, d.id') as $d) {
        $fr = lignes($d['descriptif_fr']);
        $en = lignes($d['descriptif_en']);
        $descriptif = [];
        foreach ($fr as $i => $l) $descriptif[] = bi($l, $en[$i] ?? '');
        $e = [
            'id' => $d['cle'],
            'titre' => $d['titre'],
            'annee' => $d['annee'] === null ? '' : (int) $d['annee'],
            'maison' => (string) $d['maison'],
            'descriptif' => count($descriptif) === 1 ? $descriptif[0] : ($descriptif ?: ''),
            'mot' => bi($d['mot_fr'], $d['mot_en']),
            'commander' => liens_lire($d['commander']),
            'streaming' => liens_lire($d['streaming']),
        ];
        if ($d['lien'] !== '') $e['lien'] = $d['lien'];
        $liste[] = $e;
    }
    return $liste;
}

function export_presse(): array
{
    $m = medias_par_id();
    $liste = [];
    $sql = 'SELECT p.*, a.nom AS media, a.logo_id FROM articles p LEFT JOIN annuaire a ON a.id = p.media_id ORDER BY p.ordre, p.id';
    foreach (bd_lignes($sql) as $a) {
        $liste[] = [
            'id' => $a['cle'],
            'media' => (string) $a['media'],
            'logo' => media_chemin_site($m[(int) $a['logo_id']] ?? null),
            'titre' => bi($a['titre_fr'], $a['titre_en']),
            'auteur' => $a['auteur'],
            'date' => $a['date_article'],
            'verbatim' => trim((string) $a['verbatim']),
            'langue' => $a['langue'],
            'resume' => bi($a['resume_fr'], $a['resume_en']),
            'lien' => $a['lien'],
        ];
    }
    return $liste;
}

function export_orchestres(): array
{
    $liste = [];
    foreach (bd_lignes('SELECT * FROM annuaire WHERE dans_parcours = 1 ORDER BY parcours_ordre, id') as $a) {
        if ($a['latitude'] === null || $a['longitude'] === null) continue;    // sans coordonnées, pas de point : la ligne ne sort pas
        $e = [
            'nom' => $a['nom_provisoire'] ? '' : $a['nom'],
            'ville' => bi($a['ville_fr'], $a['ville_en']),
            'lat' => (float) $a['latitude'],
            'lon' => (float) $a['longitude'],
        ];
        if ($a['parcours_zone'] !== '') $e['zone'] = $a['parcours_zone'];
        $liste[] = $e;
    }
    return $liste;
}

function export_parcours(): array
{
    $p = ['fonctions' => [], 'opera' => []];
    foreach (bd_lignes('SELECT * FROM parcours_lignes ORDER BY ordre, id') as $l) {
        if (trim($l['texte_fr']) !== '') $p[$l['liste']][] = bi($l['texte_fr'], $l['texte_en']);
    }
    return $p;
}

function export_contacts(): array
{
    $groupes = [];
    foreach (bd_lignes('SELECT * FROM contacts_groupes ORDER BY ordre, id') as $g) {
        $personnes = [];
        foreach (bd_lignes('SELECT * FROM contacts_personnes WHERE groupe_id = ? ORDER BY ordre, id', [$g['id']]) as $p) {
            $personnes[] = ['nom' => $p['nom'], 'role' => bi($p['role_fr'], $p['role_en']), 'telephone' => $p['telephone'], 'email' => $p['email']];
        }
        $groupes[] = [
            'structure' => $g['structure'],
            'lien' => $g['lien'],
            'mention' => bi($g['mention_fr'], $g['mention_en']),
            'langue' => $g['langue'],
            'personnes' => $personnes,
        ];
    }
    return $groupes;
}

function export_reseaux(): array
{
    $liste = [];
    foreach (bd_lignes('SELECT * FROM reseaux ORDER BY ordre, id') as $r) {
        $liste[] = ['id' => $r['cle'], 'nom' => $r['nom'], 'url' => $r['url'], 'barre' => (bool) $r['barre']];
    }
    return $liste;
}

function export_telechargements(): array
{
    $m = medias_par_id();
    $liste = [];
    foreach (bd_lignes('SELECT * FROM telechargements ORDER BY ordre, id') as $t) {
        $liste[] = ['id' => $t['cle'], 'libelle' => bi($t['libelle_fr'], $t['libelle_en']), 'fichier' => media_chemin_site($m[(int) $t['media_id']] ?? null)];
    }
    return $liste;
}

// ---------- Des données aux fichiers ----------

/** Les fichiers de data/ que Pupitre écrit : nom du fichier => [variable, fonction d'export]. */
const FICHIERS_DU_SITE = [
    'data/textes.js' => ['TEXTES', 'export_textes'],
    'data/agenda.js' => ['AGENDA_DONNEES', 'export_agenda'],
    'data/playlist.js' => ['PLAYLIST', 'export_playlist'],
    'data/visuels.js' => ['VISUELS', 'export_visuels'],
    'data/disques.js' => ['DISQUES', 'export_disques'],
    'data/presse.js' => ['PRESSE', 'export_presse'],
    'data/orchestres.js' => ['ORCHESTRES', 'export_orchestres'],
    'data/parcours.js' => ['PARCOURS', 'export_parcours'],
    'data/contacts.js' => ['CONTACTS', 'export_contacts'],
    'data/reseaux.js' => ['RESEAUX', 'export_reseaux'],
    'data/telechargements.js' => ['TELECHARGEMENTS', 'export_telechargements'],
];

/** Toutes les données du site, par variable : ['TEXTES' => …, 'AGENDA_DONNEES' => …]. */
function site_exports(): array
{
    $e = [];
    foreach (FICHIERS_DU_SITE as [$variable, $fonction]) $e[$variable] = $fonction();
    return $e;
}

/** JSON strict, lisible, en retrait de deux espaces. */
function json_site($donnees): string
{
    $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return preg_replace_callback('/^(?: {4})+/m', fn($m) => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json);
}

/** Texte d'un fichier de données : une ligne de commentaire, puis window.NOM = JSON strict. */
function fichier_donnees(string $variable, $donnees): string
{
    return "/* Généré par Pupitre, ne pas modifier à la main. */\nwindow.$variable = " . json_site($donnees) . ";\n";
}

/** Texte de tous les fichiers de data/ : chemin dans le site => contenu. */
function site_fichiers(array $exports): array
{
    $f = [];
    foreach (FICHIERS_DU_SITE as $chemin => [$variable]) $f[$chemin] = fichier_donnees($variable, $exports[$variable]);
    $f['data/agenda.json'] = json_site($exports['AGENDA_DONNEES']) . "\n";
    return $f;
}

/** Lit un fichier de données du site (window.NOM = JSON ;) : la valeur, ou null s'il est illisible. */
function site_lire_donnees(string $chemin)
{
    if (!is_readable($chemin)) return null;
    $t = file_get_contents($chemin);
    if (str_ends_with($chemin, '.json')) return json_decode($t, true);
    if (!preg_match('/window\.[A-Z_]+\s*=\s*(.*);\s*$/s', $t, $m)) return null;
    return json_decode($m[1], true);
}
