<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Repository\GameBattleCommandRepository;
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
     * @param GameBattleCommandRepository $commands Команды.
     * @param GameNpcRepository $npcs NPC.
     * @param IGameSessionRoster $sessionRoster Снимок сессии.
     * @param GameProcessRepository $processes Process боя.
     * @param GameBattleOrder $order Порядок хода.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameBattleCommandRepository $commands,
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
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если участника нет.
     * @throws GameInvalidException Если поле.
     * @throws GameBattleConflictException Если ключ занят.
     */
    public function start(int $gameId, int $sessionId, string $idempotencyKey, array $participants): array
    {
        $this->assertMembers($gameId, $participants);
        $battle = $this->battles->add($sessionId);
        $this->battles->replaceParticipants($battle->getId(), $participants);
        $result = $this->opened($battle->getId(), 1);
        $this->commands->add($gameId, $sessionId, $idempotencyKey, [
            'participants' => $participants,
        ], $result);

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
            $this->battles->replaceTurnOrder($battleId, $turnOrder);
        }

        $result = $this->opened($battle->getId(), $battle->getStateVersion());
        if ($turnOrder !== null) {
            $result['order'] = $turnOrder;
        }

        $this->commands->add($gameId, $sessionId, $idempotencyKey, [
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
        $this->commands->add($gameId, $sessionId, $idempotencyKey, [
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
