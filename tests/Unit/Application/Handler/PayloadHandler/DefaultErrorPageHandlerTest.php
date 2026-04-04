<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Tests\Unit\Application\Handler\PayloadHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Error\ErrorPageContext;
use Semitexa\Core\Error\ErrorPageContextStore;
use Semitexa\Core\Request;
use Semitexa\Ssr\Application\Handler\PayloadHandler\DefaultInternalErrorPageHandler;
use Semitexa\Ssr\Application\Handler\PayloadHandler\DefaultNotFoundPageHandler;
use Semitexa\Ssr\Application\Payload\Request\DefaultInternalErrorPagePayload;
use Semitexa\Ssr\Application\Payload\Request\DefaultNotFoundPagePayload;
use Semitexa\Ssr\Application\Resource\Response\DefaultErrorPageResource;

final class DefaultErrorPageHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        ErrorPageContextStore::pop();
    }

    #[Test]
    public function not_found_handler_builds_default_ssr_context(): void
    {
        $handler = new DefaultNotFoundPageHandler();
        $this->injectRequest($handler, $this->makeRequest('/missing'));

        $resource = $handler->handle(new DefaultNotFoundPagePayload(), new DefaultErrorPageResource());

        self::assertSame(404, $resource->getStatusCode());
        self::assertSame('The page you requested does not exist.', $resource->getContext()['headline']);
        self::assertSame('/missing', $resource->getContext()['requestDetails']['Path']);
    }

    #[Test]
    public function internal_error_handler_uses_current_error_page_context(): void
    {
        ErrorPageContextStore::push(new ErrorPageContext(
            statusCode: 500,
            reasonPhrase: 'Internal Server Error',
            publicMessage: 'An unexpected error occurred.',
            requestPath: '/broken',
            requestMethod: 'GET',
            requestId: 'req-123',
            debugEnabled: true,
            exceptionClass: 'RuntimeException',
            debugMessage: 'Boom',
            trace: "trace-line-1\ntrace-line-2",
            originalRouteName: 'demo.page',
        ));

        $handler = new DefaultInternalErrorPageHandler();
        $this->injectRequest($handler, $this->makeRequest('/broken'));

        $resource = $handler->handle(new DefaultInternalErrorPagePayload(), new DefaultErrorPageResource());

        self::assertSame(500, $resource->getStatusCode());
        self::assertSame('The request reached Semitexa, but rendering failed before a page could complete.', $resource->getContext()['headline']);
        self::assertSame('Boom', $resource->getContext()['debugDetails']['Message']);
        self::assertSame('demo.page', $resource->getContext()['debugDetails']['Origin route']);
    }

    private function injectRequest(object $handler, Request $request): void
    {
        $ref = new \ReflectionObject($handler);
        $prop = $ref->getProperty('request');
        $prop->setAccessible(true);
        $prop->setValue($handler, $request);
    }

    private function makeRequest(string $uri): Request
    {
        return new Request(
            method: 'GET',
            uri: $uri,
            headers: ['Accept' => 'text/html', 'X-Request-ID' => 'req-123'],
            query: [],
            post: [],
            server: [],
            cookies: [],
        );
    }
}
