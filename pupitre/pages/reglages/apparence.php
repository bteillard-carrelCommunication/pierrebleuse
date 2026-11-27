<?php
/**
 * Apparence : la couleur de fond générale du site.
 *
 * La valeur gardée est une couleur du web, en hexadécimal (sRVB) : c'est elle que le site reçoit
 * dans la variable CSS --bg, d'où découlent tous ses arrière-plans. La saisie se fait au choix par
 * le sélecteur, en hexadécimal, en RVB ou en CMJN ; les quatre restent accordés (ressources/js/pupitre.js).
 * La conversion du CMJN est approchée : sans profil colorimétrique, elle ne vaut pas épreuve.
 */

defined('PUPITRE') or exit;

$titre = 'Apparence';
$erreurs = [];
$initiale = couleur_lire(reglage('fond_initial')) ?: '#000000';

if (est_post()) {
    $couleur = recu('action') === 'restaurer' ? $initiale : couleur_lire(recu('fond'));
    if ($couleur === '') $erreurs[] = 'Cette couleur est illisible : écrivez-la en hexadécimal, par exemple #0a1524.';
    if (!$erreurs) {
        $avant = couleur_fond();
        if ($avant !== $couleur) {
            reglage_ecrire('fond', $couleur);
            journaliser('modification', 'reglages', null, 'Couleur de fond du site', ['fond' => $avant], ['fond' => $couleur]);
        }
        message('ok', recu('action') === 'restaurer' ? 'Couleur d\'origine restaurée. Le site suivra à la prochaine publication.' : 'Couleur de fond enregistrée. Le site suivra à la prochaine publication.');
        rediriger(url('apparence'));
    }
}
$fond = $erreurs ? (couleur_lire(recu('fond')) ?: couleur_fond()) : couleur_fond();
[$r, $v, $b] = array_map('intval', explode(' ', couleur_rvb($fond)));
?>
<div class="titre"><h1>Apparence</h1><span class="titre__suite">data/site.js</span></div>
<p class="intro">La couleur de fond générale du site : celle sur laquelle vole la nuée, et celle des fonds des sections, plus ou moins transparents. Les autres couleurs, les visuels et les animations ne changent pas.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="deux">
  <form method="post" class="carte" data-couleur>
    <?= champ_csrf() ?>
    <h2>Couleur de fond</h2>
    <div class="couleur">
      <div class="champ couleur__nuancier">
        <label for="c-nuancier">Sélecteur</label>
        <input type="color" id="c-nuancier" value="<?= e($fond) ?>" data-couleur-nuancier>
      </div>
      <div class="champ">
        <label for="c-fond">Hexadécimal</label>
        <input type="text" id="c-fond" name="fond" value="<?= e($fond) ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?" autocomplete="off" spellcheck="false" data-couleur-hex aria-describedby="c-fond-aide">
        <p class="aide" id="c-fond-aide">La valeur de référence, celle que le site reçoit.</p>
      </div>
    </div>
    <fieldset class="champ"><legend>RVB <span class="discret">(0 à 255)</span></legend>
      <div class="couleur__canaux">
        <label>R <input type="number" min="0" max="255" value="<?= $r ?>" data-couleur-rvb="0" aria-label="Rouge"></label>
        <label>V <input type="number" min="0" max="255" value="<?= $v ?>" data-couleur-rvb="1" aria-label="Vert"></label>
        <label>B <input type="number" min="0" max="255" value="<?= $b ?>" data-couleur-rvb="2" aria-label="Bleu"></label>
      </div>
    </fieldset>
    <fieldset class="champ"><legend>CMJN <span class="discret">(0 à 100 %)</span></legend>
      <div class="couleur__canaux">
        <label>C <input type="number" min="0" max="100" data-couleur-cmjn="0" aria-label="Cyan"></label>
        <label>M <input type="number" min="0" max="100" data-couleur-cmjn="1" aria-label="Magenta"></label>
        <label>J <input type="number" min="0" max="100" data-couleur-cmjn="2" aria-label="Jaune"></label>
        <label>N <input type="number" min="0" max="100" data-couleur-cmjn="3" aria-label="Noir"></label>
      </div>
      <p class="aide">Conversion approchée vers une couleur du web : sans profil colorimétrique, elle ne remplace pas une épreuve d'imprimeur.</p>
    </fieldset>
    <h2>Couleurs à essayer</h2>
    <div class="couleur__propositions">
      <?php foreach (COULEURS_PROPOSEES as $hex => $nom): ?>
        <button type="button" class="pastille" data-couleur-choisir="<?= e($hex) ?>"><span class="pastille__couleur" style="background: <?= e($hex) ?>"></span><span><?= e($nom) ?><br><code><?= e($hex) ?></code></span></button>
      <?php endforeach; ?>
    </div>
    <div class="actions actions--bas">
      <button type="submit" name="action" value="enregistrer" class="bouton bouton--plein">Enregistrer cette couleur</button>
      <button type="submit" name="action" value="restaurer" class="bouton"<?= $fond === $initiale ? ' disabled' : '' ?>>Restaurer la couleur d'origine (<?= e($initiale) ?>)</button>
    </div>
  </form>

  <div class="carte">
    <h2>Aperçu</h2>
    <div class="couleur__apercu" data-couleur-apercu style="background: <?= e($fond) ?>">
      <p class="couleur__apercu-titre">Pierre Bleuse</p>
      <p>Le texte du site, blanc, sur la couleur choisie.</p>
      <div class="couleur__apercu-voile"><p>Une section à 80 % : la nuée se devine derrière.</p></div>
    </div>
    <p class="aide">L'aperçu montre la couleur et son contraste avec le texte. Pour juger du rendu de la nuée, enregistrez, publiez (écran « Le site ») et regardez le site.</p>
    <ul class="items">
      <li><span class="item__titre">Sur le site aujourd'hui</span><span><code><?= e(couleur_fond()) ?></code></span></li>
      <li><span class="item__titre">Couleur d'origine</span><span><code><?= e($initiale) ?></code></span></li>
    </ul>
  </div>
</div>
