<?php
/**
 * Navigation de Pupitre : le menu latéral, le fil d'Ariane de la barre du haut, la recherche.
 *
 * Quatre espaces, plus l'administration :
 *   Tableau de bord                l'accueil : ce qui arrive, ce qui attend, ce qui cloche ;
 *   Site internet                  les textes et les blocs de la page, les données qu'elle affiche, leurs traductions, la mise en ligne ;
 *   Carrière                       le parcours, les orchestres dirigés, la carte du monde, le carnet d'adresses ;
 *   Pilotage de la communication   le calendrier éditorial, les réseaux, la lettre ;
 *   Médiathèque                    les fichiers, communs au site et à la communication ;
 *   Administration                 comptes, réglages, base, journal.
 *
 * Une section est une liste d'entrées. Une entrée : [libellé, page, paramètres, droit, pages qui
 * l'allument, pictogramme]. Une ligne ['@' => 'Données'] pose un intertitre dans la section. Les
 * pages qui allument une entrée servent aussi au fil d'Ariane : « concert » allume « Concerts ».
 * Un repère « contenu:telechargements » désigne la page « contenu » avec ce type.
 */

defined('PUPITRE') or exit;

const NAVIGATION = [
    'bord' => ['libelle' => 'Tableau de bord', 'icone' => 'tableau', 'entree' => ['Tableau de bord', 'bord', [], 'bord', ['bord', 'notifications', 'recherche'], 'tableau']],
    'site' => ['libelle' => 'Site internet', 'icone' => 'site', 'entrees' => [
        ['Textes du site', 'blocs', [], 'contenus', ['blocs', 'bloc', 'textes'], 'textes'],
        ['Blocs visuels', 'visuels', [], 'contenus', ['visuels'], 'visuels'],
        ['@' => 'Données'],
        ['Concerts', 'agenda', [], 'agenda', ['agenda', 'concert', 'imports', 'import'], 'concerts'],
        ['Presse', 'presse', [], 'contenus', ['presse', 'article'], 'presse'],
        ['Discographie', 'disques', [], 'contenus', ['disques', 'disque'], 'disques'],
        ['Distinctions', 'distinctions', [], 'contenus', ['distinctions'], 'distinctions'],
        ['Playlist', 'musiques', [], 'contenus', ['musiques'], 'playlist'],
        ['Téléchargements', 'contenu', ['type' => 'telechargements'], 'contenus', ['contenu:telechargements'], 'telechargements'],
        ['Contacts et réseaux', 'contenu', ['type' => 'contacts'], 'contenus', ['contenu:contacts'], 'contacts'],
        ['@' => 'Mise en ligne'],
        ['Traductions', 'traductions', [], 'contenus', ['traductions'], 'langues'],
        ['Langues', 'langues', [], 'contenus', ['langues'], 'langues'],
        ['Apparence', 'apparence', [], 'reglages', ['apparence'], 'apparence'],
        ['Aperçus et validations', 'apercus', [], 'site.voir', ['apercus', 'apercu'], 'oeil'],
        ['Publication', 'site', [], 'site.voir', ['site'], 'publier'],
    ]],
    'carriere' => ['libelle' => 'Carrière', 'icone' => 'carriere', 'entrees' => [
        ['Parcours', 'parcours', [], 'contenus', ['parcours'], 'parcours'],
        ['Orchestres du monde', 'orchestres', [], 'contenus', ['orchestres'], 'orchestres'],
        ['Carte interactive', 'carte', [], 'contenus', ['carte'], 'carte'],
        ['Annuaire', 'annuaire', [], 'annuaire', ['annuaire', 'fiche'], 'annuaire'],
    ]],
    'communication' => ['libelle' => 'Pilotage de la communication', 'court' => 'Communication', 'icone' => 'communication', 'entrees' => [
        ['Tableau de bord éditorial', 'editorial', [], 'communication', ['editorial'], 'editorial'],
        ['Social Media', 'social', [], 'communication', ['social', 'communication:social'], 'social'],
        ['Newsletter', 'newsletter', [], 'communication', ['newsletter', 'communication:lettre'], 'newsletter'],
        ['Résultats des publications', 'resultats', [], 'communication', ['resultats'], 'mesures'],
        ['Veille', 'veille', [], 'communication', ['veille'], 'veille'],
        ['Courrier', 'courrier', [], 'communication', ['courrier'], 'courrier'],
        ['Images des concerts', 'depots', [], 'communication', ['depots', 'depot'], 'depot'],
    ]],
    'medias' => ['libelle' => 'Médiathèque', 'icone' => 'medias', 'entree' => ['Médiathèque', 'medias', [], 'medias', ['medias', 'media'], 'medias']],
    'administration' => ['libelle' => 'Administration', 'icone' => 'administration', 'entrees' => [
        ['Comptes', 'comptes', [], 'comptes', ['comptes'], 'comptes'],
        ['Réglages', 'reglages', [], 'reglages', ['reglages'], 'reglages'],
        ['Base de données', 'base', [], 'reglages', ['base'], 'base'],
        ['Journal', 'journal', [], 'journal', ['journal'], 'journal'],
        ['Bibliothèque d\'interface', 'interface', [], 'reglages', ['interface'], 'interface'],
    ]],
];

/** Le repère de la page affichée : son nom, précisé quand une même page sert plusieurs entrées du menu. */
function navigation_repere(string $page): string
{
    if ($page === 'contenu') return 'contenu:' . ($_GET['type'] ?? '');
    if ($page === 'communication') return 'communication:' . ($GLOBALS['communication_famille'] ?? 'social');
    return $page;
}

/**
 * Le menu, tel que le compte connecté peut le voir. Rend une liste de sections :
 * ['cle', 'libelle', 'court', 'icone', 'active', 'entree' (section sans sous-menu) ou 'entrees' (liste)] ;
 * chaque entrée : ['libelle', 'url', 'icone', 'active'] ou ['titre' => intertitre].
 */
function navigation(string $repere): array
{
    $menu = [];
    $rendre = fn(array $e) => ['libelle' => $e[0], 'url' => url($e[1], $e[2]), 'icone' => $e[5], 'active' => in_array($repere, $e[4], true)];
    foreach (NAVIGATION as $cle => $s) {
        $section = ['cle' => $cle, 'libelle' => $s['libelle'], 'court' => $s['court'] ?? $s['libelle'], 'icone' => $s['icone'], 'active' => false];
        if (isset($s['entree'])) {
            if (!peut($s['entree'][3])) continue;
            $section['entree'] = $rendre($s['entree']);
            $section['active'] = $section['entree']['active'];
        } else {
            $entrees = [];
            $titre = null;
            foreach ($s['entrees'] as $e) {
                if (isset($e['@'])) { $titre = $e['@']; continue; }
                if (!peut($e[3])) continue;
                if ($titre !== null) { $entrees[] = ['titre' => $titre]; $titre = null; }      // un intertitre ne paraît que s'il a une entrée visible
                $x = $rendre($e);
                $entrees[] = $x;
                if ($x['active']) $section['active'] = true;
            }
            if (!$entrees) continue;
            $section['entrees'] = $entrees;
        }
        $menu[] = $section;
    }
    return $menu;
}

/** Où est la page affichée : [libellé de la section, intertitre ou '', libellé de l'entrée], ou [] si elle n'est pas au menu. */
function navigation_situer(string $repere): array
{
    foreach (NAVIGATION as $s) {
        if (isset($s['entree'])) {
            if (in_array($repere, $s['entree'][4], true)) return [$s['libelle'], '', ''];
            continue;
        }
        $titre = '';
        foreach ($s['entrees'] as $e) {
            if (isset($e['@'])) { $titre = $e['@']; continue; }
            if (in_array($repere, $e[4], true)) return [$s['libelle'], $titre, $e[0]];
        }
    }
    return [];
}

/** Les écrans du menu que le compte peut ouvrir, à plat : [libellé, section, adresse, pictogramme]. Pour la recherche. */
function navigation_ecrans(): array
{
    $l = [];
    foreach (NAVIGATION as $s) {
        foreach (isset($s['entree']) ? [$s['entree']] : $s['entrees'] as $e) {
            if (isset($e['@']) || !peut($e[3])) continue;
            $l[] = [$e[0], isset($s['entree']) ? '' : $s['libelle'], url($e[1], $e[2]), $e[5]];
        }
    }
    return $l;
}

/** Les sections du menu que l'utilisateur a laissées ouvertes (témoin « pupitre_menu »), la section active comprise. */
function navigation_ouvertes(array $menu): array
{
    $ouvertes = [];
    $temoin = (string) ($_COOKIE['pupitre_menu'] ?? '');
    $connues = array_column($menu, 'cle');
    if ($temoin !== '') foreach (explode('.', $temoin) as $c) if (in_array($c, $connues, true)) $ouvertes[$c] = true;
    foreach ($menu as $s) if ($s['active']) $ouvertes[$s['cle']] = true;
    return $ouvertes;
}

/**
 * Recherche dans Pupitre : écrans du menu, concerts, articles, albums, fiches de l'annuaire, actions
 * de communication. Rend des groupes [titre, [[libellé, détail, adresse, pictogramme], …]], selon
 * les droits du compte. Trois caractères au moins pour chercher dans les données.
 */
function recherche(string $q, int $par_groupe = 6): array
{
    $q = trim(preg_replace('/\s+/u', ' ', $q));
    if (mb_strlen($q) < 2) return [];
    $n = normaliser($q);
    $groupes = [];
    $ecrans = [];
    foreach (navigation_ecrans() as [$libelle, $section, $adresse, $icone]) {
        if ($n !== '' && str_contains(normaliser($libelle . ' ' . $section), $n)) $ecrans[] = [$libelle, $section, $adresse, $icone];
    }
    if ($ecrans) $groupes[] = ['Écrans', array_slice($ecrans, 0, $par_groupe)];
    if (mb_strlen($q) < 3) return $groupes;
    $comme = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $limite = ' LIMIT ' . (int) $par_groupe;
    if (peut('agenda')) {
        $l = [];
        foreach (bd_lignes("SELECT c.id, c.titre_en, c.ensemble_en, c.ville_en, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id AND r.date >= CURDATE()) AS prochaine,
                                   (SELECT MAX(r.date) FROM representations r WHERE r.concert_id = c.id) AS derniere
                            FROM concerts c WHERE c.titre_en LIKE ? OR c.ensemble_en LIKE ? OR c.ville_en LIKE ? OR c.salle_en LIKE ? ORDER BY prochaine IS NULL, prochaine, derniere DESC" . $limite, [$comme, $comme, $comme, $comme]) as $c) {
            $l[] = [$c['titre_en'], trim(date_fr($c['prochaine'] ?: $c['derniere']) . ' · ' . $c['ville_en'], ' ·'), url('concert', ['id' => $c['id']]), 'concerts'];
        }
        if ($l) $groupes[] = ['Concerts', $l];
    }
    if (peut('contenus')) {
        $l = [];
        foreach (bd_lignes("SELECT p.id, p.titre_en, p.date_article, a.nom FROM articles p LEFT JOIN annuaire a ON a.id = p.media_id
                            WHERE p.titre_en LIKE ? OR a.nom LIKE ? OR p.auteur LIKE ? ORDER BY p.date_article DESC" . $limite, [$comme, $comme, $comme]) as $p) {
            $l[] = [preg_replace('/\*/', '', (string) $p['titre_en']), trim(($p['nom'] ?? '') . ' · ' . $p['date_article'], ' ·'), url('article', ['id' => $p['id']]), 'presse'];
        }
        if ($l) $groupes[] = ['Presse', $l];
        $l = [];
        foreach (bd_lignes('SELECT id, titre, annee FROM disques WHERE titre LIKE ? OR descriptif_en LIKE ? ORDER BY ordre' . $limite, [$comme, $comme]) as $d) {
            $l[] = [preg_replace('/\*/', '', (string) $d['titre']), (string) $d['annee'], url('disque', ['id' => $d['id']]), 'disques'];
        }
        if ($l) $groupes[] = ['Discographie', $l];
    }
    if (peut('annuaire')) {
        $l = [];
        foreach (bd_lignes('SELECT id, nom, categorie, ville_en FROM annuaire WHERE nom LIKE ? OR autres_noms LIKE ? ORDER BY nom' . $limite, [$comme, $comme]) as $f) {
            $l[] = [$f['nom'], trim((CATEGORIES[$f['categorie']] ?? $f['categorie']) . ' · ' . $f['ville_en'], ' ·'), url('fiche', ['id' => $f['id']]), 'annuaire'];
        }
        if ($l) $groupes[] = ['Annuaire', $l];
    }
    if (peut('communication') && bd_table_existe('communications')) {
        $l = [];
        foreach (bd_lignes('SELECT * FROM communications WHERE titre LIKE ? OR texte LIKE ? ORDER BY COALESCE(publiee_le, prevue_le) DESC' . $limite, [$comme, $comme]) as $a) {
            $l[] = [communication_libelle($a), (CANAUX[$a['canal']][0] ?? '') . ' · ' . mb_strtolower(communication_statut($a)), url('communication', ['id' => $a['id']]), CANAUX[$a['canal']][1] ?? 'social'];
        }
        if ($l) $groupes[] = ['Communication', $l];
    }
    return $groupes;
}
