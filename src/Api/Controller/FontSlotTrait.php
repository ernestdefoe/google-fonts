<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

namespace ErnestDefoe\GoogleFonts\Api\Controller;

use Flarum\Foundation\ValidationException;

/**
 * Shared slot/weight/face helpers for the font upload + delete controllers.
 *
 * Faces are persisted as a JSON array under
 * `ernestdefoe-google-fonts.<slot>_font_faces`, each entry being
 * ['weight' => int, 'path' => string, 'url' => string]. In memory we key them
 * by weight so a re-upload of the same weight replaces in place.
 *
 * Requires the using class to expose a SettingsRepositoryInterface $settings.
 */
trait FontSlotTrait
{
    public const SLOTS = ['body', 'heading'];

    public const WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    protected function assertSlot(mixed $value): string
    {
        $slot = is_string($value) ? strtolower(trim($value)) : '';
        if (! in_array($slot, self::SLOTS, true)) {
            throw new ValidationException(['slot' => 'Invalid font slot.']);
        }

        return $slot;
    }

    protected function assertWeight(mixed $value): int
    {
        $weight = (int) $value;
        if (! in_array($weight, self::WEIGHTS, true)) {
            throw new ValidationException(['weight' => 'Invalid font weight.']);
        }

        return $weight;
    }

    /** @return array<int, array{weight:int,path:string,url:string}> keyed by weight */
    protected function readFaces(string $slot): array
    {
        $raw = (string) $this->settings->get('ernestdefoe-google-fonts.' . $slot . '_font_faces', '');
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
            if (! in_array($weight, self::WEIGHTS, true)) {
                continue;
            }
            $out[$weight] = [
                'weight' => $weight,
                'path' => (string) ($face['path'] ?? ''),
                'url' => (string) ($face['url'] ?? ''),
            ];
        }

        ksort($out);

        return $out;
    }

    /** @param array<int, array> $faces */
    protected function writeFaces(string $slot, array $faces): void
    {
        $key = 'ernestdefoe-google-fonts.' . $slot . '_font_faces';
        if (empty($faces)) {
            $this->settings->delete($key);
            return;
        }

        ksort($faces);
        $this->settings->set($key, json_encode(array_values($faces)));
    }

    /**
     * Strip the storage-only `path` field before returning faces to the admin
     * client; it only needs weight + url.
     *
     * @param array<int, array> $faces
     * @return array<int, array{weight:int,url:string}>
     */
    protected function facesForClient(array $faces): array
    {
        ksort($faces);

        return array_values(array_map(fn ($f) => [
            'weight' => (int) $f['weight'],
            'url' => (string) $f['url'],
        ], $faces));
    }
}
