<?php
/**
 * Alertes : recalculées après chaque modification et chaque nuit (cron).
 *
 * Une alerte a une clé (son type et son sujet) : tant que le sujet demeure, l'alerte garde son
 * état (ouverte, reportée, ignorée) ; quand il disparaît, elle est retirée. « Traiter » mène à
 * l'écran où l'on corrige ; « Reporter » la cache une semaine ; « Ignorer » la cache tant que
 * le sujet ne change pas.
 *
 * Chaque alerte a un niveau (NIVEAUX_ALERTE) : alerte prioritaire, action à traiter, information.
 * Le centre de notifications (inclus/notifications.php) les réunit avec ce qui attend une validation.
 */

defined('PUPITRE') or exit;

/** Types d'alerte, du plus pressant au moins pressant : type => libellé. */
const TYPES_ALERTE = [
    'technique' => 'Anomalie technique',
    'embargo' => 'Embargo arrivé à échéance',
    'proche' => 'Concert proche, fiche incomplète',
    'publication' => 'Publication à préparer',
    'communication' => 'Concert sans communication',
    'source' => 'Annoncée sans lien source',
    'lieu' => 'Salle, ville ou heure manquante',
    'connue' => 'Date passée restée « connue »',
    'doublon' => 'Doublon probable',
    'orchestre' => 'Orchestre non référencé',
    'apercu' => 'Aperçu du site',
    'depot' => 'Images déposées',
    'veille' => 'Veille',
    'courrier' => 'Courrier',
    'site' => 'Écart entre Pupitre et le site',
    'echeance' => 'Échéance à venir',
    'extrait' => 'Extrait de l\'agence',
    'vide' => 'Contenu à actualiser',
];

/**
 * Niveaux d'une alerte, du plus pressant au moins pressant : clé => [libellé, pictogramme, classe].
 *   prioritaire  à traiter sans attendre : une échéance est passée, ou quelque chose ne fonctionne pas ;
 *   action       demande une intervention, sans urgence ;
 *   info         à savoir : rien ne presse.
 * Le niveau est posé au calcul de l'alerte (alertes_calculer) et rangé avec elle.
 */
const NIVEAUX_ALERTE = [
    'prioritaire' => ['Alertes prioritaires', 'alerte', 'prioritaire'],
    'action' => ['Actions à traiter', 'actions', 'action'],
    'info' => ['Informations', 'info', 'info'],
];

const DELAI_FICHE = 21;        // jours avant un concert où une fiche incomplète devient une alerte
const DELAI_EXTRAIT = 30;      // jours au-delà desquels le dernier extrait est tenu pour ancien

/** Calcule les alertes du moment : [[type, clé, message, lien, niveau], …]. Le lien est la partie « page=… » de l'adresse. */
function alertes_calculer(): array
{
    $a = [];
    $jour = aujourdhui();
    $ajouter = function (string $type, string $cle, string $message, string $lien, string $niveau = 'action') use (&$a) {
        $a[$type . ':' . $cle] = [$type, $type . ':' . $cle, mb_substr($message, 0, 500), $lien, isset(NIVEAUX_ALERTE[$niveau]) ? $niveau : 'action'];
    };

    // représentations à venir : lieu, heure, lien source, embargo ; passées restées « connues »
    $par_concert = [];
    foreach (agenda_representations(['sauf_annulees' => true]) as $r) {
        $titre = '« ' . $r['titre_en'] . ' », ' . date_fr($r['date']);
        $lien = 'page=concert&id=' . $r['concert_id'];
        if ($r['date'] >= $jour) {
            if ($r['type'] !== 'disque') {
                $manques = array_keys(array_filter(['salle' => $r['lieu_salle'] === '', 'ville' => $r['lieu_ville'] === '', 'heure' => !$r['heure']]));
                if ($manques) $par_concert[$r['concert_id']][] = [$titre, $manques, $lien];
            }
            if ($r['statut'] === 'annoncee' && $r['lien_source'] === '') $ajouter('source', 'r' . $r['id'], $titre . ' : annoncée sans lien source.', $lien);
        }
        if ($r['statut'] === 'embargo' && $r['embargo_le'] && $r['embargo_le'] <= $jour) {
            $ajouter('embargo', 'r' . $r['id'], $titre . ' : l\'embargo est levé depuis le ' . date_fr($r['embargo_le']) . '. Annoncez la date, ou repoussez l\'échéance.', $lien, 'prioritaire');
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
        $ajouter('proche', 'c' . $id, '« ' . $c['titre_en'] . ' », ' . date_fr($prochaine) . ' : il manque ' . implode(', ', $manques) . '.', 'page=concert&id=' . $id, 'prioritaire');
    }

    // doublons probables : agenda, annuaire
    foreach (agenda_doublons() as [$x, $y]) {
        $ids = [(int) $x['id'], (int) $y['id']];
        sort($ids);
        $ajouter('doublon', 'r' . implode('-', $ids), 'Le ' . date_fr($x['date']) . ' : « ' . $x['titre_en'] . ' » et « ' . $y['titre_en'] . ' » se ressemblent.', 'page=concert&id=' . $x['concert_id']);
    }
    foreach (fiches_doublons() as [$x, $y, $raison]) {
        $ajouter('doublon', 'f' . $x['id'] . '-' . $y['id'], 'Annuaire : « ' . $x['nom'] . ' » et « ' . $y['nom'] . ' » (' . $raison . ').', 'page=fiche&id=' . $x['id']);
    }

    // orchestre d'un concert absent de la collection : la notification du cahier des charges
    foreach (concerts_orchestre_inconnu() as [$c, $r]) {
        $ajouter('orchestre', 'c' . $c['id'], '« ' . $c['ensemble_en'] . ' » (concert « ' . $c['titre_en'] . ' ») : cet orchestre n\'est pas encore référencé. Souhaitez-vous l\'ajouter à la carte mondiale ?', 'page=concert&id=' . $c['id'] . '#orchestre');
    }

    // écart entre Pupitre et le site
    try {
        $etat = publication_etat();
        if ($etat['ecarts'] > 0) $ajouter('site', 'ecarts', pluriel($etat['ecarts'], 'écart', 'écarts') . ' entre Pupitre et le site : une publication est à faire.', 'page=site');
    } catch (Throwable $e) {
        $ajouter('technique', 'racine', 'Le site est introuvable : vérifiez sa racine dans les réglages.', 'page=reglages', 'prioritaire');
    }

    // échéances à venir : un embargo qui se lève dans la semaine
    foreach (bd_lignes("SELECT r.id, r.date, r.embargo_le, r.concert_id, c.titre_en FROM representations r JOIN concerts c ON c.id = r.concert_id
                        WHERE r.statut = 'embargo' AND r.embargo_le > ? AND r.embargo_le <= ? ORDER BY r.embargo_le", [$jour, ajouter_jours($jour, 7)]) as $r) {
        $ajouter('echeance', 'r' . $r['id'], '« ' . $r['titre_en'] . ' », ' . date_fr($r['date']) . ' : l\'embargo se lève le ' . date_fr($r['embargo_le']) . '.', 'page=concert&id=' . $r['concert_id'], 'info');
    }

    // communication : concerts annoncés et proches pour lesquels rien n'est prévu ; actions à préparer dont la date approche, ou est passée
    if (bd_table_existe('communications')) {
        $sans = array_values(array_filter(concerts_a_communiquer(DELAI_COMMUNICATION), fn($c) => $c['etat'] === 'sans_action'));
        if ($sans) {
            $titres = array_map(fn($c) => '« ' . $c['titre'] . ' » (' . date_fr($c['prochaine'], false, false) . ')', array_slice($sans, 0, 4));
            $ajouter('communication', 'sans-action', pluriel(count($sans), 'concert annoncé dans les ' . DELAI_COMMUNICATION . ' jours n\'a', 'concerts annoncés dans les ' . DELAI_COMMUNICATION . ' jours n\'ont')
                . ' aucune action de communication enregistrée : ' . implode(', ', $titres) . (count($sans) > 4 ? '…' : '') . '.', 'page=editorial#concerts');
        }
        $maintenant = date('Y-m-d H:i:s');
        foreach (bd_lignes("SELECT * FROM communications WHERE statut <> 'publiee' AND prevue_le IS NOT NULL AND prevue_le <= ? ORDER BY prevue_le", [ajouter_jours($jour, DELAI_PREPARATION) . ' 23:59:59']) as $k) {
            $nom = (CANAUX[$k['canal']][0] ?? 'Action') . ' · ' . communication_libelle($k);
            $quand = date_fr(substr($k['prevue_le'], 0, 10));
            if ($k['prevue_le'] < $maintenant) {
                $ajouter('publication', 'k' . $k['id'], $nom . ' : prévue le ' . $quand . ', la date est passée. Publiez-la, ou reportez-la.', 'page=communication&id=' . $k['id'], 'prioritaire');
            } elseif ($k['statut'] === 'a_preparer') {
                $ajouter('publication', 'k' . $k['id'], $nom . ' : à préparer pour le ' . $quand . '.', 'page=communication&id=' . $k['id']);
            }
        }
    }

    // anomalies techniques : une tâche de fond qui a échoué sans raison lisible, ou qui a été interrompue
    foreach (bd_lignes("SELECT id, type, message, fin_le FROM taches WHERE statut = 'echec' AND fin_le >= ? AND (message LIKE 'La tâche a échoué%' OR message LIKE 'Interrompue%') ORDER BY id DESC LIMIT 5",
                       [date('Y-m-d H:i:s', time() - 3 * 86400)]) as $t) {
        $ajouter('technique', 't' . $t['id'], 'Une tâche de fond (' . $t['type'] . ') s\'est arrêtée le ' . horodatage_fr($t['fin_le']) . ' : ' . $t['message'], 'page=journal', 'prioritaire');
    }

    // aperçus : une validation donnée (on peut publier), des corrections demandées, une décision qui se fait attendre
    if (bd_table_existe('apercus_validations')) {
        $publie = (string) (bd_valeur("SELECT MAX(cree_le) FROM publications WHERE nature = 'publication' AND annulee = 0") ?: '1970-01-01 00:00:00');
        foreach (bd_lignes("SELECT v.*, a.titre, a.expire_le, a.retire_le FROM apercus_validations v JOIN apercus a ON a.id = v.apercu_id WHERE v.cree_le >= ? ORDER BY v.id", [date('Y-m-d H:i:s', time() - 60 * 86400)]) as $v) {
            $lien = 'page=apercu&id=' . (int) $v['apercu_id'];
            if ($v['decision'] === 'validee' && $v['decide_le'] > $publie) {
                $ajouter('apercu', 'v' . $v['id'], 'L\'aperçu « ' . $v['titre'] . ' » a été validé par ' . $v['nom'] . ' le ' . horodatage_fr($v['decide_le']) . ' : le site peut être publié.', $lien);
            } elseif ($v['decision'] === 'corrections' && $v['decide_le'] > $publie) {
                $ajouter('apercu', 'v' . $v['id'], $v['nom'] . ' demande des corrections sur l\'aperçu « ' . $v['titre'] . ' »' . (trim((string) $v['remarques']) !== '' ? ' : ' . couper(preg_replace('/\s+/u', ' ', (string) $v['remarques']), 200) : '.'), $lien);
            } elseif ($v['decision'] === 'attente' && $v['retire_le'] === null && $v['expire_le'] > date('Y-m-d H:i:s') && strtotime($v['expire_le']) < time() + 2 * 86400) {
                $ajouter('apercu', 'v' . $v['id'], 'L\'aperçu « ' . $v['titre'] . ' » expire le ' . horodatage_fr($v['expire_le']) . ' et ' . $v['nom'] . ' ne s\'est pas encore prononcé.', $lien, 'info');
            }
        }
    }

    // zones de dépôt : des fichiers viennent d'arriver ; un concert tout proche n'a pas de contact « communication »
    if (bd_table_existe('depots_fichiers')) {
        foreach (bd_lignes("SELECT d.concert_id, c.titre_en, COUNT(*) AS n, MAX(f.depose_le) AS dernier FROM depots_fichiers f JOIN depots d ON d.id = f.depot_id JOIN concerts c ON c.id = d.concert_id
                            WHERE f.statut = 'recu' GROUP BY d.concert_id, c.titre_en") as $z) {
            $ajouter('depot', 'c' . $z['concert_id'], pluriel((int) $z['n'], 'fichier déposé attend', 'fichiers déposés attendent') . ' d\'être vu pour « ' . $z['titre_en'] . ' » (dernier dépôt le ' . horodatage_fr($z['dernier']) . ').', 'page=depot&concert=' . (int) $z['concert_id']);
        }
        foreach (bd_lignes("SELECT c.id, c.titre_en, MIN(r.date) AS premiere FROM concerts c JOIN representations r ON r.concert_id = c.id
                            WHERE r.statut <> 'annulee' AND c.type <> 'disque' AND c.contact_com_email = '' GROUP BY c.id, c.titre_en HAVING MIN(r.date) BETWEEN ? AND ?", [$jour, ajouter_jours($jour, 7)]) as $x) {
            $ajouter('depot', 'sans' . $x['id'], '« ' . $x['titre_en'] . ' » (' . date_fr($x['premiere']) . ') n\'a pas de contact communication : personne ne sera invité à déposer des images.', 'page=concert&id=' . (int) $x['id'], 'info');
        }
    }

    // veille : des signalements attendent d'être lus
    if (bd_colonne_existe('veille', 'empreinte')) {
        $a_lire = (int) bd_valeur("SELECT COUNT(*) FROM veille WHERE statut = 'a_lire'");
        if ($a_lire) $ajouter('veille', 'a_lire', pluriel($a_lire, 'signalement de la veille attend', 'signalements de la veille attendent') . ' d\'être lu' . ($a_lire > 1 ? 's' : '') . '.', 'page=veille', 'info');
    }

    // courrier : des retours attendent ; un envoi n'a pas été remis
    if (bd_table_existe('courriers')) {
        foreach (bd_lignes("SELECT type_retour, COUNT(*) AS n FROM courriers WHERE sens = 'recu' AND statut = 'a_lire' GROUP BY type_retour") as $r) {
            if ($r['type_retour'] === 'rejet') $ajouter('courrier', 'rejets', pluriel((int) $r['n'], 'mail envoyé par Pupitre n\'a pas été remis', 'mails envoyés par Pupitre n\'ont pas été remis') . ' : adresse à vérifier.', 'page=courrier');
            elseif ($r['type_retour'] === 'reponse') $ajouter('courrier', 'reponses', pluriel((int) $r['n'], 'réponse attend', 'réponses attendent') . ' dans le courrier.', 'page=courrier');
            else $ajouter('courrier', $r['type_retour'], pluriel((int) $r['n'], 'retour de mail à lire', 'retours de mail à lire') . ' (' . mb_strtolower(TYPES_RETOUR[$r['type_retour']][0] ?? 'autre') . ').', 'page=courrier', 'info');
        }
    }

    // dernier extrait de l'agence
    $dernier = import_dernier();
    if (!$dernier) {
        $ajouter('extrait', 'aucun', 'Aucun extrait de l\'agence n\'a encore été importé.', 'page=imports', 'info');
    } elseif (strtotime($dernier['cree_le']) < time() - DELAI_EXTRAIT * 86400) {
        $ajouter('extrait', 'ancien', 'Le dernier extrait de l\'agence a été importé le ' . date('d/m/Y', strtotime($dernier['cree_le'])) . ', il y a plus de ' . DELAI_EXTRAIT . ' jours.', 'page=imports', 'info');
    }

    // contenus du site restés vides
    $noms = fn(array $l) => implode(', ', array_map(fn($x) => preg_replace('/\*/', '', (string) $x), $l));
    $vides = bd_colonne("SELECT d.titre FROM disques d WHERE d.publie = 1 AND d.lien = '' AND NOT EXISTS (SELECT 1 FROM disques_liens l WHERE l.disque_id = d.id) ORDER BY d.ordre, d.id");
    if ($vides) $ajouter('vide', 'disques', (count($vides) > 1 ? 'Albums sans lien d\'écoute ni de commande : ' : 'Album sans lien d\'écoute ni de commande : ') . $noms($vides) . '.', 'page=disques', 'info');
    $vides = bd_colonne("SELECT COALESCE(a.nom, p.titre_en) FROM articles p LEFT JOIN annuaire a ON a.id = p.media_id WHERE p.publie = 1 AND p.exemple = 0 AND NOT EXISTS (SELECT 1 FROM articles_citations c WHERE c.article_id = p.id) ORDER BY p.ordre, p.id");
    if ($vides) $ajouter('vide', 'articles', (count($vides) > 1 ? 'Articles sans citation : ' : 'Article sans citation : ') . $noms($vides) . '.', 'page=presse', 'info');
    // faux articles de maquette : à supprimer avant la mise en production
    $exemples = (int) bd_valeur('SELECT COUNT(*) FROM articles WHERE exemple = 1');
    if ($exemples) $ajouter('vide', 'exemples', pluriel($exemples, 'faux article de maquette est', 'faux articles de maquette sont') . ' dans la revue de presse : à supprimer avant la mise en production.', 'page=presse', 'info');
    // citations proposées par l'IA, jamais vérifiées par une personne, sur des articles publiés
    $douteuses = bd_colonne("SELECT DISTINCT COALESCE(a.nom, p.titre_en) FROM articles_citations c JOIN articles p ON p.id = c.article_id LEFT JOIN annuaire a ON a.id = p.media_id WHERE c.verifiee = 0 AND p.publie = 1");
    if ($douteuses) $ajouter('vide', 'citations', 'Citations à vérifier dans l\'article d\'origine : ' . $noms($douteuses) . '.', 'page=presse');
    // traductions proposées par l'IA : elles ne paraissent pas tant qu'elles ne sont pas validées
    foreach (bd_lignes("SELECT langue, COUNT(*) AS n FROM traductions WHERE statut = 'proposee' GROUP BY langue ORDER BY langue") as $t) {
        $ajouter('vide', 'traductions-' . $t['langue'], pluriel((int) $t['n'], 'traduction proposée par l\'IA attend', 'traductions proposées par l\'IA attendent') . ' une validation (' . mb_strtolower(langue_nom($t['langue'])) . ') : elles ne paraissent pas sur le site d\'ici là.',
            'page=traductions&langue=' . $t['langue'] . '&statut=proposee');
    }
    // traductions dont le texte anglais a changé depuis : elles paraissent toujours, mais ne disent peut-être plus la même chose
    foreach (langues_autres() as $code) {
        $n = traductions_decompte(array_filter(traductions_inventaire($code, null, true), fn($x) => $x['section'] !== ''))['a_revoir'];
        if ($n) $ajouter('vide', 'traductions-revoir-' . $code, pluriel($n, 'traduction est à revoir', 'traductions sont à revoir') . ' (' . mb_strtolower(langue_nom($code)) . ') : le texte anglais a changé depuis.',
            'page=traductions&langue=' . $code . '&statut=a_revoir', 'info');
    }
    // une ressource sans fichier ni adresse porte la mention « à venir »
    $vides = bd_colonne("SELECT libelle_en FROM telechargements WHERE media_id IS NULL AND url = '' AND publie = 1 ORDER BY ordre, id");
    if ($vides) $ajouter('vide', 'telechargements', (count($vides) > 1 ? 'Téléchargements « à venir » : ' : 'Téléchargement « à venir » : ') . $noms($vides) . '.', 'page=contenu&type=telechargements', 'info');
    $vides = bd_colonne('SELECT p.ville_en FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE a.nom_provisoire = 1 ORDER BY p.ordre');
    if ($vides) $ajouter('vide', 'orchestres', 'Carte du monde : nom de l\'orchestre à fournir pour ' . $noms($vides) . '.', 'page=carte', 'info');
    foreach (TEXTES as $cle => [$libelle]) {
        if (trim((string) bd_valeur('SELECT ' . bd_nom(colonne_reference('textes', 'texte')) . ' FROM textes WHERE cle = ?', [$cle])) === '') $ajouter('vide', 'texte-' . $cle, 'Texte vide : ' . mb_strtolower($libelle) . '.', 'page=blocs', 'info');
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
    foreach ($calculees as [$type, $cle, $message, $lien, $niveau]) {
        if (isset($connues[$cle])) {
            $maj = ['message' => $message, 'lien' => $lien, 'niveau' => $niveau, 'maj_le' => date('Y-m-d H:i:s')];
            // un report arrivé à son terme rouvre l'alerte ; une alerte ignorée dont le sujet a changé aussi
            if ($connues[$cle]['etat'] === 'reportee' && $connues[$cle]['reportee_au'] <= aujourdhui()) $maj += ['etat' => 'ouverte', 'reportee_au' => null];
            if ($connues[$cle]['etat'] === 'ignoree' && $connues[$cle]['message'] !== $message) $maj['etat'] = 'ouverte';
            if ($connues[$cle]['message'] !== $message || $connues[$cle]['lien'] !== $lien || ($connues[$cle]['niveau'] ?? '') !== $niveau || isset($maj['etat'])) bd_modifier('alertes', $maj, ['id' => $connues[$cle]['id']]);
        } else {
            bd_inserer('alertes', ['type' => $type, 'niveau' => $niveau, 'cle' => $cle, 'message' => $message, 'lien' => $lien]);
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
    $niveau = array_flip(array_keys(NIVEAUX_ALERTE));
    usort($lignes, fn($x, $y) => [$niveau[$x['niveau'] ?? 'action'] ?? 1, $rang[$x['type']] ?? 99, $x['id']] <=> [$niveau[$y['niveau'] ?? 'action'] ?? 1, $rang[$y['type']] ?? 99, $y['id']]);
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
