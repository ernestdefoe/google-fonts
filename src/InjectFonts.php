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
 * Adds the chosen fonts and the font-family overrides to the forum document
 * head. Invoked by Extend\Frontend('forum')->content().
 *
 * Each slot (body / heading) is rendered in one of two modes:
 *
 *   - Google mode  — the family is a Google Fonts name; we link the css2
 *     stylesheet. The <link> is loaded NON-render-blocking (media=print swap)
 *     so a blocked/unreachable Google (e.g. mainland China behind the GFW)
 *     never stalls page paint; visitors just keep the system-font fallback.
 *
 *   - Self-hosted mode — the admin uploaded one or more .woff2 weights for the
 *     slot. We emit an @font-face per weight pointing at the locally-served
 *     file and DO NOT contact Google at all. This is the path that works
 *     everywhere, including regions where Google is blocked.
 *
 * A slot is in self-hosted mode whenever it has stored faces; otherwise it
 * falls back to its (Google) family name, if any.
 */
class InjectFonts
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $slots = [
            'body' => $this->resolveSlot('body'),
            'heading' => $this->resolveSlot('heading'),
        ];

        $active = array_filter($slots, fn ($s) => $s !== null);
        if (empty($active)) {
            return;
        }

        // --- Self-hosted @font-face declarations (deduped across slots) -------
        $faceCss = '';
        $emitted = [];
        foreach ($active as $slot) {
            if ($slot['mode'] !== 'upload') {
                continue;
            }
            foreach ($slot['faces'] as $face) {
                $key = $slot['family'] . '|' . $face['weight'];
                if (isset($emitted[$key])) {
                    continue;
                }
                $emitted[$key] = true;
                $faceCss .= '@font-face{'
                    . 'font-family:"' . $slot['family'] . '";'
                    . 'font-style:normal;'
                    . 'font-weight:' . $face['weight'] . ';'
                    . 'font-display:swap;'
                    . 'src:url("' . $face['url'] . '") format("woff2");'
                    . '}';
            }
        }
        if ($faceCss !== '') {
            $document->head[] = '<style>' . $faceCss . '</style>';
        }

        // --- Google css2 stylesheet for any slot still in Google mode --------
        $families = [];
        foreach (['body', 'heading'] as $name) {
            $slot = $slots[$name];
            if ($slot === null || $slot['mode'] !== 'google') {
                continue;
            }
            // css2 is lenient: requesting a weight a family lacks returns the
            // available faces (HTTP 200), so a fixed weight set works for ANY
            // font. Headings get heavier weights so their bolds load too.
            $weights = $name === 'heading' ? '500;600;700;800' : '400;500;600;700';
            $families[$slot['family']] = isset($families[$slot['family']])
                ? '400;500;600;700;800'
                : $weights;
        }

        if (! empty($families)) {
            $params = [];
            foreach ($families as $family => $weights) {
                $params[] = 'family=' . str_replace(' ', '+', $family) . ':wght@' . $weights;
            }
            $href = 'https://fonts.googleapis.com/css2?' . implode('&', $params) . '&display=swap';
            $safeHref = htmlspecialchars($href, ENT_QUOTES);

            $document->head[] = '<link rel="preconnect" href="https://fonts.googleapis.com">';
            $document->head[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            // Non-render-blocking: load as print, then promote to all once it
            // arrives. <noscript> keeps it working with JS disabled.
            $document->head[] = '<link rel="stylesheet" href="' . $safeHref
                . '" media="print" onload="this.media=&#39;all&#39;">';
            $document->head[] = '<noscript><link rel="stylesheet" href="' . $safeHref . '"></noscript>';
        }

        // --- :root variables + the actual font-family overrides --------------
        $css = ':root{';
        if ($slots['body'] !== null) {
            $css .= '--ernestdefoe-gf-body:"' . $slots['body']['family'] . '",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;';
        }
        if ($slots['heading'] !== null) {
            $css .= '--ernestdefoe-gf-heading:"' . $slots['heading']['family'] . '",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;';
        }
        $css .= '}';

        if ($slots['body'] !== null) {
            $css .= 'body,.App,input,select,textarea,button,optgroup,.Button{font-family:var(--ernestdefoe-gf-body)!important;}';
        }
        if ($slots['heading'] !== null) {
            $css .= 'h1,h2,h3,h4,h5,h6,.Hero-title{font-family:var(--ernestdefoe-gf-heading)!important;}';
        }

        $document->head[] = '<style>' . $css . '</style>';
    }

    /**
     * Resolve a slot into a normalized shape, or null if it is unconfigured:
     *   ['mode' => 'upload'|'google', 'family' => string, 'faces' => array]
     */
    private function resolveSlot(string $name): ?array
    {
        $family = $this->cleanFamily((string) $this->settings->get('ernestdefoe-google-fonts.' . $name . '_font', ''));
        if ($family === '') {
            return null;
        }

        $faces = $this->faces($name);
        if (! empty($faces)) {
            return ['mode' => 'upload', 'family' => $family, 'faces' => $faces];
        }

        return ['mode' => 'google', 'family' => $family, 'faces' => []];
    }

    /**
     * Read, decode and harden the stored faces for a slot. Each surviving face
     * is ['weight' => int, 'url' => string]; anything malformed is dropped.
     */
    private function faces(string $name): array
    {
        $raw = (string) $this->settings->get('ernestdefoe-google-fonts.' . $name . '_font_faces', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $face) {
            if (! is_array($face)) {
                continue;
            }
            $weight = (int) ($face['weight'] ?? 0);
            $url = (string) ($face['url'] ?? '');
            // Defence in depth: weight must be a sane multiple of 100, and the
            // URL must contain no characters that could break out of url("...")
            // in the emitted CSS.
            if ($weight < 100 || $weight > 900 || $weight % 100 !== 0) {
                continue;
            }
            if ($url === '' || preg_match('/["\'\\\\()\s<>]/', $url)) {
                continue;
            }
            $out[$weight] = ['weight' => $weight, 'url' => $url];
        }

        ksort($out);

        return array_values($out);
    }

    /**
     * Family names are letters, numbers and spaces only. Stripping everything
     * else also neutralises any HTML/CSS/URL injection from the stored value.
     */
    private function cleanFamily(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', $value));
    }
}
