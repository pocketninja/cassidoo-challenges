#!env php
<?php
declare(strict_types=1);
require __DIR__.'/includes/functions.php';

$page = 1;

while (true) {

    line("\n\n===> FETCHING PAGE %d", $page);

    $archiveLinkTemplate = 'https://buttondown.email/cassidoo/archive?page=%d';
    $root = fetchDocument(sprintf($archiveLinkTemplate, $page));
    $xpath = new DOMXPath($root);
    $emails = $xpath->query('//div[contains(@class, "email-list")]/div[contains(@class, "email")]/a');

    foreach ($emails as $email) {
        line('%s => %s',
            trim($email->textContent),
            $email->getAttribute('href')
        );
    }

    $page++;

    $nextPageLink = $xpath->query('//div[contains(@class, "pagination")]//div[text()[contains(.,"Older")]]/..');

    // We don't care about the URL at this point, just that we have a next page link.
    if ($nextPageLink->length === 0) {
        break;
    }
}

echo "END";

//$emails = array_map(
//    fn(DOMElement $email) => [
//        'title' => $email->textContent,
//        'url' => $email->getAttribute('href'),
//    ],
//    iterator_to_array($emails)
//);
