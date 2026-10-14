<?php

declare(strict_types=1);

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
        $stateVersion = $fields['state_version'] ?? null;
        $turnOrder = self::turnOrder($fields['turn_order'] ?? null);
        $pending = $fields['pending_action_effects'] ?? [];
        $activeIndex = $fields['active_index'] ?? 0;
        $round = $fields['round'] ?? 0;
        $debt = $fields['action_debt'] ?? [];
        if (
            !is_int($id) || !is_int($sessionId) || !is_int($stateVersion) || !is_array($pending)
            || !is_int($activeIndex) || !is_int($round) || !is_array($debt)
        ) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        return new self($id, $sessionId, $stateVersion, $turnOrder, $pending, $activeIndex, $round, $debt);
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
}
