<?php
/**
 * Langues et traductions : le socle multilingue de Pupitre.
 *
 * Les langues sont dans la table `langues`. L'une est la langue de référence : celle dans laquelle
 * les contenus sont rédigés (ici le français). Son texte vit dans les tables des objets, dans les
 * colonnes « champ_fr ». Les autres langues vivent toutes dans la table `traductions` : une ligne par
 * objet, champ et langue, avec son statut. Ajouter une langue ne demande donc aucune colonne.
 *
 * Trois statuts :
 *   validee    relue par une personne : elle sort sur le site ;
 *   proposee   proposée par l'IA, pas encore relue : elle ne sort pas, le site garde la langue de référence ;
 *   a_revoir   (calculé) validée, mais le texte de référence a changé depuis : elle sort, et elle est signalée.
 * Traduction absente ou vide : le site affiche le texte de la langue de référence.
 *
 * Dans le code, un champ traduisible « x » d'une table s'écrit x_fr, x_en, x_de… dans les tableaux
 * de données. bd_inserer() et bd_modifier() (inclus/base.php) rangent d'eux-mêmes chaque langue au
 * bon endroit. Les anciennes colonnes « _en » sont gardées et tenues à jour (copie de ce qui sort
 * sur le site) : rien de ce qui les lisait ne casse.
 *
 * TRADUISIBLES dit, table par table, quels champs se traduisent. Tout le reste (dates, coordonnées,
 * liens, relations) est commun à toutes les langues et n'existe qu'une fois.
 */

defined('PUPITRE') or exit;

/**
 * Champs traduisibles : table => [nom lisible, clé primaire, colonne qui nomme la ligne, [champ => libellé], page de Pupitre].
 * La page est [nom de page, paramètre qui reçoit la clé, paramètres fixes] ; '' si l'objet n'a pas d'écran à lui.
 */
const TRADUISIBLES = [
    'libelles' => ['Libellés de l\'interface du site', 'cle', 'cle', ['texte' => 'Libellé'], ['langues', '', ['onglet' => 'libelles']]],
    'textes' => ['Textes des blocs', 'cle', 'cle', ['texte' => 'Texte'], ['blocs', '', []]],
    'concerts' => ['Concerts', 'id', 'titre_fr', ['titre' => 'Titre', 'ensemble' => 'Orchestre ou ensemble', 'salle' => 'Salle', 'ville' => 'Ville', 'pays' => 'Pays', 'mentions' => 'Informations complémentaires', 'description' => 'Description'], ['concert', 'id', []]],
    'representations' => ['Représentations', 'id', 'date', ['salle' => 'Salle', 'ville' => 'Ville', 'pays' => 'Pays'], ['', '', []]],
    'programme' => ['Programmes des concerts', 'id', 'oeuvre_fr', ['compositeur' => 'Compositeur', 'oeuvre' => 'Œuvre', 'mention' => 'Mention'], ['', '', []]],
    'participants' => ['Participants des concerts', 'id', 'nom', ['role' => 'Rôle'], ['', '', []]],
    'maisons' => ['Maisons', 'id', 'nom_fr', ['nom' => 'Nom'], ['reglages', '', []]],
    'annuaire' => ['Annuaire', 'id', 'nom', ['ville' => 'Ville', 'pays' => 'Pays'], ['fiche', 'id', []]],
    'medias' => ['Médiathèque', 'id', 'nom_origine', ['alt' => 'Texte alternatif'], ['media', 'id', []]],
    'medias_usages' => ['Médias des blocs et des collections', 'id', 'titre_fr', ['titre' => 'Titre', 'description' => 'Description', 'alt' => 'Texte alternatif'], ['visuels', '', []]],
    'parcours_lignes' => ['Parcours (anciennes lignes)', 'id', 'texte_fr', ['texte' => 'Ligne'], ['', '', []]],
    'parcours_orchestres' => ['Carte du monde', 'id', 'ville_fr', ['ville' => 'Ville'], ['carte', '', []]],
    'disques' => ['Discographie', 'id', 'titre', ['sous_titre' => 'Sous-titre', 'descriptif' => 'Descriptif', 'oeuvres' => 'Œuvres', 'interpretes' => 'Interprètes', 'presentation' => 'Présentation',
        'contexte' => 'Contexte artistique', 'mot' => 'Le mot de l\'artiste', 'citations' => 'Citations de l\'artiste', 'infos' => 'À propos des œuvres'], ['disque', 'id', []]],
    'distinctions' => ['Distinctions', 'id', 'intitule_fr', ['intitule' => 'Intitulé', 'description' => 'Description'], ['distinctions', '', []]],
    'articles' => ['Revue de presse', 'id', 'titre_fr', ['titre' => 'Titre', 'chapeau' => 'Chapeau', 'resume' => 'Résumé', 'infos' => 'Informations complémentaires'], ['article', 'id', []]],
    'articles_citations' => ['Revue de presse : citations', 'id', 'texte', ['traduction' => 'Traduction de la citation'], ['', '', []]],
    'fonctions' => ['Fonctions', 'id', 'intitule_fr', ['intitule' => 'Intitulé', 'institution' => 'Institution', 'description' => 'Description'], ['parcours', '', []]],
    'collaborations' => ['Grands débuts et collaborations', 'id', 'intitule_fr', ['intitule' => 'Intitulé', 'description' => 'Description'], ['parcours', '', []]],
    'visuels' => ['Visuels', 'cle', 'cle', ['alt' => 'Texte alternatif'], ['visuels', '', []]],
    'musiques' => ['Playlist', 'id', 'titre_fr', ['titre' => 'Titre', 'mention' => 'Mention', 'compositeur' => 'Compositeur', 'oeuvre' => 'Œuvre', 'mouvement' => 'Mouvement', 'interpretes' => 'Interprètes'], ['musiques', '', []]],
    'telechargements' => ['Téléchargements', 'id', 'libelle_fr', ['libelle' => 'Titre', 'description' => 'Description', 'conditions' => 'Conditions d\'utilisation'], ['contenu', '', ['type' => 'telechargements']]],
    'contacts_groupes' => ['Contacts : groupes', 'id', 'structure', ['mention' => 'Mention'], ['contenu', '', ['type' => 'contacts']]],
    'contacts_personnes' => ['Contacts : personnes', 'id', 'nom', ['role' => 'Fonction'], ['contenu', '', ['type' => 'contacts']]],
];

/** Tables dont les colonnes ne suivent pas la règle « champ_langue » : table => champ => [langue => colonne]. */
const COLONNES_PARTICULIERES = [
    'textes' => ['texte' => ['fr' => 'fr', 'en' => 'en']],
];

const STATUTS_TRADUCTION = ['validee' => 'Validée', 'proposee' => 'Proposée par l\'IA', 'a_revoir' => 'À revoir', 'manquante' => 'Manquante'];

// ---------- Les langues ----------

/** Toutes les langues connues, actives ou non, dans l'ordre : la langue de référence d'abord. */
function langues_toutes(): array
{
    static $l = null;
    if ($l === null || !empty($GLOBALS['langues_perimees'])) {
        $l = [];
        try {
            foreach (bd_lignes('SELECT * FROM langues ORDER BY reference DESC, ordre, code') as $x) $l[$x['code']] = $x;
        } catch (Throwable $e) {
            // base d'avant la migration des langues : on lit l'ancien réglage
        }
        if (!$l) {
            foreach (array_values(array_filter(array_map('trim', explode(',', reglage('langues') ?: 'fr')))) as $i => $code) {
                $l[$code] = ['code' => $code, 'nom' => strtoupper($code), 'nom_fr' => strtoupper($code), 'locale' => $code, 'active' => 1, 'reference' => $i === 0 ? 1 : 0, 'defaut' => $i === 0 ? 1 : 0, 'ordre' => $i];
            }
        }
        $GLOBALS['langues_perimees'] = false;
    }
    return $l;
}

/** Codes des langues des contenus, la langue de référence en premier : ['fr', 'en']. */
function langues(): array
{
    $l = [];
    foreach (langues_toutes() as $code => $x) if ($x['active'] || $x['reference']) $l[] = $code;
    return $l ?: ['fr'];
}

/** La langue dans laquelle les contenus sont rédigés. */
function langue_reference(): string
{
    foreach (langues_toutes() as $code => $x) if ($x['reference']) return $code;
    return 'fr';
}

/** La langue que le site affiche à la première visite, et celle de son HTML de repli. */
function langue_defaut(): string
{
    foreach (langues_toutes() as $code => $x) if ($x['defaut'] && $x['active']) return $code;
    return langue_reference();
}

/** Les langues actives autres que la langue de référence. */
function langues_autres(): array
{
    return array_values(array_diff(langues(), [langue_reference()]));
}

function langue_nom(string $code): string
{
    return (string) (langues_toutes()[$code]['nom_fr'] ?? strtoupper($code));
}

/** La table des traductions existe-t-elle ? Faux sur une base d'avant la migration 001 : tout s'écrit alors comme en version 1. */
function langues_installees(bool $relire = false): bool
{
    static $oui = null;
    if ($oui === null || $relire) $oui = bd_table_existe('traductions');
    return $oui;
}

// ---------- Où vit un champ ----------

/** Les colonnes d'une table, lues une fois. */
function bd_colonnes(string $table): array
{
    static $c = [];
    if (!empty($GLOBALS['colonnes_perimees'])) { $c = []; $GLOBALS['colonnes_perimees'] = false; }
    if (!isset($c[$table])) {
        $c[$table] = bd_colonne('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
    }
    return $c[$table];
}

/** Colonne qui porte un champ dans une langue : « titre_fr » en général. */
function colonne_langue(string $table, string $champ, string $langue): string
{
    return COLONNES_PARTICULIERES[$table][$champ][$langue] ?? ($champ . '_' . $langue);
}

/** Colonne du texte de référence d'un champ. */
function colonne_reference(string $table, string $champ): string
{
    return colonne_langue($table, $champ, langue_reference());
}

/** Clé primaire d'une table traduisible. */
function cle_primaire(string $table): string
{
    return TRADUISIBLES[$table][1] ?? 'id';
}

/**
 * Sépare, dans des données prêtes à écrire, ce qui va dans la table et ce qui est une traduction.
 * Rend [données de la table, [champ => [langue => texte]]].
 */
function traductions_separer(string $table, array $donnees): array
{
    if (!isset(TRADUISIBLES[$table]) || !$donnees || !langues_installees()) return [$donnees, []];
    $reference = langue_reference();
    $traductions = [];
    foreach (TRADUISIBLES[$table][3] as $champ => $_) {
        foreach (langues_toutes() as $code => $x) {
            if ($code === $reference) continue;
            $col = colonne_langue($table, $champ, $code);
            if (!array_key_exists($col, $donnees)) continue;
            $traductions[$champ][$code] = (string) ($donnees[$col] ?? '');
            unset($donnees[$col]);
        }
    }
    return [$donnees, $traductions];
}

// ---------- Lire les traductions ----------

/** Toutes les traductions d'une table, lues une fois : [clé de la ligne][champ][langue] => ligne de `traductions`. */
function traductions_de(string $objet): array
{
    static $cache = [];
    if (!empty($GLOBALS['traductions_perimees'])) { $cache = []; $GLOBALS['traductions_perimees'] = false; }
    if (!isset($cache[$objet])) {
        $cache[$objet] = [];
        try {
            foreach (bd_lignes('SELECT * FROM traductions WHERE objet = ?', [$objet]) as $t) $cache[$objet][$t['objet_id']][$t['champ']][$t['langue']] = $t;
        } catch (Throwable $e) {
            // base d'avant la migration des langues
        }
    }
    return $cache[$objet];
}

function traduction(string $objet, $id, string $champ, string $langue): ?array
{
    return traductions_de($objet)[(string) $id][$champ][$langue] ?? null;
}

/** Statut d'une traduction au regard du texte de référence d'aujourd'hui. */
function traduction_statut(?array $t, string $reference): string
{
    if (!$t || trim((string) $t['texte']) === '') return 'manquante';
    if ($t['statut'] === 'proposee') return 'proposee';
    return ($t['empreinte'] !== '' && $t['empreinte'] !== md5($reference)) ? 'a_revoir' : 'validee';
}

/** Texte qui sort sur le site pour un champ et une langue : la traduction validée, sinon ''. */
function traduction_publiee(string $objet, $id, string $champ, string $langue): string
{
    $t = traduction($objet, $id, $champ, $langue);
    return ($t && $t['statut'] === 'validee') ? trim((string) $t['texte']) : '';
}

/** Traductions qui sortent sur le site pour un champ : [langue => texte], les seules validées. */
function traductions_publiees(string $objet, $id, string $champ): array
{
    $v = [];
    foreach (langues_autres() as $langue) $v[$langue] = traduction_publiee($objet, $id, $champ, $langue);
    return $v;
}

/** Textes d'un champ pour le formulaire : [langue => texte], propositions de l'IA comprises. */
function traductions_champ(string $objet, $id, string $champ): array
{
    $v = [];
    foreach (langues_autres() as $langue) $v[$langue] = (string) (traduction($objet, $id, $champ, $langue)['texte'] ?? '');
    return $v;
}

/**
 * Champ d'une ligne, pour les fichiers du site : une chaîne si aucune traduction ne diffère du
 * texte de référence, sinon { fr: …, en: …, … }. $ligne est la ligne de la table.
 */
function multi(string $objet, array $ligne, string $champ)
{
    $reference = langue_reference();
    $texte = trim((string) ($ligne[colonne_reference($objet, $champ)] ?? ''));
    $id = $ligne[cle_primaire($objet)] ?? null;
    $v = [$reference => $texte];
    if ($id !== null) {
        foreach (langues_autres() as $langue) {
            $t = traduction_publiee($objet, $id, $champ, $langue);
            if ($t !== '' && $t !== $texte) $v[$langue] = $t;
        }
    }
    return count($v) === 1 ? $texte : $v;
}

/** Comme multi(), à partir d'un texte de référence et de ses traductions [langue => texte]. */
function multi_valeurs(string $texte, array $traductions)
{
    $texte = trim($texte);
    $v = [langue_reference() => $texte];
    foreach (langues_autres() as $langue) {
        $t = trim((string) ($traductions[$langue] ?? ''));
        if ($t !== '' && $t !== $texte) $v[$langue] = $t;
    }
    return count($v) === 1 ? $texte : $v;
}

/**
 * Les champs traduisibles d'une ligne, dans chaque langue des contenus, tels qu'un formulaire les
 * envoie : ['titre_fr' => …, 'titre_en' => …, 'titre_de' => …]. Les autres langues viennent de la
 * table des traductions (texte saisi, proposition de l'IA comprise).
 */
function ligne_langues(string $objet, array $ligne, array $champs): array
{
    $reference = langue_reference();
    $v = [];
    foreach ($champs as $champ) {
        foreach (langues() as $langue) {
            $v[$champ . '_' . $langue] = $langue === $reference
                ? (string) ($ligne[colonne_reference($objet, $champ)] ?? '')
                : (string) (traduction($objet, $ligne[cle_primaire($objet)] ?? '', $champ, $langue)['texte'] ?? '');
        }
    }
    return $v;
}

// ---------- Écrire les traductions ----------

/** Texte de référence d'un champ, relu dans la table. */
function reference_lire(string $objet, string $id, string $champ): string
{
    if (!isset(TRADUISIBLES[$objet])) return '';
    return (string) bd_valeur('SELECT ' . bd_nom(colonne_reference($objet, $champ)) . ' FROM ' . bd_nom($objet) . ' WHERE ' . bd_nom(cle_primaire($objet)) . ' = ?', [$id]);
}

/** Tient à jour l'ancienne colonne « _en » d'une table, quand elle existe : la copie de ce qui sort sur le site. */
function traduction_miroir(string $objet, string $id, string $champ, string $langue): void
{
    $col = colonne_langue($objet, $champ, $langue);
    if (!in_array($col, bd_colonnes($objet), true)) return;
    $t = bd_ligne('SELECT texte, statut FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = ?', [$objet, $id, $champ, $langue]);
    $texte = ($t && $t['statut'] === 'validee') ? (string) $t['texte'] : '';
    bd_requete('UPDATE ' . bd_nom($objet) . ' SET ' . bd_nom($col) . ' = ? WHERE ' . bd_nom(cle_primaire($objet)) . ' = ?', [$texte, $id]);
}

/**
 * Pose une traduction. $origine : main (saisie par une personne) ou ia.
 *
 * Saisie par une personne : le texte est validé, sauf s'il est identique à une proposition de l'IA
 * encore en attente (la proposition reste alors à valider, d'un geste exprès). Venue de l'IA : la
 * proposition est notée « proposee » ; une traduction déjà validée n'est jamais remplacée, sauf si
 * $forcer. Un texte vide retire la traduction. Rend vrai si quelque chose a changé.
 */
function traduction_poser(string $objet, string $id, string $champ, string $langue, string $texte, string $origine = 'main', bool $forcer = false): bool
{
    if ($langue === langue_reference() || !isset(TRADUISIBLES[$objet][3][$champ])) return false;
    $texte = trim(str_replace("\r\n", "\n", $texte));
    $ou = [$objet, $id, $champ, $langue];
    $avant = bd_ligne('SELECT * FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = ?', $ou);
    if ($texte === '') {
        if (!$avant || $origine === 'ia') return false;
        bd_requete('DELETE FROM traductions WHERE id = ?', [$avant['id']]);
    } elseif ($origine === 'ia') {
        if ($avant && $avant['statut'] === 'validee' && trim((string) $avant['texte']) !== '' && !$forcer) return false;
        if ($avant && $avant['statut'] === 'proposee' && $avant['texte'] === $texte) return false;
        $empreinte = md5(reference_lire($objet, $id, $champ));
        bd_requete('INSERT INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, ?, ?, \'proposee\', \'ia\', ?)
                    ON DUPLICATE KEY UPDATE texte = VALUES(texte), statut = VALUES(statut), origine = VALUES(origine), empreinte = VALUES(empreinte)',
            array_merge($ou, [$texte, $empreinte]));
    } else {
        if ($avant && $avant['texte'] === $texte) return false;        // rien de neuf : une proposition reste une proposition
        $empreinte = md5(reference_lire($objet, $id, $champ));
        bd_requete('INSERT INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, ?, ?, \'validee\', \'main\', ?)
                    ON DUPLICATE KEY UPDATE texte = VALUES(texte), statut = VALUES(statut), origine = VALUES(origine), empreinte = VALUES(empreinte)',
            array_merge($ou, [$texte, $empreinte]));
    }
    $GLOBALS['traductions_perimees'] = true;
    $GLOBALS['donnees_modifiees'] = true;
    traduction_miroir($objet, $id, $champ, $langue);
    return true;
}

/** Pose d'un coup les traductions d'une ligne : [champ => [langue => texte]]. */
function traductions_poser(string $objet, string $id, array $par_champ, string $origine = 'main'): void
{
    foreach ($par_champ as $champ => $par_langue) {
        foreach ($par_langue as $langue => $texte) traduction_poser($objet, $id, $champ, $langue, (string) $texte, $origine);
    }
}

/**
 * Valide une traduction telle qu'elle est : une proposition de l'IA devient une traduction validée,
 * une traduction « à revoir » est confirmée au regard du texte de référence d'aujourd'hui.
 */
function traduction_valider(string $objet, string $id, string $champ, string $langue): bool
{
    $t = bd_ligne('SELECT * FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = ?', [$objet, $id, $champ, $langue]);
    if (!$t || trim((string) $t['texte']) === '') return false;
    bd_requete("UPDATE traductions SET statut = 'validee', empreinte = ? WHERE id = ?", [md5(reference_lire($objet, $id, $champ)), $t['id']]);
    $GLOBALS['traductions_perimees'] = true;
    $GLOBALS['donnees_modifiees'] = true;
    traduction_miroir($objet, $id, $champ, $langue);
    return true;
}

/** Retire toutes les traductions d'une ligne supprimée. */
function traductions_retirer(string $objet, string $id): void
{
    bd_requete('DELETE FROM traductions WHERE objet = ? AND objet_id = ?', [$objet, $id]);
    $GLOBALS['traductions_perimees'] = true;
}

/**
 * Traite les cases « valider » des formulaires (champ tr_ok[] = « objet|clé|champ|langue ») :
 * appelé par index.php avant la page, pour tout envoi de formulaire.
 */
function traductions_valider_recues(): void
{
    $recues = $_POST['tr_ok'] ?? [];
    if (!is_array($recues)) return;
    foreach ($recues as $r) {
        $p = explode('|', (string) $r);
        if (count($p) !== 4 || !isset(TRADUISIBLES[$p[0]][3][$p[2]])) continue;
        // si le texte a été retouché dans le même envoi, l'enregistrement de la page le validera
        $nom = (string) ($_POST['tr_nom'][$r] ?? '');
        $t = traduction($p[0], $p[1], $p[2], $p[3]);
        if ($t && traduction_valider($p[0], $p[1], $p[2], $p[3])) {
            journaliser('validation', 'traduction', null, (TRADUISIBLES[$p[0]][0] ?? $p[0]) . ' · ' . (TRADUISIBLES[$p[0]][3][$p[2]] ?? $p[2]) . ' · ' . strtoupper($p[3]) . ($nom !== '' ? ' · ' . $nom : ''), null, ['texte' => couper((string) $t['texte'], 200)]);
        }
    }
}

// ---------- Vue d'ensemble : ce qui manque, ce qui attend ----------

/**
 * Tous les champs traduisibles qui portent un texte de référence, avec l'état de leur traduction
 * dans une langue. Rend [[objet, clé, champ, nom de la ligne, référence, texte, statut], …].
 */
function traductions_inventaire(string $langue, ?string $objet = null): array
{
    $l = [];
    foreach (TRADUISIBLES as $table => [$nom, $cle, $col_nom, $champs]) {
        if ($objet !== null && $objet !== $table) continue;
        if (!bd_table_existe($table)) continue;
        $lignes = bd_lignes('SELECT * FROM ' . bd_nom($table) . ' ORDER BY ' . bd_nom($cle));
        foreach ($lignes as $ligne) {
            foreach ($champs as $champ => $libelle) {
                $reference = trim((string) ($ligne[colonne_reference($table, $champ)] ?? ''));
                if ($reference === '') continue;
                $t = traduction($table, $ligne[$cle], $champ, $langue);
                $l[] = [
                    'objet' => $table, 'id' => (string) $ligne[$cle], 'champ' => $champ, 'libelle' => $libelle,
                    'nom' => (string) ($ligne[$col_nom] ?? $ligne[$cle]), 'reference' => $reference,
                    'texte' => (string) ($t['texte'] ?? ''), 'statut' => traduction_statut($t, $reference),
                    'origine' => (string) ($t['origine'] ?? ''),
                ];
            }
        }
    }
    return $l;
}

/** Décompte par statut d'un inventaire : ['validee' => n, 'proposee' => n, 'a_revoir' => n, 'manquante' => n]. */
function traductions_decompte(array $inventaire): array
{
    $n = ['validee' => 0, 'proposee' => 0, 'a_revoir' => 0, 'manquante' => 0];
    foreach ($inventaire as $x) $n[$x['statut']]++;
    return $n;
}
