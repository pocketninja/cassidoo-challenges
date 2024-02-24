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