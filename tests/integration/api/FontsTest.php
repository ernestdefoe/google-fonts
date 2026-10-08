<?php

namespace ErnestDefoe\GoogleFonts\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FontsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-google-fonts');

        $this->prepareDatabase([User::class => [$this->normalUser()]]);
    }

    private function json(string $method, string $path, ?int $actor, array $body = []): array
    {
        $request = $this->request($method, $path, array_filter(['authenticatedAs' => $actor, 'json' => $body]));
        if (! $actor) {
            // Past the CSRF check, so a guest reaches the permission check itself.
            $request = $request->withAttribute('bypassCsrfToken', true);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function upload(string $slot, int $weight, string $contents, string $filename = 'Inter-Bold.woff2', int $actor = 1): array
    {
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($contents);
        $stream->rewind();

        $request = $this->request('POST', '/api/ernestdefoe/google-fonts/font', ['authenticatedAs' => $actor])
            ->withParsedBody(['slot' => $slot, 'weight' => (string) $weight])
            ->withUploadedFiles(['font' => new UploadedFile($stream, strlen($contents), UPLOAD_ERR_OK, $filename, 'font/woff2')]);

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function stored(string $key): ?string
    {
        return $this->database()->table('settings')->where('key', 'ernestdefoe-google-fonts.'.$key)->value('value');
    }

    private function head(): string
    {
        return (string) $this->send($this->request('GET', '/'))->getBody();
    }

    public static function routes(): array
    {
        return [
            'upload' => ['POST', '/api/ernestdefoe/google-fonts/font'],
            'delete' => ['DELETE', '/api/ernestdefoe/google-fonts/font'],
            'family' => ['POST', '/api/ernestdefoe/google-fonts/font-family'],
        ];
    }

    #[Test]
    #[DataProvider('routes')]
    public function only_an_admin_manages_fonts(string $method, string $path)
    {
        $body = ['slot' => 'body', 'family' => 'Inter'];

        $this->assertSame(403, $this->json($method, $path, null, $body)[0], 'Guest');
        $this->assertSame(403, $this->json($method, $path, 2, $body)[0], 'Member');
        $this->assertNull($this->stored('body_font'));
    }

    #[Test]
    public function a_family_name_keeps_only_letters_numbers_and_spaces()
    {
        [$status, $body] = $this->json('POST', '/api/ernestdefoe/google-fonts/font-family', 1, ['slot' => 'heading', 'family' => 'Open Sans";}</style><script>']);

        $this->assertSame(200, $status);
        $this->assertSame('Open Sansstylescript', $body['data']['attributes']['family']);
        $this->assertSame('Open Sansstylescript', $this->stored('heading_font'));

        $this->assertSame(422, $this->json('POST', '/api/ernestdefoe/google-fonts/font-family', 1, ['slot' => 'footer', 'family' => 'Inter'])[0], 'No such slot');
    }

    #[Test]
    public function a_google_font_is_linked_in_the_page_head()
    {
        $this->setting('ernestdefoe-google-fonts.body_font', 'Open Sans');
        $this->setting('ernestdefoe-google-fonts.heading_font', 'Sora');

        $html = $this->head();

        $this->assertStringContainsString('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&amp;family=Sora:wght@500;600;700;800&amp;display=swap', $html);
        $this->assertStringContainsString('--ernestdefoe-gf-body:"Open Sans"', $html);
        $this->assertStringContainsString('--ernestdefoe-gf-heading:"Sora"', $html);
    }

    #[Test]
    public function nothing_is_injected_without_a_font()
    {
        $html = $this->head();

        $this->assertStringNotContainsString('ernestdefoe-gf', $html);
        $this->assertStringNotContainsString('<style>:root{}', $html, 'Not even an empty block');
    }

    #[Test]
    public function a_stored_value_cannot_break_out_of_the_page_head()
    {
        $this->setting('ernestdefoe-google-fonts.body_font', 'Evil"}</style><script>alert(1)</script>');
        $this->setting('ernestdefoe-google-fonts.body_font_faces', json_encode([
            ['weight' => 400, 'url' => 'https://cdn.test/ok.woff2'],
            ['weight' => 700, 'url' => 'x") } body{background:url("evil'],
            ['weight' => 450, 'url' => 'https://cdn.test/odd.woff2'],
        ]));

        $html = $this->head();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('src:url("https://cdn.test/ok.woff2")', $html);
        $this->assertStringNotContainsString('evil', $html, 'A URL that could leave url("…") is dropped');
        $this->assertStringNotContainsString('odd.woff2', $html, 'So is a weight that is not a multiple of 100');
    }

    #[Test]
    public function an_uploaded_woff2_is_self_hosted_and_named_from_its_file()
    {
        [$status, $body] = $this->upload('body', 700, "wOF2".str_repeat("\0", 60));

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('Inter', $body['data']['attributes']['family']);
        $this->assertSame([700], array_column($body['data']['attributes']['faces'], 'weight'));
        $this->assertArrayNotHasKey('path', $body['data']['attributes']['faces'][0], 'The storage path stays on the server');

        $faces = json_decode($this->stored('body_font_faces'), true);
        $this->assertStringStartsWith('ed-gf-body-700-', $faces[0]['path']);

        $html = $this->head();
        $this->assertStringContainsString('@font-face{font-family:"Inter";font-style:normal;font-weight:700;', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com/css2', $html, 'Self-hosted, so Google is never asked');
    }

    #[Test]
    public function only_a_real_woff2_file_is_accepted()
    {
        $this->assertSame(422, $this->upload('body', 400, '<?php echo 1;', 'shell.woff2')[0], 'Wrong signature');
        $this->assertSame(422, $this->upload('body', 400, "wOF2rest", 'font.ttf')[0], 'Wrong extension');
        $this->assertSame(422, $this->upload('body', 450, "wOF2rest")[0], 'Not a weight');
        $this->assertNull($this->stored('body_font_faces'));
    }

    #[Test]
    public function deleting_a_weight_or_a_whole_slot()
    {
        $this->upload('heading', 400, "wOF2aaaa", 'Sora-Regular.woff2');
        $this->upload('heading', 700, "wOF2bbbb", 'Sora-Bold.woff2');
        $this->assertSame('Sora', $this->stored('heading_font'));

        [$status, $body] = $this->json('DELETE', '/api/ernestdefoe/google-fonts/font', 1, ['slot' => 'heading', 'weight' => 400]);
        $this->assertSame(200, $status);
        $this->assertSame([700], array_column($body['data']['attributes']['faces'], 'weight'));

        $this->json('DELETE', '/api/ernestdefoe/google-fonts/font', 1, ['slot' => 'heading']);
        $this->assertNull($this->stored('heading_font_faces'));
        $this->assertSame('', (string) $this->stored('heading_font'), 'The upload-derived name goes with its files');
    }
}
