<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры — публичный смысл строки боя; параметры ctor — колонки, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Открытый бой.
 */
final class GameBattleRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id боя.
     * @param int $sessionId Сессия.
     * @param int $stateVersion Версия CAS.
     * @param array<int, array<string, mixed>>|null $turnOrder Порядок или null.
     * @param array<string, mixed> $pendingActionEffects Карта ожидания.
     * @param int $activeIndex Индекс текущего в turn_order.
     * @param int $round Номер раунда. 0 — курсор ещё не поставлен.
     * @param array<string, mixed> $actionDebt Карта долга ОД.
     * @param array<string, mixed> $concentrationCycle Карта цикла концентрации.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $sessionId,
        private readonly int $stateVersion,
        private readonly ?array $turnOrder,
        private readonly array $pendingActionEffects = [],
        private readonly int $activeIndex = 0,
        private readonly int $round = 0,
        private readonly array $actionDebt = [],
        private readonly array $concentrationCycle = [],
        private readonly array $marks = [],
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
            self::requiredInt($fields, 'id'),
            self::requiredInt($fields, 'session_id'),
            self::requiredInt($fields, 'state_version'),
            self::turnOrder($fields['turn_order'] ?? null),
            self::map($fields, 'pending_action_effects'),
            self::optionalInt($fields, 'active_index'),
            self::optionalInt($fields, 'round'),
            self::map($fields, 'action_debt'),
            self::map($fields, 'concentration_cycle'),
            self::marks($fields['marks'] ?? []),
        );
    }

    /**
     * Id боя.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Сессия боя.
     *
     * @return int Id сессии.
     */
    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    /**
     * Версия CAS.
     *
     * @return int Счётчик.
     */
    public function getStateVersion(): int
    {
        return $this->stateVersion;
    }

    /**
     * Порядок хода или null, пока команда его не записала.
     *
     * @return array<int, array<string, mixed>>|null Список.
     */
    public function getTurnOrder(): ?array
    {
        return $this->turnOrder;
    }

    /**
     * Карта ожидания. Ключ — character:id или npc:id.
     *
     * @return array<string, mixed> Объект.
     */
    public function getPendingActionEffects(): array
    {
        return $this->pendingActionEffects;
    }

    /**
     * Карта цикла концентрации. Ключ — character:id или npc:id.
     *
     * @return array<string, mixed> Объект.
     */
    public function getConcentrationCycle(): array
    {
        return $this->concentrationCycle;
    }

    /**
     * Индекс текущего в turn_order.
     *
     * @return int Смещение.
     */
    public function getActiveIndex(): int
    {
        return $this->activeIndex;
    }

    /**
     * Номер раунда. 0 — инициатива ещё не ставила курсор.
     *
     * @return int Номер.
     */
    public function getRound(): int
    {
        return $this->round;
    }

    /**
     * Карта долга. Ключ — character:id или npc:id.
     *
     * @return array<string, mixed> Объект.
     */
    public function getActionDebt(): array
    {
        return $this->actionDebt;
    }

    /**
     * Метки боя.
     *
     * @return array<int, array<string, mixed>> Список меток.
     */
    public function getMarks(): array
    {
        return $this->marks;
    }

    /**
     * Пустое поле или список записей.
     *
     * @param mixed $turnOrder Колонка.
     *
     * @return array<int, array<string, mixed>>|null Список.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function turnOrder(mixed $turnOrder): ?array
    {
        if ($turnOrder === null) {
            return null;
        }

        if (!is_array($turnOrder) || !array_is_list($turnOrder)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        foreach ($turnOrder as $entry) {
            if (!is_array($entry)) {
                throw new GameInvalidException('Game battle row is invalid');
            }
        }

        return $turnOrder;
    }

    /**
     * Обязательная целочисленная колонка.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если поля нет или оно не int.
     */
    private static function requiredInt(array $fields, string $key): int
    {
        $value = $fields[$key] ?? null;
        if (!is_int($value)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        return $value;
    }

    /**
     * Необязательная целочисленная колонка.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return int Значение или 0.
     *
     * @throws GameInvalidException Если значение не int.
     */
    private static function optionalInt(array $fields, string $key): int
    {
        $value = $fields[$key] ?? 0;
        if (!is_int($value)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        return $value;
    }

    /**
     * Карта колонки или пустая карта.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Карта.
     *
     * @throws GameInvalidException Если значение не массив.
     */
    private static function map(array $fields, string $key): array
    {
        $value = $fields[$key] ?? [];
        if (!is_array($value)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        return $value;
    }

    /**
     * Список меток боя.
     *
     * @param mixed $marks Колонка.
     *
     * @return array<int, array<string, mixed>> Метки.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function marks(mixed $marks): array
    {
        if (!is_array($marks) || !array_is_list($marks)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        foreach ($marks as $mark) {
            if (!is_array($mark)) {
                throw new GameInvalidException('Game battle row is invalid');
            }
        }

        return $marks;
    }
}
