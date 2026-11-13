<?php
/**
 * En-tête commun : titre « Pupitre », sous-titre lu dans les réglages, menu selon les droits.
 * Attend $titre (titre de la page) et, facultatif, $page_courante.
 */

defined('PUPITRE') or exit;

$compte_courant = compte();
$page_courante = $page_courante ?? '';
$type_courant = $_GET['type'] ?? '';
$sous_titre = reglage('sous_titre') ?: reglage('artiste_nom');
$base = chemin_pupitre();
$artiste_seul = $compte_courant && $compte_courant['role'] === 'artiste';

/* Le menu : [libellé, page, paramètres, droit, pages qui allument l'entrée]. Une entrée sans page est une place gardée. */
$menu = [
    '' => [
        ['Tableau de bord', 'bord', [], 'bord', ['bord']],
        ['Agenda', 'agenda', [], 'agenda', ['agenda', 'concert']],
        ['Annuaire', 'annuaire', [], 'annuaire', ['annuaire', 'fiche']],
        ['Extrait de l\'agence', 'imports', [], 'import', ['imports', 'import']],
    ],
    'Contenus du site' => [
        ['Textes', 'textes', [], 'contenus', ['textes']],
        ['Parcours', 'contenu', ['type' => 'parcours'], 'contenus', ['contenu:parcours']],
        ['Orchestres invités', 'orchestres', [], 'contenus', ['orchestres']],
        ['Disques', 'contenu', ['type' => 'disques'], 'contenus', ['contenu:disques']],
        ['Presse', 'contenu', ['type' => 'presse'], 'contenus', ['contenu:presse']],
        ['Visuels', 'visuels', [], 'contenus', ['visuels']],
        ['Musiques', 'musiques', [], 'contenus', ['musiques']],
        ['Téléchargements', 'contenu', ['type' => 'telechargements'], 'contenus', ['contenu:telechargements']],
        ['Contacts et réseaux', 'contenu', ['type' => 'contacts'], 'contenus', ['contenu:contacts']],
        ['Médiathèque', 'medias', [], 'medias', ['medias', 'media']],
        ['Le site', 'site', [], 'site.voir', ['site']],
    ],
    'À venir' => [
        ['Publications', 'avenir', ['quoi' => 'publications'], 'bord', ['avenir:publications']],
        ['Lettre', 'avenir', ['quoi' => 'lettre'], 'bord', ['avenir:lettre']],
        ['Veille et presse', 'avenir', ['quoi' => 'veille'], 'bord', ['avenir:veille']],
        ['Statistiques', 'avenir', ['quoi' => 'statistiques'], 'bord', ['avenir:statistiques']],
    ],
    'Pupitre' => [
        ['Vue de l\'artiste', 'artiste', [], 'bord', ['artiste']],
        ['Journal', 'journal', [], 'journal', ['journal']],
        ['Comptes', 'comptes', [], 'comptes', ['comptes']],
        ['Réglages', 'reglages', [], 'reglages', ['reglages']],
    ],
];
$repere = $page_courante . ($page_courante === 'contenu' ? ':' . $type_courant : '') . ($page_courante === 'avenir' ? ':' . ($_GET['quoi'] ?? '') : '');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf" content="<?= e(jeton_csrf()) ?>">
  <meta name="base" content="<?= e($base) ?>">
  <title><?= e($titre ?? 'Pupitre') ?> · Pupitre<?= $sous_titre !== '' ? ' · ' . e($sous_titre) : '' ?></title>
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="<?= e($base) ?>/ressources/css/pupitre.css?v=<?= e(VERSION_PUPITRE) ?>-<?= (int) @filemtime(RACINE . '/ressources/css/pupitre.css') ?>">
  <script src="<?= e($base) ?>/ressources/js/pupitre.js?v=<?= e(VERSION_PUPITRE) ?>-<?= (int) @filemtime(RACINE . '/ressources/js/pupitre.js') ?>" defer></script>
</head>
<body class="<?= $artiste_seul ? 'vue-artiste' : ($compte_courant ? 'avec-menu' : 'sans-menu') ?>">
<a class="evitement" href="#contenu">Aller au contenu</a>
<header class="tete">
  <a class="marque" href="<?= e($base) ?>/index.php">
    <span class="marque__nom">Pupitre</span>
    <?php if ($sous_titre !== ''): ?><span class="marque__sous-titre"><?= e($sous_titre) ?></span><?php endif; ?>
  </a>
  <?php if ($compte_courant): ?>
  <div class="tete__compte">
    <span class="tete__nom"><?= e($compte_courant['nom']) ?> <span class="tete__role"><?= e(ROLES[$compte_courant['role']]) ?></span></span>
    <form method="post" action="<?= e($base) ?>/deconnexion.php"><?= champ_csrf() ?><button type="submit" class="lien-bouton">Se déconnecter</button></form>
  </div>
  <?php if (!$artiste_seul): ?><button type="button" class="tete__menu" data-ouvrir-menu aria-expanded="false" aria-controls="menu">Menu</button><?php endif; ?>
  <?php endif; ?>
</header>
<?php if ($compte_courant && !$artiste_seul): ?>
<nav class="menu" id="menu" aria-label="Menu principal">
  <?php foreach ($menu as $groupe => $entrees): ?>
    <?php $visibles = array_filter($entrees, fn($x) => peut($x[3])); if (!$visibles) continue; ?>
    <?php if ($groupe !== ''): ?><p class="menu__groupe"><?= e($groupe) ?></p><?php endif; ?>
    <ul>
      <?php foreach ($visibles as [$libelle, $page, $params, $droit, $allume]): ?>
        <li><a href="<?= e(url($page, $params)) ?>"<?= in_array($repere, $allume, true) ? ' aria-current="page"' : '' ?><?= $page === 'avenir' ? ' class="menu__avenir"' : '' ?>><?= e($libelle) ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<main class="contenu" id="contenu" tabindex="-1">
<?php foreach (messages_lire() as [$type, $texte]): ?>
  <p class="message message--<?= e($type) ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>"><?= e($texte) ?></p>
<?php endforeach; ?>
