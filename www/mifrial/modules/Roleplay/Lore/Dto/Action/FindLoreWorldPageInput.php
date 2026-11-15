<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `lore.findWorldPage`.
 */
final class FindLoreWorldPageInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $q Подстрока.
     * @param OptionalString $state Active, archived или all.
     * @param OptionalString $visibility Public или private.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $q,
        public readonly OptionalString $state,
        public readonly OptionalString $visibility,
    ) {
    }
}
