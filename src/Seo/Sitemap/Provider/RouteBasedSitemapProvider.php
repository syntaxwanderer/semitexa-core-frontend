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

        $routes = $this->attributeDiscovery->getRoutes();
        usort($routes, static fn (array $a, array $b): int => ($a['path'] ?? '') <=> ($b['path'] ?? ''));

        foreach ($routes as $route) {
            if (!$this->isEligible($route)) {
                continue;
            }

            $path = (string) $route['path'];
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

        $path = (string) ($route['path'] ?? '');
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
            if (str_contains((string) $type, 'html')) {
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
            static fn (mixed $v): string => strtoupper(trim((string) $v)),
            $methods,
        )));
    }
}
