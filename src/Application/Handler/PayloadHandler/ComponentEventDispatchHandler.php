<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Handler\PayloadHandler;

use Semitexa\Core\Attributes\AsPayloadHandler;
use Semitexa\Core\Attributes\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Core\Exception\DomainException;
use Semitexa\Core\Http\Response\GenericResponse;
use Semitexa\Core\Server\SwooleBootstrap;
use Semitexa\Ssr\Application\Payload\Request\ComponentEventDispatchPayload;
use Semitexa\Ssr\Component\ComponentEventBridge;
use Semitexa\Ssr\Component\ComponentRegistry;

#[AsPayloadHandler(payload: ComponentEventDispatchPayload::class, resource: GenericResponse::class)]
final class ComponentEventDispatchHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected EventDispatcherInterface $eventDispatcher;

    public function handle(ComponentEventDispatchPayload $payload, GenericResponse $resource): GenericResponse
    {
        if (!$this->isSameOriginRequest()) {
            throw new DomainException('Cross-origin component event dispatch is not allowed.');
        }

        $component = ComponentRegistry::get($payload->getComponentName());
        if ($component === null || ($component['event'] ?? null) === null) {
            throw new DomainException('Component event bridge metadata not found.');
        }

        $frontendEvent = strtolower(trim($payload->getFrontendEvent()));
        if (!in_array($frontendEvent, (array) ($component['triggers'] ?? []), true)) {
            throw new DomainException('Frontend trigger is not declared for this component.');
        }

        if ((string) $component['event'] !== $payload->getEventClass()) {
            throw new DomainException('Posted event class does not match component contract.');
        }

        $manifest = [
            'componentId' => $payload->getComponentId(),
            'componentName' => $payload->getComponentName(),
            'eventClass' => $payload->getEventClass(),
            'triggers' => (array) ($component['triggers'] ?? []),
            'endpoint' => ComponentEventBridge::ENDPOINT_PATH,
            'pagePath' => $payload->getPagePath(),
            'issuedAt' => $payload->getIssuedAt(),
            'signature' => $payload->getSignature(),
        ];

        if (!ComponentEventBridge::verifyManifest($manifest)) {
            throw new DomainException('Component event signature validation failed.');
        }

        $eventPayload = array_merge(
            $payload->getDeclaredPayload(),
            $payload->getInteraction(),
            [
                '_frontend' => [
                    'event' => $frontendEvent,
                    'component_id' => $payload->getComponentId(),
                    'component_name' => $payload->getComponentName(),
                    'page' => $payload->getPagePath(),
                ],
            ],
        );

        $event = $this->eventDispatcher->create($payload->getEventClass(), $eventPayload);
        $this->eventDispatcher->dispatch($event);

        $body = json_encode([
            'status' => 'accepted',
            'component_id' => $payload->getComponentId(),
            'component_name' => $payload->getComponentName(),
            'frontend_event' => $frontendEvent,
            'event_class' => $payload->getEventClass(),
            'page_path' => $payload->getPagePath(),
            'accepted_at' => gmdate(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $resource
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setContent($body);
    }

    private function isSameOriginRequest(): bool
    {
        $ctx = SwooleBootstrap::getCurrentSwooleRequestResponse();
        if ($ctx === null) {
            return true;
        }

        $request = $ctx[0];
        $host = trim((string) ($request->header['host'] ?? ''));
        if ($host === '') {
            return true;
        }

        foreach (['origin', 'referer'] as $headerName) {
            $value = trim((string) ($request->header[$headerName] ?? ''));
            if ($value === '') {
                continue;
            }

            $requestHost = parse_url($value, PHP_URL_HOST);
            if (!is_string($requestHost) || $requestHost === '') {
                continue;
            }

            $requestPort = parse_url($value, PHP_URL_PORT);
            $normalizedHost = strtolower($host);
            $normalizedRequestHost = strtolower($requestHost . ($requestPort !== null ? ':' . $requestPort : ''));

            if ($normalizedRequestHost !== $normalizedHost && strtolower($requestHost) !== $normalizedHost) {
                return false;
            }
        }

        return true;
    }
}
