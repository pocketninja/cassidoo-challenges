<?php
declare(strict_types=1);
require __DIR__.'/includes/dtos.php';
require __DIR__.'/includes/functions.php';

$page = 1;

while (true) {

    line("\n\n===> FETCHING PAGE %d", $page);

    $archiveLinkTemplate = 'https://buttondown.email/cassidoo/archive?page=%d';
    $document = fetchDocument(sprintf($archiveLinkTemplate, $page));
    $xpath = new DOMXPath($document);
    $emails = $xpath->query('//div[contains(@class, "email-list")]/div[contains(@class, "email")]/a');

    foreach ($emails as $email) {

        line("\n --> %s\n   => %s",
            trim($email->textContent),
            $email->getAttribute('href')
        );

        try {
            $challenge = discoverChallenge($email->getAttribute('href'));

            if ($challenge === null) {
                line("   => [could no discover challenge // no challenge found]");
                continue;
            }

            storeChallenge($challenge);
        } catch (Exception $e) {
            line("   => [error: %s]", $e->getMessage());
        }
    }

    $page++;

    $nextPageLink = $xpath->query('//div[contains(@class, "pagination")]//div[text()[contains(.,"Older")]]/..');

    // We don't care about the URL at this point, just that we have a next page link.
    if ($nextPageLink->length === 0) {
        break;
    }
}

line("----------------------");
line("----------------------");
line("----------------------");
line("DONE");
