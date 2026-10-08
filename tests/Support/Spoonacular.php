<?php

declare(strict_types=1);

namespace Aldaba\Tests\Support;

use Aldaba\HttpClient\ClientResponse;

/**
 * Trimmed-down Spoonacular responses, shaped like the examples in the official docs.
 */
final class Spoonacular
{
    /**
     * GET /recipes/complexSearch
     */
    public static function search(int ...$ids): ClientResponse
    {
        return FakeHttpClient::json([
            'offset' => 0,
            'number' => count($ids),
            'results' => array_map(
                static fn (int $id): array => ['id' => $id, 'title' => "Recipe $id", 'imageType' => 'jpg'],
                $ids,
            ),
            'totalResults' => count($ids),
        ]);
    }

    /**
     * GET /recipes/{id}/information
     *
     * @param array<string, mixed> $overrides
     */
    public static function information(array $overrides = []): ClientResponse
    {
        return FakeHttpClient::json($overrides + self::carbonara());
    }

    /**
     * @return array<string, mixed>
     */
    public static function carbonara(): array
    {
        return [
            'id' => 654959,
            'title' => 'Pasta Carbonara',
            'readyInMinutes' => 25,
            'servings' => 4,
            'image' => 'https://img.spoonacular.com/recipes/654959-556x370.jpg',
            'imageType' => 'jpg',
            'extendedIngredients' => [
                ['id' => 11420420, 'name' => 'spaghetti', 'original' => '400g spaghetti'],
                ['id' => 1125, 'name' => 'egg yolk', 'original' => '4 egg yolks'],
            ],
            'instructions' => '<ol><li>Boil the pasta.</li><li>Mix.</li></ol>',
            'analyzedInstructions' => [
                ['name' => '', 'steps' => [
                    ['number' => 1, 'step' => 'Boil the pasta.'],
                    ['number' => 2, 'step' => 'Mix.'],
                ]],
            ],
            'pricePerServing' => 163.15,
        ];
    }
}
