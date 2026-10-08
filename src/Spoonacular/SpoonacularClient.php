<?php

declare(strict_types=1);

namespace Aldaba\Spoonacular;

use Aldaba\HttpClient\HttpClient;
use Aldaba\HttpClient\TransportException;

/**
 * Talks to the Spoonacular API: builds the requests and turns failures into SpoonacularException.
 *
 * @see https://spoonacular.com/food-api/docs
 */
final class SpoonacularClient
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
    }

    /**
     * Full "Get Recipe Information" data of the first recipe matching the name, or null if none matches.
     *
     * The search alone does not include ingredients or instructions, hence the second request.
     *
     * @return array<string, mixed>|null
     */
    public function findFirstByName(string $name): ?array
    {
        $search = $this->get('/recipes/complexSearch', ['query' => $name, 'number' => 1]);

        $first = $search['results'][0] ?? null;
        if (!is_array($first) || !is_numeric($first['id'] ?? null)) {
            return null;
        }

        return $this->get('/recipes/' . (int) $first['id'] . '/information', ['includeNutrition' => 'false']);
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        if ($this->apiKey === '') {
            throw new \LogicException('SPOONACULAR_API_KEY is not configured.');
        }

        $url = rtrim($this->baseUrl, '/') . $path . '?' . http_build_query($query);

        try {
            // The key goes in a header rather than the URL: this reduces the risk of it showing up in access logs,
            // proxies or error messages that include the URL (it does not guarantee it is never logged).
            $response = $this->http->get($url, ['Accept' => 'application/json', 'x-api-key' => $this->apiKey]);
        } catch (TransportException $e) {
            throw $e->timedOut
                ? new SpoonacularTimeout('Spoonacular did not respond in time.', 0, $e)
                : new SpoonacularException('Could not reach Spoonacular: ' . $e->getMessage(), 0, $e);
        }

        match (true) {
            $response->status === 401 => throw new SpoonacularException('Spoonacular rejected the API key.'),
            $response->status === 402,
            $response->status === 429 => throw new SpoonacularLimitExceeded("Spoonacular limit exceeded (HTTP {$response->status})."),
            $response->status >= 400 => throw new SpoonacularException("Spoonacular returned HTTP {$response->status}."),
            default => null,
        };

        $data = json_decode($response->body, true);
        if (!is_array($data)) {
            throw new SpoonacularException('Spoonacular returned invalid JSON.');
        }

        return $data;
    }
}
