<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
| docs/api/openapi.yaml is written by hand: these tests keep it aligned
| with the routes. In Docker the folder is mounted read only next to the
| backend (docker-compose.yml).
*/

/**
 * @return array<string, mixed>
 */
function openApiSpec(): array
{
    return Yaml::parseFile(base_path('../docs/api/openapi.yaml'));
}

/**
 * Operations of the specification as "METHOD /path", sorted.
 *
 * @return list<string>
 */
function documentedOperations(): array
{
    $operations = [];

    foreach (openApiSpec()['paths'] as $path => $item) {
        foreach (array_keys($item) as $method) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $operations[] = strtoupper($method).' '.$path;
            }
        }
    }

    sort($operations);

    return $operations;
}

/**
 * Routes under api/v1 as "METHOD /path", relative to the server of the
 * specification, without HEAD and with the binding field dropped from the
 * parameters ({category:slug} becomes {category}).
 *
 * @return list<string>
 */
function apiRouteOperations(): array
{
    $operations = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var RouteDefinition $route */
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        $path = preg_replace('/\{(\w+):\w+\}/', '{$1}', substr($route->uri(), strlen('api/v1')));

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $operations[] = $method.' '.$path;
        }
    }

    sort($operations);

    return $operations;
}

it('documents every route of the api', function () {
    expect(array_values(array_diff(apiRouteOperations(), documentedOperations())))->toBe([]);
});

it('documents only routes that exist, with their method', function () {
    expect(array_values(array_diff(documentedOperations(), apiRouteOperations())))->toBe([]);
});

it('documents the 78 operations of the 75 routes', function () {
    expect(documentedOperations())->toHaveCount(78);
});

it('is an openapi 3.1 document on the versioned api', function () {
    $spec = openApiSpec();

    expect($spec['openapi'])->toStartWith('3.1.')
        ->and($spec['servers'][0]['url'])->toBe('http://localhost:8000/api/v1');
});

it('gives every operation a summary, a unique id and at least one response', function () {
    $ids = [];

    foreach (openApiSpec()['paths'] as $path => $item) {
        foreach (array_intersect_key($item, array_flip(['get', 'post', 'put', 'patch', 'delete'])) as $method => $operation) {
            expect($operation['summary'] ?? null)->toBeString("{$method} {$path} has no summary")
                ->and($operation['responses'] ?? [])->not->toBeEmpty("{$method} {$path} has no responses");

            $ids[] = $operation['operationId'];
        }
    }

    expect($ids)->toHaveCount(count(array_unique($ids)));
});

it('resolves every internal reference', function () {
    $spec = openApiSpec();
    $references = [];

    array_walk_recursive($spec, function (mixed $value, string|int $key) use (&$references) {
        if ($key === '$ref') {
            $references[] = $value;
        }
    });

    $missing = array_values(array_filter(array_unique($references), function (string $reference) use ($spec): bool {
        $node = $spec;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return true;
            }

            $node = $node[$segment];
        }

        return false;
    }));

    expect($missing)->toBe([]);
});
