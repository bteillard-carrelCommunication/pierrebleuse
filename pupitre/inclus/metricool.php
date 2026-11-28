<?php
/**
 * Metricool : la plateforme qui publie sur les réseaux de l'artiste et en mesure les résultats.
 *
 * Pupitre y lit ce qui a été publié (par lui ou autrement) et ce que chaque publication a donné :
 * portée, impressions, réactions, commentaires, partages, enregistrements, clics, vues. Tout est
 * rangé dans `posts_sociaux`, une ligne par publication et par marque ; rien n'est estimé.
 *
 * Deux marques se règlent (écran Réglages) : celle des vrais comptes de l'artiste, et une marque de
 * comptes de test. Un seul mode est actif à la fois (« reel » ou « test ») : toute lecture et, demain,
 * tout envoi passent par la marque du mode actif. Basculer ne touche à aucune donnée : les
 * publications relevées restent attachées à leur marque.
 *
 * Accès : METRICOOL_JETON (le jeton d'API du compte, en-tête X-Mc-Auth) et METRICOOL_UTILISATEUR
 * (l'identifiant du compte, paramètre userId) dans pupitre/.env. Le jeton ne sort jamais dans un
 * journal ni dans un message. API : https://app.metricool.com/api (swagger.json du même hôte).
 *
 * Une publication « vient de Pupitre » quand une action de communication de Pupitre lui correspond :
 * même adresse, même référence, ou même réseau, même jour et même début de texte.
 */

defined('PUPITRE') or exit;

const METRICOOL_API = 'https://app.metricool.com/api';

/**
 * Ce que l'API sait rendre, réseau par réseau : [chemin, nature, canal de Pupitre].
 * Les mesures d'une publication changent pendant des semaines : chaque relevé relit toute la période.
 */
const METRICOOL_RELEVES = [
    ['/v2/analytics/posts/instagram', 'instagram', 'post'],
    ['/v2/analytics/reels/instagram', 'instagram', 'reel'],
    ['/v2/analytics/posts/facebook', 'facebook', 'post'],
    ['/v2/analytics/reels/facebook', 'facebook', 'reel'],
    ['/v2/analytics/posts/linkedin', 'linkedin', 'post'],
    ['/v2/analytics/posts/tiktok', 'tiktok', 'video'],
    ['/v2/analytics/posts/twitter', 'x', 'post'],
    ['/v2/analytics/posts/threads', 'threads', 'post'],
    ['/v2/analytics/posts/bluesky', 'bluesky', 'post'],
];
/** Champ de la marque (réponse de l'API) qui dit qu'un réseau y est connecté : réseau => champ. */
const METRICOOL_CONNEXIONS = ['instagram' => 'instagram', 'facebook' => 'facebook', 'linkedin' => 'linkedinCompany', 'tiktok' => 'tiktok', 'x' => 'twitter', 'threads' => 'threads', 'bluesky' => 'bluesky', 'youtube' => 'youtube'];
const RESEAUX_SOCIAUX = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X', 'threads' => 'Threads', 'bluesky' => 'Bluesky'];
const THEMES_POST = ['' => 'Non classé', 'annonce' => 'Annonce de concert', 'retour' => 'Retour de concert', 'presse' => 'Presse', 'album' => 'Album', 'coulisses' => 'Coulisses', 'personnel' => 'Personnel', 'autre' => 'Autre'];

function metricool_configure(): bool
{
    return env('METRICOOL_JETON') !== '' && env('METRICOOL_UTILISATEUR') !== '';
}

/** Le mode actif : « reel » (les comptes de l'artiste) ou « test » (les comptes de test). */
function metricool_mode(): string
{
    return reglage('metricool_mode') === 'test' ? 'test' : 'reel';
}

/** La marque d'un mode (par défaut : celle du mode actif) : ['id' => identifiant ou '', 'nom' => …, 'mode' => …]. */
function metricool_marque(?string $mode = null): array
{
    $mode = $mode ?? metricool_mode();
    $cle = $mode === 'test' ? 'metricool_marque_test' : 'metricool_marque_reelle';
    return ['id' => preg_replace('/\D/', '', reglage($cle)), 'nom' => reglage($cle . '_nom'), 'mode' => $mode];
}

/**
 * Appelle l'API. $marque : l'identifiant de la marque (blogId) ; null : aucune (liste des marques).
 * Rend [données, erreur] : les données décodées (le contenu de « data » quand la réponse en a un), ou
 * null et un message lisible. Ni le jeton, ni la réponse brute ne sont journalisés.
 */
function metricool_appeler(string $chemin, array $params = [], ?string $marque = null, string $methode = 'GET', ?array $corps = null): array
{
    if (!metricool_configure()) return [null, 'Metricool n\'est pas configuré : METRICOOL_JETON et METRICOOL_UTILISATEUR dans pupitre/.env.'];
    $params = ['userId' => env('METRICOOL_UTILISATEUR')] + ($marque !== null && $marque !== '' ? ['blogId' => $marque] : []) + $params;
    $ch = curl_init(rtrim(env('METRICOOL_URL', METRICOOL_API), '/') . $chemin . '?' . http_build_query($params));
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Mc-Auth: ' . env('METRICOOL_JETON'), 'Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $methode,
    ];
    if ($corps !== null) $options[CURLOPT_POSTFIELDS] = json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch, $options);
    $reponse = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $souci = curl_errno($ch);
    curl_close($ch);
    if ($souci !== 0 || !is_string($reponse)) return [null, 'Metricool ne répond pas (réseau, cURL ' . $souci . ').'];
    if ($code === 401 || $code === 403) return [null, 'Metricool refuse l\'accès (' . $code . ') : vérifiez le jeton, l\'identifiant du compte et que la marque lui appartient.'];
    if ($code === 404) return [null, 'Metricool ne connaît pas cette ressource pour cette marque (404).'];
    if ($code === 429) return [null, 'Metricool limite le nombre d\'appels : réessayez dans quelques minutes.'];
    if ($code < 200 || $code >= 300) return [null, 'Metricool a répondu par une erreur (' . $code . ').'];
    $d = json_decode($reponse, true);
    if (!is_array($d)) return [null, 'Metricool a répondu dans un format que Pupitre ne lit pas ici.'];
    return [array_key_exists('data', $d) && (is_array($d['data']) || $d['data'] === null) ? ($d['data'] ?? []) : $d, ''];
}

/** Les marques du compte : [[id, nom, réseaux connectés], …]. Rend [marques, erreur]. */
function metricool_marques(): array
{
    [$d, $erreur] = metricool_appeler('/admin/simpleProfiles');
    if ($d === null) return [[], $erreur];
    $marques = [];
    foreach ($d as $m) {
        if (!is_array($m) || empty($m['id'])) continue;
        $reseaux = [];
        foreach (METRICOOL_CONNEXIONS as $reseau => $champ) if (!empty($m[$champ])) $reseaux[] = $reseau;
        $marques[] = ['id' => (string) $m['id'], 'nom' => trim((string) ($m['label'] ?? '')) ?: ('Marque ' . $m['id']), 'reseaux' => $reseaux];
    }
    usort($marques, fn($a, $b) => strcasecmp($a['nom'], $b['nom']));
    return [$marques, ''];
}

/** Une date rendue par l'API ({dateTime, timezone}, une chaîne ISO, ou un horodatage) : « AAAA-MM-JJ HH:MM:SS », heure de l'installation. */
function metricool_date($v): ?string
{
    if (is_array($v)) $v = $v['dateTime'] ?? null;
    if ($v === null || $v === '') return null;
    if (is_numeric($v)) $t = (int) ($v > 20000000000 ? $v / 1000 : $v);
    else $t = strtotime((string) $v);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

/**
 * Ramène une publication, telle qu'un réseau la décrit, aux colonnes de `posts_sociaux`.
 * Un chiffre que le réseau ne donne pas reste NULL : il n'est jamais mis à zéro.
 */
function post_normaliser(string $reseau, string $nature, array $p): ?array
{
    $n = function (...$cles) use ($p) {
        foreach ($cles as $c) if (isset($p[$c]) && is_numeric($p[$c])) return (int) round((float) $p[$c]);
        return null;
    };
    $t = function (...$cles) use ($p) {
        foreach ($cles as $c) if (isset($p[$c]) && is_string($p[$c]) && trim($p[$c]) !== '') return trim($p[$c]);
        return '';
    };
    $id = $t('postId', 'reelId', 'videoId', 'tweetId', 'id');
    if ($id === '' && isset($p['id']) && is_numeric($p['id'])) $id = (string) $p['id'];
    if ($id === '') return null;
    $l = [
        'reseau' => $reseau,
        'identifiant' => mb_substr($id, 0, 190),
        'nature' => $nature,
        'publie_le' => metricool_date($p['publishedAt'] ?? $p['created'] ?? $p['createdAt'] ?? $p['publishedDate'] ?? $p['published'] ?? $p['createTime'] ?? $p['timestamp'] ?? null),
        'texte' => $t('content', 'text', 'comment', 'description', 'videoDescription', 'title'),
        'lien' => mb_substr($t('url', 'link', 'permalink', 'shareUrl'), 0, 1000),
        'visuel' => mb_substr($t('imageUrl', 'picture', 'thumbnailUrl', 'coverImageUrl', 'thumbnail', 'mediaUrl'), 0, 1000),
        'impressions' => $n('impressionsTotal', 'impressions', 'totalImpressions'),
        'portee' => $n('reach', 'impressionsUnique', 'uniqueImpressions'),
        'likes' => $n('likes', 'reactions', 'likeCount', 'totalLikes'),
        'commentaires' => $n('comments', 'commentCount', 'replies', 'totalReplies'),
        'partages' => $n('shares', 'shareCount', 'reposts', 'totalRetweets'),
        'enregistrements' => $n('saved', 'saves', 'totalBookmarks'),
        'clics' => $n('clicks', 'linkclicks', 'totalLinkClicks'),
        'vues' => $n('views', 'videoViews', 'videoViewsTotal', 'viewCount', 'totalVideoViews'),
        'interactions' => $n('interactions'),
        'engagement' => (isset($p['engagement']) && is_numeric($p['engagement'])) ? round((float) $p['engagement'], 3) : ((isset($p['totalEngagement']) && is_numeric($p['totalEngagement'])) ? round((float) $p['totalEngagement'], 3) : null),
    ];
    // interactions : ce que le réseau en dit, sinon la somme de ce qu'il donne
    if ($l['interactions'] === null) {
        $parts = array_filter([$l['likes'], $l['commentaires'], $l['partages'], $l['enregistrements']], fn($x) => $x !== null);
        $l['interactions'] = $parts ? array_sum($parts) : null;
    }
    return $l;
}

/**
 * Relève les publications de la marque active sur $jours jours, réseau par réseau, et leurs mesures.
 * Rend ['nouvelles' => n, 'mises_a_jour' => n, 'reseaux' => [réseau => nombre lu], 'erreurs' => [réseau => message], 'rapprochees' => n].
 */
function metricool_relever(int $jours = 90, ?int $tache_id = null): array
{
    $marque = metricool_marque();
    $bilan = ['nouvelles' => 0, 'mises_a_jour' => 0, 'reseaux' => [], 'erreurs' => [], 'rapprochees' => 0, 'marque' => $marque];
    if ($marque['id'] === '') { $bilan['erreurs']['réglage'] = 'Aucune marque n\'est choisie pour ce mode : réglez-la dans les réglages.'; return $bilan; }
    $de = date('Y-m-d\T00:00:00', time() - max(1, $jours) * 86400);
    $a = date('Y-m-d\T23:59:59');
    $fuseau = reglage('fuseau') ?: 'Europe/Paris';
    $refus = 0;
    foreach (METRICOOL_RELEVES as $i => [$chemin, $reseau, $nature]) {
        if ($tache_id) tache_avancer($tache_id, (int) (5 + 85 * $i / count(METRICOOL_RELEVES)), 'Lecture : ' . (RESEAUX_SOCIAUX[$reseau] ?? $reseau));
        [$d, $erreur] = metricool_appeler($chemin, ['from' => $de, 'to' => $a, 'timezone' => $fuseau], $marque['id']);
        if ($d === null) {
            // un réseau qui n'est pas connecté à la marque répond par une erreur : ce n'en est une que si tous échouent
            $bilan['erreurs'][$reseau . ($nature === 'post' ? '' : ' (' . $nature . ')')] = $erreur;
            if (str_contains($erreur, 'refuse l\'accès') && ++$refus >= 2) break;
            continue;
        }
        $lus = 0;
        foreach ($d as $p) {
            if (!is_array($p) || !($l = post_normaliser($reseau, $nature, $p))) continue;
            $lus++;
            $connu = bd_ligne('SELECT id FROM posts_sociaux WHERE marque = ? AND reseau = ? AND identifiant = ?', [$marque['id'], $l['reseau'], $l['identifiant']]);
            $l['releve_le'] = date('Y-m-d H:i:s');
            if ($connu) { bd_modifier('posts_sociaux', $l, ['id' => $connu['id']]); $bilan['mises_a_jour']++; }
            else { bd_inserer('posts_sociaux', $l + ['marque' => $marque['id']]); $bilan['nouvelles']++; }
        }
        $bilan['reseaux'][$reseau] = ($bilan['reseaux'][$reseau] ?? 0) + $lus;
    }
    $bilan['rapprochees'] = posts_rapprocher($marque['id']);
    reglage_ecrire('metricool_releve_le', date('Y-m-d H:i:s'));
    return $bilan;
}

/** Tâche de fond : relevé des publications. Charge : jours. */
function metricool_tache(array $t): string
{
    $b = metricool_relever((int) ($t['charge']['jours'] ?? 90), (int) $t['id']);
    $lus = array_sum($b['reseaux']);
    if (!$lus && $b['erreurs']) throw new ErreurLisible(reset($b['erreurs']));
    journaliser('creation', 'social', null, 'Relevé des publications · ' . ($b['marque']['nom'] ?: $b['marque']['id']), null, ['nouvelles' => $b['nouvelles'], 'mises_a_jour' => $b['mises_a_jour'], 'mode' => $b['marque']['mode']]);
    return pluriel($lus, 'publication lue', 'publications lues') . ' (' . ($b['nouvelles'] ? pluriel($b['nouvelles'], 'nouvelle', 'nouvelles') : 'aucune nouvelle') . ')'
        . ($b['rapprochees'] ? ', ' . pluriel($b['rapprochees'], 'rapprochée', 'rapprochées') . ' d\'une action de Pupitre' : '') . '.';
}

/** Texte ramené à ce qui se compare : minuscules, sans ponctuation ni espaces répétés, 60 premiers caractères. */
function post_debut(?string $texte): string
{
    return mb_substr(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $texte))), 0, 60);
}

/**
 * Rapproche les publications relevées des actions de communication de Pupitre : même adresse, même
 * référence, ou même réseau, à un jour près, et même début de texte. Rend le nombre de rapprochements faits.
 * Une action « programmée » dont la publication est retrouvée passe à « publiée », avec son adresse.
 */
function posts_rapprocher(string $marque): int
{
    $actions = bd_lignes("SELECT * FROM communications WHERE canal IN ('instagram', 'facebook', 'linkedin', 'youtube', 'tiktok', 'x', 'threads', 'bluesky')");
    if (!$actions) return 0;
    $n = 0;
    foreach (bd_lignes('SELECT * FROM posts_sociaux WHERE marque = ? AND communication_id IS NULL', [$marque]) as $p) {
        $trouvee = null;
        foreach ($actions as $a) {
            if ($a['canal'] !== $p['reseau']) continue;
            $meme_adresse = $a['lien'] !== '' && $p['lien'] !== '' && rtrim(preg_replace('/[?#].*$/', '', $a['lien']), '/') === rtrim(preg_replace('/[?#].*$/', '', $p['lien']), '/');
            $meme_reference = $a['reference_externe'] !== '' && $a['reference_externe'] === $p['identifiant'];
            $quand = $a['publiee_le'] ?: $a['prevue_le'];
            $proche = $quand && $p['publie_le'] && abs(strtotime($quand) - strtotime($p['publie_le'])) <= 36 * 3600;
            $meme_texte = post_debut($a['texte']) !== '' && mb_strlen(post_debut($a['texte'])) >= 20 && post_debut($a['texte']) === post_debut($p['texte']);
            if ($meme_adresse || $meme_reference || ($proche && $meme_texte)) { $trouvee = $a; break; }
        }
        if (!$trouvee) continue;
        $concert = bd_valeur('SELECT concert_id FROM communications_concerts WHERE communication_id = ? ORDER BY concert_id LIMIT 1', [$trouvee['id']]);
        bd_modifier('posts_sociaux', ['communication_id' => $trouvee['id'], 'concert_id' => $concert ?: null], ['id' => $p['id']]);
        if ($trouvee['statut'] !== 'publiee' && $p['publie_le']) {
            bd_modifier('communications', ['statut' => 'publiee', 'publiee_le' => $p['publie_le'], 'lien' => $trouvee['lien'] ?: $p['lien'], 'reference_externe' => $trouvee['reference_externe'] ?: $p['identifiant'], 'marque' => $marque], ['id' => $trouvee['id']]);
        }
        $n++;
    }
    return $n;
}

/** Médiane d'une liste de nombres (les valeurs absentes sont ignorées). null si la liste est vide. */
function mediane(array $valeurs): ?float
{
    $v = array_values(array_filter($valeurs, fn($x) => $x !== null));
    if (!$v) return null;
    sort($v);
    $m = intdiv(count($v), 2);
    return count($v) % 2 ? (float) $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
}

/**
 * Les publications relevées d'une marque, avec, pour chacune, son résultat rapporté à l'habitude du
 * réseau : « rapport » = ses interactions divisées par la médiane des interactions du même réseau sur
 * la période (1 = dans l'habitude ; 2 = deux fois plus). Filtres : reseau, origine (pupitre, autre), du, au, q.
 */
function posts_lister(string $marque, array $f = []): array
{
    $ou = ['marque = ?'];
    $p = [$marque];
    if (!empty($f['du'])) { $ou[] = 'publie_le >= ?'; $p[] = $f['du'] . ' 00:00:00'; }
    if (!empty($f['au'])) { $ou[] = 'publie_le <= ?'; $p[] = $f['au'] . ' 23:59:59'; }
    $tous = bd_lignes('SELECT * FROM posts_sociaux WHERE ' . implode(' AND ', $ou) . ' ORDER BY publie_le DESC, id DESC', $p);
    $medianes = [];
    foreach ($tous as $x) $medianes[$x['reseau']][] = $x['interactions'] === null ? null : (int) $x['interactions'];
    $medianes = array_map('mediane', $medianes);
    $l = [];
    foreach ($tous as $x) {
        $x['mediane'] = $medianes[$x['reseau']] ?? null;
        $x['rapport'] = ($x['interactions'] !== null && $x['mediane']) ? round((int) $x['interactions'] / $x['mediane'], 2) : null;
        $x['origine'] = $x['communication_id'] ? 'pupitre' : 'autre';
        if (!empty($f['reseau']) && $x['reseau'] !== $f['reseau']) continue;
        if (!empty($f['origine']) && $x['origine'] !== $f['origine']) continue;
        if (!empty($f['theme']) && $x['theme'] !== ($f['theme'] === 'aucun' ? '' : $f['theme'])) continue;
        if (!empty($f['q']) && mb_stripos((string) $x['texte'], $f['q']) === false) continue;
        $l[] = $x;
    }
    return $l;
}

/** Synthèse d'une liste de publications : nombres, sommes, médianes, et ce que donnent celles de Pupitre face aux autres. */
function posts_synthese(array $posts): array
{
    $somme = fn(string $c, array $l) => array_sum(array_map(fn($x) => (int) $x[$c], $l));
    $de = fn(string $origine) => array_values(array_filter($posts, fn($x) => $x['origine'] === $origine));
    $s = ['nombre' => count($posts), 'interactions' => $somme('interactions', $posts), 'impressions' => $somme('impressions', $posts), 'portee' => $somme('portee', $posts),
        'mediane' => mediane(array_map(fn($x) => $x['interactions'] === null ? null : (int) $x['interactions'], $posts)),
        'engagement' => mediane(array_map(fn($x) => $x['engagement'] === null ? null : (float) $x['engagement'], $posts))];
    foreach (['pupitre', 'autre'] as $origine) {
        $l = $de($origine);
        $s[$origine] = ['nombre' => count($l), 'mediane' => mediane(array_map(fn($x) => $x['interactions'] === null ? null : (int) $x['interactions'], $l))];
    }
    $par_reseau = [];
    foreach ($posts as $x) $par_reseau[$x['reseau']][] = $x;
    foreach ($par_reseau as $reseau => $l) {
        $s['reseaux'][$reseau] = ['nombre' => count($l), 'interactions' => $somme('interactions', $l), 'mediane' => mediane(array_map(fn($x) => $x['interactions'] === null ? null : (int) $x['interactions'], $l))];
    }
    $s['reseaux'] = $s['reseaux'] ?? [];
    return $s;
}
