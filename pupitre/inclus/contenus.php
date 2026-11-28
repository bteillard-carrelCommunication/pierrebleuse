<?php
/**
 * Contenus du site : textes, et listes d'éléments ordonnables décrites une fois ici.
 *
 * Un écran de contenu (pages/contenus/liste.php) affiche une ou plusieurs listes. Chaque liste
 * dit sa table et ses champs ; l'enregistrement, l'ordre, le contrôle et le journal sont communs.
 * Les collections plus riches (concerts, orchestres, discographie, revue de presse, parcours,
 * playlist) ont leurs propres écrans et leurs propres fichiers dans inclus/.
 *
 * Types de champ : ligne, texte, bi (champ traduisible, saisi dans la langue de rédaction), bi-texte
 * (le même, sur plusieurs lignes), nombre, decimal, adresse, email, case, date-souple (AAAA-MM-JJ ou
 * AAAA-MM), fiche (annuaire), media (médiathèque), choix. Un champ « bi » nommé x occupe la colonne
 * x_en ; ses traductions se saisissent sur l'écran « Traductions ».
 */

defined('PUPITRE') or exit;

/**
 * Les textes du site : clé => [libellé, type de champ, aide]. Chacun se règle sur l'écran de son
 * bloc (inclus/blocs.php, blocs_description()).
 */
/** Types de ressources de l'espace de téléchargement. */
const TYPES_TELECHARGEMENT = ['biographie' => 'Biographie', 'photographie' => 'Photographies', 'document' => 'Document', 'autre' => 'Autre'];

const TEXTES = [
    'citation' => ['Citation de l\'ouverture', 'bi', ''],
    'biographie' => ['Édito', 'bi-texte', 'Un paragraphe par bloc : séparez-les par une ligne vide.'],
    'saison' => ['Mention de saison', 'bi', 'Sous le titre de l\'agenda, sur l\'accueil et sur la page des concerts.'],
    'lettre' => ['Texte de la lettre', 'bi', ''],
    'credit' => ['Texte libre du pied de page', 'bi-texte', 'À droite du pied de page : crédits, mentions, droits. Les liens vers les pages légales, à gauche, sont posés par le site.'],
    'description' => ['Description du site', 'bi-texte', 'Pour les moteurs de recherche et les partages. Une phrase ou deux.'],
];

function contenus(): array
{
    return [
        'telechargements' => [
            'titre' => 'Téléchargements',
            'fichier' => 'data/telechargements.js',
            'intro' => 'Les ressources de l\'espace de téléchargement : biographies, photographies, autres documents. Sans fichier ni adresse, la ligne porte la mention « à venir ». Une ressource dépubliée quitte le site sans être supprimée. Les fichiers se déposent dans la médiathèque.',
            'apercu' => ['TELECHARGEMENTS' => ''],
            'listes' => [
                'telechargements' => ['titre' => '', 'table' => 'telechargements', 'cle' => 'libelle_en', 'ajout' => 'Ajouter une ressource', 'champs' => [
                    ['libelle', 'bi', 'Titre', 'requis' => true],
                    ['type', 'choix', 'Type', 'options' => TYPES_TELECHARGEMENT, 'vide' => '— à préciser —', 'defaut' => '', 'rang' => 'nature'],
                    ['langue', 'ligne', 'Langue du document', 'max' => 3, 'aide' => 'Son code : fr, en… Vide si la langue est sans objet (photographies).', 'rang' => 'nature'],
                    ['description', 'bi-texte', 'Description (facultative)', 'aide' => 'Une ligne discrète sous le titre.'],
                    ['media_id', 'media', 'Fichier (médiathèque)', 'types' => ['document', 'image'], 'rang' => 'source'],
                    ['url', 'adresse', 'ou adresse du fichier', 'aide' => 'Un fichier ou un dossier hébergé ailleurs. L\'adresse l\'emporte sur le fichier.', 'rang' => 'source'],
                    ['credit', 'ligne', 'Crédits', 'max' => 255, 'aide' => 'Photographe, auteur, source.'],
                    ['conditions', 'bi-texte', 'Conditions d\'utilisation (facultatives)', 'aide' => 'Par exemple : « Libre de droits pour la presse, mention du crédit obligatoire ».'],
                    ['publie', 'case', 'Publié : la ressource figure sur le site', 'defaut' => 1],
                ]],
            ],
        ],
        'contacts' => [
            'titre' => 'Contacts et réseaux',
            'fichier' => 'data/contacts.js, data/reseaux.js',
            'apercu' => ['CONTACTS' => 'Contacts', 'RESEAUX' => 'Réseaux'],
            'listes' => [
                'groupes' => ['titre' => 'Groupes de contacts', 'table' => 'contacts_groupes', 'ajout' => 'Ajouter un groupe', 'enfants' => ['contacts_personnes', 'groupe_id'], 'champs' => [
                    ['mention', 'bi', 'Mention', 'aide' => 'Intitulé du groupe, affiché en premier.'],
                    ['structure', 'ligne', 'Structure', 'aide' => 'Agence ou structure, facultative.', 'rang' => 'structure'],
                    ['lien', 'adresse', 'Adresse de la structure', 'rang' => 'structure'],
                    ['langue', 'ligne', 'Langue fixe', 'aide' => 'Si la mention et les fonctions ne se traduisent pas : le code de leur langue (en). Sinon vide.', 'max' => 2],
                ]],
                'personnes' => ['titre' => 'Personnes', 'table' => 'contacts_personnes', 'ajout' => 'Ajouter une personne', 'champs' => [
                    ['groupe_id', 'choix', 'Groupe', 'requis' => true, 'options' => 'contacts_groupes_choix', 'rang' => 'qui'],
                    ['nom', 'ligne', 'Nom', 'rang' => 'qui', 'aide' => 'Vide si le contact est celui de la structure elle-même.'],
                    ['role', 'bi', 'Fonction'],
                    ['telephone', 'ligne', 'Téléphone', 'max' => 60, 'rang' => 'joindre'],
                    ['email', 'email', 'Adresse e-mail', 'rang' => 'joindre'],
                ]],
                'reseaux' => ['titre' => 'Réseaux de l\'artiste', 'table' => 'reseaux', 'ajout' => 'Ajouter un réseau', 'champs' => [
                    ['cle', 'ligne', 'Réseau', 'requis' => true, 'aide' => 'instagram, facebook, youtube, linkedin : choisit le pictogramme.', 'max' => 30, 'suggestions' => ['instagram', 'facebook', 'youtube', 'linkedin'], 'rang' => 'reseau'],
                    ['nom', 'ligne', 'Nom affiché', 'requis' => true, 'max' => 60, 'rang' => 'reseau'],
                    ['url', 'adresse', 'Adresse', 'aide' => 'Sans adresse, le pictogramme reste affiché, inerte.'],
                    ['barre', 'case', 'Aussi dans la barre du haut'],
                ]],
            ],
        ],
    ];
}

function contacts_groupes_choix(): array
{
    $c = [];
    foreach (bd_lignes('SELECT * FROM contacts_groupes ORDER BY ordre, id') as $g) {
        $c[(int) $g['id']] = $g['structure'] !== '' ? $g['structure'] : ($g['mention_en'] !== '' ? $g['mention_en'] : 'Groupe ' . $g['id']);
    }
    return $c;
}

/** Colonne qu'occupe un champ dans les données : celle de la langue de rédaction s'il est traduisible. */
function champ_colonnes(array $champ): array
{
    if (!in_array($champ[1], ['bi', 'bi-texte'], true)) return [$champ[0]];
    return [$champ[0] . '_' . langue_reference()];
}

function liste_colonnes(array $liste): array
{
    return array_merge(...array_map('champ_colonnes', $liste['champs']));
}

function liste_lire(array $liste): array
{
    $ou = '1 = 1';
    $p = [];
    foreach ($liste['fixe'] ?? [] as $k => $v) { $ou .= ' AND ' . bd_nom($k) . ' = ?'; $p[] = $v; }
    return bd_lignes('SELECT * FROM ' . bd_nom($liste['table']) . " WHERE $ou ORDER BY ordre, id", $p);
}

/** Options d'un champ à choix : identifiant => libellé. */
function champ_options(array $champ): array
{
    if ($champ[1] === 'fiche') {
        $marques = implode(',', array_fill(0, count($champ['categories']), '?'));
        $o = [];
        foreach (bd_lignes("SELECT id, nom FROM annuaire WHERE categorie IN ($marques) ORDER BY nom", $champ['categories']) as $f) $o[(int) $f['id']] = $f['nom'];
        return $o;
    }
    if ($champ[1] === 'media') return medias_choix($champ['types']);
    if ($champ[1] === 'choix') return is_string($champ['options']) ? ($champ['options'])() : $champ['options'];
    return [];
}

/**
 * Lit une ligne de formulaire et la contrôle. Rend [données, erreurs] ; données null si la ligne est vide.
 */
function ligne_lire(array $liste, array $recu): array
{
    $d = [];
    $erreurs = [];
    $pleine = false;
    foreach ($liste['champs'] as $champ) {
        [$nom, $type, $libelle] = $champ;
        foreach (champ_colonnes($champ) as $col) {
            $max = $champ['max'] ?? (in_array($type, ['texte', 'bi-texte'], true) ? 5000 : 500);
            $v = mb_substr(trim(str_replace("\r\n", "\n", (string) ($recu[$col] ?? ''))), 0, $max);
            switch ($type) {
                case 'nombre':
                    if ($v !== '' && !ctype_digit($v)) $erreurs[] = $libelle . ' : un nombre est attendu.';
                    $v = ($v === '' || !ctype_digit($v)) ? null : (int) $v;
                    break;
                case 'decimal':
                    $v = str_replace(',', '.', $v);
                    if ($v !== '' && (!is_numeric($v) || abs((float) $v) > ($champ['borne'] ?? PHP_FLOAT_MAX))) $erreurs[] = $libelle . ' : un nombre est attendu' . (isset($champ['borne']) ? ', entre -' . $champ['borne'] . ' et ' . $champ['borne'] : '') . '.';
                    $v = ($v === '' || !is_numeric($v)) ? null : $v;
                    break;
                case 'fiche': case 'media': case 'choix':
                    $v = ($v !== '' && array_key_exists($v, champ_options($champ))) ? (ctype_digit($v) ? (int) $v : $v) : ($champ['defaut'] ?? null);
                    break;
                case 'case':
                    $v = $v === '' ? 0 : 1;
                    break;
                case 'adresse':
                    if (!adresse_valide($v)) $erreurs[] = $libelle . ' : une adresse complète est attendue (https://…).';
                    break;
                case 'email':
                    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $erreurs[] = $libelle . ' : adresse e-mail illisible.';
                    break;
                case 'date-souple-annee':
                    if ($v !== '' && !preg_match('/^\d{4}(-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?)?$/', $v)) $erreurs[] = $libelle . ' : AAAA, AAAA-MM ou AAAA-MM-JJ.';
                    break;
                case 'date-souple':
                    if ($v !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?$/', $v)) $erreurs[] = $libelle . ' : AAAA-MM-JJ ou AAAA-MM.';
                    break;
            }
            $d[$col] = $v;
            if ($type !== 'case' && $v !== null && $v !== '') $pleine = true;
        }
    }
    if (!$pleine) return [null, []];
    foreach ($liste['champs'] as $champ) {
        if (empty($champ['requis'])) continue;
        $col = champ_colonnes($champ)[0];
        if ($d[$col] === null || $d[$col] === '') $erreurs[] = $champ[2] . ' : ce champ est obligatoire.';
    }
    return [$d, $erreurs];
}

/** Identifiant court et unique dans la table, tiré d'un texte. */
function cle_unique(string $table, string $texte, ?int $sauf = null): string
{
    $base = slug(preg_replace('/\*/', '', $texte), 40) ?: 'element';
    $cle = $base;
    $n = 2;
    while (bd_valeur('SELECT id FROM ' . bd_nom($table) . ' WHERE cle = ? AND id <> ?', [$cle, $sauf ?? 0])) $cle = $base . '-' . $n++;
    return $cle;
}

/**
 * Enregistre une liste entière, dans l'ordre reçu : les lignes connues sont modifiées, les
 * nouvelles créées, les absentes supprimées. Rend la liste des erreurs ; rien n'est écrit s'il y en a.
 */
function liste_enregistrer(string $titre, array $liste, array $recues): array
{
    $colonnes = liste_colonnes($liste);
    $existantes = [];
    foreach (liste_lire($liste) as $l) $existantes[(int) $l['id']] = $l;
    $lignes = [];
    $erreurs = [];
    foreach (array_values($recues) as $i => $r) {
        if (!is_array($r)) continue;
        [$d, $e] = ligne_lire($liste, $r);
        if ($d === null) continue;
        // contrôle propre à la liste (option « controle ») : un message, ou rien
        if (!empty($liste['controle']) && ($m = ($liste['controle'])($d))) $e[] = $m;
        foreach ($e as $m) $erreurs[] = 'Ligne ' . (count($lignes) + 1) . ' · ' . $m;
        $id = ctype_digit((string) ($r['id'] ?? '')) && isset($existantes[(int) $r['id']]) ? (int) $r['id'] : null;
        $lignes[] = [$id, $d];
    }
    // une ligne encore employée ailleurs ne se retire pas (option « retenir » de la liste)
    $recues_ids = array_filter(array_map(fn($x) => $x[0], $lignes));
    foreach ($existantes as $id => $l) {
        if (!in_array($id, $recues_ids, true) && !empty($liste['retenir']) && ($m = ($liste['retenir'])($l))) $erreurs[] = $m;
    }
    if ($erreurs) return $erreurs;

    $reduire = fn(array $l) => array_intersect_key($l, array_flip($colonnes));
    $avant = array_map($reduire, array_values($existantes));
    bd_transaction(function () use ($liste, $lignes, $existantes, $titre, $avant) {
        $gardees = [];
        foreach ($lignes as $ordre => [$id, $d]) {
            $d['ordre'] = $ordre;
            if ($id) {
                $gardees[] = $id;
                bd_modifier($liste['table'], $d, ['id' => $id]);
            } else {
                foreach ($liste['fixe'] ?? [] as $k => $v) $d[$k] = $v;
                if (!empty($liste['cle'])) $d['cle'] = cle_unique($liste['table'], (string) $d[$liste['cle']]);
                bd_inserer($liste['table'], $d);
            }
        }
        foreach ($existantes as $id => $l) {
            if (in_array($id, $gardees, true)) continue;
            if (!empty($liste['enfants'])) bd_supprimer($liste['enfants'][0], [$liste['enfants'][1] => $id]);
            bd_supprimer($liste['table'], ['id' => $id]);
        }
        $apres = array_map(fn($x) => $x[1], $lignes);
        foreach ($apres as &$l) unset($l['ordre']);
        journaliser('modification', 'contenu', null, $titre, ['lignes' => $avant], ['lignes' => $apres]);
    });
    return [];
}

function textes_lire(): array
{
    $t = [];
    foreach (bd_lignes('SELECT * FROM textes') as $l) $t[$l['cle']] = $l;
    return $t;
}

/**
 * Enregistre les textes reçus, dans la langue de rédaction (champs <clé>_en). $cles : les seuls
 * textes à lire ; sans elles, tous. Leurs traductions ne changent pas : elles deviennent « à revoir ».
 */
function textes_enregistrer(array $recus, ?array $cles = null): int
{
    $avant = textes_lire();
    $reference = langue_reference();
    $n = 0;
    foreach (TEXTES as $cle => $_) {
        if ($cles !== null && !in_array($cle, $cles, true)) continue;
        if (!array_key_exists($cle . '_' . $reference, $recus)) continue;
        $neuf = trim(str_replace("\r\n", "\n", (string) $recus[$cle . '_' . $reference]));
        $ancien = (string) ($avant[$cle][$reference] ?? '');
        if ($ancien === $neuf) continue;
        bd_requete('INSERT INTO textes (cle, ' . bd_nom($reference) . ') VALUES (?, ?) ON DUPLICATE KEY UPDATE ' . bd_nom($reference) . ' = VALUES(' . bd_nom($reference) . ')', [$cle, $neuf]);
        journaliser('modification', 'texte', null, TEXTES[$cle][0], [$reference => $ancien], [$reference => $neuf]);
        $n++;
    }
    return $n;
}
