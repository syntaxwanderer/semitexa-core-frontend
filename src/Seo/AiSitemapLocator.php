<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Seo;

use Semitexa\Core\Environment;

final class AiSitemapLocator
{
    public const PATH = '/sitemap.json';

    public static function relativePath(): string
    {
        return self::PATH;
    }

    public static function absoluteUrl(): string
    {
        $override = trim((string) (Environment::getEnvValue('AI_SITEMAP_URL') ?? ''));
        if ($override !== '') {
            return $override;
        }

        return rtrim(self::resolveOrigin(), '/') . self::PATH;
    }

    private static function resolveOrigin(): string
    {
        $appUrl = trim((string) (Environment::getEnvValue('APP_URL') ?? ''));
        if ($appUrl !== '') {
            return $appUrl;
        }

        $hostHint = trim((string) (Environment::getEnvValue('ROBOTS_HOST') ?? ''));
        if ($hostHint !== '') {
            if (preg_match('#^https?://#i', $hostHint) === 1) {
                return $hostHint;
            }

            return 'http://' . $hostHint;
        }

        $scheme = trim((string) (Environment::getEnvValue('APP_SCHEME') ?? 'http'));
        $host = trim((string) (Environment::getEnvValue('APP_HOST') ?? 'localhost'));
        $port = self::resolvePort();

        if ($port === null || self::isDefaultPort($scheme, $port)) {
            return sprintf('%s://%s', $scheme, $host);
        }

        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }

    private static function resolvePort(): ?int
    {
        $appPort = trim((string) (Environment::getEnvValue('APP_PORT') ?? ''));
        if ($appPort !== '' && ctype_digit($appPort)) {
            return (int) $appPort;
        }

        $swoolePort = trim((string) (Environment::getEnvValue('SWOOLE_PORT') ?? ''));
        if ($swoolePort !== '' && ctype_digit($swoolePort)) {
            return (int) $swoolePort;
        }

        return null;
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
