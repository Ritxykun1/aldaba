<?php

declare(strict_types=1);

use Aldaba\App;
use Aldaba\Cache\FileCache;
use Aldaba\Config;
use Aldaba\Controller\DocsController;
use Aldaba\Controller\RecipeController;
use Aldaba\Http\Request;
use Aldaba\HttpClient\CurlHttpClient;
use Aldaba\Recipe\RecipeService;
use Aldaba\Spoonacular\SpoonacularClient;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$config = Config::fromEnvironment($root . '/.env');

// Manual dependency injection: every object receives what it needs through its constructor.
$recipes = new RecipeService(
    new SpoonacularClient(
        new CurlHttpClient($config->spoonacularTimeout),
        $config->spoonacularBaseUrl,
        $config->spoonacularApiKey,
    ),
    new FileCache($root . '/storage/cache', $config->cacheTtl),
);

$app = new App(
    new RecipeController($recipes),
    new DocsController($root . '/docs'),
);

$app->handle(Request::fromGlobals())->send();
