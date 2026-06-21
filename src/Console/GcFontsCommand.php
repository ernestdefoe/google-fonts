<?php

namespace ErnestDefoe\GoogleFonts\Console;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Str;

/**
 * Removes uploaded font files on the flarum-assets disk that are no longer
 * referenced by either slot's `*_font_faces` setting — e.g. orphans left behind
 * if the settings blob was wiped outside the normal delete flow. Scoped to this
 * extension's own files via the `ed-gf-` filename prefix, so nothing else on the
 * shared assets disk is ever touched. Run manually, or wire into a schedule.
 */
class GcFontsCommand extends Command
{
    protected $signature = 'ernestdefoe-google-fonts:gc {--dry-run : List orphans without deleting them}';

    protected $description = 'Delete uploaded Google-Fonts font files no longer referenced by any setting.';

    public function handle(SettingsRepositoryInterface $settings, Factory $filesystem): int
    {
        $disk = $filesystem->disk('flarum-assets');

        // Every path still referenced by a saved face, across both slots.
        $referenced = [];
        foreach (['body', 'heading'] as $slot) {
            $raw = $settings->get('ernestdefoe-google-fonts.' . $slot . '_font_faces');
            $faces = $raw ? json_decode((string) $raw, true) : [];
            if (is_array($faces)) {
                foreach ($faces as $face) {
                    $path = is_array($face) ? (string) ($face['path'] ?? '') : '';
                    if ($path !== '') {
                        $referenced[$path] = true;
                    }
                }
            }
        }

        $dry = (bool) $this->option('dry-run');
        $count = 0;

        foreach ($disk->files() as $path) {
            $name = basename($path);
            if (! Str::startsWith($name, 'ed-gf-') || ! Str::endsWith($name, '.woff2')) {
                continue;
            }
            if (isset($referenced[$path])) {
                continue;
            }

            $count++;
            $this->info(($dry ? '[dry-run] orphan: ' : 'deleting: ') . $path);
            if (! $dry) {
                try {
                    $disk->delete($path);
                } catch (\Throwable $e) {
                    $this->error('  failed: ' . $e->getMessage());
                    $count--;
                }
            }
        }

        $this->info($dry
            ? "{$count} orphaned font file(s) found (dry run — nothing deleted)."
            : "Done — removed {$count} orphaned font file(s).");

        return self::SUCCESS;
    }
}
