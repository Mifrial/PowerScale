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
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $sessionId,
        private readonly int $stateVersion,
        private readonly ?array $turnOrder,
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
        if (!is_int($id) || !is_int($sessionId) || !is_int($stateVersion)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        return new self($id, $sessionId, $stateVersion, $turnOrder);
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
