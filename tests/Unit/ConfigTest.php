<?php

declare(strict_types=1);

namespace Aldaba\Tests\Unit;

use Aldaba\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const VARIABLES = ['SPOONACULAR_API_KEY', 'SPOONACULAR_BASE_URL', 'SPOONACULAR_TIMEOUT', 'CACHE_TTL'];

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        // Start from a clean environment so the tests do not depend on the machine running them.
        foreach (self::VARIABLES as $name) {
            $this->originalEnvironment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }
    }

    public function test_it_parses_an_env_file(): void
    {
        $env = <<<ENV
            # Spoonacular
            SPOONACULAR_API_KEY="abc123"

            CACHE_TTL = 60
            not a variable
            ENV;

        self::assertSame(['SPOONACULAR_API_KEY' => 'abc123', 'CACHE_TTL' => '60'], Config::parseEnvFile($env));
    }

    public function test_defaults_apply_without_env_file(): void
    {
        $config = Config::fromEnvironment('/does/not/exist/.env');

        self::assertSame('', $config->spoonacularApiKey);
        self::assertSame('https://api.spoonacular.com', $config->spoonacularBaseUrl);
        self::assertSame(5, $config->spoonacularTimeout);
        self::assertSame(3600, $config->cacheTtl);
    }

    public function test_environment_variables_take_precedence_over_env_file(): void
    {
        $envFile = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($envFile, "CACHE_TTL=60\nSPOONACULAR_TIMEOUT=7\n");
        putenv('CACHE_TTL=120');

        try {
            $config = Config::fromEnvironment($envFile);
        } finally {
            unlink($envFile);
        }

        self::assertSame(120, $config->cacheTtl);
        self::assertSame(7, $config->spoonacularTimeout);
    }
}
