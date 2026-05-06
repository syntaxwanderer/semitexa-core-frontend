<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Payload\Request;

use Semitexa\Core\Attribute\AsPublicPayload;
use Semitexa\Core\Http\Response\ResourceResponse;

#[AsPublicPayload(
    path: '/sitemap.json',
    methods: ['GET'],
    responseWith: ResourceResponse::class,
    produces: ['application/json']
)]
final class SitemapJsonPayload
{
}
