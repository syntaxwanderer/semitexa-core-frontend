<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Payload\Request;

use Semitexa\Authorization\Attribute\PublicEndpoint;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Http\Response\ResourceResponse;

#[PublicEndpoint]
#[AsPayload(
    path: '/sitemap-{part}.xml',
    methods: ['GET'],
    public: true,
    responseWith: ResourceResponse::class,
    produces: ['application/xml'],
)]
final class SitemapPartPayload
{
    public string $part = '';
}
