<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleInput;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;

/**
 * Закрытие одиночного удара.
 */
final class GameStrikeSingleClose
{
    /**
     * Создаёт закрытие.
     *
     * @param GameStrikeSinglePrepare $prepare Подготовка.
     * @param GameStrikeSingleResolve $resolve Расчёт.
     * @param GameStrikeSingleWrite $write Запись и чат.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSinglePrepare $prepare,
        private readonly GameStrikeSingleResolve $resolve,
        private readonly GameStrikeSingleWrite $write,
    ) {
    }

    /**
     * Тело закрытия одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $requestChoice Защита запроса.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     * @param array<string, mixed>|null $resume Решение окна фактической цели.
     *
     * @return array<string, mixed> Итог.
     */
    public function close(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $requestChoice,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        ?array $resume = null,
    ): array {
        $run = new GameStrikeSingleRun(
            new GameStrikeSingleInput(
                $game,
                $sessionId,
                $battleId,
                $expectedBattleVersion,
                $expectedSheetVersion,
                $requestChoice,
            ),
            new GameStrikeWriteScope($actorUserId, $viewAll),
            $editAll,
        );
        $early = $this->prepare->prepare($run, $resume) ?? $this->resolve->resolve($run);
        if ($early !== null) {
            return $early;
        }
        return $this->write->finish($run);
    }
}
