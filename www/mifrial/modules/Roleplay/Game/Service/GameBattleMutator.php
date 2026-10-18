<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;

/**
 * Запись боя внутри уже открытой транзакции.
 */
final class GameBattleMutator
{
    /**
     * Создаёт запись.
     *
     * @param GameBattleRepository $battles Бои.
     * @param GameCommandRepository $commands Команды.
     * @param GameNpcRepository $npcs NPC.
     * @param IGameSessionRoster $sessionRoster Снимок сессии.
     * @param GameProcessRepository $processes Process боя.
     * @param GameBattleOrder $order Порядок хода.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameCommandRepository $commands,
        private readonly GameNpcRepository $npcs,
        private readonly IGameSessionRoster $sessionRoster,
        private readonly GameProcessRepository $processes,
        private readonly GameBattleOrder $order,
    ) {
    }

    /**
     * Вставляет бой и состав.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<mixed>|null $rolls Способы инициативы или null.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если участника нет.
     * @throws GameInvalidException Если поле или проверка.
     * @throws GameBattleConflictException Если ключ занят.
     */
    public function start(
        int $gameId,
        int $sessionId,
        string $idempotencyKey,
        array $participants,
        int $spaceId,
        int $rulesRevision,
        ?array $rolls = null,
    ): array {
        $this->assertMembers($gameId, $participants);
        $battle = $this->battles->add($sessionId);
        $this->battles->replaceParticipants($battle->getId(), $participants);
        $version = 1;
        $order = null;
        if ($rolls !== null) {
            $order = $this->order->rollAll($spaceId, $rulesRevision, $participants, $rolls);
            $saved = $this->battles->advanceVersion($battle->getId(), 1);
            $this->battles->replaceTurnOrder($battle->getId(), $order, 0, 1);
            $version = $saved->getStateVersion();
        }

        $result = $this->opened($battle->getId(), $version);
        if ($order !== null) {
            $result['order'] = $order;
        }

        $body = ['participants' => $participants];
        if ($rolls !== null) {
            $body['rolls'] = $rolls;
        }

        $this->commands->add($gameId, $sessionId, 'battle', $idempotencyKey, $body, $result);

        return $result;
    }

    /**
     * Меняет состав и версию.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param int $expectedVersion Ожидаемая версия.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если боя или участника нет.
     * @throws GameInvalidException Если поле или проверка новых.
     * @throws GameBattleConflictException Если версия или ключ.
     */
    public function setRoster(
        int $gameId,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        array $participants,
        int $expectedVersion,
        int $spaceId,
        int $rulesRevision,
    ): array {
        $current = $this->requireBattle($sessionId, $battleId);
        $this->assertMembers($gameId, $participants);
        $turnOrder = $this->nextOrder($current, $participants, $spaceId, $rulesRevision, $expectedVersion);
        $battle = $this->battles->advanceVersion($battleId, $expectedVersion);
        $this->battles->replaceParticipants($battleId, $participants);
        if ($turnOrder !== null) {
            $cursor = $this->keptCursor($current, $turnOrder);
            $this->battles->replaceTurnOrder($battleId, $turnOrder, $cursor['index'], $cursor['round']);
            $this->battles->replaceActionDebt($battleId, $this->debtOfRoster($current, $turnOrder));
        }

        $result = $this->opened($battle->getId(), $battle->getStateVersion());
        if ($turnOrder !== null) {
            $result['order'] = $turnOrder;
        }

        $this->commands->add($gameId, $sessionId, 'battle', $idempotencyKey, [
            'battleId' => $battleId,
            'participants' => $participants,
            'expectedVersion' => $expectedVersion,
        ], $result);

        return $result;
    }

    /**
     * Снимает один бой.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия или ключ.
     * @throws GameInvalidException Если поле.
     */
    public function end(
        int $gameId,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $battle = $this->requireBattle($sessionId, $battleId);
        if ($battle->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        $this->processes->cancelOpenForBattle($battleId);
        $this->battles->delete($battleId);
        $result = [
            'battleId' => $battleId,
            'ended' => true,
        ];
        $this->commands->add($gameId, $sessionId, 'battle', $idempotencyKey, [
            'battleId' => $battleId,
            'expectedVersion' => $expectedVersion,
        ], $result);

        return $result;
    }

    /**
     * Бой этой сессии.
     *
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если боя нет в этой сессии.
     * @throws GameInvalidException Если строка битая.
     */
    private function requireBattle(int $sessionId, int $battleId): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        return $battle;
    }

    /**
     * Новый порядок или null, пока колонка пуста. Версия сверяется до броска.
     *
     * @param GameBattleRecord $current Бой.
     * @param list<array{type: string, id: int}> $participants Новый состав.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return list<array<string, mixed>>|null Порядок.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если проверка новых чужая.
     * @throws GameNotFoundException Если листа нет.
     */
    private function nextOrder(
        GameBattleRecord $current,
        array $participants,
        int $spaceId,
        int $rulesRevision,
        int $expectedVersion,
    ): ?array {
        if ($current->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($current->getStateVersion());
        }

        $turnOrder = $current->getTurnOrder();
        if ($turnOrder === null) {
            return null;
        }

        return $this->order->appendJoined($spaceId, $rulesRevision, $turnOrder, $participants);
    }

    /**
     * Персонаж в снимке, NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param list<array{type: string, id: int}> $participants Состав.
     *
     * @return void
     *
     * @throws GameNotFoundException Если участника нет.
     */
    private function assertMembers(int $gameId, array $participants): void
    {
        foreach ($participants as $participant) {
            $this->assertMember($gameId, $participant['type'], $participant['id']);
        }
    }

    /**
     * Один участник.
     *
     * @param int $gameId Игра.
     * @param string $type character или npc.
     * @param int $id Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если участника нет.
     */
    private function assertMember(int $gameId, string $type, int $id): void
    {
        if ($type === 'character' && !$this->sessionRoster->isParticipant($gameId, $id)) {
            throw new GameNotFoundException('Game session character was not found');
        }

        if ($type === 'npc' && $this->npcs->getById($id)->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game NPC was not found');
        }
    }

    /**
     * Прежняя пара в новом порядке, иначе индекс 0. Раунд не сдвигает.
     *
     * @param GameBattleRecord $current Бой до записи.
     * @param list<array<string, mixed>> $turnOrder Новый порядок.
     *
     * @return array{index: int, round: int} Курсор.
     */
    private function keptCursor(GameBattleRecord $current, array $turnOrder): array
    {
        $order = $current->getTurnOrder() ?? [];
        $previous = $order[$current->getActiveIndex()] ?? null;
        $index = 0;
        if (is_array($previous)) {
            foreach ($turnOrder as $offset => $entry) {
                if (($entry['type'] ?? null) === ($previous['type'] ?? null) && ($entry['id'] ?? null) === ($previous['id'] ?? null)) {
                    $index = $offset;
                    break;
                }
            }
        }

        $round = $current->getRound() > 0 ? $current->getRound() : 1;

        return ['index' => $index, 'round' => $round];
    }

    /**
     * Снимает слоты акторов, которых нет в новом порядке.
     *
     * @param GameBattleRecord $current Бой.
     * @param list<array<string, mixed>> $turnOrder Новый порядок.
     *
     * @return array<string, mixed> Карта.
     */
    private function debtOfRoster(GameBattleRecord $current, array $turnOrder): array
    {
        $kept = [];
        foreach ($turnOrder as $entry) {
            if (!is_array($entry) || !is_string($entry['type'] ?? null) || !is_int($entry['id'] ?? null)) {
                continue;
            }

            $key = $entry['type'] . ':' . $entry['id'];
            if (array_key_exists($key, $current->getActionDebt())) {
                $kept[$key] = $current->getActionDebt()[$key];
            }
        }

        return $kept;
    }

    /**
     * Итог открытого боя.
     *
     * @param int $battleId Бой.
     * @param int $version Версия.
     *
     * @return array<string, mixed> JSON.
     */
    private function opened(int $battleId, int $version): array
    {
        return [
            'battleId' => $battleId,
            'version' => $version,
            'ended' => false,
        ];
    }
}
