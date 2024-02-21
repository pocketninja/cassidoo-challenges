#!env php
<?php
declare(strict_types=1);
require __DIR__.'/includes/functions.php';
const ROOT_URL = 'https://buttondown.email/cassidoo/archive';

// Grab the newsletter issue links from the main archive page.

$root = fetchDocument(ROOT_URL);
$xpath = new DOMXPath($root);
$emails = $xpath->query('//div[contains(@class, "email-list")]/div[contains(@class, "email")]/a');

$emails = array_map(
    fn(DOMElement $email) => [
        'title' => $email->textContent,
        'url' => $email->getAttribute('href'),
    ],
    iterator_to_array($emails)
);
