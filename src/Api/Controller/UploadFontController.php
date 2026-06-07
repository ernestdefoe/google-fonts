<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

namespace ErnestDefoe\GoogleFonts\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Upload one .woff2 weight for a font slot (body / heading), storing it on the
 * public `flarum-assets` disk and recording it in the slot's face list so the
 * forum can self-host the font with no call to Google. This is what makes the
 * extension work in regions where Google Fonts is blocked.
 *
 * POST /api/ernestdefoe/google-fonts/font   (multipart)
 *   slot   = body | heading
 *   weight = 100..900 (multiple of 100)
 *   font   = the .woff2 file
 *
 * Security (mirrors ernestdefoe/seo's upload controller):
 *   - admin-only via assertAdmin()
 *   - size cap + null-size guard
 *   - extension allowlist (woff2 only)
 *   - magic-byte signature check (`wOF2`) — finfo reports octet-stream for
 *     fonts, so a MIME check would wrongly reject valid files; the signature
 *     is the reliable equivalent and still defeats a renamed non-font upload
 *   - server-generated filename (never trust the client filename)
 */
class UploadFontController implements RequestHandlerInterface
{
    use FontSlotTrait;

    public const MAX_BYTES = 3 * 1024 * 1024;

    /** WOFF2 files begin with the ASCII signature "wOF2". */
    public const WOFF2_SIGNATURE = "wOF2";

    protected Cloud $disk;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        Container $container,
        protected LoggerInterface $log,
    ) {
        $this->disk = $container->make('filesystem')->disk('flarum-assets');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            RequestUtil::getActor($request)->assertAdmin();

            $body = (array) $request->getParsedBody();
            $slot = $this->assertSlot(Arr::get($body, 'slot'));
            $weight = $this->assertWeight(Arr::get($body, 'weight'));

            /** @var UploadedFileInterface|null $file */
            $file = Arr::get($request->getUploadedFiles(), 'font');
            if (! $file || $file->getError() !== UPLOAD_ERR_OK) {
                throw new ValidationException(['font' => 'No file uploaded.']);
            }

            $size = $file->getSize();
            if ($size === null || $size <= 0 || $size > self::MAX_BYTES) {
                throw new ValidationException([
                    'font' => 'Font must be 1 byte to ' . self::MAX_BYTES . ' bytes.',
                ]);
            }

            $ext = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
            if ($ext !== 'woff2') {
                throw new ValidationException(['font' => 'Only .woff2 font files are allowed.']);
            }

            $stream = $file->getStream();
            $stream->rewind();
            $signature = $stream->read(4);
            if ($signature !== self::WOFF2_SIGNATURE) {
                throw new ValidationException(['font' => 'File is not a valid WOFF2 font.']);
            }
            $stream->rewind();
            $contents = $stream->getContents();

            $faces = $this->readFaces($slot);

            // Replace any existing file for this weight, cleaning up its blob.
            if (isset($faces[$weight]['path'])) {
                $old = $faces[$weight]['path'];
                if ($old && $this->disk->exists($old)) {
                    try { $this->disk->delete($old); } catch (\Throwable) { /* ignore */ }
                }
            }

            $uploadName = 'ed-gf-' . $slot . '-' . $weight . '-' . Str::lower(Str::random(10)) . '.woff2';
            $this->disk->put($uploadName, $contents);
            $url = $this->disk->url($uploadName);

            $faces[$weight] = ['weight' => $weight, 'path' => $uploadName, 'url' => $url];
            $this->writeFaces($slot, $faces);

            // Give the slot a family name if it doesn't have one yet, so the
            // self-hosted font can be referenced immediately. Derived from the
            // uploaded filename; the admin can rename it afterwards.
            $familyKey = 'ernestdefoe-google-fonts.' . $slot . '_font';
            $family = trim((string) $this->settings->get($familyKey, ''));
            if ($family === '') {
                $family = $this->familyFromFilename((string) $file->getClientFilename(), $slot);
                $this->settings->set($familyKey, $family);
            }

            return new JsonResponse([
                'data' => [
                    'type' => 'ernestdefoe-google-fonts',
                    'attributes' => [
                        'slot' => $slot,
                        'family' => $family,
                        'faces' => $this->facesForClient($faces),
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->log->error('[google-fonts] UploadFontController failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return new JsonResponse(['errors' => [['status' => '500', 'detail' => 'Upload failed.']]], 500);
        }
    }

    private function familyFromFilename(string $filename, string $slot): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        // woff2 exports are often "Inter-Bold" / "Inter_700" — keep just the
        // human part: letters, numbers, spaces.
        $base = preg_replace('/[-_]/', ' ', $base);
        $base = trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', (string) $base));
        $base = trim((string) preg_replace('/\b(thin|extralight|light|regular|medium|semibold|bold|extrabold|black|italic|\d{3})\b/i', '', $base));
        $base = trim((string) preg_replace('/\s+/', ' ', $base));

        return $base !== '' ? $base : ('Custom ' . ucfirst($slot) . ' Font');
    }
}
