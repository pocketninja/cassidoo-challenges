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
        content: discoverChallengeContent($xpath),
    );

    var_dump($challenge);

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

    // Read until hr
    $content = [];

    $currentElement = $startElement->item(0);

    while ($currentElement !== null) {
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
    return preg_replace('`\s*this week.s question:?\s*`i', '', $content);
}


