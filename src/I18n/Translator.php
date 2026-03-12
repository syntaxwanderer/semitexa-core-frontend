<?php

declare(strict_types=1);

namespace Semitexa\Ssr\I18n;

use Semitexa\Core\Util\ProjectRoot;
use Semitexa\Locale\Context\LocaleManager;
use Semitexa\Locale\I18n\Loader\JsonFileLoader;
use Semitexa\Locale\I18n\TranslationCatalog;
use Semitexa\Locale\I18n\TranslationService;

/**
 * Static facade for backward compatibility.
 *
 * Delegates to TranslationService internally. Prefer injecting
 * TranslationService via DI in new code.
 *
 * @deprecated Use Semitexa\Locale\I18n\TranslationService via DI instead.
 */
final class Translator
{
    private static ?TranslationService $service = null;
    private static bool $initialized = false;

    /**
     * Set the backing TranslationService (call at worker boot).
     */
    public static function setService(TranslationService $service): void
    {
        self::$service = $service;
        self::$initialized = true;
    }

    /**
     * Get or lazily create the TranslationService.
     */
    public static function getService(): TranslationService
    {
        self::initialize();

        return self::$service;
    }

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$service = self::buildService();
        self::$initialized = true;
    }

    public static function trans(string $key, array $params = []): string
    {
        self::initialize();

        return self::$service->trans($key, $params);
    }

    public static function transChoice(string $key, int $count, array $params = []): string
    {
        self::initialize();

        return self::$service->transChoice($key, $count, $params);
    }

    public static function setLocale(string $locale): void
    {
        if (!class_exists(LocaleManager::class)) {
            return;
        }

        LocaleManager::getInstance()->setLocale($locale);
    }

    public static function getLocale(): string
    {
        if (!class_exists(LocaleManager::class)) {
            return 'en';
        }

        return LocaleManager::getInstance()->getLocale();
    }

    /**
     * Reset state (for tests).
     */
    public static function reset(): void
    {
        self::$service = null;
        self::$initialized = false;
    }

    private static function buildService(): TranslationService
    {
        $catalog = new TranslationCatalog();
        $modulesRoot = ProjectRoot::get() . '/src/modules';
        $loader = new JsonFileLoader($modulesRoot);
        $loader->load($catalog);

        $localeContext = class_exists(LocaleManager::class)
            ? LocaleManager::getInstance()
            : new \Semitexa\Core\Locale\DefaultLocaleContext();

        return new TranslationService($catalog, $localeContext);
    }
}
