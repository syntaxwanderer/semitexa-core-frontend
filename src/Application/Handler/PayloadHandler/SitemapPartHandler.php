<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Handler\PayloadHandler;

use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Http\Response\ResourceResponse;
use Semitexa\Core\Support\ProjectRoot;
use Semitexa\Ssr\Application\Payload\Request\SitemapPartPayload;

#[AsPayloadHandler(payload: SitemapPartPayload::class, resource: ResourceResponse::class)]
final class SitemapPartHandler implements TypedHandlerInterface
{
    public function handle(SitemapPartPayload $payload, ResourceResponse $resource): ResourceResponse
    {
        $part = preg_replace('/[^a-zA-Z0-9_-]/', '', $payload->part);
        if ($part === '' || $part === null) {
            return $resource
                ->setContent('')
                ->setStatusCode(404);
        }

        $filename = sprintf('sitemap-%s.xml', $part);
        $projectRoot = ProjectRoot::get();

        foreach ([
            $projectRoot . '/var/sitemap/' . $filename,
            $projectRoot . '/' . $filename,
            $projectRoot . '/public/' . $filename,
        ] as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $content = file_get_contents($candidate);
            if ($content !== false) {
                return $resource
                    ->setContent($content)
                    ->setHeader('Content-Type', 'application/xml; charset=utf-8');
            }
        }

        return $resource
            ->setContent('')
            ->setStatusCode(404);
    }
}
