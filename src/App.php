<?php

declare(strict_types=1);

namespace Aldaba;

use Aldaba\Controller\DocsController;
use Aldaba\Controller\RecipeController;
use Aldaba\Http\HttpException;
use Aldaba\Http\Request;
use Aldaba\Http\Response;
use Aldaba\Recipe\RecipeNotFound;
use Aldaba\Spoonacular\SpoonacularException;
use Aldaba\Spoonacular\SpoonacularLimitExceeded;
use Aldaba\Spoonacular\SpoonacularTimeout;

/**
 * Routes the request to a controller and turns every exception into a JSON error response.
 * This is the only place where exceptions become HTTP status codes.
 */
final class App
{
    public function __construct(
        private readonly RecipeController $recipes,
        private readonly DocsController $docs,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->route($request);
        } catch (HttpException $e) {
            return Response::error($e->getMessage(), $e->status, $e->status === 405 ? ['Allow' => 'GET'] : []);
        } catch (RecipeNotFound $e) {
            return Response::error($e->getMessage(), 404);
        } catch (SpoonacularTimeout $e) {
            error_log((string) $e);

            return Response::error('The recipe provider did not respond in time.', 504);
        } catch (SpoonacularLimitExceeded $e) {
            error_log((string) $e);

            return Response::error('The recipe provider is temporarily unavailable. Try again later.', 503);
        } catch (SpoonacularException $e) {
            error_log((string) $e);

            return Response::error('The recipe provider returned an error.', 502);
        } catch (\Throwable $e) {
            error_log((string) $e);

            return Response::error('Internal server error.', 500);
        }
    }

    private function route(Request $request): Response
    {
        // All routes are GET, so a path -> handler map is all the routing this API needs.
        $routes = [
            '/api/recipes' => fn (): Response => $this->recipes->search($request),
            '/docs' => fn (): Response => $this->docs->ui(),
            '/openapi.yaml' => fn (): Response => $this->docs->spec(),
        ];

        $path = rtrim($request->path, '/') ?: '/';
        $handler = $routes[$path] ?? throw new HttpException(404, 'Route not found.');

        if ($request->method !== 'GET') {
            throw new HttpException(405, 'Method not allowed.');
        }

        return $handler();
    }
}
