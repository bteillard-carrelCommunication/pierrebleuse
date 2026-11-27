<?php
/**
 * Import de l'extrait de l'agence.
 *
 * 1. Dépôt d'un PDF, d'un tableur ou d'un texte collé : rangé dans stockage/imports/, hors git.
 * 2. Lecture en tâche de fond : l'IA rend des engagements structurés, dans un JSON que le code
 *    contrôle. Un échec de contrôle donne une seule relance, puis une erreur lisible.
 * 3. Comparaison avec l'agenda, faite par le code et non par l'IA : nouvelles dates, dates
 *    modifiées, dates disparues, programmes changés. Rapprochement sur la date et l'ensemble ;
 *    les cas douteux sont présentés à la main.
 * 4. Contrôle : lieu, ville ou heure manquants, date hors saison, date passée, doublon probable.
 * 5. Validation ligne par ligne ou en bloc, avec correction possible. Rien n'est écrit avant.
 *
 * L'extrait est un document interne : son contenu ne sort ni dans un journal, ni dans un fichier
 * du site. Une date validée entre dans l'agenda au statut « connue », qui ne se publie pas.
 */

defined('PUPITRE') or exit;

const NATURES_IMPORT = [
    'nouvelle' => 'Nouvelle date',
    'modifiee' => 'Date modifiée',
    'programme' => 'Programme changé',
    'disparue' => 'Date absente de l\'extrait',
    'annulee' => 'Annulation annoncée',
    'douteuse' => 'Cas douteux',
    'inchangee' => 'Inchangée',
];

const CONTROLES_IMPORT = [
    'lieu' => 'lieu manquant',
    'ville' => 'ville manquante',
    'heure' => 'heure manquante',
    'hors_saison' => 'date hors saison',
    'passee' => 'date passée',
    'doublon' => 'doublon probable',
];

const IMPORT_TAILLE_MAX = 31457280;      // 30 Mo : sous la limite de l'API pour un PDF

const DEMANDE_EXTRAIT = 'Voici l\'extrait de l\'agence. Rends tous les engagements qu\'il contient, dans le format demandé.';

/** Schéma JSON de la réponse attendue de l'IA. Tous les champs sont présents ; l'absence se dit null. */
function schema_extrait(): array
{
    $texte = ['anyOf' => [['type' => 'string'], ['type' => 'null']]];
    $objet = fn(array $proprietes) => ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($proprietes), 'properties' => $proprietes];
    return $objet([
        'date_extrait' => $texte + ['description' => 'Date à laquelle l\'extrait a été établi, AAAA-MM-JJ, si le document la porte'],
        'engagements' => ['type' => 'array', 'items' => $objet([
            'titre' => $texte,
            'type' => ['type' => 'string', 'enum' => ['concert', 'opera', 'tournee', 'disque', 'autre']],
            'ensemble' => $texte,
            'mentions' => $texte,
            'representations' => ['type' => 'array', 'items' => $objet([
                'date' => ['type' => 'string', 'description' => 'AAAA-MM-JJ, telle qu\'écrite dans le document'],
                'heure' => $texte + ['description' => 'HH:MM, heure locale de la salle'],
                'salle' => $texte,
                'ville' => $texte,
                'pays' => $texte,
                'annulee' => ['type' => 'boolean', 'description' => 'Vrai seulement si le document dit en toutes lettres que cette représentation est annulée'],
                'motif' => $texte + ['description' => 'Ce que le document dit de l\'annulation, recopié ; null sinon'],
            ])],
            'programme' => ['type' => 'array', 'items' => $objet(['compositeur' => $texte, 'oeuvre' => $texte, 'mention' => $texte])],
            'solistes' => ['type' => 'array', 'items' => $objet(['nom' => ['type' => 'string'], 'role' => $texte])],
        ])],
    ]);
}

// ---------- 1. Dépôt ----------

/**
 * Dépose un extrait : un fichier reçu ($_FILES) ou un texte collé. Rend l'identifiant de
 * l'import, ou une chaîne : le message d'erreur.
 */
function import_deposer(?array $recu, string $texte)
{
    $dossier = dossier(STOCKAGE . '/imports');
    $nom = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if ($recu && ($recu['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($recu['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($recu['tmp_name'])) return erreur_depot((int) $recu['error']);
        if (filesize($recu['tmp_name']) > IMPORT_TAILLE_MAX) return 'Le fichier dépasse ' . taille_lisible(IMPORT_TAILLE_MAX) . '.';
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($recu['tmp_name']);
        $ext = strtolower(pathinfo((string) $recu['name'], PATHINFO_EXTENSION));
        if ($mime === 'application/pdf') [$nature, $ext] = ['pdf', 'pdf'];
        elseif ($ext === 'xlsx' && in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'], true)) $nature = 'tableur';
        elseif (in_array($ext, ['csv', 'tsv'], true) && str_starts_with($mime, 'text/')) $nature = 'tableur';
        elseif ($ext === 'txt' && str_starts_with($mime, 'text/')) $nature = 'texte';
        else return 'On attend un PDF, un tableur (.xlsx, .csv) ou un texte (.txt).';
        $fichier = $nom . '.' . $ext;
        if (!move_uploaded_file($recu['tmp_name'], $dossier . '/' . $fichier)) return 'Le fichier n\'a pas pu être rangé (droits du dossier stockage/imports).';
        $origine = mb_substr((string) $recu['name'], 0, 255);
    } elseif (trim($texte) !== '') {
        $nature = 'texte';
        $fichier = $nom . '.txt';
        file_put_contents($dossier . '/' . $fichier, $texte);
        $origine = 'Texte collé';
    } else {
        return 'Déposez un fichier ou collez un texte.';
    }
    @chmod($dossier . '/' . $fichier, 0640);
    return import_creer($nature, $fichier, $origine);
}

/** Enregistre un extrait déjà rangé dans stockage/imports/ et dépose sa tâche de lecture. */
function import_creer(string $nature, string $fichier, string $origine): int
{
    $c = en_cli() ? null : compte();
    $id = bd_inserer('imports', ['nature' => $nature, 'fichier' => $fichier, 'nom_origine' => $origine, 'cree_par' => $c['id'] ?? null]);
    $tache = tache_creer('import_lecture', ['import_id' => $id]);
    bd_modifier('imports', ['tache_id' => $tache], ['id' => $id]);
    journaliser('creation', 'import', $id, 'Extrait déposé : ' . $origine, null, ['nature' => $nature]);
    return $id;
}

// ---------- Tableur : le contenu des cellules, en texte ----------

function tableur_en_texte(string $chemin): string
{
    $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        $t = file_get_contents($chemin);
        return mb_check_encoding($t, 'UTF-8') ? $t : mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }
    $zip = new ZipArchive();
    if ($zip->open($chemin) !== true) throw new ErreurLisible('Ce tableur est illisible.');
    $xml = function (string $nom) use ($zip) {
        $t = $zip->getFromName($nom);
        return $t === false ? null : @simplexml_load_string($t);
    };
    $chaines = [];
    if ($s = $xml('xl/sharedStrings.xml')) {
        foreach ($s->si as $si) {
            $t = isset($si->t) ? (string) $si->t : '';
            foreach ($si->r as $r) $t .= (string) $r->t;
            $chaines[] = $t;
        }
    }
    // styles : quels formats de cellule sont des dates
    $dates = [];
    if ($s = $xml('xl/styles.xml')) {
        $formats = [];
        foreach ($s->numFmts->numFmt ?? [] as $f) $formats[(int) $f['numFmtId']] = (string) $f['formatCode'];
        $i = 0;
        foreach ($s->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            $code = $formats[$id] ?? '';
            $dates[$i++] = ($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47)
                || ($code !== '' && preg_match('/[dmy]/i', preg_replace('/\[[^\]]*\]|"[^"]*"/', '', $code)));
        }
    }
    $texte = '';
    for ($n = 1; $n <= 50; $n++) {
        $feuille = $xml('xl/worksheets/sheet' . $n . '.xml');
        if (!$feuille) break;
        $texte .= "--- Feuille $n ---\n";
        foreach ($feuille->sheetData->row ?? [] as $ligne) {
            $cellules = [];
            foreach ($ligne->c as $c) {
                $v = (string) $c->v;
                $type = (string) $c['t'];
                if ($type === 's') $v = $chaines[(int) $v] ?? '';
                elseif ($type === 'inlineStr') $v = (string) $c->is->t;
                elseif ($v !== '' && is_numeric($v) && !empty($dates[(int) $c['s']])) {
                    // numéro de série d'Excel : jours depuis le 30/12/1899, fraction = heure
                    $jours = (float) $v;
                    $secondes = (int) round(($jours - floor($jours)) * 86400);
                    $v = $jours >= 1 ? gmdate($secondes ? 'Y-m-d H:i' : 'Y-m-d', (int) ((floor($jours) - 25569) * 86400) + $secondes) : gmdate('H:i', $secondes);
                }
                if (trim($v) !== '') $cellules[] = trim(preg_replace('/\s+/u', ' ', $v));
            }
            if ($cellules) $texte .= implode(' | ', $cellules) . "\n";
        }
    }
    $zip->close();
    if (trim($texte) === '') throw new ErreurLisible('Ce tableur ne contient aucune cellule lisible.');
    return $texte;
}

// ---------- 2. Lecture (tâche de fond) ----------

function import_tache(array $t): string
{
    $id = (int) ($t['charge']['import_id'] ?? 0);
    $imp = bd_ligne('SELECT * FROM imports WHERE id = ?', [$id]);
    if (!$imp) throw new ErreurLisible('Import introuvable.');
    $echec = function (string $message) use ($id) {
        bd_modifier('imports', ['statut' => 'echec', 'erreur' => mb_substr($message, 0, 500), 'termine_le' => date('Y-m-d H:i:s')], ['id' => $id]);
        throw new ErreurLisible($message);
    };
    bd_modifier('imports', ['statut' => 'lecture', 'erreur' => ''], ['id' => $id]);
    tache_avancer((int) $t['id'], 5, 'Préparation du document');
    $chemin = STOCKAGE . '/imports/' . basename($imp['fichier']);
    if (!is_file($chemin)) $echec('Le fichier de l\'extrait est introuvable dans stockage/imports/.');
    try {
        $contenu = match ($imp['nature']) {
            'pdf' => [ia_bloc_pdf($chemin), ia_bloc_texte(DEMANDE_EXTRAIT)],
            'tableur' => [ia_bloc_texte("Contenu du tableur, une ligne par ligne, les cellules séparées par « | » :\n\n" . tableur_en_texte($chemin) . "\n\n" . DEMANDE_EXTRAIT)],
            default => [ia_bloc_texte("Texte de l'extrait :\n\n" . file_get_contents($chemin) . "\n\n" . DEMANDE_EXTRAIT)],
        };
    } catch (ErreurLisible $e) {
        $echec($e->getMessage());
    }
    $donnees = null;
    for ($essai = 1; $essai <= 2; $essai++) {
        tache_avancer((int) $t['id'], 10, $essai === 1 ? 'Lecture par l\'IA' : 'Lecture par l\'IA, seconde tentative');
        $dernier = 0;
        $r = ia_appeler('lecture de l\'extrait de l\'agence', [
            'systeme' => ia_consignes('lecture-extrait'),
            'contenu' => $contenu,
            'schema' => schema_extrait(),
        ], (int) $t['id'], function (int $recus) use ($t, &$dernier) {
            if ($recus - $dernier < 1500) return;
            $dernier = $recus;
            tache_avancer((int) $t['id'], min(75, 15 + (int) ($recus / 400)), 'Lecture par l\'IA : la réponse arrive');
        });
        if (!$r['ok']) $echec($r['erreur']);
        [$donnees, $erreurs] = import_controler_reponse($r['texte']);
        if (!$erreurs) break;
        if ($essai === 2) {
            $echec('La réponse de l\'IA n\'a pas passé le contrôle, deux fois de suite (' . $erreurs[0] . (count($erreurs) > 1 ? ', et ' . (count($erreurs) - 1) . ' autre(s)' : '') . '). Rien n\'a été retenu. Réessayez, ou saisissez les dates à la main.');
        }
        // une seule relance, en disant ce qui n'allait pas
        $contenu[] = ia_bloc_texte("Ta réponse précédente n'a pas passé le contrôle du programme :\n- " . implode("\n- ", array_slice($erreurs, 0, 12))
            . "\nRends de nouveau tous les engagements, en respectant exactement le format : dates en AAAA-MM-JJ, heures en HH:MM ou null.");
    }
    tache_avancer((int) $t['id'], 85, 'Comparaison avec l\'agenda');
    $bilan = import_comparer($id, $donnees);
    bd_modifier('imports', [
        'statut' => 'a_valider',
        'date_extrait' => $donnees['date_extrait'],
        'bilan' => json_encode($bilan, JSON_UNESCAPED_UNICODE),
    ], ['id' => $id]);
    return pluriel($bilan['representations'], 'représentation lue', 'représentations lues') . ', à valider.';
}

/**
 * Contrôle la réponse de l'IA. Rend [données normalisées, erreurs]. Les erreurs disent où le
 * format n'est pas respecté, sans recopier le contenu.
 */
function import_controler_reponse(string $texte): array
{
    $d = json_decode($texte, true);
    if (!is_array($d) || !isset($d['engagements']) || !is_array($d['engagements'])) {
        return [null, ['la réponse n\'est pas le JSON attendu']];
    }
    $erreurs = [];
    $chaine = fn($v) => is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v)) : '';
    $date_extrait = $chaine($d['date_extrait'] ?? '');
    $sortie = ['date_extrait' => date_valide($date_extrait) ? $date_extrait : null, 'engagements' => []];
    foreach (array_values($d['engagements']) as $i => $e) {
        $ou = 'engagement ' . ($i + 1);
        if (!is_array($e)) { $erreurs[] = $ou . ' : forme inattendue'; continue; }
        $reps = [];
        foreach (array_values(is_array($e['representations'] ?? null) ? $e['representations'] : []) as $j => $r) {
            if (!is_array($r)) { $erreurs[] = "$ou, représentation " . ($j + 1) . ' : forme inattendue'; continue; }
            $date = $chaine($r['date'] ?? '');
            if (!date_valide($date)) { $erreurs[] = "$ou, représentation " . ($j + 1) . ' : date illisible (AAAA-MM-JJ attendu)'; continue; }
            $heure = heure_lire($chaine($r['heure'] ?? ''));
            if ($heure === null) { $erreurs[] = "$ou, représentation " . ($j + 1) . ' : heure illisible (HH:MM attendu)'; continue; }
            $reps[] = ['date' => $date, 'heure' => $heure, 'salle' => $chaine($r['salle'] ?? ''), 'ville' => $chaine($r['ville'] ?? ''), 'pays' => $chaine($r['pays'] ?? ''),
                'annulee' => ($r['annulee'] ?? false) === true, 'motif' => mb_substr($chaine($r['motif'] ?? ''), 0, 300)];
        }
        if (!$reps) continue;                                  // un engagement sans date lisible n'entre pas dans l'agenda
        $programme = [];
        foreach (is_array($e['programme'] ?? null) ? $e['programme'] : [] as $p) {
            if (!is_array($p)) continue;
            $l = ['compositeur' => $chaine($p['compositeur'] ?? ''), 'oeuvre' => $chaine($p['oeuvre'] ?? ''), 'mention' => $chaine($p['mention'] ?? '')];
            if ($l['compositeur'] !== '' || $l['oeuvre'] !== '') $programme[] = $l;
        }
        $solistes = [];
        foreach (is_array($e['solistes'] ?? null) ? $e['solistes'] : [] as $s) {
            if (is_array($s) && $chaine($s['nom'] ?? '') !== '') $solistes[] = ['nom' => $chaine($s['nom']), 'role' => $chaine($s['role'] ?? '')];
        }
        $type = $chaine($e['type'] ?? '');
        $sortie['engagements'][] = [
            'titre' => $chaine($e['titre'] ?? ''),
            'type' => array_key_exists($type, TYPES_CONCERT) ? $type : 'concert',
            'ensemble' => $chaine($e['ensemble'] ?? ''),
            'mentions' => $chaine($e['mentions'] ?? ''),
            'representations' => $reps,
            'programme' => $programme,
            'solistes' => $solistes,
        ];
    }
    return [$sortie, $erreurs];
}

// ---------- 3 et 4. Comparaison et contrôle, par le code ----------

/** Deux noms désignent-ils la même chose ? Égaux une fois normalisés, ou l'un contient l'autre. */
function noms_proches(string $a, string $b): bool
{
    $a = normaliser($a);
    $b = normaliser($b);
    if ($a === '' || $b === '') return false;
    return $a === $b || (strlen($a) >= 6 && str_contains($b, $a)) || (strlen($b) >= 6 && str_contains($a, $b));
}

/** L'ensemble de l'extrait est-il celui de cette représentation de l'agenda ? */
function ensemble_correspond(string $ensemble, string $titre, array $rep): bool
{
    $noms = [$rep['ensemble_fr'], $rep['ensemble_en'] ?? '', $rep['titre_fr']];
    if (!empty($rep['ensemble_id']) && ($f = fiche_lire((int) $rep['ensemble_id']))) $noms = array_merge($noms, [$f['nom']], lignes($f['autres_noms']));
    foreach ($noms as $n) {
        if (noms_proches($ensemble, (string) $n) || ($titre !== '' && noms_proches($titre, (string) $n))) return true;
    }
    return false;
}

/** Remarques du contrôle sur une représentation de l'extrait. */
function import_controles(array $p, string $reference): array
{
    [$debut, $fin] = saison();
    $c = [];
    if ($p['type'] !== 'disque') {
        if ($p['salle'] === '') $c[] = 'lieu';
        if ($p['ville'] === '') $c[] = 'ville';
        if (!$p['heure']) $c[] = 'heure';
    }
    if ($p['date'] < $debut || $p['date'] > $fin) $c[] = 'hors_saison';
    if ($p['date'] < $reference) $c[] = 'passee';
    return $c;
}

/**
 * Compare les engagements lus à l'agenda en place et écrit les lignes de l'import.
 * Rend le bilan : décompte par nature et par remarque du contrôle.
 */
function import_comparer(int $import_id, array $donnees): array
{
    $reference = $donnees['date_extrait'] ?: aujourdhui();
    $agenda = bd_lignes("SELECT r.*, c.type, c.titre_fr, c.ensemble_fr, c.ensemble_en, c.ensemble_id, c.salle_fr AS c_salle_fr, c.salle_en AS c_salle_en,
                                c.ville_fr AS c_ville_fr, c.ville_en AS c_ville_en, c.pays_fr AS c_pays_fr, c.pays_en AS c_pays_en
                         FROM representations r JOIN concerts c ON c.id = r.concert_id WHERE r.statut <> 'annulee' ORDER BY r.date, r.heure, r.id");
    $par_date = [];
    foreach ($agenda as $r) $par_date[$r['date']][] = $r;
    $prises = [];                 // représentations de l'agenda déjà rapprochées
    $lignes = [];
    $vues = [];                   // date + ensemble déjà vus dans l'extrait : doublons
    $programmes = [];             // concerts dont le programme a déjà été comparé
    $dates = [];
    $annulations_sans_objet = 0;  // annulations annoncées pour des dates que l'agenda ne porte pas

    foreach ($donnees['engagements'] as $i => $e) {
        foreach ($e['representations'] as $r) {
            $p = ['titre' => $e['titre'], 'type' => $e['type'], 'ensemble' => $e['ensemble'], 'mentions' => $e['mentions'],
                'programme' => $e['programme'], 'solistes' => $e['solistes']] + $r;
            $dates[] = $r['date'];
            $controles = import_controles($p, $reference);
            $cle = $r['date'] . '|' . ($r['heure'] ?? '') . '|' . normaliser($e['ensemble'] ?: $e['titre']);
            if (isset($vues[$cle])) $controles[] = 'doublon';
            $vues[$cle] = true;

            $meme_jour = array_values(array_filter($par_date[$r['date']] ?? [], fn($x) => !isset($prises[$x['id']])));
            $trouvee = null;
            foreach ($meme_jour as $x) {
                if (ensemble_correspond($e['ensemble'], $e['titre'], $x) && ($x['heure'] === $r['heure'] || !$x['heure'] || !$r['heure'] || count($meme_jour) === 1)) { $trouvee = $x; break; }
            }
            if (!$trouvee) {
                foreach ($meme_jour as $x) {
                    if (ensemble_correspond($e['ensemble'], $e['titre'], $x)) { $trouvee = $x; break; }
                }
            }
            $ligne = ['engagement' => $i, 'propose' => $p, 'controles' => $controles, 'avant' => null, 'candidats' => null, 'concert_id' => null, 'representation_id' => null];
            if (!empty($r['annulee'])) {
                // le document annonce une annulation : la date de l'agenda est proposée à l'annulation ; une date
                // que l'agenda ne connaît pas (ou qu'il a déjà annulée) n'y entre pas pour autant
                if (!$trouvee) { $annulations_sans_objet++; continue; }
                $prises[$trouvee['id']] = true;
                $lignes[] = ['engagement' => $i, 'nature' => 'annulee', 'concert_id' => (int) $trouvee['concert_id'], 'representation_id' => (int) $trouvee['id'],
                    'propose' => ['titre' => $trouvee['titre_fr'], 'ensemble' => $trouvee['ensemble_fr'], 'date' => $r['date'], 'heure' => $trouvee['heure'], 'motif' => $r['motif']],
                    'avant' => ['statut' => $trouvee['statut']], 'controles' => [], 'candidats' => null];
                continue;
            }
            if ($trouvee) {
                $prises[$trouvee['id']] = true;
                $ligne['concert_id'] = (int) $trouvee['concert_id'];
                $ligne['representation_id'] = (int) $trouvee['id'];
                // ce qui change : l'extrait ne vide jamais un champ, il le complète ou le remplace
                $avant = [];
                $champs = [];
                if ($r['heure'] && $r['heure'] !== $trouvee['heure']) { $avant['heure'] = $trouvee['heure']; $champs[] = 'heure'; }
                foreach (['salle', 'ville', 'pays'] as $k) {
                    if ($r[$k] === '') continue;
                    $connus = array_filter([$trouvee[$k . '_fr'], $trouvee[$k . '_en'], $trouvee['c_' . $k . '_fr'], $trouvee['c_' . $k . '_en']], 'strlen');
                    $pareil = false;
                    foreach ($connus as $n) if (noms_proches($r[$k], $n)) { $pareil = true; break; }
                    if (!$pareil) { $avant[$k] = $trouvee[$k . '_fr'] !== '' ? $trouvee[$k . '_fr'] : $trouvee['c_' . $k . '_fr']; $champs[] = $k; }
                }
                if ($champs) {
                    $ligne['nature'] = 'modifiee';
                    $ligne['avant'] = $avant;
                    $ligne['propose']['champs'] = $champs;
                } else {
                    $ligne['nature'] = 'inchangee';
                    $ligne['controles'] = [];
                }
                // programme : comparé une fois par concert, sur les compositeurs
                if ($e['programme'] && !isset($programmes[$trouvee['concert_id']])) {
                    $programmes[$trouvee['concert_id']] = true;
                    $en_place = bd_lignes('SELECT compositeur_fr, oeuvre_fr, mention_fr FROM programme WHERE concert_id = ? ORDER BY ordre, id', [$trouvee['concert_id']]);
                    $noms = fn(array $liste, string $k) => array_values(array_unique(array_filter(array_map(function ($x) use ($k) {
                        $mots = explode(' ', normaliser((string) $x[$k]));
                        return end($mots);
                    }, $liste), 'strlen')));
                    $a = $noms($en_place, 'compositeur_fr');
                    $b = $noms($e['programme'], 'compositeur');
                    sort($a);
                    sort($b);
                    if ($a !== $b) {
                        $lignes[] = ['engagement' => $i, 'nature' => 'programme', 'concert_id' => (int) $trouvee['concert_id'], 'representation_id' => null,
                            'propose' => ['titre' => $trouvee['titre_fr'], 'date' => $r['date'], 'programme' => $e['programme']],
                            'avant' => ['programme' => $en_place], 'controles' => [], 'candidats' => null];
                    }
                }
            } else {
                // même jour avec un autre ensemble, ou même ensemble à quelques jours : à trancher à la main
                $candidats = array_map(fn($x) => (int) $x['id'], $meme_jour);
                if (!$candidats) {
                    foreach ($agenda as $x) {
                        if (isset($prises[$x['id']])) continue;
                        $ecart = abs((strtotime($x['date']) - strtotime($r['date'])) / 86400);
                        if ($ecart <= 3 && ensemble_correspond($e['ensemble'], $e['titre'], $x)) $candidats[] = (int) $x['id'];
                    }
                }
                $ligne['nature'] = $candidats ? 'douteuse' : 'nouvelle';
                $ligne['candidats'] = $candidats ?: null;
            }
            $lignes[] = $ligne;
        }
    }

    // dates disparues : à venir à la date de l'extrait, dans la période qu'il couvre, et absentes
    if ($dates) {
        $premiere = max(min($dates), $reference);
        $derniere = max($dates);
        foreach ($agenda as $x) {
            if (isset($prises[$x['id']]) || $x['type'] === 'disque' || $x['date'] < $premiere || $x['date'] > $derniere) continue;
            // un cas douteux la cite peut-être déjà : elle n'est pas « disparue » tant qu'il n'est pas tranché
            $citee = false;
            foreach ($lignes as $l) if (in_array((int) $x['id'], $l['candidats'] ?? [], true)) { $citee = true; break; }
            if ($citee) continue;
            $lignes[] = ['engagement' => -1, 'nature' => 'disparue', 'concert_id' => (int) $x['concert_id'], 'representation_id' => (int) $x['id'],
                'propose' => ['titre' => $x['titre_fr'], 'ensemble' => $x['ensemble_fr'], 'date' => $x['date'], 'heure' => $x['heure']],
                'avant' => ['statut' => $x['statut']], 'controles' => [], 'candidats' => null];
        }
    }

    $bilan = ['engagements' => count($donnees['engagements']), 'representations' => count($dates), 'natures' => [], 'controles' => [], 'annulations_sans_objet' => $annulations_sans_objet];
    bd_supprimer('import_lignes', ['import_id' => $import_id]);
    $json = fn($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach ($lignes as $ordre => $l) {
        $bilan['natures'][$l['nature']] = ($bilan['natures'][$l['nature']] ?? 0) + 1;
        foreach ($l['controles'] as $c) $bilan['controles'][$c] = ($bilan['controles'][$c] ?? 0) + 1;
        bd_inserer('import_lignes', [
            'import_id' => $import_id, 'ordre' => $ordre, 'engagement' => $l['engagement'], 'nature' => $l['nature'],
            'concert_id' => $l['concert_id'], 'representation_id' => $l['representation_id'],
            'propose' => $json($l['propose']), 'avant' => $json($l['avant']), 'controles' => $json($l['controles']), 'candidats' => $json($l['candidats']),
            'decision' => $l['nature'] === 'inchangee' ? 'validee' : 'en_attente',
        ]);
    }
    return $bilan;
}

// ---------- 5. Validation ----------

function import_ligne_lire(int $id): ?array
{
    $l = bd_ligne('SELECT * FROM import_lignes WHERE id = ?', [$id]);
    if (!$l) return null;
    foreach (['propose', 'avant', 'controles', 'candidats'] as $k) $l[$k] = json_decode((string) $l[$k], true) ?: [];
    return $l;
}

function import_lignes(int $import_id): array
{
    $lignes = [];
    foreach (bd_colonne('SELECT id FROM import_lignes WHERE import_id = ? ORDER BY ordre, id', [$import_id]) as $id) $lignes[] = import_ligne_lire((int) $id);
    return $lignes;
}

/** Corrige ce que propose une ligne avant de la valider. Rend un message d'erreur, ou null. */
function import_ligne_corriger(int $id, array $champs): ?string
{
    $l = import_ligne_lire($id);
    if (!$l || $l['decision'] !== 'en_attente' || !in_array($l['nature'], ['nouvelle', 'modifiee', 'douteuse'], true)) return 'Cette ligne ne se corrige plus.';
    $p = $l['propose'];
    $date = trim((string) ($champs['date'] ?? $p['date']));
    if (!date_valide($date)) return 'Date illisible (AAAA-MM-JJ).';
    $heure = heure_lire((string) ($champs['heure'] ?? ''));
    if ($heure === null) return 'Heure illisible (19:30).';
    $p['date'] = $date;
    $p['heure'] = $heure;
    foreach (['salle', 'ville', 'pays', 'titre', 'ensemble'] as $k) {
        if (array_key_exists($k, $champs)) $p[$k] = mb_substr(trim((string) $champs[$k]), 0, 255);
    }
    $imp = bd_ligne('SELECT date_extrait FROM imports WHERE id = ?', [$l['import_id']]);
    $controles = array_values(array_unique(array_merge(import_controles($p, $imp['date_extrait'] ?: aujourdhui()), array_intersect($l['controles'], ['doublon']))));
    bd_modifier('import_lignes', ['propose' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'controles' => json_encode($controles)], ['id' => $id]);
    journaliser('modification', 'import', (int) $l['import_id'], 'Ligne corrigée avant validation', array_intersect_key($l['propose'], $p), $p);
    return null;
}

function import_decider(array $l, string $decision, array $plus = []): void
{
    $c = compte();
    bd_modifier('import_lignes', ['decision' => $decision, 'decide_par' => $c['id'] ?? null, 'decide_le' => date('Y-m-d H:i:s')] + $plus, ['id' => $l['id']]);
    if (!bd_valeur("SELECT COUNT(*) FROM import_lignes WHERE import_id = ? AND decision = 'en_attente'", [$l['import_id']])) {
        bd_modifier('imports', ['statut' => 'termine', 'termine_le' => date('Y-m-d H:i:s')], ['id' => $l['import_id']]);
    }
}

function import_ligne_refuser(int $id): ?string
{
    $l = import_ligne_lire($id);
    if (!$l || $l['decision'] !== 'en_attente') return 'Cette ligne est déjà traitée.';
    import_decider($l, 'refusee');
    journaliser('validation', 'import', (int) $l['import_id'], 'Refusé : ' . NATURES_IMPORT[$l['nature']] . ', ' . date_fr($l['propose']['date'] ?? ''), null, ['decision' => 'refusee']);
    return null;
}

/**
 * Valide une ligne : c'est ici, et seulement ici, que l'extrait entre dans l'agenda.
 * $choix, pour un cas douteux : 'nouvelle', ou l'identifiant de la représentation à mettre à jour.
 * Rend un message d'erreur, ou null.
 */
function import_ligne_valider(int $id, string $choix = ''): ?string
{
    $l = import_ligne_lire($id);
    if (!$l || $l['decision'] !== 'en_attente') return 'Cette ligne est déjà traitée.';
    $p = $l['propose'];
    $imp = bd_ligne('SELECT * FROM imports WHERE id = ?', [$l['import_id']]);
    $extrait = 'extrait de l\'agence' . ($imp['date_extrait'] ? ' du ' . date_fr($imp['date_extrait']) : ' déposé le ' . date('d/m/Y', strtotime($imp['cree_le'])));

    return bd_transaction(function () use ($l, $p, $choix, $extrait) {
        $nature = $l['nature'];
        $rep_id = $l['representation_id'] ? (int) $l['representation_id'] : null;
        if ($nature === 'douteuse') {
            if ($choix === 'nouvelle') $nature = 'nouvelle';
            elseif (ctype_digit($choix) && in_array((int) $choix, $l['candidats'], true)) { $nature = 'modifiee'; $rep_id = (int) $choix; $p['champs'] = ['date', 'heure', 'salle', 'ville', 'pays']; }
            else return 'Pour un cas douteux, dites si c\'est une nouvelle date ou laquelle des dates de l\'agenda elle remplace.';
        }
        $plus = [];
        switch ($nature) {
            case 'nouvelle':
                // le concert de cet engagement : celui d'une autre de ses dates s'il est déjà dans l'agenda
                $concert_id = bd_valeur("SELECT concert_id FROM import_lignes WHERE import_id = ? AND engagement = ? AND concert_id IS NOT NULL
                                         AND nature IN ('nouvelle', 'modifiee', 'inchangee', 'douteuse') AND decision = 'validee' LIMIT 1", [$l['import_id'], $l['engagement']]);
                if ($concert_id && ($c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$concert_id]))) {
                    $r = ['concert_id' => (int) $concert_id, 'date' => $p['date'], 'heure' => $p['heure'] ?: null, 'origine' => 'import'];
                    foreach (['salle', 'ville', 'pays'] as $k) {
                        if ($p[$k] !== '' && !noms_proches($p[$k], $c[$k . '_fr'])) $r[$k . '_fr'] = $p[$k];
                    }
                    $plus['representation_id'] = bd_inserer('representations', $r);
                    journaliser('creation', 'concert', (int) $concert_id, $c['titre_fr'] . ', ' . date_fr($p['date']) . ' (' . $extrait . ')', null, ['date' => $p['date'], 'heure' => $p['heure'], 'statut' => 'connue']);
                } else {
                    [$concert_id, $erreurs] = concert_enregistrer(null, [
                        'type' => $p['type'],
                        'titre_fr' => $p['titre'] !== '' ? $p['titre'] : ($p['ensemble'] !== '' ? $p['ensemble'] : 'Engagement du ' . date_fr($p['date'])),
                        'ensemble_fr' => $p['ensemble'],
                        'salle_fr' => $p['salle'], 'ville_fr' => $p['ville'], 'pays_fr' => $p['pays'],
                        'mentions_fr' => $p['mentions'],
                        'notes_internes' => 'Créé d\'après l\'' . $extrait . '.',
                        'reps' => [['date' => $p['date'], 'heure' => (string) $p['heure']]],
                        'prog' => array_map(fn($x) => ['compositeur_fr' => $x['compositeur'], 'oeuvre_fr' => $x['oeuvre'], 'mention_fr' => $x['mention']], $p['programme'] ?? []),
                        'avec' => array_map(fn($x) => ['nom' => $x['nom'], 'role_fr' => $x['role']], $p['solistes'] ?? []),
                    ]);
                    if ($erreurs) return implode(' ', $erreurs);
                    bd_modifier('representations', ['origine' => 'import'], ['concert_id' => $concert_id]);
                    $plus['representation_id'] = (int) bd_valeur('SELECT id FROM representations WHERE concert_id = ? LIMIT 1', [$concert_id]);
                }
                $plus['concert_id'] = (int) $concert_id;
                break;

            case 'modifiee':
                $r = bd_ligne('SELECT r.*, c.titre_fr FROM representations r JOIN concerts c ON c.id = r.concert_id WHERE r.id = ?', [$rep_id]);
                if (!$r) return 'La représentation à modifier n\'existe plus.';
                $maj = [];
                foreach ($p['champs'] ?? [] as $k) {
                    if ($k === 'date') $maj['date'] = $p['date'];
                    elseif ($k === 'heure') { if ($p['heure']) $maj['heure'] = $p['heure']; }
                    elseif ($p[$k] !== '') $maj[$k . '_fr'] = $p[$k];
                }
                [$a, $b] = differences(array_intersect_key($r, $maj), $maj);
                if ($b) {
                    bd_modifier('representations', $b, ['id' => $rep_id]);
                    journaliser('modification', 'concert', (int) $r['concert_id'], $r['titre_fr'] . ', ' . date_fr($r['date']) . ' (' . $extrait . ')', $a, $b);
                }
                $plus = ['representation_id' => $rep_id, 'concert_id' => (int) $r['concert_id']];
                break;

            case 'programme':
                $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$l['concert_id']]);
                if (!$c) return 'Le concert n\'existe plus.';
                $colonnes = 'compositeur_fr, compositeur_en, oeuvre_fr, oeuvre_en, mention_fr, mention_en';
                $avant = bd_lignes("SELECT $colonnes FROM programme WHERE concert_id = ? ORDER BY ordre, id", [$c['id']]);
                bd_supprimer('programme', ['concert_id' => $c['id']]);
                $apres = [];
                foreach ($p['programme'] as $i => $x) {
                    $ligne = ['compositeur_fr' => $x['compositeur'], 'oeuvre_fr' => $x['oeuvre'], 'mention_fr' => $x['mention']];
                    bd_inserer('programme', $ligne + ['concert_id' => $c['id'], 'ordre' => $i, 'compositeur_id' => fiche_trouver($x['compositeur'], ['compositeur'])]);
                    $apres[] = $ligne;
                }
                journaliser('modification', 'concert', (int) $c['id'], $c['titre_fr'] . ', programme (' . $extrait . ')', ['programme' => $avant], ['programme' => $apres]);
                break;

            case 'disparue':
                $erreur = statut_changer((int) $rep_id, 'annulee', ['raison' => 'Absente de l\'' . $extrait . '.']);
                if ($erreur) return $erreur;
                break;

            case 'annulee':
                $erreur = statut_changer((int) $rep_id, 'annulee', ['raison' => 'Annulation annoncée dans l\'' . $extrait . (($p['motif'] ?? '') !== '' ? ' : ' . $p['motif'] : '.')]);
                if ($erreur) return $erreur;
                break;

            default:
                return 'Rien à valider sur cette ligne.';
        }
        import_decider($l, 'validee', $plus);
        journaliser('validation', 'import', (int) $l['import_id'], 'Validé : ' . NATURES_IMPORT[$l['nature']] . ', ' . date_fr($p['date'] ?? ''), null, ['decision' => 'validee']);
        return null;
    });
}

/** Date du dernier extrait lu jusqu'au bout, ou null. */
function import_dernier(): ?array
{
    return bd_ligne("SELECT * FROM imports WHERE statut IN ('a_valider', 'termine') ORDER BY id DESC LIMIT 1");
}
