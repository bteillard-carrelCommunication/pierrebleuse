<?php
/**
 * Journal des modifications : qui, quoi, quand, avant, après.
 *
 * objet_enregistrer() et objet_supprimer() écrivent en base et journalisent d'un même geste :
 * les pages n'écrivent pas dans les tables sans passer par eux (ou par journaliser()).
 */

defined('PUPITRE') or exit;

/** Ne garde que les champs qui changent entre $avant et $apres. */
function differences(?array $avant, ?array $apres): array
{
    if ($avant === null || $apres === null) return [$avant, $apres];
    $a = $b = [];
    foreach (array_unique(array_merge(array_keys($avant), array_keys($apres))) as $k) {
        $x = $avant[$k] ?? null;
        $y = $apres[$k] ?? null;
        if (is_array($x) || is_array($y)) { if ($x == $y) continue; }
        elseif ((string) $x === (string) $y) continue;
        $a[$k] = $x;
        $b[$k] = $y;
    }
    return [$a, $b];
}

/**
 * Écrit une ligne du journal. $action : creation, modification, suppression, statut, publication,
 * retour, validation, connexion… Une modification sans différence n'est pas journalisée.
 */
function journaliser(string $action, string $type, ?int $id, string $libelle, ?array $avant = null, ?array $apres = null): void
{
    if ($avant !== null && $apres !== null) {
        [$avant, $apres] = differences($avant, $apres);
        if (!$avant && !$apres) return;
    }
    $c = en_cli() ? null : compte();
    $json = fn($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    bd_inserer('journal', [
        'compte_id' => $c['id'] ?? null,
        'compte_nom' => $c['nom'] ?? (en_cli() ? 'ligne de commande' : ''),
        'action' => $action,
        'objet_type' => $type,
        'objet_id' => $id,
        'objet_libelle' => mb_substr($libelle, 0, 255),
        'avant' => $json($avant),
        'apres' => $json($apres),
    ]);
    $GLOBALS['donnees_modifiees'] = true;        // les alertes seront recalculées en fin de requête
}

/**
 * Crée ($id null) ou modifie une ligne d'une table, et journalise. Rend l'identifiant.
 * $type et $libelle nomment l'objet dans le journal (« concert », « Au revoir, Pierre »).
 */
function objet_enregistrer(string $table, ?int $id, array $donnees, string $type, string $libelle, string $cle_primaire = 'id')
{
    if ($id === null) {
        $nouveau = bd_inserer($table, $donnees);
        journaliser('creation', $type, $nouveau ?: null, $libelle, null, $donnees);
        return $nouveau;
    }
    $avant = bd_ligne('SELECT * FROM ' . bd_nom($table) . ' WHERE ' . bd_nom($cle_primaire) . ' = ?', [$id]);
    if (!$avant) throw new RuntimeException('Objet introuvable.');
    [$a, $b] = differences(array_intersect_key($avant, $donnees), $donnees);
    if ($b) {
        bd_modifier($table, $b, [$cle_primaire => $id]);
        journaliser('modification', $type, $id, $libelle, $a, $b);
    }
    return $id;
}

function objet_supprimer(string $table, int $id, string $type, string $libelle): void
{
    $avant = bd_ligne('SELECT * FROM ' . bd_nom($table) . ' WHERE id = ?', [$id]);
    if (!$avant) return;
    bd_supprimer($table, ['id' => $id]);
    journaliser('suppression', $type, $id, $libelle, $avant, null);
}

/** Dernières lignes du journal d'un objet (fiche concert, fiche d'annuaire…). */
function journal_objet(string $type, int $id, int $n = 30): array
{
    return bd_lignes('SELECT * FROM journal WHERE objet_type = ? AND objet_id = ? ORDER BY id DESC LIMIT ' . (int) $n, [$type, $id]);
}

/** Rend lisible une valeur du journal. */
function journal_valeur($v): string
{
    if ($v === null || $v === '') return '(vide)';
    if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return (string) $v;
}
