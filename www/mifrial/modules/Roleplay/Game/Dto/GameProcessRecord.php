<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка process. Листа в ней нет.
 */
final class GameProcessRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $sessionId Сессия.
     * @param int|null $battleId Бой или null.
     * @param string $participantType Тип character или npc.
     * @param int $participantId Участник.
     * @param string $status Статус open, resolved или cancelled.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $sessionId,
        private readonly ?int $battleId,
        private readonly string $participantType,
        private readonly int $participantId,
        private readonly string $status,
    ) {
    }

    /**
     * Запись из строки таблицы.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Строка.
     *
     * @throws GameInvalidException Если поле битое.
     */
    public static function fromNormalized(array $fields): self
    {
        $id = $fields['id'] ?? null;
        $sessionId = $fields['session_id'] ?? null;
        $battleId = $fields['battle_id'] ?? null;
        $participantType = $fields['participant_type'] ?? null;
        $participantId = $fields['participant_id'] ?? null;
        $status = $fields['status'] ?? null;
        if (!is_int($id) || !is_int($sessionId) || !is_int($participantId)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        if (!is_string($participantType) || !is_string($status)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        if ($battleId !== null && !is_int($battleId)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return new self($id, $sessionId, $battleId, $participantType, $participantId, $status);
    }

    /**
     * Id строки.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Сессия.
     *
     * @return int Id сессии.
     */
    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    /**
     * Бой или пусто.
     *
     * @return int|null Id боя.
     */
    public function getBattleId(): ?int
    {
        return $this->battleId;
    }

    /**
     * Тип участника.
     *
     * @return string character или npc.
     */
    public function getParticipantType(): string
    {
        return $this->participantType;
    }

    /**
     * Id участника.
     *
     * @return int Id.
     */
    public function getParticipantId(): int
    {
        return $this->participantId;
    }

    /**
     * Статус.
     *
     * @return string open, resolved или cancelled.
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
