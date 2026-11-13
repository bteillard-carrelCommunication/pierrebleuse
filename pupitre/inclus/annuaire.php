<?php
/**
 * Annuaire : personnes et structures, comptes sociaux, doublons, concerts liés.
 */

defined('PUPITRE') or exit;

const CATEGORIES = [
    'orchestre' => 'Orchestre', 'ensemble' => 'Ensemble', 'salle' => 'Salle', 'festival' => 'Festival',
    'soliste' => 'Soliste', 'compositeur' => 'Compositeur', 'maison_de_disques' => 'Maison de disques',
    'media' => 'Média', 'photographe' => 'Photographe', 'producteur' => 'Producteur', 'agence' => 'Agence',
];

/** États d'un compte social. Seul un compte vérifié sert de référence. */
const ETATS_COMPTE = ['propose' => 'Proposé', 'verifie' => 'Vérifié', 'a_revoir' => 'À revoir'];

const RESEAUX_CONNUS = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'youtube' => 'YouTube',
    'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'bluesky' => 'Bluesky', 'threads' => 'Threads'];

const ZONES_PARCOURS = ['' => 'Déduite de la longitude', 'ameriques' => 'Amériques', 'europe-afrique' => 'Europe et Afrique', 'asie-oceanie' => 'Asie, Russie et Océanie'];

function fiche_lire(int $id): ?array
{
    return bd_ligne('SELECT * FROM annuaire WHERE id = ?', [$id]);
}

/** Tous les noms d'une fiche, normalisés : son nom, puis ses autres noms. */
function fiche_noms(array $f): array
{
    $noms = array_merge([$f['nom']], lignes($f['autres_noms'] ?? ''));
    return array_values(array_unique(array_filter(array_map('normaliser', $noms), 'strlen')));
}

/** Index de l'annuaire : nom normalisé => identifiants des fiches qui le portent. */
function annuaire_index(): array
{
    static $index = null;
    if ($index === null || !empty($GLOBALS['annuaire_perime'])) {
        $index = [];
        foreach (bd_lignes('SELECT id, categorie, nom, autres_noms FROM annuaire ORDER BY id') as $f) {
            foreach (fiche_noms($f) as $n) $index[$n][] = ['id' => (int) $f['id'], 'categorie' => $f['categorie']];
        }
        $GLOBALS['annuaire_perime'] = false;
    }
    return $index;
}

/** La fiche qui porte ce nom (ou cet autre nom), parmi les catégories données ; null si aucune. */
function fiche_trouver(string $nom, array $categories = []): ?int
{
    $n = normaliser($nom);
    if ($n === '') return null;
    foreach (annuaire_index()[$n] ?? [] as $f) {
        if (!$categories || in_array($f['categorie'], $categories, true)) return $f['id'];
    }
    return null;
}

/** Crée la fiche si aucune ne porte ce nom dans ces catégories. Rend son identifiant. */
function fiche_assurer(string $categorie, string $nom, array $champs = [], array $categories_voisines = []): ?int
{
    $nom = trim($nom);
    if ($nom === '') return null;
    $id = fiche_trouver($nom, array_merge([$categorie], $categories_voisines));
    if ($id) return $id;
    $id = objet_enregistrer('annuaire', null, array_merge(['categorie' => $categorie, 'nom' => $nom], $champs), 'fiche', $nom);
    $GLOBALS['annuaire_perime'] = true;
    return $id;
}

/** Noms proposés à la saisie (listes de choix des formulaires), par catégories. */
function annuaire_noms(array $categories): array
{
    if (!$categories) return [];
    $marques = implode(',', array_fill(0, count($categories), '?'));
    return bd_colonne("SELECT nom FROM annuaire WHERE categorie IN ($marques) ORDER BY nom", $categories);
}

/**
 * Doublons probables : deux fiches qui partagent un nom (ou un autre nom), ou dont les noms sont
 * presque identiques. Rend des paires [fiche, fiche, raison]. $id : seulement celles de cette fiche.
 */
function fiches_doublons(?int $id = null): array
{
    $fiches = bd_lignes('SELECT id, categorie, nom, autres_noms, ville_fr FROM annuaire ORDER BY id');
    $noms = [];
    foreach ($fiches as $f) $noms[$f['id']] = fiche_noms($f);
    $paires = [];
    $n = count($fiches);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $a = $fiches[$i];
            $b = $fiches[$j];
            if ($id !== null && (int) $a['id'] !== $id && (int) $b['id'] !== $id) continue;
            $raison = '';
            if (array_intersect($noms[$a['id']], $noms[$b['id']])) {
                $raison = 'même nom';
            } else {
                $x = $noms[$a['id']][0] ?? '';
                $y = $noms[$b['id']][0] ?? '';
                if (strlen($x) >= 8 && strlen($y) >= 8 && abs(strlen($x) - strlen($y)) <= 3 && levenshtein($x, $y) <= 2) $raison = 'noms très proches';
            }
            if ($raison !== '') $paires[] = [$a, $b, $raison];
        }
    }
    return $paires;
}

/** Concerts liés à une fiche : comme ensemble, salle, participant ou compositeur. */
function fiche_concerts(int $id): array
{
    $sql = "SELECT c.id, c.titre_fr, c.type, x.lien,
                   (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id) AS premiere
            FROM concerts c JOIN (
                SELECT id AS concert_id, 'ensemble' AS lien FROM concerts WHERE ensemble_id = ?
                UNION SELECT id, 'salle' FROM concerts WHERE salle_id = ?
                UNION SELECT p.concert_id, 'participant' FROM participants p JOIN participants_fiches pf ON pf.participant_id = p.id WHERE pf.fiche_id = ?
                UNION SELECT concert_id, 'compositeur' FROM programme WHERE compositeur_id = ?
            ) x ON x.concert_id = c.id
            ORDER BY premiere DESC, c.id";
    return bd_lignes($sql, [$id, $id, $id, $id]);
}

/** Où une fiche est utilisée hors de l'agenda (disques, presse) : pour refuser une suppression. */
function fiche_usages(int $id): array
{
    $u = [];
    foreach (fiche_concerts($id) as $c) $u[] = 'concert « ' . $c['titre_fr'] . ' » (' . $c['lien'] . ')';
    foreach (bd_colonne('SELECT ville_fr FROM parcours_orchestres WHERE fiche_id = ?', [$id]) as $v) $u[] = 'parcours du site (' . $v . ')';
    foreach (bd_colonne('SELECT titre FROM disques WHERE maison_id = ?', [$id]) as $t) $u[] = 'disque « ' . $t . ' »';
    foreach (bd_colonne('SELECT titre_fr FROM articles WHERE media_id = ?', [$id]) as $t) $u[] = 'article « ' . $t . ' »';
    return $u;
}

/**
 * Fuseau probable d'un lieu, d'après son pays et sa ville : celui du pays s'il n'en a qu'un (ou
 * si tous marquent la même heure à cette date), sinon celui qui porte le nom de la ville.
 * Rend '' quand on ne peut pas le dire : rien n'est inventé.
 */
function fuseau_deviner(string $pays_fr, string $ville_fr, string $ville_en = '', ?string $date = null): string
{
    static $codes = null;
    if (!class_exists('Locale') || trim($pays_fr) === '') return '';
    if ($codes === null) {
        $codes = [];
        foreach (range('A', 'Z') as $a) {
            foreach (range('A', 'Z') as $b) {
                $nom = Locale::getDisplayRegion('-' . $a . $b, 'fr');
                $n = normaliser($nom);
                // un même nom peut revenir sous un code périmé, sans fuseau : seul compte le code qui en a
                if ($nom === '' || $nom === $a . $b || isset($codes[$n])) continue;
                if (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $a . $b)) $codes[$n] = $a . $b;
            }
        }
    }
    $code = $codes[normaliser($pays_fr)] ?? '';
    if ($code === '') return '';
    $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);
    if (!$zones) return '';
    if (count($zones) === 1) return $zones[0];
    foreach ([$ville_en, $ville_fr] as $ville) {
        $v = normaliser($ville);
        if ($v === '') continue;
        foreach ($zones as $z) {
            if (normaliser(str_replace('_', ' ', substr($z, strrpos($z, '/') + 1))) === $v) return $z;
        }
    }
    try {
        $quand = new DateTimeImmutable(($date ?: date('Y-m-d')) . ' 12:00:00', new DateTimeZone('UTC'));
        $decalages = array_unique(array_map(fn($z) => (new DateTimeZone($z))->getOffset($quand), $zones));
        if (count($decalages) === 1) return $zones[0];
    } catch (Throwable $e) {
    }
    return '';
}
