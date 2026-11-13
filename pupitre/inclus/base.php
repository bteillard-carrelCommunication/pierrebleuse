<?php
/**
 * Base de données : une connexion PDO, des requêtes préparées, quelques raccourcis.
 * Toutes les requêtes de l'application passent par ce fichier.
 */

defined('PUPITRE') or exit;

class ErreurBase extends RuntimeException {}

/** La connexion, ouverte à la première demande. */
function bd(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $nom = env('BD_NOM');
    if ($nom === '' || env('BD_UTILISATEUR') === '') {
        throw new ErreurBase('La base n\'est pas configurée : renseignez BD_NOM, BD_UTILISATEUR et BD_MOT_DE_PASSE dans pupitre/.env.');
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('BD_HOTE', '127.0.0.1'), env('BD_PORT', '3306'), $nom);
    try {
        $pdo = new PDO($dsn, env('BD_UTILISATEUR'), env('BD_MOT_DE_PASSE'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $e) {
        // le message de PDO peut nommer l'hôte et l'utilisateur : il ne sort pas d'ici
        throw new ErreurBase('Connexion à la base impossible. Vérifiez les variables BD_ de pupitre/.env et que le serveur MySQL tourne.');
    }
    return $pdo;
}

function bd_requete(string $sql, array $params = []): PDOStatement
{
    $r = bd()->prepare($sql);
    $r->execute($params);
    return $r;
}

/** Toutes les lignes. */
function bd_lignes(string $sql, array $params = []): array
{
    return bd_requete($sql, $params)->fetchAll();
}

/** La première ligne, ou null. */
function bd_ligne(string $sql, array $params = []): ?array
{
    $l = bd_requete($sql, $params)->fetch();
    return $l === false ? null : $l;
}

/** La première colonne de la première ligne. */
function bd_valeur(string $sql, array $params = [])
{
    $v = bd_requete($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** La première colonne de chaque ligne. */
function bd_colonne(string $sql, array $params = []): array
{
    return bd_requete($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

function bd_nom(string $nom): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $nom)) throw new ErreurBase('Nom de table ou de colonne refusé.');
    return '`' . $nom . '`';
}

/** INSERT : rend l'identifiant créé. */
function bd_inserer(string $table, array $donnees): int
{
    $colonnes = array_keys($donnees);
    $sql = 'INSERT INTO ' . bd_nom($table) . ' (' . implode(', ', array_map('bd_nom', $colonnes)) . ') VALUES ('
        . implode(', ', array_fill(0, count($colonnes), '?')) . ')';
    bd_requete($sql, array_values($donnees));
    return (int) bd()->lastInsertId();
}

/** UPDATE … WHERE (égalités) : rend le nombre de lignes touchées. */
function bd_modifier(string $table, array $donnees, array $ou): int
{
    if (!$donnees) return 0;
    $sql = 'UPDATE ' . bd_nom($table) . ' SET ' . implode(', ', array_map(fn($c) => bd_nom($c) . ' = ?', array_keys($donnees)))
        . ' WHERE ' . implode(' AND ', array_map(fn($c) => bd_nom($c) . ' = ?', array_keys($ou)));
    return bd_requete($sql, array_merge(array_values($donnees), array_values($ou)))->rowCount();
}

/** DELETE … WHERE (égalités). */
function bd_supprimer(string $table, array $ou): int
{
    $sql = 'DELETE FROM ' . bd_nom($table) . ' WHERE ' . implode(' AND ', array_map(fn($c) => bd_nom($c) . ' = ?', array_keys($ou)));
    return bd_requete($sql, array_values($ou))->rowCount();
}

/** Exécute une fonction dans une transaction : tout, ou rien. */
function bd_transaction(callable $f)
{
    $pdo = bd();
    if ($pdo->inTransaction()) return $f();
    $pdo->beginTransaction();
    try {
        $r = $f();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
