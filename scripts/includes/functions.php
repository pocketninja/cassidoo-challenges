<?php
declare(strict_types=1);
libxml_use_internal_errors(true);

function line(string $template, ...$args): void
{
    echo vsprintf($template.PHP_EOL, $args);
}

function cachedGetContents(string $path): string
{
    $tmpPath = sprintf(
        '/tmp/scraper-cache-%s',
        md5($path)
    );

    if (file_exists($tmpPath)) {
        line(" -> [cached]");
        return file_get_contents($tmpPath);
    }

    // Be kind to the server.
    sleep(1);

    $contents = file_get_contents($path);

    if (empty($contents)) {
        throw new Exception(sprintf('Could not fetch: %s', $path));
    }

    line(" -> [fetched]");

    file_put_contents($tmpPath, $contents);

    return $contents;
}

function fetchDocument(string $path): DOMDocument
{
    line('Fetching: %s', $path);
    $contents = cachedGetContents($path);

    $document = new DOMDocument();
    $document->loadHTML($contents);

    return $document;
}

function discoverChallenge(string $path): ?Challenge
{
    $document = fetchDocument($path);
    $xpath = new DOMXPath($document);

    $challenge = new Challenge(
        title: discoverTitle($xpath),
        date: discoverDate($xpath),
        content: discoverChallengeContent($xpath),
        link: $path,
    );

    return $challenge->valid() ? $challenge : null;
}

function discoverTitle(DOMXPath $xpath): ?string
{
    $title = $xpath->query('//h1[contains(@class, "subject")]');

    if ($title->length === 0) {
        return null;
    }

    return trim($title->item(0)->textContent);
}

function discoverChallengeContent(DOMXPath $xpath): ?string
{
    // Find the element which contains "this week" and "question" (the "'s" sometimes is slanted).
    $startElement = $xpath->query('
        //div[contains(@class, "email-detail")]
            //p[text()[contains(., "This week") and contains(., "question")]]
    ');

    if ($startElement->length === 0) {
        return null;
    }

    $content = [];

    $currentElement = $startElement->item(0);
    $bail = false;

    while ($currentElement !== null && $bail === false) {
        // Examples always end with a <hr>.
        if ($currentElement->nodeName === 'hr') {
            break;
        }

        $textContent = $currentElement->textContent;
        $lowerTextContent = strtolower($textContent);

        $skipContentConditions = [
            str_contains($lowerTextContent, "you can submit")
        ];

        $includeContent = true;

        foreach ($skipContentConditions as $skip) {
            if ($skip) {
                $includeContent = false;
                break;
            }
        }

        if ($includeContent) {
            $textContent = trim($textContent);

            if ($currentElement->nodeName === 'div' && $currentElement->getAttribute('class') === 'codehilite') {
                $textContent = sprintf('<pre>%s</pre>', htmlentities($textContent));
                $bail = true;
            }

            $content[] = $textContent;
        }

        $currentElement = $currentElement->nextSibling;
    }

    $content = array_map(
        fn(string $item) => str_starts_with($item, '<pre')
            ? $item
            : sprintf('<p>%s</p>', $item),
        array_filter($content)
    );

    $content = implode(PHP_EOL, $content);

    // Trim the intro.
    return preg_replace('`\s*this week[^q]+question:?\s*`i', '', $content);
}

function discoverDate(DOMXPath $xpath): ?string
{
    // Date el is .email-detail .email-detail__header h3.byline
    $date = $xpath->query('//div[contains(@class, "email-detail")]//h3[contains(@class, "byline")]');

    if ($date->length === 0) {
        return null;
    }

    try {
        $date = new DateTimeImmutable(trim($date->item(0)->textContent));
    } catch (Exception) {
        return null;
    }

    return $date->format('Y-m-d');
}

function storeChallenge(Challenge $challenge): void
{
    static $challengesPath = __DIR__.'/../../challenges';

    line('  ==> Storing: %s', $challenge->title);

    $challengePath = sprintf('%s/%s', $challengesPath, $challenge->date);
    $payloadPath = sprintf('%s/challenge.json', $challengePath);

    if (!is_dir($challengePath)) {
        match (mkdir($challengePath, recursive: true)) {
            true => line('Created: %s', $challengePath),
            default => throw new Exception(sprintf('Could not create: %s', $challengePath))
        };
    }

    match (file_put_contents($payloadPath, json_encode($challenge, JSON_PRETTY_PRINT))) {
        false => throw new Exception(sprintf('Could not write: %s', $payloadPath)),
        default => line('Stored: %s', $payloadPath)
    };
}

/**
 * @return SolutionType[]
 */
function discoverSolutionTypes(DirectoryIterator $directory): array
{
    $solutions = [];
//    var_dump(compact('directory'));

    foreach ($directory as $solution) {
//        var_dump(compact('solution'));
        if ($solution->isDot()) {
            continue;
        }

        $filename = $solution->getFilename();

        if (!str_starts_with($filename, 'solution')) {
            continue;
        }

        $extension = $solution->getExtension();

//        var_dump(compact('extension', 'solution'));

        $solutions[] = SolutionType::tryFrom($extension);
    }


    return array_filter($solutions);
}

function emptyFile(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }

    touch($path);
}

function assertChallengePublicPath(Challenge $challenge): void
{
    $publicPath = sprintf(
        __DIR__.'/../../public/challenges/%s',
        $challenge->date
    );

    if (!is_dir($publicPath)) {
        if (!mkdir($publicPath, recursive: true)) {
            throw new Exception(sprintf('Could not create: %s', $publicPath));
        }
    }
}

/**
 * @param  SolutionType[]  $solutionTypes
 */
function generateChallengeIntro(Challenge $challenge, array $solutionTypes): void
{
    assertChallengePublicPath($challenge);

    //@TODO If ever necessary/worth it, extract these out to template files, with tokens.
    static $template = <<<'HTML'
<div class="challenge-intro challenge-intro--%6$s">
    <h1 class="challenge-intro__title">
        <time class="challenge-intro__date" datetime="%4$s">%4$s</time>
        %1$s
    </h1>
    <div class="challenge-intro__solutions">%3$s</div>
    <div class="challenge-intro__description">%2$s</div>
    <a href="%5$s" target="_blank" class="challenge-intro__link">Newsletter link</a>
</div>
HTML;

    static $solutionLinkTemplate = <<<'HTML'
<a
    href="https://github.com/pocketninja/cassidoo-challenges/blob/main/challenges/%1$s/%2$s"
    hx-get="/challenges/%1$s/%2$s.html"
    hx-target="#code-dialog .code-dialog__content"
    class="solution-link"
>
 %3$s
 </a>
HTML;


    $solutionsHtml = count($solutionTypes) === 0
        ? '<p>No solutions yet.</p>'
        : implode(' ',
            array_map(
                fn(SolutionType $type) => sprintf(
                    $solutionLinkTemplate,
                    $challenge->date,
                    $type->filename(),
                    $type->label(),
                    $challenge->link,
                ),
                $solutionTypes
            )
        );

    $introHtml = sprintf(
        $template,
        $challenge->title,
        $challenge->content,
        $solutionsHtml,
        $challenge->date,
        $challenge->link,
        count($solutionTypes) > 0 ? 'solved' : 'unsolved'
    );

    $introPath = sprintf(
        __DIR__.'/../../public/challenges/%s/intro.html',
        $challenge->date
    );

    file_put_contents($introPath, $introHtml);
}

function generateSolutionHtml(Challenge $challenge, SolutionType $solutionType): void
{
    assertChallengePublicPath($challenge);

    static $solutionHtmlTemplate = <<<'HTML'
<pre class="challenge-solution">%s</pre>
HTML;


    $solutionOutputPath = sprintf(
        __DIR__.'/../../public/challenges/%s/%s.html',
        $challenge->date,
        $solutionType->filename()
    );

    $solutionPath = sprintf(
        __DIR__.'/../../challenges/%s/%s',
        $challenge->date,
        $solutionType->filename()
    );

    $solutionHtml = sprintf(
        $solutionHtmlTemplate,
        htmlentities(file_get_contents($solutionPath))
    );

    file_put_contents($solutionOutputPath, $solutionHtml);
}

function generateStats(array $stats): void
{
    static $statTemplate = <<<'HTML'
<div class="stat stat--%2$s" style="--percentage-complete: %1$f%%;" data-total="%3$s" data-solved="%4$s">
    <span class="stat__label">%2$s (%3$s / %4$s)</span>
    <span class="stat__percentage-bar">%1$f%%</span>
</div>
HTML;

    $statsHtml = array_map(
        fn(string $year, array $data) => sprintf(
            $statTemplate,
            $data['solved'] / $data['total'] * 100,
            $year,
            $data['total'],
            $data['solved']
        ),
        array_keys($stats),
        $stats
    );

    $html = sprintf(
        '<div class="stats">%s</div>',
        implode(PHP_EOL, $statsHtml)
    );

    $statPath = __DIR__.'/../../public/stats.html';

    file_put_contents($statPath, $html);
}
