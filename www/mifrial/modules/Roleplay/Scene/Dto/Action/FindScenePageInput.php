<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `scene.findPage`.
 */
final class FindScenePageInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $q Подстрока имени.
     * @param OptionalBool $isArchived Архив.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $q,
        public readonly OptionalBool $isArchived,
    ) {
    }
}
