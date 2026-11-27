<?php
/**
 * Migration 001 : langues, traductions, libellés de l'interface du site.
 *
 * Avant : deux langues écrites en dur (réglage « fr,en »), une colonne par langue (titre_fr,
 * titre_en) et une centaine de libellés d'interface écrits dans js/contenu.js.
 * Après : une table `langues`, une table `traductions` (une ligne par objet, champ et langue, avec
 * son statut) et une table `libelles`. Les colonnes « _en » ne sont ni supprimées ni vidées : leur
 * contenu est copié dans `traductions`, puis Pupitre les tient à jour (copie de ce qui sort sur le
 * site). Défaire cette migration ne perd donc rien.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Langues, traductions et libellés de l\'interface',

    'monter' => function () {
        $pdo = bd();
        $pdo->exec("CREATE TABLE IF NOT EXISTS langues (
            code       VARCHAR(8)   NOT NULL PRIMARY KEY COMMENT 'fr, en, de…',
            nom        VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'nom de la langue dans cette langue : Français, English',
            nom_fr     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'nom de la langue pour Pupitre',
            locale     VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'pour les dates du site : fr-FR, en-GB',
            active     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'proposée sur le site',
            reference  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'langue de rédaction des contenus : une seule',
            defaut     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'langue du site à la première visite : une seule',
            ordre      INT          NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS traductions (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            objet       VARCHAR(40)  NOT NULL COMMENT 'table de l''objet traduit',
            objet_id    VARCHAR(80)  NOT NULL COMMENT 'clé de la ligne : identifiant, ou clé texte',
            champ       VARCHAR(60)  NOT NULL,
            langue      VARCHAR(8)   NOT NULL,
            texte       MEDIUMTEXT   NULL,
            statut      ENUM('validee','proposee') NOT NULL DEFAULT 'validee' COMMENT 'proposee : proposée par l''IA, ne sort pas sur le site',
            origine     ENUM('main','ia','reprise') NOT NULL DEFAULT 'main',
            empreinte   CHAR(32)     NOT NULL DEFAULT '' COMMENT 'MD5 du texte de référence au moment de la traduction : s''il a changé, la traduction est à revoir',
            modifie_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY objet (objet, objet_id, champ, langue),
            KEY langue (langue, statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS libelles (
            cle       VARCHAR(80)  NOT NULL PRIMARY KEY COMMENT 'clé du libellé dans le site : nav.agenda',
            texte_fr  TEXT         NULL COMMENT 'texte dans la langue de référence',
            groupe    VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'partie du site : nav, agenda, presse…',
            ordre     INT          NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $GLOBALS['colonnes_perimees'] = $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = true;

        // --- Les langues : celles du réglage d'avant, la première est la langue de référence et, pour l'instant, celle du site
        if (!bd_valeur('SELECT COUNT(*) FROM langues')) {
            $connues = ['fr' => ['Français', 'Français', 'fr-FR'], 'en' => ['English', 'Anglais', 'en-GB']];
            $codes = array_values(array_filter(array_map('trim', explode(',', reglage('langues') ?: 'fr,en')))) ?: ['fr', 'en'];
            foreach ($codes as $i => $code) {
                [$nom, $nom_fr, $locale] = $connues[$code] ?? [strtoupper($code), strtoupper($code), $code];
                bd_requete('INSERT INTO langues (code, nom, nom_fr, locale, active, reference, defaut, ordre) VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
                    [$code, $nom, $nom_fr, $locale, $i === 0 ? 1 : 0, $i === 0 ? 1 : 0, $i]);
            }
            $GLOBALS['langues_perimees'] = true;
        }
        $reference = langue_reference();

        // --- Les traductions : copie des colonnes « _en » (et de toute autre langue qui aurait sa colonne)
        $copier = $pdo->prepare("INSERT IGNORE INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, ?, ?, 'validee', 'reprise', ?)");
        foreach (TRADUISIBLES as $table => [$nom, $cle, $col_nom, $champs]) {
            if ($table === 'libelles' || !bd_table_existe($table)) continue;
            $colonnes = bd_colonnes($table);
            foreach (bd_lignes('SELECT * FROM ' . bd_nom($table)) as $ligne) {
                foreach ($champs as $champ => $_) {
                    $ref = (string) ($ligne[colonne_langue($table, $champ, $reference)] ?? '');
                    foreach (langues_toutes() as $code => $x) {
                        if ($code === $reference) continue;
                        $col = colonne_langue($table, $champ, $code);
                        if (!in_array($col, $colonnes, true)) continue;
                        $texte = trim((string) ($ligne[$col] ?? ''));
                        if ($texte === '') continue;
                        $copier->execute([$table, (string) $ligne[$cle], $champ, $code, $texte, md5($ref)]);
                    }
                }
            }
        }

        // --- Les libellés de l'interface du site, tels qu'ils étaient dans js/contenu.js
        if (!bd_valeur('SELECT COUNT(*) FROM libelles')) {
            $ordre = 0;
            foreach (require __DIR__ . '/donnees/libelles-001.php' as $cle_libelle => [$fr, $en]) {
                bd_requete('INSERT INTO libelles (cle, texte_fr, groupe, ordre) VALUES (?, ?, ?, ?)', [$cle_libelle, $fr, explode('.', $cle_libelle)[0], $ordre++]);
                if ($en !== '') $copier->execute(['libelles', $cle_libelle, 'texte', 'en', $en, md5($fr)]);
            }
        }
        $GLOBALS['traductions_perimees'] = true;
    },

    'descendre' => function () {
        // les colonnes « _en » des tables ont été tenues à jour : rien n'est perdu
        bd_table_retirer('libelles');
        bd_table_retirer('traductions');
        bd_table_retirer('langues');
        $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = true;
    },
];
