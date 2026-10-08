<?php

declare(strict_types=1);

namespace Aldaba;

/**
 * Application settings, read from environment variables with a `.env` file as fallback.
 */
final class Config
{
    public function __construct(
        public readonly string $spoonacularApiKey,
        public readonly string $spoonacularBaseUrl = 'https://api.spoonacular.com',
        public readonly int $spoonacularTimeout = 5,
        public readonly int $cacheTtl = 3600,
    ) {
    }

    /**
     * Real environment variables (e.g. set by Docker) take precedence over the `.env` file.
     */
    public static function fromEnvironment(string $envFile): self
    {
        $file = is_file($envFile) ? self::parseEnvFile((string) file_get_contents($envFile)) : [];
        $get = static function (string $name, string $default) use ($file): string {
            $value = getenv($name);

            return $value !== false && $value !== '' ? $value : ($file[$name] ?? $default);
        };

        return new self(
            spoonacularApiKey: $get('SPOONACULAR_API_KEY', ''),
            spoonacularBaseUrl: $get('SPOONACULAR_BASE_URL', 'https://api.spoonacular.com'),
            spoonacularTimeout: max(1, (int) $get('SPOONACULAR_TIMEOUT', '5')),
            cacheTtl: max(0, (int) $get('CACHE_TTL', '3600')),
        );
    }

    /**
     * Parses `KEY=value` lines. Blank lines and `#` comments are ignored; surrounding quotes are removed.
     *
     * @return array<string, string>
     */
    public static function parseEnvFile(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $values[$name] = trim($value, '"\'');
        }

        return $values;
    }
}
