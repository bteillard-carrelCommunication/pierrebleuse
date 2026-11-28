<?php
/**
 * Amorce : chargée par chaque point d'entrée (index.php, connexion.php, ajax/, cron/, sql/).
 * Configuration, base, session, en-têtes de sécurité, gestion des erreurs.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fonctions.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/droits.php';
require_once __DIR__ . '/reglages.php';
require_once __DIR__ . '/langues.php';
require_once __DIR__ . '/sauvegarde.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/journal.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/courrier.php';
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/annuaire.php';
require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/orchestres.php';
require_once __DIR__ . '/medias.php';
require_once __DIR__ . '/contenus.php';
require_once __DIR__ . '/blocs.php';
require_once __DIR__ . '/animations.php';
require_once __DIR__ . '/visuels.php';
require_once __DIR__ . '/presse.php';
require_once __DIR__ . '/discographie.php';
require_once __DIR__ . '/parcours.php';
require_once __DIR__ . '/site/export.php';
require_once __DIR__ . '/site/repli.php';
require_once __DIR__ . '/publication.php';
require_once __DIR__ . '/apercu.php';
require_once __DIR__ . '/depot.php';
require_once __DIR__ . '/taches.php';
require_once __DIR__ . '/ia.php';
require_once __DIR__ . '/ia_services.php';
require_once __DIR__ . '/ia_recherche.php';
require_once __DIR__ . '/veille.php';
require_once __DIR__ . '/import.php';
require_once __DIR__ . '/communication.php';
require_once __DIR__ . '/metricool.php';
require_once __DIR__ . '/alertes.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/icones.php';
require_once __DIR__ . '/navigation.php';
require_once RACINE . '/gabarits/composants.php';

mb_internal_encoding('UTF-8');

// stockage/ est interdit au web : la règle est reposée si elle manque (dossier neuf, hors git)
if (!is_file(dossier(STOCKAGE) . '/.htaccess')) {
    @file_put_contents(STOCKAGE . '/.htaccess', "# Interdit au web : ce dossier ne se lit que depuis PHP ou la ligne de commande.\nRequire all denied\n");
}
error_reporting(E_ALL);
ini_set('display_errors', (en_dev() || en_cli()) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', dossier(STOCKAGE . '/journaux') . '/php.log');
date_default_timezone_set('Europe/Paris');

/** Une erreur non prévue : journalisée (sans secret), et une page lisible. */
set_exception_handler(function (Throwable $e) {
    $prevue = $e instanceof ErreurBase;
    journal_fichier('erreurs', get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    if (en_cli()) {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
        exit(1);
    }
    if (!headers_sent()) http_response_code(500);
    $detail = ($prevue || en_dev()) ? $e->getMessage() : 'Une erreur est survenue. Elle est notée dans le journal de Pupitre.';
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => $detail], JSON_UNESCAPED_UNICODE);
        exit;
    }
    while (ob_get_level() > 0) ob_end_clean();
    // la page d'erreur ne dépend de rien qui puisse être en panne (ni base, ni réglage) : le logotype et la feuille de style, c'est tout
    $base = e(chemin_pupitre());
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Pupitre</title>'
        . '<link rel="icon" href="' . $base . '/ressources/marque/icones/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="' . $base . '/ressources/css/pupitre.css?v=' . e(VERSION_PUPITRE) . '"></head>'
        . '<body class="porte"><main class="porte__carte"><h1 class="marque marque--grande"><img class="marque__logo" src="' . $base . '/ressources/marque/logo/pupitre-logo-principal.svg" alt="Pupitre" width="127" height="44"></h1>'
        . '<p class="message message--erreur" role="alert">' . e($detail) . '</p>'
        . '<p><a class="bouton" href="' . $base . '/index.php">Revenir au tableau de bord</a></p></main></body></html>';
    exit;
});

if (!en_cli()) {
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    // images, sons et vidéos : aussi ceux d'une adresse externe (médias « adresse externe » de la médiathèque, aperçus) ; jamais de script d'ailleurs
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
    session_demarrer();
}

// le fuseau de l'installation (réglage) date les journaux et le tableau de bord
try {
    $fuseau = reglage('fuseau');
    if ($fuseau !== '' && in_array($fuseau, timezone_identifiers_list(), true)) date_default_timezone_set($fuseau);
} catch (Throwable $e) {
    // base pas encore installée
}

/** Après toute modification, les alertes sont recalculées une fois, en fin de requête. */
register_shutdown_function(function () {
    if (empty($GLOBALS['donnees_modifiees'])) return;
    try {
        alertes_recalculer();
    } catch (Throwable $e) {
        journal_fichier('erreurs', 'alertes : ' . $e->getMessage());
    }
});
