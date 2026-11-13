<?php
/**
 * Ce que l'IA rendrait à la lecture de tests/extrait-fictif.txt : un extrait fictif, fabriqué
 * pour les tests. Sert à tests/import.php, qui éprouve le code sans appeler l'IA.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$rep = fn(string $date, ?string $heure, ?string $salle, ?string $ville, ?string $pays) => compact('date', 'heure', 'salle', 'ville', 'pays');
$eng = fn(?string $titre, string $type, ?string $ensemble, array $reps, array $programme = [], array $solistes = [], ?string $mentions = null)
    => ['titre' => $titre, 'type' => $type, 'ensemble' => $ensemble, 'mentions' => $mentions, 'representations' => $reps, 'programme' => $programme, 'solistes' => $solistes];
$oeuvre = fn(string $c, string $o, ?string $m = null) => ['compositeur' => $c, 'oeuvre' => $o, 'mention' => $m];

$tournee = [];
foreach (range(5, 16) as $i => $j) $tournee[] = $rep(sprintf('2026-12-%02d', $j), $i < 8 ? '20:00' : null, null, 'Ville-fictive-' . chr(65 + $i), 'France');
return [
    'date_extrait' => '2026-09-21',
    'engagements' => [
        $eng(null, 'concert', 'Ensemble fictif des Lisières', [$rep('2026-10-02', '20:00', 'Salle fictive des Pas perdus', 'Ville-fictive', 'France')], [$oeuvre('Compositrice Fictive', 'Œuvre fictive n° 1')]),
        $eng(null, 'concert', 'Ensemble fictif des Lisières', [$rep('2026-10-02', '20:00', 'Salle fictive des Pas perdus', 'Ville-fictive', 'France')]),
        $eng(null, 'concert', 'Malmö SymfoniOrkester', [$rep('2026-10-15', '19:00', 'Malmö Live Konserthus', 'Malmö', 'Suède')],
            [$oeuvre('Dukas', 'La Péri'), $oeuvre('Manoury', 'Anticipations'), $oeuvre('Bartók', 'Concerto pour piano n° 3'), $oeuvre('Debussy', 'La Mer')],
            [['nom' => 'Jean-Efflam Bavouzet', 'role' => 'piano']]),
        $eng(null, 'concert', 'Orquestra Sinfónica do Porto Casa da Música', [$rep('2026-10-23', '21:00', 'Casa da Música, Sala Suggia', 'Porto', 'Portugal'), $rep('2026-10-25', '18:00', 'Casa da Música, Sala Suggia', 'Porto', 'Portugal')]),
        $eng(null, 'concert', 'Filarmonica George Enescu', [$rep('2026-10-29', '19:00', 'Athénée roumain', 'Bucarest', 'Roumanie'), $rep('2026-10-30', '19:30', 'Athénée roumain', 'Bucarest', 'Roumanie')],
            [], [['nom' => 'Veronika Eberle', 'role' => 'violon']]),
        $eng(null, 'concert', 'Kammerorchester Basel', [$rep('2026-11-07', '19:30', 'Stadtcasino Basel', 'Bâle', 'Suisse')],
            [$oeuvre('Beat Furrer', 'Nouvelle œuvre pour orchestre'), $oeuvre('Béla Bartók', 'Musique pour cordes, percussion et célesta'), $oeuvre('Compositrice Fictive', 'Concerto fictif')],
            [['nom' => 'Pierre-Laurent Aimard', 'role' => 'piano']]),
        $eng('EIC & Friends, Bertrand Chamayou', 'concert', 'Ensemble intercontemporain', [$rep('2026-11-14', '17:30', 'Cité de la musique, Salle des concerts', 'Paris', 'France'), $rep('2026-11-14', '20:00', 'Cité de la musique, Salle des concerts', 'Paris', 'France')]),
        $eng('Au revoir, Pierre', 'concert', 'Odense Symphony Orchestra', [$rep('2026-11-19', '19:00', 'Salle fictive d\'Odense', 'Odense', 'Danemark')]),
        $eng(null, 'concert', 'Orquesta y Coro Nacionales de España', [$rep('2026-11-27', '19:30', 'Auditorio Nacional de Música, Sala sinfónica', 'Madrid', 'Espagne'), $rep('2026-11-28', '19:30', 'Auditorio Nacional de Música, Sala sinfónica', 'Madrid', 'Espagne')]),
        $eng(null, 'tournee', 'Orchestre fictif de la Vallée', $tournee),
        $eng('Concert de Noël', 'concert', 'Orquesta Sinfónica de Castilla y León', [$rep('2026-12-19', '19:30', 'Centro Cultural Miguel Delibes', 'Valladolid', 'Espagne')]),
        $eng('Red Fox', 'opera', 'Dutch National Opera', [$rep('2026-05-13', '20:00', 'Dutch National Opera & Ballet', 'Amsterdam', 'Pays-Bas'),
            $rep('2026-05-15', '20:00', 'Dutch National Opera & Ballet', 'Amsterdam', 'Pays-Bas'), $rep('2026-05-18', '20:00', 'Dutch National Opera & Ballet', 'Amsterdam', 'Pays-Bas')],
            [$oeuvre('Du Yun', 'Red Fox', 'création mondiale')]),
    ],
];

