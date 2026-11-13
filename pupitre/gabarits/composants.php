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
    return 'c-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $nom), '-');
}

/**
 * Champ sur une ligne. Options : type (text, url, email, date, number), aide, requis, liste
 * (identifiant d'une liste de choix), max, indication (texte en filigrane), prefixe, classe.
 */
function champ(string $nom, string $libelle, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_champ($n);
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
    $id = id_champ($n);
    $lignes = $o['lignes'] ?? max(2, min(12, substr_count((string) $valeur, "\n") + 2));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . '</label>';
    $s .= '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . (int) $lignes . '"' . (!empty($o['aide']) ? ' aria-describedby="' . $id . '-aide"' : '') . '>' . e($valeur) . '</textarea>';
    if (!empty($o['aide'])) $s .= '<p class="aide" id="' . $id . '-aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

/** Champ en deux langues, côte à côte : colonnes nom_fr et nom_en. Option texte : zones de plusieurs lignes. */
function champ_bi(string $nom, string $libelle, $fr, $en, array $o = []): string
{
    $s = '<fieldset class="bi"><legend>' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</legend><div class="bi__paire">';
    foreach (['fr' => ['Français', $fr], 'en' => ['Anglais', $en]] as $langue => [$nom_langue, $valeur]) {
        $n = nom_champ($o['prefixe'] ?? '', $nom . '_' . $langue);
        $id = id_champ($n);
        $s .= '<div class="bi__langue"><label for="' . $id . '"><abbr title="' . $nom_langue . '">' . strtoupper($langue) . '</abbr></label>';
        if (!empty($o['texte'])) {
            $lignes = max(2, min(14, substr_count((string) $valeur, "\n") + 2 + (int) (mb_strlen((string) $valeur) / 90)));
            $s .= '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . $lignes . '" lang="' . $langue . '">' . e($valeur) . '</textarea>';
        } else {
            $s .= '<input type="text" id="' . $id . '" name="' . e($n) . '" value="' . e($valeur) . '" lang="' . $langue . '"'
                . ($langue === 'fr' && !empty($o['requis']) ? ' required' : '')
                . (!empty($o['liste']) && $langue === 'fr' ? ' list="' . e($o['liste']) . '"' : '')
                . ($langue === 'en' ? ' placeholder="comme en français"' : '') . '>';
        }
        $s .= '</div>';
    }
    $s .= '</div>';
    if (!empty($o['aide'])) $s .= '<p class="aide">' . e($o['aide']) . '</p>';
    return $s . '</fieldset>';
}

/** Liste de choix. $options : valeur => libellé. Option vide : libellé du choix « aucun ». */
function champ_choix(string $nom, string $libelle, array $options, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_champ($n);
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
    $id = id_champ($n);
    return '<div class="champ champ--case"><input type="checkbox" id="' . $id . '" name="' . e($n) . '" value="1"' . ($coche ? ' checked' : '') . '> <label for="' . $id . '">' . e($libelle) . '</label></div>';
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
        . '<button type="button" class="outil" data-monter aria-label="Monter ' . e($quoi) . '" title="Monter">↑</button>'
        . '<button type="button" class="outil" data-descendre aria-label="Descendre ' . e($quoi) . '" title="Descendre">↓</button>'
        . '<button type="button" class="outil outil--retirer" data-retirer aria-label="Retirer ' . e($quoi) . '" title="Retirer">×</button>'
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

/** Bloc d'aperçu : le HTML que le site affichera, en français et en anglais. $zones : fr => lignes, en => lignes. */
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
function liste_formulaire(array $liste, array $lignes, string $nom = 'lignes'): string
{
    $rendre = function (array $l, string $i) use ($liste, $nom): string {
        $prefixe = $nom . '[' . $i . ']';
        $s = '<div class="ligne" data-ligne><div class="ligne__tete"><span class="ligne__rang">nº <span data-rang></span></span>' . boutons_ligne() . '</div>';
        $s .= '<input type="hidden" name="' . e($prefixe) . '[id]" value="' . e($l['id'] ?? '') . '">';
        $rang = '';
        foreach ($liste['champs'] as $champ) {
            [$c, $type, $libelle] = $champ;
            $o = ['prefixe' => $prefixe, 'aide' => $champ['aide'] ?? '', 'requis' => !empty($champ['requis']), 'max' => $champ['max'] ?? 0];
            // les champs qui portent le même « rang » se suivent sur une ligne
            $nouveau = $champ['rang'] ?? '';
            if ($nouveau !== $rang) {
                if ($rang !== '') $s .= '</div>';
                if ($nouveau !== '') $s .= '<div class="colonnes">';
                $rang = $nouveau;
            }
            switch ($type) {
                case 'bi': $s .= champ_bi($c, $libelle, $l[$c . '_fr'] ?? '', $l[$c . '_en'] ?? '', $o); break;
                case 'bi-texte': $s .= champ_bi($c, $libelle, $l[$c . '_fr'] ?? '', $l[$c . '_en'] ?? '', $o + ['texte' => true]); break;
                case 'texte': $s .= champ_texte($c, $libelle, $l[$c] ?? '', $o); break;
                case 'case': $s .= champ_case($c, $libelle, !empty($l[$c]), $o); break;
                case 'fiche': case 'media': case 'choix':
                    $choix = isset($champ['defaut']) ? $o : $o + ['vide' => $type === 'media' ? '— aucun fichier —' : '— choisir —'];
                    $s .= champ_choix($c, $libelle, champ_options($champ), $l[$c] ?? ($champ['defaut'] ?? ''), $choix);
                    break;
                case 'nombre': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['classe' => 'champ--court']); break;
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
    $d = json_decode(json_encode($exports), true);
    $par_langue = [];
    foreach (langues() as $langue) {
        $toutes = repli_zones($exports, $langue);
        // l'aperçu montre les disques et la presse en entier, pas seulement le repli de l'accueil
        $toutes['DISQUES'] = repli_disques($d['DISQUES'] ?? [], $langue, true);
        $toutes['PRESSE'] = repli_presse($d['PRESSE'] ?? [], $langue, true);
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
