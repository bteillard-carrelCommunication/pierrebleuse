<?php
/**
 * Centre de notifications, en pleine page : tout ce qui demande l'attention, par niveau. Les
 * alertes se traitent, se reportent ou s'ignorent ; les validations mènent à l'écran où décider.
 * Les actions sont celles du tableau de bord (pages/tableau-de-bord.php), qui les exécute.
 */

defined('PUPITRE') or exit;

$titre = 'Notifications';
$notifs = notifications_lire();
$decompte = notifications_decompte($notifs);
$niveau = isset(NIVEAUX_ALERTE[recu('niveau')]) ? recu('niveau') : '';
$montrees = $niveau === '' ? $notifs : array_values(array_filter($notifs, fn($n) => $n['niveau'] === $niveau));
$cachees = array_values(array_filter(alertes_lire(true), fn($a) => $a['etat'] !== 'ouverte'));
$descriptions = [
    'prioritaire' => 'Une échéance est passée, ou quelque chose ne fonctionne pas : à traiter sans attendre.',
    'action' => 'Demande une intervention, sans urgence : une validation, une information à compléter, une publication à préparer.',
    'info' => 'À savoir. Rien ne presse : un contenu à actualiser, un rappel.',
];
$boutons = function (array $n): string {
    $s = '<a class="bouton bouton--petit bouton--doux" href="' . e($n['lien']) . '">' . ($n['alerte'] ? 'Traiter' : 'Ouvrir') . '</a>';
    if (!$n['alerte']) return $s;
    foreach (['reporter' => 'Reporter d\'une semaine', 'ignorer' => 'Ignorer'] as $quoi => $libelle) {
        $s .= '<form method="post" action="' . e(url('bord')) . '">' . champ_csrf() . '<input type="hidden" name="action" value="alerte"><input type="hidden" name="retour" value="notifications">'
            . '<input type="hidden" name="id" value="' . (int) $n['alerte'] . '"><input type="hidden" name="quoi" value="' . $quoi . '"><button type="submit" class="bouton bouton--petit">' . $libelle . '</button></form>';
    }
    return $s;
};
?>
<div class="titre">
  <h1>Notifications</h1>
  <span class="titre__suite"><?= $decompte['a_traiter'] ? e(pluriel($decompte['a_traiter'], 'point à traiter', 'points à traiter')) : 'rien à traiter' ?><?= $decompte['info'] ? ' · ' . e(pluriel($decompte['info'], 'information', 'informations')) : '' ?></span>
  <div class="titre__actions">
    <form method="post" action="<?= e(url('bord')) ?>"><?= champ_csrf() ?><input type="hidden" name="retour" value="notifications"><button type="submit" name="action" value="recalculer" class="bouton"><?= icone('actualiser') ?>Recalculer</button></form>
  </div>
</div>

<nav class="onglets" aria-label="Filtrer par niveau">
  <a href="<?= e(url('notifications')) ?>"<?= $niveau === '' ? ' aria-current="page"' : '' ?>>Tout <span class="carte__compte"><?= count($notifs) ?></span></a>
  <?php foreach (NIVEAUX_ALERTE as $k => [$libelle]): ?>
    <a href="<?= e(url('notifications', ['niveau' => $k])) ?>"<?= $niveau === $k ? ' aria-current="page"' : '' ?>><?= e($libelle) ?> <span class="carte__compte"><?= (int) $decompte[$k] ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if (!$montrees): ?>
  <div class="carte"><?= etat_vide('ok', $niveau === '' ? 'Tout est en ordre' : 'Rien à ce niveau', $niveau === '' ? 'Aucune alerte, aucune action à traiter, aucune information.' : '') ?></div>
<?php endif; ?>
<?php foreach (NIVEAUX_ALERTE as $k => [$libelle, $icone]): $du_niveau = array_values(array_filter($montrees, fn($n) => $n['niveau'] === $k)); if (!$du_niveau) continue; ?>
<section class="carte carte--plate" aria-labelledby="n-<?= e($k) ?>">
  <div class="notifs__tete"><h2 id="n-<?= e($k) ?>"><?= e($libelle) ?></h2><span class="carte__compte"><?= count($du_niveau) ?></span></div>
  <p class="aide" style="margin:0;padding:0 1.1rem .8rem"><?= e($descriptions[$k]) ?></p>
  <div class="notifs__liste" style="max-height:none">
    <?php foreach ($du_niveau as $n) echo notification_afficher($n, $boutons($n)); ?>
  </div>
</section>
<?php endforeach; ?>

<?php if ($cachees && $niveau === ''): ?>
<details class="carte"><summary>Alertes reportées ou ignorées (<?= count($cachees) ?>)</summary>
  <ul class="items">
  <?php foreach ($cachees as $a): ?>
    <li><span class="item__titre"><?= e(TYPES_ALERTE[$a['type']] ?? $a['type']) ?></span><span><?= e($a['message']) ?></span>
      <span class="discret"><?= $a['etat'] === 'reportee' ? 'reportée au ' . e(date_fr($a['reportee_au'])) : 'ignorée : elle reviendra si son sujet change' ?></span>
      <form method="post" action="<?= e(url('bord')) ?>" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="alerte"><input type="hidden" name="retour" value="notifications"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="quoi" value="rouvrir"><button type="submit" class="bouton bouton--petit">Rouvrir</button></form></li>
  <?php endforeach; ?>
  </ul>
</details>
<?php endif; ?>
<p class="aide">Les notifications sont recalculées après chaque modification, et chaque nuit. « Reporter » cache une alerte une semaine ; « Ignorer » la cache tant que son sujet ne change pas.</p>
