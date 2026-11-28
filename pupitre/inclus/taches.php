<?php
/**
 * Tâches de fond : une table, exécutée par cron/taches.php.
 *
 * Une page dépose une tâche (tache_creer) et suit son avancement (ajax/tache.php). Le cron les
 * exécute une à une. Sur le poste local, le bouton « Lancer maintenant » démarre le même script
 * en arrière-plan : une requête web ne porte jamais un travail long.
 */

defined('PUPITRE') or exit;

/** Types de tâches : type => fonction qui l'exécute (elle reçoit la tâche, sa charge décodée). */
const TACHES = [
    'import_lecture' => 'import_tache',
    'analyse_son' => 'analyse_tache',
    'traduction' => 'traduction_tache',
    'article_lecture' => 'article_tache',
    'metricool_releve' => 'metricool_tache',
    'annuaire_completer' => 'annuaire_completer_tache',
    'veille' => 'veille_tache',
    'courrier' => 'courrier_tache',
];

const TACHE_DELAI = 1200;      // une tâche « en cours » depuis vingt minutes est tenue pour interrompue

function tache_creer(string $type, array $charge): int
{
    $c = en_cli() ? null : compte();
    return bd_inserer('taches', [
        'type' => $type,
        'charge' => json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'message' => 'En attente',
        'cree_par' => $c['id'] ?? null,
    ]);
}

function tache_lire(int $id): ?array
{
    return bd_ligne('SELECT * FROM taches WHERE id = ?', [$id]);
}

/** Avancement d'une tâche, de 0 à 100, avec un message court pour l'écran. */
function tache_avancer(int $id, int $pourcent, string $message): void
{
    bd_modifier('taches', ['avancement' => max(0, min(100, $pourcent)), 'message' => mb_substr($message, 0, 500)], ['id' => $id]);
}

/** Exécute les tâches en attente, une à une. Rend [[id, type, statut, message], …]. */
function taches_executer(int $max = 10): array
{
    // un seul exécutant à la fois
    $verrou = fopen(dossier(STOCKAGE . '/journaux') . '/taches.verrou', 'c');
    if (!$verrou || !flock($verrou, LOCK_EX | LOCK_NB)) return [];
    $faites = [];
    try {
        bd_requete("UPDATE taches SET statut = 'echec', message = 'Interrompue : relancez-la.', fin_le = NOW()
                    WHERE statut = 'en_cours' AND debut_le < ?", [date('Y-m-d H:i:s', time() - TACHE_DELAI)]);
        while ($max-- > 0) {
            $t = bd_ligne("SELECT * FROM taches WHERE statut = 'en_attente' ORDER BY id LIMIT 1");
            if (!$t) break;
            $pris = bd_requete("UPDATE taches SET statut = 'en_cours', debut_le = NOW(), message = 'En cours' WHERE id = ? AND statut = 'en_attente'", [$t['id']])->rowCount();
            if (!$pris) continue;
            $t['charge'] = json_decode((string) $t['charge'], true) ?: [];
            $statut = 'terminee';
            $message = 'Terminée';
            try {
                $fonction = TACHES[$t['type']] ?? null;
                if (!$fonction) throw new RuntimeException('Type de tâche inconnu.');
                $retour = $fonction($t);
                if (is_string($retour) && $retour !== '') $message = $retour;
            } catch (Throwable $e) {
                $statut = 'echec';
                // un message lisible pour l'écran ; le détail technique va au journal, sans contenu
                $message = $e instanceof ErreurLisible ? $e->getMessage() : 'La tâche a échoué. Le détail est dans le journal de Pupitre.';
                journal_fichier('erreurs', 'tâche ' . $t['id'] . ' (' . $t['type'] . ') : ' . get_class($e) . ' ' . ($e instanceof ErreurLisible ? $e->getMessage() : basename($e->getFile()) . ':' . $e->getLine()));
            }
            bd_modifier('taches', ['statut' => $statut, 'message' => mb_substr($message, 0, 500), 'avancement' => 100, 'fin_le' => date('Y-m-d H:i:s')], ['id' => $t['id']]);
            $faites[] = [(int) $t['id'], $t['type'], $statut, $message];
        }
    } finally {
        flock($verrou, LOCK_UN);
        fclose($verrou);
    }
    return $faites;
}

/** Une erreur dont le message peut être montré tel quel à l'utilisateur. */
class ErreurLisible extends RuntimeException {}

/**
 * Chemin de PHP en ligne de commande : PHP_CLI, sinon celui qui accompagne ce PHP, sinon un PHP voisin.
 *
 * Le PHP du serveur web n'a pas toujours son binaire en ligne de commande (un MAMP dont la version
 * servie n'a que « php-cgi », un .env recopié d'un autre poste) : on cherche alors, dans le même
 * dossier d'installation, la version la plus récente qui en a un, puis les emplacements d'usage.
 * Rend '' si rien ne convient : les tâches attendront le cron.
 */
function php_cli(): string
{
    static $trouve = null;
    if ($trouve !== null) return $trouve;
    $convient = fn($c) => is_string($c) && $c !== '' && is_file($c) && is_executable($c) && !str_contains(basename($c), 'cgi') && !str_contains(basename($c), 'fpm');
    $p = env('PHP_CLI');
    if ($convient($p)) return $trouve = $p;
    foreach ([PHP_BINDIR . '/php', PHP_BINARY] as $c) {
        if ($convient($c)) return $trouve = $c;
    }
    // les versions voisines (MAMP, Homebrew, phpenv : …/php8.3.14/bin, …/php8.4.1/bin), la plus récente d'abord
    $voisins = glob(dirname(PHP_BINDIR, 2) . '/*/bin/php') ?: [];
    usort($voisins, fn($a, $b) => version_compare(preg_replace('/[^0-9.]/', '', basename(dirname($b, 2))) ?: '0', preg_replace('/[^0-9.]/', '', basename(dirname($a, 2))) ?: '0'));
    foreach (array_merge($voisins, ['/opt/homebrew/bin/php', '/usr/local/bin/php', '/usr/bin/php']) as $c) {
        if ($convient($c)) return $trouve = $c;
    }
    return $trouve = '';
}

/**
 * Démarre cron/taches.php en arrière-plan (bouton « Lancer maintenant », dépôt d'un extrait).
 * Rend faux si le poste ne le permet pas : la tâche attendra alors le cron.
 */
function taches_lancer_en_fond(): bool
{
    $php = php_cli();
    if ($php === '' || !function_exists('exec')) return false;
    $commande = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(RACINE . '/cron/taches.php') . ' > /dev/null 2>&1 &';
    @exec($commande, $sortie, $code);
    return $code === 0;
}

/** Y a-t-il des tâches en attente ou en cours ? */
function taches_en_cours(): int
{
    return (int) bd_valeur("SELECT COUNT(*) FROM taches WHERE statut IN ('en_attente', 'en_cours')");
}

/**
 * Travaux réguliers : ce qui se fait dans la journée, sans attendre la nuit. Les aperçus expirés sont
 * retirés ; les invitations à déposer des images partent la veille des concerts. Appelés à chaque
 * passage du cron et, sur un poste sans cron, à l'ouverture du tableau de bord (au plus une fois par
 * quart d'heure, sauf $forcer). Rend ce qui a été fait : ['apercus' => n, 'invitations' => n].
 */
function travaux_reguliers(bool $forcer = false): array
{
    try {
        if (!$forcer && (int) reglage('travaux_le') > time() - 900) return [];
        reglage_ecrire('travaux_le', (string) time());
        return array_filter(['apercus' => apercus_purger(), 'invitations' => depots_inviter_veille()]);
    } catch (Throwable $e) {
        journal_fichier('erreurs', 'travaux réguliers : ' . $e->getMessage());
        return [];
    }
}

/**
 * Travaux de nuit : alertes recalculées (embargos arrivés à échéance, dates passées…).
 * Une fois par jour, sauf $forcer. Rend vrai s'ils ont tourné.
 */
function travaux_de_nuit(bool $forcer = false): bool
{
    if (!$forcer && reglage('nuit_le') === date('Y-m-d')) return false;
    alertes_recalculer();
    bd_requete('DELETE FROM tentatives_connexion WHERE cree_le < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
    bd_requete('DELETE FROM liens_connexion WHERE expire_le < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    foreach (glob(STOCKAGE . '/imports/article-*.json') ?: [] as $f) if (filemtime($f) < time() - 2 * 86400) @unlink($f);     // lectures d'articles : des brouillons
    // le texte d'un article collé pour une lecture n'est pas gardé dans la file des tâches une fois la lecture finie
    bd_requete("UPDATE taches SET charge = NULL WHERE type = 'article_lecture' AND statut IN ('terminee', 'echec') AND fin_le < ?", [date('Y-m-d H:i:s', time() - 2 * 86400)]);
    // la veille : une collecte par nuit (flux d'actualités ; la recherche de l'IA, payante, deux fois par semaine)
    try {
        if (bd_colonne_existe('veille', 'empreinte') && veille_requetes()) tache_creer('veille', ['jours' => 14, 'ia' => in_array((int) date('N'), [1, 4], true)]);
        if (function_exists('metricool_configure') && metricool_configure() && metricool_marque()['id'] !== '' && bd_table_existe('posts_sociaux')) tache_creer('metricool_releve', ['jours' => 60]);
    } catch (Throwable $e) {
        journal_fichier('erreurs', 'travaux de nuit : ' . $e->getMessage());
    }
    reglage_ecrire('nuit_le', date('Y-m-d'));
    return true;
}

// ---------- Analyse d'une bande-son (outils/analyse-bande-son.py du site) ----------

/** Chemin d'un outil : la variable d'environnement, sinon le PATH et les emplacements courants. */
function outil(string $nom, string $variable): string
{
    $p = env($variable);
    if ($p !== '' && is_executable($p)) return $p;
    foreach (array_merge(explode(PATH_SEPARATOR, (string) getenv('PATH')), ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin']) as $d) {
        if ($d !== '' && is_executable($d . '/' . $nom)) return $d . '/' . $nom;
    }
    return '';
}

/** La commande d'analyse d'un fichier son ou vidéo, pour l'exécuter ou pour l'afficher. */
function analyse_commande(string $source, string $sortie, string $python = 'python3'): string
{
    $racine = site_racine();
    return escapeshellarg($python) . ' ' . escapeshellarg($racine . '/outils/analyse-bande-son.py') . ' ' . escapeshellarg($source) . ' ' . escapeshellarg($sortie);
}

/** L'analyse peut-elle tourner ici ? Rend '' si oui, sinon ce qui manque. */
function analyse_possible(): string
{
    if (outil('python3', 'PYTHON3') === '') return 'python3 est introuvable';
    if (outil('ffmpeg', 'FFMPEG') === '') return 'ffmpeg est introuvable';
    if (!function_exists('exec')) return 'ce serveur ne permet pas de lancer un outil';
    $racine = site_racine();
    if ($racine === '' || !is_file($racine . '/outils/analyse-bande-son.py')) return 'outils/analyse-bande-son.py est introuvable dans le site';
    return '';
}

/**
 * Tâche : analyse la bande-son d'un média et range le résultat dans stockage/analyses/.
 * Charge : media_id, table (musiques, ou medias_usages pour une vidéo de bloc visuel), cle (ligne à mettre à jour), nom (du fichier d'analyse).
 */
function analyse_tache(array $t): string
{
    $manque = analyse_possible();
    if ($manque !== '') throw new ErreurLisible('Analyse impossible : ' . $manque . '.');
    $m = bd_ligne('SELECT * FROM medias WHERE id = ?', [(int) ($t['charge']['media_id'] ?? 0)]);
    $source = $m ? media_fichier($m) : '';
    if ($source === '') throw new ErreurLisible('Le fichier à analyser est introuvable.');
    $nom = slug((string) ($t['charge']['nom'] ?? 'piste')) ?: 'piste';
    $sortie = dossier(STOCKAGE . '/analyses') . '/' . $nom . '.json';
    tache_avancer((int) $t['id'], 20, 'Analyse de la bande-son');
    $ffmpeg = dirname(outil('ffmpeg', 'FFMPEG'));
    $commande = 'PATH=' . escapeshellarg($ffmpeg . PATH_SEPARATOR . getenv('PATH')) . ' ' . analyse_commande($source, $sortie, outil('python3', 'PYTHON3')) . ' 2>&1';
    exec($commande, $lignes, $code);
    if ($code !== 0 || !is_file($sortie) || json_decode((string) file_get_contents($sortie)) === null) {
        journal_fichier('erreurs', 'analyse-bande-son : code ' . $code . ' · ' . mb_substr(implode(' / ', array_slice($lignes, -3)), 0, 300));
        throw new ErreurLisible('L\'analyse de la bande-son a échoué (code ' . $code . ').');
    }
    $piste = 'data/' . $nom . '.json';
    if (($t['charge']['table'] ?? '') === 'musiques') bd_modifier('musiques', ['piste' => $piste], ['id' => (int) $t['charge']['cle']]);
    if (($t['charge']['table'] ?? '') === 'medias_usages') {
        // élément d'un bloc visuel : l'analyse se range dans ses réglages
        $e = bd_ligne('SELECT * FROM medias_usages WHERE id = ?', [(int) $t['charge']['cle']]);
        if ($e) {
            $p = json_decode((string) $e['parametres'], true) ?: [];
            $p['piste'] = $piste;
            bd_modifier('medias_usages', ['parametres' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id' => $e['id']]);
        }
    }
    journaliser('modification', 'contenu', null, 'Analyse de bande-son : ' . $nom, ['piste' => ''], ['piste' => $piste]);
    return 'Analyse terminée : ' . $piste;
}
