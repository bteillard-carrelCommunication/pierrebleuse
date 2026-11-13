<?php
/**
 * Fonctions communes : échappement, adresses, messages, dates, textes en deux langues.
 */

defined('PUPITRE') or exit;

/** Échappe une valeur pour le HTML. Toute sortie passe par ici. */
function e($s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Adresse d'une page de Pupitre : url('concert', ['id' => 3]). */
function url(string $page = 'bord', array $params = []): string
{
    $q = http_build_query(array_merge(['page' => $page], $params));
    return chemin_pupitre() . '/index.php?' . $q;
}

function rediriger(string $adresse): void
{
    header('Location: ' . $adresse);
    exit;
}

function est_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Champ texte reçu, nettoyé de ses espaces. */
function recu(string $nom, string $defaut = ''): string
{
    $v = $_POST[$nom] ?? $_GET[$nom] ?? $defaut;
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : $defaut;
}

function recu_entier(string $nom): ?int
{
    $v = recu($nom);
    return ($v !== '' && ctype_digit($v)) ? (int) $v : null;
}

/** Message affiché une fois, à la page suivante. $type : ok, alerte, erreur. */
function message(string $type, string $texte): void
{
    $_SESSION['messages'][] = [$type, $texte];
}

function messages_lire(): array
{
    $m = $_SESSION['messages'] ?? [];
    unset($_SESSION['messages']);
    return $m;
}

/** Réponse JSON d'une action de ajax/. */
function repondre_json(array $donnees, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------- Dates : toujours la date et l'heure locales de la salle, jamais converties ----------

const MOIS_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const JOURS_FR = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

function date_valide(string $iso): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) return false;
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** « 14 novembre 2026 » ; avec $jour : « samedi 14 novembre 2026 » ; sans $annee : « 14 novembre ». */
function date_fr(?string $iso, bool $jour = false, bool $annee = true): string
{
    if (!$iso || !date_valide(substr($iso, 0, 10))) return '';
    [$a, $m, $j] = array_map('intval', explode('-', substr($iso, 0, 10)));
    $s = ($j === 1 ? '1er' : $j) . ' ' . MOIS_FR[$m - 1] . ($annee ? ' ' . $a : '');
    if ($jour) $s = JOURS_FR[(int) date('w', mktime(12, 0, 0, $m, $j, $a))] . ' ' . $s;
    return $s;
}

/** « novembre 2026 » pour AAAA-MM. */
function mois_fr(string $am): string
{
    [$a, $m] = array_map('intval', explode('-', $am));
    return MOIS_FR[$m - 1] . ' ' . $a;
}

/** « 14/11/2026 à 19 h 30 » pour une date et heure de la base (horodatage de Pupitre). */
function horodatage_fr(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    return $t ? date('d/m/Y', $t) . ' à ' . heure_fr(date('H:i', $t)) : '';
}

/** « 20 h », « 19 h 30 ». */
function heure_fr(?string $h): string
{
    if (!$h || !preg_match('/^(\d{1,2}):(\d{2})/', $h, $m)) return '';
    return (int) $m[1] . ' h' . ($m[2] === '00' ? '' : ' ' . $m[2]);
}

/** Heure saisie (« 19h30 », « 19:30 », « 20 h ») → « 19:30 » ; '' si vide ; null si illisible. */
function heure_lire(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return '';
    if (!preg_match('/^(\d{1,2})\s*(?:[:hH.]\s*(\d{2})?)?$/', $s, $m)) return null;
    $h = (int) $m[1];
    $mn = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
    if ($h > 23 || $mn > 59) return null;
    return sprintf('%02d:%02d', $h, $mn);
}

function aujourdhui(): string
{
    return date('Y-m-d');
}

function ajouter_jours(string $iso, int $n): string
{
    return date('Y-m-d', strtotime($iso . ' 12:00:00 ' . ($n >= 0 ? '+' : '') . $n . ' days'));
}

// ---------- Textes ----------

/** Minuscules, sans accents ni ponctuation : pour comparer deux noms. */
function normaliser(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    if (class_exists('Normalizer')) {
        $s = preg_replace('/\p{Mn}+/u', '', Normalizer::normalize($s, Normalizer::FORM_D) ?: $s);
    }
    $s = strtr($s, ['œ' => 'oe', 'æ' => 'ae', 'ø' => 'o', 'ß' => 'ss', 'ł' => 'l', 'đ' => 'd']);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

/** Identifiant lisible : « Au revoir, Pierre » → « au-revoir-pierre ». */
function slug(string $s, int $max = 60): string
{
    $s = str_replace(' ', '-', normaliser($s));
    return trim(substr($s, 0, $max), '-');
}

/** Lignes non vides d'un champ multiligne. */
function lignes(?string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $s)), 'strlen'));
}

/** Paragraphes d'un champ : séparés par une ligne vide. */
function paragraphes(?string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', trim((string) $s))), 'strlen'));
}

function couper(?string $s, int $n = 80): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}

function adresse_valide(string $u): bool
{
    return $u === '' || (bool) preg_match('#^https?://[^\s<>"]+$#i', $u);
}

/** Taille lisible : « 1,4 Mo ». */
function taille_lisible(int $octets): string
{
    if ($octets >= 1048576) return str_replace('.', ',', (string) round($octets / 1048576, 1)) . ' Mo';
    if ($octets >= 1024) return round($octets / 1024) . ' ko';
    return $octets . ' o';
}

function pluriel(int $n, string $un, string $plusieurs): string
{
    return $n . ' ' . ($n > 1 ? $plusieurs : $un);
}

/** Crée le dossier s'il manque. */
function dossier(string $chemin): string
{
    if (!is_dir($chemin)) @mkdir($chemin, 0775, true);
    return $chemin;
}

/** Écrit une ligne dans un journal de stockage/journaux/. Jamais de secret, jamais de contenu confidentiel. */
function journal_fichier(string $nom, string $ligne): void
{
    $f = dossier(STOCKAGE . '/journaux') . '/' . $nom . '.log';
    @file_put_contents($f, '[' . date('Y-m-d H:i:s') . '] ' . $ligne . "\n", FILE_APPEND | LOCK_EX);
}
