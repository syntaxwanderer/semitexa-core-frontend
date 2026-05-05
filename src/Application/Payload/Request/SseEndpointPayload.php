<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Payload\Request;

use Semitexa\Core\Attribute\AsPublicPayload;
use Semitexa\Core\Attribute\TransportType;
use Semitexa\Core\Http\Response\ResourceResponse;

#[AsPublicPayload(
    responseWith: ResourceResponse::class,
    path: '/sse',
    methods: ['GET'],
    transport: TransportType::Sse,
    produces: ['text/event-stream'],
)]
class SseEndpointPayload
{
    protected string $sessionId = '';

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }
}
