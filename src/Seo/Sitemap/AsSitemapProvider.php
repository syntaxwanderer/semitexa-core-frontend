<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo\Sitemap;

use Semitexa\Ssr\Application\Service\Seo\Sitemap\AsSitemapProvider as AppAsSitemapProvider;

/**
 * @deprecated Use Semitexa\Ssr\Application\Service\Seo\Sitemap\AsSitemapProvider instead.
 */
if (!class_exists(AsSitemapProvider::class, false)) {
    class_alias(AppAsSitemapProvider::class, AsSitemapProvider::class);
}
