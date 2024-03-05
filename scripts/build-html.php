<?php
declare(strict_types=1);
require __DIR__.'/includes/enums.php';
require __DIR__.'/includes/dtos.php';
require __DIR__.'/includes/functions.php';

$challengesPath = __DIR__.'/../challenges/';
$challengesDirectory = new DirectoryIterator($challengesPath);

$stats = [
    'all' => [
        'total' => 0,
        'solved' => 0,
    ],
];

// Generate all the intros.
foreach ($challengesDirectory as $challengeDirectory) {
    if ($challengeDirectory->isDot() || $challengeDirectory->isFile()) {
        continue;
    }

    $challengeCount++;

    $challengeDate = $challengeDirectory->getFilename();
    $challengeYear = (new DateTimeImmutable($challengeDate))->format('Y');

    if (!array_key_exists($challengeYear, $stats)) {
        $stats[$challengeYear] = [
            'total' => 0,
            'solved' => 0,
        ];
    }

    $stats['all']['total']++;
    $stats[$challengeYear]['total']++;

    line('Generating intro for: %s', $challengeDate);

    $challengeSpecPath = sprintf('%s/challenge.json', $challengeDirectory->getPathname());
    $challengeSpec = file_get_contents($challengeSpecPath);
    $challengeSpec = json_decode($challengeSpec, associative: true);

    $solutionTypes = discoverSolutionTypes(new DirectoryIterator($challengeDirectory->getPathname()));

    $challenge = new Challenge(...$challengeSpec);

    line(' --> %s', $challenge->title);

    generateChallengeIntro($challenge, $solutionTypes);

    line(' --> Generated intro for %d solution(s)', count($solutionTypes));

    foreach ($solutionTypes as $solutionType) {
        generateSolutionHtml($challenge, $solutionType);
        line(' ----> Generated solution for: %s', $solutionType->value);
    }

    if (count($solutionTypes)) {
        $stats['all']['solved']++;
        $stats[$challengeYear]['solved']++;
    }
}

generateStats($stats);

// Generate the overall index to be read into index.html.
$challengeIndexFile = __DIR__.'/../public/challenge-index.html';
emptyFile($challengeIndexFile);

file_put_contents($challengeIndexFile, file_get_contents(__DIR__.'/../public/stats.html'));

$intros = glob(__DIR__.'/../public/challenges/*/intro.html');
rsort($intros);

foreach ($intros as $intro) {
    $intro = file_get_contents($intro).str_pad('', 3, PHP_EOL);
    file_put_contents($challengeIndexFile, $intro, FILE_APPEND);
}
