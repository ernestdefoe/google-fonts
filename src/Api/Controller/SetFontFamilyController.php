<?php

/*
 * This file is part of ernestdefoe/google-fonts.
 *
 * Licensed under the MIT license.
 */

namespace ErnestDefoe\GoogleFonts\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Persist a slot's family name immediately (used by the upload UI, where the
 * family is saved as-you-go rather than via the deferred Save button).
 *
 * POST /api/ernestdefoe/google-fonts/font-family   (JSON body)
 *   slot   = body | heading
 *   family = the family name to store
 *
 * The value is sanitised the same way InjectFonts does (letters/numbers/spaces)
 * so what is stored is exactly what gets rendered.
 */
class SetFontFamilyController implements RequestHandlerInterface
{
    use FontSlotTrait;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = (array) $request->getParsedBody();
        $slot = $this->assertSlot(Arr::get($body, 'slot'));
        $family = trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', (string) Arr::get($body, 'family', '')));

        $this->settings->set('ernestdefoe-google-fonts.' . $slot . '_font', $family);

        return new JsonResponse([
            'data' => [
                'type' => 'ernestdefoe-google-fonts',
                'attributes' => [
                    'slot' => $slot,
                    'family' => $family,
                ],
            ],
        ]);
    }
}
