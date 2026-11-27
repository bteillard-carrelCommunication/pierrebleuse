<?php
/**
 * Migrations de la base : des étapes numérotées, rejouables et réversibles.
 *
 * Une migration est un fichier de sql/migrations/, nommé NNN-sujet.php, qui rend :
 *     ['titre' => '…', 'monter' => function () {…}, 'descendre' => function () {…}]
 * « monter » fait évoluer la base sans rien perdre ; « descendre » la ramène à l'état précédent.
 * Chaque migration vérifie ce qui existe avant d'agir (bd_table_existe, bd_colonne_existe) : la
 * rejouer ne casse rien. La table `migrations` retient celles qui sont passées.
 *
 * Avant de monter ou de descendre quoi que ce soit, la base entière est sauvegardée dans
 * stockage/sauvegardes/ (inclus/sauvegarde.php).
 *
 *     php pupitre/sql/migrer.php            passe les migrations en attente
 *     php pupitre/sql/migrer.php --etat     dit où en est la base
 *     php pupitre/sql/migrer.php --defaire  défait la dernière migration passée
 */

defined('PUPITRE') or exit;

function bd_table_existe(string $table): bool
{
    return (bool) bd_valeur('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
}

function bd_colonne_existe(string $table, string $colonne): bool
{
    return (bool) bd_valeur('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $colonne]);
}

function bd_index_existe(string $table, string $index): bool
{
    return (bool) bd_valeur('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$table, $index]);
}

/** Ajoute une colonne si elle manque. $definition : « VARCHAR(40) NOT NULL DEFAULT '' AFTER titre ». */
function bd_colonne_ajouter(string $table, string $colonne, string $definition): bool
{
    if (bd_colonne_existe($table, $colonne)) return false;
    bd()->exec('ALTER TABLE ' . bd_nom($table) . ' ADD COLUMN ' . bd_nom($colonne) . ' ' . $definition);
    $GLOBALS['colonnes_perimees'] = true;
    return true;
}

function bd_colonne_retirer(string $table, string $colonne): bool
{
    if (!bd_colonne_existe($table, $colonne)) return false;
    bd()->exec('ALTER TABLE ' . bd_nom($table) . ' DROP COLUMN ' . bd_nom($colonne));
    $GLOBALS['colonnes_perimees'] = true;
    return true;
}

function bd_table_retirer(string $table): void
{
    bd()->exec('DROP TABLE IF EXISTS ' . bd_nom($table));
    $GLOBALS['colonnes_perimees'] = true;
}

/** Les migrations livrées avec le code : nom => chemin, dans l'ordre. */
function migrations_disponibles(): array
{
    $m = [];
    foreach (glob(RACINE . '/sql/migrations/[0-9]*.php') ?: [] as $f) $m[basename($f, '.php')] = $f;
    ksort($m);
    return $m;
}

function migrations_table(): void
{
    bd()->exec("CREATE TABLE IF NOT EXISTS migrations (
        nom        VARCHAR(120) NOT NULL PRIMARY KEY,
        titre      VARCHAR(255) NOT NULL DEFAULT '',
        passee_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Noms des migrations déjà passées. */
function migrations_passees(): array
{
    try {
        return bd_colonne('SELECT nom FROM migrations ORDER BY nom');
    } catch (Throwable $e) {
        return [];
    }
}

/** Les migrations qui restent à passer : nom => chemin. */
function migrations_en_attente(): array
{
    return array_diff_key(migrations_disponibles(), array_flip(migrations_passees()));
}

/**
 * Passe les migrations en attente. $dire reçoit les messages. $sauvegarder : faux pour une base
 * neuve (départ), où il n'y a rien à garder. Rend les noms des migrations passées.
 */
function migrer(?callable $dire = null, bool $sauvegarder = true): array
{
    $dire = $dire ?? fn(string $s) => null;
    migrations_table();
    $attente = migrations_en_attente();
    if (!$attente) return [];
    if ($sauvegarder) $dire('Sauvegarde de la base : ' . basename(base_sauvegarder('avant-' . array_key_first($attente))));
    $faites = [];
    foreach ($attente as $nom => $chemin) {
        $m = require $chemin;
        if (!is_array($m) || !is_callable($m['monter'] ?? null)) throw new RuntimeException('Migration illisible : ' . $nom);
        ($m['monter'])();
        bd_requete('INSERT INTO migrations (nom, titre) VALUES (?, ?) ON DUPLICATE KEY UPDATE titre = VALUES(titre)', [$nom, (string) ($m['titre'] ?? '')]);
        $GLOBALS['colonnes_perimees'] = $GLOBALS['reglages_perimes'] = $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = true;
        langues_installees(true);
        $dire('Migration ' . $nom . ' : ' . ($m['titre'] ?? ''));
        $faites[] = $nom;
    }
    return $faites;
}

/** Défait la dernière migration passée (ou celle qui est nommée, si c'est la dernière). Rend son nom. */
function migration_defaire(?callable $dire = null): ?string
{
    $dire = $dire ?? fn(string $s) => null;
    $passees = migrations_passees();
    if (!$passees) return null;
    $nom = end($passees);
    $chemin = migrations_disponibles()[$nom] ?? null;
    if (!$chemin) throw new RuntimeException('Le fichier de la migration ' . $nom . ' est introuvable.');
    $m = require $chemin;
    if (!is_callable($m['descendre'] ?? null)) throw new RuntimeException('La migration ' . $nom . ' ne sait pas se défaire.');
    $dire('Sauvegarde de la base : ' . basename(base_sauvegarder('avant-retrait-' . $nom)));
    ($m['descendre'])();
    bd_supprimer('migrations', ['nom' => $nom]);
    $GLOBALS['colonnes_perimees'] = $GLOBALS['reglages_perimes'] = $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = true;
    langues_installees(true);
    $dire('Migration défaite : ' . $nom);
    return $nom;
}
