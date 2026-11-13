<?php
/**
 * Configuration : lecture de pupitre/.env.
 *
 * .env n'est jamais commité ; .env.exemple liste les variables. Aucune valeur de ce fichier ne
 * doit sortir dans un journal ou dans un message d'erreur.
 */

define('PUPITRE', true);
define('RACINE', dirname(__DIR__));                 // le dossier pupitre/
define('STOCKAGE', RACINE . '/stockage');
define('VERSION_PUPITRE', '1.0');

/** Lit un fichier .env : une variable par ligne, CLE=valeur, # pour un commentaire. */
function env_lire(string $chemin): array
{
    $v = [];
    if (!is_readable($chemin)) return $v;
    foreach (file($chemin, FILE_IGNORE_NEW_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || strpos($ligne, '=') === false) continue;
        [$cle, $valeur] = explode('=', $ligne, 2);
        $valeur = trim($valeur);
        if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && substr($valeur, -1) === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }
        $v[trim($cle)] = $valeur;
    }
    return $v;
}

/** Valeur d'une variable d'environnement (.env, sinon l'environnement du processus). */
function env(string $cle, string $defaut = ''): string
{
    static $v = null;
    if ($v === null) $v = env_lire(RACINE . '/.env');
    if (isset($v[$cle]) && $v[$cle] !== '') return $v[$cle];
    $e = getenv($cle);
    return ($e !== false && $e !== '') ? $e : $defaut;
}

function en_dev(): bool
{
    return env('APP_ENV', 'prod') === 'dev';
}

function en_cli(): bool
{
    return PHP_SAPI === 'cli';
}

/** Adresse de Pupitre, sans barre finale : APP_URL, sinon déduite de la requête. */
function adresse_pupitre(): string
{
    $url = rtrim(env('APP_URL'), '/');
    if ($url !== '') return $url;
    if (en_cli()) return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . chemin_pupitre();
}

/** Chemin de Pupitre sur le serveur web (« /pierrebleuse/pupitre »), sans barre finale. */
function chemin_pupitre(): string
{
    $url = rtrim(env('APP_URL'), '/');
    if ($url !== '') return rtrim((string) parse_url($url, PHP_URL_PATH), '/');
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dossier = rtrim(dirname($script), '/');
    // un script de ajax/ est servi un cran sous la racine de Pupitre
    return preg_replace('#/ajax$#', '', $dossier);
}
