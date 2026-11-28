<?php
/**
 * Agenda : concerts, représentations, statuts.
 *
 * Le statut se règle par représentation. « Publiée » est posé par la publication, jamais à la
 * main. Seules les représentations annoncées ou publiées sortent dans les fichiers du site.
 * Les dates et les heures sont celles de la salle : elles ne sont jamais converties.
 *
 * Un concert est un objet à part entière, avec sa fiche : une production et toutes ses dates. Il
 * se dépublie d'un geste sans rien perdre (colonne publie) : aucune de ses dates ne sort alors,
 * quel que soit leur statut, et il revient tel quel quand on le republie. « À la une » (colonne
 * temps_fort) est une marque éditoriale indépendante de la publication. Il peut être associé à
 * plusieurs orchestres (inclus/orchestres.php) et, plus tard, à des articles de presse.
 */

defined('PUPITRE') or exit;

/**
 * Natures d'un événement de l'agenda. Un concert est un concert : l'écran ne demande plus de type.
 * « Tournée » se déduit des dates (concert_nature()) ; « opéra » et « disque » ne viennent que de
 * l'extrait de l'agence ou d'une fiche d'avant, et se gardent : le site les présente à sa façon
 * (série de représentations, parution d'un disque).
 */
const TYPES_CONCERT = ['concert' => 'Concert', 'opera' => 'Opéra', 'tournee' => 'Tournée', 'disque' => 'Disque'];

const STATUTS = [
    'connue' => 'Connue',
    'embargo' => 'Sous embargo',
    'annoncee' => 'Annoncée',
    'publiee' => 'Publiée',
    'annulee' => 'Annulée ou reportée',
];

const STATUTS_SENS = [
    'connue' => 'Dans l\'extrait de l\'agence, pas encore annoncée par l\'organisateur. Ne sort pas sur le site.',
    'embargo' => 'Annonce prévue à une date fixe. Ne sort pas sur le site ; alerte à l\'échéance.',
    'annoncee' => 'Annoncée par l\'organisateur. Sortira sur le site à la prochaine publication.',
    'publiee' => 'Présente dans la dernière publication du site.',
    'annulee' => 'Retirée du site à la prochaine publication.',
];

/** Statuts qu'une personne peut poser à la main depuis le statut actuel. « publiee » n'y figure jamais. */
function statuts_possibles(string $actuel): array
{
    return match ($actuel) {
        'connue' => ['embargo', 'annoncee', 'annulee'],
        'embargo' => ['connue', 'annoncee', 'annulee'],
        'annoncee' => ['connue', 'embargo', 'annulee'],
        'publiee' => ['connue', 'annulee'],
        'annulee' => ['connue', 'embargo', 'annoncee'],
        default => [],
    };
}

function concert_lire(int $id): ?array
{
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$id]);
    if (!$c) return null;
    $c['representations'] = bd_lignes('SELECT * FROM representations WHERE concert_id = ? ORDER BY date, heure, id', [$id]);
    $c['programme'] = bd_lignes('SELECT * FROM programme WHERE concert_id = ? ORDER BY ordre, id', [$id]);
    $c['participants'] = bd_lignes('SELECT * FROM participants WHERE concert_id = ? ORDER BY ordre, id', [$id]);
    $c['orchestres'] = concert_orchestres($id);
    foreach ($c['participants'] as &$p) {
        $p['fiches'] = bd_lignes('SELECT a.id, a.nom FROM participants_fiches pf JOIN annuaire a ON a.id = pf.fiche_id WHERE pf.participant_id = ? ORDER BY a.nom', [$p['id']]);
    }
    unset($p);
    return $c;
}

/** Lieu d'une représentation, dans la langue de rédaction : le sien (tournée), sinon celui du concert. */
function lieu(array $r, array $c, string $champ): string
{
    $k = $champ . '_' . langue_reference();
    return (string) (($r[$k] ?? '') !== '' ? $r[$k] : ($c[$k] ?? ''));
}

/**
 * Lieux distincts où se tiennent les représentations d'un concert : la ville et la salle de chaque date
 * (les siennes propres, sinon celles du concert). Les dates annulées ne comptent pas.
 */
function concert_lieux(array $concert, array $reps): array
{
    $lieux = [];
    foreach ($reps as $r) {
        if (($r['statut'] ?? '') === 'annulee') continue;
        $ville = normaliser((string) (($r['ville_en'] ?? '') !== '' ? $r['ville_en'] : ($concert['ville_en'] ?? '')));
        $salle = normaliser((string) (($r['salle_en'] ?? '') !== '' ? $r['salle_en'] : ($concert['salle_en'] ?? '')));
        if ($ville . $salle !== '') $lieux[$ville . '|' . $salle] = true;
    }
    return array_keys($lieux);
}

/**
 * Nature d'un concert, déduite de ses dates. Un concert est un concert ; dès que ses représentations se
 * tiennent dans des lieux différents (une autre ville, ou une autre salle), c'est une tournée : le site
 * donne alors son lieu à chaque date. Une nature particulière déjà posée (opéra, disque), ou donnée par
 * l'extrait de l'agence ($voulue), est gardée tant que les dates n'en font pas une tournée.
 */
function concert_nature(?int $id, array $concert, array $reps, string $voulue = ''): string
{
    if (count(concert_lieux($concert, $reps)) >= 2) return 'tournee';
    $actuelle = $id ? (string) bd_valeur('SELECT type FROM concerts WHERE id = ?', [$id]) : '';
    foreach ([$voulue, $actuelle] as $t) if (in_array($t, ['opera', 'disque'], true)) return $t;
    return 'concert';
}

/**
 * Change le statut d'une représentation. $champs : lien_source, embargo_le, raison.
 * Rend un message d'erreur, ou null si le changement est fait.
 */
function statut_changer(int $rep_id, string $nouveau, array $champs = []): ?string
{
    $r = bd_ligne('SELECT r.*, c.titre_en FROM representations r JOIN concerts c ON c.id = r.concert_id WHERE r.id = ?', [$rep_id]);
    if (!$r) return 'Représentation introuvable.';
    if ($nouveau === 'publiee') return '« Publiée » est posé par la publication, jamais à la main.';
    if ($nouveau === $r['statut']) return null;
    if (!in_array($nouveau, statuts_possibles($r['statut']), true)) {
        return 'Une représentation « ' . STATUTS[$r['statut']] . ' » ne peut pas passer à « ' . (STATUTS[$nouveau] ?? $nouveau) . ' ».';
    }
    $maj = ['statut' => $nouveau];
    if ($nouveau === 'annoncee') {
        $lien = trim((string) ($champs['lien_source'] ?? '')) ?: $r['lien_source'];
        if ($lien === '' || !adresse_valide($lien)) return 'Pour annoncer une date, le lien source est obligatoire : la page où l\'organisateur l\'annonce.';
        $maj['lien_source'] = $lien;
        $maj['embargo_le'] = null;
        $maj['raison'] = '';
    } elseif ($nouveau === 'embargo') {
        $date = trim((string) ($champs['embargo_le'] ?? '')) ?: (string) $r['embargo_le'];
        if (!date_valide($date)) return 'Un embargo demande la date à laquelle l\'annonce est prévue.';
        $maj['embargo_le'] = $date;
        $maj['raison'] = '';
    } elseif ($nouveau === 'annulee') {
        $raison = trim((string) ($champs['raison'] ?? ''));
        if ($raison === '') return 'Pour annuler ou reporter une date, la raison est obligatoire.';
        $maj['raison'] = mb_substr($raison, 0, 500);
    } else {
        $maj['embargo_le'] = null;
        $maj['raison'] = '';
    }
    $avant = array_intersect_key($r, $maj);
    bd_modifier('representations', $maj, ['id' => $rep_id]);
    journaliser('statut', 'concert', (int) $r['concert_id'], $r['titre_en'] . ', ' . date_fr($r['date']), $avant, $maj);
    return null;
}

/**
 * Ce qui manque à une fiche pour annoncer le concert. Rend une liste de libellés, vide si tout y est.
 * $c : un concert lu par concert_lire().
 */
function concert_manques(array $c): array
{
    $m = [];
    $disque = ($c['type'] === 'disque');
    $reps = array_values(array_filter($c['representations'] ?? [], fn($r) => $r['statut'] !== 'annulee'));
    if (trim($c['titre_en']) === '') $m[] = 'titre';
    if (!$reps) $m[] = 'une représentation';
    if (!$disque) {
        if (trim($c['ensemble_en']) === '') $m[] = 'ensemble';
        foreach (['salle' => 'salle', 'ville' => 'ville', 'pays' => 'pays'] as $champ => $libelle) {
            foreach ($reps as $r) {
                if (lieu($r, $c, $champ) === '') { $m[] = $libelle; break; }
            }
        }
        foreach ($reps as $r) {
            if (!$r['heure']) { $m[] = 'heure'; break; }
        }
        if (empty($c['programme'])) $m[] = 'programme';
    }
    foreach ($reps as $r) {
        if ($r['lien_source'] === '') { $m[] = 'lien source (page de l\'organisateur)'; break; }
    }
    return $m;
}

/** Ce qui manque pour que la fiche soit complète : ce qu'il faut pour annoncer, plus un lien pour le public. */
function concert_incomplet(array $c): array
{
    $m = concert_manques($c);
    $lien = $c['lien_billetterie'] !== '' || $c['page_organisateur'] !== ''
        || array_filter($c['representations'] ?? [], fn($r) => $r['lien_billetterie'] !== '');
    if (!$lien) $m[] = 'lien de billetterie ou page de l\'organisateur';
    return $m;
}

/**
 * Identifiant de l'événement dans les fichiers du site, unique : « au-revoir-pierre ».
 * Il ne porte jamais de date : celle de la première représentation peut ne pas être annoncée,
 * et l'identifiant, lui, sort sur le site dès qu'une autre date l'est. (Les concerts repris du
 * site gardent l'identifiant qu'ils y avaient.)
 */
function concert_cle(string $titre, ?int $sauf = null): string
{
    $base = slug($titre, 50) ?: 'concert';
    $cle = $base;
    $n = 2;
    while (bd_valeur('SELECT id FROM concerts WHERE cle = ? AND id <> ?', [$cle, $sauf ?? 0])) $cle = $base . '-' . $n++;
    return $cle;
}

/**
 * Heure de l'artiste pour une représentation : l'heure de la salle lue dans le fuseau de
 * l'artiste (réglage). Calcul d'affichage seulement ; '' si le fuseau de la salle est inconnu.
 */
function heure_artiste(string $date, ?string $heure, string $fuseau_salle): string
{
    if (!$heure || $fuseau_salle === '') return '';
    try {
        $la = new DateTimeImmutable($date . ' ' . $heure . ':00', new DateTimeZone($fuseau_salle));
        $ici = $la->setTimezone(new DateTimeZone(reglage('fuseau') ?: 'Europe/Paris'));
        $s = heure_fr($ici->format('H:i'));
        if ($ici->format('Y-m-d') !== $date) $s .= ' (le ' . date_fr($ici->format('Y-m-d'), false, false) . ')';
        return $s;
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Représentations de l'agenda, avec leur concert. Filtres : statut, maison, type, du, au, q.
 * Chaque ligne porte lieu_salle, lieu_ville, lieu_pays (ceux de la date, sinon ceux du concert).
 */
function agenda_representations(array $filtres = []): array
{
    $ou = ['1 = 1'];
    $p = [];
    if (!empty($filtres['statut'])) { $ou[] = 'r.statut = ?'; $p[] = $filtres['statut']; }
    if (!empty($filtres['sauf_annulees'])) $ou[] = "r.statut <> 'annulee'";
    if (!empty($filtres['maison'])) { $ou[] = 'c.maison_id = ?'; $p[] = (int) $filtres['maison']; }
    if (!empty($filtres['type'])) { $ou[] = 'c.type = ?'; $p[] = $filtres['type']; }
    if (!empty($filtres['du'])) { $ou[] = 'r.date >= ?'; $p[] = $filtres['du']; }
    if (!empty($filtres['au'])) { $ou[] = 'r.date <= ?'; $p[] = $filtres['au']; }
    if (!empty($filtres['q'])) {
        $ou[] = '(c.titre_en LIKE ? OR c.ensemble_en LIKE ? OR c.ville_en LIKE ? OR r.ville_en LIKE ? OR c.salle_en LIKE ?)';
        $q = '%' . $filtres['q'] . '%';
        array_push($p, $q, $q, $q, $q, $q);
    }
    $sql = "SELECT r.*, c.cle, c.type, c.titre_en, c.ensemble_en, c.maison_id, c.fuseau, c.temps_fort, c.publie,
                   COALESCE(NULLIF(r.salle_en, ''), c.salle_en) AS lieu_salle,
                   COALESCE(NULLIF(r.ville_en, ''), c.ville_en) AS lieu_ville,
                   COALESCE(NULLIF(r.pays_en, ''), c.pays_en) AS lieu_pays
            FROM representations r JOIN concerts c ON c.id = r.concert_id
            WHERE " . implode(' AND ', $ou) . ' ORDER BY r.date, r.heure, r.id';
    return bd_lignes($sql, $p);
}

/** Doublons probables : le même jour, deux concerts différents avec le même ensemble ou le même titre. */
function agenda_doublons(): array
{
    $paires = [];
    $par_date = [];
    foreach (agenda_representations(['sauf_annulees' => true]) as $r) $par_date[$r['date']][] = $r;
    foreach ($par_date as $reps) {
        $n = count($reps);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $reps[$i];
                $b = $reps[$j];
                if ($a['concert_id'] === $b['concert_id']) {
                    if ($a['heure'] === $b['heure']) $paires[] = [$a, $b];
                    continue;
                }
                $meme_ensemble = normaliser($a['ensemble_en']) !== '' && normaliser($a['ensemble_en']) === normaliser($b['ensemble_en']);
                $meme_titre = normaliser($a['titre_en']) !== '' && normaliser($a['titre_en']) === normaliser($b['titre_en']);
                $meme_lieu = normaliser($a['lieu_ville']) === normaliser($b['lieu_ville']);
                if ($meme_titre || ($meme_ensemble && $meme_lieu && $a['heure'] === $b['heure'])) $paires[] = [$a, $b];
            }
        }
    }
    return $paires;
}

// ---------- Enregistrement d'une fiche concert ----------

/** Relie les noms saisis aux fiches de l'annuaire : rien n'est créé ici. */
function concert_relier(array &$d): void
{
    $d['ensemble_id'] = fiche_trouver($d['ensemble_en'], ['orchestre', 'ensemble']);
    $d['salle_id'] = fiche_trouver($d['salle_en'], ['salle', 'festival']);
}

/**
 * Crée ou modifie un concert à partir d'un formulaire. Rend [identifiant, erreurs].
 * $f : champs du concert, plus reps[], prog[], avec[] (listes de lignes).
 * Les statuts ne se changent pas ici (statut_changer).
 */
function concert_enregistrer(?int $id, array $f): array
{
    $t = fn(string $k, int $max = 255) => mb_substr(trim((string) ($f[$k] ?? '')), 0, $max);
    $erreurs = [];
    // les champs traduisibles arrivent dans la langue de rédaction (titre_en…) ; leurs traductions se règlent sur l'écran « Traductions »
    $d = recu_tr($f, 'titre', 255) + recu_tr($f, 'ensemble', 255) + recu_tr($f, 'salle', 255)
        + recu_tr($f, 'ville', 120) + recu_tr($f, 'pays', 120) + recu_tr($f, 'mentions', 2000) + recu_tr($f, 'description', 5000)
        + [
            'maison_id' => ctype_digit((string) ($f['maison_id'] ?? '')) ? (int) $f['maison_id'] : null,
            'fuseau' => in_array($f['fuseau'] ?? '', timezone_identifiers_list(), true) ? $f['fuseau'] : '',
            'lien_billetterie' => $t('lien_billetterie', 500), 'page_organisateur' => $t('page_organisateur', 500),
            'temps_fort' => empty($f['temps_fort']) ? 0 : 1,
            'notes_internes' => $t('notes_internes', 5000),
        ];
    // le contact « communication » : la personne qui partagera photos et vidéos (zone de dépôt, inclus/depot.php)
    if (bd_colonne_existe('concerts', 'contact_com_email')) {
        $d += ['contact_com_nom' => $t('contact_com_nom', 190), 'contact_com_email' => mb_strtolower($t('contact_com_email', 190)), 'contact_com_langue' => in_array($f['contact_com_langue'] ?? '', ['fr', 'en'], true) ? $f['contact_com_langue'] : 'en'];
        if ($d['contact_com_email'] !== '' && !filter_var($d['contact_com_email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = 'Contact communication : l\'adresse e-mail est incomplète.';
    }
    if ($d['titre_en'] === '') $erreurs[] = 'Le titre est obligatoire.';
    foreach (['lien_billetterie' => 'Le lien de billetterie', 'page_organisateur' => 'La page de l\'organisateur'] as $k => $nom) {
        if (!adresse_valide($d[$k])) $erreurs[] = $nom . ' doit être une adresse complète (https://…).';
    }
    concert_relier($d);
    // la salle choisie dans l'annuaire apporte sa ville, son pays et son fuseau quand ils manquent
    if ($d['salle_id'] && ($salle = fiche_lire($d['salle_id']))) {
        foreach (['ville_en', 'pays_en', 'fuseau'] as $k) {
            if (($d[$k] ?? '') === '' && ($salle[$k] ?? '') !== '') $d[$k] = $salle[$k];
        }
    }

    // représentations
    $reps = [];
    foreach (array_values($f['reps'] ?? []) as $i => $r) {
        $date = trim((string) ($r['date'] ?? ''));
        if ($date === '' && trim(implode('', array_map('strval', $r))) === trim((string) ($r['id'] ?? ''))) continue;   // ligne vide
        $heure = heure_lire((string) ($r['heure'] ?? ''));
        if (!date_valide($date)) { $erreurs[] = 'Représentation ' . ($i + 1) . ' : date illisible (AAAA-MM-JJ).'; continue; }
        if ($heure === null) { $erreurs[] = 'Représentation du ' . date_fr($date) . ' : heure illisible (19:30).'; continue; }
        $l = ['id' => ctype_digit((string) ($r['id'] ?? '')) ? (int) $r['id'] : null, 'date' => $date, 'heure' => $heure === '' ? null : $heure]
            + recu_tr($r, 'salle', 255) + recu_tr($r, 'ville', 120) + recu_tr($r, 'pays', 120);
        foreach (['lien_billetterie' => 500, 'lien_source' => 500] as $k => $max) {
            $l[$k] = mb_substr(trim((string) ($r[$k] ?? '')), 0, $max);
        }
        foreach (['lien_billetterie', 'lien_source'] as $k) {
            if (!adresse_valide($l[$k])) $erreurs[] = 'Représentation du ' . date_fr($date) . ' : adresse incomplète (https://…).';
        }
        $reps[] = $l;
    }
    if (!$reps && $id === null) $erreurs[] = 'Indiquez au moins une représentation, avec sa date.';

    // programme et participants
    $id_ligne = fn(array $p) => ctype_digit((string) ($p['id'] ?? '')) ? (int) $p['id'] : null;
    $prog = [];
    foreach (array_values($f['prog'] ?? []) as $p) {
        $l = recu_tr($p, 'compositeur', 255) + recu_tr($p, 'oeuvre', 500) + recu_tr($p, 'mention', 255);
        if ($l['compositeur_en'] === '' && $l['oeuvre_en'] === '') continue;
        $prog[] = $l + ['id' => $id_ligne($p)];
    }
    $avec = [];
    foreach (array_values($f['avec'] ?? []) as $p) {
        $l = ['nom' => mb_substr(trim((string) ($p['nom'] ?? '')), 0, 255)] + recu_tr($p, 'role', 255);
        if ($l['nom'] !== '') $avec[] = $l + ['id' => $id_ligne($p)];
    }
    if ($erreurs) return [$id, $erreurs];

    return [bd_transaction(function () use ($id, $d, $reps, $prog, $avec, $f) {
        if ($d['fuseau'] === '') $d['fuseau'] = fuseau_deviner($d['pays_en'], $d['ville_en'], $reps[0]['date'] ?? null);
        $d['type'] = concert_nature($id, $d, $reps, (string) ($f['type'] ?? ''));
        if ($id === null) {
            $d['cle'] = concert_cle($d['titre_en']);
            $id = objet_enregistrer('concerts', null, $d, 'concert', $d['titre_en']);
        } else {
            objet_enregistrer('concerts', $id, $d, 'concert', $d['titre_en']);
        }
        $titre = $d['titre_en'];

        // représentations : celles qui restent sont modifiées, les nouvelles naissent « connues », les autres partent
        $existantes = [];
        foreach (bd_lignes('SELECT * FROM representations WHERE concert_id = ?', [$id]) as $r) $existantes[(int) $r['id']] = $r;
        $gardees = [];
        foreach ($reps as $l) {
            $rid = $l['id'];
            unset($l['id']);
            if ($rid && isset($existantes[$rid])) {
                $gardees[] = $rid;
                // le lien source d'une date déjà annoncée ne s'efface pas par mégarde
                if ($l['lien_source'] === '' && in_array($existantes[$rid]['statut'], ['annoncee', 'publiee'], true)) unset($l['lien_source']);
                [$a, $b] = differences(array_intersect_key($existantes[$rid], $l), $l);
                if ($b) {
                    bd_modifier('representations', $b, ['id' => $rid]);
                    journaliser('modification', 'concert', $id, $titre . ', ' . date_fr($existantes[$rid]['date']), $a, $b);
                }
            } else {
                $l['concert_id'] = $id;
                bd_inserer('representations', $l);
                journaliser('creation', 'concert', $id, $titre . ', ' . date_fr($l['date']), null, ['date' => $l['date'], 'heure' => $l['heure'], 'statut' => 'connue']);
            }
        }
        foreach ($existantes as $rid => $r) {
            if (in_array($rid, $gardees, true)) continue;
            bd_supprimer('representations', ['id' => $rid]);
            journaliser('suppression', 'concert', $id, $titre . ', ' . date_fr($r['date']), ['date' => $r['date'], 'heure' => $r['heure'], 'statut' => $r['statut']], null);
        }

        // programme : chaque œuvre déjà là est modifiée sur place et garde ses traductions
        $sans_id = fn(array $l) => array_diff_key($l, ['id' => 1]);
        $avant = array_map(fn($p) => ligne_langues('programme', $p, ['compositeur', 'oeuvre', 'mention']), bd_lignes('SELECT * FROM programme WHERE concert_id = ? ORDER BY ordre, id', [$id]));
        $apres = array_map($sans_id, $prog);
        if ($avant != $apres) {
            foreach ($prog as &$l) $l['compositeur_id'] = fiche_trouver($l['compositeur_en'], ['compositeur']);
            unset($l);
            lignes_synchroniser('programme', 'concert_id', $id, $prog, ['compositeur', 'oeuvre', 'mention']);
            journaliser('modification', 'concert', $id, $titre . ', programme', ['programme' => $avant], ['programme' => $apres]);
        } else {
            // les liens vers l'annuaire suivent les fiches créées depuis
            foreach (bd_lignes('SELECT id, compositeur_en, compositeur_id FROM programme WHERE concert_id = ?', [$id]) as $p) {
                $fiche = fiche_trouver($p['compositeur_en'], ['compositeur']);
                if ((int) $p['compositeur_id'] !== (int) $fiche) bd_modifier('programme', ['compositeur_id' => $fiche], ['id' => $p['id']]);
            }
        }

        // participants : une ligne peut réunir plusieurs fiches (« A, B »)
        $avant = array_map(fn($p) => ['nom' => $p['nom']] + ligne_langues('participants', $p, ['role']), bd_lignes('SELECT * FROM participants WHERE concert_id = ? ORDER BY ordre, id', [$id]));
        $apres = array_map($sans_id, $avec);
        if ($avant != $apres) {
            lignes_synchroniser('participants', 'concert_id', $id, $avec, ['role']);
            bd_requete('DELETE pf FROM participants_fiches pf LEFT JOIN participants p ON p.id = pf.participant_id WHERE p.id IS NULL');
            journaliser('modification', 'concert', $id, $titre . ', participants', ['participants' => $avant], ['participants' => $apres]);
        }
        participants_relier($id);

        // orchestres associés : ceux que l'écran envoie (s'il les envoie), puis l'ensemble reconnu sans ambiguïté
        if (array_key_exists('orchestres', $f)) concert_orchestres_poser($id, is_array($f['orchestres']) ? $f['orchestres'] : [], $titre);
        concert_orchestre_reconnaitre($id);
        return $id;
    }), []];
}

/** Publie ou dépublie un concert : rien n'est supprimé, ses dates gardent leur statut. */
function concert_publier(int $id, bool $publie): bool
{
    $c = bd_ligne('SELECT id, titre_en, publie FROM concerts WHERE id = ?', [$id]);
    if (!$c || (bool) $c['publie'] === $publie) return false;
    bd_modifier('concerts', ['publie' => $publie ? 1 : 0], ['id' => $id]);
    journaliser('statut', 'concert', $id, $c['titre_en'], ['publie' => (int) $c['publie']], ['publie' => $publie ? 1 : 0]);
    return true;
}

/** Relie chaque participant d'un concert aux fiches de l'annuaire qui portent son nom. */
function participants_relier(int $concert_id): void
{
    foreach (bd_lignes('SELECT id, nom FROM participants WHERE concert_id = ?', [$concert_id]) as $p) {
        $fiches = [];
        $entier = fiche_trouver($p['nom']);
        if ($entier) {
            $fiches[] = $entier;
        } else {
            foreach (array_map('trim', explode(',', $p['nom'])) as $nom) {
                if ($nom !== '' && ($f = fiche_trouver($nom))) $fiches[] = $f;
            }
        }
        $fiches = array_values(array_unique($fiches));
        $actuelles = array_map('intval', bd_colonne('SELECT fiche_id FROM participants_fiches WHERE participant_id = ? ORDER BY fiche_id', [$p['id']]));
        sort($fiches);
        if ($fiches === $actuelles) continue;
        bd_supprimer('participants_fiches', ['participant_id' => $p['id']]);
        foreach ($fiches as $f) bd_inserer('participants_fiches', ['participant_id' => $p['id'], 'fiche_id' => $f]);
    }
}

function concert_supprimer(int $id): void
{
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$id]);
    if (!$c) return;
    bd_transaction(function () use ($id, $c) {
        bd_requete('DELETE pf FROM participants_fiches pf JOIN participants p ON p.id = pf.participant_id WHERE p.concert_id = ?', [$id]);
        foreach (['participants', 'programme', 'representations', 'notes_artiste'] as $t) bd_supprimer($t, ['concert_id' => $id]);
        bd_requete('DELETE FROM concerts_orchestres WHERE concert_id = ?', [$id]);
        // sa zone de dépôt part avec lui ; les images déjà retenues restent dans la médiathèque
        if (bd_table_existe('depots_fichiers')) {
            foreach (bd_lignes('SELECT f.id FROM depots_fichiers f JOIN depots d ON d.id = f.depot_id WHERE d.concert_id = ?', [$id]) as $x) depot_fichier_supprimer((int) $x['id']);
            bd_supprimer('depots', ['concert_id' => $id]);
        }
        bd_supprimer('concerts', ['id' => $id]);
        journaliser('suppression', 'concert', $id, $c['titre_en'], ['cle' => $c['cle'], 'titre_en' => $c['titre_en'], 'type' => $c['type']], null);
    });
}

/** Refait les liens d'un concert vers l'annuaire (après la création d'une fiche). */
function concert_relier_tout(int $id): void
{
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$id]);
    if (!$c) return;
    $d = ['ensemble_en' => $c['ensemble_en'], 'salle_en' => $c['salle_en']];
    concert_relier($d);
    bd_modifier('concerts', ['ensemble_id' => $d['ensemble_id'], 'salle_id' => $d['salle_id']], ['id' => $id]);
    foreach (bd_lignes('SELECT id, compositeur_en FROM programme WHERE concert_id = ?', [$id]) as $p) {
        bd_modifier('programme', ['compositeur_id' => fiche_trouver($p['compositeur_en'], ['compositeur'])], ['id' => $p['id']]);
    }
    participants_relier($id);
}

/** Applique un statut à toutes les représentations d'un concert qui peuvent le recevoir. Rend [faites, erreurs]. */
function statut_changer_tout(int $concert_id, string $nouveau, array $champs = []): array
{
    $faites = 0;
    $erreurs = [];
    foreach (bd_lignes('SELECT id, date, statut FROM representations WHERE concert_id = ? ORDER BY date, heure, id', [$concert_id]) as $r) {
        if ($r['statut'] === $nouveau || !in_array($nouveau, statuts_possibles($r['statut']), true)) continue;
        $e = statut_changer((int) $r['id'], $nouveau, $champs);
        if ($e) $erreurs[] = date_fr($r['date']) . ' : ' . $e; else $faites++;
    }
    return [$faites, $erreurs];
}
