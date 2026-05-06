<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo\Sitemap;

use Semitexa\Ssr\Application\Service\Seo\Sitemap\SitemapUrlProviderInterface as AppSitemapUrlProviderInterface;

/**
 * @deprecated Use Semitexa\Ssr\Application\Service\Seo\Sitemap\SitemapUrlProviderInterface instead.
 */
if (!interface_exists(SitemapUrlProviderInterface::class, false)) {
    class_alias(AppSitemapUrlProviderInterface::class, SitemapUrlProviderInterface::class);
}
