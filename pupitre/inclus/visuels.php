<?php
/**
 * Blocs visuels : des emplacements configurables.
 *
 * Un bloc visuel porte une liste d'éléments, rangés dans `medias_usages` (objet « blocs », clé du
 * bloc, rôle « element »). Un élément est un média de la médiathèque et la manière de le montrer :
 *   image      l'image telle quelle ;
 *   animation  l'image, animée par un module du registre (inclus/animations.php) et ses réglages ;
 *   video      une vidéo : fichier déposé, adresse directe ou lien YouTube.
 * Ce que le site montre se déduit de la liste (elements_affiches()) : le premier élément publié
 * décide. Une image : elle seule. Une vidéo : toutes les vidéos publiées, dans l'ordre, que le
 * visiteur parcourt une à une. Les autres éléments restent en réserve dans le bloc, réutilisables.
 *
 * Sur un bloc visuel, le vol d'étourneaux n'est pas visible : le média couvre toute la section,
 * devant la nuée, qui continue de voler derrière et reparaît à la section suivante. C'est une règle
 * du site (js/sections.js), qui vaut pour tout bloc visuel, pas un réglage.
 */

defined('PUPITRE') or exit;

/** Blocs qui portent des médias : clé => « plein » (bloc visuel en pleine page) ou « fond » (photographie derrière un bloc de texte). */
const BLOCS_A_MEDIAS = ['visuel-1' => 'plein', 'visuel-2' => 'plein', 'visuel-3' => 'plein', 'suivre' => 'fond'];

const NATURES_ELEMENT = ['image' => 'Image fixe', 'animation' => 'Image animée', 'video' => 'Vidéo'];

/** Éléments d'un bloc, dans l'ordre : lignes de `medias_usages`, avec « p » (réglages décodés) et « media » (ligne de la médiathèque). */
function elements_lire(string $bloc): array
{
    $medias = medias_par_id();
    $l = [];
    foreach (bd_lignes("SELECT * FROM medias_usages WHERE objet = 'blocs' AND objet_id = ? AND role = 'element' ORDER BY ordre, id", [$bloc]) as $e) {
        $e['p'] = json_decode((string) $e['parametres'], true) ?: [];
        $e['media'] = $medias[(int) $e['media_id']] ?? null;
        $l[] = $e;
    }
    return $l;
}

function element_lire(int $id): ?array
{
    $e = bd_ligne("SELECT * FROM medias_usages WHERE id = ? AND objet = 'blocs' AND role = 'element'", [$id]);
    if (!$e) return null;
    $e['p'] = json_decode((string) $e['parametres'], true) ?: [];
    $e['media'] = medias_par_id()[(int) $e['media_id']] ?? null;
    return $e;
}

/** Un élément peut-il sortir sur le site ? Publié, média présent, droits d'utilisation validés. */
function element_montrable(array $e): bool
{
    return $e['publie'] && media_publiable($e['media']);
}

/**
 * Ce que le site montre d'un bloc : le premier élément montrable décide. Une image ou une image
 * animée : elle seule. Une vidéo : toutes les vidéos montrables, dans l'ordre. Un bloc « fond » ne
 * montre qu'une image.
 */
function elements_affiches(string $bloc): array
{
    $montrables = array_values(array_filter(elements_lire($bloc), 'element_montrable'));
    if ((BLOCS_A_MEDIAS[$bloc] ?? '') === 'fond') $montrables = array_values(array_filter($montrables, fn($e) => $e['nature'] !== 'video'));
    if (!$montrables) return [];
    if ($montrables[0]['nature'] !== 'video') return [$montrables[0]];
    return array_values(array_filter($montrables, fn($e) => $e['nature'] === 'video'));
}

/** En quelques mots, ce qu'un bloc affiche : « image animée », « 3 vidéos », « rien ». */
function bloc_visuel_resume(string $bloc): string
{
    $a = elements_affiches($bloc);
    if (!$a) return 'rien pour l\'instant';
    if ($a[0]['nature'] === 'image') return 'une image fixe';
    if ($a[0]['nature'] === 'animation') return 'une image animée (' . mb_strtolower(ANIMATIONS[$a[0]['p']['animation']['module'] ?? '']['nom'] ?? 'animation') . ')';
    return count($a) === 1 ? 'une vidéo' : count($a) . ' vidéos';
}

/** « 60% 35% » pour le site, à partir d'un point [x, y] en pourcentage ; '' sans point. */
function position_css($point): string
{
    return (is_array($point) && count($point) === 2) ? ((int) $point[0]) . '% ' . ((int) $point[1]) . '%' : '';
}

/** Un élément tel que le site le lit (data/visuels.js). */
function element_exporter(array $e): array
{
    $m = $e['media'];
    $p = $e['p'];
    $alt = multi('medias_usages', $e, 'alt');
    if ($alt === '' && $m) $alt = multi('medias', $m, 'alt');            // sans texte propre à cet emploi : celui du média
    $v = [];
    if ($e['nature'] === 'video') {
        $youtube = media_youtube($m);
        if ($youtube !== '') {
            $v = ['type' => 'youtube', 'id' => $youtube];
        } else {
            $v = ['type' => 'video', 'src' => media_chemin_site($m)];
            $affiche = media_chemin_site(medias_par_id()[(int) $e['affiche_id']] ?? null);
            if ($affiche !== '') $v['affiche'] = $affiche;
            if (!empty($p['son'])) {
                $v['son'] = true;
                if (!empty($p['piste'])) $v['piste'] = $p['piste'];
            }
        }
        if (trim((string) $e['titre_en']) !== '') $v['titre'] = multi('medias_usages', $e, 'titre');
        if (trim((string) $e['description_en']) !== '') $v['description'] = multi('medias_usages', $e, 'description');
    } else {
        $v = ['type' => 'image', 'src' => media_chemin_site($m)];
        $animation = $e['nature'] === 'animation' ? animation_lire((string) ($p['animation']['module'] ?? ''), (array) ($p['animation'] ?? [])) : null;
        if ($animation) $v['animation'] = $animation;
    }
    if (($x = position_css($p['position'] ?? null)) !== '') $v['position'] = $x;
    if (($x = position_css($p['mobile'] ?? null)) !== '') $v['positionMobile'] = $x;
    $v['alt'] = $alt;
    $credit = trim((string) $e['credit']) !== '' ? trim((string) $e['credit']) : trim((string) ($m['credit'] ?? ''));
    if ($credit !== '') $v['credit'] = $credit;
    return $v;
}

/** Crée un élément à la fin d'un bloc. Une image entre en image fixe, une vidéo en vidéo avec son. Rend son identifiant. */
function element_creer(string $bloc, int $media_id): int
{
    $m = medias_par_id()[$media_id] ?? null;
    if (!$m || !isset(BLOCS_A_MEDIAS[$bloc])) throw new RuntimeException('Bloc ou média inconnu.');
    $nature = $m['type'] === 'video' ? 'video' : 'image';
    $ordre = (int) bd_valeur("SELECT COALESCE(MAX(ordre), -1) + 1 FROM medias_usages WHERE objet = 'blocs' AND objet_id = ? AND role = 'element'", [$bloc]);
    return (int) objet_enregistrer('medias_usages', null, [
        'media_id' => $media_id, 'objet' => 'blocs', 'objet_id' => $bloc, 'role' => 'element', 'nature' => $nature,
        'parametres' => json_encode($nature === 'video' ? ['son' => true] : new stdClass()), 'publie' => 1, 'ordre' => $ordre,
    ], 'element', 'Bloc « ' . (blocs_tous()[$bloc]['nom'] ?? $bloc) . ' » · ' . media_nom($m));
}

/** Échange un élément avec son voisin dans le bloc. $sens : -1 pour monter, 1 pour descendre. */
function element_deplacer(int $id, int $sens): void
{
    $e = element_lire($id);
    if (!$e) return;
    $liste = array_column(elements_lire($e['objet_id']), 'id');
    $i = array_search($id, array_map('intval', $liste), true);
    $j = $i + $sens;
    if ($i === false || $j < 0 || $j >= count($liste)) return;
    [$liste[$i], $liste[$j]] = [$liste[$j], $liste[$i]];
    foreach ($liste as $ordre => $x) bd_modifier('medias_usages', ['ordre' => $ordre], ['id' => (int) $x]);
    journaliser('modification', 'element', $id, 'Bloc « ' . (blocs_tous()[$e['objet_id']]['nom'] ?? $e['objet_id']) . ' » : ordre des éléments', ['rang' => $i + 1], ['rang' => $j + 1]);
}
