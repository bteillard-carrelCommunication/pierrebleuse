<?php
/**
 * Migration 006 : la revue de presse devient une collection à part entière.
 *
 * Un article gagne : son type, un chapeau, des informations complémentaires, son adresse canonique,
 * un statut de publication, la sélection pour l'accueil avec son rang et sa largeur (1, 2 ou
 * 4 colonnes), la marque « à la une ». Ses citations ont leur table (articles_citations) : chaque
 * citation est gardée dans sa langue et sa formulation d'origine, avec sa traduction à part ; l'une
 * est la citation principale. Le verbatim d'aujourd'hui y entre tel quel, comme citation principale.
 * Un article s'associe à des concerts (articles_concerts) et à des albums (articles_disques) sans
 * jamais être dupliqué. Ses visuels passent par la médiathèque commune (medias_usages).
 *
 * Le référentiel des médias de presse reste l'annuaire (catégorie « media ») : nom, logo, site,
 * pays ; il gagne la langue principale et un traitement typographique du nom.
 *
 * Valeurs de départ : tous les articles sont publiés ; les quatre premiers sont sur l'accueil, dans
 * leur ordre, sur une colonne chacun : ce que le site montrait.
 *
 * Les huit faux articles de maquette (data/presse-exemples.js), qui masquaient les vrais à l'écran,
 * entrent dans la collection, marqués « exemple » : ils se repèrent d'un coup d'œil et se suppriment
 * d'un geste (écran Revue de presse) avant la mise en production. Ils prennent la place qu'ils
 * avaient : l'accueil, avec des largeurs variées pour juger la nouvelle grille. Leurs médias
 * inventés entrent dans l'annuaire avec la note « EXEMPLE DE MAQUETTE » et partent avec eux.
 */

defined('PUPITRE') or exit;

const MARQUE_EXEMPLE = 'EXEMPLE DE MAQUETTE : média inventé, à supprimer avant la mise en production.';

return [
    'titre' => 'Revue de presse : types, citations, relations, mise en avant, médias',

    'monter' => function () {
        bd_colonne_ajouter('articles', 'type', "VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'critique_concert, critique_album, portrait, interview, actualite, autre' AFTER cle");
        bd_colonne_ajouter('articles', 'chapeau_fr', "TEXT NULL AFTER titre_en");
        bd_colonne_ajouter('articles', 'infos_fr', "TEXT NULL COMMENT 'informations complémentaires' AFTER resume_en");
        bd_colonne_ajouter('articles', 'url_canonique', "VARCHAR(500) NOT NULL DEFAULT '' AFTER lien");
        bd_colonne_ajouter('articles', 'publie', "TINYINT(1) NOT NULL DEFAULT 1 AFTER url_canonique");
        bd_colonne_ajouter('articles', 'une', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'à la une : marque éditoriale, indépendante de la publication' AFTER publie");
        $neuve = bd_colonne_ajouter('articles', 'accueil', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'sélectionné pour la page d''accueil' AFTER une");
        bd_colonne_ajouter('articles', 'ordre_accueil', "INT NOT NULL DEFAULT 0 AFTER accueil");
        bd_colonne_ajouter('articles', 'largeur', "TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'sur l''accueil : 1, 2 ou 4 colonnes' AFTER ordre_accueil");
        bd_colonne_ajouter('articles', 'exemple', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'faux article de maquette, à supprimer avant la mise en production' AFTER largeur");
        bd_colonne_ajouter('articles', 'origine', "VARCHAR(20) NOT NULL DEFAULT 'main' COMMENT 'main, url (importé d''une adresse), texte (texte collé)' AFTER exemple");
        bd_colonne_ajouter('articles', 'texte_source', "MEDIUMTEXT NULL COMMENT 'texte de l''article récupéré ou collé : sert à vérifier les citations, ne sort jamais sur le site' AFTER origine");
        bd_colonne_ajouter('articles', 'cree_le', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ordre");

        bd()->exec("CREATE TABLE IF NOT EXISTS articles_citations (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            article_id     INT UNSIGNED NOT NULL,
            texte          TEXT         NOT NULL COMMENT 'la citation, dans sa langue et sa formulation d''origine, avec ses guillemets',
            langue         VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'langue d''origine de la citation',
            traduction_fr  TEXT         NULL COMMENT 'traduction dans la langue de référence ; vide si la citation est déjà dans cette langue',
            principale     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'la citation mise en avant : une par article',
            verifiee       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 : proposée par l''IA, formulation à vérifier dans l''article',
            ordre          INT          NOT NULL DEFAULT 0,
            KEY article_id (article_id, ordre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bd()->exec("CREATE TABLE IF NOT EXISTS articles_concerts (
            article_id  INT UNSIGNED NOT NULL,
            concert_id  INT UNSIGNED NOT NULL,
            PRIMARY KEY (article_id, concert_id),
            KEY concert_id (concert_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bd()->exec("CREATE TABLE IF NOT EXISTS articles_disques (
            article_id  INT UNSIGNED NOT NULL,
            disque_id   INT UNSIGNED NOT NULL,
            PRIMARY KEY (article_id, disque_id),
            KEY disque_id (disque_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // référentiel des médias de presse : l'annuaire, catégorie « media »
        bd_colonne_ajouter('annuaire', 'langue', "VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'média : langue principale' AFTER pays_en");
        bd_colonne_ajouter('annuaire', 'typo', "VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'média sans logo : traitement typographique de son nom sur le site' AFTER logo_id");

        if ($neuve) {
            // le verbatim de chaque article devient sa citation principale, tel quel
            foreach (bd_lignes("SELECT id, verbatim, langue FROM articles WHERE TRIM(COALESCE(verbatim, '')) <> ''") as $a) {
                bd_requete('INSERT INTO articles_citations (article_id, texte, langue, principale, verifiee, ordre) VALUES (?, ?, ?, 1, 1, 0)', [$a['id'], trim($a['verbatim']), $a['langue']]);
            }
            // les quatre premiers étaient sur l'accueil
            foreach (bd_colonne('SELECT id FROM articles ORDER BY ordre, id LIMIT 4') as $i => $id) {
                bd_requete('UPDATE articles SET accueil = 1, ordre_accueil = ? WHERE id = ?', [$i, $id]);
            }

            // les faux articles de maquette entrent, marqués « exemple », à la place qu'ils avaient sur l'accueil
            $racine = site_racine();
            $source = $racine !== '' ? (string) @file_get_contents($racine . '/data/presse-exemples.js') : '';
            if (preg_match('/window\.PRESSE\s*=\s*(\[.*\]);/s', $source, $m)) {
                // le fichier est écrit en JavaScript (clés sans guillemets) : on le lit champ par champ
                preg_match_all('/\{\s*id:\s*\'([^\']+)\',\s*exemple:\s*true,(.*?)\n  \}/s', $m[1], $blocs, PREG_SET_ORDER);
                $chaine = function (string $bloc, string $cle): string {
                    if (!preg_match('/\b' . $cle . ':\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/s', $bloc, $x)) return '';
                    return stripslashes($x[1] !== '' ? $x[1] : ($x[2] ?? ''));
                };
                $paire = function (string $bloc, string $cle) use ($chaine): array {
                    if (preg_match('/\b' . $cle . ':\s*\{(.*?)\}/s', $bloc, $x)) return [$chaine($x[1], 'fr'), $chaine($x[1], 'en')];
                    $v = $chaine($bloc, $cle);
                    return [$v, ''];
                };
                $ordre = (int) bd_valeur('SELECT COALESCE(MAX(ordre), 0) FROM articles');
                $largeurs = [4, 2, 2, 1, 1, 1, 1];            // pour juger la grille : toute la largeur, deux colonnes, une colonne
                bd_requete('UPDATE articles SET accueil = 0 WHERE exemple = 0');
                foreach ($blocs as $i => $b) {
                    $bloc = $b[2];
                    $media = $chaine($bloc, 'media');
                    $logo = $chaine($bloc, 'logo');
                    $media_id = null;
                    if ($media !== '') {
                        $media_id = bd_valeur("SELECT id FROM annuaire WHERE categorie = 'media' AND nom = ?", [$media]);
                        if (!$media_id) {
                            $logo_id = null;
                            if ($logo !== '' && is_file($racine . '/' . $logo)) {
                                bd_requete("INSERT INTO medias (type, chemin_site, nom_origine, mime, taille, droits) VALUES ('image', ?, ?, 'image/svg+xml', ?, ?)",
                                    [$logo, basename($logo), (int) filesize($racine . '/' . $logo), MARQUE_EXEMPLE]);
                                $logo_id = (int) bd()->lastInsertId();
                            }
                            bd_requete("INSERT INTO annuaire (categorie, nom, logo_id, notes) VALUES ('media', ?, ?, ?)", [$media, $logo_id, MARQUE_EXEMPLE]);
                            $media_id = (int) bd()->lastInsertId();
                        }
                    }
                    [$titre_fr, $titre_en] = $paire($bloc, 'titre');
                    [$resume_fr, $resume_en] = $paire($bloc, 'resume');
                    $accueil = $i < 7 ? 1 : 0;
                    bd_requete("INSERT INTO articles (cle, media_id, titre_fr, titre_en, auteur, date_article, verbatim, langue, resume_fr, resume_en, lien, ordre, publie, accueil, ordre_accueil, largeur, exemple, origine)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 1, 'main')",
                        [$b[1], $media_id, $titre_fr, $titre_en, $chaine($bloc, 'auteur'), $chaine($bloc, 'date'), $chaine($bloc, 'verbatim'), $chaine($bloc, 'langue'), $resume_fr, $resume_en,
                         $chaine($bloc, 'lien'), ++$ordre, $accueil, $i, $largeurs[$i] ?? 1]);
                    $id = (int) bd()->lastInsertId();
                    foreach (['titre' => [$titre_fr, $titre_en], 'resume' => [$resume_fr, $resume_en]] as $champ => [$fr, $en]) {
                        if ($en !== '') bd_requete("INSERT IGNORE INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES ('articles', ?, ?, 'en', ?, 'validee', 'reprise', ?)", [(string) $id, $champ, $en, md5($fr)]);
                    }
                    if ($chaine($bloc, 'verbatim') !== '') {
                        bd_requete('INSERT INTO articles_citations (article_id, texte, langue, principale, verifiee, ordre) VALUES (?, ?, ?, 1, 1, 0)', [$id, $chaine($bloc, 'verbatim'), $chaine($bloc, 'langue')]);
                    }
                }
            } else {
                // pas de maquette à reprendre : les vrais articles gardent l'accueil
            }
        }

        // libellés de la presse
        libelle_creer('presse.plus', 'En savoir plus', ['en' => 'Read more']);
        libelle_creer('presse.traduction', 'Traduction', ['en' => 'Translation']);
        libelle_creer('presse.concerts', 'Concerts associés', ['en' => 'Related concerts']);
        libelle_creer('presse.albums', 'Albums associés', ['en' => 'Related albums']);
        libelle_creer('presse.citations', 'Autres citations', ['en' => 'More quotes']);
        libelle_creer('presse.auteur', 'Par {auteur}', ['en' => 'By {auteur}']);
        libelle_creer('panneau.fermer', 'Refermer', ['en' => 'Close']);
        foreach (['critique_concert' => ['Critique de concert', 'Concert review'], 'critique_album' => ['Critique d\'album', 'Album review'], 'portrait' => ['Portrait', 'Profile'],
                  'interview' => ['Interview', 'Interview'], 'actualite' => ['Actualité', 'News'], 'autre' => ['Publication', 'Publication']] as $type => [$fr, $en]) {
            libelle_creer('presse.type.' . $type, $fr, ['en' => $en]);
        }
        $GLOBALS['traductions_perimees'] = $GLOBALS['libelles_perimes'] = $GLOBALS['medias_perimes'] = $GLOBALS['annuaire_perime'] = true;
    },

    'descendre' => function () {
        // les faux articles repartent, avec leurs médias et leurs logos inventés
        $ids = bd_colonne('SELECT id FROM articles WHERE exemple = 1');
        if ($ids) {
            $liste = implode(',', array_map('intval', $ids));
            bd_requete("DELETE FROM traductions WHERE objet = 'articles' AND objet_id IN ($liste)");
            bd_requete("DELETE FROM articles WHERE id IN ($liste)");
        }
        foreach (bd_lignes("SELECT id, logo_id FROM annuaire WHERE categorie = 'media' AND notes = ?", [MARQUE_EXEMPLE]) as $f) {
            if ($f['logo_id']) bd_requete('DELETE FROM medias WHERE id = ?', [$f['logo_id']]);
            bd_requete('DELETE FROM annuaire WHERE id = ?', [$f['id']]);
        }
        bd_requete("DELETE FROM traductions WHERE objet = 'articles_citations'");
        bd_requete("DELETE FROM traductions WHERE objet = 'articles' AND champ IN ('chapeau', 'infos')");
        bd_requete("DELETE FROM medias_usages WHERE objet = 'articles'");
        foreach (['articles_disques', 'articles_concerts', 'articles_citations'] as $t) bd_table_retirer($t);
        foreach (['cree_le', 'texte_source', 'origine', 'exemple', 'largeur', 'ordre_accueil', 'accueil', 'une', 'publie', 'url_canonique', 'infos_fr', 'chapeau_fr', 'type'] as $c) bd_colonne_retirer('articles', $c);
        bd_colonne_retirer('annuaire', 'typo');
        bd_colonne_retirer('annuaire', 'langue');
        foreach (bd_colonne("SELECT cle FROM libelles WHERE cle LIKE 'presse.type.%' OR cle IN ('presse.plus', 'presse.traduction', 'presse.concerts', 'presse.albums', 'presse.citations', 'presse.auteur', 'panneau.fermer')") as $cle) {
            bd_requete("DELETE FROM traductions WHERE objet = 'libelles' AND objet_id = ?", [$cle]);
            bd_requete('DELETE FROM libelles WHERE cle = ?', [$cle]);
        }
        $GLOBALS['traductions_perimees'] = $GLOBALS['libelles_perimes'] = $GLOBALS['medias_perimes'] = $GLOBALS['annuaire_perime'] = true;
    },
];
