<?php
/**
 * Migration 005 : concerts (publication, description, plusieurs orchestres) et orchestres (carte du monde).
 *
 * Concerts : un concert se publie ou se dépublie sans être supprimé (publie) ; il gagne une
 * description ; il peut être associé à plusieurs orchestres (concerts_orchestres), l'ensemble déjà
 * relié y entre. Les statuts par représentation (connue, embargo, annoncée, publiée, annulée) ne
 * changent pas.
 *
 * Orchestres : un orchestre est une fiche de l'annuaire (nom officiel, autres noms, ville de
 * référence, pays, coordonnées, site, notes), qui gagne la date de première collaboration. La carte
 * du monde est la liste administrée « orchestre + ville » qui existait déjà (parcours_orchestres) :
 * chaque ligne gagne un statut d'affichage et un statut de vérification. Les 24 lignes d'aujourd'hui
 * sont gardées, affichées, avec leurs coordonnées, et marquées « à confirmer ».
 *
 * Une seule ligne change, à la demande de Benjamin Teillard (27/11/2026) : un orchestre se place
 * dans sa ville, pas dans celle du concert. Le Tokyo Symphony Orchestra, placé à Kawasaki, passe
 * à Tokyo, aux coordonnées que la carte emploie déjà pour Tokyo. L'ancienne position est gardée
 * dans les réglages (migration_005_tokyo) : défaire la migration la remet.
 *
 * La fiche d'un orchestre qui n'a qu'une ligne sur la carte reçoit cette ville et ces coordonnées
 * comme ville de référence, si elle n'en avait pas.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Concerts : publication, description, orchestres ; carte du monde : statuts',

    'monter' => function () {
        // --- concerts
        bd_colonne_ajouter('concerts', 'publie', "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 : dépublié, aucune de ses dates ne sort sur le site' AFTER temps_fort");
        bd_colonne_ajouter('concerts', 'description_fr', "TEXT NULL AFTER mentions_en");
        bd()->exec("CREATE TABLE IF NOT EXISTS concerts_orchestres (
            concert_id  INT UNSIGNED NOT NULL,
            fiche_id    INT UNSIGNED NOT NULL COMMENT 'annuaire : orchestre ou ensemble',
            ordre       INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (concert_id, fiche_id),
            KEY fiche_id (fiche_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bd()->exec('INSERT IGNORE INTO concerts_orchestres (concert_id, fiche_id, ordre) SELECT id, ensemble_id, 0 FROM concerts WHERE ensemble_id IS NOT NULL');

        // --- orchestres : fiche de l'annuaire
        bd_colonne_ajouter('annuaire', 'premiere_collaboration', "VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ' AFTER site");
        bd_colonne_ajouter('annuaire', 'verifie', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ville et coordonnées de référence vérifiées' AFTER longitude");

        // --- carte du monde : statuts de chaque ligne « orchestre + ville »
        $neuve = bd_colonne_ajouter('parcours_orchestres', 'affiche', "TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'affichée sur la carte du site' AFTER zone");
        bd_colonne_ajouter('parcours_orchestres', 'verifie', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'position vérifiée ; 0 : à confirmer' AFTER affiche");
        bd_colonne_ajouter('parcours_orchestres', 'origine', "VARCHAR(20) NOT NULL DEFAULT 'reprise' COMMENT 'reprise, main, concert, ia' AFTER verifie");

        if ($neuve) {
            // le Tokyo Symphony Orchestra : de Kawasaki à Tokyo, aux coordonnées déjà employées pour Tokyo
            $ligne = bd_ligne("SELECT p.* FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE a.nom = 'Tokyo Symphony Orchestra' AND p.ville_fr = 'Kawasaki'");
            $tokyo = bd_ligne("SELECT latitude, longitude FROM parcours_orchestres WHERE ville_fr = 'Tokyo' AND latitude IS NOT NULL LIMIT 1");
            if ($ligne && $tokyo) {
                reglage_ecrire('migration_005_tokyo', json_encode(['id' => (int) $ligne['id'], 'ville_fr' => $ligne['ville_fr'], 'ville_en' => $ligne['ville_en'], 'latitude' => $ligne['latitude'], 'longitude' => $ligne['longitude']]));
                bd_requete("UPDATE parcours_orchestres SET ville_fr = 'Tokyo', ville_en = '', latitude = ?, longitude = ? WHERE id = ?", [$tokyo['latitude'], $tokyo['longitude'], $ligne['id']]);
                bd_requete("DELETE FROM traductions WHERE objet = 'parcours_orchestres' AND objet_id = ? AND champ = 'ville'", [(string) $ligne['id']]);
            }
            // ville de référence des fiches : celle de leur unique ligne sur la carte, à confirmer
            foreach (bd_lignes('SELECT fiche_id, COUNT(*) n, MIN(id) id FROM parcours_orchestres GROUP BY fiche_id HAVING n = 1') as $x) {
                $l = bd_ligne('SELECT * FROM parcours_orchestres WHERE id = ?', [$x['id']]);
                $f = bd_ligne('SELECT * FROM annuaire WHERE id = ?', [$x['fiche_id']]);
                if (!$l || !$f || $f['ville_fr'] !== '' || $f['latitude'] !== null) continue;
                bd_requete('UPDATE annuaire SET ville_fr = ?, ville_en = ?, latitude = ?, longitude = ? WHERE id = ?', [$l['ville_fr'], $l['ville_en'], $l['latitude'], $l['longitude'], $f['id']]);
                bd_requete("INSERT IGNORE INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte)
                            SELECT 'annuaire', ?, 'ville', langue, texte, statut, 'reprise', empreinte FROM traductions WHERE objet = 'parcours_orchestres' AND objet_id = ? AND champ = 'ville'", [(string) $f['id'], (string) $l['id']]);
            }
        }

        // --- libellés : titre de l'agenda, fiche du concert, revue de presse d'un concert
        foreach (['fr' => ['Agenda', 'Les prochains concerts'], 'en' => ['Concerts', 'Upcoming concerts']] as $langue => [$avant, $apres]) {
            if ($langue === langue_reference()) {
                bd_requete("UPDATE libelles SET texte_fr = ? WHERE cle = 'agenda.titre' AND texte_fr = ?", [$apres, $avant]);
            } else {
                bd_requete("UPDATE traductions SET texte = ?, empreinte = ? WHERE objet = 'libelles' AND objet_id = 'agenda.titre' AND champ = 'texte' AND langue = ? AND texte = ?", [$apres, md5('Les prochains concerts'), $langue, $avant]);
            }
        }
        libelle_creer('concerts.detail', 'Détail du concert', ['en' => 'Concert details']);
        libelle_creer('concerts.fermer', 'Refermer', ['en' => 'Close']);
        libelle_creer('concerts.presse', 'Revue de presse de ce concert', ['en' => 'Press reviews']);
        libelle_creer('concerts.description', 'Présentation', ['en' => 'About']);
        $GLOBALS['traductions_perimees'] = $GLOBALS['libelles_perimes'] = $GLOBALS['annuaire_perime'] = true;
    },

    'descendre' => function () {
        $tokyo = json_decode(reglage('migration_005_tokyo'), true);
        if (is_array($tokyo) && !empty($tokyo['id'])) {
            bd_requete('UPDATE parcours_orchestres SET ville_fr = ?, ville_en = ?, latitude = ?, longitude = ? WHERE id = ?', [$tokyo['ville_fr'], $tokyo['ville_en'], $tokyo['latitude'], $tokyo['longitude'], $tokyo['id']]);
            bd_requete("DELETE FROM reglages WHERE cle = 'migration_005_tokyo'");
        }
        foreach (['fr' => ['Les prochains concerts', 'Agenda'], 'en' => ['Upcoming concerts', 'Concerts']] as $langue => [$avant, $apres]) {
            if ($langue === langue_reference()) bd_requete("UPDATE libelles SET texte_fr = ? WHERE cle = 'agenda.titre' AND texte_fr = ?", [$apres, $avant]);
            else bd_requete("UPDATE traductions SET texte = ?, empreinte = ? WHERE objet = 'libelles' AND objet_id = 'agenda.titre' AND langue = ? AND texte = ?", [$apres, md5('Agenda'), $langue, $avant]);
        }
        foreach (['concerts.detail', 'concerts.fermer', 'concerts.presse', 'concerts.description'] as $cle) {
            bd_requete("DELETE FROM traductions WHERE objet = 'libelles' AND objet_id = ?", [$cle]);
            bd_requete('DELETE FROM libelles WHERE cle = ?', [$cle]);
        }
        foreach (['origine', 'verifie', 'affiche'] as $c) bd_colonne_retirer('parcours_orchestres', $c);
        bd_colonne_retirer('annuaire', 'verifie');
        bd_colonne_retirer('annuaire', 'premiere_collaboration');
        bd_table_retirer('concerts_orchestres');
        bd_requete("DELETE FROM traductions WHERE objet = 'concerts' AND champ = 'description'");
        bd_colonne_retirer('concerts', 'description_fr');
        bd_colonne_retirer('concerts', 'publie');
        $GLOBALS['traductions_perimees'] = $GLOBALS['libelles_perimes'] = $GLOBALS['reglages_perimes'] = true;
    },
];
