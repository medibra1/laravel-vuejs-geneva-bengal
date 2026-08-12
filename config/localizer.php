<?php

declare(strict_types=1);

use NielsNumbers\LaravelLocalizer\Detectors\BrowserDetector;
use NielsNumbers\LaravelLocalizer\Detectors\UserDetector;

return [
    // Ported from the old config/laravellocalization.php: same two
    // locales, same fr/en fallback split (see config/app.php).
    'supported_locales' => ['fr', 'en'],

    // Ported from hideDefaultLocaleInURL = false — /fr/... stays
    // explicit, there's no unprefixed default-locale route.
    'hide_default_locale' => false,

    'redirect_enabled' => true,

    // Ported from useAcceptLanguageHeader = true, now handled by
    // BrowserDetector in the `detectors` list below rather than a flag.
    'persist_locale' => [
        'session' => true,
        'cookie' => false,
    ],

    'detectors' => [
        UserDetector::class,
        BrowserDetector::class,
    ],

    // Per-locale override for writing direction. Keys must match the
    // locale codes used in `supported_locales`. Values: 'rtl' or 'ltr'.
    // Wins over the script-based detection in `LocaleDirection`.
    'locale_directions' => [],
];
