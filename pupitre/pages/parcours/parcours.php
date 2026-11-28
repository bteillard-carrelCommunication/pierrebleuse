<?php
/**
 * Parcours professionnel : les fonctions, puis les grands débuts et collaborations.
 * Les anciennes lignes « Opéra et création » ne sortent plus sur le site : elles sont gardées et se
 * lisent en bas de cet écran.
 */

defined('PUPITRE') or exit;

$titre = 'Parcours';

function concerts_choix(): array
{
    $c = [];
    foreach (bd_lignes("SELECT c.id, c.titre_en, c.ensemble_en, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id) AS premiere FROM concerts c WHERE c.type <> 'disque' ORDER BY premiere DESC, c.id") as $x) {
        $c[(int) $x['id']] = annee_de((string) $x['premiere']) . ' · ' . $x['titre_en'] . ($x['ensemble_en'] !== '' && stripos($x['titre_en'], $x['ensemble_en']) === false ? ' (' . $x['ensemble_en'] . ')' : '');
    }
    return $c;
}

$listes = [
    'fonctions' => ['titre' => 'Fonctions', 'table' => 'fonctions', 'ajout' => 'Ajouter une fonction',
        'controle' => fn(array $d) => (!date_souple_valide((string) ($d['debut'] ?? '')) || !date_souple_valide((string) ($d['fin'] ?? ''))) ? 'Début et fin : AAAA, AAAA-MM ou AAAA-MM-JJ.' : '',
        'champs' => [
            ['intitule', 'bi', 'Intitulé', 'requis' => true, 'aide' => 'Directeur musical, chef principal, premier chef invité…'],
            ['institution', 'bi', 'Institution', 'aide' => 'Son appellation officielle ; traduite seulement si elle a un nom d\'usage dans l\'autre langue.'],
            ['debut', 'date-souple-annee', 'Début', 'rang' => 'dates'],
            ['fin', 'date-souple-annee', 'Fin', 'aide' => 'Vide si la fonction est en cours.', 'rang' => 'dates'],
            ['actuelle', 'case', 'Fonction actuelle (décochée : fonction passée)', 'defaut' => 1],
            ['description', 'bi-texte', 'Description (facultative)'],
            ['lien', 'adresse', 'Lien (facultatif)'],
            ['une', 'case', 'À la une : mise en avant dans la liste'],
            ['publie', 'case', 'Publiée', 'defaut' => 1],
        ]],
    'collaborations' => ['titre' => 'Grands débuts et collaborations', 'table' => 'collaborations', 'ajout' => 'Ajouter une collaboration',
        'controle' => function (array $d) {
            if (empty($d['concert_id']) && empty($d['fiche_id']) && trim((string) ($d['intitule_en'] ?? '')) === '') return 'Choisissez un concert, un orchestre, ou donnez un intitulé.';
            if (!date_souple_valide((string) ($d['annee'] ?? ''))) return 'Année ou date : AAAA, AAAA-MM ou AAAA-MM-JJ.';
            if (!empty($d['publie']) && empty($d['verifiee'])) return 'Une collaboration ne se publie qu\'une fois confirmée : cochez « collaboration confirmée », ou laissez-la non publiée.';
            return '';
        },
        'champs' => [
            ['concert_id', 'choix', 'Concert ou événement de l\'agenda', 'options' => 'concerts_choix', 'vide' => '— aucun —', 'rang' => 'source'],
            ['fiche_id', 'fiche', 'Orchestre de la collection', 'categories' => ['orchestre', 'ensemble'], 'vide' => '— aucun —', 'rang' => 'source'],
            ['intitule', 'bi', 'Intitulé éditorial (facultatif)', 'aide' => 'Vide : le nom de l\'orchestre, sinon le titre du concert. *…* pour l\'italique.'],
            ['annee', 'date-souple-annee', 'Année ou date', 'aide' => 'Vide : celle du concert.', 'rang' => 'plus'],
            ['description', 'bi-texte', 'Courte description (facultative)'],
            ['verifiee', 'case', 'Collaboration confirmée'],
            ['une', 'case', 'À la une'],
            ['publie', 'case', 'Publiée : figure dans la sélection du site'],
        ]],
];
$erreurs = [];
$fautive = '';
if (est_post() && isset($listes[recu('liste')])) {
    $fautive = recu('liste');
    $erreurs = liste_enregistrer($listes[$fautive]['titre'], $listes[$fautive], is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
    if (!$erreurs) { message('ok', $listes[$fautive]['titre'] . ' : enregistré. Le site suivra à la prochaine publication.'); rediriger(url('parcours') . '#' . $fautive); }
}
$publiees = (int) bd_valeur('SELECT COUNT(*) FROM collaborations WHERE publie = 1');
$archives = bd_lignes("SELECT * FROM parcours_lignes WHERE liste = 'opera' ORDER BY ordre, id");
?>
<div class="titre"><h1>Parcours</h1><span class="titre__suite">data/parcours.js</span></div>
<p class="intro">Le parcours du site n'est pas un CV : il fait comprendre en quelques secondes les fonctions artistiques majeures et le prestige des collaborations. L'ordre des lignes est celui du site, indépendamment des dates.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<form method="post" class="carte" id="fonctions">
  <?= champ_csrf() ?><input type="hidden" name="liste" value="fonctions">
  <h2>Fonctions</h2>
  <p class="aide aide--haut">La première ligne est la première du site : aujourd'hui la direction musicale de l'Ensemble intercontemporain. N'ajoutez une nomination qu'une fois officialisée : préparée d'avance, laissez-la non publiée.</p>
  <?php $lignes = ($erreurs && $fautive === 'fonctions') ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : liste_lire($listes['fonctions']); ?>
  <?= liste_formulaire($listes['fonctions'], $lignes, 'lignes', $erreurs && $fautive === 'fonctions') ?>
  <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer les fonctions</button></div>
</form>

<form method="post" class="carte" id="collaborations">
  <?= champ_csrf() ?><input type="hidden" name="liste" value="collaborations">
  <h2>Grands débuts et collaborations</h2>
  <p class="aide aide--haut">Une sélection de quatre ou cinq collaborations : le site en montre <?= COLLABORATIONS_MAX ?> au plus, dans l'ordre de cette liste. Appuyez-vous de préférence sur un concert de l'agenda ou sur un orchestre de la collection ; ne saisissez un intitulé à la main que faute des deux. Dépublier retire de la sélection sans rien supprimer. L'intitulé de la rubrique se règle sur son <a href="<?= e(url('bloc', ['cle' => 'parcours-collaborations'])) ?>">bloc</a>.</p>
  <?php if (!$publiees): ?><p class="message message--alerte">Aucune collaboration n'est publiée : la rubrique ne s'affiche pas sur le site. Les lignes ci-dessous sont des brouillons préparés à partir des orchestres déjà dans les données ; publiez celles que vous confirmez.</p><?php endif; ?>
  <?php $lignes = ($erreurs && $fautive === 'collaborations') ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : liste_lire($listes['collaborations']); ?>
  <?= liste_formulaire($listes['collaborations'], $lignes, 'lignes', $erreurs && $fautive === 'collaborations') ?>
  <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer les collaborations</button></div>
</form>

<?php if ($archives): ?>
<div class="carte">
  <h2>Archives : « Opéra et création »</h2>
  <p class="aide aide--haut">Ces lignes ne sortent plus sur le site depuis le remplacement de la rubrique par « Grands débuts et collaborations ». Elles sont gardées telles quelles dans la base.</p>
  <ul class="items">
    <?php foreach ($archives as $l): ?><li><span><?= e(preg_replace('/\*/', '', $l['texte_en'])) ?></span><?php $autres = traductions_noms('parcours_lignes', $l['id'], 'texte'); if ($autres): ?><span class="discret"><?= e(preg_replace('/\*/', '', implode(' · ', $autres))) ?></span><?php endif; ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?= apercu_zones(['PARCOURS-FONCTIONS' => 'Fonctions', 'PARCOURS-COLLABORATIONS' => 'Grands débuts et collaborations']) ?>
