<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto\Action;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `map.save`.
 */
final class SaveMapInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param OptionalInt $id Карта или отсутствие для новой.
     * @param OptionalInt $expectedVersion Версия, которую загрузил клиент.
     * @param OptionalBool $force Перезаписать без сверки.
     * @param array<string|int, mixed> $map Полный снимок.
     *
     * @return void
     */
    public function __construct(
        public readonly OptionalInt $id,
        public readonly OptionalInt $expectedVersion,
        public readonly OptionalBool $force,
        public readonly array $map,
    ) {
    }
}
