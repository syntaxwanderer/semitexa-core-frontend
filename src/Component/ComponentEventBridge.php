<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Component;

use Semitexa\Core\Environment;
use Semitexa\Core\Server\SwooleBootstrap;

final class ComponentEventBridge
{
    public const ENDPOINT_PATH = '/__semitexa_component_event';

    /** @var list<string> */
    private const NATIVE_TRIGGERS = ['click', 'change', 'input', 'submit', 'hover'];

    /** @var list<string> */
    private const CUSTOM_PREFIXES = ['semitexa:', 'ui:'];

    /**
     * @param list<string> $triggers
     * @return list<string>
     */
    public static function normalizeTriggers(array $triggers): array
    {
        $normalized = [];

        foreach ($triggers as $trigger) {
            $trigger = strtolower(trim((string) $trigger));
            if ($trigger === '') {
                continue;
            }

            if (!self::isAllowedTrigger($trigger)) {
                throw new \LogicException(sprintf(
                    'Unsupported component trigger "%s".',
                    $trigger,
                ));
            }

            if (in_array($trigger, $normalized, true)) {
                throw new \LogicException(sprintf(
                    'Duplicate component trigger "%s".',
                    $trigger,
                ));
            }

            $normalized[] = $trigger;
        }

        return $normalized;
    }

    public static function buildManifest(array $component, ?string $componentId = null): array
    {
        $pagePath = self::resolveCurrentPagePath();

        $manifest = [
            'componentId' => $componentId ?? ('cmp_' . bin2hex(random_bytes(8))),
            'componentName' => (string) $component['name'],
            'eventClass' => (string) $component['event'],
            'triggers' => self::normalizeTriggers((array) ($component['triggers'] ?? [])),
            'endpoint' => self::ENDPOINT_PATH,
            'pagePath' => $pagePath,
            'issuedAt' => time(),
        ];

        $manifest['signature'] = self::sign($manifest);

        return $manifest;
    }

    public static function verifyManifest(array $manifest): bool
    {
        $signature = (string) ($manifest['signature'] ?? '');
        if ($signature === '') {
            return false;
        }

        $unsigned = $manifest;
        unset($unsigned['signature']);

        return hash_equals($signature, self::sign($unsigned));
    }

    public static function renderTriggerAttributes(array $context, string $trigger, array $payload = []): string
    {
        $component = $context['_component'] ?? null;
        if (!is_array($component) || ($component['event'] ?? null) === null) {
            return '';
        }

        $trigger = strtolower(trim($trigger));
        $allowedTriggers = self::normalizeTriggers((array) ($component['triggers'] ?? []));

        if (!in_array($trigger, $allowedTriggers, true)) {
            throw new \LogicException(sprintf(
                'Component "%s" does not declare trigger "%s".',
                (string) ($component['name'] ?? 'unknown'),
                $trigger,
            ));
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return sprintf(
            'data-semitexa-component-event="%s" data-semitexa-component-payload="%s"',
            self::escapeAttribute($trigger),
            self::escapeAttribute($payloadJson),
        );
    }

    public static function annotateRoot(string $html, array $component, string $componentId, ?array $manifest = null): string
    {
        $attributes = self::buildRootAttributes($component, $componentId, $manifest);

        return preg_replace_callback(
            '/<([a-zA-Z][a-zA-Z0-9:-]*)(\s[^>]*)?>/',
            static function (array $matches) use ($attributes): string {
                $tag = $matches[1];
                $existing = $matches[2] ?? '';

                return sprintf('<%s%s %s>', $tag, $existing, $attributes);
            },
            $html,
            1
        ) ?? $html;
    }

    public static function isAllowedTrigger(string $trigger): bool
    {
        if (in_array($trigger, self::NATIVE_TRIGGERS, true)) {
            return true;
        }

        foreach (self::CUSTOM_PREFIXES as $prefix) {
            if (str_starts_with($trigger, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function buildRootAttributes(array $component, string $componentId, ?array $manifest = null): string
    {
        $attributes = [
            'data-semitexa-component="' . self::escapeAttribute((string) $component['name']) . '"',
            'data-semitexa-component-id="' . self::escapeAttribute($componentId) . '"',
        ];

        if (($component['script'] ?? null) !== null) {
            $attributes[] = 'data-semitexa-component-script="' . self::escapeAttribute((string) $component['script']) . '"';
        }

        if ($manifest !== null) {
            $triggers = json_encode($manifest['triggers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $attributes[] = 'data-semitexa-component-event-class="' . self::escapeAttribute((string) $manifest['eventClass']) . '"';
            $attributes[] = 'data-semitexa-component-triggers="' . self::escapeAttribute($triggers) . '"';
            $attributes[] = 'data-semitexa-component-event-endpoint="' . self::escapeAttribute((string) $manifest['endpoint']) . '"';
            $attributes[] = 'data-semitexa-component-page="' . self::escapeAttribute((string) $manifest['pagePath']) . '"';
            $attributes[] = 'data-semitexa-component-issued-at="' . self::escapeAttribute((string) $manifest['issuedAt']) . '"';
            $attributes[] = 'data-semitexa-component-signature="' . self::escapeAttribute((string) $manifest['signature']) . '"';
        }

        return implode(' ', $attributes);
    }

    private static function resolveCurrentPagePath(): string
    {
        $ctx = SwooleBootstrap::getCurrentSwooleRequestResponse();
        $uri = $ctx !== null ? (string) ($ctx[0]->server['request_uri'] ?? '/') : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private static function sign(array $manifest): string
    {
        $payload = self::canonicalize($manifest);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $json, self::resolveSecret());
    }

    private static function resolveSecret(): string
    {
        $explicit = Environment::getEnvValue('APP_SECRET');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        return hash(
            'sha256',
            implode('|', [
                (string) Environment::getEnvValue('APP_NAME', 'Semitexa'),
                (string) Environment::getEnvValue('APP_HOST', 'localhost'),
                (string) Environment::getEnvValue('APP_PORT', '8000'),
            ])
        );
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
