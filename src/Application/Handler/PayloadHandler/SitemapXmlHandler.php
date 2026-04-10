<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Application\Handler\PayloadHandler;

use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsMutable;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Http\Response\ResourceResponse;
use Semitexa\Core\Request;
use Semitexa\Core\Support\ProjectRoot;
use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Ssr\Application\Payload\Request\SitemapXmlPayload;
use Semitexa\Ssr\Seo\AiSitemapLocator;
use Semitexa\Ssr\Seo\Sitemap\SitemapGenerationContext;
use Semitexa\Ssr\Seo\Sitemap\SitemapGenerator;

#[AsPayloadHandler(payload: SitemapXmlPayload::class, resource: ResourceResponse::class)]
final class SitemapXmlHandler implements TypedHandlerInterface
{
    #[InjectAsMutable]
    protected Request $request;

    #[InjectAsMutable]
    protected TenantContextInterface $tenantContext;

    #[InjectAsReadonly]
    protected ?SitemapGenerator $generator = null;

    public function handle(SitemapXmlPayload $payload, ResourceResponse $resource): ResourceResponse
    {
        return $resource
            ->setContent($this->resolveContent())
            ->setHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    private function resolveContent(): string
    {
        $projectRoot = ProjectRoot::get();

        // Check for pre-generated or manual override files
        foreach ([
            $projectRoot . '/var/sitemap/sitemap.xml',
            $projectRoot . '/sitemap.xml',
            $projectRoot . '/public/sitemap.xml',
        ] as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $content = file_get_contents($candidate);
            if ($content !== false) {
                return $content;
            }
        }

        // Fall back to dynamic generation
        return $this->generateDynamic();
    }

    private function generateDynamic(): string
    {
        if ($this->generator === null) {
            return $this->renderEmptySitemap();
        }

        $baseUrl = AiSitemapLocator::originUrl($this->request, $this->tenantContext);
        $context = new SitemapGenerationContext(
            baseUrl: $baseUrl,
            tenantContext: $this->tenantContext,
        );

        $result = $this->generator->generate($context);

        return $result['xml'];
    }

    private function renderEmptySitemap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>' . "\n";
    }
}
