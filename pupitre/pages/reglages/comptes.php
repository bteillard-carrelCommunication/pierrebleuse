<?php
/**
 * Comptes (pilote) : créer, changer de rôle, désactiver, renvoyer un lien.
 * Aucun mot de passe n'est saisi ici : chacun choisit le sien par le lien qu'il reçoit.
 */

defined('PUPITRE') or exit;

$titre = 'Comptes';
$moi = compte();
$erreurs = [];

if (est_post()) {
    $action = recu('action');
    if ($action === 'creer') {
        $email = mb_strtolower(recu('email'));
        $nom = recu('nom');
        $role = recu('role');
        if ($nom === '') $erreurs[] = 'Le nom est obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs[] = 'Adresse e-mail illisible.';
        if (!isset(ROLES[$role])) $erreurs[] = 'Rôle inconnu.';
        if (!$erreurs && bd_valeur('SELECT id FROM comptes WHERE email = ?', [$email])) $erreurs[] = 'Un compte porte déjà cette adresse.';
        if (!$erreurs) {
            objet_enregistrer('comptes', null, ['email' => $email, 'nom' => $nom, 'role' => $role], 'compte', $nom);
            lien_envoyer($email);
            message('ok', 'Compte créé. Un lien vient d\'être envoyé à ' . $email . ' : il est valable quinze minutes, et peut être renvoyé d\'ici.');
            rediriger(url('comptes'));
        }
    } elseif (($c = bd_ligne('SELECT * FROM comptes WHERE id = ?', [recu_entier('id') ?? 0]))) {
        $pilotes_actifs = (int) bd_valeur("SELECT COUNT(*) FROM comptes WHERE role = 'pilote' AND actif = 1");
        $dernier_pilote = ($c['role'] === 'pilote' && $c['actif'] && $pilotes_actifs <= 1);
        if ($action === 'lien') {
            lien_envoyer($c['email']);
            message('ok', 'Lien envoyé à ' . $c['email'] . '.');
        } elseif ($action === 'activer') {
            $actif = recu('actif') === '1' ? 1 : 0;
            if (!$actif && $dernier_pilote) message('erreur', 'Il faut garder au moins un pilote actif.');
            else { objet_enregistrer('comptes', (int) $c['id'], ['actif' => $actif], 'compte', $c['nom']); message('ok', $actif ? 'Compte réactivé.' : 'Compte désactivé : il n\'a plus accès à Pupitre.'); }
        } elseif ($action === 'role' && isset(ROLES[recu('role')])) {
            if (recu('role') !== 'pilote' && $dernier_pilote) message('erreur', 'Il faut garder au moins un pilote actif.');
            else {
                $maj = ['role' => recu('role')];
                if (recu('role') === 'artiste') $maj['mot_de_passe'] = null;      // l'artiste entre par lien
                objet_enregistrer('comptes', (int) $c['id'], $maj, 'compte', $c['nom']);
                message('ok', 'Rôle enregistré.');
            }
        }
        rediriger(url('comptes'));
    }
}
$comptes = bd_lignes("SELECT * FROM comptes ORDER BY actif DESC, FIELD(role, 'pilote', 'editeur', 'artiste'), nom");
?>
<div class="titre"><h1>Comptes</h1></div>
<?php if ($erreurs): ?><ul class="erreurs"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="defile"><table class="tableau">
  <thead><tr><th>Nom</th><th>Adresse</th><th>Rôle</th><th>Dernière connexion</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($comptes as $c): ?>
    <tr<?= $c['actif'] ? '' : ' class="passee"' ?>>
      <th scope="row"><?= e($c['nom']) ?><?= (int) $c['id'] === (int) $moi['id'] ? ' <span class="puce">vous</span>' : '' ?><?= $c['actif'] ? '' : ' <span class="puce puce--erreur">désactivé</span>' ?></th>
      <td><?= e($c['email']) ?></td>
      <td>
        <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <label class="sr" for="role-<?= (int) $c['id'] ?>">Rôle de <?= e($c['nom']) ?></label>
          <select id="role-<?= (int) $c['id'] ?>" name="role" data-auto><?php foreach (ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $c['role'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
      </td>
      <td class="discret"><?= $c['derniere_connexion'] ? e(horodatage_fr($c['derniere_connexion'])) : 'jamais' ?><?= $c['role'] !== 'artiste' && empty($c['mot_de_passe']) ? '<br>mot de passe à choisir' : '' ?></td>
      <td><div class="en-ligne">
        <?php if ($c['actif']): ?>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="lien"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button type="submit" class="bouton bouton--petit"><?= $c['role'] === 'artiste' ? 'Envoyer un lien de connexion' : 'Envoyer un lien de mot de passe' ?></button></form>
        <?php endif; ?>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="activer"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="actif" value="<?= $c['actif'] ? 0 : 1 ?>"><button type="submit" class="bouton bouton--petit<?= $c['actif'] ? ' bouton--danger' : '' ?>"><?= $c['actif'] ? 'Désactiver' : 'Réactiver' ?></button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<form method="post" class="carte" style="margin-top:1.2rem">
  <?= champ_csrf() ?><input type="hidden" name="action" value="creer">
  <h2>Nouveau compte</h2>
  <div class="colonnes">
    <?= champ('nom', 'Nom', recu('nom'), ['requis' => true]) ?>
    <?= champ('email', 'Adresse e-mail', recu('email'), ['type' => 'email', 'requis' => true]) ?>
    <?= champ_choix('role', 'Rôle', ROLES, recu('role') ?: 'editeur') ?>
  </div>
  <p class="aide">Pilote : tout. Éditeur : agenda, annuaire, contenus, médias, validation des imports ; ni réglages ni publication. Artiste : sa vue, en lecture, et une note par concert.</p>
  <div class="actions"><button type="submit" class="bouton bouton--plein">Créer et envoyer le lien</button></div>
</form>
