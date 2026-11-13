<?php
/**
 * Alertes : recalculées après chaque modification et chaque nuit (cron).
 *
 * Une alerte a une clé (son type et son sujet) : tant que le sujet demeure, l'alerte garde son
 * état (ouverte, reportée, ignorée) ; quand il disparaît, elle est retirée. « Traiter » mène à
 * l'écran où l'on corrige ; « Reporter » la cache une semaine ; « Ignorer » la cache tant que
 * le sujet ne change pas.
 */

defined('PUPITRE') or exit;

/** Types d'alerte, du plus pressant au moins pressant : type => libellé. */
const TYPES_ALERTE = [
    'embargo' => 'Embargo arrivé à échéance',
    'proche' => 'Concert proche, fiche incomplète',
    'source' => 'Annoncée sans lien source',
    'lieu' => 'Salle, ville ou heure manquante',
    'connue' => 'Date passée restée « connue »',
    'doublon' => 'Doublon probable',
    'site' => 'Écart entre Pupitre et le site',
    'extrait' => 'Extrait de l\'agence',
    'vide' => 'Contenu du site resté vide',
];

const DELAI_FICHE = 21;        // jours avant un concert où une fiche incomplète devient une alerte
const DELAI_EXTRAIT = 30;      // jours au-delà desquels le dernier extrait est tenu pour ancien

/** Calcule les alertes du moment : [[type, clé, message, lien], …]. Le lien est la partie « page=… » de l'adresse. */
function alertes_calculer(): array
{
    $a = [];
    $jour = aujourdhui();
    $ajouter = function (string $type, string $cle, string $message, string $lien) use (&$a) {
        $a[$type . ':' . $cle] = [$type, $type . ':' . $cle, mb_substr($message, 0, 500), $lien];
    };

    // représentations à venir : lieu, heure, lien source, embargo ; passées restées « connues »
    $par_concert = [];
    foreach (agenda_representations(['sauf_annulees' => true]) as $r) {
        $titre = '« ' . $r['titre_fr'] . ' », ' . date_fr($r['date']);
        $lien = 'page=concert&id=' . $r['concert_id'];
        if ($r['date'] >= $jour) {
            if ($r['type'] !== 'disque') {
                $manques = array_keys(array_filter(['salle' => $r['lieu_salle'] === '', 'ville' => $r['lieu_ville'] === '', 'heure' => !$r['heure']]));
                if ($manques) $par_concert[$r['concert_id']][] = [$titre, $manques, $lien];
            }
            if ($r['statut'] === 'annoncee' && $r['lien_source'] === '') $ajouter('source', 'r' . $r['id'], $titre . ' : annoncée sans lien source.', $lien);
        }
        if ($r['statut'] === 'embargo' && $r['embargo_le'] && $r['embargo_le'] <= $jour) {
            $ajouter('embargo', 'r' . $r['id'], $titre . ' : l\'embargo est levé depuis le ' . date_fr($r['embargo_le']) . '. Annoncez la date, ou repoussez l\'échéance.', $lien);
        }
        if ($r['statut'] === 'connue' && $r['date'] < $jour) {
            $ajouter('connue', 'r' . $r['id'], $titre . ' : date passée, restée « connue ».', $lien);
        }
    }
    foreach ($par_concert as $id => $lignes) {
        $manques = array_values(array_unique(array_merge(...array_column($lignes, 1))));
        $ajouter('lieu', 'c' . $id, $lignes[0][0] . (count($lignes) > 1 ? ' et ' . (count($lignes) - 1) . ' autre(s) date(s)' : '') . ' : ' . implode(', ', $manques) . ' à renseigner.', $lignes[0][2]);
    }

    // concerts à moins de 21 jours dont la fiche est incomplète
    $bientot = bd_colonne("SELECT DISTINCT concert_id FROM representations WHERE statut <> 'annulee' AND date BETWEEN ? AND ?", [$jour, ajouter_jours($jour, DELAI_FICHE)]);
    foreach ($bientot as $id) {
        $c = concert_lire((int) $id);
        if (!$c) continue;
        $manques = concert_incomplet($c);
        if (!$manques) continue;
        $prochaine = '';
        foreach ($c['representations'] as $r) if ($r['statut'] !== 'annulee' && $r['date'] >= $jour) { $prochaine = $r['date']; break; }
        $ajouter('proche', 'c' . $id, '« ' . $c['titre_fr'] . ' », ' . date_fr($prochaine) . ' : il manque ' . implode(', ', $manques) . '.', 'page=concert&id=' . $id);
    }

    // doublons probables : agenda, annuaire
    foreach (agenda_doublons() as [$x, $y]) {
        $ids = [(int) $x['id'], (int) $y['id']];
        sort($ids);
        $ajouter('doublon', 'r' . implode('-', $ids), 'Le ' . date_fr($x['date']) . ' : « ' . $x['titre_fr'] . ' » et « ' . $y['titre_fr'] . ' » se ressemblent.', 'page=concert&id=' . $x['concert_id']);
    }
    foreach (fiches_doublons() as [$x, $y, $raison]) {
        $ajouter('doublon', 'f' . $x['id'] . '-' . $y['id'], 'Annuaire : « ' . $x['nom'] . ' » et « ' . $y['nom'] . ' » (' . $raison . ').', 'page=fiche&id=' . $x['id']);
    }

    // écart entre Pupitre et le site
    try {
        $etat = publication_etat();
        if ($etat['ecarts'] > 0) $ajouter('site', 'ecarts', pluriel($etat['ecarts'], 'écart', 'écarts') . ' entre Pupitre et le site : une publication est à faire.', 'page=site');
    } catch (Throwable $e) {
        $ajouter('site', 'racine', 'Le site est introuvable : vérifiez sa racine dans les réglages.', 'page=reglages');
    }

    // dernier extrait de l'agence
    $dernier = import_dernier();
    if (!$dernier) {
        $ajouter('extrait', 'aucun', 'Aucun extrait de l\'agence n\'a encore été importé.', 'page=imports');
    } elseif (strtotime($dernier['cree_le']) < time() - DELAI_EXTRAIT * 86400) {
        $ajouter('extrait', 'ancien', 'Le dernier extrait de l\'agence a été importé le ' . date('d/m/Y', strtotime($dernier['cree_le'])) . ', il y a plus de ' . DELAI_EXTRAIT . ' jours.', 'page=imports');
    }

    // contenus du site restés vides
    $noms = fn(array $l) => implode(', ', array_map(fn($x) => preg_replace('/\*/', '', (string) $x), $l));
    $vides = bd_colonne("SELECT titre FROM disques WHERE lien = '' AND TRIM(COALESCE(commander, '')) = '' AND TRIM(COALESCE(streaming, '')) = '' ORDER BY ordre, id");
    if ($vides) $ajouter('vide', 'disques', (count($vides) > 1 ? 'Disques sans lien : ' : 'Disque sans lien : ') . $noms($vides) . '.', 'page=contenu&type=disques');
    $vides = bd_colonne("SELECT COALESCE(a.nom, p.titre_fr) FROM articles p LEFT JOIN annuaire a ON a.id = p.media_id WHERE TRIM(COALESCE(p.verbatim, '')) = '' ORDER BY p.ordre, p.id");
    if ($vides) $ajouter('vide', 'articles', (count($vides) > 1 ? 'Articles sans verbatim : ' : 'Article sans verbatim : ') . $noms($vides) . '.', 'page=contenu&type=presse');
    $vides = bd_colonne('SELECT libelle_fr FROM telechargements WHERE media_id IS NULL ORDER BY ordre, id');
    if ($vides) $ajouter('vide', 'telechargements', (count($vides) > 1 ? 'Téléchargements « à venir » : ' : 'Téléchargement « à venir » : ') . $noms($vides) . '.', 'page=contenu&type=telechargements');
    $vides = bd_colonne('SELECT p.ville_fr FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE a.nom_provisoire = 1 ORDER BY p.ordre');
    if ($vides) $ajouter('vide', 'orchestres', 'Parcours : nom de l\'orchestre à fournir pour ' . $noms($vides) . '.', 'page=orchestres');
    foreach (TEXTES as $cle => [$libelle]) {
        if (trim((string) bd_valeur('SELECT fr FROM textes WHERE cle = ?', [$cle])) === '') $ajouter('vide', 'texte-' . $cle, 'Texte vide : ' . mb_strtolower($libelle) . '.', 'page=textes');
    }
    return array_values($a);
}

/** Met la table des alertes à jour. Rend le nombre d'alertes ouvertes. */
function alertes_recalculer(): int
{
    $GLOBALS['donnees_modifiees'] = false;
    $calculees = alertes_calculer();
    $cles = array_column($calculees, 1);
    $connues = [];
    foreach (bd_lignes('SELECT * FROM alertes') as $l) $connues[$l['cle']] = $l;
    foreach ($calculees as [$type, $cle, $message, $lien]) {
        if (isset($connues[$cle])) {
            $maj = ['message' => $message, 'lien' => $lien, 'maj_le' => date('Y-m-d H:i:s')];
            // un report arrivé à son terme rouvre l'alerte ; une alerte ignorée dont le sujet a changé aussi
            if ($connues[$cle]['etat'] === 'reportee' && $connues[$cle]['reportee_au'] <= aujourdhui()) $maj += ['etat' => 'ouverte', 'reportee_au' => null];
            if ($connues[$cle]['etat'] === 'ignoree' && $connues[$cle]['message'] !== $message) $maj['etat'] = 'ouverte';
            if ($connues[$cle]['message'] !== $message || $connues[$cle]['lien'] !== $lien || isset($maj['etat'])) bd_modifier('alertes', $maj, ['id' => $connues[$cle]['id']]);
        } else {
            bd_inserer('alertes', ['type' => $type, 'cle' => $cle, 'message' => $message, 'lien' => $lien]);
        }
    }
    foreach ($connues as $cle => $l) {
        if (!in_array($cle, $cles, true)) bd_supprimer('alertes', ['id' => $l['id']]);
    }
    return (int) bd_valeur("SELECT COUNT(*) FROM alertes WHERE etat = 'ouverte'");
}

/** Alertes à l'écran, dans l'ordre des types. $toutes : avec les reportées et les ignorées. */
function alertes_lire(bool $toutes = false): array
{
    $lignes = bd_lignes('SELECT * FROM alertes' . ($toutes ? '' : " WHERE etat = 'ouverte'") . ' ORDER BY id');
    $rang = array_flip(array_keys(TYPES_ALERTE));
    usort($lignes, fn($x, $y) => [$rang[$x['type']] ?? 99, $x['id']] <=> [$rang[$y['type']] ?? 99, $y['id']]);
    return $lignes;
}

function alerte_reporter(int $id, int $jours = 7): void
{
    bd_modifier('alertes', ['etat' => 'reportee', 'reportee_au' => ajouter_jours(aujourdhui(), $jours)], ['id' => $id]);
}

function alerte_ignorer(int $id): void
{
    bd_modifier('alertes', ['etat' => 'ignoree', 'reportee_au' => null], ['id' => $id]);
}

function alerte_rouvrir(int $id): void
{
    bd_modifier('alertes', ['etat' => 'ouverte', 'reportee_au' => null], ['id' => $id]);
}
