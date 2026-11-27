<?php
/**
 * Registre des modules d'animation des blocs visuels, côté Pupitre.
 *
 * Une animation est du code du site : un module déclaré dans js/animations.js sous un identifiant
 * stable. Pupitre ne fait que choisir, pour une image, un module de ce registre et les valeurs de ses
 * réglages déclarés : aucun script ne se saisit dans Pupitre, et une valeur hors de la liste est
 * refusée (animation_lire()). Le média, le type d'animation et les réglages restent trois choses
 * distinctes : la même photographie peut recevoir un autre module, le même module une autre photographie.
 *
 * Ajouter un module : pupitre/LISEZ-MOI.md, « Ajouter un module d'animation ». En deux gestes : le
 * déclarer dans js/animations.js du site, puis ici, sous le même identifiant.
 */

defined('PUPITRE') or exit;

/**
 * identifiant => [nom, description, reglages => [nom du réglage => [libellé, choix => [valeur => libellé], defaut, aide]]].
 * Les préréglages de « lumiere » sont ceux de js/lumiere.js (PRESETS).
 */
const ANIMATIONS = [
    'lumiere' => [
        'nom' => 'Découpes de lumière',
        'description' => 'L\'image se révèle par plans de lumière (js/lumiere.js). L\'animation existante, inchangée.',
        'reglages' => [
            'preset' => [
                'libelle' => 'Préréglage',
                'choix' => [
                    'bleuse-1' => 'Bleuse 1 : réglé sur la photographie bleuse-1.jpg',
                    'bleuse-3' => 'Bleuse 3 : réglé sur la photographie bleuse-3.jpg',
                    'generique' => 'Générique : pour toute autre photographie',
                ],
                'defaut' => 'generique',
                'aide' => 'Un préréglage nommé d\'après une photographie est dessiné pour elle. Pour une nouvelle photographie, choisissez « Générique » : il suit le point de cadrage.',
            ],
        ],
    ],
];

/** Modules proposés dans une liste de choix : identifiant => nom. */
function animations_choix(): array
{
    return array_map(fn($a) => $a['nom'], ANIMATIONS);
}

/**
 * Lit et contrôle le choix d'une animation : rend ['module' => …, réglage => valeur…], ou null si
 * le module n'est pas au registre. Un réglage absent ou hors liste prend sa valeur par défaut.
 */
function animation_lire(string $module, array $recu): ?array
{
    $a = ANIMATIONS[$module] ?? null;
    if (!$a) return null;
    $v = ['module' => $module];
    foreach ($a['reglages'] as $nom => $r) {
        $x = (string) ($recu[$nom] ?? '');
        $v[$nom] = array_key_exists($x, $r['choix']) ? $x : $r['defaut'];
    }
    return $v;
}
