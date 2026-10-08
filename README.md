# Recipe API

## Descripción

Servicio REST en **PHP 8.3 puro, sin framework**, que busca una receta por nombre en la [API de Spoonacular](https://spoonacular.com/food-api) y devuelve el primer resultado con:

- nombre,
- tiempo estimado de preparación,
- número de porciones,
- lista de ingredientes,
- instrucciones de preparación (si existen),
- URL de la imagen.

Incluye caché en ficheros, Docker, documentación OpenAPI/Swagger y tests con PHPUnit.

## Requisitos

- Una API key de Spoonacular (gratuita): https://spoonacular.com/food-api/console#Profile
- **Docker** con Docker Compose, o bien
- **PHP 8.3+** con las extensiones `curl` y `mbstring`, y **Composer**.

## Instalación

```bash
git clone <url-del-repositorio> recipe-api
cd recipe-api
cp .env.example .env
composer install        # no es necesario si usas Docker
```

## Configuración

Toda la configuración se lee de variables de entorno. En local se usa el fichero `.env`; si una variable existe también en el entorno real (por ejemplo, la define Docker), tiene prioridad.

| Variable | Por defecto | Descripción |
|---|---|---|
| `SPOONACULAR_API_KEY` | — | API key de Spoonacular (**obligatoria**) |
| `SPOONACULAR_BASE_URL` | `https://api.spoonacular.com` | URL base de la API |
| `SPOONACULAR_TIMEOUT` | `5` | Segundos de espera máxima a Spoonacular |
| `CACHE_TTL` | `3600` | Segundos que una receta permanece en caché |

### Spoonacular API Key

1. Crea una cuenta en https://spoonacular.com/food-api/console.
2. Copia la clave desde *Profile → Show API Key*.
3. Pégala en `.env`:

   ```dotenv
   SPOONACULAR_API_KEY=tu_api_key
   ```

`.env` está en `.gitignore` y `.dockerignore`: la clave no se sube al repositorio ni se copia en la imagen Docker.

## Ejecución local

```bash
composer serve
```

Usa el servidor integrado de PHP en http://localhost:8080.

## Ejecución con Docker

```bash
cp .env.example .env    # y rellena SPOONACULAR_API_KEY
docker compose up --build
```

La API queda en http://localhost:8080. La imagen usa Apache con `mod_php` y sirve únicamente el directorio `public/`.

## Endpoint

### `GET /api/recipes?name={nombre}`

| Parámetro | Ubicación | Obligatorio | Reglas |
|---|---|---|---|
| `name` | query | sí | texto, de 1 a 100 caracteres, con al menos una letra o número |

```bash
curl "http://localhost:8080/api/recipes?name=pasta%20carbonara"
```

Si Spoonacular devuelve varios resultados, solo se devuelve el primero.

### Ejemplo de respuesta

`200 OK`

```json
{
  "data": {
    "name": "Pasta Carbonara",
    "ready_in_minutes": 25,
    "servings": 4,
    "ingredients": ["400g spaghetti", "4 egg yolks", "100g guanciale"],
    "instructions": ["Boil the pasta.", "Fry the guanciale.", "Mix with the yolks off the heat."],
    "image": "https://img.spoonacular.com/recipes/654959-556x370.jpg"
  }
}
```

- `instructions` es la lista ordenada de pasos, o `null` si la receta no tiene instrucciones.
- `ready_in_minutes`, `servings` e `image` son `null` si Spoonacular no los informa.

## Errores

Todos los errores tienen el mismo formato:

```json
{ "error": { "message": "No recipe found for 'xyzzy'." } }
```

| Código | Cuándo |
|---|---|
| 404 | Ninguna receta coincide con el nombre, o la ruta no existe |
| 405 | Método distinto de `GET` |
| 422 | `name` falta, está vacío, supera 100 caracteres o no tiene letras ni números |
| 502 | Spoonacular no responde, rechaza la API key, devuelve un error o un JSON inválido |
| 503 | Se ha agotado la cuota diaria (402) o el límite por minuto (429) de Spoonacular |
| 504 | Spoonacular no ha respondido en `SPOONACULAR_TIMEOUT` segundos |
| 500 | Error inesperado (por ejemplo, falta `SPOONACULAR_API_KEY`) |

Los detalles técnicos (excepción, traza, respuesta de Spoonacular) solo se escriben en el log del servidor, nunca en la respuesta.

Ejemplos:

```bash
# 422: nombre vacío
curl -i "http://localhost:8080/api/recipes?name="
# HTTP/1.1 422 Unprocessable Entity
# Content-Type: application/json
#
# {"error":{"message":"The 'name' query parameter must not be empty."}}

# 404: ninguna receta coincide (Spoonacular no devuelve resultados para ese nombre)
curl -i "http://localhost:8080/api/recipes?name=xyzzyqwertyuiop"
# HTTP/1.1 404 Not Found
# Content-Type: application/json
#
# {"error":{"message":"No recipe found for 'xyzzyqwertyuiop'."}}
```

## Caché

- Se guarda en ficheros JSON en `storage/cache/`, uno por receta, con caducidad `CACHE_TTL`.
- La clave es el nombre normalizado: `Pasta Carbonara`, ` pasta  carbonara ` y `pasta-carbonara` comparten la entrada `recipe:pasta-carbonara`.
- Se guarda la respuesta ya transformada, no la de Spoonacular.
- Solo se cachean las búsquedas con éxito: ni los errores ni los "no encontrado".

## Tests

```bash
composer test
# o dentro de Docker:
docker compose run --rm app vendor/bin/phpunit
```

Ningún test accede a Internet: la red se sustituye por `tests/Support/FakeHttpClient`, que devuelve respuestas de Spoonacular preparadas y registra las peticiones realizadas.

- **Unitarios** (`tests/Unit`): transformación de la respuesta, normalización de la clave de caché, caché en ficheros, configuración y traducción de errores HTTP del cliente de Spoonacular.
- **Integración** (`tests/Integration`): la aplicación completa (rutas → controlador → servicio → caché → cliente) con peticiones HTTP simuladas. Cubren receta encontrada, varios resultados, 404, 422, errores de Spoonacular, caché, receta sin instrucciones, JSON inválido y timeout.

## Swagger

- Swagger UI: http://localhost:8080/docs
- Especificación OpenAPI 3: http://localhost:8080/openapi.yaml ([docs/openapi.yaml](docs/openapi.yaml))

## Arquitectura

```
public/index.php                  punto de entrada: lee la configuración, crea los objetos y atiende la petición
src/App.php                       rutas y conversión de excepciones en respuestas JSON
src/Config.php                    variables de entorno y fichero .env
src/Controller/                   RecipeController (validación + respuesta) y DocsController (Swagger)
src/Http/                         Request, Response, HttpException
src/Recipe/RecipeService.php      caché + transformación al formato público
src/Spoonacular/                  cliente de Spoonacular y sus excepciones
src/HttpClient/                   interfaz HttpClient y su implementación con cURL
src/Cache/FileCache.php           caché en ficheros con TTL
docs/                             openapi.yaml y Swagger UI
```

Flujo de una petición:

```
index.php → App (ruta) → RecipeController (valida) → RecipeService (caché / transforma)
          → SpoonacularClient (petición y errores) → HttpClient (cURL) → Spoonacular
```

## Decisiones técnicas

- **Sin framework ni dependencias de runtime.** Solo PHPUnit en desarrollo. Composer se usa para el autoload PSR-4.
- **`GET /api/recipes?name=`.** Es una búsqueda sobre la colección de recetas y el nombre no identifica un recurso único (puede devolver resultados distintos con el tiempo), así que va como parámetro de query y no en el path.
- **cURL nativo detrás de una interfaz `HttpClient`.** Para una sola petición GET, Guzzle no aporta lo suficiente para justificar la dependencia. La interfaz es lo que permite testear sin red.
- **Dos llamadas a Spoonacular.** `complexSearch` con `number=1` obtiene el id del primer resultado y `/recipes/{id}/information` trae ingredientes e instrucciones, que la búsqueda no incluye. La caché amortiza la segunda llamada.
- **Excepciones de dominio, una sola traducción a HTTP.** El servicio y el cliente lanzan `RecipeNotFound` y `SpoonacularException` (y sus subtipos). Solo `App` las convierte en códigos HTTP.
- **Inyección de dependencias manual** en `public/index.php`, sin contenedor: son unos pocos objetos.
- **Caché en ficheros.** Funciona igual en local y en Docker sin servicios extra. Redis o APCu serían la opción con varias instancias.
- **Apache + mod_php en Docker.** Una sola imagen oficial y un único proceso; PHP-FPM + Nginx exigiría dos contenedores para el mismo resultado.
- **Seguridad.** La API key nunca está en el código y se envía en la cabecera `x-api-key` en lugar de en la URL: así se reduce el riesgo de que aparezca en logs de acceso, proxies o mensajes de error asociados a la URL (aunque no garantiza que nunca se registre). Se valida la entrada; los parámetros se codifican con `http_build_query` y el id con `(int)`. La URL de Spoonacular es fija por configuración y no la controla el usuario. Las respuestas incluyen `X-Content-Type-Options: nosniff`, y el contenedor usa `php.ini-production` y oculta las versiones de PHP y Apache.

## Mejoras futuras

- Caché compartida (Redis) si se despliega más de una instancia.
- Rate limiting propio por cliente, para proteger la cuota de Spoonacular.
- Cachear también los "no encontrado" con un TTL corto.
- Logs estructurados (PSR-3) en lugar de `error_log`.
