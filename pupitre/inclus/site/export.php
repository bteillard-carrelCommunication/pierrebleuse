<?php
/**
 * Adaptateur du site, première moitié : de la base aux fichiers de data/.
 *
 * Chaque fonction export_…() rend les données d'un fichier, dans la forme exacte que lit le
 * site (voir LISEZ-MOI.md du site, « Données »). site_fichiers() en fait le texte des fichiers.
 * Le site ne reçoit que les représentations annoncées par leur organisateur : statuts
 * « annoncee » et « publiee », et rien d'autre (export_agenda).
 *
 * C'est ici, et dans repli.php, que Pupitre connaît la forme du site. Si le site change de
 * gabarit, ce sont ces deux fichiers qu'on adapte.
 */

defined('PUPITRE') or exit;

/** Statuts qui sortent sur le site. */
const STATUTS_SUR_LE_SITE = ['annoncee', 'publiee'];

const NOTE_AGENDA = 'Uniquement des représentations annoncées par leur organisateur, de concerts publiés. Tout champ texte est soit une chaîne (le texte anglais, qui vaut pour toutes les langues), soit un objet {en, fr, …}. Une tournée porte ville, pays, salle et lien dans chaque représentation. Dates et heures locales de la salle.';

/**
 * Champ en deux langues : une chaîne si l'anglais est vide ou identique, sinon { fr, en }.
 * Gardé pour la reprise (sql/depart.php). Les exports passent par multi() (inclus/langues.php),
 * qui lit toutes les langues dans la table des traductions.
 */
function bi(?string $fr, ?string $en)
{
    $fr = trim((string) $fr);
    $en = trim((string) $en);
    return ($en === '' || $en === $fr) ? $fr : ['fr' => $fr, 'en' => $en];
}

/** Lit un champ du site (chaîne ou { fr, en }) : [fr, en], l'anglais vide s'il est identique. */
function bi_lire($champ): array
{
    if ($champ === null) return ['', ''];
    if (is_array($champ)) {
        $fr = trim((string) ($champ['fr'] ?? ''));
        $en = trim((string) ($champ['en'] ?? ''));
        return [$fr, $en === $fr ? '' : $en];
    }
    return [trim((string) $champ), ''];
}

/**
 * Chemin d'un média pour le site : son adresse externe s'il n'est que cela, sinon le chemin qu'il a
 * déjà dans le site, sinon celui qu'il aura une fois copié à la publication.
 */
function media_chemin_site(?array $m): string
{
    if (!$m) return '';
    if (!empty($m['url_externe'])) return $m['url_externe'];
    if (!empty($m['chemin_site'])) return $m['chemin_site'];
    return empty($m['fichier']) ? '' : 'assets/pupitre/' . $m['fichier'];
}

function medias_par_id(): array
{
    static $m = null;
    if ($m === null || !empty($GLOBALS['medias_perimes'])) {
        $m = [];
        foreach (bd_lignes('SELECT * FROM medias') as $l) $m[(int) $l['id']] = $l;
        $GLOBALS['medias_perimes'] = false;
    }
    return $m;
}

/** Champ multiligne : une ligne par ligne affichée, chacune dans chaque langue (le rang fait le lien). */
function multi_lignes(string $objet, array $ligne, string $champ): array
{
    $reference = lignes($ligne[colonne_reference($objet, $champ)] ?? '');
    $autres = [];
    foreach (langues_autres() as $langue) $autres[$langue] = lignes(traduction_publiee($objet, $ligne[cle_primaire($objet)], $champ, $langue));
    $v = [];
    foreach ($reference as $i => $l) $v[] = multi_valeurs($l, array_map(fn($x) => $x[$i] ?? '', $autres));
    return $v;
}

// ---------- Un fichier, une fonction ----------

/**
 * Réglages généraux du site : langues, blocs visibles. Lu par js/contenu.js, dans le <head> de
 * chaque page, avant tout le reste. La couleur de fond n'est pas ici : elle n'a qu'une place,
 * css/reglages.css (site_reglages_css()).
 */
function export_site(): array
{
    $langues = [];
    foreach (langues_toutes() as $code => $l) {
        if ($l['active'] || $l['reference']) $langues[] = ['code' => $code, 'nom' => $l['nom'], 'locale' => $l['locale'] ?: $code];
    }
    return [
        'langues' => ['reference' => langue_reference(), 'defaut' => langue_defaut(), 'liste' => $langues],
        'blocs' => (object) blocs_visibilite(),
    ];
}

/** Libellés de l'interface du site : clé => texte, dans chaque langue. */
function export_libelles(): object
{
    $l = [];
    foreach (bd_lignes('SELECT * FROM libelles ORDER BY ordre, cle') as $x) $l[$x['cle']] = multi('libelles', $x, 'texte');
    return (object) $l;
}

function export_textes(): array
{
    $t = [];
    foreach (bd_lignes('SELECT * FROM textes') as $l) $t[$l['cle']] = $l;
    $champ = fn(string $cle) => multi('textes', $t[$cle] ?? ['cle' => $cle], 'texte');
    // la biographie : un paragraphe par bloc, chaque paragraphe dans chaque langue
    $reference = paragraphes($t['biographie'][langue_reference()] ?? '');
    $autres = [];
    foreach (langues_autres() as $langue) $autres[$langue] = paragraphes(traduction_publiee('textes', 'biographie', 'texte', $langue));
    $bio = [];
    foreach ($reference as $i => $p) $bio[] = multi_valeurs($p, array_map(fn($x) => $x[$i] ?? '', $autres));
    return [
        'description' => $champ('description'),
        'citation' => $champ('citation'),
        'biographie' => $bio,
        'saison' => $champ('saison'),
        'lettre' => $champ('lettre'),
        'credit' => $champ('credit'),
    ];
}

/**
 * L'agenda du site. $statuts : par défaut ceux qui sortent sur le site ; la vue de l'artiste
 * et les contrôles ne passent jamais par ici pour écrire un fichier.
 */
function export_agenda(array $statuts = STATUTS_SUR_LE_SITE): array
{
    $statuts = array_values(array_intersect($statuts, ['connue', 'embargo', 'annoncee', 'publiee', 'annulee']));
    $maisons = [];
    $cles_maisons = [];
    foreach (bd_lignes('SELECT * FROM maisons ORDER BY ordre, id') as $m) {
        $maisons[$m['cle']] = multi('maisons', $m, 'nom');
        $cles_maisons[(int) $m['id']] = $m['cle'];
    }
    $evenements = [];
    $version = '';
    if ($statuts) {
        $marques = implode(',', array_fill(0, count($statuts), '?'));
        $reps = bd_lignes("SELECT * FROM representations WHERE statut IN ($marques) ORDER BY date, heure, id", $statuts);
        $par_concert = [];
        foreach ($reps as $r) $par_concert[(int) $r['concert_id']][] = $r;
        // un concert dépublié ne sort pas, quel que soit le statut de ses dates
        $concerts = $par_concert
            ? bd_lignes('SELECT * FROM concerts WHERE publie = 1 AND id IN (' . implode(',', array_keys($par_concert)) . ')')
            : [];
        // dans l'ordre de la première représentation qui sort
        usort($concerts, fn($a, $b) => [$par_concert[$a['id']][0]['date'], $a['id']] <=> [$par_concert[$b['id']][0]['date'], $b['id']]);
        foreach ($concerts as $c) {
            $id = (int) $c['id'];
            $e = [
                'id' => $c['cle'],
                'type' => $c['type'],
                'titre' => multi('concerts', $c, 'titre'),
                'ensemble' => multi('concerts', $c, 'ensemble'),
                'maison' => $cles_maisons[(int) $c['maison_id']] ?? '',
            ];
            foreach (['ville', 'pays', 'salle'] as $k) {
                if ($c[$k . '_en'] !== '') $e[$k] = multi('concerts', $c, $k);
            }
            $e['representations'] = [];
            foreach ($par_concert[$id] as $r) {
                $l = ['date' => $r['date']];
                if ($r['heure']) $l['heure'] = $r['heure'];
                foreach (['ville', 'pays', 'salle'] as $k) {
                    if ($r[$k . '_en'] !== '') $l[$k] = multi('representations', $r, $k);
                }
                if ($r['lien_billetterie'] !== '') $l['lien'] = $r['lien_billetterie'];
                $e['representations'][] = $l;
                $version = max($version, substr($r['modifie_le'], 0, 10));
            }
            $avec = [];
            foreach (bd_lignes('SELECT * FROM participants WHERE concert_id = ? ORDER BY ordre, id', [$id]) as $p) {
                $avec[] = ['nom' => $p['nom'], 'role' => multi('participants', $p, 'role')];
            }
            if ($avec) $e['avec'] = $avec;
            $programme = [];
            foreach (bd_lignes('SELECT * FROM programme WHERE concert_id = ? ORDER BY ordre, id', [$id]) as $p) {
                $l = ['compositeur' => multi('programme', $p, 'compositeur'), 'oeuvre' => multi('programme', $p, 'oeuvre')];
                if ($p['mention_en'] !== '') $l['mention'] = multi('programme', $p, 'mention');
                $programme[] = $l;
            }
            if ($programme) $e['programme'] = $programme;
            if (trim((string) $c['mentions_en']) !== '') $e['mentions'] = multi('concerts', $c, 'mentions');
            if (trim((string) $c['description_en']) !== '') $e['description'] = multi('concerts', $c, 'description');
            $e['tempsFort'] = (bool) $c['temps_fort'];
            $e['lien'] = $c['lien_billetterie'] !== '' ? $c['lien_billetterie'] : $c['page_organisateur'];
            $evenements[] = $e;
            $version = max($version, substr($c['modifie_le'], 0, 10));
        }
    }
    return [
        'version' => $version ?: date('Y-m-d'),
        'note' => NOTE_AGENDA,
        'maisons' => (object) $maisons,
        'evenements' => $evenements,
    ];
}

/**
 * La playlist : les titres publiés qui ont une source (fichier de la médiathèque ou adresse), dans
 * l'ordre. Le morceau d'ouverture porte « ouverture » : le site commence par lui, quel que soit son
 * rang. Le lecteur affiche le titre et la mention ; les autres champs décrivent l'enregistrement.
 */
function export_playlist(): array
{
    $m = medias_par_id();
    $liste = [];
    foreach (bd_lignes('SELECT * FROM musiques WHERE publie = 1 ORDER BY ordre, id') as $t) {
        $media = $m[(int) $t['media_id']] ?? null;
        $src = trim((string) $t['url']) !== '' ? trim((string) $t['url']) : (media_publiable($media) ? media_chemin_site($media) : '');
        if ($src === '') continue;                       // pas de source : le titre ne sort pas
        $e = [
            'id' => $t['cle'],
            'src' => $src,
            'piste' => $t['piste'],
            'titre' => multi('musiques', $t, 'titre'),
            'mention' => multi('musiques', $t, 'mention'),
        ];
        foreach (['compositeur', 'oeuvre', 'mouvement', 'interpretes'] as $k) {
            if (trim((string) $t[$k . '_en']) !== '') $e[$k] = multi('musiques', $t, $k);
        }
        if (trim((string) $t['orchestre']) !== '') $e['orchestre'] = $t['orchestre'];
        if ($t['nature'] !== '') $e['nature'] = $t['nature'];
        if (trim((string) $t['credit']) !== '') $e['credit'] = $t['credit'];
        if ($t['ouverture']) $e['ouverture'] = true;
        $liste[] = $e;
    }
    return $liste;
}

/**
 * Les blocs à médias : pour chaque bloc affiché, les éléments que le site montre (inclus/visuels.php).
 * Un bloc masqué n'envoie rien : son contenu reste dans Pupitre.
 */
function export_visuels(): object
{
    $v = [];
    foreach (BLOCS_A_MEDIAS as $bloc => $role) {
        if (!bloc_visible($bloc)) continue;
        $elements = array_map('element_exporter', elements_affiches($bloc));
        if ($elements) $v[$bloc] = ['elements' => $elements];
    }
    return (object) $v;
}

/** « Nom | adresse », un par ligne → [{ nom, url }]. */
function liens_lire(?string $texte): array
{
    $liens = [];
    foreach (lignes($texte) as $l) {
        $p = array_map('trim', explode('|', $l, 2));
        $url = $p[1] ?? $p[0];
        if (!adresse_valide($url) || $url === '') continue;
        $liens[] = ['nom' => isset($p[1]) ? $p[0] : (string) parse_url($url, PHP_URL_HOST), 'url' => $url];
    }
    return $liens;
}

/** Lignes d'un champ non traduit (compositeurs…) : une par ligne. */
function lignes_simples(?string $texte): array
{
    return lignes($texte);
}

/**
 * La discographie : les albums publiés, dans leur ordre. Chaque album porte de quoi dessiner sa
 * vignette (année, label, titre, descriptif, distinctions mises en avant) et son panneau détaillé.
 * Ne sortent que les relations vers des objets publiés : articles publiés, distinctions publiées.
 */
function export_disques(): array
{
    $m = medias_par_id();
    $liste = [];
    foreach (bd_lignes('SELECT d.*, a.nom AS maison FROM disques d LEFT JOIN annuaire a ON a.id = d.maison_id WHERE d.publie = 1 ORDER BY d.ordre, d.id') as $d) {
        $id = (int) $d['id'];
        $descriptif = multi_lignes('disques', $d, 'descriptif');
        $e = [
            'id' => $d['cle'],
            'titre' => $d['titre'],
            'annee' => $d['annee'] === null ? '' : (int) $d['annee'],
            'maison' => (string) $d['maison'],
            'descriptif' => count($descriptif) === 1 ? $descriptif[0] : ($descriptif ?: ''),
        ];
        if (trim((string) $d['sous_titre_en']) !== '') $e['sousTitre'] = multi('disques', $d, 'sous_titre');
        if ($d['date_sortie'] !== '') $e['sortie'] = $d['date_sortie'];
        if (($x = lignes_simples($d['compositeurs']))) $e['compositeurs'] = $x;
        if (($x = multi_lignes('disques', $d, 'oeuvres'))) $e['oeuvres'] = $x;
        if (trim((string) $d['ensemble']) !== '') $e['ensemble'] = $d['ensemble'];
        if (($x = multi_lignes('disques', $d, 'interpretes'))) $e['interpretes'] = $x;
        // deux textes : la présentation de la rédaction, et la note de l'artiste (présentée comme telle)
        foreach (['presentation', 'mot'] as $k) {
            if (trim((string) ($d[$k . '_en'] ?? '')) !== '') $e[$k] = multi('disques', $d, $k);
        }
        // distinctions publiées ; « une » : montrée dès la vignette
        $distinctions = [];
        foreach (bd_lignes('SELECT * FROM distinctions WHERE disque_id = ? AND publie = 1 ORDER BY ordre, id', [$id]) as $x) {
            $ligne = ['intitule' => multi('distinctions', $x, 'intitule')];
            if ($x['organisme'] !== '') $ligne['organisme'] = $x['organisme'];
            if ($x['annee'] !== '') $ligne['annee'] = annee_de($x['annee']);
            if (trim((string) $x['description_en']) !== '') $ligne['description'] = multi('distinctions', $x, 'description');
            if ($x['url'] !== '') $ligne['url'] = $x['url'];
            $logo = $m[(int) $x['logo_id']] ?? null;
            if (media_publiable($logo)) $ligne['logo'] = media_chemin_site($logo);
            if ($x['une']) $ligne['une'] = true;
            $distinctions[] = $ligne;
        }
        if ($distinctions) $e['distinctions'] = $distinctions;
        // liens : une plateforme ou un distributeur ne sort que s'il a une adresse
        foreach (['ecoute' => 'ecouter', 'commande' => 'commander'] as $nature => $cle) {
            $liens = [];
            foreach (bd_lignes('SELECT * FROM disques_liens WHERE disque_id = ? AND nature = ? ORDER BY ordre, id', [$id, $nature]) as $l) {
                if (!adresse_valide($l['url']) || $l['url'] === '') continue;
                $liens[] = ['plateforme' => $l['plateforme'], 'nom' => $l['nom'], 'url' => $l['url']];
            }
            $e[$cle] = $liens;
        }
        if ($d['lien'] !== '') $e['lien'] = $d['lien'];
        if (($x = visuels_exporter('disques', $id))) $e['visuels'] = $x;
        // critiques : les articles publiés de la revue de presse associés à l'album
        $articles = bd_colonne('SELECT p.cle FROM articles_disques x JOIN articles p ON p.id = x.article_id WHERE x.disque_id = ? AND p.publie = 1 ORDER BY p.une DESC, p.date_article DESC, p.ordre', [$id]);
        if ($articles) $e['articles'] = $articles;
        $liste[] = $e;
    }
    return $liste;
}

/**
 * La revue de presse : les articles publiés, dans l'ordre de la collection. Un article n'existe
 * qu'une fois ; l'accueil, la page Presse, les fiches de concerts et les panneaux d'albums lisent
 * tous ce fichier. « accueil » dit s'il est sélectionné pour l'accueil, avec son rang et sa largeur.
 * Les citations gardent leur langue et leur formulation d'origine ; leur traduction est à part.
 * Ne sortent que les relations vers des concerts et des albums publiés.
 */
function export_presse(): array
{
    $m = medias_par_id();
    $liste = [];
    $sql = 'SELECT p.*, a.nom AS media, a.logo_id, a.typo, a.site AS media_site FROM articles p LEFT JOIN annuaire a ON a.id = p.media_id WHERE p.publie = 1 ORDER BY p.ordre, p.id';
    foreach (bd_lignes($sql) as $a) {
        $id = (int) $a['id'];
        $logo = $m[(int) $a['logo_id']] ?? null;
        $e = [
            'id' => $a['cle'],
            'type' => $a['type'],
            'media' => (string) $a['media'],
            'logo' => media_publiable($logo) ? media_chemin_site($logo) : '',
            'titre' => multi('articles', $a, 'titre'),
            'auteur' => $a['auteur'],
            'date' => $a['date_article'],
            'langue' => $a['langue'],
        ];
        if ((string) $a['typo'] !== '') $e['typo'] = $a['typo'];
        $citations = [];
        foreach (bd_lignes('SELECT * FROM articles_citations WHERE article_id = ? ORDER BY principale DESC, ordre, id', [$id]) as $c) {
            $x = ['texte' => trim($c['texte']), 'langue' => $c['langue'] !== '' ? $c['langue'] : $a['langue']];
            $traductions = [];
            if (trim((string) $c['traduction_en']) !== '') $traductions[langue_reference()] = trim($c['traduction_en']);
            foreach (langues_autres() as $langue) {
                $t = traduction_publiee('articles_citations', $c['id'], 'traduction', $langue);
                if ($t !== '') $traductions[$langue] = $t;
            }
            unset($traductions[$x['langue']]);                   // une citation ne se traduit pas dans sa propre langue
            if ($traductions) $x['traduction'] = $traductions;
            if ($c['principale']) $x['principale'] = true;
            $citations[] = $x;
        }
        $e['citations'] = $citations;
        foreach (['chapeau', 'resume', 'infos'] as $k) {
            $e[$k] = trim((string) $a[$k . '_en']) !== '' ? multi('articles', $a, $k) : '';
        }
        $e['lien'] = $a['lien'];
        if (($x = visuels_exporter('articles', $id))) $e['visuels'] = $x;
        $concerts = array_values(array_filter(bd_lignes('SELECT c.id, c.cle FROM articles_concerts x JOIN concerts c ON c.id = x.concert_id WHERE x.article_id = ? ORDER BY c.id', [$id]), fn($c) => concert_sur_le_site((int) $c['id'])));
        if ($concerts) $e['concerts'] = array_column($concerts, 'cle');
        $albums = bd_colonne('SELECT d.cle FROM articles_disques x JOIN disques d ON d.id = x.disque_id WHERE x.article_id = ? AND d.publie = 1 ORDER BY d.ordre', [$id]);
        if ($albums) $e['albums'] = $albums;
        if ($a['accueil']) { $e['accueil'] = true; $e['rang'] = (int) $a['ordre_accueil']; $e['largeur'] = in_array((int) $a['largeur'], [1, 2, 4], true) ? (int) $a['largeur'] : 1; }
        if ($a['une']) $e['une'] = true;
        if ($a['exemple']) $e['exemple'] = true;                 // faux article de maquette : à supprimer avant la mise en production
        $liste[] = $e;
    }
    return $liste;
}

function export_orchestres(): array
{
    $liste = [];
    // la carte du monde : les lignes « orchestre + ville » affichées. Être dans la collection n'est pas paraître sur la carte.
    $sql = 'SELECT p.*, a.nom, a.nom_provisoire FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE p.affiche = 1 ORDER BY p.ordre, p.id';
    foreach (bd_lignes($sql) as $a) {
        if ($a['latitude'] === null || $a['longitude'] === null) continue;    // sans coordonnées, pas de point : la ligne ne sort pas
        $e = [
            'nom' => $a['nom_provisoire'] ? '' : $a['nom'],
            'ville' => multi('parcours_orchestres', $a, 'ville'),
            'lat' => (float) $a['latitude'],
            'lon' => (float) $a['longitude'],
        ];
        if ($a['zone'] !== '') $e['zone'] = $a['zone'];
        $liste[] = $e;
    }
    return $liste;
}

/**
 * Le parcours : les fonctions publiées, dans leur ordre, et la sélection des grands débuts et
 * collaborations (publiées, cinq au plus). Une collaboration ne nomme son concert que s'il est sur
 * le site, et ne signale une revue de presse que s'il a des articles publiés.
 */
function export_parcours(): array
{
    $fonctions = [];
    foreach (bd_lignes('SELECT * FROM fonctions WHERE publie = 1 ORDER BY ordre, id') as $f) {
        if (trim($f['intitule_en']) === '') continue;
        $e = ['intitule' => multi('fonctions', $f, 'intitule'), 'institution' => multi('fonctions', $f, 'institution'), 'debut' => annee_de($f['debut']), 'fin' => annee_de($f['fin']), 'actuelle' => (bool) $f['actuelle']];
        if (trim((string) $f['description_en']) !== '') $e['description'] = multi('fonctions', $f, 'description');
        if ($f['lien'] !== '') $e['lien'] = $f['lien'];
        if ($f['une']) $e['une'] = true;
        $fonctions[] = $e;
    }
    $collaborations = [];
    foreach (bd_lignes('SELECT * FROM collaborations WHERE publie = 1 ORDER BY ordre, id LIMIT ' . COLLABORATIONS_MAX) as $c) {
        $r = collaboration_resoudre($c);
        if ($r['intitule_en'] === '') continue;
        $e = ['intitule' => multi_valeurs($r['intitule_en'], trim((string) $c['intitule_en']) !== '' ? traductions_publiees('collaborations', $c['id'], 'intitule') : [])];
        if ($r['orchestre'] !== '' && $r['orchestre'] !== $r['intitule_en']) $e['orchestre'] = $r['orchestre'];
        if ($r['annee'] !== '') $e['annee'] = $r['annee'];
        if (trim((string) $c['description_en']) !== '') $e['description'] = multi('collaborations', $c, 'description');
        if ($r['concert'] && concert_sur_le_site((int) $r['concert']['id'])) {
            $e['concert'] = $r['concert']['cle'];
            if (bd_valeur('SELECT COUNT(*) FROM articles_concerts x JOIN articles p ON p.id = x.article_id WHERE x.concert_id = ? AND p.publie = 1', [$r['concert']['id']])) $e['presse'] = true;
        }
        if ($c['une']) $e['une'] = true;
        $collaborations[] = $e;
    }
    return ['fonctions' => $fonctions, 'collaborations' => $collaborations];
}

function export_contacts(): array
{
    $groupes = [];
    foreach (bd_lignes('SELECT * FROM contacts_groupes ORDER BY ordre, id') as $g) {
        $personnes = [];
        foreach (bd_lignes('SELECT * FROM contacts_personnes WHERE groupe_id = ? ORDER BY ordre, id', [$g['id']]) as $p) {
            $personnes[] = ['nom' => $p['nom'], 'role' => multi('contacts_personnes', $p, 'role'), 'telephone' => $p['telephone'], 'email' => $p['email']];
        }
        $groupes[] = [
            'structure' => $g['structure'],
            'lien' => $g['lien'],
            'mention' => multi('contacts_groupes', $g, 'mention'),
            'langue' => $g['langue'],
            'personnes' => $personnes,
        ];
    }
    return $groupes;
}

function export_reseaux(): array
{
    $liste = [];
    foreach (bd_lignes('SELECT * FROM reseaux ORDER BY ordre, id') as $r) {
        $liste[] = ['id' => $r['cle'], 'nom' => $r['nom'], 'url' => $r['url'], 'barre' => (bool) $r['barre']];
    }
    return $liste;
}

function export_telechargements(): array
{
    $m = medias_par_id();
    $plein = fn($v) => is_array($v) ? (bool) array_filter($v, 'strlen') : (string) $v !== '';
    $liste = [];
    foreach (bd_lignes('SELECT * FROM telechargements WHERE publie = 1 ORDER BY ordre, id') as $t) {
        // l'adresse d'un fichier hébergé ailleurs l'emporte sur le fichier de la médiathèque
        $l = ['id' => $t['cle'], 'libelle' => multi('telechargements', $t, 'libelle'), 'fichier' => $t['url'] !== '' ? $t['url'] : media_chemin_site($m[(int) $t['media_id']] ?? null)];
        if ($t['type'] !== '') $l['type'] = $t['type'];
        if ($t['langue'] !== '') $l['langue'] = $t['langue'];
        foreach (['description', 'conditions'] as $champ) {
            $v = multi('telechargements', $t, $champ);
            if ($plein($v)) $l[$champ] = $v;
        }
        if ($t['credit'] !== '') $l['credit'] = $t['credit'];
        $liste[] = $l;
    }
    return $liste;
}

// ---------- Des données aux fichiers ----------

/** Les fichiers de data/ que Pupitre écrit : nom du fichier => [variable, fonction d'export]. */
const FICHIERS_DU_SITE = [
    'data/site.js' => ['SITE', 'export_site'],
    'data/libelles.js' => ['LIBELLES', 'export_libelles'],
    'data/textes.js' => ['TEXTES', 'export_textes'],
    'data/agenda.js' => ['AGENDA_DONNEES', 'export_agenda'],
    'data/playlist.js' => ['PLAYLIST', 'export_playlist'],
    'data/visuels.js' => ['VISUELS', 'export_visuels'],
    'data/disques.js' => ['DISQUES', 'export_disques'],
    'data/presse.js' => ['PRESSE', 'export_presse'],
    'data/orchestres.js' => ['ORCHESTRES', 'export_orchestres'],
    'data/parcours.js' => ['PARCOURS', 'export_parcours'],
    'data/contacts.js' => ['CONTACTS', 'export_contacts'],
    'data/reseaux.js' => ['RESEAUX', 'export_reseaux'],
    'data/telechargements.js' => ['TELECHARGEMENTS', 'export_telechargements'],
];

/** Toutes les données du site, par variable : ['TEXTES' => …, 'AGENDA_DONNEES' => …]. */
function site_exports(): array
{
    $e = [];
    foreach (FICHIERS_DU_SITE as [$variable, $fonction]) $e[$variable] = $fonction();
    // ce qui ne part pas dans un fichier de données : la couleur de fond, pour css/reglages.css
    $e['@reglages'] = ['fond' => couleur_fond()];
    return $e;
}

/** JSON strict, lisible, en retrait de deux espaces. */
function json_site($donnees): string
{
    $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return preg_replace_callback('/^(?: {4})+/m', fn($m) => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json);
}

/** Texte d'un fichier de données : une ligne de commentaire, puis window.NOM = JSON strict. */
function fichier_donnees(string $variable, $donnees): string
{
    return "/* Généré par Pupitre, ne pas modifier à la main. */\nwindow.$variable = " . json_site($donnees) . ";\n";
}

/** Texte de tous les fichiers de data/ : chemin dans le site => contenu. */
function site_fichiers(array $exports): array
{
    $f = [];
    foreach (FICHIERS_DU_SITE as $chemin => [$variable]) $f[$chemin] = fichier_donnees($variable, $exports[$variable]);
    $f['data/agenda.json'] = json_site($exports['AGENDA_DONNEES']) . "\n";
    // la feuille de style générée : la couleur de fond et les blocs masqués, en un seul fichier
    if (isset($exports['@reglages']['fond'])) {
        $site = json_decode(json_encode($exports['SITE'] ?? []), true);
        $f[REGLAGES_CSS] = site_reglages_css((string) $exports['@reglages']['fond'], (array) ($site['blocs'] ?? []));
    }
    return $f;
}

/** Lit un fichier de données du site (window.NOM = JSON ;) : la valeur, ou null s'il est illisible. */
function site_lire_donnees(string $chemin)
{
    if (!is_readable($chemin)) return null;
    $t = file_get_contents($chemin);
    if (str_ends_with($chemin, '.json')) return json_decode($t, true);
    if (!preg_match('/window\.[A-Z_]+\s*=\s*(.*);\s*$/s', $t, $m)) return null;
    return json_decode($m[1], true);
}
