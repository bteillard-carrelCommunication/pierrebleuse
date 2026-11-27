<?php
/**
 * Connexion.
 *
 * Agence (pilote, éditeur) : adresse et mot de passe. Artiste : lien de connexion à usage unique,
 * envoyé par mail, valable quinze minutes. Le même lien sert à un compte de l'agence pour choisir
 * son mot de passe (premier accès, oubli).
 *
 * Un lien ne se consomme qu'à l'envoi du formulaire, pas à l'ouverture de la page : un antivirus
 * de messagerie qui visite le lien ne le brûle pas. Les réponses ne disent jamais si une adresse
 * correspond à un compte.
 */

require __DIR__ . '/inclus/amorce.php';

const MOT_DE_PASSE_MIN = 12;

if (compte()) rediriger(chemin_pupitre() . '/index.php');

$erreur = '';
$info = '';
$jeton = recu('jeton');
$lien = $jeton !== '' ? lien_lire($jeton) : null;
$ecran = 'connexion';
if ($jeton !== '') $ecran = $lien ? ($lien['objet'] === 'mot_de_passe' ? 'mot_de_passe' : 'lien') : 'lien_perime';

if (est_post()) {
    verifier_csrf();
    $action = recu('action');
    if ($action === 'connexion') {
        $email = mb_strtolower(recu('email'));
        if (trop_de_tentatives($email)) {
            $erreur = 'Trop de tentatives. Réessayez dans un quart d\'heure.';
        } elseif (connecter_par_mot_de_passe($email, (string) ($_POST['mot_de_passe'] ?? ''))) {
            rediriger(chemin_pupitre() . '/index.php');
        } else {
            $erreur = 'Adresse ou mot de passe incorrect.';
        }
    } elseif ($action === 'lien') {
        $email = mb_strtolower(recu('email'));
        if (!trop_de_tentatives($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) lien_envoyer($email);
        $info = 'Si un compte correspond à cette adresse, un lien vient de lui être envoyé. Il est valable quinze minutes.';
        $ecran = 'connexion';
    } elseif ($action === 'entrer' && $lien && $lien['objet'] === 'connexion') {
        lien_consommer((int) $lien['lien_id']);
        noter_tentative($lien['email'], true);
        ouvrir_session($lien);
        rediriger(chemin_pupitre() . '/index.php');
    } elseif ($action === 'mot_de_passe' && $lien && $lien['objet'] === 'mot_de_passe') {
        $mdp = (string) ($_POST['mot_de_passe'] ?? '');
        if (mb_strlen($mdp) < MOT_DE_PASSE_MIN) {
            $erreur = 'Le mot de passe doit compter au moins ' . MOT_DE_PASSE_MIN . ' caractères.';
        } elseif ($mdp !== (string) ($_POST['confirmation'] ?? '')) {
            $erreur = 'Les deux saisies ne sont pas identiques.';
        } else {
            lien_consommer((int) $lien['lien_id']);
            bd_modifier('comptes', ['mot_de_passe' => password_hash($mdp, PASSWORD_DEFAULT)], ['id' => $lien['id']]);
            ouvrir_session($lien);
            journaliser('modification', 'compte', (int) $lien['id'], $lien['nom'], ['mot_de_passe' => '(ancien)'], ['mot_de_passe' => '(nouveau)']);
            message('ok', 'Votre mot de passe est enregistré.');
            rediriger(chemin_pupitre() . '/index.php');
        }
    }
}

$sous_titre = reglage('sous_titre') ?: reglage('artiste_nom');
$base = chemin_pupitre();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Connexion · Pupitre</title>
  <link rel="icon" href="data:,">
  <meta name="theme-color" content="#f5f6fc">
  <link rel="stylesheet" href="<?= e($base) ?>/ressources/css/pupitre.css?v=<?= e(VERSION_PUPITRE) ?>-<?= (int) @filemtime(RACINE . '/ressources/css/pupitre.css') ?>">
</head>
<body class="porte">
<main class="porte__carte">
  <h1 class="marque marque--grande"><span class="marque__logo" aria-hidden="true">P</span><span class="marque__nom">Pupitre</span><?php if ($sous_titre !== ''): ?><span class="marque__sous-titre"><?= e($sous_titre) ?></span><?php endif; ?></h1>
  <?php if ($erreur !== ''): ?><p class="message message--erreur" role="alert"><?= e($erreur) ?></p><?php endif; ?>
  <?php if ($info !== ''): ?><p class="message message--ok" role="status"><?= e($info) ?></p><?php endif; ?>

  <?php if ($ecran === 'lien'): ?>
    <p>Bonjour <?= e($lien['nom']) ?>.</p>
    <form method="post">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="entrer">
      <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
      <button type="submit" class="bouton bouton--plein" autofocus>Entrer dans Pupitre</button>
    </form>

  <?php elseif ($ecran === 'mot_de_passe'): ?>
    <p>Bonjour <?= e($lien['nom']) ?>. Choisissez votre mot de passe : <?= MOT_DE_PASSE_MIN ?> caractères au moins.</p>
    <form method="post">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="mot_de_passe">
      <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
      <div class="champ"><label for="mdp">Mot de passe</label><input type="password" id="mdp" name="mot_de_passe" autocomplete="new-password" minlength="<?= MOT_DE_PASSE_MIN ?>" required autofocus></div>
      <div class="champ"><label for="mdp2">Encore une fois</label><input type="password" id="mdp2" name="confirmation" autocomplete="new-password" minlength="<?= MOT_DE_PASSE_MIN ?>" required></div>
      <button type="submit" class="bouton bouton--plein">Enregistrer et entrer</button>
    </form>

  <?php elseif ($ecran === 'lien_perime'): ?>
    <p class="message message--alerte" role="alert">Ce lien n'est plus valable : il a déjà servi, ou ses quinze minutes sont passées.</p>
    <p><a class="bouton" href="<?= e($base) ?>/connexion.php">Demander un nouveau lien</a></p>

  <?php else: ?>
    <form method="post" class="porte__bloc">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="connexion">
      <div class="champ"><label for="email">Adresse e-mail</label><input type="email" id="email" name="email" autocomplete="username" required autofocus value="<?= e(recu('email')) ?>"></div>
      <div class="champ"><label for="mdp">Mot de passe</label><input type="password" id="mdp" name="mot_de_passe" autocomplete="current-password" required></div>
      <button type="submit" class="bouton bouton--plein">Se connecter</button>
    </form>
    <details class="porte__lien">
      <summary>Recevoir un lien par mail</summary>
      <p class="aide">Pour l'artiste : un lien de connexion. Pour l'agence : un lien pour choisir ou changer son mot de passe. Le lien est valable quinze minutes et ne sert qu'une fois.</p>
      <form method="post">
        <?= champ_csrf() ?>
        <input type="hidden" name="action" value="lien">
        <div class="champ"><label for="email-lien">Adresse e-mail</label><input type="email" id="email-lien" name="email" autocomplete="email" required></div>
        <button type="submit" class="bouton">Envoyer le lien</button>
      </form>
    </details>
  <?php endif; ?>
</main>
</body>
</html>
