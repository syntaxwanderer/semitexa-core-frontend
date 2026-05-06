<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Payload\Request;

use Semitexa\Core\Attribute\AsPublicPayload;
use Semitexa\Core\Http\Response\ResourceResponse;

#[AsPublicPayload(
    responseWith: ResourceResponse::class,
    path: '/__semitexa_hug',
    methods: ['GET'],
    name: 'ssr.hug',
)]
class SsrFallbackPayload
{
    protected string $handle = '';
    protected string $slots = '';
    protected string $deferredRequestId = '';

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function setHandle(string $handle): void
    {
        $this->handle = $handle;
    }

    public function getSlots(): string
    {
        return $this->slots;
    }

    public function setSlots(string $slots): void
    {
        $this->slots = $slots;
    }

    public function getDeferredRequestId(): string
    {
        return $this->deferredRequestId;
    }

    public function setDeferredRequestId(string $deferredRequestId): void
    {
        $this->deferredRequestId = $deferredRequestId;
    }
}
