<?php
/**
 * Parcours professionnel : fonctions, grands débuts et collaborations.
 *
 * Le parcours du site n'est pas un CV : il dit en quelques lignes les fonctions artistiques
 * majeures et quatre ou cinq collaborations prestigieuses. Les fonctions se hiérarchisent par leur
 * ordre, indépendamment de leurs dates. Une collaboration s'appuie de préférence sur ce qui existe
 * déjà : un concert de l'agenda, un orchestre de la collection ; elle ne se saisit à la main que
 * faute des deux. Retirer une collaboration de la sélection, c'est la dépublier : rien n'est
 * supprimé. Aucune collaboration ne paraît sans qu'une personne l'ait publiée.
 *
 * Les anciennes lignes « Opéra et création » restent dans la table parcours_lignes : elles ne
 * sortent plus sur le site, et se lisent sur l'écran Parcours.
 */

defined('PUPITRE') or exit;

/** Nombre de collaborations que le site montre au plus : la sélection reste synthétique. */
const COLLABORATIONS_MAX = 5;

/** Année d'une date souple (« 2023 », « 2023-05 », « 2023-05-14 ») ; '' si elle est vide. */
function annee_de(string $date): string
{
    return preg_match('/^(\d{4})/', $date, $m) ? $m[1] : '';
}

/** Une date souple est-elle bien écrite ? (vide accepté) */
function date_souple_valide(string $d): bool
{
    return $d === '' || (bool) preg_match('/^\d{4}(-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?)?$/', $d);
}

/**
 * Ce que montre une collaboration : son intitulé éditorial s'il existe, sinon le nom de son
 * orchestre, sinon le titre de son concert. Rend ['intitule_fr' => …, 'orchestre' => …, 'annee' => …, 'concert' => ligne ou null].
 */
function collaboration_resoudre(array $c): array
{
    $orchestre = $c['fiche_id'] ? (string) bd_valeur('SELECT nom FROM annuaire WHERE id = ?', [$c['fiche_id']]) : '';
    $concert = $c['concert_id'] ? bd_ligne('SELECT * FROM concerts WHERE id = ?', [$c['concert_id']]) : null;
    if ($orchestre === '' && $concert) {
        $o = concert_orchestres((int) $concert['id']);
        $orchestre = $o ? $o[0]['nom'] : (string) $concert['ensemble_fr'];
    }
    $annee = annee_de((string) $c['annee']);
    if ($annee === '' && $concert) $annee = annee_de((string) bd_valeur("SELECT MIN(date) FROM representations WHERE concert_id = ? AND statut <> 'annulee'", [$concert['id']]));
    $intitule = trim((string) $c['intitule_fr']) !== '' ? trim((string) $c['intitule_fr']) : ($orchestre !== '' ? $orchestre : (string) ($concert['titre_fr'] ?? ''));
    return ['intitule_fr' => $intitule, 'orchestre' => $orchestre, 'annee' => $annee, 'concert' => $concert];
}

/** Un concert sort-il sur le site ? Publié, avec au moins une date annoncée ou publiée. */
function concert_sur_le_site(int $id): bool
{
    return (bool) bd_valeur("SELECT COUNT(*) FROM concerts c JOIN representations r ON r.concert_id = c.id WHERE c.id = ? AND c.publie = 1 AND r.statut IN ('annoncee', 'publiee')", [$id]);
}
