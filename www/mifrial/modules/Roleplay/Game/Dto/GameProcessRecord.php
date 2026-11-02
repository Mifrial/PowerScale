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
     * @param string|null $processRuleCode Карточка процесса.
     * @param string|null $currentStepCode Текущий шаг.
     * @param string|null $stepStatus Статус pending или completed.
     * @param array<string, mixed> $counters Счётчики.
     * @param array<string, int> $weaponUseCounts Использования оружия.
     * @param string|null $lastTargetType Тип прошлой цели.
     * @param int|null $lastTargetId Id прошлой цели.
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
        private readonly ?string $processRuleCode = null,
        private readonly ?string $currentStepCode = null,
        private readonly ?string $stepStatus = null,
        private readonly array $counters = [],
        private readonly array $weaponUseCounts = [],
        private readonly ?string $lastTargetType = null,
        private readonly ?int $lastTargetId = null,
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
        $processRuleCode = $fields['process_rule_code'] ?? null;
        $currentStepCode = $fields['current_step_code'] ?? null;
        $stepStatus = $fields['step_status'] ?? null;
        $counters = $fields['counters'] ?? [];
        $weaponUseCounts = $fields['weapon_use_counts'] ?? [];
        $lastTargetType = $fields['last_target_type'] ?? null;
        $lastTargetId = $fields['last_target_id'] ?? null;
        if (!is_int($id) || !is_int($sessionId) || !is_int($participantId)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        if (!is_string($participantType) || !is_string($status)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        if ($battleId !== null && !is_int($battleId)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        if (
            ($processRuleCode !== null && !is_string($processRuleCode))
            || ($currentStepCode !== null && !is_string($currentStepCode))
            || ($stepStatus !== null && !is_string($stepStatus))
            || !is_array($counters)
            || !is_array($weaponUseCounts)
            || ($lastTargetType !== null && !is_string($lastTargetType))
            || ($lastTargetId !== null && !is_int($lastTargetId))
        ) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return new self(
            $id,
            $sessionId,
            $battleId,
            $participantType,
            $participantId,
            $status,
            $processRuleCode,
            $currentStepCode,
            $stepStatus,
            $counters,
            self::counts($weaponUseCounts),
            $lastTargetType,
            $lastTargetId,
        );
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

    /**
     * Карточка процесса.
     *
     * @return string|null Код или null у строки проверки.
     */
    public function getProcessRuleCode(): ?string
    {
        return $this->processRuleCode;
    }

    /**
     * Текущий шаг.
     *
     * @return string|null Код или null.
     */
    public function getCurrentStepCode(): ?string
    {
        return $this->currentStepCode;
    }

    /**
     * Статус шага.
     *
     * @return string|null pending или completed.
     */
    public function getStepStatus(): ?string
    {
        return $this->stepStatus;
    }

    /**
     * Счётчики.
     *
     * @return array<string, mixed> Значения.
     */
    public function getCounters(): array
    {
        return $this->counters;
    }

    /**
     * Использования оружия.
     *
     * @return array<string, int> Счётчики.
     */
    public function getWeaponUseCounts(): array
    {
        return $this->weaponUseCounts;
    }

    /**
     * Тип прошлой цели.
     *
     * @return string|null Тип или null.
     */
    public function getLastTargetType(): ?string
    {
        return $this->lastTargetType;
    }

    /**
     * Id прошлой цели.
     *
     * @return int|null Id или null.
     */
    public function getLastTargetId(): ?int
    {
        return $this->lastTargetId;
    }

    /**
     * Целые счётчики использования оружия.
     *
     * @param array<string|int, mixed> $rows Строка JSON.
     *
     * @return array<string, int> Счётчики.
     */
    private static function counts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $key => $value) {
            if (is_string($key) && is_int($value) && $value >= 0) {
                $counts[$key] = $value;
            }
        }

        return $counts;
    }
}
