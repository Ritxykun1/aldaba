<?php

declare(strict_types=1);

namespace Aldaba\Recipe;

use Aldaba\Cache\FileCache;
use Aldaba\Spoonacular\SpoonacularClient;

final class RecipeService
{
    public function __construct(
        private readonly SpoonacularClient $spoonacular,
        private readonly FileCache $cache,
    ) {
    }

    /**
     * @return array{name: string, ready_in_minutes: ?int, servings: ?int, ingredients: list<string>, instructions: ?list<string>, image: ?string}
     * @throws RecipeNotFound
     * @throws \Aldaba\Spoonacular\SpoonacularException
     */
    public function findByName(string $name): array
    {
        $key = self::cacheKey($name);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Only successful lookups reach the cache: errors and "not found" are thrown before this point.
        $raw = $this->spoonacular->findFirstByName($name) ?? throw new RecipeNotFound($name);

        $recipe = self::map($raw);
        $this->cache->set($key, $recipe);

        return $recipe;
    }

    /**
     * "Pasta  Carbonara", "pasta-carbonara" and " PASTA carbonara " all become "recipe:pasta-carbonara".
     */
    public static function cacheKey(string $name): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name));

        return 'recipe:' . trim((string) $slug, '-');
    }

    /**
     * Keeps only the fields our API exposes; Spoonacular's response is never returned as is.
     *
     * @param array<string, mixed> $raw
     */
    public static function map(array $raw): array
    {
        $ingredients = [];
        foreach ($raw['extendedIngredients'] ?? [] as $ingredient) {
            $text = trim((string) ($ingredient['original'] ?? $ingredient['name'] ?? ''));
            if ($text !== '') {
                $ingredients[] = $text;
            }
        }

        return [
            'name' => (string) ($raw['title'] ?? ''),
            'ready_in_minutes' => is_numeric($raw['readyInMinutes'] ?? null) ? (int) $raw['readyInMinutes'] : null,
            'servings' => is_numeric($raw['servings'] ?? null) ? (int) $raw['servings'] : null,
            'ingredients' => $ingredients,
            'instructions' => self::instructions($raw),
            'image' => is_string($raw['image'] ?? null) ? $raw['image'] : null,
        ];
    }

    /**
     * Prefer the structured steps; fall back to the free-text (HTML) instructions.
     *
     * @param array<string, mixed> $raw
     * @return list<string>|null
     */
    private static function instructions(array $raw): ?array
    {
        $steps = [];
        foreach ($raw['analyzedInstructions'] ?? [] as $block) {
            foreach ($block['steps'] ?? [] as $step) {
                $text = trim((string) ($step['step'] ?? ''));
                if ($text !== '') {
                    $steps[] = $text;
                }
            }
        }

        if ($steps !== []) {
            return $steps;
        }

        // Turn list items, paragraphs and line breaks into separate lines before stripping the HTML.
        $html = preg_replace('#</li>|</p>|<br\s*/?>#i', "\n", (string) ($raw['instructions'] ?? ''));
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            static fn (string $line): bool => $line !== '',
        ));

        return $lines === [] ? null : $lines;
    }
}
