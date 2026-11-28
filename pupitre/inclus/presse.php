<?php
/**
 * Revue de presse : la collection des articles, leurs citations, leurs relations.
 *
 * Un article est un objet indépendant, jamais dupliqué : l'accueil, la page Presse, les fiches de
 * concerts et les panneaux d'albums montrent le même article. Il a un type, un média (une fiche de
 * l'annuaire, catégorie « media » : le référentiel des médias de presse), un auteur, une date, une
 * langue d'origine, un chapeau, un résumé, des citations, des visuels, des concerts et des albums
 * associés. Trois réglages indépendants : publié ou non ; sélectionné pour l'accueil, avec son rang
 * et sa largeur ; « à la une ». Une mise en avant ne rend jamais visible un article dépublié.
 *
 * Citations : chaque citation est gardée dans sa langue et sa formulation d'origine (texte, langue).
 * Sa traduction est à part (traduction_en pour l'anglais, la table des traductions pour les autres langues) :
 * le site ne présente jamais une traduction, ni un résumé, comme la citation d'origine. Une citation
 * proposée par l'IA n'est retenue que si elle se retrouve mot pour mot dans le texte de l'article
 * (citation_dans_texte) ; elle reste « à vérifier » tant qu'une personne ne l'a pas validée.
 */

defined('PUPITRE') or exit;

const TYPES_ARTICLE = [
    'critique_concert' => 'Critique de concert', 'critique_album' => 'Critique d\'album', 'portrait' => 'Portrait',
    'interview' => 'Interview', 'actualite' => 'Article d\'actualité', 'autre' => 'Autre publication',
];

/** Largeurs possibles d'un article sur l'accueil, en colonnes de la grille de quatre. */
const LARGEURS_ARTICLE = [1 => 'Une colonne (un quart)', 2 => 'Deux colonnes (la moitié)', 4 => 'Quatre colonnes (toute la largeur)'];

/** Traitements typographiques du nom d'un média sans logo : valeur => libellé. La valeur est une classe CSS du site. */
const TYPOS_MEDIA = ['' => 'Par défaut : serif italique', 'romain' => 'Serif romain', 'capitales' => 'Linéale, capitales espacées', 'gras' => 'Linéale grasse'];

const MARQUE_MEDIA_EXEMPLE = 'EXEMPLE DE MAQUETTE : média inventé, à supprimer avant la mise en production.';

function article_lire(int $id): ?array
{
    $a = bd_ligne('SELECT p.*, m.nom AS media, m.logo_id, m.typo AS media_typo, m.site AS media_site FROM articles p LEFT JOIN annuaire m ON m.id = p.media_id WHERE p.id = ?', [$id]);
    if (!$a) return null;
    $a['citations'] = bd_lignes('SELECT * FROM articles_citations WHERE article_id = ? ORDER BY principale DESC, ordre, id', [$id]);
    $a['concerts'] = array_map('intval', bd_colonne('SELECT concert_id FROM articles_concerts WHERE article_id = ?', [$id]));
    $a['disques'] = array_map('intval', bd_colonne('SELECT disque_id FROM articles_disques WHERE article_id = ?', [$id]));
    return $a;
}

/** Tous les articles, dans l'ordre de la collection, avec leur média. Filtres : q, type, media, etat (publies, depublies, accueil, exemples). */
function articles_lister(array $f = []): array
{
    $ou = ['1 = 1'];
    $p = [];
    if (!empty($f['type'])) { $ou[] = 'p.type = ?'; $p[] = $f['type']; }
    if (!empty($f['media'])) { $ou[] = 'p.media_id = ?'; $p[] = (int) $f['media']; }
    if (($f['etat'] ?? '') === 'publies') $ou[] = 'p.publie = 1';
    if (($f['etat'] ?? '') === 'depublies') $ou[] = 'p.publie = 0';
    if (($f['etat'] ?? '') === 'accueil') $ou[] = 'p.accueil = 1';
    if (($f['etat'] ?? '') === 'exemples') $ou[] = 'p.exemple = 1';
    if (!empty($f['q'])) {
        $ou[] = '(p.titre_en LIKE ? OR p.auteur LIKE ? OR m.nom LIKE ? OR p.resume_en LIKE ? OR EXISTS (SELECT 1 FROM articles_citations c WHERE c.article_id = p.id AND c.texte LIKE ?))';
        $q = '%' . $f['q'] . '%';
        array_push($p, $q, $q, $q, $q, $q);
    }
    return bd_lignes('SELECT p.*, m.nom AS media, m.logo_id,
            (SELECT texte FROM articles_citations c WHERE c.article_id = p.id ORDER BY c.principale DESC, c.ordre, c.id LIMIT 1) AS citation
        FROM articles p LEFT JOIN annuaire m ON m.id = p.media_id WHERE ' . implode(' AND ', $ou) . ' ORDER BY p.ordre, p.id', $p);
}

/**
 * Compose l'accueil. $ordre : les articles qui y paraissent, dans l'ordre où ils paraissent ; $largeurs : la
 * largeur de chacun (1, 2 ou 4 colonnes). Le rang d'un article est sa place dans la liste (1, 2, 3…) : il ne se
 * saisit pas, il se déduit. Un article absent de la liste quitte l'accueil (il garde sa largeur).
 * Journalise ce qui a changé. Rend le nombre d'articles modifiés.
 */
function accueil_composer(array $ordre, array $largeurs = []): int
{
    $ordre = array_values(array_unique(array_filter(array_map('intval', $ordre))));
    $avant = $apres = [];
    bd_transaction(function () use ($ordre, $largeurs, &$avant, &$apres) {
        $rangs = array_flip($ordre);
        foreach (bd_lignes('SELECT id, cle, accueil, ordre_accueil, largeur FROM articles') as $x) {
            $id = (int) $x['id'];
            $sur = isset($rangs[$id]);
            $largeur = (int) ($largeurs[$id] ?? $x['largeur']);
            $d = ['accueil' => $sur ? 1 : 0, 'ordre_accueil' => $sur ? $rangs[$id] + 1 : 0, 'largeur' => isset(LARGEURS_ARTICLE[$largeur]) ? $largeur : (int) $x['largeur']];
            $etait = ['accueil' => (int) $x['accueil'], 'ordre_accueil' => (int) $x['ordre_accueil'], 'largeur' => (int) $x['largeur']];
            // un article hors de l'accueil ne change pas de rang pour autant : seul son retrait compte
            if (!$sur && !$etait['accueil']) continue;
            if ($d == $etait) continue;
            $avant[$x['cle']] = ['accueil' => $etait['accueil'], 'rang' => $etait['ordre_accueil'], 'largeur' => $etait['largeur']];
            $apres[$x['cle']] = ['accueil' => $d['accueil'], 'rang' => $d['ordre_accueil'], 'largeur' => $d['largeur']];
            bd_modifier('articles', $d, ['id' => $id]);
        }
    });
    if ($apres) {
        journaliser('modification', 'article', null, 'Revue de presse : composition de l\'accueil', $avant, $apres);
        $GLOBALS['donnees_modifiees'] = true;
    }
    return count($apres);
}

/** Les articles de l'accueil, dans l'ordre où ils paraissent. $tous : articles_lister(). */
function accueil_articles(array $tous): array
{
    $accueil = array_values(array_filter($tous, fn($a) => $a['accueil']));
    usort($accueil, fn($x, $y) => [(int) $x['ordre_accueil'], (int) $x['id']] <=> [(int) $y['ordre_accueil'], (int) $y['id']]);
    return $accueil;
}

/** Identifiant de l'article dans les fichiers du site, unique : « le-monde-portrait ». */
function article_cle(string $media, string $titre, ?int $sauf = null): string
{
    return cle_unique('articles', trim($media . ' ' . $titre) ?: 'article', $sauf);
}

/** Adresse ramenée à ce qui la distingue : sans protocole, sans « www. », sans paramètres de suivi, sans barre finale. Pour repérer un doublon. */
function adresse_canon(string $u): string
{
    $u = trim($u);
    if ($u === '') return '';
    $p = parse_url($u);
    if (empty($p['host'])) return mb_strtolower($u);
    $params = [];
    parse_str($p['query'] ?? '', $params);
    foreach (array_keys($params) as $k) {
        if (preg_match('/^(utm_|fbclid|gclid|mc_|xtor|at_|ref$|source$|cmp$)/i', (string) $k)) unset($params[$k]);
    }
    ksort($params);
    return preg_replace('/^www\./', '', mb_strtolower($p['host'])) . rtrim($p['path'] ?? '', '/') . ($params ? '?' . http_build_query($params) : '');
}

/**
 * Doublons probables d'un article : même adresse (ou même adresse canonique), ou même média avec
 * le même titre ou la même date et un titre voisin. Rend [[article, raison], …]. $sauf : l'article lui-même.
 */
function article_doublons(array $a, ?int $sauf = null): array
{
    $trouves = [];
    $adresses = array_values(array_filter(array_map('adresse_canon', [(string) ($a['lien'] ?? ''), (string) ($a['url_canonique'] ?? '')]), 'strlen'));
    $titre = normaliser(preg_replace('/\*/', '', (string) ($a['titre_en'] ?? '')));
    foreach (bd_lignes('SELECT p.id, p.titre_en, p.lien, p.url_canonique, p.media_id, p.date_article, m.nom AS media FROM articles p LEFT JOIN annuaire m ON m.id = p.media_id') as $b) {
        if ($sauf !== null && (int) $b['id'] === $sauf) continue;
        $raison = '';
        if ($adresses && array_intersect($adresses, array_filter(array_map('adresse_canon', [$b['lien'], $b['url_canonique']]), 'strlen'))) $raison = 'même adresse';
        elseif (!empty($a['media_id']) && (int) $a['media_id'] === (int) $b['media_id']) {
            $t = normaliser(preg_replace('/\*/', '', $b['titre_en']));
            if ($titre !== '' && $titre === $t) $raison = 'même média, même titre';
            elseif ($titre !== '' && $t !== '' && ($a['date_article'] ?? '') !== '' && $a['date_article'] === $b['date_article']) {
                similar_text($titre, $t, $pourcent);
                if ($pourcent >= 70) $raison = 'même média, même date, titre voisin';
            }
        }
        if ($raison !== '') $trouves[] = [$b, $raison];
    }
    return $trouves;
}

/** Ramène un texte à ses lettres et à ses chiffres, sans accents ni ponctuation ni guillemets : pour chercher une citation dans un article. */
function texte_nu(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return normaliser($s);
}

/** Une citation se retrouve-t-elle mot pour mot dans le texte de l'article ? (aux guillemets, à la ponctuation et aux espaces près) */
function citation_dans_texte(string $citation, string $texte): bool
{
    $c = texte_nu($citation);
    return $c !== '' && mb_strlen($c) >= 8 && str_contains(texte_nu($texte), $c);
}

/**
 * Enregistre les citations d'un article. $recues : lignes [id, texte, langue, traduction_en, principale, verifiee],
 * et, pour une citation lue par l'IA, ia_tr [langue => traduction proposée] : rangée parmi les traductions à valider.
 * Une seule citation principale : la première cochée, sinon la première de la liste.
 */
function citations_enregistrer(int $article_id, array $recues, string $principale = ''): void
{
    $existantes = [];
    foreach (bd_lignes('SELECT * FROM articles_citations WHERE article_id = ?', [$article_id]) as $c) $existantes[(int) $c['id']] = $c;
    $gardees = [];
    $lignes = [];
    foreach ($recues as $i => $r) {                  // la clé de la ligne dans le formulaire désigne la citation principale
        if (!is_array($r)) continue;
        $texte = trim(str_replace("\r\n", "\n", (string) ($r['texte'] ?? '')));
        if ($texte === '') continue;
        $lignes[] = [(string) $i, ctype_digit((string) ($r['id'] ?? '')) && isset($existantes[(int) $r['id']]) ? (int) $r['id'] : null, $r, $texte];
    }
    $rang_principal = null;
    foreach ($lignes as $k => [$i]) if ($principale !== '' && $principale === $i) $rang_principal = $k;
    if ($rang_principal === null && $lignes) $rang_principal = 0;
    foreach ($lignes as $k => [$i, $id, $r, $texte]) {
        $d = ['texte' => mb_substr($texte, 0, 5000), 'langue' => preg_match('/^[a-z]{2,3}$/', mb_strtolower(trim((string) ($r['langue'] ?? '')))) ? mb_strtolower(trim((string) $r['langue'])) : '',
            'principale' => $k === $rang_principal ? 1 : 0, 'verifiee' => empty($r['verifiee']) ? 0 : 1, 'ordre' => $k] + recu_tr($r, 'traduction', 5000);
        if ($id) { $gardees[] = $id; bd_modifier('articles_citations', $d, ['id' => $id]); }
        else $id = bd_inserer('articles_citations', $d + ['article_id' => $article_id]);
        foreach (is_array($r['ia_tr'] ?? null) ? $r['ia_tr'] : [] as $langue => $t) {
            if (isset(langues_toutes()[$langue]) && $langue !== $d['langue'] && is_string($t)) traduction_poser('articles_citations', (string) $id, 'traduction', (string) $langue, mb_substr($t, 0, 5000), 'ia');
        }
    }
    foreach ($existantes as $id => $c) if (!in_array($id, $gardees, true)) bd_supprimer('articles_citations', ['id' => $id]);
}

/** Pose les concerts et les albums associés à un article. */
function article_relations_poser(int $article_id, array $concerts, array $disques): void
{
    foreach (['articles_concerts' => ['concert_id', $concerts, 'concerts'], 'articles_disques' => ['disque_id', $disques, 'disques']] as $table => [$col, $ids, $cible]) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) $ids = array_map('intval', bd_colonne('SELECT id FROM ' . bd_nom($cible) . ' WHERE id IN (' . implode(',', $ids) . ')'));
        $avant = array_map('intval', bd_colonne('SELECT ' . bd_nom($col) . ' FROM ' . bd_nom($table) . ' WHERE article_id = ?', [$article_id]));
        sort($avant); sort($ids);
        if ($avant === $ids) continue;
        bd_requete('DELETE FROM ' . bd_nom($table) . ' WHERE article_id = ?', [$article_id]);
        foreach ($ids as $x) bd_requete('INSERT INTO ' . bd_nom($table) . ' (article_id, ' . bd_nom($col) . ') VALUES (?, ?)', [$article_id, $x]);
        journaliser('modification', 'article', $article_id, 'Article : ' . ($cible === 'concerts' ? 'concerts associés' : 'albums associés'), [$cible => $avant], [$cible => $ids]);
    }
}

/** Articles associés à un concert ou à un album. $table : articles_concerts ou articles_disques. */
function articles_de(string $objet, int $id): array
{
    [$table, $col] = $objet === 'concert' ? ['articles_concerts', 'concert_id'] : ['articles_disques', 'disque_id'];
    return bd_lignes('SELECT p.*, m.nom AS media FROM ' . $table . ' x JOIN articles p ON p.id = x.article_id LEFT JOIN annuaire m ON m.id = p.media_id WHERE x.' . $col . ' = ? ORDER BY p.date_article DESC, p.ordre', [$id]);
}

function article_supprimer(int $id): void
{
    $a = bd_ligne('SELECT * FROM articles WHERE id = ?', [$id]);
    if (!$a) return;
    bd_transaction(function () use ($id, $a) {
        bd_supprimer('articles_citations', ['article_id' => $id]);
        bd_requete('DELETE FROM articles_concerts WHERE article_id = ?', [$id]);
        bd_requete('DELETE FROM articles_disques WHERE article_id = ?', [$id]);
        bd_supprimer('medias_usages', ['objet' => 'articles', 'objet_id' => (string) $id]);
        objet_supprimer('articles', $id, 'article', preg_replace('/\*/', '', $a['titre_en']) ?: $a['cle']);
    });
}

/** Supprime d'un geste tous les faux articles de maquette, leurs médias inventés et leurs logos. Rend le nombre d'articles supprimés. */
function articles_exemples_supprimer(): int
{
    $ids = array_map('intval', bd_colonne('SELECT id FROM articles WHERE exemple = 1'));
    foreach ($ids as $id) article_supprimer($id);
    foreach (bd_lignes("SELECT id, nom, logo_id FROM annuaire WHERE categorie = 'media' AND notes = ?", [MARQUE_MEDIA_EXEMPLE]) as $f) {
        if (bd_valeur('SELECT COUNT(*) FROM articles WHERE media_id = ?', [$f['id']])) continue;       // un vrai article l'emploie : il reste
        bd_supprimer('annuaire', ['id' => $f['id']]);
        if ($f['logo_id'] && !media_emplois((int) $f['logo_id'])) bd_supprimer('medias', ['id' => $f['logo_id']]);
    }
    $GLOBALS['annuaire_perime'] = $GLOBALS['medias_perimes'] = true;
    if ($ids) journaliser('suppression', 'article', null, 'Faux articles de maquette', ['articles' => count($ids)], null);
    return count($ids);
}

/** Visuels d'un objet (article, album) : lignes de medias_usages, rôle « visuel », avec leur média. */
function visuels_de(string $objet, int $id): array
{
    $medias = medias_par_id();
    $l = [];
    foreach (bd_lignes("SELECT * FROM medias_usages WHERE objet = ? AND objet_id = ? AND role = 'visuel' ORDER BY ordre, id", [$objet, (string) $id]) as $u) {
        $u['media'] = $medias[(int) $u['media_id']] ?? null;
        $l[] = $u;
    }
    return $l;
}

/** Visuels d'un objet tels que le site les lit : ceux qui sont publiés et dont le média peut sortir. */
function visuels_exporter(string $objet, int $id): array
{
    $v = [];
    foreach (visuels_de($objet, $id) as $u) {
        if (!$u['publie'] || !media_publiable($u['media'])) continue;
        $alt = multi('medias_usages', $u, 'alt');
        if ($alt === '') $alt = multi('medias', $u['media'], 'alt');
        $e = ['src' => media_chemin_site($u['media']), 'alt' => $alt];
        $credit = trim((string) $u['credit']) !== '' ? trim((string) $u['credit']) : trim((string) $u['media']['credit']);
        if ($credit !== '') $e['credit'] = $credit;
        $v[] = $e;
    }
    return $v;
}

/**
 * Traite les actions de la liste de visuels d'un objet (écrans Album et Article) : ajouter (média
 * de la médiathèque, fichier déposé ou adresse externe), monter, descendre, retirer, régler.
 * Rend un message d'erreur, '' si une action a été faite, null s'il n'y avait rien à faire.
 */
function visuels_traiter(string $objet, int $id, string $libelle): ?string
{
    $action = recu('visuel');
    if ($action === '') return null;
    $nom = OBJETS_A_MEDIAS[$objet][0] . ' « ' . $libelle . ' »';
    if ($action === 'ajouter') {
        $media = null;
        if (!empty($_FILES['fichier']['name'])) $media = media_deposer($_FILES['fichier'], ['credit' => recu('visuel_credit')], ['image']);
        elseif (recu('adresse') !== '') $media = media_creer_externe(recu('adresse'), 'image', ['credit' => recu('visuel_credit'), 'droits' => recu('visuel_droits')]);
        elseif (recu_entier('media_id')) $media = recu_entier('media_id');
        else return 'Choisissez une image, déposez un fichier ou donnez une adresse.';
        if (is_string($media)) return $media;
        $m = medias_par_id()[$media] ?? null;
        if (!$m || $m['type'] !== 'image') return 'Un visuel est une image.';
        // une image venue de la presse par son adresse n'est pas publiée d'office : ses droits sont à confirmer
        if ($objet === 'articles' && recu('adresse') !== '') { bd_modifier('medias', ['droits_valides' => 0], ['id' => $media]); $GLOBALS['medias_perimes'] = true; }
        $ordre = (int) bd_valeur("SELECT COALESCE(MAX(ordre), -1) + 1 FROM medias_usages WHERE objet = ? AND objet_id = ? AND role = 'visuel'", [$objet, (string) $id]);
        objet_enregistrer('medias_usages', null, ['media_id' => $media, 'objet' => $objet, 'objet_id' => (string) $id, 'role' => 'visuel', 'nature' => 'image', 'publie' => 1, 'ordre' => $ordre], 'visuel', $nom . ' · ' . media_nom($m));
        return '';
    }
    $u = bd_ligne("SELECT * FROM medias_usages WHERE id = ? AND objet = ? AND objet_id = ? AND role = 'visuel'", [recu_entier('usage') ?? 0, $objet, (string) $id]);
    if (!$u) return 'Visuel introuvable.';
    if ($action === 'retirer') { objet_supprimer('medias_usages', (int) $u['id'], 'visuel', $nom); return ''; }
    if ($action === 'monter' || $action === 'descendre') {
        $ordre = array_map('intval', bd_colonne("SELECT id FROM medias_usages WHERE objet = ? AND objet_id = ? AND role = 'visuel' ORDER BY ordre, id", [$objet, (string) $id]));
        $i = array_search((int) $u['id'], $ordre, true);
        $j = $i + ($action === 'monter' ? -1 : 1);
        if ($i !== false && $j >= 0 && $j < count($ordre)) {
            [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            foreach ($ordre as $rang => $x) bd_modifier('medias_usages', ['ordre' => $rang], ['id' => $x]);
        }
        return '';
    }
    if ($action === 'regler') {
        $r = is_array($_POST['v'][$u['id']] ?? null) ? $_POST['v'][$u['id']] : [];
        objet_enregistrer('medias_usages', (int) $u['id'], recu_tr($r, 'alt', 500) + ['credit' => mb_substr(trim((string) ($r['credit'] ?? '')), 0, 255), 'publie' => empty($r['publie']) ? 0 : 1], 'visuel', $nom);
        return '';
    }
    return null;
}
