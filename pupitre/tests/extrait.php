<?php
/**
 * Lit un extrait déjà déposé dans pupitre/stockage/imports/, comme le ferait l'écran, et donne le
 * bilan du contrôle. Appelle l'IA : ANTHROPIC_API_KEY doit être renseignée dans pupitre/.env.
 *
 *     php pupitre/tests/extrait.php nom-du-fichier.pdf
 *
 * Seuls des décomptes sont affichés : le contenu de l'extrait ne sort pas dans le terminal.
 * Les lignes restent à valider dans Pupitre (« Extrait de l'agence ») : rien n'entre dans l'agenda ici.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../inclus/amorce.php';
set_time_limit(0);

$nom = basename((string) ($argv[1] ?? ''));
$chemin = STOCKAGE . '/imports/' . $nom;
if ($nom === '' || !is_file($chemin)) {
    fwrite(STDERR, "Usage : php pupitre/tests/extrait.php <fichier présent dans pupitre/stockage/imports/>\n");
    exit(1);
}
$ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
$nature = match ($ext) { 'pdf' => 'pdf', 'xlsx', 'csv', 'tsv' => 'tableur', default => 'texte' };
$id = import_creer($nature, $nom, $nom);
echo "Import $id déposé. Lecture en cours (une à quelques minutes)…\n";
foreach (taches_executer() as [$tache, $type, $statut, $message]) echo "tâche $tache ($type) : $statut · $message\n";

$imp = bd_ligne('SELECT * FROM imports WHERE id = ?', [$id]);
if ($imp['statut'] !== 'a_valider') {
    echo "État : " . $imp['statut'] . ($imp['erreur'] !== '' ? ' · ' . $imp['erreur'] : '') . "\n";
    exit(1);
}
$b = json_decode((string) $imp['bilan'], true);
echo "Extrait du " . ($imp['date_extrait'] ?: '(date non lue)') . " : {$b['engagements']} engagements, {$b['representations']} représentations\n";
foreach (NATURES_IMPORT as $k => $libelle) if (!empty($b['natures'][$k])) printf("  %-22s %d\n", $libelle, $b['natures'][$k]);
echo "Contrôle\n";
foreach (CONTROLES_IMPORT as $k => $libelle) printf("  %-22s %d\n", $libelle, $b['controles'][$k] ?? 0);
// décompte des dates passées par mois, sans titre ni lieu
$passees = [];
foreach (import_lignes($id) as $l) {
    if (in_array('passee', $l['controles'], true)) $passees[substr($l['propose']['date'], 0, 7)] = ($passees[substr($l['propose']['date'], 0, 7)] ?? 0) + 1;
}
foreach ($passees as $mois => $n) echo "  dates passées en " . mois_fr($mois) . " : $n\n";
echo "À valider dans Pupitre : index.php?page=import&id=$id\n";
