<?php
/**
 * Places gardées pour les versions suivantes. Rien n'est construit ici : le menu et la base
 * (sql/schema.sql, « Places gardées ») attendent ces modules.
 */

defined('PUPITRE') or exit;

$modules = [
    'publications' => ['Publications', 'Rédaction des publications pour les réseaux à partir de l\'agenda, validation, puis envoi par Metricool. L\'annuaire porte déjà les comptes sociaux et les mots-dièse dont elles auront besoin.', 'publications_reseaux'],
    'lettre' => ['Lettre', 'Inscription depuis le site (le formulaire du site reste inactif), liste des inscrits, envoi mensuel des concerts et des actualités.', 'lettre_inscrits'],
    'veille' => ['Veille et presse', 'Veille des parutions et revue de presse automatique, proposées à la validation avant d\'entrer dans l\'écran « Presse ». Liens de dépôt pour les photographes.', 'veille, depots'],
    'statistiques' => ['Statistiques', 'Fréquentation du site, portée des publications, suivi par concert.', ''],
];
$quoi = recu('quoi');
$m = $modules[$quoi] ?? null;
$titre = $m[0] ?? 'À venir';
?>
<div class="titre"><h1><?= e($titre) ?></h1><span class="titre__suite">à venir</span></div>
<div class="carte">
  <?php if ($m): ?>
    <p><?= e($m[1]) ?></p>
    <p class="aide">Hors de cette version.<?= $m[2] !== '' ? ' Place gardée dans la base : ' . e($m[2]) . '.' : '' ?></p>
  <?php else: ?>
    <ul class="items"><?php foreach ($modules as $k => $x): ?><li><a class="item__titre" href="<?= e(url('avenir', ['quoi' => $k])) ?>"><?= e($x[0]) ?></a><span><?= e($x[1]) ?></span></li><?php endforeach; ?></ul>
  <?php endif; ?>
</div>
