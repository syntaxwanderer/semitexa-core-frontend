<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Component;

use Semitexa\Ssr\Asset\AssetCollectorStore;
use Semitexa\Ssr\Template\ModuleTemplateRegistry;

final class ComponentRenderer
{
    private static array $renderedSlots = [];

    public static function render(string $name, array $props = [], array $slots = []): string
    {
        $component = ComponentRegistry::get($name);

        if ($component === null) {
            return "<!-- Component '{$name}' not found -->";
        }

        $previousSlots = self::$renderedSlots;
        self::$renderedSlots[$name] = $slots;

        try {
            $template = $component['template'] ?? "components/{$name}.html.twig";
            $manifest = null;
            $componentId = null;

            if (($component['event'] ?? null) !== null || ($component['script'] ?? null) !== null) {
                $componentId = 'cmp_' . bin2hex(random_bytes(8));
            }

            if (($component['event'] ?? null) !== null) {
                $manifest = ComponentEventBridge::buildManifest($component, $componentId);
            }

            if (class_exists(AssetCollectorStore::class)) {
                $collector = AssetCollectorStore::get();

                if (($component['event'] ?? null) !== null) {
                    $collector->require('ssr:js:component-events');
                }

                if (($component['script'] ?? null) !== null) {
                    $collector->require('ssr:js:component-runtime');
                    $collector->require((string) $component['script']);
                }
            }

            $context = array_merge($props, [
                '_component' => $component,
                '_component_event_manifest' => $manifest,
                '_component_id' => $componentId,
                '_slots' => $slots,
            ]);

            $html = ModuleTemplateRegistry::getTwig()->render($template, $context);

            $html = self::processNestedComponents($html);

            if ($componentId !== null) {
                $html = ComponentEventBridge::annotateRoot($html, $component, $componentId, $manifest);
            }

            return $html;
        } finally {
            self::$renderedSlots = $previousSlots;
        }
    }

    private static function processNestedComponents(string $html): string
    {
        return preg_replace_callback(
            '/\{\{\s*component\(\s*["\']([^"\']+)["\']\s*(?:,\s*(\{[^\}]*\}))?\s*\)\s*\}\}/',
            function ($matches) {
                $name = $matches[1];
                $props = isset($matches[2]) ? json_decode($matches[2], true) : [];
                return self::render($name, $props, []);
            },
            $html
        );
    }

    public static function getSlot(string $componentName, string $slotName, array $default = []): array
    {
        return self::$renderedSlots[$componentName][$slotName] ?? $default;
    }
}
