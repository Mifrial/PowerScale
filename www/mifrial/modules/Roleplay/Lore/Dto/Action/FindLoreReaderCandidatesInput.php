<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `lore.findReaderCandidates`.
 */
final class FindLoreReaderCandidatesInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $worldCode Код мира.
     * @param OptionalString $q Подстрока логина или имени.
     * @param int $limit Размер 1..50.
     *
     * @return void
     */
    public function __construct(
        public readonly string $worldCode,
        public readonly OptionalString $q,
        public readonly int $limit = 20,
    ) {
    }
}
