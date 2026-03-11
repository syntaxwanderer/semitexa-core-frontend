<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Routing;

use Semitexa\Core\Discovery\AttributeDiscovery;
use Semitexa\Core\Request;

final class UrlGenerator
{
    public static function to(string $routeName, array $params = []): string
    {
        $route = AttributeDiscovery::findRouteByName($routeName);

        if ($route === null) {
            $route = self::findByPath($routeName);
        }

        if ($route === null) {
            throw new \RuntimeException("Route '{$routeName}' not found");
        }

        return self::buildPath($route['path'], $params);
    }

    public static function current(Request $request, array $overrides = []): string
    {
        $path = $request->getUri();

        if (!empty($overrides)) {
            $query = http_build_query($overrides);
            $path = strtok($path, '?') . '?' . $query;
        }

        return $path;
    }

    private static function buildPath(string $path, array $params): string
    {
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", urlencode((string) $value), $path);
            $path = str_replace("{$key}", urlencode((string) $value), $path);
        }

        $path = preg_replace('/\{(\w+)\?\}/', '', $path);

        return $path;
    }

    private static function findByPath(string $path): ?array
    {
        return AttributeDiscovery::findRoute($path, 'GET');
    }
}
