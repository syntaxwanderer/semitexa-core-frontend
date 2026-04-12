<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo\Sitemap;

final readonly class SitemapWriteResult
{
    public function __construct(
        public bool $success,
        public int $totalUrls,
        public int $filesWritten,
        public string $primaryPath,
    ) {}
}
