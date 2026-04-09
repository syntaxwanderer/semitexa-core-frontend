<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Log;

use Semitexa\Core\Log\LoggerInterface;

/**
 * Static logger accessor for SSR subsystem.
 *
 * Most SSR classes use static methods (boot-time registries, renderers) and cannot
 * use property injection. This thin accessor is set once during bootstrap and provides
 * a nullable logger to all static call sites.
 *
 * Falls back to error_log() when no logger is configured (e.g. during tests).
 */
final class SsrLogger
{
    private static ?LoggerInterface $logger = null;

    public static function set(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function get(): ?LoggerInterface
    {
        return self::$logger;
    }

    public static function error(string $message, array $context = []): void
    {
        if (self::$logger !== null) {
            self::$logger->error($message, $context);
            return;
        }
        error_log('[Semitexa SSR] ' . $message);
    }

    public static function warning(string $message, array $context = []): void
    {
        if (self::$logger !== null) {
            self::$logger->warning($message, $context);
            return;
        }
        error_log('[Semitexa SSR] ' . $message);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (self::$logger !== null) {
            self::$logger->debug($message, $context);
            return;
        }
        // Debug messages are silently dropped when no logger is configured
    }

    public static function reset(): void
    {
        self::$logger = null;
    }
}
