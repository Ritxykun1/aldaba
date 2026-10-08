<?php

declare(strict_types=1);

namespace Aldaba\Tests\Unit;

use Aldaba\HttpClient\ClientResponse;
use Aldaba\HttpClient\TransportException;
use Aldaba\Spoonacular\SpoonacularClient;
use Aldaba\Spoonacular\SpoonacularException;
use Aldaba\Spoonacular\SpoonacularLimitExceeded;
use Aldaba\Spoonacular\SpoonacularTimeout;
use Aldaba\Tests\Support\FakeHttpClient;
use Aldaba\Tests\Support\Spoonacular;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpoonacularClientTest extends TestCase
{
    public function test_it_searches_and_then_fetches_the_recipe_information(): void
    {
        $http = new FakeHttpClient([Spoonacular::search(654959), Spoonacular::information()]);

        $recipe = $this->client($http)->findFirstByName('pasta carbonara');

        self::assertSame('Pasta Carbonara', $recipe['title'] ?? null);
        self::assertSame(
            'https://api.spoonacular.com/recipes/complexSearch?query=pasta+carbonara&number=1',
            $http->requests[0]['url'],
        );
        self::assertSame(
            'https://api.spoonacular.com/recipes/654959/information?includeNutrition=false',
            $http->requests[1]['url'],
        );
    }

    public function test_it_uses_only_the_first_result(): void
    {
        $http = new FakeHttpClient([Spoonacular::search(111, 222, 333), Spoonacular::information()]);

        $this->client($http)->findFirstByName('pasta');

        self::assertCount(2, $http->requests);
        self::assertStringContainsString('/recipes/111/information', $http->requests[1]['url']);
    }

    public function test_the_api_key_travels_in_a_header_not_in_the_url(): void
    {
        $http = new FakeHttpClient([Spoonacular::search()]);

        $this->client($http)->findFirstByName('pasta');

        self::assertSame('secret-key', $http->requests[0]['headers']['x-api-key']);
        self::assertStringNotContainsString('secret-key', $http->requests[0]['url']);
    }

    public function test_no_results_is_null(): void
    {
        $http = new FakeHttpClient([Spoonacular::search()]);

        self::assertNull($this->client($http)->findFirstByName('xyz'));
    }

    /**
     * @param class-string<SpoonacularException> $expected
     */
    #[DataProvider('failures')]
    public function test_failures_become_spoonacular_exceptions(ClientResponse|TransportException $response, string $expected): void
    {
        $this->expectException($expected);

        $this->client(new FakeHttpClient([$response]))->findFirstByName('pasta');
    }

    /**
     * @return iterable<string, array{ClientResponse|TransportException, class-string<SpoonacularException>}>
     */
    public static function failures(): iterable
    {
        yield 'invalid API key' => [new ClientResponse(401, '{"status":"failure"}'), SpoonacularException::class];
        yield 'daily quota used up' => [new ClientResponse(402, '{"status":"failure"}'), SpoonacularLimitExceeded::class];
        yield 'rate limited' => [new ClientResponse(429, ''), SpoonacularLimitExceeded::class];
        yield 'server error' => [new ClientResponse(500, ''), SpoonacularException::class];
        yield 'invalid JSON' => [new ClientResponse(200, '<html>oops</html>'), SpoonacularException::class];
        yield 'timeout' => [new TransportException('Operation timed out', timedOut: true), SpoonacularTimeout::class];
        yield 'network error' => [new TransportException('Could not resolve host'), SpoonacularException::class];
    }

    public function test_missing_api_key_is_a_configuration_error(): void
    {
        $this->expectException(\LogicException::class);

        (new SpoonacularClient(new FakeHttpClient(), 'https://api.spoonacular.com', ''))->findFirstByName('pasta');
    }

    private function client(FakeHttpClient $http): SpoonacularClient
    {
        return new SpoonacularClient($http, 'https://api.spoonacular.com/', 'secret-key');
    }
}
