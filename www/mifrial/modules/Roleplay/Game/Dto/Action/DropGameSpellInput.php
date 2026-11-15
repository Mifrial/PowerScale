<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.dropSpell.
 */
final class DropGameSpellInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа кастера.
     * @param string $spellId Строковый id активного заклинания.
     * @param int $expectedSpellVersion Версия строки заклинания.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly int $expectedBattleVersion,
        public readonly int $expectedSheetVersion,
        public readonly string $spellId,
        public readonly int $expectedSpellVersion,
    ) {
    }
}
