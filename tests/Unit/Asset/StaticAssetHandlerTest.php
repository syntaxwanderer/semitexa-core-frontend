<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Tests\Unit\Asset;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Ssr\Application\Service\Asset\StaticAssetHandler;

final class StaticAssetHandlerTest extends TestCase
{
    #[Test]
    public function match_prefix_accepts_assets_and_static_routes(): void
    {
        $method = new \ReflectionMethod(StaticAssetHandler::class, 'matchPrefix');
        $method->setAccessible(true);

        self::assertSame('/assets/', $method->invoke(null, '/assets/semitexa-site/css/site.css?v=1'));
        self::assertSame('/static/', $method->invoke(null, '/static/semitexa-site/css/site.css?v=1'));
        self::assertNull($method->invoke(null, '/foo/semitexa-site/css/site.css?v=1'));
    }

    /**
     * `immutable` is a one-year-no-revalidate promise — only safe when the
     * URL itself encodes the content version. Hardcoded module asset URLs
     * with no `?v=` get pinned in browsers for a year (this is the regression
     * that hid a CSRF JS fix from the live PipelineTest page). The handler
     * must downgrade unversioned URLs to a revalidating cache directive.
     */
    #[Test]
    public function unversioned_uri_downgrades_cache_control_to_must_revalidate(): void
    {
        self::assertSame(
            'public, max-age=0, must-revalidate',
            StaticAssetHandler::cacheControlForUri('/assets/PipelineTest/js/pipeline-test.js'),
        );
        self::assertSame(
            'public, max-age=0, must-revalidate',
            StaticAssetHandler::cacheControlForUri('/assets/PipelineTest/js/pipeline-test.js?other=1'),
        );
        self::assertSame(
            'public, max-age=0, must-revalidate',
            StaticAssetHandler::cacheControlForUri('/assets/PipelineTest/js/pipeline-test.js?v='),
        );
    }

    #[Test]
    public function versioned_uri_keeps_immutable_cache_control(): void
    {
        self::assertSame(
            'public, max-age=31536000, immutable',
            StaticAssetHandler::cacheControlForUri('/assets/PipelineTest/js/pipeline-test.js?v=ab12cd34ef56'),
        );
        self::assertSame(
            'public, max-age=31536000, immutable',
            StaticAssetHandler::cacheControlForUri('/assets/site/css/site.css?other=1&v=hash'),
        );
    }

    #[Test]
    public function etag_for_file_is_quoted_mtime_size_pair(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'static-asset-etag-');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, 'hello world');
            $stat = stat($tmp);
            self::assertNotFalse($stat);
            $expected = sprintf('"%x-%x"', $stat['mtime'], $stat['size']);

            self::assertSame($expected, StaticAssetHandler::etagForFile($tmp));
            self::assertStringStartsWith('"', StaticAssetHandler::etagForFile($tmp));
            self::assertStringEndsWith('"', StaticAssetHandler::etagForFile($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function etag_for_missing_file_is_empty_so_no_header_is_sent(): void
    {
        $missing = sys_get_temp_dir() . '/static-asset-handler-test-missing-' . bin2hex(random_bytes(6));
        self::assertFileDoesNotExist($missing);

        // An empty ETag sentinel signals handle() to skip emitting the header.
        // This keeps the conditional-GET path safe when stat() fails.
        self::assertSame('', @StaticAssetHandler::etagForFile($missing));
    }
}
