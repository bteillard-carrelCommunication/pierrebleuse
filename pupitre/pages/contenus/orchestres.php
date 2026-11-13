<?php
/**
 * Orchestres invités : la liste du parcours et les points de la carte du site.
 *
 * Une ligne = une fiche de l'annuaire et le lieu où la placer. Le lieu est celui du concert, pas
 * le siège de l'orchestre : un même ensemble peut figurer dans plusieurs villes. Pupitre propose
 * les orchestres des concerts passés ; rien n'entre dans le parcours sans une personne.
 */

defined('PUPITRE') or exit;

$titre = 'Orchestres invités';
$liste = ['table' => 'parcours_orchestres', 'ajout' => 'Ajouter une ligne', 'champs' => [
    ['fiche_id', 'fiche', 'Orchestre ou ensemble', 'categories' => ['orchestre', 'ensemble'], 'requis' => true, 'rang' => 'qui'],
    ['zone', 'choix', 'Zone de la liste', 'options' => array_diff_key(ZONES_PARCOURS, ['' => 1]), 'vide' => ZONES_PARCOURS[''], 'defaut' => '', 'rang' => 'qui'],
    ['ville', 'bi', 'Ville du concert', 'requis' => true, 'rang' => 'ou'],
    ['latitude', 'decimal', 'Latitude', 'requis' => true, 'borne' => 90, 'rang' => 'ou'],
    ['longitude', 'decimal', 'Longitude', 'requis' => true, 'borne' => 180, 'rang' => 'ou'],
]];
$erreurs = [];

if (est_post()) {
    if (recu('action') === 'ajouter' && ($f = fiche_lire(recu_entier('id') ?? 0))) {
        // la proposition entre avec le lieu de la fiche : à vérifier, c'est le lieu du concert qui compte
        bd_inserer('parcours_orchestres', ['fiche_id' => $f['id'], 'ville_fr' => $f['ville_fr'], 'ville_en' => $f['ville_en'], 'latitude' => $f['latitude'],
            'longitude' => $f['longitude'], 'ordre' => 1 + (int) bd_valeur('SELECT MAX(ordre) FROM parcours_orchestres')]);
        journaliser('creation', 'contenu', null, 'Orchestres invités : ' . $f['nom'], null, ['ville' => $f['ville_fr']]);
        message($f['latitude'] === null || $f['ville_fr'] === '' ? 'alerte' : 'ok', '« ' . $f['nom'] . ' » est ajouté en fin de liste.'
            . ($f['latitude'] === null || $f['ville_fr'] === '' ? ' Indiquez sa ville, sa latitude et sa longitude : sans elles, la ligne ne sort pas sur le site.' : ''));
        rediriger(url('orchestres'));
    }
    $erreurs = liste_enregistrer('Orchestres invités', $liste, is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
    if (!$erreurs) {
        message('ok', 'Parcours enregistré. Le site suivra à la prochaine publication.');
        rediriger(url('orchestres'));
    }
}

$lignes = $erreurs ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : liste_lire($liste);
// propositions : les ensembles des concerts passés qui n'y sont pas encore
$propositions = bd_lignes("SELECT a.*, MAX(r.date) AS derniere, COUNT(DISTINCT c.id) AS concerts
    FROM annuaire a JOIN concerts c ON c.ensemble_id = a.id JOIN representations r ON r.concert_id = c.id
    WHERE a.categorie IN ('orchestre', 'ensemble') AND r.date < ? AND r.statut <> 'annulee' AND c.type <> 'disque'
      AND NOT EXISTS (SELECT 1 FROM parcours_orchestres p WHERE p.fiche_id = a.id)
    GROUP BY a.id ORDER BY derniere DESC", [aujourdhui()]);
$provisoires = bd_colonne('SELECT p.ville_fr FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE a.nom_provisoire = 1');
?>
<div class="titre"><h1>Orchestres invités</h1><span class="titre__suite">data/orchestres.js</span></div>
<p class="intro">La liste du parcours et les points de la carte du site. Chaque ligne associe une fiche de l'annuaire au lieu où la placer : la ville du concert, qui n'est pas toujours celle de l'orchestre. Un même ensemble peut revenir dans plusieurs villes. L'ordre de la liste est celui du site, zone par zone.</p>
<p class="intro">L'orchestre se choisit parmi les fiches de l'annuaire : pour un nouvel orchestre, créez d'abord sa fiche. La ville est l'étiquette du point sur la carte. Latitude et longitude en degrés décimaux, nord et est positifs. La zone se déduit de la longitude ; elle s'impose quand cela ne suffit pas (le Proche-Orient ou la Russie, à l'ouest de 60° E).</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($provisoires): ?><p class="message message--alerte">Nom de l'orchestre à fournir pour <?= e(implode(', ', $provisoires)) ?> : en attendant, le site n'affiche que la ville.</p><?php endif; ?>

<form method="post" class="carte">
  <?= champ_csrf() ?>
  <?= liste_formulaire($liste, $lignes) ?>
  <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer le parcours</button>
    <a class="bouton" href="<?= e(url('fiche', ['id' => 'nouvelle', 'categorie' => 'orchestre'])) ?>">Nouvelle fiche d'orchestre</a></div>
</form>

<div class="carte">
  <h2>Propositions</h2>
  <p class="intro">Les orchestres et ensembles des concerts passés qui ne sont pas dans le parcours.</p>
  <?php if (!$propositions): ?><p class="rien">Aucune proposition pour l'instant.</p><?php else: ?>
  <ul class="items">
  <?php foreach ($propositions as $f): ?>
    <li>
      <span class="item__titre"><?= e($f['nom']) ?></span>
      <span class="discret"><?= e(pluriel((int) $f['concerts'], 'concert', 'concerts')) ?>, le dernier le <?= e(date_fr($f['derniere'])) ?></span>
      <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="ajouter"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <button type="submit" class="bouton bouton--petit">Ajouter au parcours</button></form>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<?= apercu_zones(['ORCHESTRES' => '']) ?>
