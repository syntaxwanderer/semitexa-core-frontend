<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Async;

use Semitexa\Core\Attribute\AsEventListener;
use Semitexa\Core\Attribute\InjectAsMutable;
use Semitexa\Core\Event\HandlerCompleted;
use Semitexa\Core\Event\EventExecution;
use Semitexa\Core\Session\SessionInterface;

#[AsEventListener(event: HandlerCompleted::class, execution: EventExecution::Async)]
final class HandlerCompletedListener
{
    #[InjectAsMutable]
    protected SessionInterface $session;

    public function handle(HandlerCompleted $event): void
    {
        $sessionId = $this->session->get('semitexa_sse_session');

        if (!is_string($sessionId) || trim($sessionId) === '') {
            return;
        }

        AsyncResourceSseServer::broadcast(
            $sessionId,
            $event->handlerClass,
            $event->resource
        );
    }
}
