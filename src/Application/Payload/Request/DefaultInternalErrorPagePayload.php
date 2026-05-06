<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Payload\Request;

use Semitexa\Core\Attribute\AsPublicPayload;
use Semitexa\Ssr\Application\Resource\Response\DefaultErrorPageResource;

#[AsPublicPayload(
    responseWith: DefaultErrorPageResource::class,
    produces: ['text/html'],
    path: '/__semitexa/error/500',
    methods: ['GET'],
    name: 'error.500',
)]
final class DefaultInternalErrorPagePayload
{
}
