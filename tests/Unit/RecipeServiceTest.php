<?php

declare(strict_types=1);

namespace Aldaba\Tests\Unit;

use Aldaba\Cache\FileCache;
use Aldaba\HttpClient\ClientResponse;
use Aldaba\Recipe\RecipeNotFound;
use Aldaba\Recipe\RecipeService;
use Aldaba\Spoonacular\SpoonacularClient;
use Aldaba\Spoonacular\SpoonacularException;
use Aldaba\Tests\Support\FakeHttpClient;
use Aldaba\Tests\Support\Spoonacular;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecipeServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/aldaba-cache-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_it_keeps_only_the_public_fields(): void
    {
        self::assertSame([
            'name' => 'Pasta Carbonara',
            'ready_in_minutes' => 25,
            'servings' => 4,
            'ingredients' => ['400g spaghetti', '4 egg yolks'],
            'instructions' => ['Boil the pasta.', 'Mix.'],
            'image' => 'https://img.spoonacular.com/recipes/654959-556x370.jpg',
        ], RecipeService::map(Spoonacular::carbonara()));
    }

    public function test_it_falls_back_to_plain_text_instructions(): void
    {
        $html = "<ol>\n    <li>Boil &amp; salt.</li>\n    <li>Mix.</li>\n</ol>";
        $raw = ['analyzedInstructions' => [], 'instructions' => $html] + Spoonacular::carbonara();

        self::assertSame(['Boil & salt.', 'Mix.'], RecipeService::map($raw)['instructions']);
    }

    public function test_paragraphs_and_line_breaks_split_the_fallback_instructions(): void
    {
        $html = '<p>Just cook it.</p><p>Serve<br>hot.<br />Enjoy.</p>';
        $raw = ['analyzedInstructions' => [], 'instructions' => $html] + Spoonacular::carbonara();

        self::assertSame(['Just cook it.', 'Serve', 'hot.', 'Enjoy.'], RecipeService::map($raw)['instructions']);
    }

    public function test_instructions_are_null_when_missing(): void
    {
        $raw = Spoonacular::carbonara();
        unset($raw['analyzedInstructions'], $raw['instructions']);

        self::assertNull(RecipeService::map($raw)['instructions']);
    }

    public function test_optional_fields_missing_are_null(): void
    {
        $recipe = RecipeService::map(['title' => 'Toast']);

        self::assertSame(
            ['name' => 'Toast', 'ready_in_minutes' => null, 'servings' => null, 'ingredients' => [], 'instructions' => null, 'image' => null],
            $recipe,
        );
    }

    #[DataProvider('equivalentNames')]
    public function test_cache_key_is_normalized(string $name): void
    {
        self::assertSame('recipe:pasta-carbonara', RecipeService::cacheKey($name));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function equivalentNames(): iterable
    {
        yield ['pasta carbonara'];
        yield ['  Pasta   CARBONARA '];
        yield ['pasta-carbonara'];
        yield ['pasta, carbonara!'];
    }

    public function test_repeated_lookups_hit_the_cache(): void
    {
        $http = new FakeHttpClient([Spoonacular::search(654959), Spoonacular::information()]);
        $service = $this->service($http);

        $first = $service->findByName('Pasta Carbonara');
        $second = $service->findByName('pasta carbonara');

        self::assertSame($first, $second);
        self::assertCount(2, $http->requests, 'Only the first lookup should reach Spoonacular');
    }

    public function test_not_found_is_not_cached(): void
    {
        $http = new FakeHttpClient([Spoonacular::search(), Spoonacular::search(654959), Spoonacular::information()]);
        $service = $this->service($http);

        try {
            $service->findByName('carbonara');
            self::fail('Expected RecipeNotFound');
        } catch (RecipeNotFound) {
        }

        self::assertSame('Pasta Carbonara', $service->findByName('carbonara')['name']);
    }

    public function test_errors_are_not_cached(): void
    {
        $http = new FakeHttpClient([
            new ClientResponse(500, ''),
            Spoonacular::search(654959),
            Spoonacular::information(),
        ]);
        $service = $this->service($http);

        try {
            $service->findByName('carbonara');
            self::fail('Expected SpoonacularException');
        } catch (SpoonacularException) {
        }

        self::assertSame('Pasta Carbonara', $service->findByName('carbonara')['name']);
    }

    private function service(FakeHttpClient $http): RecipeService
    {
        return new RecipeService(
            new SpoonacularClient($http, 'https://api.spoonacular.com', 'test-key'),
            new FileCache($this->dir, 60),
        );
    }
}
