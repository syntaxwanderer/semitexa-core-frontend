<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Payload\Request;

use Semitexa\Core\Attribute\AsPublicPayload;
use Semitexa\Core\Http\Response\ResourceResponse;

#[AsPublicPayload(
    path: '/sitemap-{part}.xml',
    methods: ['GET'],
    responseWith: ResourceResponse::class,
    produces: ['application/xml'],
)]
final class SitemapPartPayload
{
    public string $part = '';
}
