<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Context;

use Swoole\Coroutine;

final class PageRenderContextStore
{
    private const KEY_CONTEXT = '__ssr_page_render_context';

    private static array $staticContext = [];

    public static function set(array $context): void
    {
        if (self::inCoroutine()) {
            Coroutine::getContext()[self::KEY_CONTEXT] = $context;
            return;
        }

        self::$staticContext = $context;
    }

    public static function get(): array
    {
        if (self::inCoroutine()) {
            return Coroutine::getContext()[self::KEY_CONTEXT] ?? [];
        }

        return self::$staticContext;
    }

    public static function reset(): void
    {
        if (self::inCoroutine()) {
            Coroutine::getContext()[self::KEY_CONTEXT] = [];
            return;
        }

        self::$staticContext = [];
    }

    private static function inCoroutine(): bool
    {
        return class_exists(Coroutine::class, false) && Coroutine::getCid() > 0;
    }
}
