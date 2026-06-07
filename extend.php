<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

use ErnestDefoe\GoogleFonts\InjectFonts;
use Flarum\Extend;

return [
    // Inject the Google Fonts <link> + font-family overrides into the forum
    // <head> server-side (no FOUT, no client round-trip to read settings).
    (new Extend\Frontend('forum'))
        ->content(InjectFonts::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),
];
