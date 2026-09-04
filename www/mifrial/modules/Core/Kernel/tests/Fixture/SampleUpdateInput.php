<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Минимальный input для тестов binder.
 */
final class SampleUpdateInput implements IActionInput
{
    /**
     * Создаёт фикстуру.
     *
     * @param int $id Id.
     * @param OptionalString $name Имя.
     * @param OptionalBool $active Активность.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly OptionalString $name,
        public readonly OptionalBool $active,
    ) {
    }
}
