<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;

/**
 * Закрытие широкого удара.
 */
final class GameStrikeWideClose
{
    /**
     * Создаёт закрытие.
     *
     * @param GameStrikeWideRequest $request Приём ответов.
     * @param GameStrikeWideOpening $opening Срез и бросок атаки.
     * @param GameStrikeWidePrepare $prepare Подготовка строк.
     * @param GameStrikeWideHits $hits Урон и записи целей.
     * @param GameStrikeWidePending $pending Ожидание атакующего.
     * @param GameStrikeWideFinalize $finalize Чат и сохранение.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeWideRequest $request,
        private readonly GameStrikeWideOpening $opening,
        private readonly GameStrikeWidePrepare $prepare,
        private readonly GameStrikeWideHits $hits,
        private readonly GameStrikeWidePending $pending,
        private readonly GameStrikeWideFinalize $finalize,
    ) {
    }

    /**
     * Принимает ответы целей, считает удар и закрывает его.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $requestChoices Ответы целей.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     * @param array<int, array<string, mixed>> $resume Решения окна по строкам.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если удар или цель битые.
     */
    public function close(
        GameRecord $game, GameBattleRecord $battle, int $expectedBattleVersion, array $requestChoices,
        int $actorUserId, bool $editAll, bool $viewAll, array $resume = [],
    ): array {
        $run = new GameStrikeWideRun($game, $battle, $expectedBattleVersion, new GameStrikeWriteScope($actorUserId, $viewAll), $editAll, $resume);
        $state = $this->request->accept($run, $requestChoices);
        if ($state['early'] !== null) {
            return $state['early'];
        }

        $opening = $this->opening->open($run, $state['open'], $state['rows'], $state['targetKeys']);
        $prepared = $this->prepare->prepare($run, $state['open'], $opening['rows'], $opening);
        if ($prepared['early'] !== null) {
            return $prepared['early'];
        }

        $hits = $this->hits->hits($run, $state['open'], $prepared, $opening);
        $pending = $this->pending->pending($run, $state['open'], $prepared, $hits['results'], $opening, $state['targetKeys']);

        return $this->finalize->finish($run, $state['open'], $prepared, $opening, $hits['results'], $pending);
    }
}
