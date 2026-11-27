<?php
/**
 * Fiche d'annuaire : une personne ou une structure. Autres noms, lieu, coordonnées, site,
 * comptes sociaux (proposé, vérifié, à revoir), mots-dièse, contacts, crédit, logo.
 * Depuis la fiche : les concerts liés, les doublons probables.
 */

defined('PUPITRE') or exit;

$nouvelle = recu('id') === 'nouvelle';
$id = $nouvelle ? null : recu_entier('id');
$f = $id ? fiche_lire($id) : null;
if (!$nouvelle && !$f) {
    http_response_code(404);
    $titre = 'Fiche introuvable';
    echo '<div class="vide"><h1>Fiche introuvable</h1><p><a class="bouton" href="' . e(url('annuaire')) . '">Retour à l\'annuaire</a></p></div>';
    return;
}
$liste_comptes = ['table' => 'annuaire_comptes', 'fixe' => ['fiche_id' => $id], 'ajout' => 'Ajouter un compte', 'champs' => [
    ['reseau', 'choix', 'Réseau', 'requis' => true, 'options' => RESEAUX_CONNUS, 'rang' => 'compte'],
    ['adresse', 'ligne', 'Compte ou adresse', 'requis' => true, 'max' => 255, 'aide' => '@nom ou adresse complète.', 'rang' => 'compte'],
    ['etat', 'choix', 'État', 'options' => ETATS_COMPTE, 'defaut' => 'propose', 'rang' => 'compte'],
]];
$liste_contacts = ['table' => 'annuaire_contacts', 'fixe' => ['fiche_id' => $id], 'ajout' => 'Ajouter un contact', 'champs' => [
    ['nom', 'ligne', 'Nom', 'requis' => true, 'max' => 190, 'rang' => 'qui'],
    ['fonction', 'ligne', 'Fonction', 'max' => 190, 'rang' => 'qui'],
    ['email', 'email', 'Adresse e-mail', 'rang' => 'joindre'],
    ['telephone', 'ligne', 'Téléphone', 'max' => 60, 'rang' => 'joindre'],
]];
$erreurs = [];

if (est_post()) {
    $action = recu('action');
    if ($action === 'enregistrer') {
        $nombre = fn(string $k) => recu($k) === '' ? null : str_replace(',', '.', recu($k));
        $d = [
            'categorie' => array_key_exists(recu('categorie'), CATEGORIES) ? recu('categorie') : 'orchestre',
            'nom' => mb_substr(recu('nom'), 0, 190),
            'nom_provisoire' => empty($_POST['nom_provisoire']) ? 0 : 1,
            'autres_noms' => implode("\n", lignes(recu('autres_noms'))),
            'latitude' => $nombre('latitude'), 'longitude' => $nombre('longitude'),
            'verifie' => empty($_POST['verifie']) ? 0 : 1,
            'langue' => preg_match('/^[a-z]{2,3}$/', mb_strtolower(recu('langue'))) ? mb_strtolower(recu('langue')) : '',
            'typo' => array_key_exists(recu('typo'), TYPOS_MEDIA) ? recu('typo') : '',
            'premiere_collaboration' => recu('premiere_collaboration'),
            'fuseau' => in_array(recu('fuseau'), timezone_identifiers_list(), true) ? recu('fuseau') : '',
            'site' => recu('site'), 'mots_diese' => recu('mots_diese'), 'credit' => recu('credit'),
            'logo_id' => recu_entier('logo_id'), 'notes' => recu('notes'),
        ] + recu_bi($_POST, 'ville', 120) + recu_bi($_POST, 'pays', 120);
        if ($d['premiere_collaboration'] !== '' && !preg_match('/^\d{4}(-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?)?$/', $d['premiere_collaboration'])) $erreurs[] = 'Première collaboration : une année (2019), un mois (2019-03) ou un jour (2019-03-14).';
        if ($d['nom'] === '') $erreurs[] = 'Le nom est obligatoire.';
        if (!adresse_valide($d['site'])) $erreurs[] = 'Le site doit être une adresse complète (https://…).';
        foreach (['latitude' => 90, 'longitude' => 180] as $k => $max) {
            if ($d[$k] !== null && (!is_numeric($d[$k]) || abs((float) $d[$k]) > $max)) $erreurs[] = ucfirst($k) . ' : un nombre entre -' . $max . ' et ' . $max . ' (degrés décimaux).';
        }
        if (!$erreurs) {
            $id = objet_enregistrer('annuaire', $id, $d, 'fiche', $d['nom']);
            $GLOBALS['annuaire_perime'] = true;
            $pareilles = array_filter(fiches_doublons($id));
            message('ok', 'Fiche enregistrée.' . ($pareilles ? ' Attention : elle ressemble à une autre fiche (voir « Doublons probables »).' : ''));
            rediriger(url('fiche', ['id' => $id]));
        }
    } elseif ($f && in_array($action, ['comptes', 'contacts'], true)) {
        $liste = $action === 'comptes' ? $liste_comptes : $liste_contacts;
        $erreurs = liste_enregistrer($f['nom'] . ($action === 'comptes' ? ', comptes sociaux' : ', contacts'), $liste, is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
        if (!$erreurs) {
            message('ok', $action === 'comptes' ? 'Comptes sociaux enregistrés.' : 'Contacts enregistrés.');
            rediriger(url('fiche', ['id' => $id]));
        }
    } elseif ($f && $action === 'supprimer') {
        $emplois = fiche_usages($id);
        if ($emplois) {
            message('erreur', 'Cette fiche est employée (' . implode(' ; ', array_slice($emplois, 0, 4)) . (count($emplois) > 4 ? '…' : '') . ') : elle ne peut pas être supprimée.');
            rediriger(url('fiche', ['id' => $id]));
        }
        bd_supprimer('annuaire_comptes', ['fiche_id' => $id]);
        bd_supprimer('annuaire_contacts', ['fiche_id' => $id]);
        objet_supprimer('annuaire', $id, 'fiche', $f['nom']);
        message('ok', 'Fiche supprimée.');
        rediriger(url('annuaire'));
    }
}

$v = function (string $k) use ($f, $erreurs) {
    if (est_post() && $erreurs && recu('action') === 'enregistrer') return recu($k);
    return (string) ($f[$k] ?? ($k === 'categorie' ? recu('categorie') : ''));
};
$coche = fn(string $k) => (est_post() && $erreurs && recu('action') === 'enregistrer') ? !empty($_POST[$k]) : !empty($f[$k]);
$titre = $nouvelle ? 'Nouvelle fiche' : $f['nom'];
$resaisie = est_post() && $erreurs && recu('action') === 'enregistrer';
$orchestre = in_array($v('categorie') ?: 'orchestre', CATEGORIES_ORCHESTRE, true);
// traductions d'un champ : celles de la base, ou ce qui vient d'être saisi
$lgf = fn(string $champ) => ($resaisie || !$id) ? ['valeurs' => saisie_bi($_POST, $champ)] : ['objet' => ['annuaire', $id, $champ]];
$concerts = $f ? fiche_concerts($id) : [];
$doublons = $f ? fiches_doublons($id) : [];
$liens = ['ensemble' => 'ensemble', 'salle' => 'salle', 'participant' => 'participant', 'compositeur' => 'compositeur'];
?>
<p class="fil"><a href="<?= e(url('annuaire')) ?>">Annuaire</a></p>
<div class="titre"><h1><?= e($titre) ?></h1><?php if ($f): ?><span class="titre__suite"><?= e(CATEGORIES[$f['categorie']]) ?></span><?php endif; ?></div>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="deux">
<div>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <div class="colonnes">
      <?= champ_choix('categorie', 'Catégorie', CATEGORIES, $v('categorie') ?: 'orchestre') ?>
      <?= champ('nom', 'Nom', $v('nom'), ['requis' => true, 'max' => 190]) ?>
    </div>
    <?= champ_case('nom_provisoire', 'Nom à fournir : en attendant, le site n\'affiche que la ville', $coche('nom_provisoire')) ?>
    <?= champ_texte('autres_noms', 'Autres noms', $v('autres_noms'), ['aide' => 'Un par ligne : nom dans une autre langue, sigle, ancien nom. Ils servent à reconnaître la fiche à l\'import et à repérer les doublons.']) ?>
    <div class="colonnes">
      <?= champ_bi('ville', $orchestre ? 'Ville de référence' : 'Ville', $v('ville_fr'), $v('ville_en'), $lgf('ville')) ?>
      <?= champ_bi('pays', 'Pays', $v('pays_fr'), $v('pays_en'), $lgf('pays')) ?>
    </div>
    <div class="colonnes">
      <?= champ('latitude', 'Latitude', $v('latitude'), ['indication' => '48.86', 'aide' => 'Degrés décimaux, nord positif.']) ?>
      <?= champ('longitude', 'Longitude', $v('longitude'), ['indication' => '2.35', 'aide' => 'Degrés décimaux, est positif.']) ?>
      <?= champ_choix('fuseau', 'Fuseau', array_combine(timezone_identifiers_list(), timezone_identifiers_list()), $v('fuseau'), ['vide' => '— inconnu —']) ?>
    </div>
    <?php if ($orchestre): ?>
    <div class="colonnes">
      <?= champ_case('verifie', 'Ville et coordonnées de référence vérifiées', $coche('verifie')) ?>
      <?= champ('premiere_collaboration', 'Première collaboration', $v('premiere_collaboration'), ['max' => 10, 'indication' => '2019', 'aide' => 'Année, mois (2019-03) ou jour (2019-03-14). Facultative.']) ?>
    </div>
    <p class="aide">La ville de référence est celle de l'orchestre, pas celle d'un concert donné ailleurs. Sa place sur la carte du monde se règle sur l'écran <a href="<?= e($id ? url('carte', ['orchestre' => $id]) . '#placer' : url('carte')) ?>">Carte interactive</a>.</p>
    <?php endif; ?>
    <div class="colonnes">
      <?= champ('site', 'Site', $v('site'), ['type' => 'url', 'indication' => 'https://…']) ?>
      <?= champ('mots_diese', 'Mots-dièse', $v('mots_diese'), ['indication' => '#…']) ?>
    </div>
    <div class="colonnes">
      <?= champ('credit', 'Crédit', $v('credit'), ['aide' => 'Mention à porter quand on cite ou qu\'on montre ce nom.']) ?>
      <?= champ_choix('logo_id', 'Logo', medias_choix(['image']), $v('logo_id'), ['vide' => '— aucun —', 'aide' => 'Choisi dans la médiathèque. Pour un média : le logo de la presse, affiché en blanc.']) ?>
    </div>
    <?php if (($v('categorie') ?: 'orchestre') === 'media'): ?>
    <div class="colonnes">
      <?= champ('langue', 'Langue principale du média', $v('langue'), ['max' => 3, 'indication' => 'en', 'aide' => 'Code de la langue : fr, en, de…']) ?>
      <?= champ_choix('typo', 'Écriture de son nom sur le site', TYPOS_MEDIA, $v('typo'), ['aide' => 'Sans logo, le site écrit le nom du média : ce choix en règle le caractère. Avec un logo, le logo suffit.']) ?>
    </div>
    <?php endif; ?>
    <?= champ_texte('notes', $orchestre ? 'Informations complémentaires' : 'Notes', $v('notes')) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein"><?= $nouvelle ? 'Créer la fiche' : 'Enregistrer la fiche' ?></button></div>
  </form>

  <?php if ($f): ?>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="comptes">
    <h2>Comptes sociaux</h2>
    <p class="intro">Un compte est d'abord « proposé ». Il ne sert de référence qu'une fois « vérifié » par une personne.</p>
    <?= liste_formulaire($liste_comptes, bd_lignes('SELECT * FROM annuaire_comptes WHERE fiche_id = ? ORDER BY ordre, id', [$id])) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer les comptes</button></div>
  </form>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="contacts">
    <h2>Contacts</h2>
    <?= liste_formulaire($liste_contacts, bd_lignes('SELECT * FROM annuaire_contacts WHERE fiche_id = ? ORDER BY ordre, id', [$id])) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer les contacts</button></div>
  </form>
  <form method="post" class="actions">
    <?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
    <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer cette fiche ?">Supprimer cette fiche</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($f): ?>
<div>
  <?php if ($f['logo_id']): ?><div class="carte"><h2>Logo</h2><p><img src="<?= e(media_adresse((int) $f['logo_id'])) ?>" alt="Logo de <?= e($f['nom']) ?>" style="max-height:5rem;background:#222;padding:.6rem;border-radius:4px"></p></div><?php endif; ?>
  <?php if ($doublons): ?>
  <div class="carte">
    <h2>Doublons probables</h2>
    <ul class="items"><?php foreach ($doublons as [$a, $b, $raison]): $autre = (int) $a['id'] === $id ? $b : $a; ?>
      <li><a class="item__titre" href="<?= e(url('fiche', ['id' => $autre['id']])) ?>"><?= e($autre['nom']) ?></a><span class="discret"><?= e(CATEGORIES[$autre['categorie']]) ?> · <?= e($raison) ?></span></li>
    <?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
  <?php $parcours = bd_lignes('SELECT * FROM parcours_orchestres WHERE fiche_id = ? ORDER BY ordre, id', [$id]); ?>
  <?php if ($parcours || in_array($f['categorie'], ['orchestre', 'ensemble'], true)): ?>
  <div class="carte">
    <h2>Parcours du site</h2>
    <?php if (!$parcours): ?><p class="rien">N'y figure pas.</p><?php else: ?>
    <ul class="items"><?php foreach ($parcours as $x): ?><li><span>Orchestre invité, placé à <?= e($x['ville_fr']) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <p class="actions"><a class="bouton bouton--petit" href="<?= e(url('carte')) ?>">Carte interactive</a></p>
  </div>
  <?php endif; ?>
  <div class="carte">
    <h2>Concerts liés</h2>
    <?php if (!$concerts): ?><p class="rien">Aucun concert.</p><?php else: ?>
    <ul class="items"><?php foreach ($concerts as $c): ?>
      <li><a class="item__titre" href="<?= e(url('concert', ['id' => $c['id']])) ?>"><?= e($c['titre_fr']) ?></a><span class="discret"><?= e(date_fr($c['premiere'])) ?> · <?= e($liens[$c['lien']] ?? $c['lien']) ?></span></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
  <div class="carte"><h2>Journal</h2><?= journal_afficher(journal_objet('fiche', $id, 15)) ?></div>
</div>
<?php endif; ?>
</div>
