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
    'textes' => ['contenus/textes.php', 'contenus'],
    'contenu' => ['contenus/liste.php', 'contenus'],
    'orchestres' => ['contenus/orchestres.php', 'contenus'],
    'visuels' => ['contenus/visuels.php', 'contenus'],
    'musiques' => ['contenus/musiques.php', 'contenus'],
    'medias' => ['mediatheque/liste.php', 'medias'],
    'media' => ['mediatheque/media.php', 'medias'],
    'site' => ['site/site.php', 'site.voir'],
    'journal' => ['journal/journal.php', 'journal'],
    'comptes' => ['reglages/comptes.php', 'comptes'],
    'reglages' => ['reglages/reglages.php', 'reglages'],
    'artiste' => ['artiste/vue.php', 'artiste.vue'],
    'avenir' => ['avenir.php', 'bord'],
];

$compte = exiger_connexion();
$page = recu('page') ?: ($compte['role'] === 'artiste' ? 'artiste' : 'bord');
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
    if (est_post()) verifier_csrf();
    $titre = 'Pupitre';
    ob_start();
    require RACINE . '/pages/' . $fichier;
    $corps = ob_get_clean();
}
$page_courante = $page;
require RACINE . '/gabarits/entete.php';
echo $corps;
require RACINE . '/gabarits/pied.php';
