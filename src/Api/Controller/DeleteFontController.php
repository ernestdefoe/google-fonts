<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

namespace ErnestDefoe\GoogleFonts\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Remove an uploaded font weight from a slot, or all of them.
 *
 * DELETE /api/ernestdefoe/google-fonts/font   (JSON body)
 *   slot   = body | heading
 *   weight = 100..900  (optional; omit to remove every weight for the slot)
 *
 * Removing every weight reverts the slot to Google mode. The family name is
 * left untouched so the admin can keep or replace it.
 */
class DeleteFontController implements RequestHandlerInterface
{
    use FontSlotTrait;

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

            $faces = $this->readFaces($slot);
            $weightRaw = Arr::get($body, 'weight');

            if ($weightRaw === null || $weightRaw === '') {
                // Remove the whole slot, including its (custom, upload-derived)
                // family name — otherwise the now-faceless slot would fall back
                // to Google mode and request a non-existent family.
                $this->deleteFiles($faces);
                $faces = [];
                $this->settings->set('ernestdefoe-google-fonts.' . $slot . '_font', '');
            } else {
                $weight = $this->assertWeight($weightRaw);
                if (isset($faces[$weight])) {
                    $this->deleteFiles([$faces[$weight]]);
                    unset($faces[$weight]);
                }
            }

            $this->writeFaces($slot, $faces);

            return new JsonResponse([
                'data' => [
                    'type' => 'ernestdefoe-google-fonts',
                    'attributes' => [
                        'slot' => $slot,
                        'faces' => $this->facesForClient($faces),
                    ],
                ],
            ]);
        } catch (\Flarum\Foundation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->log->error('[google-fonts] DeleteFontController failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return new JsonResponse(['errors' => [['status' => '500', 'detail' => 'Delete failed.']]], 500);
        }
    }

    /** @param array<int, array> $faces */
    private function deleteFiles(array $faces): void
    {
        foreach ($faces as $face) {
            $path = (string) ($face['path'] ?? '');
            if ($path !== '' && $this->disk->exists($path)) {
                try { $this->disk->delete($path); } catch (\Throwable) { /* ignore */ }
            }
        }
    }
}
