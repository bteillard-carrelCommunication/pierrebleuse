<?php
/**
 * Services d'IA de Pupitre, tous bâtis sur le même appel (inclus/ia.php) et sur les mêmes principes :
 *
 *   - d'abord ce qui se fait sans IA (noms déjà connus, coordonnées déjà en base, métadonnées lues
 *     dans la page) : l'IA n'est appelée que pour ce qui reste ;
 *   - une réponse structurée, contrainte par un schéma JSON, puis recontrôlée par le programme ;
 *   - ce que rend l'IA est une proposition : elle préremplit un formulaire ou attend une validation,
 *     elle n'est jamais écrite d'elle-même dans un contenu publié ;
 *   - ce qui vient d'un document ou d'un site externe est une donnée à analyser, jamais une consigne ;
 *   - sans clé, sans réseau ou en cas d'erreur, l'écran dit pourquoi et tout reste faisable à la main.
 *
 * Services : ia_structure (appel générique), ia_geolocaliser (carte du monde), traduction
 * (propositions de traduction), lecture d'un article de presse (page récupérée par Pupitre lui-même).
 */

defined('PUPITRE') or exit;

/** Schéma JSON : un objet dont tous les champs sont requis. */
function schema_objet(array $proprietes): array
{
    return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($proprietes), 'properties' => $proprietes];
}

/** Schéma JSON : une chaîne, ou null. */
function schema_texte(string $description = ''): array
{
    return ['anyOf' => [['type' => 'string'], ['type' => 'null']]] + ($description !== '' ? ['description' => $description] : []);
}

/**
 * Appel à réponse structurée. Demande un JSON conforme à $schema ; $controler reçoit les données
 * et rend la liste de ce qui ne va pas (vide : c'est bon). Une seule relance, en disant ce qui
 * n'allait pas. Rend [données ou null, message d'erreur lisible ou ''].
 */
function ia_structure(string $objet, string $systeme, array $contenu, array $schema, ?callable $controler = null, string $effort = 'medium', ?int $tache_id = null, ?callable $suivi = null): array
{
    for ($essai = 1; $essai <= 2; $essai++) {
        $r = ia_appeler($objet, ['systeme' => $systeme, 'contenu' => $contenu, 'schema' => $schema, 'effort' => $effort], $tache_id, $suivi);
        if (!$r['ok']) return [null, $r['erreur']];
        $donnees = json_decode($r['texte'], true);
        $erreurs = is_array($donnees) ? ($controler ? $controler($donnees) : []) : ['la réponse n\'est pas un JSON lisible'];
        if (!$erreurs) return [$donnees, ''];
        if ($essai === 2) return [null, 'La réponse de l\'IA n\'a pas passé le contrôle, deux fois de suite (' . $erreurs[0] . '). Rien n\'a été retenu : faites-le à la main, ou réessayez.'];
        $contenu[] = ia_bloc_texte("Ta réponse précédente n'a pas passé le contrôle du programme :\n- " . implode("\n- ", array_slice($erreurs, 0, 10)) . "\nRends de nouveau la réponse complète, dans le format demandé.");
    }
    return [null, 'Réponse inexploitable.'];
}

// ---------- Géolocalisation : une proposition de coordonnées pour une ville ----------

/**
 * Propose les coordonnées d'une ville. À n'appeler qu'après coordonnees_connues() : ce que Pupitre
 * sait déjà ne se redemande pas. Rend ['latitude', 'longitude', 'remarque'], ou null si l'IA ne
 * sait pas situer la ville. Lève ErreurLisible si l'appel échoue.
 */
function ia_geolocaliser(string $orchestre, string $ville, string $pays = ''): ?array
{
    $schema = schema_objet([
        'trouve' => ['type' => 'boolean'],
        'latitude' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
        'longitude' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
        'remarque' => ['type' => 'string'],
    ]);
    $demande = "Orchestre ou ensemble : " . ($orchestre !== '' ? $orchestre : '(non précisé)') . "\nVille : " . $ville . "\nPays : " . ($pays !== '' ? $pays : '(non précisé)');
    [$d, $erreur] = ia_structure('géolocalisation d\'une ville', ia_consignes('geolocalisation'), [ia_bloc_texte($demande)], $schema, function (array $d) {
        if (empty($d['trouve'])) return [];
        if (!is_numeric($d['latitude'] ?? null) || !is_numeric($d['longitude'] ?? null)) return ['coordonnées absentes alors que la ville est dite trouvée'];
        if (abs((float) $d['latitude']) > 90 || abs((float) $d['longitude']) > 180) return ['coordonnées hors des bornes'];
        return [];
    }, 'low');
    if ($d === null) throw new ErreurLisible($erreur);
    if (empty($d['trouve'])) return null;
    return ['latitude' => round((float) $d['latitude'], 2), 'longitude' => round((float) $d['longitude'], 2), 'remarque' => trim((string) $d['remarque'])];
}

// ---------- Traduction : des propositions, jamais plus ----------

/**
 * Champs qui portent la parole de l'artiste : ils ne sont jamais traduits d'office par l'IA.
 * Une personne les traduit, ou demande une proposition champ par champ.
 */
const TRADUCTION_A_LA_MAIN = [
    'textes' => ['citation' => 1],                 // clé du texte : la citation de l'artiste, en ouverture
    'disques' => ['mot' => 1, 'citations' => 1],   // champs : le mot de l'artiste, les citations de l'album
    'articles_citations' => ['traduction' => 1],   // la traduction d'une citation de presse se règle sur la fiche de l'article
];

/** Un champ est-il réservé à une traduction humaine ? */
function traduction_reservee(string $objet, string $id, string $champ): bool
{
    if ($objet === 'textes') return isset(TRADUCTION_A_LA_MAIN['textes'][$id]);
    return isset(TRADUCTION_A_LA_MAIN[$objet][$champ]);
}

/**
 * Propose la traduction d'une série de textes. $elements : [[cle, texte, contexte], …].
 * Rend [cle => traduction] ; lève ErreurLisible si l'appel échoue.
 */
function ia_traduire(array $elements, string $langue, ?int $tache_id = null): array
{
    if (!$elements) return [];
    $nom = langues_toutes()[$langue]['nom_fr'] ?? $langue;
    $schema = schema_objet(['traductions' => ['type' => 'array', 'items' => schema_objet(['cle' => ['type' => 'string'], 'texte' => ['type' => 'string'], 'remarque' => ['type' => 'string']])]]);
    $demande = "Langue cible : " . $nom . " (code " . $langue . ").\n\nÉléments à traduire, en JSON (cle, contexte, texte) :\n"
        . json_encode(array_map(fn($e) => ['cle' => (string) $e[0], 'contexte' => $e[2], 'texte' => $e[1]], $elements), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $cles = array_map(fn($e) => (string) $e[0], $elements);
    [$d, $erreur] = ia_structure('proposition de traduction (' . $langue . ')', ia_consignes('traduction'), [ia_bloc_texte($demande)], $schema, function (array $d) use ($cles) {
        $rendues = array_column($d['traductions'] ?? [], 'cle');
        $manquantes = array_diff($cles, $rendues);
        return $manquantes ? ['il manque la traduction de ' . count($manquantes) . ' élément(s) : ' . implode(', ', array_slice($manquantes, 0, 5))] : [];
    }, 'medium', $tache_id);
    if ($d === null) throw new ErreurLisible($erreur);
    $t = [];
    foreach ($d['traductions'] as $x) if (in_array($x['cle'], $cles, true)) $t[$x['cle']] = trim((string) $x['texte']);
    return $t;
}

/** Textes que l'IA a déjà jugés sans traduction propre dans une langue (noms propres, titres) : ils ne lui sont pas renvoyés. */
function traductions_sans_objet(string $langue, ?array $ecrire = null): array
{
    $f = dossier(STOCKAGE . '/ia') . '/sans-traduction-' . preg_replace('/[^a-z]/', '', $langue) . '.json';
    if ($ecrire !== null) { file_put_contents($f, json_encode(array_keys($ecrire)), LOCK_EX); return $ecrire; }
    $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    return is_array($d) ? array_fill_keys($d, true) : [];
}

/**
 * Propose les traductions manquantes d'une langue (toute la base, ou une table). Dans l'ordre :
 *
 *   1. ce que Pupitre sait déjà : un texte identique a une traduction validée ailleurs (« Allemagne »,
 *      un rôle, un nom de salle) : elle est reprise comme proposition, sans appel à l'IA ;
 *   2. ce que l'IA a déjà jugé sans traduction propre (un nom d'orchestre) n'est pas redemandé ;
 *   3. le reste part à l'IA, chaque texte une seule fois même s'il sert à vingt endroits.
 *
 * Tout est noté « proposée » : rien ne sort sur le site tant qu'une personne n'a pas validé, et une
 * traduction déjà validée n'est jamais remplacée. Rend [proposées, textes de l'artiste laissés, reprises sans IA].
 */
function traductions_proposer(string $langue, string|array|null $objet = null, ?int $tache_id = null, int $max = 400): array
{
    $objets = $objet === null ? null : (array) $objet;          // une table, plusieurs, ou toute la base
    $connues = [];          // texte de référence => traduction validée ailleurs
    $manquantes = [];
    $reservees = 0;
    foreach (traductions_inventaire($langue) as $x) {
        if ($x['statut'] === 'validee' && $x['texte'] !== $x['reference']) $connues[$x['reference']] ??= $x['texte'];
        if ($x['statut'] !== 'manquante' || ($objets !== null && !in_array($x['objet'], $objets, true))) continue;
        if (traduction_reservee($x['objet'], $x['id'], $x['champ'])) { $reservees++; continue; }
        if (preg_match('/^[\d\s.,:;\/()+-]*$/u', $x['reference'])) continue;            // rien à traduire dans un nombre ou une date
        $manquantes[] = $x;
    }
    $proposees = $reprises = 0;
    $sans_objet = traductions_sans_objet($langue);
    $par_texte = [];        // texte de référence => les endroits où il sert
    foreach ($manquantes as $x) {
        if (isset($connues[$x['reference']])) {
            if (traduction_poser($x['objet'], $x['id'], $x['champ'], $langue, $connues[$x['reference']], 'ia')) { $proposees++; $reprises++; }
            continue;
        }
        if (isset($sans_objet[md5($x['reference'])])) continue;
        $par_texte[$x['reference']][] = $x;
    }
    $textes = array_slice(array_keys($par_texte), 0, $max);
    foreach (array_chunk($textes, 30) as $n => $lot) {
        if ($tache_id) tache_avancer($tache_id, min(95, 5 + (int) (90 * $n * 30 / max(1, count($textes)))), 'Traduction : ' . min(count($textes), ($n + 1) * 30) . ' textes sur ' . count($textes));
        $elements = [];
        foreach ($lot as $i => $texte) { $x = $par_texte[$texte][0]; $elements[] = ['e' . $i, $texte, TRADUISIBLES[$x['objet']][0] . ' · ' . $x['libelle']]; }
        $rendues = ia_traduire($elements, $langue, $tache_id);
        foreach ($lot as $i => $texte) {
            $t = $rendues['e' . $i] ?? '';
            if ($t === '') continue;
            if ($t === $texte) { $sans_objet[md5($texte)] = true; continue; }       // identique : le site retombe déjà sur le texte d'origine
            foreach ($par_texte[$texte] as $x) if (traduction_poser($x['objet'], $x['id'], $x['champ'], $langue, $t, 'ia')) $proposees++;
        }
        traductions_sans_objet($langue, $sans_objet);
    }
    return [$proposees, $reservees, $reprises];
}

/** Tâche de fond : propositions de traduction. Charge : langue (ou langues), objet ou objets (facultatifs : toute la base). */
function traduction_tache(array $t): string
{
    $langues = array_values(array_filter((array) ($t['charge']['langues'] ?? [$t['charge']['langue'] ?? '']), fn($l) => is_string($l) && isset(langues_toutes()[$l]) && $l !== langue_reference()));
    if (!$langues) throw new ErreurLisible('Langue inconnue.');
    $objets = array_values(array_filter((array) ($t['charge']['objets'] ?? [$t['charge']['objet'] ?? '']), fn($o) => is_string($o) && isset(TRADUISIBLES[$o])));
    $proposees = $reservees = $reprises = 0;
    foreach ($langues as $langue) {
        [$p, $r, $d] = traductions_proposer($langue, $objets ?: null, (int) $t['id']);
        $proposees += $p; $reservees += $r; $reprises += $d;
    }
    journaliser('creation', 'traduction', null, 'Propositions de traduction · ' . strtoupper(implode(', ', $langues)), null, ['proposees' => $proposees, 'reprises' => $reprises]);
    return ($proposees ? pluriel($proposees, 'traduction proposée', 'traductions proposées') . ', à valider' . ($reprises ? ' (dont ' . $reprises . ' reprises de traductions déjà validées, sans appel à l\'IA)' : '') . '.' : 'Aucune traduction à proposer : ce qui reste s\'écrit de la même façon dans cette langue.')
        . ($reservees ? ' ' . pluriel($reservees, 'texte de l\'artiste ou citation de presse laissé', 'textes de l\'artiste ou citations de presse laissés') . ' à une traduction choisie.' : '');
}

// ---------- Récupérer une page web, sans jamais toucher au réseau interne ----------

/** Une adresse IP est-elle publique ? Les plages privées, locales, de liaison et réservées sont refusées. */
function ip_publique(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
    // plages que le filtre laisse passer : CGNAT, IPv6 de liaison, IPv4 embarquées dans IPv6
    if (preg_match('/^(100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|169\.254\.|0\.|22[4-9]\.|23\d\.)/', $ip)) return false;      // CGNAT, liaison, multidiffusion
    $bas = strtolower($ip);
    if (str_contains($bas, ':')) {
        // IPv6 : liaison locale, adresses locales uniques, multidiffusion, et tout ce qui enveloppe une adresse IPv4 (mappée, NAT64, 6to4)
        foreach (['fe8', 'fe9', 'fea', 'feb', 'fc', 'fd', 'ff', '::ffff:', '64:ff9b:', '2002:'] as $prefixe) if (str_starts_with($bas, $prefixe)) return false;
        if ($bas === '::1' || $bas === '::') return false;
    }
    return true;
}

/**
 * Contrôle une adresse avant de l'ouvrir : http ou https, ports habituels, nom d'hôte qui se résout
 * en adresses publiques seulement. Rend [hôte, port, IP retenue], ou une chaîne : le refus.
 */
function adresse_sure(string $url)
{
    $p = parse_url($url);
    $schema = strtolower((string) ($p['scheme'] ?? ''));
    $hote = strtolower((string) ($p['host'] ?? ''));
    if (!in_array($schema, ['http', 'https'], true) || $hote === '') return 'L\'adresse doit commencer par https:// ou http://.';
    if (isset($p['user']) || isset($p['pass'])) return 'Une adresse avec identifiants n\'est pas acceptée.';
    $port = (int) ($p['port'] ?? ($schema === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443], true)) return 'Seuls les ports habituels du web sont acceptés.';
    $hote = trim($hote, '[]');
    if (filter_var($hote, FILTER_VALIDATE_IP)) {
        return ip_publique($hote) ? [$hote, $port, $hote] : 'Cette adresse désigne une machine du réseau interne : elle est refusée.';
    }
    if (!str_contains($hote, '.') || preg_match('/\.(local|localhost|internal|lan|home|test|invalid)$/', $hote) || $hote === 'localhost') return 'Cette adresse ne désigne pas un site public.';
    $ips = array_merge((array) (@gethostbynamel($hote) ?: []), array_column(@dns_get_record($hote, DNS_AAAA) ?: [], 'ipv6'));
    if (!$ips) return 'Ce site est introuvable (nom de domaine inconnu).';
    foreach ($ips as $ip) if (!ip_publique($ip)) return 'Ce nom de domaine mène à une machine du réseau interne : il est refusé.';
    $ipv4 = array_values(array_filter($ips, fn($ip) => (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
    return [$hote, $port, $ipv4[0] ?? $ips[0]];
}

/**
 * Récupère une page web publique, comme le ferait un lecteur sans compte : pas de témoin, pas
 * d'identifiant, aucun contournement de mur de paiement. Chaque redirection est contrôlée à son
 * tour, et la connexion est faite sur l'adresse IP qui vient d'être contrôlée (une résolution DNS
 * ne peut pas changer entre le contrôle et la lecture). Taille et durée bornées.
 * Rend ['ok' => bool, 'erreur' => …, 'html' => …, 'url' => adresse finale, 'http' => code].
 */
function page_recuperer(string $url, int $taille_max = 3145728): array
{
    $rien = ['ok' => false, 'erreur' => '', 'html' => '', 'url' => $url, 'http' => 0];
    for ($saut = 0; $saut < 6; $saut++) {
        $sure = adresse_sure($url);
        if (is_string($sure)) return ['erreur' => $sure] + $rien;
        [$hote, $port, $ip] = $sure;
        $corps = '';
        $trop = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => [$hote . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Pupitre/1.0 (revue de presse)',          // un nom honnête : Pupitre ne se fait pas passer pour un navigateur
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5', 'Accept-Language: fr,en;q=0.8,de;q=0.6'],
            CURLOPT_WRITEFUNCTION => function ($ch, string $morceau) use (&$corps, &$trop, $taille_max) {
                $corps .= $morceau;
                if (strlen($corps) > $taille_max) { $trop = true; return 0; }
                return strlen($morceau);
            },
        ]);
        curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $vers = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $erreur = curl_errno($ch);
        curl_close($ch);
        if ($trop) return ['erreur' => 'La page est trop lourde pour être lue (plus de ' . taille_lisible($taille_max) . ').', 'http' => $http] + $rien;
        if ($http >= 300 && $http < 400 && $vers !== '') { $url = $vers; continue; }
        if ($erreur !== 0) return ['erreur' => 'Le site n\'a pas répondu.', 'http' => $http] + $rien;
        if (in_array($http, [401, 402, 403, 451], true)) return ['erreur' => 'Le site refuse la lecture de cette page (accès réservé ou protégé). Pupitre ne contourne aucun mur de paiement : collez le texte de l\'article si vous y avez accès.', 'http' => $http, 'url' => $url] + $rien;
        if ($http !== 200) return ['erreur' => 'Le site a répondu par une erreur (' . $http . ').', 'http' => $http, 'url' => $url] + $rien;
        if ($type !== '' && !str_contains($type, 'html') && !str_contains($type, 'xml') && !str_contains($type, 'text/plain')) return ['erreur' => 'Cette adresse ne mène pas à une page web (' . mb_substr($type, 0, 40) . ').', 'http' => $http, 'url' => $url] + $rien;
        return ['ok' => true, 'erreur' => '', 'html' => $corps, 'url' => $url, 'http' => $http];
    }
    return ['erreur' => 'Trop de redirections.'] + $rien;
}

/**
 * Ce qu'une page dit d'elle-même, lu sans IA : titre, média, auteur, date, langue, adresse
 * canonique, image, description (balises Open Graph, données structurées JSON-LD, balises meta),
 * et le texte de l'article (données structurées, sinon balise <article>, sinon les paragraphes).
 */
function page_analyser(string $html, string $url): array
{
    $m = ['titre' => '', 'media' => '', 'auteur' => '', 'date' => '', 'langue' => '', 'canonique' => '', 'image' => '', 'description' => '', 'texte' => ''];
    if (trim($html) === '') return $m;
    $doc = new DOMDocument();
    $precedent = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($precedent);
    $x = new DOMXPath($doc);
    $meta = function (array $noms) use ($x): string {
        foreach ($noms as $n) {
            foreach ($x->query('//meta[@property="' . $n . '" or @name="' . $n . '" or @itemprop="' . $n . '"]') as $noeud) {
                $v = trim((string) $noeud->getAttribute('content'));
                if ($v !== '') return $v;
            }
        }
        return '';
    };
    $propre = fn(string $s): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    // données structurées : le premier objet de type article
    $ld = null;
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        $d = json_decode(trim($s->textContent), true);
        if (!is_array($d)) continue;
        $candidats = isset($d['@graph']) && is_array($d['@graph']) ? $d['@graph'] : (array_is_list($d) ? $d : [$d]);
        foreach ($candidats as $c) {
            $type = is_array($c) ? (array) ($c['@type'] ?? []) : [];
            if (array_intersect($type, ['NewsArticle', 'Article', 'Review', 'ReportageNewsArticle', 'BlogPosting', 'OpinionNewsArticle', 'CriticReview'])) { $ld = $c; break 2; }
        }
    }
    $nom_de = function ($v) use (&$nom_de): string {
        if (is_string($v)) return $v;
        if (is_array($v) && array_is_list($v)) return implode(', ', array_filter(array_map($nom_de, $v), 'strlen'));
        return is_array($v) ? (string) ($v['name'] ?? '') : '';
    };
    $m['titre'] = $propre($meta(['og:title', 'twitter:title']) ?: (string) ($ld['headline'] ?? '') ?: (string) ($x->query('//title')->item(0)->textContent ?? ''));
    $m['media'] = $propre($meta(['og:site_name', 'application-name']) ?: $nom_de($ld['publisher'] ?? ''));
    $m['auteur'] = $propre($nom_de($ld['author'] ?? '') ?: $meta(['author', 'article:author', 'parsely-author', 'dc.creator']));
    if (preg_match('#^https?://#', $m['auteur'])) $m['auteur'] = '';                          // une adresse de profil n'est pas un nom
    $date = (string) ($ld['datePublished'] ?? '') ?: $meta(['article:published_time', 'datePublished', 'date', 'dc.date', 'parsely-pub-date']);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $d)) $m['date'] = $d[1];
    $langue = (string) ($x->query('//html/@lang')->item(0)->nodeValue ?? '') ?: $meta(['og:locale', 'language']);
    if (preg_match('/^([a-zA-Z]{2,3})/', $langue, $d)) $m['langue'] = strtolower($d[1]);
    $canon = $x->query('//link[@rel="canonical"]/@href')->item(0);
    $m['canonique'] = trim((string) ($canon->nodeValue ?? '')) ?: $meta(['og:url']);
    if ($m['canonique'] !== '' && !preg_match('#^https?://#i', $m['canonique'])) $m['canonique'] = '';
    $m['image'] = $meta(['og:image', 'twitter:image']);
    if ($m['image'] !== '' && !preg_match('#^https?://#i', $m['image'])) $m['image'] = '';
    $m['description'] = $propre($meta(['og:description', 'description', 'twitter:description']));
    // le texte : celui des données structurées, sinon les paragraphes de l'article
    $texte = $propre((string) ($ld['articleBody'] ?? $ld['reviewBody'] ?? ''));
    if (mb_strlen($texte) < 400) {
        foreach ($x->query('//script|//style|//noscript|//nav|//aside|//footer|//form|//header//nav|//figure//figcaption|//*[@aria-hidden="true"]') as $n) $n->parentNode?->removeChild($n);
        $racines = $x->query('//article');
        $racine = $racines->length ? $racines->item(0) : ($x->query('//main')->item(0) ?? $x->query('//body')->item(0));
        $morceaux = [];
        if ($racine) {
            foreach ($x->query('.//p|.//h2|.//h3|.//blockquote|.//li', $racine) as $p) {
                $t = $propre($p->textContent);
                if (mb_strlen($t) >= 30 || $p->nodeName === 'h2' || $p->nodeName === 'h3') $morceaux[] = $t;
            }
        }
        $autre = implode("\n\n", array_values(array_unique($morceaux)));
        if (mb_strlen($autre) > mb_strlen($texte)) $texte = $autre;
    }
    $m['texte'] = mb_substr($texte, 0, 60000);
    return $m;
}

// ---------- Lecture d'un article de presse ----------

function schema_article(): array
{
    $par_langue = [];
    foreach (array_keys(langues_toutes()) as $code) $par_langue[$code] = schema_texte();
    return schema_objet([
        'titre' => schema_texte(), 'media' => schema_texte(), 'auteur' => schema_texte(),
        'date' => schema_texte('AAAA-MM-JJ, ou AAAA-MM'), 'langue' => schema_texte('code à deux lettres'),
        'type' => ['type' => 'string', 'enum' => array_keys(TYPES_ARTICLE)],
        'citations' => ['type' => 'array', 'items' => schema_objet(['texte' => ['type' => 'string'], 'traductions' => schema_objet($par_langue)])],
        'citation_principale' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
        'chapeau' => schema_objet($par_langue), 'resume' => schema_objet($par_langue),
        'concerts' => ['type' => 'array', 'items' => ['type' => 'integer']],
        'albums' => ['type' => 'array', 'items' => ['type' => 'integer']],
        'acces' => ['type' => 'string', 'enum' => ['complet', 'partiel', 'aucun']],
        'incertitudes' => ['type' => 'array', 'items' => ['type' => 'string']],
    ]);
}

/**
 * Lit un article et prépare de quoi préremplir sa fiche. $url : son adresse (facultative si le
 * texte est collé) ; $texte_colle : le texte de l'article collé par l'utilisateur. Rend
 * ['ok' => bool, 'erreur' => …, 'propose' => champs du formulaire, 'notes' => remarques à montrer].
 * Rien n'est écrit dans la revue de presse.
 */
function article_lire_source(string $url, string $texte_colle = '', ?int $tache_id = null): array
{
    $avancer = fn(int $p, string $m) => $tache_id ? tache_avancer($tache_id, $p, $m) : null;
    $notes = [];
    $meta = ['titre' => '', 'media' => '', 'auteur' => '', 'date' => '', 'langue' => '', 'canonique' => '', 'image' => '', 'description' => '', 'texte' => ''];
    $url = trim($url);
    $texte_colle = trim(str_replace("\r\n", "\n", $texte_colle));
    if ($url !== '') {
        $avancer(10, 'Lecture de la page');
        $page = page_recuperer($url);
        if ($page['ok']) {
            $meta = page_analyser($page['html'], $page['url']);
            $url = $page['url'];
        } elseif ($texte_colle === '') {
            return ['ok' => false, 'erreur' => $page['erreur'] . ' Vous pouvez coller le texte de l\'article, ou saisir sa fiche à la main.', 'propose' => ['lien' => $url, 'origine' => 'url'], 'notes' => []];
        } else {
            $notes[] = 'La page n\'a pas pu être lue (' . $page['erreur'] . ') : l\'analyse porte sur le texte collé.';
        }
    }
    $texte = $texte_colle !== '' ? mb_substr($texte_colle, 0, 60000) : $meta['texte'];
    if (mb_strlen($texte) < 200 && $meta['description'] === '') {
        return ['ok' => false, 'erreur' => 'Le texte de l\'article n\'a pas pu être récupéré (page vide, protégée ou écrite par un script). Collez son texte, ou saisissez sa fiche à la main.',
            'propose' => array_filter(['lien' => $url, 'url_canonique' => $meta['canonique'], 'titre_fr' => $meta['titre'], 'media_neuf' => $meta['media'], 'auteur' => $meta['auteur'], 'date_article' => $meta['date'], 'langue' => $meta['langue'], 'origine' => 'url'], 'strlen'), 'notes' => $notes];
    }
    // ce que le programme sait déjà faire seul est fait ; l'IA structure le reste
    $propose = ['lien' => $url, 'url_canonique' => $meta['canonique'] !== $url ? $meta['canonique'] : '', 'titre_fr' => $meta['titre'], 'auteur' => $meta['auteur'], 'date_article' => $meta['date'],
        'langue' => $meta['langue'], 'origine' => $texte_colle !== '' ? 'texte' : 'url', 'texte_source' => $texte, 'citations' => [], 'concerts' => [], 'disques' => [], 'traductions' => []];
    $media_id = $meta['media'] !== '' ? fiche_trouver($meta['media'], ['media']) : null;
    if (!$media_id && $url !== '') {
        // le média par son site : le domaine de l'adresse est celui d'une fiche du référentiel
        $hote = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        foreach (bd_lignes("SELECT id, site FROM annuaire WHERE categorie = 'media' AND site <> ''") as $f) {
            if ($hote !== '' && preg_replace('/^www\./', '', strtolower((string) parse_url($f['site'], PHP_URL_HOST))) === $hote) { $media_id = (int) $f['id']; break; }
        }
    }
    if ($media_id) $propose['media_id'] = $media_id; else { $propose['media_neuf'] = $meta['media']; $propose['media_site'] = $url !== '' ? (parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST)) : ''; }
    if ($meta['image'] !== '') $propose['image'] = $meta['image'];
    if (!ia_configuree()) {
        $notes[] = 'L\'IA n\'est pas configurée : seuls les champs lus dans la page sont préremplis.';
        return ['ok' => true, 'erreur' => '', 'propose' => $propose, 'notes' => $notes];
    }
    $avancer(35, 'Analyse par l\'IA');
    $concerts = [];
    foreach (bd_lignes("SELECT c.id, c.titre_fr, c.ensemble_fr, c.ville_fr, c.salle_fr, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id) AS du, (SELECT MAX(r.date) FROM representations r WHERE r.concert_id = c.id) AS au FROM concerts c WHERE c.type <> 'disque' ORDER BY du") as $c) {
        $concerts[] = ['id' => (int) $c['id'], 'titre' => $c['titre_fr'], 'ensemble' => $c['ensemble_fr'], 'ville' => $c['ville_fr'], 'salle' => $c['salle_fr'], 'du' => $c['du'], 'au' => $c['au']];
    }
    $albums = array_map(fn($d) => ['id' => (int) $d['id'], 'titre' => preg_replace('/\*/', '', $d['titre']), 'annee' => $d['annee']], bd_lignes('SELECT id, titre, annee FROM disques ORDER BY ordre'));
    $langues_site = [];
    foreach (langues_toutes() as $code => $l) $langues_site[] = $code . ' (' . $l['nom_fr'] . ')';
    $demande = "Chef d'orchestre concerné : " . (reglage('artiste_nom') ?: 'l\'artiste du site') . "\n"
        . "Langues du site : " . implode(', ', $langues_site) . "\n\n"
        . "Métadonnées relevées par le programme dans la page (vides si absentes) :\n" . json_encode(array_diff_key($meta, ['texte' => 1, 'image' => 1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n\n"
        . "Concerts connus du site :\n" . json_encode($concerts, JSON_UNESCAPED_UNICODE) . "\n\nAlbums connus du site :\n" . json_encode($albums, JSON_UNESCAPED_UNICODE) . "\n\n"
        . "<article>\n" . str_replace(['<article>', '</article>'], '', $texte) . "\n</article>";
    $dernier = 0;
    [$d, $erreur] = ia_structure('lecture d\'un article de presse', ia_consignes('lecture-article'), [ia_bloc_texte($demande)], schema_article(), function (array $d) {
        $e = [];
        if (($d['date'] ?? null) !== null && !preg_match('/^\d{4}-\d{2}(-\d{2})?$/', (string) $d['date'])) $e[] = 'date : AAAA-MM-JJ ou AAAA-MM attendu';
        if (($d['langue'] ?? null) !== null && !preg_match('/^[a-z]{2,3}$/', (string) $d['langue'])) $e[] = 'langue : un code de deux ou trois lettres minuscules attendu';
        return $e;
    }, 'medium', $tache_id, function (int $recus) use ($avancer, &$dernier) {
        if ($recus - $dernier < 600) return;
        $dernier = $recus;
        $avancer(min(90, 40 + (int) ($recus / 80)), 'Analyse par l\'IA : la réponse arrive');
    });
    if ($d === null) {
        $notes[] = 'L\'analyse par l\'IA n\'a pas abouti (' . $erreur . ') : seuls les champs lus dans la page sont préremplis.';
        return ['ok' => true, 'erreur' => '', 'propose' => $propose, 'notes' => $notes];
    }
    // les métadonnées de la page l'emportent ; l'IA comble ce qui manque
    foreach (['titre_fr' => 'titre', 'auteur' => 'auteur', 'date_article' => 'date', 'langue' => 'langue'] as $champ => $k) {
        if (($propose[$champ] ?? '') === '' && trim((string) ($d[$k] ?? '')) !== '') $propose[$champ] = trim((string) $d[$k]);
    }
    if (empty($propose['media_id']) && ($propose['media_neuf'] ?? '') === '' && trim((string) ($d['media'] ?? '')) !== '') {
        $media_id = fiche_trouver(trim($d['media']), ['media']);
        if ($media_id) $propose['media_id'] = $media_id; else $propose['media_neuf'] = trim($d['media']);
    }
    $propose['type'] = isset(TYPES_ARTICLE[$d['type'] ?? '']) ? $d['type'] : '';
    $reference = langue_reference();
    foreach (['chapeau', 'resume'] as $champ) {
        $propose[$champ . '_' . $reference] = trim((string) ($d[$champ][$reference] ?? ''));
        foreach (langues_autres() as $code) $propose['traductions'][$champ][$code] = trim((string) ($d[$champ][$code] ?? ''));
    }
    // citations : seules celles qui se retrouvent mot pour mot dans le texte sont gardées
    $ecartees = 0;
    $langue_article = (string) ($propose['langue'] ?? '');
    $principale = is_int($d['citation_principale'] ?? null) ? $d['citation_principale'] : 0;
    foreach (array_values($d['citations'] ?? []) as $i => $c) {
        $texte_c = trim((string) ($c['texte'] ?? ''));
        if ($texte_c === '') continue;
        if (!citation_dans_texte($texte_c, $texte)) { $ecartees++; continue; }
        $ligne = ['texte' => citation_guillemets($texte_c, $langue_article), 'langue' => $langue_article, 'principale' => $i === $principale, 'verifiee' => 0];
        // sa traduction dans les autres langues du site : jamais dans la langue de la citation elle-même
        foreach (array_keys(langues_toutes()) as $code) {
            $ligne['traduction_' . $code] = $code === $langue_article ? '' : trim((string) ($c['traductions'][$code] ?? ''));
        }
        $propose['citations'][] = $ligne;
    }
    if ($propose['citations'] && !array_filter($propose['citations'], fn($c) => $c['principale'])) $propose['citations'][0]['principale'] = true;
    if ($ecartees) $notes[] = pluriel($ecartees, 'citation proposée par l\'IA a été écartée', 'citations proposées par l\'IA ont été écartées') . ' : elle ne se retrouvait pas mot pour mot dans le texte de l\'article.';
    // relations : seulement vers des concerts et des albums qui existent
    $ids_concerts = array_column($concerts, 'id');
    $ids_albums = array_column($albums, 'id');
    $propose['concerts'] = array_values(array_intersect(array_map('intval', (array) ($d['concerts'] ?? [])), $ids_concerts));
    $propose['disques'] = array_values(array_intersect(array_map('intval', (array) ($d['albums'] ?? [])), $ids_albums));
    if (($d['acces'] ?? '') === 'partiel') $notes[] = 'Le texte récupéré paraît incomplet (extrait, ou article réservé aux abonnés) : le résumé et les citations ne portent que sur ce qui a été lu.';
    if (($d['acces'] ?? '') === 'aucun') $notes[] = 'Le texte récupéré ne contient pas l\'article : collez son texte pour obtenir un résumé et des citations.';
    foreach ((array) ($d['incertitudes'] ?? []) as $x) if (trim((string) $x) !== '') $notes[] = 'À vérifier : ' . trim((string) $x);
    $avancer(95, 'Contrôle des citations');
    return ['ok' => true, 'erreur' => '', 'propose' => $propose, 'notes' => $notes];
}

/** Pose autour d'une citation les guillemets de sa langue, si elle n'en porte pas déjà. */
function citation_guillemets(string $texte, string $langue): string
{
    $texte = trim($texte);
    if (preg_match('/^[«“"„‘]/u', $texte)) return $texte;
    return match ($langue) {
        'fr' => '« ' . $texte . ' »',
        'de' => '„' . $texte . '“',
        default => '“' . $texte . '”',
    };
}

/** Tâche de fond : lecture d'un article. Charge : url, texte. Le résultat est rangé dans stockage/imports/article-<tâche>.json. */
function article_tache(array $t): string
{
    $r = article_lire_source((string) ($t['charge']['url'] ?? ''), (string) ($t['charge']['texte'] ?? ''), (int) $t['id']);
    $f = dossier(STOCKAGE . '/imports') . '/article-' . (int) $t['id'] . '.json';
    file_put_contents($f, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($f, 0600);
    if (!$r['ok']) throw new ErreurLisible($r['erreur']);
    return 'Article lu : formulaire prérempli, à relire.';
}

/** Résultat d'une lecture d'article, relu depuis stockage/imports/ ; null s'il manque. */
function article_lecture_resultat(int $tache_id): ?array
{
    $f = STOCKAGE . '/imports/article-' . $tache_id . '.json';
    $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    return is_array($d) ? $d : null;
}
