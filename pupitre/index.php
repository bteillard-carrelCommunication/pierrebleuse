<?php
/**
 * Pupitre : point d'entrée de toutes les pages. index.php?page=…
 *
 * Sans connexion, aucune page ne répond. Chaque page a son droit (inclus/droits.php), vérifié ici
 * avant de la charger ; chaque envoi de formulaire porte le jeton anti-CSRF, vérifié ici aussi.
 */

require __DIR__ . '/inclus/amorce.php';

/** Les pages : nom => [fichier dans pages/, droit exigé]. */
const PAGES = [
    'bord' => ['tableau-de-bord.php', 'bord'],
    'agenda' => ['agenda/liste.php', 'agenda'],
    'concert' => ['agenda/concert.php', 'agenda'],
    'annuaire' => ['annuaire/liste.php', 'annuaire'],
    'fiche' => ['annuaire/fiche.php', 'annuaire'],
    'imports' => ['import/liste.php', 'import'],
    'import' => ['import/import.php', 'import'],
    'blocs' => ['blocs/liste.php', 'contenus'],
    'bloc' => ['blocs/bloc.php', 'contenus'],
    'textes' => ['contenus/textes.php', 'contenus'],
    'contenu' => ['contenus/liste.php', 'contenus'],
    'orchestres' => ['contenus/orchestres.php', 'contenus'],
    'disques' => ['discographie/liste.php', 'contenus'],
    'disque' => ['discographie/disque.php', 'contenus'],
    'distinctions' => ['discographie/distinctions.php', 'contenus'],
    'parcours' => ['parcours/parcours.php', 'contenus'],
    'presse' => ['presse/liste.php', 'contenus'],
    'article' => ['presse/article.php', 'contenus'],
    'visuels' => ['contenus/visuels.php', 'contenus'],
    'musiques' => ['contenus/musiques.php', 'contenus'],
    'medias' => ['mediatheque/liste.php', 'medias'],
    'media' => ['mediatheque/media.php', 'medias'],
    'site' => ['site/site.php', 'site.voir'],
    'journal' => ['journal/journal.php', 'journal'],
    'comptes' => ['reglages/comptes.php', 'comptes'],
    'reglages' => ['reglages/reglages.php', 'reglages'],
    'apparence' => ['reglages/apparence.php', 'reglages'],
    'langues' => ['langues/langues.php', 'contenus'],
    'base' => ['reglages/base.php', 'reglages'],
    'artiste' => ['artiste/vue.php', 'artiste.vue'],
    'carte' => ['contenus/orchestres.php', 'contenus'],
    'editorial' => ['communication/editorial.php', 'communication'],
    'social' => ['communication/liste.php', 'communication'],
    'newsletter' => ['communication/liste.php', 'communication'],
    'communication' => ['communication/action.php', 'communication'],
    'notifications' => ['notifications.php', 'bord'],
    'recherche' => ['recherche.php', 'bord'],
    'interface' => ['reglages/interface.php', 'reglages'],
    'avenir' => ['avenir.php', 'bord'],
];

$compte = exiger_connexion();
$page = recu('page') ?: ($compte['role'] === 'artiste' ? 'artiste' : 'bord');

// Le code attend une base à niveau : tant que des migrations restent à passer, seul l'écran « Base » répond.
if (migrations_en_attente() && $page !== 'base') {
    if (peut('reglages')) rediriger(url('base'));
    http_response_code(503);
    $titre = 'Mise à niveau';
    $page_courante = '';
    require RACINE . '/gabarits/entete.php';
    echo '<div class="vide"><h1>Pupitre est en cours de mise à niveau</h1><p>La base doit être mise à niveau par un pilote (écran « Base de données »). Revenez dans un instant.</p></div>';
    require RACINE . '/gabarits/pied.php';
    exit;
}
if (!isset(PAGES[$page])) {
    http_response_code(404);
    $page = 'introuvable';
    $titre = 'Page introuvable';
    $corps = '<div class="vide"><h1>Page introuvable</h1><p><a class="bouton" href="' . e(chemin_pupitre()) . '/index.php">Retour</a></p></div>';
} else {
    [$fichier, $droit] = PAGES[$page];
    exiger_droit($droit);
    if (est_post() && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // un envoi plus lourd que ce que PHP accepte arrive vide : on le dit, plutôt qu'un jeton « expiré »
        message('erreur', 'L\'envoi est trop lourd pour ce serveur (' . ini_get('post_max_size') . ' au plus).');
        rediriger(url($page, array_diff_key($_GET, ['page' => 1])));
    }
    if (est_post()) {
        verifier_csrf();
        if (peut('contenus')) traductions_valider_recues();       // cases « valider » des champs traduits, sur tous les écrans
    }
    $titre = 'Pupitre';
    $page_courante = $page;          // retenue avant d'inclure la page : une page peut employer une variable $page pour son propre compte
    ob_start();
    require RACINE . '/pages/' . $fichier;
    $corps = ob_get_clean();
}
$page_courante = $page_courante ?? $page;
require RACINE . '/gabarits/entete.php';
echo $corps;
require RACINE . '/gabarits/pied.php';
