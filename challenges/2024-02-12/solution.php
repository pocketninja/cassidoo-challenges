<?php
declare(strict_types=1);

function fromTo(int $from = 0, int $to = 5): Closure
{
    return function () use (&$from, $to) {
        if ($from !== $to) {
            return match ($from < $to) {
                true => $from++,
                default => $from--
            };
        }
        return null;
    };
}

// Sample range as per challenge.
echo "\n --- Single range (original challenge output) ---";
$range = fromTo(to: 3);
printf("\n > %s", $range() ?? 'null');
printf("\n > %s", $range() ?? 'null');
printf("\n > %s", $range() ?? 'null');
printf("\n > %s", $range() ?? 'null');


// Multiple ranges, supports both directions.
echo "\n\n --- Multiple ranges, both directions ---";
$rangeOne = fromTo(to: 4);
$rangeTwo = fromTo(from: 11);
$rangeThree = fromTo(from: 10, to: -1);

for ($i = 0; $i < 13; $i++) {
    printf("\n > %02d: %-5s | %-5s | %-5s",
        $i,
        $rangeOne() ?? 'null',
        $rangeTwo() ?? 'null',
        $rangeThree() ?? 'null',
    );
}
