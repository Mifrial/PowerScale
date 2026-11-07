<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры строки — публичный смысл Record; параметры ctor — колонки, не порты.

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
        return new self(
            self::int($fields, 'id'),
            self::int($fields, 'session_id'),
            self::optionalInt($fields, 'battle_id'),
            self::string($fields, 'participant_type'),
            self::int($fields, 'participant_id'),
            self::string($fields, 'status'),
            self::optionalString($fields, 'process_rule_code'),
            self::optionalString($fields, 'current_step_code'),
            self::optionalString($fields, 'step_status'),
            self::rows($fields, 'counters'),
            self::counts(self::rows($fields, 'weapon_use_counts')),
            self::optionalString($fields, 'last_target_type'),
            self::optionalInt($fields, 'last_target_id'),
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
     * Обязательное целое поле.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Имя.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если поле битое.
     */
    private static function int(array $fields, string $key): int
    {
        $value = $fields[$key] ?? null;
        if (!is_int($value)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return $value;
    }

    /**
     * Обязательная строка.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Имя.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если поле битое.
     */
    private static function string(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return $value;
    }

    /**
     * Необязательное целое.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Имя.
     *
     * @return int|null Значение или null.
     *
     * @throws GameInvalidException Если поле битое.
     */
    private static function optionalInt(array $fields, string $key): ?int
    {
        $value = $fields[$key] ?? null;
        if ($value !== null && !is_int($value)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return $value;
    }

    /**
     * Необязательная строка.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Имя.
     *
     * @return string|null Значение или null.
     *
     * @throws GameInvalidException Если поле битое.
     */
    private static function optionalString(array $fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return $value;
    }

    /**
     * JSON-объект колонки или пустой массив.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Имя.
     *
     * @return array<string|int, mixed> Значения.
     *
     * @throws GameInvalidException Если поле битое.
     */
    private static function rows(array $fields, string $key): array
    {
        $value = $fields[$key] ?? [];
        if (!is_array($value)) {
            throw new GameInvalidException('Game process row is invalid');
        }

        return $value;
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
