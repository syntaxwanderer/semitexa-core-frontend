<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo;

use Semitexa\Core\Environment;

final class RobotsTxtRenderer
{
    public static function render(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Semitexa crawler hints',
            '# AI sitemap: ' . AiSitemapLocator::absoluteUrl(),
            '# HTML pages may expose a machine-readable alternate JSON document.',
            '# Try the same page with ?_format=json when you want page/meta/slot IRIs.',
            '# Slot documents are addressable with ?_format=json&_slot=<name>.',
            '# Deferred regions stay part of the same SSR page model.',
        ];

        $sitemap = trim((string) (Environment::getEnvValue('ROBOTS_SITEMAP_URL') ?? ''));
        if ($sitemap !== '') {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $sitemap;
        }

        $host = trim((string) (Environment::getEnvValue('ROBOTS_HOST') ?? ''));
        if ($host !== '') {
            $lines[] = 'Host: ' . $host;
        }

        return implode("\n", $lines) . "\n";
    }
}
