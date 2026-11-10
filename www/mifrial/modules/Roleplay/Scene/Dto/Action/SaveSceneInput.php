<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `scene.save`.
 */
final class SaveSceneInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param OptionalInt $id Локация или отсутствие для новой.
     * @param OptionalInt $expectedVersion Версия, которую загрузил клиент.
     * @param array<string|int, mixed> $scene Полный снимок.
     *
     * @return void
     */
    public function __construct(
        public readonly OptionalInt $id,
        public readonly OptionalInt $expectedVersion,
        public readonly array $scene,
    ) {
    }
}
