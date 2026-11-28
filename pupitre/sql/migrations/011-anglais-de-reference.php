<?php
/**
 * Migration 011 : l'anglais devient la langue de rédaction (langue de référence).
 *
 * Avant : les contenus sont rédigés en français (colonnes « champ_fr », `textes.fr`), l'anglais est
 * une traduction parmi d'autres (table `traductions`, et d'anciennes colonnes « champ_en » tenues en
 * miroir). Après : les contenus sont rédigés en anglais (colonnes « champ_en », `textes.en`), et le
 * français est une traduction comme une autre, dans la table `traductions`. Les colonnes
 * « champ_fr » disparaissent : il n'y a plus qu'un endroit où lire le texte de référence.
 *
 * Pour chaque texte, sans rien perdre :
 *   - le texte anglais de référence est la traduction anglaise validée ; à défaut, le texte français
 *     tel quel (c'est déjà ce que le site affichait en anglais : un nom propre, un titre d'œuvre) ;
 *   - le texte français devient une traduction validée quand il diffère de l'anglais ; un texte
 *     français sans anglais validé est gardé lui aussi dès qu'il est rédigé (plusieurs lignes, ou
 *     plus de 80 caractères) ou que l'IA lui avait proposé un anglais : le réécrire en anglais ne
 *     fera pas disparaître le français du site ;
 *   - une proposition d'anglais de l'IA qui n'était pas validée ne devient pas le texte de
 *     référence (l'IA propose, une personne valide) : elle est gardée dans les réglages
 *     (migration_011_propositions), et l'écran « Langues » propose de l'adopter ou de l'écarter.
 *
 * Citations de presse (`articles_citations.traduction`) : ce champ n'est pas un texte rédigé mais
 * la traduction d'une citation ; il n'a pas de repli. L'anglais y reçoit la seule traduction
 * anglaise validée, le français devient une traduction.
 *
 * La liste des champs est écrite ici, telle qu'elle était le jour de la migration : elle ne suit
 * pas les évolutions ultérieures de TRADUISIBLES.
 */

defined('PUPITRE') or exit;

/** table => [clé primaire, champs]. `textes` a ses colonnes à part : « fr » et « en ». */
const MIGRATION_011_CHAMPS = [
    'libelles' => ['cle', ['texte']],
    'textes' => ['cle', ['texte']],
    'concerts' => ['id', ['titre', 'ensemble', 'salle', 'ville', 'pays', 'mentions', 'description']],
    'representations' => ['id', ['salle', 'ville', 'pays']],
    'programme' => ['id', ['compositeur', 'oeuvre', 'mention']],
    'participants' => ['id', ['role']],
    'maisons' => ['id', ['nom']],
    'annuaire' => ['id', ['ville', 'pays']],
    'medias' => ['id', ['alt']],
    'medias_usages' => ['id', ['titre', 'description', 'alt']],
    'parcours_lignes' => ['id', ['texte']],
    'parcours_orchestres' => ['id', ['ville']],
    'disques' => ['id', ['sous_titre', 'descriptif', 'oeuvres', 'interpretes', 'presentation', 'contexte', 'mot', 'citations', 'infos']],
    'distinctions' => ['id', ['intitule', 'description']],
    'articles' => ['id', ['titre', 'chapeau', 'resume', 'infos']],
    'articles_citations' => ['id', ['traduction']],
    'fonctions' => ['id', ['intitule', 'institution', 'description']],
    'collaborations' => ['id', ['intitule', 'description']],
    'visuels' => ['cle', ['alt']],
    'musiques' => ['id', ['titre', 'mention', 'compositeur', 'oeuvre', 'mouvement', 'interpretes']],
    'telechargements' => ['id', ['libelle', 'description', 'conditions']],
    'contacts_groupes' => ['id', ['mention']],
    'contacts_personnes' => ['id', ['role']],
];

/** Colonne d'un champ dans une langue, telle qu'elle était ou sera : `textes` a « fr » et « en ». */
function migration_011_colonne(string $table, string $champ, string $langue): string
{
    return $table === 'textes' ? $langue : $champ . '_' . $langue;
}

/** Définition SQL d'une colonne existante, pour en créer une jumelle : « VARCHAR(255) NOT NULL DEFAULT '' COMMENT '…' ». */
function migration_011_definition(string $table, string $colonne): string
{
    $c = bd_ligne('SELECT column_type AS type, is_nullable AS nul, column_default AS defaut, column_comment AS commentaire
                   FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $colonne]);
    if (!$c) throw new RuntimeException('Colonne introuvable : ' . $table . '.' . $colonne);
    $type = is_resource($c['type']) ? stream_get_contents($c['type']) : (string) $c['type'];
    $s = $type . ($c['nul'] === 'YES' ? ' NULL' : ' NOT NULL');
    if ($c['defaut'] !== null) $s .= ' DEFAULT ' . bd()->quote((string) $c['defaut']);
    $commentaire = str_replace(['langue de référence', 'français et anglais'], ['langue de rédaction', 'langue de rédaction'], (string) $c['commentaire']);
    if ($commentaire !== '') $s .= ' COMMENT ' . bd()->quote($commentaire);
    return $s;
}

/** Un texte rédigé, par opposition à un nom ou à un titre court : plusieurs lignes, ou plus de 80 caractères. */
function migration_011_redige(string $texte): bool
{
    return str_contains($texte, "\n") || mb_strlen($texte) > 80;
}

return [
    'titre' => 'L\'anglais devient la langue de rédaction ; le français, une traduction',

    'monter' => function () {
        $pdo = bd();
        // l'anglais doit exister : il devient la langue de référence, la première et celle du site par défaut
        if (!(int) bd_valeur("SELECT COUNT(*) FROM langues WHERE code = 'en'")) {
            bd_requete("INSERT INTO langues (code, nom, nom_fr, locale, active, reference, defaut, ordre) VALUES ('en', 'English', 'Anglais', 'en-GB', 1, 0, 0, 0)");
        }
        $avant = ['reference' => (string) bd_valeur('SELECT code FROM langues WHERE reference = 1 LIMIT 1'), 'defaut' => (string) bd_valeur('SELECT code FROM langues WHERE defaut = 1 LIMIT 1'),
            'ordres' => [], 'colonnes_creees' => []];
        foreach (bd_lignes('SELECT code, ordre, active FROM langues') as $l) $avant['ordres'][$l['code']] = [(int) $l['ordre'], (int) $l['active']];
        $deja = json_decode((string) bd_valeur("SELECT valeur FROM reglages WHERE cle = 'migration_011'"), true);
        if (is_array($deja)) $avant = $deja + $avant;            // migration reprise après une interruption : on garde l'état d'origine déjà noté
        $propositions = json_decode((string) bd_valeur("SELECT valeur FROM reglages WHERE cle = 'migration_011_propositions'"), true) ?: [];

        $poser_fr = $pdo->prepare("INSERT INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, 'fr', ?, 'validee', 'reprise', ?)
                                   ON DUPLICATE KEY UPDATE texte = VALUES(texte), statut = VALUES(statut), origine = VALUES(origine), empreinte = VALUES(empreinte)");
        $bilan = ['textes' => 0, 'anglais_valide' => 0, 'francais_gardes' => 0, 'propositions' => 0];
        foreach (MIGRATION_011_CHAMPS as $table => [$cle, $champs]) {
            if (!bd_table_existe($table)) continue;
            foreach ($champs as $champ) {
                $fr = migration_011_colonne($table, $champ, 'fr');
                $en = migration_011_colonne($table, $champ, 'en');
                if (!bd_colonne_existe($table, $fr)) continue;                 // champ déjà passé à l'anglais
                if (bd_colonne_ajouter($table, $en, migration_011_definition($table, $fr) . ' AFTER ' . bd_nom($fr))) $avant['colonnes_creees'][] = $table . '.' . $en;
                reglage_ecrire('migration_011', json_encode($avant, JSON_UNESCAPED_UNICODE));
                $anglais = [];
                foreach (bd_lignes("SELECT objet_id, texte, statut FROM traductions WHERE objet = ? AND champ = ? AND langue = 'en'", [$table, $champ]) as $t) $anglais[(string) $t['objet_id']] = $t;
                $ecrire = $pdo->prepare('UPDATE ' . bd_nom($table) . ' SET ' . bd_nom($en) . ' = ? WHERE ' . bd_nom($cle) . ' = ?');
                foreach (bd_lignes('SELECT ' . bd_nom($cle) . ' AS cle, ' . bd_nom($fr) . ' AS fr FROM ' . bd_nom($table)) as $ligne) {
                    $id = (string) $ligne['cle'];
                    $f = trim(str_replace("\r\n", "\n", (string) $ligne['fr']));
                    $t = $anglais[$id] ?? null;
                    $e = ($t && $t['statut'] === 'validee') ? trim(str_replace("\r\n", "\n", (string) $t['texte'])) : '';
                    $p = ($t && $t['statut'] === 'proposee') ? trim(str_replace("\r\n", "\n", (string) $t['texte'])) : '';
                    if ($table === 'articles_citations') {
                        // la traduction d'une citation n'a pas de repli : chaque langue porte la sienne, ou rien
                        $reference = $e;
                        $francais = $f !== '' ? $f : null;
                    } else {
                        $reference = $e !== '' ? $e : $f;
                        $francais = null;
                        if ($f !== '' && $e !== '' && $f !== $e) $francais = $f;
                        elseif ($f !== '' && $e === '' && ($p !== '' || migration_011_redige($f))) $francais = $f;
                    }
                    $ecrire->execute([($reference === '' && $ligne['fr'] === null) ? null : $reference, $id]);      // un champ jamais rempli reste « non renseigné »
                    if ($f !== '' || $e !== '') $bilan['textes']++;
                    if ($e !== '') $bilan['anglais_valide']++;
                    if ($francais !== null) { $poser_fr->execute([$table, $id, $champ, $francais, md5($reference)]); $bilan['francais_gardes']++; }
                    if ($p !== '' && $p !== $reference) { $propositions[] = ['objet' => $table, 'id' => $id, 'champ' => $champ, 'texte' => $p]; $bilan['propositions']++; }
                }
                reglage_ecrire('migration_011_propositions', json_encode($propositions, JSON_UNESCAPED_UNICODE));
                bd_requete("DELETE FROM traductions WHERE objet = ? AND champ = ? AND langue = 'en'", [$table, $champ]);
                bd_colonne_retirer($table, $fr);
            }
        }

        // l'anglais : langue de référence, première de la liste, langue du site par défaut ; le français reste proposé tel qu'il l'était
        bd_requete("UPDATE langues SET reference = (code = 'en'), defaut = (code = 'en')");
        bd_requete("UPDATE langues SET active = 1, ordre = 0 WHERE code = 'en'");
        $rang = 1;
        foreach (bd_colonne("SELECT code FROM langues WHERE code <> 'en' ORDER BY (code = 'fr') DESC, ordre, code") as $code) bd_requete('UPDATE langues SET ordre = ? WHERE code = ?', [$rang++, $code]);
        reglage_ecrire('migration_011', json_encode($avant, JSON_UNESCAPED_UNICODE));
        reglage_ecrire('migration_011_bilan', json_encode($bilan));
        $GLOBALS['colonnes_perimees'] = $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = $GLOBALS['reglages_perimes'] = true;
    },

    'descendre' => function () {
        $pdo = bd();
        $avant = json_decode((string) bd_valeur("SELECT valeur FROM reglages WHERE cle = 'migration_011'"), true) ?: [];
        $creees = array_flip($avant['colonnes_creees'] ?? []);
        $poser_en = $pdo->prepare("INSERT INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, 'en', ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE texte = VALUES(texte), statut = VALUES(statut), origine = VALUES(origine), empreinte = VALUES(empreinte)");
        foreach (MIGRATION_011_CHAMPS as $table => [$cle, $champs]) {
            if (!bd_table_existe($table)) continue;
            foreach ($champs as $champ) {
                $fr = migration_011_colonne($table, $champ, 'fr');
                $en = migration_011_colonne($table, $champ, 'en');
                if (bd_colonne_existe($table, $fr) || !bd_colonne_existe($table, $en)) continue;
                bd_colonne_ajouter($table, $fr, migration_011_definition($table, $en) . ' AFTER ' . bd_nom($en));
                $francais = [];
                foreach (bd_lignes("SELECT objet_id, texte, statut FROM traductions WHERE objet = ? AND champ = ? AND langue = 'fr'", [$table, $champ]) as $t) $francais[(string) $t['objet_id']] = $t;
                $ecrire = $pdo->prepare('UPDATE ' . bd_nom($table) . ' SET ' . bd_nom($fr) . ' = ?, ' . bd_nom($en) . ' = ? WHERE ' . bd_nom($cle) . ' = ?');
                foreach (bd_lignes('SELECT ' . bd_nom($cle) . ' AS cle, ' . bd_nom($en) . ' AS en FROM ' . bd_nom($table)) as $ligne) {
                    $id = (string) $ligne['cle'];
                    $r = trim((string) $ligne['en']);
                    $t = $francais[$id] ?? null;
                    $f = ($t && $t['statut'] === 'validee') ? trim((string) $t['texte']) : '';
                    if ($table === 'articles_citations') { $reference = $f; $anglais = $r; }
                    else { $reference = $f !== '' ? $f : $r; $anglais = ($f !== '' && $r !== '' && $r !== $f) ? $r : ''; }
                    // l'ancienne colonne « _en » redevient le miroir de ce qui sort sur le site : la traduction validée, ou rien
                    $ecrire->execute([($reference === '' && $ligne['en'] === null) ? null : $reference, ($anglais === '' && $ligne['en'] === null) ? null : $anglais, $id]);
                    if ($anglais !== '') $poser_en->execute([$table, $id, $champ, $anglais, 'validee', 'reprise', md5($reference)]);
                }
                bd_requete("DELETE FROM traductions WHERE objet = ? AND champ = ? AND langue = 'fr'", [$table, $champ]);
                if (isset($creees[$table . '.' . $en])) bd_colonne_retirer($table, $en);
            }
        }
        // les propositions de l'IA qui attendaient reprennent leur place
        foreach (json_decode((string) bd_valeur("SELECT valeur FROM reglages WHERE cle = 'migration_011_propositions'"), true) ?: [] as $p) {
            if (!isset(MIGRATION_011_CHAMPS[$p['objet'] ?? '']) || bd_valeur("SELECT COUNT(*) FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = 'en'", [$p['objet'], $p['id'], $p['champ']])) continue;
            $col = migration_011_colonne($p['objet'], $p['champ'], 'fr');
            $reference = (string) bd_valeur('SELECT ' . bd_nom($col) . ' FROM ' . bd_nom($p['objet']) . ' WHERE ' . bd_nom(MIGRATION_011_CHAMPS[$p['objet']][0]) . ' = ?', [$p['id']]);
            $poser_en->execute([$p['objet'], $p['id'], $p['champ'], $p['texte'], 'proposee', 'ia', md5($reference)]);
        }
        $reference = ($avant['reference'] ?? '') ?: 'fr';
        $defaut = ($avant['defaut'] ?? '') ?: 'en';
        bd_requete('UPDATE langues SET reference = (code = ?), defaut = (code = ?)', [$reference, $defaut]);
        foreach ($avant['ordres'] ?? [] as $code => [$ordre, $active]) bd_requete('UPDATE langues SET ordre = ?, active = ? WHERE code = ?', [$ordre, $active, $code]);
        bd_requete("DELETE FROM reglages WHERE cle IN ('migration_011', 'migration_011_propositions', 'migration_011_bilan')");
        $GLOBALS['colonnes_perimees'] = $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = $GLOBALS['reglages_perimes'] = true;
    },
];
