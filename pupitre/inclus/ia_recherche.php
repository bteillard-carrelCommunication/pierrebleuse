<?php
/**
 * IA et recherche sur le web : le modèle cherche lui-même (outil « web_search » de l'API d'Anthropic,
 * exécuté par l'API), lit ce qu'il trouve et répond en citant ses sources.
 *
 * Deux services s'en servent, et la règle de Pupitre vaut pour les deux : l'IA propose, une personne
 * valide.
 *   - compléter une fiche de l'annuaire : son site officiel, ses comptes sur les réseaux sociaux
 *     (ils entrent « proposés », avec la page où ils ont été trouvés) ;
 *   - la veille : les pages récentes qui parlent de l'artiste (inclus/veille.php).
 *
 * Forme de la requête (documentation de l'API) : tools: [{ type: "web_search_20260209", name:
 * "web_search", max_uses, allowed_callers: ["direct"] }]. La réponse mêle des blocs de texte (avec leurs
 * citations), des blocs server_tool_use et web_search_tool_result ; stop_reason « pause_turn » demande
 * de renvoyer la conversation telle quelle pour que le tour reprenne. Chaque recherche est facturée par
 * l'API en plus des jetons : max_uses borne la dépense. L'outil doit être autorisé dans la console du
 * compte ; sinon l'API refuse la requête (400), et Pupitre le dit.
 *
 * La sortie contrainte par schéma ne se combine pas avec les citations : la réponse se termine donc par
 * un bloc JSON, relu ici avec tolérance ; s'il est illisible, un second appel, structuré, le remet en forme.
 */

defined('PUPITRE') or exit;

const IA_OUTIL_RECHERCHE = 'web_search_20260209';
const IA_OUTIL_RECHERCHE_SIMPLE = 'web_search_20250305';      // repli, si l'API refuse la version ci-dessus pour ce modèle

/**
 * Pose une question au modèle en lui donnant la recherche sur le web. Options : recherches (nombre
 * maximal, 5 par défaut), domaines_exclus, effort.
 * Rend ['ok', 'texte', 'sources' => [[adresse, titre]], 'recherches' => n, 'erreur'].
 */
function ia_chercher(string $objet, string $systeme, string $demande, array $o = [], ?int $tache_id = null): array
{
    // la recherche peut tourner sur un autre modèle que le reste (ANTHROPIC_MODELE_RECHERCHE) : elle lit beaucoup de pages, donc beaucoup de jetons
    $modele = env('ANTHROPIC_MODELE_RECHERCHE') ?: ia_modele();
    $r = ['ok' => false, 'texte' => '', 'sources' => [], 'recherches' => 0, 'erreur' => ''];
    $debut = microtime(true);
    $jetons = [0, 0];
    $noter = function (string $issue, string $technique = '') use ($objet, $modele, $tache_id, $debut, &$jetons) {
        bd_inserer('appels_ia', ['objet' => mb_substr($objet, 0, 80), 'modele' => mb_substr($modele, 0, 80), 'jetons_entree' => $jetons[0], 'jetons_sortie' => $jetons[1],
            'duree_ms' => (int) round((microtime(true) - $debut) * 1000), 'resultat' => $issue, 'erreur' => mb_substr($technique, 0, 255), 'tache_id' => $tache_id]);
    };
    if (!ia_configuree()) {
        $r['erreur'] = 'La clé de l\'API n\'est pas renseignée : ajoutez ANTHROPIC_API_KEY dans pupitre/.env.';
        return $r;
    }
    $outil = ['type' => IA_OUTIL_RECHERCHE, 'name' => 'web_search', 'max_uses' => max(1, min(12, (int) ($o['recherches'] ?? 5))), 'allowed_callers' => ['direct']];
    if (!empty($o['domaines_exclus'])) $outil['blocked_domains'] = array_values(array_slice($o['domaines_exclus'], 0, 50));
    $messages = [['role' => 'user', 'content' => $demande]];
    $entetes = ['content-type: application/json', 'anthropic-version: ' . IA_VERSION, 'x-api-key: ' . env('ANTHROPIC_API_KEY')];
    $sources = [];

    for ($tour = 1; $tour <= 6; $tour++) {
        $corps = ['model' => $modele, 'max_tokens' => 16000, 'system' => $systeme, 'messages' => $messages, 'tools' => [$outil]];
        if (!str_contains($modele, '4-5')) $corps['output_config'] = ['effort' => $o['effort'] ?? 'medium'];
        $ch = curl_init(env('ANTHROPIC_URL', IA_ADRESSE));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $entetes, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 600,
            CURLOPT_POSTFIELDS => json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $brut = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl = curl_errno($ch);
        curl_close($ch);
        $d = is_string($brut) ? json_decode($brut, true) : null;
        if ($curl !== 0 || $http !== 200 || !is_array($d)) {
            $type = (string) ($d['error']['type'] ?? '');
            $detail = mb_substr((string) ($d['error']['message'] ?? ''), 0, 180);
            // la version de l'outil refusée pour ce modèle : une fois, avec la version simple
            if ($http === 400 && $outil['type'] === IA_OUTIL_RECHERCHE && stripos($detail, 'web_search') !== false && stripos($detail, 'not enabled') === false && stripos($detail, 'disabled') === false) {
                $outil = array_diff_key($outil, ['allowed_callers' => 1]);
                $outil['type'] = IA_OUTIL_RECHERCHE_SIMPLE;
                continue;
            }
            $r['erreur'] = match (true) {
                $http === 401 => 'La clé de l\'API est refusée : vérifiez ANTHROPIC_API_KEY dans pupitre/.env.',
                $http === 400 && stripos($detail, 'web_search') !== false => 'La recherche sur le web n\'est pas autorisée pour ce compte de l\'API : un administrateur doit l\'activer dans la console d\'Anthropic.',
                $http === 400 => 'L\'API a refusé la requête. Le détail technique est dans le journal des appels.',
                $http === 429 => 'Trop de demandes en cours sur ce compte. Réessayez dans quelques minutes.',
                $curl !== 0 => 'L\'API n\'a pas répondu (réseau). Réessayez.',
                default => 'Le service de l\'API est momentanément indisponible. Réessayez dans quelques minutes.',
            };
            $noter('erreur', 'HTTP ' . $http . ($type !== '' ? ' ' . $type : '') . ($curl !== 0 ? ' cURL ' . $curl : '') . ($http === 400 ? ' · ' . $detail : ''));
            return $r;
        }
        $jetons[0] += (int) ($d['usage']['input_tokens'] ?? 0);
        $jetons[1] += (int) ($d['usage']['output_tokens'] ?? 0);
        $r['recherches'] += (int) ($d['usage']['server_tool_use']['web_search_requests'] ?? 0);
        foreach ($d['content'] ?? [] as $bloc) {
            if (($bloc['type'] ?? '') === 'text') {
                $r['texte'] .= (string) ($bloc['text'] ?? '');
                foreach ($bloc['citations'] ?? [] as $c) if (!empty($c['url'])) $sources[(string) $c['url']] = (string) ($c['title'] ?? '');
            } elseif (($bloc['type'] ?? '') === 'web_search_tool_result' && is_array($bloc['content'] ?? null) && array_is_list($bloc['content'])) {
                foreach ($bloc['content'] as $x) if (!empty($x['url'])) $sources[(string) $x['url']] ??= (string) ($x['title'] ?? '');
            }
        }
        $arret = (string) ($d['stop_reason'] ?? '');
        if ($arret === 'pause_turn') {
            // le tour n'est pas fini : la conversation repart telle quelle, avec ce que le modèle a déjà fait
            $messages[] = ['role' => 'assistant', 'content' => $d['content']];
            continue;
        }
        foreach ($sources as $adresse => $titre) $r['sources'][] = [$adresse, $titre];
        if ($arret === 'refusal') { $r['erreur'] = 'Le modèle a refusé de traiter cette demande.'; $noter('refus'); return $r; }
        if (trim($r['texte']) === '') { $r['erreur'] = 'La réponse du modèle est arrivée vide.'; $noter('erreur', 'réponse vide (' . $arret . ')'); return $r; }
        $r['ok'] = true;
        $noter('ok');
        return $r;
    }
    $r['erreur'] = 'La recherche n\'a pas abouti dans le temps imparti. Réessayez.';
    $noter('erreur', 'trop de reprises');
    return $r;
}

/** Le dernier bloc JSON d'un texte (entre ```json et ```, ou la dernière accolade équilibrée). null s'il n'y en a pas de lisible. */
function json_extraire(string $texte): ?array
{
    if (preg_match_all('/```(?:json)?\s*(\{.*?\})\s*```/s', $texte, $m) && ($d = json_decode(end($m[1]), true)) !== null && is_array($d)) return $d;
    $fin = strrpos($texte, '}');
    while ($fin !== false) {
        $profondeur = 0;
        for ($i = $fin; $i >= 0; $i--) {
            if ($texte[$i] === '}') $profondeur++;
            elseif ($texte[$i] === '{' && --$profondeur === 0) {
                $d = json_decode(substr($texte, $i, $fin - $i + 1), true);
                if (is_array($d)) return $d;
                break;
            }
        }
        $fin = $fin > 0 ? strrpos(substr($texte, 0, $fin), '}') : false;
    }
    return null;
}

/** L'identifiant d'un compte, tel qu'on l'écrit pour l'identifier : « @nom ». '' si l'adresse n'en donne pas. */
function compte_identifiant(string $reseau, string $adresse): string
{
    $a = trim($adresse);
    if ($a === '') return '';
    if ($a[0] === '@') return '@' . ltrim(preg_replace('/[^\w.\-]/u', '', $a), '@');
    if (!preg_match('#^https?://#i', $a)) return preg_match('/^[\w.\-]{2,60}$/u', $a) ? '@' . $a : '';
    $chemin = trim((string) parse_url($a, PHP_URL_PATH), '/');
    $segments = array_values(array_filter(explode('/', $chemin), 'strlen'));
    if (!$segments) return '';
    // un compte désigné par son numéro (facebook.com/1000…, linkedin.com/company/8510…) n'a pas d'identifiant qui s'écrive : son adresse sert telle quelle
    if (ctype_digit($segments[0]) || (in_array($reseau, ['linkedin'], true) && isset($segments[1]) && ctype_digit($segments[1]))) return '';
    return match ($reseau) {
        'linkedin' => in_array($segments[0], ['company', 'in', 'school', 'showcase'], true) && isset($segments[1]) ? '@' . urldecode($segments[1]) : '',
        'youtube' => str_starts_with($segments[0], '@') ? $segments[0] : (in_array($segments[0], ['c', 'user'], true) && isset($segments[1]) ? '@' . $segments[1] : ''),
        'tiktok', 'threads' => str_starts_with($segments[0], '@') ? $segments[0] : '@' . $segments[0],
        'bluesky' => $segments[0] === 'profile' && isset($segments[1]) ? '@' . $segments[1] : '',
        'facebook' => in_array($segments[0], ['profile.php', 'people', 'pages', 'groups'], true) ? '' : '@' . $segments[0],
        default => in_array($segments[0], ['p', 'reel', 'explore', 'share', 'intent', 'hashtag', 'search'], true) ? '' : '@' . $segments[0],
    };
}

/** Ramène l'adresse d'un compte à sa forme simple : sans paramètres de suivi, sans barre finale. */
function compte_adresse_propre(string $adresse): string
{
    $a = trim($adresse);
    if (!preg_match('#^https?://#i', $a)) return $a;
    return rtrim(preg_replace('/[?#].*$/', '', $a), '/');
}

/** Ce qui manque à une fiche de l'annuaire, pour l'identifier sur les réseaux : ['site' => bool, 'reseaux' => [réseaux sans compte]]. */
function fiche_manques(array $f): array
{
    $ont = array_column(bd_lignes('SELECT DISTINCT reseau FROM annuaire_comptes WHERE fiche_id = ?', [$f['id']]), 'reseau');
    $attendus = in_array($f['categorie'], ['compositeur'], true) ? [] : ['instagram', 'facebook'];
    return ['site' => trim((string) $f['site']) === '', 'reseaux' => array_values(array_diff($attendus, $ont)), 'aucun_compte' => !$ont];
}

/**
 * Demande à l'IA le site officiel et les comptes officiels d'une fiche. Les comptes trouvés entrent
 * « proposés » (origine « ia », avec leur source) ; le site, s'il manquait, est proposé de même, comme un
 * compte « site ». Rien de déjà saisi n'est touché. Rend [nombre de propositions, remarques, erreur].
 */
function fiche_completer(int $fiche_id, ?int $tache_id = null): array
{
    $f = fiche_lire($fiche_id);
    if (!$f) return [0, '', 'Fiche introuvable.'];
    $connus = bd_lignes('SELECT reseau, adresse FROM annuaire_comptes WHERE fiche_id = ?', [$fiche_id]);
    $demande = "Fiche à compléter :\n" . json_encode(array_filter([
        'nom' => $f['nom'], 'categorie' => CATEGORIES[$f['categorie']] ?? $f['categorie'], 'autres_noms' => lignes((string) ($f['autres_noms'] ?? '')),
        'ville' => $f['ville_en'], 'pays' => $f['pays_en'], 'site_connu' => $f['site'],
        'comptes_deja_connus' => array_map(fn($c) => $c['reseau'] . ' : ' . $c['adresse'], $connus),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        . "\n\nContexte : cette fiche appartient à l'annuaire de l'équipe du chef d'orchestre " . (reglage('artiste_nom') ?: 'de ce site') . ", qui travaille avec elle."
        . "\nCe qui manque : " . (trim((string) $f['site']) === '' ? 'le site officiel ; ' : '') . 'les comptes officiels sur les réseaux sociaux qui ne sont pas déjà connus.';
    $r = ia_chercher('complément de fiche (annuaire)', ia_consignes('completer-fiche'), $demande, ['recherches' => 4, 'effort' => 'medium'], $tache_id);
    if (!$r['ok']) return [0, '', $r['erreur']];
    $d = json_extraire($r['texte']);
    if ($d === null) return [0, '', 'La réponse de l\'IA n\'a pas pu être lue. Rien n\'a été retenu : réessayez, ou saisissez les comptes à la main.'];
    $n = fiche_propositions_poser($f, $d);
    $remarques = trim((string) ($d['remarques'] ?? ''));
    // ce que l'IA dit de sa recherche reste au journal de la fiche : ce qu'elle a trouvé, ce dont elle doute
    journaliser('creation', 'fiche', $fiche_id, $f['nom'] . ' : recherche de ses comptes par l\'IA', null, array_filter(['proposes' => $n, 'remarques' => $remarques, 'recherches' => $r['recherches']]));
    return [$n, $remarques, ''];
}

/** Range ce que l'IA propose pour une fiche : comptes « proposés », jamais en double. Rend le nombre de propositions posées. */
function fiche_propositions_poser(array $f, array $d): int
{
    $deja = [];
    foreach (bd_lignes('SELECT reseau, adresse FROM annuaire_comptes WHERE fiche_id = ?', [$f['id']]) as $c) $deja[$c['reseau'] . '|' . mb_strtolower(compte_adresse_propre($c['adresse']))] = true;
    $n = 0;
    $ordre = (int) bd_valeur('SELECT COALESCE(MAX(ordre), 0) FROM annuaire_comptes WHERE fiche_id = ?', [$f['id']]);
    $poser = function (string $reseau, string $adresse, string $source) use ($f, &$deja, &$n, &$ordre) {
        $adresse = compte_adresse_propre($adresse);
        if (!adresse_valide($adresse) || $adresse === '' || isset($deja[$reseau . '|' . mb_strtolower($adresse)])) return;
        bd_inserer('annuaire_comptes', ['fiche_id' => $f['id'], 'reseau' => $reseau, 'adresse' => mb_substr($adresse, 0, 255), 'etat' => 'propose', 'origine' => 'ia',
            'source' => mb_substr(adresse_valide($source) ? $source : '', 0, 500), 'ordre' => ++$ordre]);
        $deja[$reseau . '|' . mb_strtolower($adresse)] = true;
        $n++;
    };
    if (trim((string) $f['site']) === '' && is_string($d['site'] ?? null) && $d['site'] !== '') $poser('site', $d['site'], $d['site']);
    foreach (is_array($d['comptes'] ?? null) ? $d['comptes'] : [] as $c) {
        if (!is_array($c) || !isset(RESEAUX_CONNUS[$c['reseau'] ?? '']) || ($c['reseau'] ?? '') === 'site') continue;
        if (!in_array($c['confiance'] ?? '', ['haute', 'moyenne'], true)) continue;
        // un compte sans page où il a été vu ne se propose pas
        if (!adresse_valide((string) ($c['source'] ?? '')) || ($c['source'] ?? '') === '') continue;
        $poser($c['reseau'], (string) ($c['adresse'] ?? ''), (string) $c['source']);
    }
    if ($n) $GLOBALS['notifications_perimees'] = true;
    return $n;
}

/** Les fiches qu'il vaut la peine de compléter d'abord : celles des concerts à venir, sans site ou sans aucun compte. */
function fiches_a_completer(int $max = 15): array
{
    $jour = aujourdhui();
    return bd_lignes("SELECT DISTINCT a.id, a.nom, a.categorie FROM annuaire a
        WHERE a.categorie IN ('orchestre', 'ensemble', 'salle', 'festival', 'soliste', 'media', 'maison_de_disques')
          AND NOT EXISTS (SELECT 1 FROM annuaire_comptes x WHERE x.fiche_id = a.id)
          AND (a.id IN (SELECT c.ensemble_id FROM concerts c JOIN representations r ON r.concert_id = c.id WHERE r.date >= ? AND c.ensemble_id IS NOT NULL)
            OR a.id IN (SELECT c.salle_id FROM concerts c JOIN representations r ON r.concert_id = c.id WHERE r.date >= ? AND c.salle_id IS NOT NULL)
            OR a.id IN (SELECT o.fiche_id FROM concerts_orchestres o JOIN representations r ON r.concert_id = o.concert_id WHERE r.date >= ?)
            OR a.id IN (SELECT pf.fiche_id FROM participants_fiches pf JOIN participants p ON p.id = pf.participant_id JOIN representations r ON r.concert_id = p.concert_id WHERE r.date >= ?))
        ORDER BY a.nom LIMIT " . (int) $max, [$jour, $jour, $jour, $jour]);
}

/** Tâche de fond : compléter des fiches. Charge : fiches (identifiants) ; sans liste : celles des concerts à venir. */
function annuaire_completer_tache(array $t): string
{
    $ids = array_values(array_filter(array_map('intval', (array) ($t['charge']['fiches'] ?? []))));
    if (!$ids) $ids = array_map(fn($f) => (int) $f['id'], fiches_a_completer(12));
    if (!$ids) return 'Aucune fiche à compléter : toutes celles des concerts à venir ont déjà un compte.';
    $proposes = 0;
    $soucis = [];
    foreach ($ids as $i => $id) {
        $nom = (string) bd_valeur('SELECT nom FROM annuaire WHERE id = ?', [$id]);
        tache_avancer((int) $t['id'], (int) (5 + 90 * $i / count($ids)), 'Recherche : ' . $nom . ' (' . ($i + 1) . ' sur ' . count($ids) . ')');
        [$n, , $erreur] = fiche_completer($id, (int) $t['id']);
        $proposes += $n;
        if ($erreur !== '') { $soucis[] = $erreur; if (count($soucis) >= 2 && !$proposes) break; }     // la clé ou l'outil refusés : inutile d'insister
    }
    if ($soucis && !$proposes) throw new ErreurLisible($soucis[0]);
    return ($proposes ? pluriel($proposes, 'compte proposé', 'comptes proposés') . ', à vérifier (tableau de bord, « À valider »)' : 'Aucun compte officiel trouvé avec assez de certitude') . ' pour ' . pluriel(count($ids), 'fiche', 'fiches') . '.';
}

/**
 * Les parties prenantes de concerts : orchestres, salle, solistes et autres participants reliés à l'annuaire,
 * chacun avec ses comptes par réseau. Rend [fiche => ['id', 'nom', 'categorie', 'mots_diese', 'comptes' => [réseau => ['adresse', 'identifiant', 'etat']]]].
 * Pour un réseau, le compte vérifié l'emporte sur un compte proposé.
 */
function parties_prenantes(array $concerts): array
{
    $concerts = array_values(array_filter(array_map('intval', $concerts)));
    if (!$concerts) return [];
    $dans = implode(',', $concerts);
    $ids = array_merge(
        bd_colonne("SELECT ensemble_id FROM concerts WHERE id IN ($dans) AND ensemble_id IS NOT NULL"),
        bd_colonne("SELECT fiche_id FROM concerts_orchestres WHERE concert_id IN ($dans)"),
        bd_colonne("SELECT salle_id FROM concerts WHERE id IN ($dans) AND salle_id IS NOT NULL"),
        bd_colonne("SELECT pf.fiche_id FROM participants_fiches pf JOIN participants p ON p.id = pf.participant_id WHERE p.concert_id IN ($dans)")
    );
    $l = [];
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $f = bd_ligne('SELECT id, nom, categorie, mots_diese, site FROM annuaire WHERE id = ?', [$id]);
        if (!$f) continue;
        $f['comptes'] = [];
        foreach (bd_lignes("SELECT * FROM annuaire_comptes WHERE fiche_id = ? AND reseau <> 'site' AND etat <> 'a_revoir' ORDER BY etat = 'verifie' DESC, ordre, id", [$id]) as $c) {
            $f['comptes'][$c['reseau']] ??= ['adresse' => $c['adresse'], 'identifiant' => compte_identifiant($c['reseau'], $c['adresse']), 'etat' => $c['etat']];
        }
        $l[$id] = $f;
    }
    uasort($l, fn($a, $b) => [array_search($a['categorie'], array_keys(CATEGORIES)), $a['nom']] <=> [array_search($b['categorie'], array_keys(CATEGORIES)), $b['nom']]);
    return $l;
}
