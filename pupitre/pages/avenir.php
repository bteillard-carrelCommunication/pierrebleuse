<?php
/**
 * Anciennes « places gardées » du menu (version 1). Le pilotage de la communication a désormais ses
 * écrans : les anciennes adresses y mènent. La veille de presse et les statistiques restent hors de
 * cette version ; elles renvoient au tableau de bord éditorial.
 */

defined('PUPITRE') or exit;

$vers = ['publications' => 'social', 'lettre' => 'newsletter'];
rediriger(url($vers[recu('quoi')] ?? 'editorial'));
