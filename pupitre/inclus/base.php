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

/**
 * INSERT : rend l'identifiant créé. Les champs traduisibles écrits dans une autre langue que la
 * langue de référence (titre_en, titre_de…) partent d'eux-mêmes dans la table des traductions
 * (inclus/langues.php).
 */
function bd_inserer(string $table, array $donnees): int
{
    [$donnees, $traductions] = traductions_separer($table, $donnees);
    $colonnes = array_keys($donnees);
    $sql = 'INSERT INTO ' . bd_nom($table) . ' (' . implode(', ', array_map('bd_nom', $colonnes)) . ') VALUES ('
        . implode(', ', array_fill(0, count($colonnes), '?')) . ')';
    bd_requete($sql, array_values($donnees));
    $id = (int) bd()->lastInsertId();
    if ($traductions) {
        $cle = cle_primaire($table);
        traductions_poser($table, (string) ($cle === 'id' ? $id : ($donnees[$cle] ?? '')), $traductions);
    }
    return $id;
}

/** UPDATE … WHERE (égalités) : rend le nombre de lignes touchées. Mêmes règles de langue que bd_inserer(). */
function bd_modifier(string $table, array $donnees, array $ou): int
{
    [$donnees, $traductions] = traductions_separer($table, $donnees);
    $n = 0;
    if ($donnees) {
        $sql = 'UPDATE ' . bd_nom($table) . ' SET ' . implode(', ', array_map(fn($c) => bd_nom($c) . ' = ?', array_keys($donnees)))
            . ' WHERE ' . implode(' AND ', array_map(fn($c) => bd_nom($c) . ' = ?', array_keys($ou)));
        $n = bd_requete($sql, array_merge(array_values($donnees), array_values($ou)))->rowCount();
    }
    if ($traductions) {
        $cle = cle_primaire($table);
        if (!array_key_exists($cle, $ou)) throw new ErreurBase('Une traduction ne s\'écrit que sur une ligne désignée par sa clé.');
        traductions_poser($table, (string) $ou[$cle], $traductions);
    }
    return $n;
}

/** DELETE … WHERE (égalités). Les lignes supprimées emportent leurs traductions. */
function bd_supprimer(string $table, array $ou): int
{
    $condition = implode(' AND ', array_map(fn($c) => bd_nom($c) . ' = ?', array_keys($ou)));
    $cles = [];
    if (isset(TRADUISIBLES[$table]) && langues_installees()) {
        $cles = bd_colonne('SELECT ' . bd_nom(cle_primaire($table)) . ' FROM ' . bd_nom($table) . ' WHERE ' . $condition, array_values($ou));
    }
    $n = bd_requete('DELETE FROM ' . bd_nom($table) . ' WHERE ' . $condition, array_values($ou))->rowCount();
    foreach ($cles as $cle) traductions_retirer($table, (string) $cle);
    return $n;
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
