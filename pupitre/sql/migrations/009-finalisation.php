<?php
/**
 * Migration 009 : finalisation.
 *
 * Langue : l'anglais devient la langue de la première visite (le français reste la langue de
 * référence, celle dans laquelle les contenus sont rédigés, et reste proposé sur le site).
 * L'ancienne langue par défaut est gardée dans les réglages (migration_009_defaut).
 *
 * Téléchargements : chaque ressource gagne un type, une description facultative, la langue du
 * document, une adresse (fichier hébergé ailleurs), des crédits, des conditions d'utilisation et
 * un statut de publication. Les quatre lignes existantes sont gardées, publiées, et reçoivent
 * leur type et leur langue.
 *
 * « Restons en contact » : le bloc de texte passe à gauche (feuille de style du site) ; la
 * photographie est cadrée plus haut (64 % / 28 % au lieu de 64 % / 50 %), pour que le visage reste
 * dans l'image sur les écrans très larges. L'ancien cadrage est gardé (migration_009_cadrage).
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Finalisation : anglais par défaut, téléchargements, cadrage de « Restons en contact »',

    'monter' => function () {
        // --- langue de la première visite
        if ((int) bd_valeur("SELECT COUNT(*) FROM langues WHERE code = 'en'")) {
            reglage_ecrire('migration_009_defaut', (string) bd_valeur('SELECT code FROM langues WHERE defaut = 1 LIMIT 1'));
            bd()->exec("UPDATE langues SET active = 1 WHERE code = 'en'");
            bd()->exec("UPDATE langues SET defaut = (code = 'en')");
        }

        // --- téléchargements
        bd_colonne_ajouter('telechargements', 'type', "VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'biographie, photographie, document, autre' AFTER cle");
        bd_colonne_ajouter('telechargements', 'description_fr', "TEXT NULL AFTER libelle_en");
        bd_colonne_ajouter('telechargements', 'langue', "VARCHAR(3) NOT NULL DEFAULT '' COMMENT 'langue du document, vide : sans objet' AFTER description_fr");
        bd_colonne_ajouter('telechargements', 'url', "VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'fichier hébergé ailleurs' AFTER media_id");
        bd_colonne_ajouter('telechargements', 'credit', "VARCHAR(255) NOT NULL DEFAULT '' AFTER url");
        bd_colonne_ajouter('telechargements', 'conditions_fr', "TEXT NULL AFTER credit");
        bd_colonne_ajouter('telechargements', 'publie', "TINYINT(1) NOT NULL DEFAULT 1 AFTER conditions_fr");
        foreach (['bio-fr' => ['biographie', 'fr'], 'bio-en' => ['biographie', 'en'], 'photos' => ['photographie', ''], 'identite' => ['document', '']] as $cle => [$type, $langue]) {
            bd_requete("UPDATE telechargements SET type = ?, langue = ? WHERE cle = ? AND type = ''", [$type, $langue, $cle]);
        }

        // --- cadrage de la photographie de « Restons en contact »
        $e = bd_ligne("SELECT id, parametres FROM medias_usages WHERE objet = 'blocs' AND objet_id = 'suivre' AND role = 'element' AND nature = 'image' ORDER BY ordre, id LIMIT 1");
        $p = $e ? (json_decode((string) $e['parametres'], true) ?: []) : [];
        if ($e && ($p['position'] ?? null) === [64, 50]) {
            reglage_ecrire('migration_009_cadrage', json_encode(['id' => (int) $e['id'], 'position' => $p['position']]));
            $p['position'] = [64, 28];
            bd_modifier('medias_usages', ['parametres' => json_encode($p)], ['id' => $e['id']]);
        }
    },

    'descendre' => function () {
        $c = json_decode((string) reglage('migration_009_cadrage'), true);
        if (is_array($c) && ($e = bd_ligne('SELECT id, parametres FROM medias_usages WHERE id = ?', [$c['id']]))) {
            $p = json_decode((string) $e['parametres'], true) ?: [];
            if (($p['position'] ?? null) === [64, 28]) { $p['position'] = $c['position']; bd_modifier('medias_usages', ['parametres' => json_encode($p)], ['id' => $e['id']]); }
        }
        bd_requete("DELETE FROM reglages WHERE cle IN ('migration_009_cadrage')");

        bd_requete("DELETE FROM traductions WHERE objet = 'telechargements' AND champ IN ('description', 'conditions')");
        foreach (['publie', 'conditions_fr', 'credit', 'url', 'langue', 'description_fr', 'type'] as $col) bd_colonne_retirer('telechargements', $col);

        $avant = (string) reglage('migration_009_defaut');
        if ($avant !== '' && (int) bd_valeur('SELECT COUNT(*) FROM langues WHERE code = ?', [$avant])) {
            bd_requete('UPDATE langues SET defaut = (code = ?)', [$avant]);
        }
        bd_requete("DELETE FROM reglages WHERE cle = 'migration_009_defaut'");
    },
];
