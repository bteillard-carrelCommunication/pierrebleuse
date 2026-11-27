<?php
/**
 * Composants d'interface : champs de formulaire, étiquettes, listes ordonnables.
 * Tout ce qui sort passe par e().
 */

defined('PUPITRE') or exit;

/** Nom d'un champ, éventuellement dans une ligne de liste : nom_champ('lignes[3]', 'titre') → lignes[3][titre]. */
function nom_champ(string $prefixe, string $nom): string
{
    return $prefixe === '' ? $nom : $prefixe . '[' . $nom . ']';
}

function id_champ(string $nom): string
{
    // le jeton « __i__ » du modèle d'une ligne ajoutée est gardé : le script le remplace par le rang de la ligne, l'identifiant reste unique
    $parties = array_map(fn($p) => trim(preg_replace('/[^a-z0-9]+/i', '-', $p), '-'), explode('__i__', $nom));
    return 'c-' . implode('-__i__-', array_filter($parties, 'strlen') ?: ['']);
}

/**
 * Un identifiant qui ne sert qu'une fois dans la page : quand deux formulaires portent un champ du
 * même nom, le second reçoit un suffixe. Sans cela, le libellé du second désignerait le champ du premier.
 */
function id_unique(string $id): string
{
    static $vus = [];
    $vus[$id] = ($vus[$id] ?? 0) + 1;
    return $vus[$id] === 1 ? $id : $id . '-' . $vus[$id];
}

/**
 * Champ sur une ligne. Options : type (text, url, email, date, number), aide, requis, liste
 * (identifiant d'une liste de choix), max, indication (texte en filigrane), prefixe, classe.
 */
function champ(string $nom, string $libelle, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</label>';
    $s .= '<input type="' . e($o['type'] ?? 'text') . '" id="' . $id . '" name="' . e($n) . '" value="' . e($valeur) . '"'
        . (!empty($o['requis']) ? ' required' : '')
        . (!empty($o['liste']) ? ' list="' . e($o['liste']) . '"' : '')
        . (!empty($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '')
        . (!empty($o['indication']) ? ' placeholder="' . e($o['indication']) . '"' : '')
        . (!empty($o['aide']) ? ' aria-describedby="' . $id . '-aide"' : '') . '>';
    if (!empty($o['aide'])) $s .= '<p class="aide" id="' . $id . '-aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

function champ_texte(string $nom, string $libelle, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $lignes = $o['lignes'] ?? max(2, min(12, substr_count((string) $valeur, "\n") + 2));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . '</label>';
    $s .= '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . (int) $lignes . '"' . (!empty($o['aide']) ? ' aria-describedby="' . $id . '-aide"' : '') . '>' . e($valeur) . '</textarea>';
    if (!empty($o['aide'])) $s .= '<p class="aide" id="' . $id . '-aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

/**
 * Champ traduisible : la langue de référence, puis chaque autre langue des contenus, côte à côte.
 * Les champs envoyés s'appellent nom_fr, nom_en, nom_de… ; bd_inserer() et bd_modifier() rangent
 * chaque langue à sa place (inclus/langues.php).
 *
 * $fr : le texte de référence. $en : le texte anglais connu de l'appelant (anciens écrans).
 * Options : texte (zones de plusieurs lignes), requis, liste, aide, prefixe ;
 *   objet    [table, clé de la ligne, champ] : les traductions et leur statut sont lus dans la base,
 *            propositions de l'IA comprises ; le champ vaut $nom s'il n'est pas donné ;
 *   valeurs  [langue => texte] : ce qui vient d'être saisi, à réafficher après une erreur.
 */
function champ_bi(string $nom, string $libelle, $fr, $en = '', array $o = []): string
{
    $reference = langue_reference();
    $objet = $o['objet'] ?? null;
    if ($objet && ($objet[1] === null || $objet[1] === '')) $objet = null;
    $champ_objet = $objet[2] ?? $nom;
    $noms = [];
    $s = '<fieldset class="bi"><legend>' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</legend>'
        . '<div class="bi__paire bi__paire--' . count(langues()) . '">';
    foreach (langues() as $langue) {
        $t = null;
        if ($langue === $reference) {
            $valeur = $fr;
        } elseif (isset($o['valeurs']) && array_key_exists($langue, $o['valeurs'])) {
            $valeur = $o['valeurs'][$langue];
        } elseif ($objet) {
            $t = traduction($objet[0], $objet[1], $champ_objet, $langue);
            $valeur = $t['texte'] ?? ($langue === 'en' ? $en : '');
        } else {
            $valeur = $langue === 'en' ? $en : '';
        }
        $n = nom_champ($o['prefixe'] ?? '', $nom . '_' . $langue);
        $id = id_unique(id_champ($n));
        $s .= '<div class="bi__langue"><label for="' . $id . '"><abbr title="' . e(langue_nom($langue)) . '">' . e(strtoupper($langue)) . '</abbr></label>';
        if (!empty($o['texte'])) {
            $lignes = max(2, min(14, substr_count((string) $valeur, "\n") + 2 + (int) (mb_strlen((string) $valeur) / 90)));
            $s .= '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . $lignes . '" lang="' . e($langue) . '">' . e($valeur) . '</textarea>';
        } else {
            $s .= '<input type="text" id="' . $id . '" name="' . e($n) . '" value="' . e($valeur) . '" lang="' . e($langue) . '"'
                . ($langue === $reference && !empty($o['requis']) ? ' required' : '')
                . (!empty($o['liste']) && $langue === $reference ? ' list="' . e($o['liste']) . '"' : '')
                . ($langue !== $reference ? ' placeholder="comme en ' . e(mb_strtolower(langue_nom($reference))) . '"' : '') . '>';
        }
        // statut de la traduction : une proposition de l'IA ou une traduction à revoir se valide d'une case
        if ($t) {
            $statut = traduction_statut($t, (string) $fr);
            if ($statut === 'proposee' || $statut === 'a_revoir') {
                $jeton = implode('|', [$objet[0], $objet[1], $champ_objet, $langue]);
                $s .= '<p class="bi__statut"><span class="puce puce--alerte">' . ($statut === 'proposee' ? 'proposée par l\'IA, pas encore sur le site' : 'à revoir : le texte de référence a changé') . '</span> '
                    . '<label class="bi__valider"><input type="checkbox" name="tr_ok[]" value="' . e($jeton) . '"> ' . ($statut === 'proposee' ? 'valider' : 'toujours bonne') . '</label></p>';
            }
        }
        $s .= '</div>';
    }
    $s .= '</div>';
    if (!empty($o['aide'])) $s .= '<p class="aide">' . e($o['aide']) . '</p>';
    return $s . '</fieldset>';
}

/** Les champs nom_fr, nom_en, nom_de… lus dans un envoi de formulaire : [colonne => texte], coupés à $max. */
function recu_bi(array $recu, string $nom, int $max = 500): array
{
    $v = [];
    foreach (langues() as $langue) {
        $v[$nom . '_' . $langue] = mb_substr(trim(str_replace("\r\n", "\n", (string) ($recu[$nom . '_' . $langue] ?? ''))), 0, $max);
    }
    return $v;
}

/** Traductions saisies d'un champ, à repasser à champ_bi(['valeurs' => …]) après une erreur. */
function saisie_bi(array $recu, string $nom): array
{
    $v = [];
    foreach (langues_autres() as $langue) if (array_key_exists($nom . '_' . $langue, $recu)) $v[$langue] = (string) $recu[$nom . '_' . $langue];
    return $v;
}

/** Liste de choix. $options : valeur => libellé. Option vide : libellé du choix « aucun ». */
function champ_choix(string $nom, string $libelle, array $options, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</label>';
    $s .= '<select id="' . $id . '" name="' . e($n) . '">';
    if (array_key_exists('vide', $o)) $s .= '<option value="">' . e($o['vide']) . '</option>';
    foreach ($options as $v => $l) {
        $s .= '<option value="' . e($v) . '"' . ((string) $v === (string) $valeur ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    $s .= '</select>';
    if (!empty($o['aide'])) $s .= '<p class="aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

function champ_case(string $nom, string $libelle, bool $coche, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    return '<div class="champ champ--case"><input type="checkbox" id="' . $id . '" name="' . e($n) . '" value="1"' . ($coche ? ' checked' : '') . '> <label for="' . $id . '">' . e($libelle) . '</label>'
        . (!empty($o['aide']) ? '<p class="aide">' . e($o['aide']) . '</p>' : '') . '</div>';
}

/** Liste de valeurs proposées à la saisie (annuaire…). */
function liste_choix(string $id, array $valeurs): string
{
    $s = '<datalist id="' . e($id) . '">';
    foreach ($valeurs as $v) $s .= '<option value="' . e($v) . '">';
    return $s . '</datalist>';
}

function etiquette_statut(string $statut): string
{
    return '<span class="statut statut--' . e($statut) . '" title="' . e(STATUTS_SENS[$statut] ?? '') . '">' . e(STATUTS[$statut] ?? $statut) . '</span>';
}

/** Boutons d'une ligne de liste ordonnable : monter, descendre, retirer. Utilisables au clavier. */
function boutons_ligne(string $quoi = 'cette ligne'): string
{
    return '<span class="ligne__outils">'
        . '<button type="button" class="outil" data-monter aria-label="Monter ' . e($quoi) . '" title="Monter">' . icone('monter') . '</button>'
        . '<button type="button" class="outil" data-descendre aria-label="Descendre ' . e($quoi) . '" title="Descendre">' . icone('descendre') . '</button>'
        . '<button type="button" class="outil outil--retirer" data-retirer aria-label="Retirer ' . e($quoi) . '" title="Retirer">' . icone('fermer') . '</button>'
        . '</span>';
}

/** Suivi d'une tâche de fond : barre d'avancement tenue à jour par ressources/js/pupitre.js. */
function suivi_tache(array $t): string
{
    $fini = in_array($t['statut'], ['terminee', 'echec'], true);
    return '<div class="tache tache--' . e($t['statut']) . '" data-tache="' . (int) $t['id'] . '"' . ($fini ? ' data-finie' : '') . ' role="status" aria-live="polite">'
        . '<div class="tache__barre"><span style="width:' . (int) $t['avancement'] . '%"></span></div>'
        . '<p class="tache__message">' . e($t['message']) . '</p></div>';
}

/** Bloc d'aperçu : le HTML que le site affichera, dans chaque langue. $par_langue : fr => lignes, en => lignes… */
function apercu(array $par_langue, string $titre = 'Aperçu'): string
{
    $s = '<section class="apercu" aria-label="' . e($titre) . '"><h2>' . e($titre) . '</h2><div class="apercu__langues">';
    $site = site_adresse();
    foreach ($par_langue as $langue => $lignes) {
        // les images du site sont lues sur le site ; un média pas encore publié n'y est pas
        $html = preg_replace('/\ssrc="(?!https?:|\/|data:)/', ' src="' . e($site), implode("\n", $lignes));
        $s .= '<div class="apercu__langue" lang="' . e($langue) . '"><p class="apercu__code">' . strtoupper(e($langue)) . '</p><div class="apercu__site">' . $html . '</div></div>';
    }
    return $s . '</div><p class="aide">L\'aperçu montre le contenu, pas la mise en page du site.</p></section>';
}

/** Lignes du journal, lisibles : qui, quoi, quand, avant, après. */
function journal_afficher(array $lignes, bool $avec_objet = false): string
{
    if (!$lignes) return '<p class="rien">Rien pour l\'instant.</p>';
    $actions = ['creation' => 'Création', 'modification' => 'Modification', 'suppression' => 'Suppression', 'statut' => 'Statut',
        'publication' => 'Publication', 'retour' => 'Retour en arrière', 'validation' => 'Validation', 'note' => 'Note'];
    $s = '<ul class="journal">';
    foreach ($lignes as $l) {
        $avant = json_decode((string) $l['avant'], true);
        $apres = json_decode((string) $l['apres'], true);
        $s .= '<li><strong>' . e($actions[$l['action']] ?? $l['action']) . '</strong>'
            . ($avec_objet ? ' · ' . e($l['objet_type']) : '') . ' · ' . e($l['objet_libelle'])
            . '<br><span class="journal__qui">' . e($l['compte_nom'] ?: '—') . ', ' . e(horodatage_fr($l['cree_le'])) . '</span>';
        $cles = array_unique(array_merge(array_keys(is_array($avant) ? $avant : []), array_keys(is_array($apres) ? $apres : [])));
        if ($cles && $l['action'] !== 'creation') {
            $s .= '<div class="journal__diff">';
            foreach ($cles as $k) {
                $s .= '<div><span class="discret">' . e($k) . ' :</span> ';
                if (is_array($avant) && array_key_exists($k, $avant)) $s .= '<span class="avant">' . e(couper(journal_valeur($avant[$k]), 240)) . '</span> ';
                if (is_array($apres) && array_key_exists($k, $apres)) $s .= '<span class="apres">' . e(couper(journal_valeur($apres[$k]), 240)) . '</span>';
                $s .= '</div>';
            }
            $s .= '</div>';
        }
        $s .= '</li>';
    }
    return $s . '</ul>';
}

/**
 * Formulaire d'une liste ordonnable décrite dans inclus/contenus.php : une ligne par élément, ses
 * champs, les boutons d'ordre, un modèle pour les lignes ajoutées.
 */
function liste_formulaire(array $liste, array $lignes, string $nom = 'lignes', bool $saisie = false): string
{
    $rendre = function (array $l, string $i) use ($liste, $nom, $saisie): string {
        $prefixe = $nom . '[' . $i . ']';
        $s = '<div class="ligne" data-ligne><div class="ligne__tete"><span class="ligne__rang">nº <span data-rang></span></span>' . boutons_ligne() . '</div>';
        $s .= '<input type="hidden" name="' . e($prefixe) . '[id]" value="' . e($l['id'] ?? '') . '">';
        $rang = '';
        foreach ($liste['champs'] as $champ) {
            [$c, $type, $libelle] = $champ;
            $o = ['prefixe' => $prefixe, 'aide' => $champ['aide'] ?? '', 'requis' => !empty($champ['requis']), 'max' => $champ['max'] ?? 0];
            // champ traduisible : après une erreur, ce qui vient d'être saisi ; sinon les traductions de la ligne, lues dans la base
            $langues = $saisie ? ['valeurs' => saisie_bi($l, $c)] : ['objet' => [$liste['table'], $l['id'] ?? null, $c]];
            // les champs qui portent le même « rang » se suivent sur une ligne
            $nouveau = $champ['rang'] ?? '';
            if ($nouveau !== $rang) {
                if ($rang !== '') $s .= '</div>';
                if ($nouveau !== '') $s .= '<div class="colonnes">';
                $rang = $nouveau;
            }
            switch ($type) {
                case 'bi': $s .= champ_bi($c, $libelle, $l[$c . '_fr'] ?? '', $l[$c . '_en'] ?? '', $o + $langues); break;
                case 'bi-texte': $s .= champ_bi($c, $libelle, $l[$c . '_fr'] ?? '', $l[$c . '_en'] ?? '', $o + $langues + ['texte' => true]); break;
                case 'texte': $s .= champ_texte($c, $libelle, $l[$c] ?? '', $o); break;
                case 'case':
                    // ligne nouvelle : la valeur par défaut de la case ; ligne saisie ou lue : ce qu'elle dit
                    $s .= champ_case($c, $libelle, ($saisie || array_key_exists($c, $l)) ? !empty($l[$c]) : !empty($champ['defaut']), $o);
                    break;
                case 'fiche': case 'media': case 'choix':
                    $choix = isset($champ['vide']) ? $o + ['vide' => $champ['vide']]
                        : (isset($champ['defaut']) ? $o : $o + ['vide' => $type === 'media' ? '— aucun fichier —' : '— choisir —']);
                    $s .= champ_choix($c, $libelle, champ_options($champ), $l[$c] ?? ($champ['defaut'] ?? ''), $choix);
                    break;
                case 'nombre': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['classe' => 'champ--court']); break;
                case 'decimal':
                    $valeur = (string) ($l[$c] ?? '');
                    if (str_contains($valeur, '.')) $valeur = rtrim(rtrim($valeur, '0'), '.');      // 52.37000 → 52.37
                    $s .= champ($c, $libelle, $valeur, $o + ['classe' => 'champ--court']);
                    break;
                case 'adresse': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['type' => 'url', 'indication' => 'https://…']); break;
                case 'email': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['type' => 'email']); break;
                default:
                    $liste_id = '';
                    if (!empty($champ['suggestions'])) { $liste_id = 'sug-' . id_champ($nom . $c); }
                    $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['liste' => $liste_id]);
            }
        }
        if ($rang !== '') $s .= '</div>';
        return $s . '</div>';
    };
    $s = '<div data-liste data-suivant="0"><div class="lignes" data-lignes>';
    foreach (array_values($lignes) as $i => $l) $s .= $rendre($l, (string) $i);
    $s .= '</div><template data-modele>' . $rendre([], '__i__') . '</template>';
    foreach ($liste['champs'] as $champ) {
        if (!empty($champ['suggestions'])) $s .= liste_choix('sug-' . id_champ($nom . $champ[0]), $champ['suggestions']);
    }
    $s .= '<p><button type="button" class="bouton bouton--petit" data-ajouter>' . e($liste['ajout'] ?? 'Ajouter une ligne') . '</button></p></div>';
    return $s;
}

/** Aperçu des zones du site nommées, dans chaque langue des contenus. $zones : repère => intertitre. */
function apercu_zones(array $zones, ?array $exports = null): string
{
    $exports = $exports ?? site_exports();
    $par_langue = [];
    foreach (langues() as $langue) {
        $toutes = repli_zones($exports, $langue);
        $lignes = [];
        foreach ($zones as $nom => $intertitre) {
            if ($intertitre !== '') $lignes[] = '<p class="mention">' . e($intertitre) . '</p>';
            $corps = $toutes[$nom] ?? [];
            $liste = $corps && str_starts_with(ltrim($corps[0]), '<li');
            $lignes[] = $liste ? '<ul>' . implode('', $corps) . '</ul>' : implode("\n", $corps);
            if (!$corps) $lignes[] = '<p class="avenir">vide</p>';
        }
        $par_langue[$langue] = $lignes;
    }
    return apercu($par_langue);
}

// ---------- Composants du design system (montrés dans « Bibliothèque d'interface ») ----------

const MOIS_COURTS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

/**
 * État vide : un pictogramme, un titre, une phrase, et de quoi agir. À employer quand une liste n'a
 * rien à montrer : dire pourquoi, et quoi faire. $action : du HTML déjà échappé (un lien, un bouton).
 */
function etat_vide(string $icone, string $titre, string $texte = '', string $action = ''): string
{
    return '<div class="vide"><div class="vide__icone">' . icone($icone) . '</div><p class="vide__titre">' . e($titre) . '</p>'
        . ($texte !== '' ? '<p>' . e($texte) . '</p>' : '') . ($action !== '' ? '<p>' . $action . '</p>' : '') . '</div>';
}

/** Pastille de date : le jour et le mois abrégé. $classe : pastille-date--jour (aujourd'hui), pastille-date--alerte. */
function pastille_date(string $iso, string $classe = ''): string
{
    $t = strtotime(substr($iso, 0, 10) . ' 12:00');
    if (!$t) return '';
    return '<time class="pastille-date' . ($classe !== '' ? ' ' . e($classe) : '') . '" datetime="' . e(substr($iso, 0, 10)) . '"><strong>' . (int) date('j', $t) . '</strong><span>' . e(MOIS_COURTS[(int) date('n', $t) - 1]) . '</span></time>';
}

/**
 * Indicateur : un libellé, une valeur, une précision. Options : lien (la tuile devient un lien),
 * icone, classe (indicateur--alerte, indicateur--ok). La valeur est un fait lu dans la base, jamais une estimation.
 */
function indicateur(string $libelle, $valeur, string $precision = '', array $o = []): string
{
    $balise = !empty($o['lien']) ? 'a' : 'div';
    return '<' . $balise . ' class="indicateur' . (!empty($o['classe']) ? ' ' . e($o['classe']) : '') . '"' . (!empty($o['lien']) ? ' href="' . e($o['lien']) . '"' : '') . '>'
        . '<span class="indicateur__libelle">' . (!empty($o['icone']) ? icone($o['icone']) : '') . e($libelle) . '</span>'
        . '<span class="indicateur__valeur">' . e((string) $valeur) . '</span>'
        . ($precision !== '' ? '<span class="indicateur__precision">' . e($precision) . '</span>' : '') . '</' . $balise . '>';
}

/**
 * Tuile : un pictogramme dans sa bulle, un titre, un détail, et un chiffre facultatif. Options : lien,
 * chiffre, classe (tuile--vigilance, tuile--actions, tuile--calme, tuile--plein).
 */
function tuile(string $icone, string $titre, string $detail, array $o = []): string
{
    $balise = !empty($o['lien']) ? 'a' : 'div';
    return '<' . $balise . ' class="tuile' . (!empty($o['classe']) ? ' ' . e($o['classe']) : '') . '"' . (!empty($o['lien']) ? ' href="' . e($o['lien']) . '"' : '') . '>'
        . '<span class="tuile__bulle">' . icone($icone) . '</span>'
        . '<span class="tuile__textes"><span class="tuile__titre">' . e($titre) . '</span><span class="tuile__detail">' . e($detail) . '</span></span>'
        . (isset($o['chiffre']) ? '<span class="tuile__chiffre">' . e((string) $o['chiffre']) . '</span>' : '') . '</' . $balise . '>';
}

/** Le canal d'une action de communication : son pictogramme dans une bulle, son nom. */
function canal_afficher(string $canal, bool $nom = true): string
{
    [$libelle, $icone] = CANAUX[$canal] ?? ['Canal inconnu', 'social'];
    return '<span class="canal"><span class="canal__bulle">' . icone($icone) . '</span>' . ($nom ? e($libelle) : '<span class="sr">' . e($libelle) . '</span>') . '</span>';
}

/** Une notification, telle que le centre de notifications et son écran la montrent. $actions : du HTML déjà échappé. */
function notification_afficher(array $n, string $actions = ''): string
{
    $niveaux = ['prioritaire' => 'prioritaire', 'action' => 'à traiter', 'info' => 'information'];
    return '<div class="notif notif--' . e($n['niveau']) . '"><span class="notif__icone">' . icone($n['icone']) . '</span>'
        . '<span class="notif__type">' . e($n['type']) . ' <span class="niveau niveau--' . e($n['niveau']) . '">' . e($niveaux[$n['niveau']] ?? '') . '</span></span>'
        . '<span class="notif__message">' . e($n['message']) . '</span>'
        . ($n['quand'] ? '<span class="notif__quand">' . e(il_y_a($n['quand'])) . '</span>' : '')
        . ($actions !== '' ? '<div class="notif__actions">' . $actions . '</div>' : '') . '</div>';
}
