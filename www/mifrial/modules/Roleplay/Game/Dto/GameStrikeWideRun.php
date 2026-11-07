<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Входные данные одного прогона закрытия широкого удара.
 */
final class GameStrikeWideRun
{
    /**
     * Создаёт контекст прогона.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param GameStrikeWriteScope $scope Права записи и просмотра.
     * @param bool $editAll Ключ.
     * @param array<int, array<string, mixed>> $resume Решения окна по строкам.
     *
     * @return void
     */
    public function __construct(
        public readonly GameRecord $game,
        public readonly GameBattleRecord $battle,
        public readonly int $expectedBattleVersion,
        public readonly GameStrikeWriteScope $scope,
        public readonly bool $editAll,
        public readonly array $resume = [],
    ) {
    }
}
