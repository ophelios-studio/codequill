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

Formatter::register('eth_address', function (string $address) {
    if (empty($address)) {
        return '';
    }

    // Ensure address is properly formatted
    $address = strtolower($address);
    if (str_starts_with($address, '0x')) {
        $address = substr($address, 2);
    }

    return '0x' . substr($address, 0, 6) . '...' . substr($address, -4);
});

