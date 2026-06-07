<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

namespace ErnestDefoe\GoogleFonts;

use Flarum\Frontend\Document;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Adds the chosen Google Fonts stylesheet and the font-family overrides to the
 * forum document head. Invoked by Extend\Frontend('forum')->content().
 */
class InjectFonts
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $body = $this->clean((string) $this->settings->get('ernestdefoe-google-fonts.body_font', ''));
        $heading = $this->clean((string) $this->settings->get('ernestdefoe-google-fonts.heading_font', ''));

        if ($body === '' && $heading === '') {
            return;
        }

        // Build a deduped family => weights map for the css2 request. The css2
        // API is lenient: requesting a weight a family lacks returns the
        // available faces (HTTP 200) rather than erroring, so a fixed weight
        // set works for ANY font.
        $families = [];
        if ($body !== '') {
            $families[$body] = '400;500;600;700';
        }
        if ($heading !== '') {
            // Same family as the body? Widen weights so heading bolds load too.
            $families[$heading] = isset($families[$heading]) ? '400;500;600;700;800' : '500;600;700;800';
        }

        $params = [];
        foreach ($families as $family => $weights) {
            $params[] = 'family=' . str_replace(' ', '+', $family) . ':wght@' . $weights;
        }
        $href = 'https://fonts.googleapis.com/css2?' . implode('&', $params) . '&display=swap';

        $document->head[] = '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $document->head[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $document->head[] = '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '">';

        $css = ':root{';
        if ($body !== '') {
            $css .= '--ernestdefoe-gf-body:"' . $body . '",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;';
        }
        if ($heading !== '') {
            $css .= '--ernestdefoe-gf-heading:"' . $heading . '",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;';
        }
        $css .= '}';

        if ($body !== '') {
            $css .= 'body,.App,input,select,textarea,button,optgroup,.Button{font-family:var(--ernestdefoe-gf-body)!important;}';
        }
        if ($heading !== '') {
            $css .= 'h1,h2,h3,h4,h5,h6,.Hero-title{font-family:var(--ernestdefoe-gf-heading)!important;}';
        }

        $document->head[] = '<style>' . $css . '</style>';
    }

    /**
     * Google font family names are letters, numbers and spaces only. Stripping
     * everything else also neutralises any HTML/CSS/URL injection from the
     * stored setting value.
     */
    private function clean(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', $value));
    }
}
