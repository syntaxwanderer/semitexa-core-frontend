<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Server\Lifecycle;

use Semitexa\Core\Attributes\AsServerLifecycleListener;
use Semitexa\Core\Server\Lifecycle\ServerLifecycleContext;
use Semitexa\Core\Server\Lifecycle\ServerLifecyclePhase;
use Semitexa\Core\Server\Lifecycle\ServerLifecycleListenerInterface;
use Semitexa\Ssr\Isomorphic\DeferredRequestRegistry;

#[AsServerLifecycleListener(
    phase: ServerLifecyclePhase::WorkerStartAfterServerBindings->value,
    priority: 10,
    requiresContainer: false,
)]
final class InitializeDeferredRequestRegistryListener implements ServerLifecycleListenerInterface
{
    public function handle(ServerLifecycleContext $context): void
    {
        $deferredRequestTable = $context->bootstrapState?->get(SsrBootstrapStateKey::DEFERRED_REQUEST_TABLE);
        if ($deferredRequestTable !== null && method_exists(DeferredRequestRegistry::class, 'setTable')) {
            DeferredRequestRegistry::setTable($deferredRequestTable);
        }

        DeferredRequestRegistry::initialize();
    }
}
