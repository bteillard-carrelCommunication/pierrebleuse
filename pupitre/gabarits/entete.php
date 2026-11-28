<?php
/**
 * Cadre commun des pages : menu latéral, barre du haut (fil d'Ariane, recherche, état du site,
 * notifications, compte), puis le contenu. Attend $titre et, facultatif, $page_courante.
 *
 * Le menu est décrit dans inclus/navigation.php, les notifications dans inclus/notifications.php.
 * L'artiste n'a ni menu ni recherche : sa vue est sa seule page. Aucun script n'est écrit ici :
 * les comportements passent par des attributs data- (ressources/js/pupitre.js).
 */

defined('PUPITRE') or exit;

$compte_courant = compte();
$page_courante = $page_courante ?? '';
$sous_titre = reglage('sous_titre') ?: reglage('artiste_nom');
$base = chemin_pupitre();
$artiste_seul = $compte_courant && $compte_courant['role'] === 'artiste';
$avec_menu = $compte_courant && !$artiste_seul;

$repere = navigation_repere($page_courante);
$menu = $avec_menu ? navigation($repere) : [];
$ouvertes = navigation_ouvertes($menu);
$situation = $avec_menu ? navigation_situer($repere) : [];
$menu_reduit = $avec_menu && ($_COOKIE['pupitre_lateral'] ?? '') === 'reduit';

// centre de notifications : alertes et validations en attente, par niveau
$notifs = ($avec_menu && peut('bord')) ? notifications_lire() : [];
$decompte = notifications_decompte($notifs);

// état du site : ce que le dernier calcul des alertes en dit (pas de comparaison de fichiers à chaque page)
$etat_site = null;
if ($avec_menu && peut('site.voir')) {
    try {
        $etat_site = ['classe' => '', 'texte' => 'Site à jour', 'titre' => 'Le site dit ce que dit Pupitre.'];
        foreach (bd_lignes("SELECT cle, message FROM alertes WHERE cle IN ('site:ecarts', 'technique:racine')") as $a) {
            if ($a['cle'] === 'technique:racine') $etat_site = ['classe' => ' etat-site--erreur', 'texte' => 'Site introuvable', 'titre' => $a['message']];
            elseif ($etat_site['classe'] === '') $etat_site = ['classe' => ' etat-site--attente', 'texte' => pluriel((int) $a['message'], 'modification à publier', 'modifications à publier'), 'titre' => $a['message']];
        }
    } catch (Throwable $e) {
        $etat_site = null;
    }
}

// initiales du compte, pour sa pastille
$initiales = '';
if ($compte_courant) {
    foreach (preg_split('/[\s-]+/u', trim((string) $compte_courant['nom'])) ?: [] as $mot) if ($mot !== '' && mb_strlen($initiales) < 2) $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
}
$icones_message = ['ok' => 'ok', 'alerte' => 'alerte', 'erreur' => 'erreur'];
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
  <?= marque_tete() ?>

  <script src="<?= e($base) ?>/ressources/js/pupitre.js?v=<?= e(VERSION_PUPITRE) ?>-<?= (int) @filemtime(RACINE . '/ressources/js/pupitre.js') ?>" defer></script>
</head>
<body class="<?= $artiste_seul ? 'vue-artiste' : ($compte_courant ? 'avec-menu' : 'sans-menu') ?><?= $menu_reduit ? ' menu-reduit' : '' ?>">
<a class="evitement" href="#contenu">Aller au contenu</a>
<?php if ($avec_menu): ?>
<div class="voile" data-fermer-menu hidden></div>
<aside class="lateral" id="menu">
  <div class="lateral__tete">
    <a class="marque" href="<?= e($base) ?>/index.php" title="Tableau de bord">
      <?= marque_logo('inverse', 34) ?><?= marque_symbole('mandarine', 26) ?>
      <?php if ($sous_titre !== ''): ?><span class="marque__sous-titre"><?= e($sous_titre) ?></span><?php endif; ?>
    </a>
    <button type="button" class="outil lateral__replier" data-replier-menu aria-pressed="<?= $menu_reduit ? 'true' : 'false' ?>" aria-label="Réduire le menu à ses pictogrammes" title="Réduire ou déplier le menu"><?= icone($menu_reduit ? 'menu-ouvrir' : 'menu-reduire') ?></button>
  </div>
  <nav class="menu" aria-label="Menu principal">
    <ul class="menu__liste">
    <?php foreach ($menu as $s): ?>
      <?php if (isset($s['entree'])): ?>
        <li><a class="menu__lien" href="<?= e($s['entree']['url']) ?>" title="<?= e($s['libelle']) ?>"<?= $s['active'] ? ' aria-current="page"' : '' ?>><?= icone($s['icone']) ?><span class="menu__libelle"><?= e($s['libelle']) ?></span></a></li>
      <?php else: ?>
        <li>
          <details class="menu__section<?= $s['active'] ? ' est-active' : '' ?>" data-section="<?= e($s['cle']) ?>"<?= isset($ouvertes[$s['cle']]) ? ' open' : '' ?>>
            <summary class="menu__lien" title="<?= e($s['libelle']) ?>"><?= icone($s['icone']) ?><span class="menu__libelle"><?= e($s['libelle']) ?></span><?= icone('chevron-bas', 'menu__chevron') ?></summary>
            <ul class="menu__sous">
              <?php foreach ($s['entrees'] as $entree): ?>
                <?php if (isset($entree['titre'])): ?>
                  <li class="menu__titre"><?= e($entree['titre']) ?></li>
                <?php else: ?>
                  <li><a href="<?= e($entree['url']) ?>"<?= $entree['active'] ? ' aria-current="page"' : '' ?>><?= e($entree['libelle']) ?></a></li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ul>
          </details>
        </li>
      <?php endif; ?>
    <?php endforeach; ?>
    </ul>
  </nav>
  <p class="lateral__pied">Pupitre <?= e(VERSION_PUPITRE) ?></p>
</aside>
<?php endif; ?>
<div class="principal">
<header class="tete">
  <?php if ($avec_menu): ?>
    <button type="button" class="tete__bouton tete__menu" data-ouvrir-menu aria-expanded="false" aria-controls="menu" aria-label="Ouvrir le menu"><?= icone('menu') ?></button>
    <nav class="fil-ariane" aria-label="Fil d'Ariane">
      <ol>
        <?php $etapes = array_values(array_filter($situation, 'strlen')) ?: [$titre ?? 'Pupitre']; foreach ($etapes as $i => $etape): ?>
          <li<?= $i === count($etapes) - 1 ? ' aria-current="page"' : '' ?>><?= e($etape) ?></li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <form class="recherche" role="search" method="get" action="<?= e($base) ?>/index.php" data-recherche>
      <input type="hidden" name="page" value="recherche">
      <?= icone('recherche') ?>
      <label class="sr" for="recherche-q">Chercher dans Pupitre</label>
      <input type="search" id="recherche-q" name="q" value="<?= e($page_courante === 'recherche' ? recu('q') : '') ?>" placeholder="Chercher un concert, un article, un écran…" autocomplete="off"
        role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="recherche-resultats" aria-expanded="false" aria-describedby="recherche-aide">
      <kbd class="recherche__touche" aria-hidden="true">/</kbd>
      <span class="sr" id="recherche-aide">Les résultats s'affichent pendant la saisie : flèches pour les parcourir, Entrée pour ouvrir.</span>
      <span class="sr" role="status" aria-live="polite" data-recherche-annonce></span>
      <div class="flottant recherche__resultats" id="recherche-resultats" role="listbox" aria-label="Résultats de la recherche" hidden></div>
    </form>
    <div class="tete__outils">
      <button type="button" class="tete__bouton tete__recherche" data-ouvrir-recherche aria-expanded="false" aria-label="Chercher"><?= icone('recherche') ?></button>
      <?php if ($etat_site): ?>
        <a class="etat-site<?= e($etat_site['classe']) ?>" href="<?= e(url('site')) ?>" title="<?= e($etat_site['titre']) ?>" aria-label="<?= e($etat_site['texte']) ?> : ouvrir la publication du site"><span class="etat-site__texte"><?= e($etat_site['texte']) ?></span></a>
      <?php endif; ?>
      <?php if (peut('bord')): ?>
      <div class="tete__relatif">
        <button type="button" class="tete__bouton" data-bascule="#notifs" aria-expanded="false" aria-controls="notifs"
          aria-label="Notifications : <?= $decompte['a_traiter'] ? e(pluriel($decompte['a_traiter'], 'point à traiter', 'points à traiter')) . ($decompte['prioritaire'] ? ', dont ' . e(pluriel($decompte['prioritaire'], 'alerte prioritaire', 'alertes prioritaires')) : '') : 'rien à traiter' ?>">
          <?= icone('cloche') ?><?php if ($decompte['a_traiter']): ?><span class="compteur<?= $decompte['prioritaire'] ? '' : ' compteur--calme' ?>" aria-hidden="true"><?= $decompte['a_traiter'] > 99 ? '99+' : (int) $decompte['a_traiter'] ?></span><?php endif; ?>
        </button>
        <section class="flottant notifs" id="notifs" aria-label="Notifications" hidden data-notifs>
          <div class="notifs__tete"><p class="notifs__titre">Notifications</p><span class="carte__compte"><?= count($notifs) ?></span></div>
          <div class="notifs__filtres" role="group" aria-label="Filtrer par niveau">
            <button type="button" class="notifs__filtre" data-notifs-filtre="" aria-pressed="true">Tout</button>
            <?php foreach (['prioritaire' => 'Prioritaires', 'action' => 'À traiter', 'info' => 'Informations'] as $niveau => $libelle): ?>
              <button type="button" class="notifs__filtre" data-notifs-filtre="<?= e($niveau) ?>" aria-pressed="false"><?= e($libelle) ?> <span class="discret"><?= (int) $decompte[$niveau] ?></span></button>
            <?php endforeach; ?>
          </div>
          <div class="notifs__liste">
            <?php if (!$notifs): ?>
              <div class="vide"><div class="vide__icone"><?= icone('ok') ?></div><p class="vide__titre">Tout est en ordre</p><p>Aucune alerte, rien à traiter.</p></div>
            <?php endif; ?>
            <?php foreach (array_slice($notifs, 0, 30) as $n): ?>
              <a class="notif notif--<?= e($n['niveau']) ?>" href="<?= e($n['lien']) ?>" data-niveau="<?= e($n['niveau']) ?>">
                <span class="notif__icone"><?= icone($n['icone']) ?></span>
                <span class="notif__type"><?= e($n['type']) ?> <span class="niveau niveau--<?= e($n['niveau']) ?>"><?= e(['prioritaire' => 'prioritaire', 'action' => 'à traiter', 'info' => 'information'][$n['niveau']]) ?></span></span>
                <span class="notif__message"><?= e(couper($n['message'], 190)) ?></span>
                <?php if ($n['quand']): ?><span class="notif__quand"><?= e(il_y_a($n['quand'])) ?></span><?php endif; ?>
              </a>
            <?php endforeach; ?>
            <p class="recherche__vide" data-notifs-vide hidden>Rien à ce niveau.</p>
          </div>
          <div class="notifs__pied"><a href="<?= e(url('notifications')) ?>">Tout voir et traiter</a><?php if (count($notifs) > 30): ?><span class="discret"><?= count($notifs) - 30 ?> de plus</span><?php endif; ?></div>
        </section>
      </div>
      <?php endif; ?>
      <div class="tete__relatif">
        <button type="button" class="compte" data-bascule="#compte-menu" aria-expanded="false" aria-controls="compte-menu" aria-label="Compte de <?= e($compte_courant['nom']) ?>">
          <span class="avatar" aria-hidden="true"><?= e($initiales) ?></span>
          <span class="compte__textes"><span class="tete__nom"><?= e($compte_courant['nom']) ?></span><span class="tete__role"><?= e(ROLES[$compte_courant['role']]) ?></span></span>
          <?= icone('chevron-bas') ?>
        </button>
        <div class="flottant" id="compte-menu" hidden>
          <?php if (peut('artiste.vue')): ?><a class="flottant__lien" href="<?= e(url('artiste')) ?>"><?= icone('artiste') ?>Vue de l'artiste</a><?php endif; ?>
          <?php if (site_adresse() !== ''): ?><a class="flottant__lien" href="<?= e(site_adresse()) ?>" target="_blank" rel="noopener"><?= icone('externe') ?>Voir le site</a><?php endif; ?>
          <div class="flottant__trait"></div>
          <form method="post" action="<?= e($base) ?>/deconnexion.php" class="tete__compte"><?= champ_csrf() ?><button type="submit" class="flottant__lien"><?= icone('deconnexion') ?>Se déconnecter</button></form>
        </div>
      </div>
    </div>
  <?php else: ?>
    <a class="marque" href="<?= e($base) ?>/index.php">
      <?= marque_logo('principal', 30) ?>
      <?php if ($sous_titre !== ''): ?><span class="marque__sous-titre"><?= e($sous_titre) ?></span><?php endif; ?>
    </a>
    <?php if ($compte_courant): ?>
    <div class="tete__outils">
      <span class="compte__textes"><span class="tete__nom"><?= e($compte_courant['nom']) ?></span><span class="tete__role"><?= e(ROLES[$compte_courant['role']]) ?></span></span>
      <form method="post" action="<?= e($base) ?>/deconnexion.php" class="tete__compte"><?= champ_csrf() ?><button type="submit" class="bouton bouton--petit"><?= icone('deconnexion') ?>Se déconnecter</button></form>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</header>
<main class="contenu" id="contenu" tabindex="-1">
<?php foreach (messages_lire() as [$type, $texte]): ?>
  <div class="message message--compose message--<?= e($type) ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>"><?= icone($icones_message[$type] ?? 'info') ?><p class="message__texte"><?= e($texte) ?></p><button type="button" class="message__fermer" data-fermer-message aria-label="Masquer ce message"><?= icone('fermer') ?></button></div>
<?php endforeach; ?>
