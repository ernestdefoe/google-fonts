<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

use ErnestDefoe\GoogleFonts\Api\Controller\DeleteFontController;
use ErnestDefoe\GoogleFonts\Api\Controller\SetFontFamilyController;
use ErnestDefoe\GoogleFonts\Api\Controller\UploadFontController;
use ErnestDefoe\GoogleFonts\Console\GcFontsCommand;
use ErnestDefoe\GoogleFonts\InjectFonts;
use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;

return [
    // Inject the chosen fonts (Google <link> and/or self-hosted @font-face) +
    // font-family overrides into the forum <head> server-side (no FOUT, no
    // client round-trip to read settings).
    (new Extend\Frontend('forum'))
        ->content(InjectFonts::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    // Admin-only endpoints powering the "upload your own font" path, so the
    // forum can self-host fonts and work where Google Fonts is blocked.
    (new Extend\Routes('api'))
        ->post('/ernestdefoe/google-fonts/font', 'ernestdefoe-google-fonts.upload', UploadFontController::class)
        ->delete('/ernestdefoe/google-fonts/font', 'ernestdefoe-google-fonts.delete', DeleteFontController::class)
        ->post('/ernestdefoe/google-fonts/font-family', 'ernestdefoe-google-fonts.family', SetFontFamilyController::class),

    new Extend\Locales(__DIR__ . '/locale'),

    // GC orphaned uploaded font files (e.g. left behind if the settings blob was
    // reset outside the normal delete flow). Runnable manually, and swept weekly.
    (new Extend\Console())
        ->command(GcFontsCommand::class)
        ->schedule('ernestdefoe-google-fonts:gc', function (Event $event) {
            $event->weekly();
        }),
];
