<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo\Sitemap\Provider;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Discovery\AttributeDiscovery;
use Semitexa\Ssr\Seo\AiSitemapLocator;
use Semitexa\Ssr\Seo\Sitemap\AsSitemapProvider;
use Semitexa\Ssr\Seo\Sitemap\SitemapGenerationContext;
use Semitexa\Ssr\Seo\Sitemap\SitemapUrl;
use Semitexa\Ssr\Seo\Sitemap\SitemapUrlProviderInterface;

/**
 * Default sitemap provider that yields URLs for all public, GET, HTML-like
 * routes discovered via AttributeDiscovery.
 *
 * Uses a high priority value (1000) so custom module providers run first.
 */
#[AsSitemapProvider(priority: 1000)]
final class RouteBasedSitemapProvider implements SitemapUrlProviderInterface
{
    #[InjectAsReadonly]
    protected ?AttributeDiscovery $attributeDiscovery = null;

    /** @var list<string> */
    private const array EXCLUDED_PATHS = [
        '/robots.txt',
        '/llms.txt',
        '/sitemap.xml',
        AiSitemapLocator::PATH,
    ];

    public function provideUrls(SitemapGenerationContext $context): iterable
    {
        if ($this->attributeDiscovery === null) {
            return;
        }

        $routes = array_values(array_filter(
            $this->attributeDiscovery->getRoutes(),
            static fn (mixed $route): bool => is_array($route),
        ));
        /** @var list<array<string, mixed>> $routes */
        usort($routes, fn (array $a, array $b): int => $this->stringValue($a['path'] ?? '') <=> $this->stringValue($b['path'] ?? ''));

        foreach ($routes as $route) {
            if (!$this->isEligible($route)) {
                continue;
            }

            $path = $this->stringValue($route['path'] ?? '');
            $url = rtrim($context->baseUrl, '/') . '/' . ltrim($path, '/');

            yield new SitemapUrl(
                loc: $url,
                changefreq: 'weekly',
                priority: 0.5,
            );
        }
    }

    /**
     * @param array<string, mixed> $route
     */
    private function isEligible(array $route): bool
    {
        if (($route['public'] ?? true) !== true) {
            return false;
        }

        $path = $this->stringValue($route['path'] ?? '');
        if ($path === '' || str_starts_with($path, '/__semitexa')) {
            return false;
        }

        if (in_array($path, self::EXCLUDED_PATHS, true)) {
            return false;
        }

        // Skip templated paths (e.g. /products/{slug}) — these need concrete URLs
        if (str_contains($path, '{') && str_contains($path, '}')) {
            return false;
        }

        if (!in_array('GET', $this->normalizeMethods($route), true)) {
            return false;
        }

        return $this->isHtmlLikeRoute($route);
    }

    /**
     * @param array<string, mixed> $route
     */
    private function isHtmlLikeRoute(array $route): bool
    {
        $produces = $route['produces'] ?? [];
        if (!is_array($produces) || $produces === []) {
            return true;
        }

        foreach ($produces as $type) {
            if (is_scalar($type) && str_contains((string) $type, 'html')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $route
     * @return list<string>
     */
    private function normalizeMethods(array $route): array
    {
        $methods = $route['methods'] ?? [$route['method'] ?? 'GET'];
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        return array_values(array_unique(array_map(
            fn (mixed $v): string => strtoupper(trim($this->stringValue($v))),
            $methods,
        )));
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }
}
