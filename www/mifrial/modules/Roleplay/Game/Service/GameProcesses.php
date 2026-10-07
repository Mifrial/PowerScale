<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameProcessRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Открытие и resolve process. Эффект листа не считает.
 */
final class GameProcesses implements IGameProcesses
{
    /**
     * Создаёт сценарий.
     *
     * @param IGames $games Игра.
     * @param GameSessionRepository $sessions Сессия.
     * @param IGameSessionRoster $sessionRoster Снимок.
     * @param GameNpcRepository $npcs NPC.
     * @param GameBattleRepository $battles Бой.
     * @param GameProcessRepository $processes Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameSessionRepository $sessions,
        private readonly IGameSessionRoster $sessionRoster,
        private readonly GameNpcRepository $npcs,
        private readonly GameBattleRepository $battles,
        private readonly GameProcessRepository $processes,
    ) {
    }

    /**
     * Пишет open.
     *
     * @param int $gameId Игра.
     * @param int|null $battleId Бой или null.
     * @param string $participantType Тип character или npc.
     * @param int $participantId Участник.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если сессии нет, игра completed или участник пустой.
     * @throws GameNotFoundException Если игры, снимка, NPC или боя нет.
     */
    public function open(int $gameId, ?int $battleId, string $participantType, int $participantId): array
    {
        $this->assertParticipant($participantType, $participantId);
        $sessionId = $this->requireSession($gameId);
        if ($battleId === null) {
            $this->assertSessionMember($gameId, $participantType, $participantId);
        } else {
            $this->assertBattleMember($sessionId, $battleId, $participantType, $participantId);
        }

        return $this->view($this->processes->add($sessionId, $battleId, $participantType, $participantId));
    }

    /**
     * Переводит open в resolved.
     *
     * @param int $processId Process.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если статус не open.
     */
    public function resolve(int $processId): array
    {
        $row = $this->processes->getById($processId);
        if ($row->getStatus() !== 'open') {
            throw new GameInvalidException('Game process is not open');
        }

        $this->processes->markResolved($processId);

        return $this->view($this->processes->getById($processId));
    }

    /**
     * Переводит одну строку open в cancelled.
     *
     * @param int $processId Process.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если статус не open.
     */
    public function cancel(int $processId): array
    {
        $row = $this->processes->getById($processId);
        if ($row->getStatus() !== 'open') {
            throw new GameInvalidException('Game process is not open');
        }

        $this->processes->markCancelled($processId);

        return $this->view($this->processes->getById($processId));
    }

    /**
     * Гасит open персонажа текущей сессии.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForCharacter(int $gameId, int $characterId): void
    {
        $this->processes->cancelOpenForCharacter($this->sessions->findSessionId($gameId), $characterId);
    }

    /**
     * Гасит open одного боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForBattle(int $battleId): void
    {
        $this->processes->cancelOpenForBattle($battleId);
    }

    /**
     * Гасит оставшиеся open сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForSession(int $sessionId): void
    {
        $this->processes->cancelOpenForSession($sessionId);
    }

    /**
     * Тип и id участника.
     *
     * @param string $participantType Тип.
     * @param int $participantId Id.
     *
     * @return void
     *
     * @throws GameInvalidException Если тип чужой или id меньше 1.
     */
    private function assertParticipant(string $participantType, int $participantId): void
    {
        if (($participantType !== 'character' && $participantType !== 'npc') || $participantId < 1) {
            throw new GameInvalidException('Game process participant is invalid');
        }
    }

    /**
     * Живая сессия незакрытой игры.
     *
     * @param int $gameId Игра.
     *
     * @return int Id сессии.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если completed или сессии нет.
     */
    private function requireSession(int $gameId): int
    {
        if ($this->games->get($gameId)->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        return $sessionId;
    }

    /**
     * Персонаж снимка или NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param string $participantType Тип.
     * @param int $participantId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если участника нет.
     */
    private function assertSessionMember(int $gameId, string $participantType, int $participantId): void
    {
        $inSession = $this->sessionRoster->isParticipant($gameId, $participantId);
        if ($participantType === 'character' && !$inSession) {
            throw new GameNotFoundException('Game character was not found');
        }

        if ($participantType === 'npc' && $this->npcs->getById($participantId)->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game NPC was not found');
        }
    }

    /**
     * Участник открытого боя этой сессии.
     *
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $participantType Тип.
     * @param int $participantId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если боя или участника нет.
     * @throws GameInvalidException Если состав битый.
     */
    private function assertBattleMember(
        int $sessionId,
        int $battleId,
        string $participantType,
        int $participantId,
    ): void {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        foreach ($this->battles->findParticipants($battleId) as $participant) {
            if ($participant['type'] === $participantType && $participant['id'] === $participantId) {
                return;
            }
        }

        throw new GameNotFoundException('Game battle participant was not found');
    }

    /**
     * Вид строки.
     *
     * @param GameProcessRecord $row Строка.
     *
     * @return array<string, mixed> Поля.
     */
    private function view(GameProcessRecord $row): array
    {
        return [
            'processId' => $row->getId(),
            'sessionId' => $row->getSessionId(),
            'battleId' => $row->getBattleId(),
            'participantType' => $row->getParticipantType(),
            'participantId' => $row->getParticipantId(),
            'status' => $row->getStatus(),
        ];
    }
}
