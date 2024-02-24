#!env php
<?php
declare(strict_types=1);
require __DIR__.'/includes/enums.php';
require __DIR__.'/includes/dtos.php';
require __DIR__.'/includes/functions.php';

$challengesPath = __DIR__.'/../challenges/';
$challengesDirectory = new DirectoryIterator($challengesPath);

// Generate all the intros.
foreach ($challengesDirectory as $challengeDirectory) {
    if ($challengeDirectory->isDot() || $challengeDirectory->isFile()) {
        continue;
    }

    line('Generating intro for: %s', $challengeDirectory->getFilename());

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
}

// Generate the overall index to be read into index.html.
$challengeIndexFile = __DIR__.'/../public/challenge-index.html';
emptyFile($challengeIndexFile);

$intros = glob(__DIR__.'/../public/challenges/*/intro.html');
rsort($intros);

foreach ($intros as $intro) {
    $intro = file_get_contents($intro).str_pad('', 3, PHP_EOL);
    file_put_contents($challengeIndexFile, $intro, FILE_APPEND);
}
