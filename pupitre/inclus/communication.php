<?php
/**
 * Pilotage de la communication : les actions de communication et leur lien avec les concerts.
 *
 * Une action est une publication sur un réseau social ou une lettre d'information. Elle a un canal,
 * un statut (à préparer, programmée, publiée), une date prévue, une date réelle, et les concerts
 * qu'elle concerne. Le but : voir d'un coup d'œil ce qui est prévu, ce qui est fait, et surtout les
 * concerts à venir pour lesquels rien n'est encore prévu.
 *
 * Tout se saisit à la main pour l'instant. Aucune plateforme n'est connectée : la connexion aux
 * réseaux et à l'outil d'envoi de la lettre fera l'objet d'une seconde phase. Les colonnes `origine`
 * et `reference_externe` l'attendent ; une action venue d'une plateforme entrera dans la même table.
 *
 * Règle reprise de l'agenda : on ne communique que sur un concert annoncé par son organisateur. Une
 * date « connue » ou « sous embargo » n'est pas comptée comme « sans communication » : elle ne
 * peut pas encore en avoir.
 */

defined('PUPITRE') or exit;

/** Canaux : clé => [nom, pictogramme, famille]. La famille range l'action dans « Social Media » ou dans « Newsletter ». */
const CANAUX = [
    'instagram' => ['Instagram', 'instagram', 'social'],
    'facebook' => ['Facebook', 'facebook', 'social'],
    'youtube' => ['YouTube', 'youtube', 'social'],
    'linkedin' => ['LinkedIn', 'linkedin', 'social'],
    'autre' => ['Autre réseau', 'social', 'social'],
    'lettre' => ['Newsletter', 'newsletter', 'lettre'],
];

const FAMILLES_COMMUNICATION = ['social' => 'Social Media', 'lettre' => 'Newsletter'];

/** Statuts d'une action : clé => [libellé pour un réseau, libellé pour la lettre, classe de la puce, pictogramme]. */
const STATUTS_COMMUNICATION = [
    'a_preparer' => ['À préparer', 'À préparer', 'puce--alerte', 'brouillon'],
    'programmee' => ['Programmée', 'Programmée', 'puce--accent', 'programmee'],
    'publiee' => ['Publiée', 'Envoyée', 'puce--ok', 'ok'],
];

const DELAI_COMMUNICATION = 30;      // jours avant un concert annoncé où l'absence de communication devient une alerte
const DELAI_PREPARATION = 7;         // jours avant la date prévue où une action « à préparer » devient une alerte

/** Canaux d'une famille : clé => nom. */
function canaux_de(string $famille): array
{
    $c = [];
    foreach (CANAUX as $cle => [$nom, , $f]) if ($f === $famille) $c[$cle] = $nom;
    return $c;
}

function canal_famille(string $canal): string
{
    return CANAUX[$canal][2] ?? 'social';
}

/** Libellé du statut d'une action, selon son canal (« publiée » pour un réseau, « envoyée » pour la lettre). */
function communication_statut(array $a): string
{
    $s = STATUTS_COMMUNICATION[$a['statut']] ?? STATUTS_COMMUNICATION['a_preparer'];
    return canal_famille((string) $a['canal']) === 'lettre' ? $s[1] : $s[0];
}

/** La puce de statut d'une action. */
function communication_puce(array $a): string
{
    $s = STATUTS_COMMUNICATION[$a['statut']] ?? STATUTS_COMMUNICATION['a_preparer'];
    return '<span class="puce ' . $s[2] . '">' . icone($s[3]) . e(communication_statut($a)) . '</span>';
}

/** Ce qui nomme une action dans une liste : son titre, sinon le début de son texte. */
function communication_libelle(array $a): string
{
    $t = trim((string) $a['titre']);
    if ($t !== '') return $t;
    $t = trim(preg_replace('/\s+/u', ' ', (string) $a['texte']));
    return $t !== '' ? couper($t, 70) : (CANAUX[$a['canal']][0] ?? 'Action') . ' sans titre';
}

/** La date qui situe une action dans le temps : sa date réelle si elle est publiée, sinon sa date prévue. */
function communication_date(array $a): ?string
{
    return ($a['statut'] === 'publiee' && $a['publiee_le']) ? $a['publiee_le'] : ($a['prevue_le'] ?: null);
}

/** Une action, avec les identifiants de ses concerts. */
function communication_lire(int $id): ?array
{
    $a = bd_ligne('SELECT * FROM communications WHERE id = ?', [$id]);
    if (!$a) return null;
    $a['concerts'] = array_map('intval', bd_colonne('SELECT concert_id FROM communications_concerts WHERE communication_id = ? ORDER BY concert_id', [$id]));
    return $a;
}

/**
 * Actions, les plus proches d'abord. Filtres : famille (social, lettre), canal, statut, du, au
 * (sur la date qui situe l'action), concert, limite. Chaque ligne porte `concerts` : [id => titre].
 */
function communications_lister(array $f = []): array
{
    $ou = ['1 = 1'];
    $p = [];
    if (!empty($f['famille'])) {
        $canaux = array_keys(canaux_de($f['famille']));
        $ou[] = 'k.canal IN (' . implode(',', array_fill(0, count($canaux), '?')) . ')';
        $p = array_merge($p, $canaux);
    }
    if (!empty($f['canal'])) { $ou[] = 'k.canal = ?'; $p[] = $f['canal']; }
    if (!empty($f['statut'])) { $ou[] = 'k.statut = ?'; $p[] = $f['statut']; }
    if (!empty($f['concert'])) { $ou[] = 'EXISTS (SELECT 1 FROM communications_concerts x WHERE x.communication_id = k.id AND x.concert_id = ?)'; $p[] = (int) $f['concert']; }
    $quand = "COALESCE(IF(k.statut = 'publiee', k.publiee_le, NULL), k.prevue_le)";
    if (!empty($f['du'])) { $ou[] = "$quand >= ?"; $p[] = $f['du'] . ' 00:00:00'; }
    if (!empty($f['au'])) { $ou[] = "$quand <= ?"; $p[] = $f['au'] . ' 23:59:59'; }
    $ordre = !empty($f['recentes']) ? "$quand DESC, k.id DESC" : "$quand IS NULL, $quand, k.id";
    $lignes = bd_lignes("SELECT k.* FROM communications k WHERE " . implode(' AND ', $ou) . " ORDER BY $ordre" . (!empty($f['limite']) ? ' LIMIT ' . (int) $f['limite'] : ''), $p);
    if (!$lignes) return [];
    $par_action = [];
    $ids = array_map(fn($l) => (int) $l['id'], $lignes);
    foreach (bd_lignes('SELECT x.communication_id, c.id, c.titre_en FROM communications_concerts x JOIN concerts c ON c.id = x.concert_id
                        WHERE x.communication_id IN (' . implode(',', $ids) . ') ORDER BY c.titre_en') as $x) {
        $par_action[(int) $x['communication_id']][(int) $x['id']] = $x['titre_en'];
    }
    foreach ($lignes as &$l) $l['concerts'] = $par_action[(int) $l['id']] ?? [];
    return $lignes;
}

/**
 * Crée ou modifie une action. $f : canal, titre, texte, statut, date_prevue, heure_prevue,
 * date_publiee, heure_publiee, lien, notes, concerts (identifiants). Rend [identifiant, erreurs].
 */
function communication_enregistrer(?int $id, array $f): array
{
    $erreurs = [];
    $t = fn(string $k, int $max) => mb_substr(trim(str_replace("\r\n", "\n", (string) ($f[$k] ?? ''))), 0, $max);
    $quand = function (string $date, string $heure, string $nom) use (&$erreurs): ?string {
        if ($date === '') return null;
        if (!date_valide($date)) { $erreurs[] = $nom . ' : date illisible.'; return null; }
        $h = heure_lire($heure);
        if ($h === null) { $erreurs[] = $nom . ' : heure illisible (19:30).'; return null; }
        return $date . ' ' . ($h !== '' ? $h : '00:00') . ':00';
    };
    $d = [
        'canal' => isset(CANAUX[$f['canal'] ?? '']) ? $f['canal'] : '',
        'titre' => $t('titre', 255),
        'texte' => $t('texte', 20000),
        'statut' => isset(STATUTS_COMMUNICATION[$f['statut'] ?? '']) ? $f['statut'] : 'a_preparer',
        'prevue_le' => $quand($t('date_prevue', 10), $t('heure_prevue', 5), 'Date prévue'),
        'publiee_le' => $quand($t('date_publiee', 10), $t('heure_publiee', 5), 'Date de publication'),
        'lien' => $t('lien', 500),
        'notes' => $t('notes', 5000),
    ];
    $lettre = canal_famille($d['canal']) === 'lettre';
    if ($d['canal'] === '') $erreurs[] = 'Choisissez le canal.';
    if ($d['titre'] === '' && $d['texte'] === '') $erreurs[] = $lettre ? 'Donnez l\'objet de la lettre, ou son texte.' : 'Donnez un intitulé, ou le texte de la publication.';
    if (!adresse_valide($d['lien'])) $erreurs[] = 'L\'adresse doit être complète (https://…).';
    if ($d['statut'] === 'programmee' && !$d['prevue_le']) $erreurs[] = 'Une action programmée a une date prévue.';
    if ($d['statut'] === 'publiee' && !$d['publiee_le']) $erreurs[] = $lettre ? 'Une lettre envoyée a une date d\'envoi.' : 'Une publication publiée a une date de publication.';
    if ($d['statut'] !== 'publiee') $d['publiee_le'] = null;          // la date réelle n'a de sens qu'une fois l'action faite
    if ($d['statut'] === 'publiee' && $d['publiee_le'] && $d['publiee_le'] > date('Y-m-d H:i:s', time() + 86400)) $erreurs[] = 'Une date de publication à venir : choisissez plutôt le statut « programmée ».';
    $concerts = [];
    foreach ((array) ($f['concerts'] ?? []) as $c) if (ctype_digit((string) $c) && bd_valeur('SELECT id FROM concerts WHERE id = ?', [(int) $c])) $concerts[] = (int) $c;
    $concerts = array_values(array_unique($concerts));
    if ($erreurs) return [$id, $erreurs];

    $libelle = (CANAUX[$d['canal']][0] ?? '') . ' · ' . communication_libelle($d);
    $id = bd_transaction(function () use ($id, $d, $concerts, $libelle) {
        if ($id === null) {
            $c = compte();
            $id = (int) objet_enregistrer('communications', null, $d + ['cree_par' => $c['id'] ?? null], 'communication', $libelle);
        } else {
            objet_enregistrer('communications', $id, $d, 'communication', $libelle);
        }
        $avant = array_map('intval', bd_colonne('SELECT concert_id FROM communications_concerts WHERE communication_id = ? ORDER BY concert_id', [$id]));
        sort($concerts);
        if ($avant !== $concerts) {
            bd_supprimer('communications_concerts', ['communication_id' => $id]);
            foreach ($concerts as $c) bd_inserer('communications_concerts', ['communication_id' => $id, 'concert_id' => $c]);
            journaliser('modification', 'communication', $id, $libelle . ' : concerts associés', ['concerts' => $avant], ['concerts' => $concerts]);
        }
        return $id;
    });
    $GLOBALS['donnees_modifiees'] = true;
    return [$id, []];
}

function communication_supprimer(int $id): void
{
    $a = communication_lire($id);
    if (!$a) return;
    bd_supprimer('communications_concerts', ['communication_id' => $id]);
    objet_supprimer('communications', $id, 'communication', (CANAUX[$a['canal']][0] ?? '') . ' · ' . communication_libelle($a));
    $GLOBALS['donnees_modifiees'] = true;
}

/** Les actions de communication d'un concert, les plus proches d'abord. */
function communications_de_concert(int $concert_id): array
{
    return communications_lister(['concert' => $concert_id]);
}

/**
 * Les concerts à venir et leur communication : un concert par ligne, avec sa prochaine date, ses
 * dates à venir, ses actions, et son état :
 *   couvert      au moins une action de communication est enregistrée ;
 *   sans_action  annoncé par son organisateur (ou déjà publié sur le site), et rien n'est prévu ;
 *   en_attente   pas encore annoncé (date connue, ou sous embargo) : on ne communique pas encore.
 * $jours : horizon, en jours ; 0 : tous les concerts à venir.
 */
function concerts_a_communiquer(int $jours = 0): array
{
    $jour = aujourdhui();
    $filtres = ['sauf_annulees' => true, 'du' => $jour];
    if ($jours > 0) $filtres['au'] = ajouter_jours($jour, $jours);
    $concerts = [];
    foreach (agenda_representations($filtres) as $r) {
        if (!$r['publie']) continue;                                     // un concert dépublié ne se communique pas
        $id = (int) $r['concert_id'];
        if (!isset($concerts[$id])) {
            $concerts[$id] = ['id' => $id, 'titre' => $r['titre_en'], 'type' => $r['type'], 'ensemble' => $r['ensemble_en'], 'prochaine' => $r['date'], 'heure' => $r['heure'],
                'ville' => $r['lieu_ville'], 'salle' => $r['lieu_salle'], 'pays' => $r['lieu_pays'], 'dates' => [], 'statuts' => [], 'embargo_le' => null, 'actions' => []];
        }
        $concerts[$id]['dates'][] = $r['date'];
        $concerts[$id]['statuts'][$r['statut']] = true;
        if ($r['statut'] === 'embargo' && $r['embargo_le']) $concerts[$id]['embargo_le'] = min($concerts[$id]['embargo_le'] ?? $r['embargo_le'], $r['embargo_le']);
    }
    if (!$concerts) return [];
    foreach (bd_lignes('SELECT x.concert_id, k.* FROM communications_concerts x JOIN communications k ON k.id = x.communication_id
                        WHERE x.concert_id IN (' . implode(',', array_keys($concerts)) . ") ORDER BY COALESCE(IF(k.statut = 'publiee', k.publiee_le, NULL), k.prevue_le), k.id") as $a) {
        $concerts[(int) $a['concert_id']]['actions'][] = $a;
    }
    foreach ($concerts as &$c) {
        $annonce = isset($c['statuts']['annoncee']) || isset($c['statuts']['publiee']);
        $c['annonce'] = $annonce;
        $c['etat'] = $c['actions'] ? 'couvert' : ($annonce ? 'sans_action' : 'en_attente');
        $c['dans'] = (int) round((strtotime($c['prochaine'] . ' 12:00') - strtotime($jour . ' 12:00')) / 86400);
    }
    return array_values($concerts);
}

/**
 * Le calendrier éditorial d'un mois (AAAA-MM) : jour => liste d'éléments [nature, libellé, lien, détail, statut].
 * Natures : concert (une représentation), action (publication ou lettre), embargo (échéance d'annonce).
 */
function calendrier_editorial(string $mois): array
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) $mois = substr(aujourdhui(), 0, 7);
    $du = $mois . '-01';
    $au = date('Y-m-t', strtotime($du . ' 12:00'));
    $jours = [];
    foreach (agenda_representations(['sauf_annulees' => true, 'du' => $du, 'au' => $au]) as $r) {
        $jours[$r['date']][] = ['nature' => 'concert', 'libelle' => $r['titre_en'], 'lien' => url('concert', ['id' => $r['concert_id']]),
            'detail' => trim(heure_fr($r['heure']) . ' ' . $r['lieu_ville']), 'statut' => $r['statut'], 'icone' => $r['type'] === 'disque' ? 'disques' : 'concerts'];
    }
    foreach (bd_lignes("SELECT r.embargo_le, r.date, r.concert_id, c.titre_en FROM representations r JOIN concerts c ON c.id = r.concert_id
                        WHERE r.statut = 'embargo' AND r.embargo_le BETWEEN ? AND ? ORDER BY r.embargo_le", [$du, $au]) as $r) {
        $jours[$r['embargo_le']][] = ['nature' => 'embargo', 'libelle' => 'Fin d\'embargo : ' . $r['titre_en'], 'lien' => url('concert', ['id' => $r['concert_id']]),
            'detail' => 'concert du ' . date_fr($r['date']), 'statut' => 'embargo', 'icone' => 'embargo'];
    }
    foreach (communications_lister(['du' => $du, 'au' => $au]) as $a) {
        $quand = communication_date($a);
        if (!$quand) continue;
        $jours[substr($quand, 0, 10)][] = ['nature' => 'action', 'libelle' => communication_libelle($a), 'lien' => url('communication', ['id' => $a['id']]),
            'detail' => (CANAUX[$a['canal']][0] ?? '') . ' · ' . mb_strtolower(communication_statut($a)), 'statut' => $a['statut'], 'icone' => CANAUX[$a['canal']][1] ?? 'social'];
    }
    ksort($jours);
    return $jours;
}

/** Décompte des actions par statut, pour une famille (social, lettre) ou pour toutes. */
function communications_decompte(?string $famille = null): array
{
    $n = ['a_preparer' => 0, 'programmee' => 0, 'publiee' => 0];
    $ou = '1 = 1';
    $p = [];
    if ($famille !== null) {
        $canaux = array_keys(canaux_de($famille));
        $ou = 'canal IN (' . implode(',', array_fill(0, count($canaux), '?')) . ')';
        $p = $canaux;
    }
    foreach (bd_lignes("SELECT statut, COUNT(*) AS n FROM communications WHERE $ou GROUP BY statut", $p) as $l) $n[$l['statut']] = (int) $l['n'];
    return $n;
}

/** Les comptes de l'artiste sur les réseaux, tels que le site les affiche : clé du canal => adresse. */
function comptes_artiste(): array
{
    $c = [];
    foreach (bd_lignes('SELECT cle, nom, url FROM reseaux ORDER BY ordre, id') as $r) if ($r['url'] !== '') $c[$r['cle']] = ['nom' => $r['nom'], 'url' => $r['url']];
    return $c;
}
