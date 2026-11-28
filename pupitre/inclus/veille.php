<?php
/**
 * Veille : repérer ce qui se publie sur l'artiste, le garder, en garder la trace, le partager.
 *
 * Trois sources alimentent la veille, toutes rangées dans la même table (`veille`) :
 *   actualites   un flux d'actualités (Google Actualités, en RSS) par recherche et par édition : la presse
 *                en ligne, sans clé ni abonnement ; le lien passe par l'agrégateur ;
 *   recherche    une recherche menée par l'IA sur le web (inclus/ia_recherche.php) : ce que les flux
 *                d'actualités ne voient pas (sites de salles et d'orchestres, blogs, annonces de saison),
 *                avec un extrait et une première lecture : l'artiste y est-il identifié, ou seulement cité ;
 *   manuel       une adresse ajoutée à la main : Pupitre lit la page pour en reprendre le titre et le média.
 *
 * Un signalement ne se note qu'une fois (empreinte de son adresse). Il arrive « à lire » ; une personne
 * le retient (il peut devenir un article de la revue de presse), le partage (une action de communication
 * est créée, qui le cite), ou l'écarte. Chaque décision est journalisée.
 *
 * Les recherches (noms à chercher) et les éditions (langue et pays) se règlent sur l'écran de la veille.
 * La collecte se lance à la main, ou chaque nuit par le cron.
 */

defined('PUPITRE') or exit;

const SOURCES_VEILLE = ['actualites' => 'Flux d\'actualités', 'recherche' => 'Recherche de l\'IA', 'reseaux' => 'Réseaux sociaux', 'manuel' => 'Ajout à la main'];
const NATURES_VEILLE = ['presse' => 'Presse', 'web' => 'Web', 'social' => 'Réseau social'];
const STATUTS_VEILLE = ['a_lire' => ['À lire', 'puce--accent'], 'retenue' => ['Retenue', 'puce--ok'], 'partagee' => ['Partagée', 'puce--ok'], 'ecartee' => ['Écartée', '']];
const MENTIONS_VEILLE = ['identifie' => ['Identifié', 'puce--ok'], 'cite' => ['Cité, sans être identifié', 'puce--alerte'], 'a_verifier' => ['À vérifier', '']];
const VEILLE_FLUX = 'https://news.google.com/rss/search';
/** Éditions proposées : code => [libellé, hl, gl, ceid]. */
const EDITIONS_VEILLE = [
    'fr-FR' => ['France', 'fr', 'FR', 'FR:fr'], 'en-GB' => ['Royaume-Uni', 'en-GB', 'GB', 'GB:en'], 'en-US' => ['États-Unis', 'en-US', 'US', 'US:en'],
    'de-DE' => ['Allemagne', 'de', 'DE', 'DE:de'], 'es-ES' => ['Espagne', 'es', 'ES', 'ES:es'], 'it-IT' => ['Italie', 'it', 'IT', 'IT:it'],
    'nl-NL' => ['Pays-Bas', 'nl', 'NL', 'NL:nl'], 'sv-SE' => ['Suède', 'sv', 'SE', 'SE:sv'], 'da-DK' => ['Danemark', 'da', 'DK', 'DK:da'],
    'fr-BE' => ['Belgique', 'fr', 'BE', 'BE:fr'], 'fr-CH' => ['Suisse', 'fr', 'CH', 'CH:fr'], 'de-AT' => ['Autriche', 'de', 'AT', 'AT:de'],
];

/** Ce que la veille cherche : une recherche par ligne. Par défaut, le nom de l'artiste entre guillemets. */
function veille_requetes(): array
{
    $l = lignes(reglage('veille_requetes'));
    if ($l) return array_slice($l, 0, 8);
    $nom = trim(reglage('artiste_nom'));
    return $nom !== '' ? ['"' . $nom . '"'] : [];
}

/** Les éditions (langue et pays) où chercher les actualités. */
function veille_editions(): array
{
    $l = array_values(array_filter(array_map('trim', explode(',', reglage('veille_editions'))), fn($c) => isset(EDITIONS_VEILLE[$c])));
    return $l ?: ['fr-FR', 'en-GB', 'de-DE', 'es-ES'];
}

/** Empreinte d'un signalement : celle de son adresse ramenée à sa forme canonique, ou d'un identifiant donné par la source. */
function veille_empreinte(string $adresse, string $identifiant = ''): string
{
    return sha1($identifiant !== '' ? 'id:' . $identifiant : 'u:' . adresse_canon($adresse));
}

/**
 * Note un signalement, s'il est nouveau. $s : source, nature, titre, lien, site, auteur, extrait, langue, mention,
 * paru_le, requete, reseau, identifiant (facultatif : ce par quoi la source le désigne). Rend son identifiant, ou null s'il est déjà connu.
 */
function veille_noter(array $s): ?int
{
    $lien = trim((string) ($s['lien'] ?? ''));
    if (!preg_match('#^https?://[^\s<>"]+$#i', $lien) || trim((string) ($s['titre'] ?? '')) === '') return null;
    $empreinte = veille_empreinte($lien, (string) ($s['identifiant'] ?? ''));
    // la même page peut arriver par deux sources : par son adresse, ou par son titre chez le même média
    if (bd_valeur('SELECT id FROM veille WHERE empreinte = ? OR empreinte = ?', [$empreinte, veille_empreinte($lien)])) return null;
    $titre = mb_substr(trim(html_entity_decode(strip_tags((string) $s['titre']), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 500);
    $site = mb_substr(trim((string) ($s['site'] ?? '')), 0, 190);
    if ($site !== '' && bd_valeur('SELECT id FROM veille WHERE titre = ? AND site = ?', [$titre, $site])) return null;
    $date = (string) ($s['paru_le'] ?? '');
    $media = $site !== '' ? fiche_trouver($site, ['media']) : null;
    return bd_inserer('veille', [
        'source' => isset(SOURCES_VEILLE[$s['source'] ?? '']) ? $s['source'] : 'manuel',
        'nature' => isset(NATURES_VEILLE[$s['nature'] ?? '']) ? $s['nature'] : 'web',
        'reseau' => mb_substr((string) ($s['reseau'] ?? ''), 0, 30),
        'media_id' => $media,
        'titre' => $titre, 'site' => $site, 'auteur' => mb_substr(trim((string) ($s['auteur'] ?? '')), 0, 190),
        'extrait' => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($s['extrait'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 1500),
        'langue' => preg_match('/^[a-z]{2}$/', (string) ($s['langue'] ?? '')) ? $s['langue'] : '',
        'mention' => isset(MENTIONS_VEILLE[$s['mention'] ?? '']) ? $s['mention'] : 'a_verifier',
        'requete' => mb_substr((string) ($s['requete'] ?? ''), 0, 190),
        'empreinte' => $empreinte,
        'lien' => mb_substr($lien, 0, 1000),
        'paru_le' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
        'statut' => 'a_lire',
    ]);
}

/** Télécharge un flux depuis l'adresse de l'agrégateur (une adresse fixe, jamais saisie par un utilisateur). Rend le texte, ou ''. */
function veille_telecharger(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; veille; +rss)', CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml;q=0.9, */*;q=0.5'],
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    $r = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return (is_string($r) && $code === 200 && strlen($r) < 5000000) ? $r : '';
}

/** Lit un flux RSS : [[titre, lien, date AAAA-MM-JJ, site, extrait, identifiant], …]. Un flux illisible rend une liste vide. */
function veille_flux_lire(string $xml): array
{
    if (trim($xml) === '') return [];
    $precedent = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($precedent);
    if (!$doc || !isset($doc->channel->item)) return [];
    $l = [];
    foreach ($doc->channel->item as $item) {
        $titre = trim((string) $item->title);
        $site = trim((string) $item->source);
        // l'agrégateur écrit « Titre - Média » : le média est déjà donné à part
        if ($site !== '' && str_ends_with($titre, ' - ' . $site)) $titre = trim(mb_substr($titre, 0, mb_strlen($titre) - mb_strlen(' - ' . $site)));
        $t = strtotime((string) $item->pubDate);
        $l[] = ['titre' => $titre, 'lien' => trim((string) $item->link), 'paru_le' => $t ? date('Y-m-d', $t) : '', 'site' => $site,
            'extrait' => trim(strip_tags(html_entity_decode((string) $item->description, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 'identifiant' => trim((string) $item->guid)];
    }
    return $l;
}

/** Collecte par les flux d'actualités : chaque recherche, dans chaque édition, sur les $jours derniers jours. Rend [nouveaux, lus, flux illisibles]. */
function veille_actualites(int $jours = 30, ?int $tache_id = null): array
{
    $nouveaux = $lus = $illisibles = 0;
    $requetes = veille_requetes();
    $editions = veille_editions();
    $n = 0;
    foreach ($requetes as $requete) {
        foreach ($editions as $code) {
            [, $hl, $gl, $ceid] = EDITIONS_VEILLE[$code];
            if ($tache_id) tache_avancer($tache_id, (int) (5 + 55 * $n++ / max(1, count($requetes) * count($editions))), 'Actualités : ' . $requete . ' · ' . EDITIONS_VEILLE[$code][0]);
            $xml = veille_telecharger(env('VEILLE_FLUX_URL', VEILLE_FLUX) . '?' . http_build_query(['q' => $requete . ' when:' . max(1, $jours) . 'd', 'hl' => $hl, 'gl' => $gl, 'ceid' => $ceid]));
            if ($xml === '') { $illisibles++; continue; }
            foreach (veille_flux_lire($xml) as $x) {
                $lus++;
                // l'extrait de l'agrégateur ne fait que répéter le titre et le média : il n'apporte rien
                $extrait = (mb_stripos($x['extrait'], mb_substr($x['titre'], 0, 30)) !== false) ? '' : $x['extrait'];
                if (veille_noter(['source' => 'actualites', 'nature' => 'presse', 'langue' => substr($hl, 0, 2), 'requete' => $requete, 'extrait' => $extrait] + $x)) $nouveaux++;
            }
        }
    }
    return [$nouveaux, $lus, $illisibles];
}

/** Collecte par la recherche de l'IA sur le web. Rend [nouveaux, signalés, erreur]. */
function veille_recherche_ia(int $jours = 30, ?int $tache_id = null): array
{
    if (!ia_configuree()) return [0, 0, ''];
    $nom = reglage('artiste_nom');
    $connus = bd_colonne('SELECT lien FROM veille ORDER BY id DESC LIMIT 60');
    $actualite = bd_lignes("SELECT c.titre_en, c.ensemble_en, c.ville_en, MIN(r.date) AS du FROM concerts c JOIN representations r ON r.concert_id = c.id
        WHERE r.date BETWEEN ? AND ? AND r.statut IN ('annoncee', 'publiee') GROUP BY c.id, c.titre_en, c.ensemble_en, c.ville_en ORDER BY du LIMIT 12", [ajouter_jours(aujourdhui(), -$jours), ajouter_jours(aujourdhui(), 45)]);
    $sites = array_values(array_filter(array_merge([parse_url(site_adresse(), PHP_URL_HOST)], array_map(fn($u) => parse_url($u, PHP_URL_HOST), bd_colonne("SELECT url FROM reseaux WHERE url <> ''")))));
    $demande = "Chef d'orchestre : " . $nom . "\nPériode : du " . ajouter_jours(aujourdhui(), -$jours) . " au " . aujourdhui() . " (aujourd'hui).\n"
        . "Langues où chercher : " . implode(', ', array_unique(array_map(fn($c) => substr($c, 0, 2), veille_editions()))) . ".\n"
        . "Recherches de l'équipe : " . implode(' ; ', veille_requetes()) . "\n\n"
        . "Son actualité (concerts annoncés, pour orienter les recherches) :\n" . json_encode($actualite, JSON_UNESCAPED_UNICODE) . "\n\n"
        . "Ses propres sites et comptes (à écarter) : " . implode(', ', array_unique($sites)) . "\n"
        . "Adresses déjà signalées (à ne pas rendre de nouveau) :\n" . implode("\n", $connus);
    if ($tache_id) tache_avancer($tache_id, 65, 'Recherche sur le web par l\'IA');
    $r = ia_chercher('veille (recherche sur le web)', ia_consignes('veille'), $demande, ['recherches' => 8, 'effort' => 'medium'], $tache_id);
    if (!$r['ok']) return [0, 0, $r['erreur']];
    $d = json_extraire($r['texte']);
    if ($d === null || !is_array($d['signalements'] ?? null)) return [0, 0, 'La réponse de l\'IA n\'a pas pu être lue : rien n\'a été retenu de sa recherche.'];
    // seules les adresses que la recherche a réellement vues sont gardées : une adresse que l'IA aurait écrite de tête ne passe pas
    $vues = [];
    foreach ($r['sources'] as [$adresse]) $vues[adresse_canon($adresse)] = true;
    $nouveaux = 0;
    foreach ($d['signalements'] as $s) {
        if (!is_array($s) || !isset($vues[adresse_canon((string) ($s['adresse'] ?? ''))])) continue;
        if (veille_noter(['source' => 'recherche', 'lien' => (string) $s['adresse'], 'titre' => (string) ($s['titre'] ?? ''), 'site' => (string) ($s['site'] ?? ''), 'paru_le' => (string) ($s['date'] ?? ''),
            'langue' => (string) ($s['langue'] ?? ''), 'nature' => (string) ($s['nature'] ?? 'web'), 'mention' => (string) ($s['mention'] ?? 'a_verifier'), 'extrait' => (string) ($s['extrait'] ?? '')])) $nouveaux++;
    }
    return [$nouveaux, count($d['signalements']), ''];
}

/** Ajoute une adresse à la main : la page est lue (sans IA) pour en reprendre le titre, le média, la date. Rend [identifiant ou null, erreur]. */
function veille_ajouter(string $url, string $titre = ''): array
{
    $url = trim($url);
    if (!preg_match('#^https?://[^\s<>"]+$#i', $url)) return [null, 'L\'adresse doit être complète : https://…'];
    if (bd_valeur('SELECT id FROM veille WHERE empreinte = ?', [veille_empreinte($url)])) return [null, 'Cette adresse est déjà dans la veille.'];
    $meta = ['titre' => '', 'media' => '', 'auteur' => '', 'date' => '', 'langue' => '', 'description' => ''];
    $page = page_recuperer($url);
    if ($page['ok']) $meta = array_merge($meta, page_analyser($page['html'], $page['url']));
    $hote = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
    $social = preg_match('/(^|\.)(instagram|facebook|x|twitter|linkedin|tiktok|threads|youtube|bsky)\.(com|net|app)$/', $hote, $m) ? ($m[2] === 'twitter' ? 'x' : ($m[2] === 'bsky' ? 'bluesky' : $m[2])) : '';
    $id = veille_noter(['source' => 'manuel', 'nature' => $social !== '' ? 'social' : 'web', 'reseau' => $social, 'lien' => $url, 'titre' => trim($titre) ?: ($meta['titre'] ?: $hote), 'site' => $meta['media'] ?: $hote,
        'auteur' => $meta['auteur'], 'paru_le' => substr((string) $meta['date'], 0, 10), 'langue' => $meta['langue'], 'extrait' => $meta['description']]);
    return $id ? [$id, ''] : [null, 'Ce signalement est déjà dans la veille (même titre, même média).'];
}

/** Tâche de fond : collecte. Charge : jours, ia (faux pour s'en tenir aux flux). */
function veille_tache(array $t): string
{
    $jours = max(1, min(120, (int) ($t['charge']['jours'] ?? 30)));
    [$nouveaux, $lus, $illisibles] = veille_actualites($jours, (int) $t['id']);
    $ia = [0, 0, ''];
    if (($t['charge']['ia'] ?? true) && ia_configuree()) $ia = veille_recherche_ia($jours, (int) $t['id']);
    reglage_ecrire('veille_le', date('Y-m-d H:i:s'));
    $total = $nouveaux + $ia[0];
    if ($total) journaliser('creation', 'veille', null, 'Veille : ' . pluriel($total, 'nouveau signalement', 'nouveaux signalements'), null, ['actualites' => $nouveaux, 'recherche_ia' => $ia[0]]);
    $GLOBALS['donnees_modifiees'] = true;
    return ($total ? pluriel($total, 'nouveau signalement', 'nouveaux signalements') : 'Rien de nouveau')
        . ' (' . $lus . ' lus dans les flux d\'actualités' . ($ia[1] ? ', ' . $ia[1] . ' proposés par l\'IA' : '') . ').'
        . ($illisibles ? ' ' . pluriel($illisibles, 'flux n\'a pas répondu', 'flux n\'ont pas répondu') . '.' : '')
        . ($ia[2] !== '' ? ' Recherche de l\'IA : ' . $ia[2] : '');
}

/** Les signalements. Filtres : statut, source, nature, mention, q. */
function veille_lister(array $f = [], int $limite = 200): array
{
    $ou = ['1 = 1'];
    $p = [];
    foreach (['statut', 'source', 'nature', 'mention'] as $k) if (!empty($f[$k])) { $ou[] = "v.$k = ?"; $p[] = $f[$k]; }
    if (!empty($f['q'])) { $ou[] = '(v.titre LIKE ? OR v.site LIKE ? OR v.extrait LIKE ?)'; $q = '%' . $f['q'] . '%'; array_push($p, $q, $q, $q); }
    return bd_lignes('SELECT v.* FROM veille v WHERE ' . implode(' AND ', $ou) . ' ORDER BY COALESCE(v.paru_le, DATE(v.cree_le)) DESC, v.id DESC LIMIT ' . (int) $limite, $p);
}

function veille_decompte(): array
{
    $d = array_fill_keys(array_keys(STATUTS_VEILLE), 0);
    try {
        foreach (bd_lignes('SELECT statut, COUNT(*) AS n FROM veille GROUP BY statut') as $l) if (isset($d[$l['statut']])) $d[$l['statut']] = (int) $l['n'];
    } catch (Throwable $e) {
        // base d'avant la migration 012
    }
    return $d;
}

/** Change le statut d'un signalement, et en garde la trace. */
function veille_decider(int $id, string $statut, array $plus = []): void
{
    $v = bd_ligne('SELECT * FROM veille WHERE id = ?', [$id]);
    if (!$v || !isset(STATUTS_VEILLE[$statut])) return;
    $c = compte();
    bd_modifier('veille', ['statut' => $statut, 'traite_par' => $c['id'] ?? null, 'traite_le' => date('Y-m-d H:i:s')] + $plus, ['id' => $id]);
    journaliser('statut', 'veille', $id, couper($v['titre'], 120) . ($v['site'] !== '' ? ' · ' . $v['site'] : ''), ['statut' => $v['statut']], ['statut' => $statut] + $plus);
    $GLOBALS['donnees_modifiees'] = true;
}
