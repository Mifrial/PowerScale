<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;

/**
 * Прерывание открытого процесса текущего участника.
 */
final class GameTurnProcessAbort
{
    private readonly GameBattleLookup $lookup;

    /**
     * Создаёт сценарий.
     *
     * @param GameTurnAccess $access Права и сессия.
     * @param GameBattleRepository $battles Строки боя.
     * @param GameActionRepository $strikes Открытые удары.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameProcessRepository $processRows Строки процессов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnAccess $access,
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $strikes,
        private readonly GameProcessFlow $processes,
        private readonly GameProcessRepository $processRows,
    ) {
        $this->lookup = new GameBattleLookup($battles);
    }

    /**
     * Гасит сессию процесса текущего.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если удар, долг или процесс не позволяют.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия другая.
     */
    public function abort(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        [$battleId, $expectedBattle] = $this->fields($body);
        $battle = $this->lookup->find($battleId, $sessionId);
        $current = $this->current($battle);
        $this->access->assertActor($game, $actorUserId, $editAll, $current);
        $this->assertReady($battleId, $battle, $current);
        $aborted = $this->processes->abort(
            $game,
            $battle,
            $current,
            $this->mode($body),
            $battle->getPendingActionEffects(),
        );

        return $this->save($battleId, $expectedBattle, $aborted);
    }

    /**
     * Пишет новое ожидание и собирает итог.
     *
     * @param int $battleId Бой.
     * @param int $expectedBattle Версия боя.
     * @param array<string, mixed> $aborted Итог прерывания.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    private function save(int $battleId, int $expectedBattle, array $aborted): array
    {
        $saved = $this->battles->replaceTracked(
            $battleId,
            $expectedBattle,
            $aborted['pending'],
            null,
            null,
            null,
        );

        return [
            'battleId' => $battleId,
            'version' => $saved->getStateVersion(),
            'processRuleCode' => $aborted['processRuleCode'],
            'stepCode' => $aborted['stepCode'],
            'pendingActionEffects' => $aborted['pending'],
            'aborted' => true,
        ];
    }

    /**
     * Обязательные поля тела.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array{0: int, 1: int} Бой и версия.
     *
     * @throws GameInvalidException Если поля битые.
     */
    private function fields(array $body): array
    {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedBattleVersion'] ?? null;
        if (!is_int($battleId) || !is_int($expectedBattle)) {
            throw new GameInvalidException('Game process field is invalid');
        }

        return [$battleId, $expectedBattle];
    }

    /**
     * Актор прерывания: курсор боя или единственная открытая сессия.
     *
     * @param GameBattleRecord $battle Бой.
     *
     * @return array{type: string, id: int} Актор.
     *
     * @throws GameInvalidException Если актора не определить.
     */
    private function current(GameBattleRecord $battle): array
    {
        $order = $battle->getTurnOrder() ?? [];
        $entry = $order[$battle->getActiveIndex()] ?? null;
        if (is_array($entry) && is_string($entry['type'] ?? null) && is_int($entry['id'] ?? null)) {
            return ['type' => $entry['type'], 'id' => $entry['id']];
        }

        $rows = $this->processRows->findOpenForBattle($battle->getId());
        if (count($rows) !== 1) {
            throw new GameInvalidException('Game process is missing');
        }

        return ['type' => $rows[0]->getParticipantType(), 'id' => $rows[0]->getParticipantId()];
    }

    /**
     * Ни удара, ни долга.
     *
     * @param int $battleId Бой.
     * @param GameBattleRecord $battle Строка боя.
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return void
     *
     * @throws GameInvalidException Если удар или долг открыты.
     */
    private function assertReady(int $battleId, GameBattleRecord $battle, array $current): void
    {
        if ($this->strikes->findOpenAny($battleId) !== null) {
            throw new GameInvalidException('Game strike is open');
        }

        $debt = $battle->getActionDebt();
        $slot = $debt[$current['type'] . ':' . $current['id']] ?? null;
        if (is_array($slot) && is_int($slot['remaining'] ?? null) && $slot['remaining'] > 0) {
            throw new GameInvalidException('Game action debt is open');
        }
    }

    /**
     * Режим прерывания.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return string normal или emergency.
     *
     * @throws GameInvalidException Если режим чужой.
     */
    private function mode(array $body): string
    {
        $mode = $body['mode'] ?? 'normal';
        if ($mode !== 'normal' && $mode !== 'emergency') {
            throw new GameInvalidException('Game process interruption is invalid');
        }

        return $mode;
    }
}
