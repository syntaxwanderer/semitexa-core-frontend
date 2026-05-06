<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Contract;

use Semitexa\Ssr\Domain\Contract\TypedSlotHandlerInterface as DomainTypedSlotHandlerInterface;

/**
 * @deprecated Use Semitexa\Ssr\Domain\Contract\TypedSlotHandlerInterface instead.
 */
if (!interface_exists(TypedSlotHandlerInterface::class, false)) {
    class_alias(DomainTypedSlotHandlerInterface::class, TypedSlotHandlerInterface::class);
}
