<?php
/**
 * Migration 007 : discographie détaillée, distinctions, fonctions, grands débuts et collaborations.
 *
 * Albums : chaque album gagne un sous-titre, une date de sortie, ses compositeurs, ses œuvres, son
 * orchestre, ses interprètes, une présentation, le contexte artistique, des citations de l'artiste,
 * des informations sur les œuvres, un statut de publication. « Le mot de l'artiste » (mot) reste :
 * ce sont ses notes personnelles, distinctes de la présentation. Les liens « Écouter » et
 * « Commander », écrits jusqu'ici « Nom | adresse » ligne par ligne, ont leur table (disques_liens),
 * avec la plateforme qui choisit le pictogramme ; les anciennes colonnes sont gardées. Les visuels
 * d'un album passent par la médiathèque commune (medias_usages). Aucun album n'est perdu.
 *
 * Distinctions : une collection (distinctions), rattachée à un album. Le Ligeti portait dans son
 * descriptif la ligne « Diapason d'or de l'année, Choc de Classica » : ce sont deux distinctions
 * distinctes, rattachées toutes deux à l'album, mises en avant sur sa vignette comme la ligne
 * l'était ; la ligne quitte le descriptif. Rien d'autre n'est renseigné : ni année, ni adresse.
 *
 * Fonctions : les lignes de texte libre deviennent des fiches (intitulé, institution, début, fin,
 * actuelle ou passée, à la une, publication, lien). Les trois lignes d'aujourd'hui sont reprises ;
 * la première, directeur musical de l'Ensemble intercontemporain, est « à la une ».
 *
 * Grands débuts et collaborations : une collection (collaborations), liée à un concert ou à un
 * orchestre existant, ou saisie à la main. Elle remplace « Opéra et création » sur le site ; les
 * lignes d'opéra et de création restent dans la base (parcours_lignes), consultables dans Pupitre.
 * Trois brouillons NON PUBLIÉS sont préparés pour des orchestres déjà dans les données du site et
 * cités le 20/11/2026 (Royal Concertgebouw Orchestra, London Philharmonic Orchestra, City of
 * Birmingham Symphony Orchestra, ce dernier « à confirmer ») : rien ne paraît tant qu'une personne
 * ne les publie pas.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Discographie détaillée, distinctions, fonctions, grands débuts et collaborations',

    'monter' => function () {
        // --- albums
        bd_colonne_ajouter('disques', 'sous_titre_fr', "VARCHAR(255) NOT NULL DEFAULT '' AFTER titre");
        bd_colonne_ajouter('disques', 'date_sortie', "VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ' AFTER annee");
        bd_colonne_ajouter('disques', 'compositeurs', "TEXT NULL COMMENT 'un par ligne' AFTER maison_id");
        bd_colonne_ajouter('disques', 'oeuvres_fr', "TEXT NULL COMMENT 'une œuvre par ligne' AFTER compositeurs");
        bd_colonne_ajouter('disques', 'ensemble', "VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'orchestre ou ensemble, appellation officielle' AFTER oeuvres_fr");
        bd_colonne_ajouter('disques', 'ensemble_id', "INT UNSIGNED NULL COMMENT 'annuaire' AFTER ensemble");
        bd_colonne_ajouter('disques', 'interpretes_fr', "TEXT NULL COMMENT 'interprètes et collaborateurs, un par ligne' AFTER ensemble_id");
        bd_colonne_ajouter('disques', 'presentation_fr', "TEXT NULL COMMENT 'présentation générale' AFTER descriptif_en");
        bd_colonne_ajouter('disques', 'contexte_fr', "TEXT NULL COMMENT 'contexte artistique de l''enregistrement' AFTER presentation_fr");
        bd_colonne_ajouter('disques', 'citations_fr', "TEXT NULL COMMENT 'citations de l''artiste, fournies ou validées par lui, une par ligne' AFTER mot_en");
        bd_colonne_ajouter('disques', 'infos_fr', "TEXT NULL COMMENT 'informations complémentaires sur les œuvres' AFTER citations_fr");
        $neuve = bd_colonne_ajouter('disques', 'publie', "TINYINT(1) NOT NULL DEFAULT 1 AFTER lien");

        bd()->exec("CREATE TABLE IF NOT EXISTS disques_liens (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            disque_id   INT UNSIGNED NOT NULL,
            nature      ENUM('ecoute', 'commande') NOT NULL,
            plateforme  VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'spotify, apple-music, deezer, youtube, qobuz… : choisit le pictogramme ; vide : lien simple',
            nom         VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'nom affiché : la plateforme, ou le distributeur',
            url         VARCHAR(500) NOT NULL DEFAULT '',
            ordre       INT          NOT NULL DEFAULT 0,
            KEY disque_id (disque_id, nature, ordre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // --- distinctions
        bd()->exec("CREATE TABLE IF NOT EXISTS distinctions (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            disque_id       INT UNSIGNED NULL COMMENT 'album distingué',
            intitule_fr     VARCHAR(255) NOT NULL DEFAULT '',
            organisme       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'organisme ou média qui la décerne',
            annee           VARCHAR(10)  NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
            logo_id         INT UNSIGNED NULL COMMENT 'médiathèque',
            description_fr  TEXT         NULL,
            url             VARCHAR(500) NOT NULL DEFAULT '',
            une             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'mise en avant : montrée dès la vignette de l''album',
            publie          TINYINT(1)   NOT NULL DEFAULT 1,
            ordre           INT          NOT NULL DEFAULT 0,
            KEY disque_id (disque_id, ordre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // --- fonctions, collaborations
        bd()->exec("CREATE TABLE IF NOT EXISTS fonctions (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            intitule_fr     VARCHAR(255) NOT NULL DEFAULT '',
            institution_fr  VARCHAR(255) NOT NULL DEFAULT '',
            institution_id  INT UNSIGNED NULL COMMENT 'annuaire',
            description_fr  TEXT         NULL,
            debut           VARCHAR(10)  NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
            fin             VARCHAR(10)  NOT NULL DEFAULT '',
            actuelle        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 : fonction actuelle ; 0 : fonction passée',
            une             TINYINT(1)   NOT NULL DEFAULT 0,
            lien            VARCHAR(500) NOT NULL DEFAULT '',
            publie          TINYINT(1)   NOT NULL DEFAULT 1,
            ordre           INT          NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bd()->exec("CREATE TABLE IF NOT EXISTS collaborations (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            concert_id      INT UNSIGNED NULL COMMENT 'le concert ou l''événement qu''elle met en avant',
            fiche_id        INT UNSIGNED NULL COMMENT 'annuaire : l''orchestre',
            intitule_fr     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'intitulé éditorial ; vide : le nom de l''orchestre ou le titre du concert',
            description_fr  TEXT         NULL,
            annee           VARCHAR(10)  NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
            une             TINYINT(1)   NOT NULL DEFAULT 0,
            verifiee        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0 : collaboration à confirmer',
            publie          TINYINT(1)   NOT NULL DEFAULT 0,
            ordre           INT          NOT NULL DEFAULT 0,
            KEY concert_id (concert_id),
            KEY fiche_id (fiche_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $tr = bd()->prepare("INSERT IGNORE INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, ?, ?, 'validee', 'reprise', ?)");

        if ($neuve) {
            // liens « Nom | adresse » : une ligne par lien, la plateforme devinée d'après le nom ou l'adresse
            foreach (bd_lignes('SELECT id, commander, streaming FROM disques') as $d) {
                foreach (['ecoute' => 'streaming', 'commande' => 'commander'] as $nature => $colonne) {
                    foreach (liens_lire($d[$colonne]) as $i => $l) {
                        bd_requete('INSERT INTO disques_liens (disque_id, nature, plateforme, nom, url, ordre) VALUES (?, ?, ?, ?, ?, ?)',
                            [$d['id'], $nature, plateforme_deviner($l['nom'], $l['url']), $l['nom'], $l['url'], $i]);
                    }
                }
            }
            // le Ligeti : la ligne « Diapason d'or de l'année, Choc de Classica » devient deux distinctions
            $ligeti = bd_ligne("SELECT * FROM disques WHERE cle = 'ligeti'");
            if ($ligeti && !bd_valeur('SELECT COUNT(*) FROM distinctions')) {
                $fr = lignes($ligeti['descriptif_fr']);
                $en = lignes(traduction_publiee('disques', $ligeti['id'], 'descriptif', 'en') ?: (string) $ligeti['descriptif_en']);
                $rang = null;
                foreach ($fr as $i => $l) if (str_contains($l, 'Diapason d\'or') && str_contains($l, 'Classica')) $rang = $i;
                if ($rang !== null) {
                    $parts_fr = array_map('trim', explode(',', $fr[$rang]));
                    $parts_en = array_map('trim', explode(',', $en[$rang] ?? ''));
                    foreach ($parts_fr as $k => $intitule) {
                        $organisme = str_contains($intitule, 'Diapason') ? 'Diapason' : (str_contains($intitule, 'Classica') ? 'Classica' : '');
                        bd_requete('INSERT INTO distinctions (disque_id, intitule_fr, organisme, une, publie, ordre) VALUES (?, ?, ?, 1, 1, ?)', [$ligeti['id'], $intitule, $organisme, $k]);
                        $id = (int) bd()->lastInsertId();
                        if (($parts_en[$k] ?? '') !== '' && $parts_en[$k] !== $intitule) $tr->execute(['distinctions', (string) $id, 'intitule', 'en', $parts_en[$k], md5($intitule)]);
                    }
                    // la ligne quitte le descriptif, dans chaque langue (elle est gardée dans les réglages, pour défaire)
                    reglage_ecrire('migration_007_ligeti', json_encode(['fr' => $ligeti['descriptif_fr'], 'en' => implode("\n", $en)], JSON_UNESCAPED_UNICODE));
                    unset($fr[$rang], $en[$rang]);
                    bd_requete('UPDATE disques SET descriptif_fr = ?, descriptif_en = ? WHERE id = ?', [implode("\n", $fr), implode("\n", $en), $ligeti['id']]);
                    bd_requete("UPDATE traductions SET texte = ?, empreinte = ? WHERE objet = 'disques' AND objet_id = ? AND champ = 'descriptif' AND langue = 'en'", [implode("\n", $en), md5(implode("\n", $fr)), (string) $ligeti['id']]);
                }
            }
        }

        // fonctions : « Intitulé, institution, depuis AAAA » ou « …, AAAA à AAAA »
        if (!bd_valeur('SELECT COUNT(*) FROM fonctions')) {
            $decouper = function (string $ligne): array {
                $p = array_map('trim', explode(',', $ligne));
                $periode = count($p) > 1 ? (string) end($p) : '';
                $debut = $fin = '';
                $actuelle = 1;
                if (preg_match('/^(?:depuis|since)\s+(\d{4})$/iu', $periode, $m)) { $debut = $m[1]; array_pop($p); }
                elseif (preg_match('/^(\d{4})\s*(?:à|to|–|-)\s*(\d{4})$/iu', $periode, $m)) { $debut = $m[1]; $fin = $m[2]; $actuelle = 0; array_pop($p); }
                $intitule = (string) array_shift($p);
                return [$intitule, implode(', ', $p), $debut, $fin, $actuelle];
            };
            foreach (bd_lignes("SELECT * FROM parcours_lignes WHERE liste = 'fonctions' ORDER BY ordre, id") as $i => $l) {
                [$intitule, $institution, $debut, $fin, $actuelle] = $decouper($l['texte_fr']);
                $institution_id = bd_valeur('SELECT id FROM annuaire WHERE nom = ? LIMIT 1', [$institution]);
                bd_requete('INSERT INTO fonctions (intitule_fr, institution_fr, institution_id, debut, fin, actuelle, une, publie, ordre) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
                    [$intitule, $institution, $institution_id ?: null, $debut, $fin, $actuelle, $i === 0 ? 1 : 0, $i]);
                $id = (int) bd()->lastInsertId();
                $en = traduction_publiee('parcours_lignes', $l['id'], 'texte', 'en') ?: (string) $l['texte_en'];
                if ($en !== '') {
                    [$intitule_en, $institution_en] = $decouper($en);
                    if ($intitule_en !== '' && $intitule_en !== $intitule) $tr->execute(['fonctions', (string) $id, 'intitule', 'en', $intitule_en, md5($intitule)]);
                    if ($institution_en !== '' && $institution_en !== $institution) $tr->execute(['fonctions', (string) $id, 'institution', 'en', $institution_en, md5($institution)]);
                }
            }
        }

        // collaborations : trois brouillons non publiés, pour des orchestres déjà dans les données
        if (!bd_valeur('SELECT COUNT(*) FROM collaborations')) {
            foreach (['Royal Concertgebouw Orchestra' => 1, 'London Philharmonic Orchestra' => 1, 'City of Birmingham Symphony Orchestra' => 0] as $nom => $verifiee) {
                $fiche = bd_valeur("SELECT id FROM annuaire WHERE nom = ? AND categorie IN ('orchestre', 'ensemble')", [$nom]);
                if ($fiche) bd_requete('INSERT INTO collaborations (fiche_id, verifiee, publie, ordre) VALUES (?, ?, 0, ?)', [$fiche, $verifiee, (int) bd_valeur('SELECT COUNT(*) FROM collaborations')]);
            }
        }

        // --- libellés
        libelle_creer('parcours.collaborations', 'Grands débuts et collaborations', ['en' => 'Major debuts and collaborations']);
        libelle_creer('parcours.depuis', 'depuis {annee}', ['en' => 'since {annee}']);
        libelle_creer('parcours.periode', '{debut} à {fin}', ['en' => '{debut} to {fin}']);
        libelle_creer('disques.plus', 'En savoir plus', ['en' => 'Find out more']);
        libelle_creer('disques.presentation', 'Présentation', ['en' => 'About the recording']);
        libelle_creer('disques.contexte', 'Contexte artistique', ['en' => 'Artistic context']);
        libelle_creer('disques.oeuvres', 'Œuvres', ['en' => 'Works']);
        libelle_creer('disques.compositeurs', 'Compositeurs', ['en' => 'Composers']);
        libelle_creer('disques.interpretes', 'Interprètes', ['en' => 'Performers']);
        libelle_creer('disques.infos', 'À propos des œuvres', ['en' => 'About the works']);
        libelle_creer('disques.distinctions', 'Distinctions', ['en' => 'Awards']);
        libelle_creer('disques.critiques', 'La critique', ['en' => 'Reviews']);
        libelle_creer('disques.page', 'Page de l\'album', ['en' => 'Album page']);
        libelle_creer('disques.sortie', 'Sortie', ['en' => 'Released']);
        libelle_creer('disques.image', 'Image {n} sur {total}', ['en' => 'Image {n} of {total}']);
        libelle_creer('disques.image.suivante', 'Image suivante', ['en' => 'Next image']);
        libelle_creer('disques.image.precedente', 'Image précédente', ['en' => 'Previous image']);
        $GLOBALS['traductions_perimees'] = $GLOBALS['libelles_perimes'] = true;
    },

    'descendre' => function () {
        $ligeti = json_decode(reglage('migration_007_ligeti'), true);
        if (is_array($ligeti)) {
            $id = bd_valeur("SELECT id FROM disques WHERE cle = 'ligeti'");
            if ($id) {
                bd_requete('UPDATE disques SET descriptif_fr = ?, descriptif_en = ? WHERE id = ?', [$ligeti['fr'], $ligeti['en'], $id]);
                bd_requete("UPDATE traductions SET texte = ?, empreinte = ? WHERE objet = 'disques' AND objet_id = ? AND champ = 'descriptif' AND langue = 'en'", [$ligeti['en'], md5($ligeti['fr']), (string) $id]);
            }
            bd_requete("DELETE FROM reglages WHERE cle = 'migration_007_ligeti'");
        }
        bd_requete("DELETE FROM traductions WHERE objet IN ('distinctions', 'fonctions', 'collaborations')");
        bd_requete("DELETE FROM traductions WHERE objet = 'disques' AND champ IN ('sous_titre', 'oeuvres', 'interpretes', 'presentation', 'contexte', 'citations', 'infos')");
        bd_requete("DELETE FROM medias_usages WHERE objet = 'disques'");
        foreach (['collaborations', 'fonctions', 'distinctions', 'disques_liens'] as $t) bd_table_retirer($t);
        foreach (['publie', 'infos_fr', 'citations_fr', 'contexte_fr', 'presentation_fr', 'interpretes_fr', 'ensemble_id', 'ensemble', 'oeuvres_fr', 'compositeurs', 'date_sortie', 'sous_titre_fr'] as $c) bd_colonne_retirer('disques', $c);
        foreach (bd_colonne("SELECT cle FROM libelles WHERE cle IN ('parcours.collaborations', 'parcours.depuis', 'parcours.periode') OR cle IN ('disques.plus', 'disques.presentation', 'disques.contexte', 'disques.oeuvres', 'disques.compositeurs', 'disques.interpretes', 'disques.infos', 'disques.distinctions', 'disques.critiques', 'disques.page', 'disques.sortie', 'disques.image', 'disques.image.suivante', 'disques.image.precedente')") as $cle) {
            bd_requete("DELETE FROM traductions WHERE objet = 'libelles' AND objet_id = ?", [$cle]);
            bd_requete('DELETE FROM libelles WHERE cle = ?', [$cle]);
        }
        $GLOBALS['traductions_perimees'] = $GLOBALS['libelles_perimes'] = $GLOBALS['reglages_perimes'] = true;
    },
];
