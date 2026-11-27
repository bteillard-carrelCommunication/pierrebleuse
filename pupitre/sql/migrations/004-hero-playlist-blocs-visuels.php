<?php
/**
 * Migration 004 : playlist détaillée, blocs visuels configurables, réorganisation des visuels.
 *
 * Playlist : chaque titre gagne compositeur, œuvre, mouvement, orchestre, interprètes, nature de
 * l'enregistrement (studio, live, autre), adresse externe, crédits, statut de publication. Le morceau
 * d'ouverture se choisit d'une marque, quel que soit l'ordre : c'est, au départ, le premier titre,
 * celui qui ouvrait déjà le site. Aucun extrait n'est remplacé.
 *
 * Blocs visuels : un bloc porte désormais une liste d'éléments (image, image animée, une ou
 * plusieurs vidéos), rangés dans `medias_usages`. Les quatre visuels d'avant y sont repris, avec
 * la réorganisation demandée le 20/11/2026 :
 *     Visuel 1  reçoit l'image et l'animation du Visuel 2 ;
 *     Visuel 2  reçoit la vidéo du Visuel 3 ;
 *     Visuel 3  reçoit l'ancien contenu du Visuel 1, gardé et réutilisable, et le bloc est masqué ;
 *     Restons en contact  garde sa photographie de fond (ancien « visuel-4 »).
 * Aucune image, aucune animation n'est modifiée : les éléments changent seulement de bloc. La table
 * `visuels` n'est ni modifiée ni supprimée : elle garde l'état d'avant.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Playlist détaillée, blocs visuels, réorganisation des visuels',

    'monter' => function () {
        // --- Playlist
        bd_colonne_ajouter('musiques', 'compositeur_fr', "VARCHAR(255) NOT NULL DEFAULT '' AFTER titre_en");
        bd_colonne_ajouter('musiques', 'oeuvre_fr', "VARCHAR(255) NOT NULL DEFAULT '' AFTER compositeur_fr");
        bd_colonne_ajouter('musiques', 'mouvement_fr', "VARCHAR(255) NOT NULL DEFAULT '' AFTER oeuvre_fr");
        bd_colonne_ajouter('musiques', 'orchestre', "VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'appellation officielle, non traduite' AFTER mouvement_fr");
        bd_colonne_ajouter('musiques', 'orchestre_id', "INT UNSIGNED NULL COMMENT 'annuaire' AFTER orchestre");
        bd_colonne_ajouter('musiques', 'interpretes_fr', "VARCHAR(500) NOT NULL DEFAULT '' AFTER orchestre_id");
        bd_colonne_ajouter('musiques', 'nature', "VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'studio, live, autre ; vide : à préciser' AFTER interpretes_fr");
        bd_colonne_ajouter('musiques', 'url', "VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'adresse du fichier son, s''il n''est pas dans la médiathèque' AFTER media_id");
        bd_colonne_ajouter('musiques', 'credit', "VARCHAR(500) NOT NULL DEFAULT '' AFTER nature");
        bd_colonne_ajouter('musiques', 'publie', "TINYINT(1) NOT NULL DEFAULT 1 AFTER credit");
        if (bd_colonne_ajouter('musiques', 'ouverture', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'morceau d''ouverture du site : un seul' AFTER publie")) {
            $premier = bd_valeur('SELECT id FROM musiques ORDER BY ordre, id LIMIT 1');
            if ($premier) bd_requete('UPDATE musiques SET ouverture = 1 WHERE id = ?', [$premier]);
        }

        // --- Blocs visuels : reprise des visuels d'avant, avec la réorganisation
        if (!bd_valeur("SELECT COUNT(*) FROM medias_usages WHERE objet = 'blocs' AND role = 'element'")) {
            $destination = ['visuel-2' => 'visuel-1', 'visuel-3' => 'visuel-2', 'visuel-1' => 'visuel-3', 'visuel-4' => 'suivre'];
            $position = fn($x, $y) => ($x === null || $y === null) ? null : [(int) $x, (int) $y];
            foreach (bd_lignes('SELECT * FROM visuels ORDER BY ordre, cle') as $v) {
                $bloc = $destination[$v['cle']] ?? null;
                if (!$bloc) continue;
                $media = $v['media_id'] ? (int) $v['media_id'] : null;
                if (in_array($v['type'], ['youtube'], true) && $v['source'] !== '') {
                    // une vidéo YouTube devient un média à adresse externe
                    $adresse = preg_match('#^https?://#', $v['source']) ? $v['source'] : 'https://www.youtube.com/watch?v=' . $v['source'];
                    bd_requete("INSERT INTO medias (type, url_externe, nom_origine, titre) VALUES ('video', ?, 'YouTube', 'YouTube')", [$adresse]);
                    $media = (int) bd()->lastInsertId();
                }
                if (!$media) continue;                            // un emplacement sans média (couleur seule) n'a rien à reprendre
                $parametres = [];
                if (($p = $position($v['position_x'], $v['position_y'])) !== null) $parametres['position'] = $p;
                if (($p = $position($v['mobile_x'], $v['mobile_y'])) !== null) $parametres['mobile'] = $p;
                $nature = $v['type'] === 'image' ? 'image' : 'video';
                if ($v['type'] === 'image' && $v['lumiere'] !== '') {
                    $nature = 'animation';
                    $parametres['animation'] = ['module' => 'lumiere', 'preset' => $v['lumiere']];
                }
                if ($v['type'] === 'video') {
                    $parametres['son'] = (bool) $v['son'];
                    if ($v['piste'] !== '') $parametres['piste'] = $v['piste'];
                }
                bd_requete("INSERT INTO medias_usages (media_id, objet, objet_id, role, nature, affiche_id, alt_fr, parametres, publie, ordre) VALUES (?, 'blocs', ?, 'element', ?, ?, ?, ?, 1, 0)",
                    [$media, $bloc, $nature, $v['affiche_id'] ?: null, (string) $v['alt_fr'], json_encode($parametres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                $id = (int) bd()->lastInsertId();
                // les textes alternatifs traduits suivent l'élément
                bd_requete("INSERT IGNORE INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte)
                            SELECT 'medias_usages', ?, 'alt', langue, texte, statut, 'reprise', empreinte FROM traductions WHERE objet = 'visuels' AND objet_id = ? AND champ = 'alt'", [(string) $id, $v['cle']]);
            }
            // Visuel 3 : masqué pour le moment (son contenu, l'ancien Visuel 1, reste en place)
            bd_requete("UPDATE blocs SET visible = 0 WHERE cle = 'visuel-3'");
        }

        // --- Libellés du bouton Scroll et du lecteur vidéo
        libelle_creer('ouverture.scroll', 'Scroll', ['en' => 'Scroll']);
        libelle_creer('video.lire', 'Lire la vidéo', ['en' => 'Play the video']);
        libelle_creer('video.pause', 'Mettre la vidéo en pause', ['en' => 'Pause the video']);
        libelle_creer('video.suivante', 'Vidéo suivante', ['en' => 'Next video']);
        libelle_creer('video.precedente', 'Vidéo précédente', ['en' => 'Previous video']);
        libelle_creer('video.position', 'Vidéo {n} sur {total}', ['en' => 'Video {n} of {total}']);
        $GLOBALS['blocs_perimes'] = $GLOBALS['traductions_perimees'] = true;
    },

    'descendre' => function () {
        $ids = bd_colonne("SELECT id FROM medias_usages WHERE objet = 'blocs' AND role = 'element'");
        if ($ids) bd_requete("DELETE FROM traductions WHERE objet = 'medias_usages' AND objet_id IN (" . implode(',', array_map('intval', $ids)) . ')');
        bd_requete("DELETE FROM medias_usages WHERE objet = 'blocs' AND role = 'element'");
        bd_requete("UPDATE blocs SET visible = 1 WHERE cle = 'visuel-3'");
        foreach (['ouverture.scroll', 'video.lire', 'video.pause', 'video.suivante', 'video.precedente', 'video.position'] as $cle) {
            bd_requete("DELETE FROM traductions WHERE objet = 'libelles' AND objet_id = ?", [$cle]);
            bd_requete('DELETE FROM libelles WHERE cle = ?', [$cle]);
        }
        bd_requete("DELETE FROM traductions WHERE objet = 'musiques' AND champ IN ('compositeur', 'oeuvre', 'mouvement', 'interpretes')");
        foreach (['ouverture', 'publie', 'credit', 'url', 'nature', 'interpretes_fr', 'orchestre_id', 'orchestre', 'mouvement_fr', 'oeuvre_fr', 'compositeur_fr'] as $c) bd_colonne_retirer('musiques', $c);
        $GLOBALS['blocs_perimes'] = $GLOBALS['traductions_perimees'] = true;
    },
];
