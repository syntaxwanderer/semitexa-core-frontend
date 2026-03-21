<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Contract;

interface TypedSlotHandlerInterface
{
    public function handle(object $slot): object;
}
