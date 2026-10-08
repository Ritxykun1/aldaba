<?php

declare(strict_types=1);

namespace Aldaba\Tests\Integration;

use Aldaba\App;
use Aldaba\Cache\FileCache;
use Aldaba\Controller\DocsController;
use Aldaba\Controller\RecipeController;
use Aldaba\Http\Request;
use Aldaba\Http\Response;
use Aldaba\HttpClient\ClientResponse;
use Aldaba\HttpClient\TransportException;
use Aldaba\Recipe\RecipeService;
use Aldaba\Spoonacular\SpoonacularClient;
use Aldaba\Tests\Support\FakeHttpClient;
use Aldaba\Tests\Support\Spoonacular;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the whole application (routing -> controller -> service -> cache -> Spoonacular client)
 * with only the network replaced by FakeHttpClient.
 */
final class RecipeEndpointTest extends TestCase
{
    private string $dir;

    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/aldaba-cache-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_recipe_found(): void
    {
        $response = $this->get('pasta', [Spoonacular::search(654959), Spoonacular::information()]);

        self::assertSame(200, $response->status);
        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame([
            'data' => [
                'name' => 'Pasta Carbonara',
                'ready_in_minutes' => 25,
                'servings' => 4,
                'ingredients' => ['400g spaghetti', '4 egg yolks'],
                'instructions' => ['Boil the pasta.', 'Mix.'],
                'image' => 'https://img.spoonacular.com/recipes/654959-556x370.jpg',
            ],
        ], $response->data());
    }

    public function test_multiple_results_return_only_the_first(): void
    {
        $response = $this->get('pasta', [Spoonacular::search(111, 222), Spoonacular::information(['id' => 111])]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('/recipes/111/information', $this->http->requests[1]['url']);
        self::assertArrayNotHasKey(0, $response->data()['data'], 'A single recipe, not a list');
    }

    public function test_recipe_not_found_is_404(): void
    {
        $response = $this->get('xyzzy', [Spoonacular::search()]);

        self::assertSame(404, $response->status);
        self::assertSame(['error' => ['message' => "No recipe found for 'xyzzy'."]], $response->data());
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidNames')]
    public function test_invalid_name_is_422(array $query): void
    {
        $response = $this->app([])->handle(new Request('GET', '/api/recipes', $query));

        self::assertSame(422, $response->status);
        self::assertIsString($response->data()['error']['message']);
        self::assertSame([], $this->http->requests, 'Invalid input must not reach Spoonacular');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidNames(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['name' => '   ']];
        yield 'array' => [['name' => ['a', 'b']]];
        yield 'too long' => [['name' => str_repeat('a', 101)]];
        yield 'only symbols' => [['name' => '!!!']];
        yield 'invalid UTF-8' => [['name' => "\xB1\x31"]];
    }

    /**
     * @param ClientResponse|TransportException $failure
     */
    #[DataProvider('spoonacularFailures')]
    public function test_spoonacular_failures_are_translated(ClientResponse|TransportException $failure, int $status): void
    {
        $this->expectErrorLog();

        $response = $this->get('pasta', [$failure]);

        self::assertSame($status, $response->status);
        self::assertStringNotContainsString('test-key', $response->body);
    }

    /**
     * @return iterable<string, array{ClientResponse|TransportException, int}>
     */
    public static function spoonacularFailures(): iterable
    {
        yield 'server error' => [new ClientResponse(500, ''), 502];
        yield 'invalid API key' => [new ClientResponse(401, ''), 502];
        yield 'invalid JSON' => [new ClientResponse(200, 'not json'), 502];
        yield 'network error' => [new TransportException('Could not resolve host'), 502];
        yield 'daily quota used up' => [new ClientResponse(402, ''), 503];
        yield 'rate limited' => [new ClientResponse(429, ''), 503];
        yield 'timeout' => [new TransportException('Operation timed out', timedOut: true), 504];
    }

    /**
     * complexSearch succeeds, then /information fails: no partial recipe and nothing cached.
     */
    #[DataProvider('spoonacularFailures')]
    public function test_information_failure_after_successful_search(
        ClientResponse|TransportException $failure,
        int $status,
    ): void {
        $this->expectErrorLog();

        $response = $this->get('pasta', [Spoonacular::search(654959), $failure]);

        self::assertSame($status, $response->status);
        self::assertArrayNotHasKey('data', $response->data());
        self::assertArrayHasKey('error', $response->data());
        self::assertStringContainsString('/recipes/complexSearch', $this->http->requests[0]['url']);
        self::assertStringContainsString('/recipes/654959/information', $this->http->requests[1]['url']);
        self::assertSame([], glob($this->dir . '/*') ?: [], 'Nothing must be cached');
    }

    public function test_second_equivalent_request_is_served_from_cache(): void
    {
        $app = $this->app([Spoonacular::search(654959), Spoonacular::information()]);

        $first = $app->handle(new Request('GET', '/api/recipes', ['name' => 'Pasta Carbonara']));
        $second = $app->handle(new Request('GET', '/api/recipes', ['name' => 'pasta carbonara']));

        self::assertSame(200, $second->status);
        self::assertSame($first->body, $second->body);
        self::assertCount(2, $this->http->requests, 'The second request must not call Spoonacular');
    }

    public function test_recipe_without_instructions_still_works(): void
    {
        $response = $this->get('toast', [
            Spoonacular::search(1),
            Spoonacular::information(['analyzedInstructions' => [], 'instructions' => null]),
        ]);

        self::assertSame(200, $response->status);
        self::assertNull($response->data()['data']['instructions']);
    }

    public function test_missing_api_key_is_500_without_details(): void
    {
        $this->expectErrorLog();

        $app = new App(
            new RecipeController(new RecipeService(
                new SpoonacularClient(new FakeHttpClient(), 'https://api.spoonacular.com', ''),
                new FileCache($this->dir, 60),
            )),
            new DocsController(__DIR__ . '/../../docs'),
        );

        $response = $app->handle(new Request('GET', '/api/recipes', ['name' => 'pasta']));

        self::assertSame(500, $response->status);
        self::assertSame(['error' => ['message' => 'Internal server error.']], $response->data());
    }

    public function test_unknown_route_is_404(): void
    {
        self::assertSame(404, $this->app([])->handle(new Request('GET', '/nope'))->status);
    }

    public function test_wrong_method_is_405(): void
    {
        $response = $this->app([])->handle(new Request('POST', '/api/recipes', ['name' => 'pasta']));

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->headers['Allow']);
    }

    public function test_docs_are_served(): void
    {
        $app = $this->app([]);

        self::assertSame(200, $app->handle(new Request('GET', '/docs'))->status);
        self::assertStringContainsString('/api/recipes', $app->handle(new Request('GET', '/openapi.yaml'))->body);
    }

    /**
     * @param list<ClientResponse|TransportException> $spoonacularResponses
     */
    private function get(string $name, array $spoonacularResponses): Response
    {
        return $this->app($spoonacularResponses)->handle(new Request('GET', '/api/recipes', ['name' => $name]));
    }

    /**
     * @param list<ClientResponse|TransportException> $spoonacularResponses
     */
    private function app(array $spoonacularResponses): App
    {
        $this->http = new FakeHttpClient($spoonacularResponses);

        return new App(
            new RecipeController(new RecipeService(
                new SpoonacularClient($this->http, 'https://api.spoonacular.com', 'test-key'),
                new FileCache($this->dir, 60),
            )),
            new DocsController(__DIR__ . '/../../docs'),
        );
    }
}
