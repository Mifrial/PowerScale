<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход конца хода, продолжения и отмены.
 */
final class GameTurnInput implements IActionInput
{
    /**
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа текущего.
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $idempotencyKey,
        public readonly int $battleId,
        public readonly int $expectedBattleVersion,
        public readonly int $expectedSheetVersion,
    ) {
    }

    /**
     * @return array<string, mixed> Тело replay.
     */
    public function body(): array
    {
        return [
            'idempotencyKey' => $this->idempotencyKey,
            'battleId' => $this->battleId,
            'expectedBattleVersion' => $this->expectedBattleVersion,
            'expectedSheetVersion' => $this->expectedSheetVersion,
        ];
    }
}
