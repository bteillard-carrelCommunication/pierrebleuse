<?php
/**
 * Contenus du site : textes, et listes d'éléments ordonnables décrites une fois ici.
 *
 * Un écran de contenu (pages/contenus/liste.php) affiche une ou plusieurs listes. Chaque liste
 * dit sa table et ses champs ; l'enregistrement, l'ordre, le contrôle et le journal sont communs.
 *
 * Types de champ : ligne, texte, bi (français et anglais côte à côte), bi-texte, nombre, decimal,
 * adresse, email, case, date-souple (AAAA-MM-JJ ou AAAA-MM), fiche (annuaire), media (médiathèque), choix.
 * Un champ « bi » nommé x occupe les colonnes x_fr et x_en.
 */

defined('PUPITRE') or exit;

/** Les six textes du site, dans l'ordre de l'écran : clé => [libellé, type de champ, aide]. */
const TEXTES = [
    'citation' => ['Citation de l\'ouverture', 'bi', ''],
    'biographie' => ['Biographie', 'bi-texte', 'Un paragraphe par bloc : séparez-les par une ligne vide.'],
    'saison' => ['Mention de saison', 'bi', 'Sous le titre de l\'agenda, sur l\'accueil et sur la page des concerts.'],
    'lettre' => ['Texte de la lettre', 'bi', ''],
    'credit' => ['Crédit du pied de page', 'bi-texte', ''],
    'description' => ['Description de la page', 'bi-texte', 'Pour les moteurs de recherche et les partages. Une phrase ou deux.'],
];

function contenus(): array
{
    return [
        'parcours' => [
            'titre' => 'Parcours',
            'fichier' => 'data/parcours.js',
            'apercu' => ['PARCOURS-FONCTIONS' => 'Fonctions', 'PARCOURS-OPERA' => 'Opéra et création'],
            'listes' => [
                'fonctions' => ['titre' => 'Fonctions', 'table' => 'parcours_lignes', 'fixe' => ['liste' => 'fonctions'], 'ajout' => 'Ajouter une fonction',
                    'champs' => [['texte', 'bi', 'Ligne', 'requis' => true]]],
                'opera' => ['titre' => 'Opéra et création', 'table' => 'parcours_lignes', 'fixe' => ['liste' => 'opera'], 'ajout' => 'Ajouter une ligne',
                    'champs' => [['texte', 'bi', 'Ligne', 'requis' => true, 'aide' => '*…* pour l\'italique.']]],
            ],
        ],
        'disques' => [
            'titre' => 'Disques',
            'fichier' => 'data/disques.js',
            'intro' => 'Du plus récent au plus ancien : l\'ordre de la liste est celui du site.',
            'apercu' => ['DISQUES' => ''],
            'listes' => [
                'disques' => ['titre' => '', 'table' => 'disques', 'cle' => 'titre', 'ajout' => 'Ajouter un disque', 'champs' => [
                    ['titre', 'ligne', 'Titre', 'requis' => true, 'aide' => '*…* pour l\'italique.', 'rang' => 'tete'],
                    ['annee', 'nombre', 'Année', 'rang' => 'tete'],
                    ['maison_id', 'fiche', 'Maison de disques', 'categories' => ['maison_de_disques'], 'rang' => 'tete'],
                    ['descriptif', 'bi-texte', 'Descriptif', 'aide' => 'Interprètes, distinctions : une ligne par ligne affichée.'],
                    ['mot', 'bi-texte', 'Le mot de l\'artiste'],
                    ['commander', 'texte', 'Liens « Commander »', 'aide' => 'Un par ligne : Nom | https://…', 'rang' => 'liens'],
                    ['streaming', 'texte', 'Liens « Écouter »', 'aide' => 'Un par ligne : Nom | https://…', 'rang' => 'liens'],
                    ['lien', 'adresse', 'Page du disque (« En savoir plus »)'],
                ]],
            ],
        ],
        'presse' => [
            'titre' => 'Presse',
            'fichier' => 'data/presse.js',
            'intro' => 'Les quatre premiers articles sont sur l\'accueil, tous sur la page de la presse. Le logo est celui de la fiche du média, dans l\'annuaire.',
            'apercu' => ['PRESSE' => ''],
            'listes' => [
                'articles' => ['titre' => '', 'table' => 'articles', 'cle' => 'titre_fr', 'ajout' => 'Ajouter un article', 'champs' => [
                    ['media_id', 'fiche', 'Média', 'categories' => ['media'], 'requis' => true],
                    ['titre', 'bi', 'Titre de l\'article', 'aide' => '*…* pour l\'italique.'],
                    ['auteur', 'ligne', 'Auteur', 'rang' => 'signature'],
                    ['date_article', 'date-souple', 'Date', 'aide' => 'AAAA-MM-JJ, ou AAAA-MM si le jour est inconnu.', 'rang' => 'signature'],
                    ['verbatim', 'texte', 'Verbatim', 'aide' => 'Cité dans sa langue, avec ses guillemets.'],
                    ['langue', 'ligne', 'Langue du verbatim', 'aide' => 'Code de la langue : fr, en, de…', 'max' => 2],
                    ['resume', 'bi-texte', 'Résumé'],
                    ['lien', 'adresse', 'Adresse de l\'article'],
                ]],
            ],
        ],
        'telechargements' => [
            'titre' => 'Téléchargements',
            'fichier' => 'data/telechargements.js',
            'intro' => 'Sans fichier, la ligne porte la mention « à venir ». Les fichiers se déposent dans la médiathèque.',
            'apercu' => ['TELECHARGEMENTS' => ''],
            'listes' => [
                'telechargements' => ['titre' => '', 'table' => 'telechargements', 'cle' => 'libelle_fr', 'ajout' => 'Ajouter une ligne', 'champs' => [
                    ['libelle', 'bi', 'Libellé', 'requis' => true],
                    ['media_id', 'media', 'Fichier', 'types' => ['document', 'image']],
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
        $c[(int) $g['id']] = $g['structure'] !== '' ? $g['structure'] : ($g['mention_fr'] !== '' ? $g['mention_fr'] : 'Groupe ' . $g['id']);
    }
    return $c;
}

/** Colonnes de la base qu'occupe un champ. */
function champ_colonnes(array $champ): array
{
    return in_array($champ[1], ['bi', 'bi-texte'], true) ? [$champ[0] . '_fr', $champ[0] . '_en'] : [$champ[0]];
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

function textes_enregistrer(array $recus): void
{
    $avant = textes_lire();
    foreach (TEXTES as $cle => $_) {
        $fr = trim(str_replace("\r\n", "\n", (string) ($recus[$cle . '_fr'] ?? '')));
        $en = trim(str_replace("\r\n", "\n", (string) ($recus[$cle . '_en'] ?? '')));
        $a = ['fr' => $avant[$cle]['fr'] ?? '', 'en' => $avant[$cle]['en'] ?? ''];
        if ($a === ['fr' => $fr, 'en' => $en]) continue;
        bd_requete('INSERT INTO textes (cle, fr, en) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE fr = VALUES(fr), en = VALUES(en)', [$cle, $fr, $en]);
        journaliser('modification', 'texte', null, TEXTES[$cle][0], $a, ['fr' => $fr, 'en' => $en]);
    }
}
