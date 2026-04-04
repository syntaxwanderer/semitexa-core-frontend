<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo;

use Semitexa\Core\Discovery\AttributeDiscovery;

final class AiSitemapJsonRenderer
{
    public static function render(): string
    {
        return json_encode(
            self::buildDocument(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDocument(): array
    {
        AttributeDiscovery::initialize();

        $pages = [];
        $endpoints = [];
        $templates = [];

        $routes = AttributeDiscovery::getRoutes();
        usort($routes, static fn (array $a, array $b): int => ($a['path'] ?? '') <=> ($b['path'] ?? ''));

        foreach ($routes as $route) {
            if (!self::isEligibleRoute($route)) {
                continue;
            }

            $path = (string) $route['path'];
            $entry = self::buildRouteEntry($route, $path);

            if (self::isTemplatedPath($path)) {
                $templates[] = $entry + [
                    'path_template' => $path,
                    'parameters' => self::extractPathParameters($path),
                ];
                continue;
            }

            if (self::isHtmlLikeRoute($route)) {
                $pages[] = $entry;
                continue;
            }

            $endpoints[] = $entry;
        }

        return [
            'version' => '1.0',
            'generated_at' => gmdate(DATE_ATOM),
            'site' => [
                'ai_sitemap' => AiSitemapLocator::absoluteUrl(),
                'robots' => self::absoluteUrl('/robots.txt'),
                'llms' => self::absoluteUrl('/llms.txt'),
            ],
            'hints' => [
                'purpose' => 'Crawler-oriented route inventory for LLMs and other machine agents.',
                'preferred_flow' => [
                    'Start from pages for human-readable entry points.',
                    'Append ?_format=json to HTML pages for machine-readable page documents.',
                    'Append ?_format=json&_slot=<slot-name> for slot-level documents when needed.',
                ],
            ],
            'pages' => $pages,
            'endpoints' => $endpoints,
            'templates' => $templates,
        ];
    }

    /**
     * @param array<string, mixed> $route
     * @return array<string, mixed>
     */
    private static function buildRouteEntry(array $route, string $path): array
    {
        return [
            'path' => $path,
            'url' => self::absoluteUrl($path),
            'route_name' => $route['name'] ?? null,
            'methods' => self::normalizeMethods($route),
            'payload_class' => $route['class'] ?? null,
            'alternates' => [
                'json' => self::absoluteUrl($path) . '?_format=json',
            ],
            'content_types' => self::normalizeProduces($route),
        ];
    }

    /**
     * @param array<string, mixed> $route
     */
    private static function isEligibleRoute(array $route): bool
    {
        if (($route['public'] ?? true) !== true) {
            return false;
        }

        $path = (string) ($route['path'] ?? '');
        if ($path === '' || str_starts_with($path, '/__semitexa_')) {
            return false;
        }

        if (in_array($path, ['/robots.txt', '/llms.txt', AiSitemapLocator::PATH], true)) {
            return false;
        }

        return in_array('GET', self::normalizeMethods($route), true);
    }

    /**
     * @param array<string, mixed> $route
     */
    private static function isHtmlLikeRoute(array $route): bool
    {
        $produces = self::normalizeProduces($route);
        if ($produces === []) {
            return true;
        }

        foreach ($produces as $type) {
            if (str_contains($type, 'html')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $route
     * @return list<string>
     */
    private static function normalizeMethods(array $route): array
    {
        $methods = $route['methods'] ?? [$route['method'] ?? 'GET'];
        $normalized = array_values(array_unique(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            is_array($methods) ? $methods : [$methods]
        )));
        sort($normalized);

        return array_values(array_filter($normalized, static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param array<string, mixed> $route
     * @return list<string>
     */
    private static function normalizeProduces(array $route): array
    {
        $produces = $route['produces'] ?? [];
        if (!is_array($produces)) {
            $produces = [$produces];
        }

        $normalized = array_values(array_unique(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $produces
        )));

        return array_values(array_filter($normalized, static fn (string $value): bool => $value !== ''));
    }

    private static function isTemplatedPath(string $path): bool
    {
        return str_contains($path, '{') && str_contains($path, '}');
    }

    /**
     * @return list<string>
     */
    private static function extractPathParameters(string $path): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+\??)\}/', $path, $matches);

        return array_values(array_map(
            static fn (string $value): string => rtrim($value, '?'),
            $matches[1] ?? []
        ));
    }

    private static function absoluteUrl(string $path): string
    {
        return AiSitemapLocator::originUrl() . '/' . ltrim($path, '/');
    }
}
