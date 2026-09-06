<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Tests\Unit\Async;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Ssr\Application\Service\Async\LivePubSubChannelController;
use Semitexa\Ssr\Application\Service\Async\ResourceInvalidationSubscriber;

/**
 * The two-live-feeds-on-one-worker seam: when a SECOND distinct scope appears
 * while the single subscribe loop is already blocked on its socket read, the
 * controller has to interrupt that loop so it resubscribes with the full
 * desired set. A pings stream joining a leads stream is the real case.
 *
 * Until now this was covered only through its primitives — isSubscribedTo() and
 * interrupt() each tested alone — and never through subscribe(), which is where
 * the DECISION lives. That decision has two ways to be wrong and neither is
 * visible from the primitives:
 *
 *   - not interrupting on a new scope leaves the second feed permanently deaf,
 *     the exact limitation Phase 4 retired;
 *   - interrupting on a scope the loop ALREADY covers tears down and rebuilds
 *     the Redis connection on every connect, which is an interrupt storm that
 *     still looks like a working system.
 *
 * White-box for the same reason the keep-alive test beside this one is: the
 * collaborator is a final class wired for Redis, so both sides are built
 * without their constructors and their state is placed directly.
 */
final class LivePubSubResubscribeTest extends TestCase
{
    /**
     * @param list<string> $activeChannels what the running loop currently covers
     */
    private static function controller(
        bool $loopRunning,
        array $activeChannels,
        bool $withConnection = true,
    ): array {
        $subscriber = (new \ReflectionClass(ResourceInvalidationSubscriber::class))->newInstanceWithoutConstructor();

        $channels = new \ReflectionProperty(ResourceInvalidationSubscriber::class, 'activeChannels');
        $channels->setValue($subscriber, $activeChannels);

        if ($withConnection) {
            // Never connected: Predis is lazy, and interrupt() only needs a
            // client to close. A null connection would make interrupt() return
            // early and hide the very transition under test.
            $connection = new \ReflectionProperty(ResourceInvalidationSubscriber::class, 'activeConnection');
            $connection->setValue($subscriber, new \Predis\Client(['host' => '127.0.0.1', 'port' => 1]));
        }

        $controller = (new \ReflectionClass(LivePubSubChannelController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(LivePubSubChannelController::class, 'subscriber'))->setValue($controller, $subscriber);
        (new \ReflectionProperty(LivePubSubChannelController::class, 'loopRunning'))->setValue($controller, $loopRunning);

        return [$controller, $subscriber];
    }

    private static function wasInterrupted(ResourceInvalidationSubscriber $subscriber): bool
    {
        $p = new \ReflectionProperty(ResourceInvalidationSubscriber::class, 'interrupted');

        return (bool) $p->getValue($subscriber);
    }

    /**
     * The transition the seam exists for.
     */
    #[Test]
    public function a_scope_the_running_loop_does_not_cover_interrupts_it(): void
    {
        [$controller, $subscriber] = self::controller(
            loopRunning: true,
            activeChannels: ['ui.invalidate.acme.leads'],
        );

        $controller->subscribe(['ui.invalidate.acme.pings']);

        self::assertTrue(
            self::wasInterrupted($subscriber),
            'a second live feed on this worker would never receive invalidations',
        );
    }

    /**
     * And the way it is most likely to be got wrong in the other direction:
     * every connect re-announces its channels, so interrupting on an
     * already-covered scope rebuilds the Redis connection on each one.
     */
    #[Test]
    public function a_scope_the_loop_already_covers_does_not_interrupt_it(): void
    {
        [$controller, $subscriber] = self::controller(
            loopRunning: true,
            activeChannels: ['ui.invalidate.acme.leads', 'ui.invalidate.acme.pings'],
        );

        $controller->subscribe(['ui.invalidate.acme.pings']);

        self::assertFalse(
            self::wasInterrupted($subscriber),
            'the loop already covers this channel — interrupting it is a connection rebuild for nothing',
        );
    }

    /**
     * Partial coverage is uncovered: one new channel among known ones still
     * needs the resubscribe, or that one channel is deaf.
     */
    #[Test]
    public function a_partially_covered_set_still_interrupts(): void
    {
        [$controller, $subscriber] = self::controller(
            loopRunning: true,
            activeChannels: ['ui.invalidate.acme.leads'],
        );

        $controller->subscribe(['ui.invalidate.acme.leads', 'ui.invalidate.acme.pings']);

        self::assertTrue(self::wasInterrupted($subscriber));
    }

    /**
     * Before the loop runs there is nothing to interrupt — the first subscribe
     * launches it instead, and an interrupt here would be acting on a
     * connection that does not exist.
     */
    #[Test]
    public function the_first_subscribe_launches_rather_than_interrupts(): void
    {
        [$controller, $subscriber] = self::controller(
            loopRunning: false,
            activeChannels: [],
        );

        $controller->subscribe(['ui.invalidate.acme.leads']);

        self::assertFalse(self::wasInterrupted($subscriber));
    }

    #[Test]
    public function an_empty_channel_list_does_nothing_at_all(): void
    {
        [$controller, $subscriber] = self::controller(
            loopRunning: true,
            activeChannels: ['ui.invalidate.acme.leads'],
        );

        $controller->subscribe([]);

        self::assertFalse(self::wasInterrupted($subscriber));
    }

    /**
     * Unsubscribe-on-last is a documented no-op on the single loop. If it ever
     * starts interrupting, the last feed leaving would tear down the connection
     * the remaining feeds are still using.
     */
    #[Test]
    public function unsubscribe_never_interrupts_the_loop(): void
    {
        [$controller, $subscriber] = self::controller(
            loopRunning: true,
            activeChannels: ['ui.invalidate.acme.leads'],
        );

        $controller->unsubscribe(['ui.invalidate.acme.leads']);
        $controller->unsubscribe(['ui.invalidate.acme.pings']);

        self::assertFalse(self::wasInterrupted($subscriber));
    }
}
