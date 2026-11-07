<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Запрос закрытия одиночного удара.
 */
final class GameStrikeSingleInput
{
    /**
     * Создаёт запрос закрытия.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $requestChoice Защита запроса.
     *
     * @return void
     */
    public function __construct(
        public readonly GameRecord $game,
        public readonly int $sessionId,
        public readonly int $battleId,
        public readonly int $expectedBattleVersion,
        public int $expectedSheetVersion,
        public readonly array $requestChoice,
    ) {
    }
}
