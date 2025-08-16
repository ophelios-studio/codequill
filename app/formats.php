<?php

use Zephyrus\Utilities\Formatter;

/**
 * Add global project formats here ...
 */

Formatter::register('day_full', function ($dateTime) {
    if (!$dateTime instanceof \DateTime) {
        $dateTime = new DateTime($dateTime);
    }
    $formatter = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::LONG, IntlDateFormatter::SHORT, null, null, "EEEE");
    return $formatter->format($dateTime->getTimestamp());
});
