<?php
/**
 * Centre de notifications : tout ce qui demande l'attention, en un seul endroit.
 *
 * Il réunit deux sources, sans rien stocker de plus :
 *   - les alertes ouvertes (inclus/alertes.php), chacune avec son niveau ;
 *   - ce qui attend une validation : extraits de l'agence à valider, notes de l'artiste, comptes de
 *     réseaux proposés (toujours au niveau « action »).
 *
 * Trois niveaux, du plus pressant au moins pressant (NIVEAUX_ALERTE) : alerte prioritaire, action à
 * traiter, information. Le compteur de la barre du haut ne compte que les deux premiers : une
 * information ne réclame rien.
 */

defined('PUPITRE') or exit;

/**
 * Les notifications du moment, les plus pressantes d'abord. Chaque ligne :
 * ['niveau', 'type' (libellé court), 'message', 'lien' (adresse complète), 'quand' (date de dernière mise à jour ou null),
 *  'icone', 'alerte' (identifiant de l'alerte, ou null pour une validation)].
 */
function notifications_lire(): array
{
    static $n = null;
    if ($n !== null && empty($GLOBALS['notifications_perimees'])) return $n;
    $GLOBALS['notifications_perimees'] = false;
    $n = [];
    $base = chemin_pupitre() . '/index.php?';
    $icones = ['technique' => 'alerte', 'embargo' => 'embargo', 'proche' => 'concerts', 'publication' => 'brouillon', 'communication' => 'communication', 'source' => 'lien',
        'lieu' => 'lieu', 'connue' => 'horloge', 'doublon' => 'copier', 'orchestre' => 'orchestres', 'site' => 'publier', 'echeance' => 'horloge', 'extrait' => 'import', 'vide' => 'textes'];
    try {
        foreach (alertes_lire() as $a) {
            $n[] = ['niveau' => isset(NIVEAUX_ALERTE[$a['niveau'] ?? '']) ? $a['niveau'] : 'action', 'type' => TYPES_ALERTE[$a['type']] ?? $a['type'], 'message' => $a['message'],
                'lien' => $base . $a['lien'], 'quand' => $a['maj_le'], 'icone' => $icones[$a['type']] ?? 'info', 'alerte' => (int) $a['id']];
        }
        // ce qui attend une validation
        foreach (bd_lignes("SELECT i.id, i.statut, i.date_extrait, i.cree_le, (SELECT COUNT(*) FROM import_lignes l WHERE l.import_id = i.id AND l.decision = 'en_attente') AS attente
                            FROM imports i WHERE i.statut = 'a_valider' ORDER BY i.id DESC") as $i) {
            if (!(int) $i['attente']) continue;
            $n[] = ['niveau' => 'action', 'type' => 'Extrait de l\'agence à valider', 'message' => 'Extrait' . ($i['date_extrait'] ? ' du ' . date_fr($i['date_extrait']) : '') . ' : '
                . pluriel((int) $i['attente'], 'changement attend', 'changements attendent') . ' une décision.', 'lien' => url('import', ['id' => $i['id']]), 'quand' => $i['cree_le'], 'icone' => 'import', 'alerte' => null];
        }
        foreach (bd_lignes("SELECT n.id, n.texte, n.cree_le, n.concert_id, c.titre_fr, k.nom FROM notes_artiste n JOIN concerts c ON c.id = n.concert_id JOIN comptes k ON k.id = n.compte_id
                            WHERE n.statut = 'a_traiter' ORDER BY n.id DESC") as $x) {
            $n[] = ['niveau' => 'action', 'type' => 'Note de l\'artiste', 'message' => $x['nom'] . ', sur « ' . $x['titre_fr'] . ' » : ' . couper(preg_replace('/\s+/u', ' ', (string) $x['texte']), 140),
                'lien' => url('bord') . '#b-valider', 'quand' => $x['cree_le'], 'icone' => 'artiste', 'alerte' => null];
        }
        $proposes = (int) bd_valeur("SELECT COUNT(*) FROM annuaire_comptes WHERE etat = 'propose'");
        if ($proposes) {
            $n[] = ['niveau' => 'action', 'type' => 'Comptes de réseaux à vérifier', 'message' => pluriel($proposes, 'compte proposé attend', 'comptes proposés attendent') . ' une vérification dans l\'annuaire.',
                'lien' => url('bord') . '#b-valider', 'quand' => null, 'icone' => 'reseau', 'alerte' => null];
        }
    } catch (Throwable $e) {
        // une base pas encore à niveau : le centre reste vide plutôt que de bloquer la page
        journal_fichier('erreurs', 'notifications : ' . get_class($e) . ' ' . basename($e->getFile()) . ':' . $e->getLine());
        return $n = [];
    }
    $rang = array_flip(array_keys(NIVEAUX_ALERTE));
    usort($n, fn($x, $y) => [$rang[$x['niveau']], -strtotime((string) ($x['quand'] ?? '1970-01-01'))] <=> [$rang[$y['niveau']], -strtotime((string) ($y['quand'] ?? '1970-01-01'))]);
    return $n;
}

/** Décompte des notifications par niveau : ['prioritaire' => n, 'action' => n, 'info' => n, 'a_traiter' => prioritaires + actions]. */
function notifications_decompte(?array $n = null): array
{
    $d = ['prioritaire' => 0, 'action' => 0, 'info' => 0];
    foreach ($n ?? notifications_lire() as $x) $d[$x['niveau']]++;
    $d['a_traiter'] = $d['prioritaire'] + $d['action'];
    return $d;
}

/** « il y a 3 h », « hier », « le 12 novembre » : l'ancienneté d'une notification. */
function il_y_a(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    if (!$t) return '';
    $s = time() - $t;
    if ($s < 90) return 'à l\'instant';
    if ($s < 3600) return 'il y a ' . (int) round($s / 60) . ' min';
    if ($s < 86400 && date('Y-m-d', $t) === date('Y-m-d')) return 'il y a ' . (int) round($s / 3600) . ' h';
    if (date('Y-m-d', $t) === date('Y-m-d', time() - 86400)) return 'hier';
    if ($s < 7 * 86400) return 'il y a ' . (int) floor($s / 86400) . ' jours';
    return 'le ' . date_fr(date('Y-m-d', $t), false, date('Y', $t) !== date('Y'));
}
