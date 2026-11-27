<?php
/**
 * IA : appel direct de l'API d'Anthropic (Messages), en cURL, côté serveur.
 *
 * C'est le seul endroit de Pupitre qui parle à l'API : tous les services (import des concerts,
 * lecture d'un article, traduction, géolocalisation : inclus/ia_services.php, inclus/import.php)
 * passent par ia_appeler().
 *
 * Règle de l'application : l'IA propose, une personne valide. Rien de ce que rend l'IA n'est
 * écrit dans l'agenda, dans l'annuaire ou dans un contenu publié sans validation.
 *
 * Clé dans ANTHROPIC_API_KEY, modèle dans ANTHROPIC_MODELE. Chaque appel est journalisé dans la
 * table appels_ia : objet, modèle, jetons, durée, résultat. Ni la clé, ni le contenu envoyé, ni
 * la réponse ne sont jamais écrits dans un journal ou dans un message d'erreur.
 *
 * Forme de la requête (documentation de l'API, version 2026-10-21) :
 *   - en-têtes x-api-key, anthropic-version, content-type ;
 *   - un PDF part en bloc « document », source base64, avant le texte de la demande ;
 *   - la sortie est contrainte par un schéma JSON : output_config.format, type json_schema ;
 *   - la réponse est lue en flux (SSE) : pas de délai d'attente HTTP sur un long document ;
 *   - stop_reason se lit avant le contenu : « refusal » et « max_tokens » ne rendent pas un JSON sûr.
 */

defined('PUPITRE') or exit;

const IA_ADRESSE = 'https://api.anthropic.com/v1/messages';
const IA_VERSION = '2026-10-21';
const IA_MODELE_DEFAUT = 'claude-opus-5-5';
const IA_JETONS_MAX = 64000;

/** Modèles pour lesquels l'API sait rejouer une demande refusée par ses filtres sur un autre modèle. */
const IA_MODELES_AVEC_REPLI = ['claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-2', 'claude-fable-5-1', 'claude-sonnet-5-5'];

function ia_configuree(): bool
{
    return env('ANTHROPIC_API_KEY') !== '';
}

function ia_modele(): string
{
    return env('ANTHROPIC_MODELE', IA_MODELE_DEFAUT);
}

/** Lit un fichier de consignes de inclus/consignes/. */
function ia_consignes(string $nom): string
{
    $f = RACINE . '/inclus/consignes/' . basename($nom) . '.md';
    if (!is_readable($f)) throw new ErreurLisible('Consignes introuvables : ' . basename($nom) . '.md');
    return trim(file_get_contents($f));
}

/** Bloc de contenu pour un PDF : le fichier entier, en base64 sans retour à la ligne. */
function ia_bloc_pdf(string $chemin): array
{
    return ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode(file_get_contents($chemin))]];
}

function ia_bloc_texte(string $texte): array
{
    return ['type' => 'text', 'text' => $texte];
}

/**
 * Appelle le modèle. $requete : systeme (consignes), contenu (blocs du message), schema (schéma
 * JSON de la réponse, facultatif), effort (low, medium, high…).
 * $suivi($caracteres) est appelé pendant la lecture, pour l'avancement à l'écran.
 * Rend ['ok', 'texte', 'arret', 'erreur', 'modele', 'jetons_entree', 'jetons_sortie'].
 */
function ia_appeler(string $objet, array $requete, ?int $tache_id = null, ?callable $suivi = null): array
{
    $modele = ia_modele();
    $resultat = ['ok' => false, 'texte' => '', 'arret' => '', 'erreur' => '', 'modele' => $modele, 'jetons_entree' => 0, 'jetons_sortie' => 0];
    $debut = microtime(true);
    $noter = function (string $issue, string $technique = '') use (&$resultat, $objet, $tache_id, $debut) {
        bd_inserer('appels_ia', [
            'objet' => mb_substr($objet, 0, 80),
            'modele' => mb_substr($resultat['modele'], 0, 80),
            'jetons_entree' => $resultat['jetons_entree'],
            'jetons_sortie' => $resultat['jetons_sortie'],
            'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
            'resultat' => $issue,
            'erreur' => mb_substr($technique, 0, 255),
            'tache_id' => $tache_id,
        ]);
    };
    if (!ia_configuree()) {
        $resultat['erreur'] = 'La clé de l\'API n\'est pas renseignée : ajoutez ANTHROPIC_API_KEY dans pupitre/.env.';
        $noter('erreur', 'clé absente');
        return $resultat;
    }

    $corps = [
        'model' => $modele,
        'max_tokens' => IA_JETONS_MAX,
        'stream' => true,
        'system' => (string) ($requete['systeme'] ?? ''),
        'messages' => [['role' => 'user', 'content' => $requete['contenu']]],
    ];
    $config = [];
    if (!str_contains($modele, '4-5')) $config['effort'] = $requete['effort'] ?? 'high';
    if (!empty($requete['schema'])) $config['format'] = ['type' => 'json_schema', 'schema' => $requete['schema']];
    if ($config) $corps['output_config'] = $config;
    $entetes = ['content-type: application/json', 'anthropic-version: ' . IA_VERSION, 'x-api-key: ' . env('ANTHROPIC_API_KEY')];
    if (in_array($modele, IA_MODELES_AVEC_REPLI, true)) {
        // si les filtres de sécurité du modèle refusent la demande, l'API la rejoue sur un autre modèle
        $corps['fallbacks'] = 'default';
        $entetes[] = 'anthropic-beta: server-side-fallback-2026-07-01';
    }
    $charge = json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $http = 0;
    $erreur_api = '';
    for ($essai = 1; $essai <= 3; $essai++) {
        $tampon = '';
        $brut = '';
        $texte = '';
        $arret = '';
        $erreur_api = '';
        $ch = curl_init(IA_ADRESSE);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_POSTFIELDS => $charge,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_WRITEFUNCTION => function ($ch, string $morceau) use (&$tampon, &$brut, &$texte, &$arret, &$erreur_api, &$resultat, $suivi) {
                if (strlen($brut) < 4000) $brut .= $morceau;              // une réponse d'erreur n'est pas un flux
                $tampon .= $morceau;
                while (($fin = strpos($tampon, "\n")) !== false) {
                    $ligne = rtrim(substr($tampon, 0, $fin), "\r");
                    $tampon = substr($tampon, $fin + 1);
                    if (!str_starts_with($ligne, 'data:')) continue;
                    $ev = json_decode(trim(substr($ligne, 5)), true);
                    if (!is_array($ev)) continue;
                    switch ($ev['type'] ?? '') {
                        case 'message_start':
                            $resultat['modele'] = (string) ($ev['message']['model'] ?? $resultat['modele']);
                            $resultat['jetons_entree'] = (int) ($ev['message']['usage']['input_tokens'] ?? 0);
                            break;
                        case 'content_block_start':
                            // repli après refus : ce qui précède vient du modèle qui a refusé, on le laisse tomber
                            if (($ev['content_block']['type'] ?? '') === 'fallback') $texte = '';
                            break;
                        case 'content_block_delta':
                            if (($ev['delta']['type'] ?? '') === 'text_delta') {
                                $texte .= (string) ($ev['delta']['text'] ?? '');
                                if ($suivi) $suivi(strlen($texte));
                            }
                            break;
                        case 'message_delta':
                            if (isset($ev['delta']['stop_reason'])) $arret = (string) $ev['delta']['stop_reason'];
                            if (isset($ev['usage']['output_tokens'])) $resultat['jetons_sortie'] = (int) $ev['usage']['output_tokens'];
                            if (isset($ev['usage']['input_tokens'])) $resultat['jetons_entree'] = (int) $ev['usage']['input_tokens'];
                            break;
                        case 'error':
                            $erreur_api = (string) ($ev['error']['type'] ?? 'erreur');
                            break;
                    }
                }
                return strlen($morceau);
            },
        ]);
        curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl = curl_errno($ch);
        curl_close($ch);

        if ($curl === 0 && $http === 200 && $erreur_api === '') {
            $resultat['texte'] = $texte;
            $resultat['arret'] = $arret;
            if ($arret === 'refusal') {
                $resultat['erreur'] = 'Le modèle a refusé de traiter ce document.';
                $noter('refus');
            } elseif ($arret === 'max_tokens') {
                $resultat['erreur'] = 'La réponse du modèle est incomplète : le document est trop long pour une seule lecture. Découpez-le en deux dépôts.';
                $noter('tronque');
            } elseif ($arret === '' || trim($texte) === '') {
                $resultat['erreur'] = 'La réponse du modèle est arrivée vide ou interrompue.';
                $noter('erreur', 'flux interrompu');
            } else {
                $resultat['ok'] = true;
                $noter('ok');
            }
            return $resultat;
        }

        // erreur : hors flux, le corps est un JSON { type: error, error: { type, message } }
        $detail = '';
        if ($erreur_api === '' && $http !== 200) {
            $e = json_decode($brut, true);
            $erreur_api = (string) ($e['error']['type'] ?? '');
            // une requête mal formée (400) : le message de l'API nomme le paramètre fautif, jamais le document
            if ($http === 400) $detail = ' · ' . mb_substr((string) ($e['error']['message'] ?? ''), 0, 180);
        }
        $passagere = $curl !== 0 || in_array($http, [408, 409, 429, 500, 502, 503, 504, 529], true) || in_array($erreur_api, ['overloaded_error', 'api_error', 'rate_limit_error'], true);
        if (!$passagere || $essai === 3) {
            $resultat['erreur'] = match (true) {
                $http === 401 => 'La clé de l\'API est refusée : vérifiez ANTHROPIC_API_KEY dans pupitre/.env.',
                $http === 402 => 'Le compte de l\'API est bloqué pour une question de facturation.',
                $http === 403 => 'Cette clé n\'a pas le droit d\'utiliser ce modèle.',
                $http === 404 => 'Modèle inconnu ou indisponible pour ce compte : vérifiez ANTHROPIC_MODELE dans pupitre/.env.',
                $http === 413 => 'Le document est trop lourd pour l\'API (32 Mo et 600 pages au plus).',
                $http === 400 => 'L\'API a refusé la requête. Le détail technique est dans le journal des appels.',
                $http === 429 => 'Trop de demandes en cours sur ce compte. Réessayez dans quelques minutes.',
                $curl !== 0 => 'L\'API n\'a pas répondu (réseau). Réessayez.',
                default => 'Le service de l\'API est momentanément indisponible. Réessayez dans quelques minutes.',
            };
            $noter('erreur', 'HTTP ' . $http . ($erreur_api !== '' ? ' ' . $erreur_api : '') . ($curl !== 0 ? ' cURL ' . $curl : '') . $detail);
            return $resultat;
        }
        sleep($essai * 3);
    }
    return $resultat;
}
