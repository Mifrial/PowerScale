<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `map.findPage`.
 */
final class FindMapPageInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $q Подстрока имени.
     * @param OptionalInt $ownerId Владелец.
     * @param OptionalBool $isArchived Архив.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $q,
        public readonly OptionalInt $ownerId,
        public readonly OptionalBool $isArchived,
    ) {
    }
}
