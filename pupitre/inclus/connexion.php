<?php
/**
 * Connexion : session, comptes, liens à usage unique, tentatives, jeton anti-CSRF.
 *
 * Comptes de l'agence (pilote, éditeur) : adresse et mot de passe. Artiste : lien de connexion
 * à usage unique envoyé par mail, valable quinze minutes. Le même mécanisme de lien sert à
 * choisir ou à changer le mot de passe d'un compte de l'agence.
 */

defined('PUPITRE') or exit;

const DUREE_LIEN = 900;            // quinze minutes
const DUREE_INACTIVITE = 7200;     // deux heures sans action : la session se ferme
const TENTATIVES_MAX = 5;          // échecs tolérés…
const TENTATIVES_FENETRE = 900;    // …par quart d'heure, par adresse et par poste

function session_demarrer(): void
{
    if (en_cli() || session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('pupitre');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (chemin_pupitre() ?: '') . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',       // Lax : le lien reçu par mail doit pouvoir ouvrir la session
    ]);
    session_start();
    $maintenant = time();
    if (isset($_SESSION['vu_le']) && $maintenant - $_SESSION['vu_le'] > DUREE_INACTIVITE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['vu_le'] = $maintenant;
}

/** Le compte connecté, relu en base à chaque requête (un compte désactivé perd l'accès aussitôt). */
function compte(): ?array
{
    static $compte = false;
    if ($compte !== false) return $compte;
    $compte = null;
    if (!empty($_SESSION['compte_id'])) {
        $c = bd_ligne('SELECT id, email, nom, role FROM comptes WHERE id = ? AND actif = 1', [$_SESSION['compte_id']]);
        if ($c) $compte = $c; else unset($_SESSION['compte_id']);
    }
    return $compte;
}

function peut(string $droit): bool
{
    $c = compte();
    return $c !== null && role_peut($c['role'], $droit);
}

/** Sans connexion, aucune page ne répond : retour à l'écran de connexion. */
function exiger_connexion(): array
{
    $c = compte();
    if ($c) return $c;
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/')) repondre_json(['erreur' => 'Connexion requise.'], 401);
    rediriger(chemin_pupitre() . '/connexion.php');
}

function exiger_droit(string $droit): array
{
    $c = exiger_connexion();
    if (!role_peut($c['role'], $droit)) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/')) repondre_json(['erreur' => 'Action non autorisée.'], 403);
        http_response_code(403);
        afficher_refus();
    }
    return $c;
}

function afficher_refus(): void
{
    $titre = 'Accès refusé';
    $contenu = '<div class="vide"><h1>Accès refusé</h1><p>Votre rôle ne donne pas accès à cette page ou à cette action.</p>'
        . '<p><a class="bouton" href="' . e(chemin_pupitre() . '/index.php') . '">Retour</a></p></div>';
    require RACINE . '/gabarits/entete.php';
    echo $contenu;
    require RACINE . '/gabarits/pied.php';
    exit;
}

function ouvrir_session(array $c): void
{
    session_regenerate_id(true);
    $_SESSION['compte_id'] = (int) $c['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    bd_modifier('comptes', ['derniere_connexion' => date('Y-m-d H:i:s')], ['id' => $c['id']]);
}

function fermer_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
    }
}

// ---------- Jeton anti-CSRF : sur chaque formulaire et chaque action ----------

function jeton_csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jeton_csrf()) . '">';
}

function verifier_csrf(): void
{
    $recu = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($recu) || $recu === '' || !hash_equals(jeton_csrf(), $recu)) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/')) repondre_json(['erreur' => 'Session expirée : rechargez la page.'], 403);
        http_response_code(403);
        exit('Formulaire expiré. Revenez à la page précédente, rechargez-la et recommencez.');
    }
}

// ---------- Tentatives de connexion ----------

function adresse_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45);
}

function trop_de_tentatives(string $email): bool
{
    $depuis = date('Y-m-d H:i:s', time() - TENTATIVES_FENETRE);
    $n = (int) bd_valeur('SELECT COUNT(*) FROM tentatives_connexion WHERE reussie = 0 AND cree_le > ? AND (email = ? OR ip = ?)',
        [$depuis, $email, adresse_ip()]);
    return $n >= TENTATIVES_MAX;
}

function noter_tentative(string $email, bool $reussie): void
{
    bd_inserer('tentatives_connexion', ['email' => mb_substr($email, 0, 190), 'ip' => adresse_ip(), 'reussie' => $reussie ? 1 : 0]);
    if ($reussie) bd_requete('DELETE FROM tentatives_connexion WHERE email = ? AND reussie = 0', [$email]);
}

/** Connexion par adresse et mot de passe (comptes de l'agence). Rend le compte, ou null. */
function connecter_par_mot_de_passe(string $email, string $mot_de_passe): ?array
{
    $c = bd_ligne('SELECT * FROM comptes WHERE email = ? AND actif = 1', [$email]);
    // même durée de calcul que le compte existe ou non : sans compte, on vérifie contre une
    // empreinte factice (celle d'une chaîne aléatoire jetée aussitôt, qui n'ouvre rien)
    $empreinte = $c['mot_de_passe'] ?? '$2y$10$MPhCXIOhXevvVattNqMWduhlOwjYu6X0qTeXZ21WVC9/q4mJss3KK';
    $bon = password_verify($mot_de_passe, (string) $empreinte);
    if (!$c || !$bon || $c['role'] === 'artiste' || empty($c['mot_de_passe'])) {
        noter_tentative($email, false);
        return null;
    }
    if (password_needs_rehash($c['mot_de_passe'], PASSWORD_DEFAULT)) {
        bd_modifier('comptes', ['mot_de_passe' => password_hash($mot_de_passe, PASSWORD_DEFAULT)], ['id' => $c['id']]);
    }
    noter_tentative($email, true);
    ouvrir_session($c);
    return $c;
}

// ---------- Liens à usage unique ----------

/** Crée un lien pour un compte. Seule l'empreinte du jeton est gardée en base. Rend l'adresse. */
function lien_creer(int $compte_id, string $objet): string
{
    $jeton = bin2hex(random_bytes(32));
    bd_requete('DELETE FROM liens_connexion WHERE compte_id = ? AND (utilise_le IS NOT NULL OR expire_le < NOW())', [$compte_id]);
    bd_inserer('liens_connexion', [
        'compte_id' => $compte_id,
        'jeton' => hash('sha256', $jeton),
        'objet' => $objet,
        'expire_le' => date('Y-m-d H:i:s', time() + DUREE_LIEN),
    ]);
    return adresse_pupitre() . '/connexion.php?jeton=' . $jeton;
}

/** Le lien encore valable qui correspond au jeton, avec son compte ; null sinon. */
function lien_lire(string $jeton): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) return null;
    return bd_ligne('SELECT l.id AS lien_id, l.objet, c.* FROM liens_connexion l JOIN comptes c ON c.id = l.compte_id
                     WHERE l.jeton = ? AND l.utilise_le IS NULL AND l.expire_le > NOW() AND c.actif = 1', [hash('sha256', $jeton)]);
}

function lien_consommer(int $lien_id): void
{
    bd_modifier('liens_connexion', ['utilise_le' => date('Y-m-d H:i:s')], ['id' => $lien_id]);
}

/**
 * Envoie à un compte le lien qui lui convient : connexion pour l'artiste, choix du mot de passe
 * pour l'agence. Ne dit jamais à l'appelant si l'adresse est connue.
 */
function lien_envoyer(string $email): void
{
    $c = bd_ligne('SELECT * FROM comptes WHERE email = ? AND actif = 1', [$email]);
    if (!$c) return;
    // au plus trois liens par quart d'heure et par compte
    $recents = (int) bd_valeur('SELECT COUNT(*) FROM liens_connexion WHERE compte_id = ? AND cree_le > ?', [$c['id'], date('Y-m-d H:i:s', time() - 900)]);
    if ($recents >= 3) return;
    $artiste = ($c['role'] === 'artiste');
    $lien = lien_creer((int) $c['id'], $artiste ? 'connexion' : 'mot_de_passe');
    $nom = reglage('sous_titre');
    $sujet = $artiste ? 'Votre lien de connexion à Pupitre' : 'Pupitre : choisir votre mot de passe';
    $texte = "Bonjour " . $c['nom'] . ",\n\n"
        . ($artiste ? "Voici votre lien de connexion à Pupitre" : "Voici le lien pour choisir votre mot de passe Pupitre")
        . ($nom !== '' ? " ($nom)" : '') . " :\n\n$lien\n\n"
        . "Il est valable quinze minutes et ne sert qu'une fois.\nSi vous n'avez rien demandé, ignorez ce message.\n";
    envoyer_mail($c['email'], $c['nom'], $sujet, $texte);
}
