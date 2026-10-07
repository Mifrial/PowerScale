<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная строка NPC.
 */
final class GameNpcRecord
{
    /**
     * Собирает строку.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<string, mixed> $version Лист.
     * @param int $actualVersion CAS.
     * @param array<string, mixed> $visibility Видимость.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly string $name,
        private readonly array $version,
        private readonly int $actualVersion,
        private readonly array $visibility,
    ) {
    }

    /**
     * Строка из нормализованных полей таблицы.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Строка.
     *
     * @throws GameInvalidException Если поле битое.
     */
    public static function fromNormalized(array $fields): self
    {
        $version = $fields['version'] ?? null;
        $visibility = $fields['visibility'] ?? null;
        if (!is_array($version) || !is_array($visibility)) {
            throw new GameInvalidException('NPC row is invalid');
        }

        return new self(
            self::intOf($fields, 'id'),
            self::intOf($fields, 'game_id'),
            self::stringOf($fields, 'name'),
            $version,
            self::intOf($fields, 'actual_version'),
            $visibility,
        );
    }

    /**
     * Id NPC.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Игра.
     *
     * @return int Id игры.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Лист npc.version.
     *
     * @return array<string, mixed> JSON.
     */
    public function getVersion(): array
    {
        return $this->version;
    }

    /**
     * Технический CAS.
     *
     * @return int Счётчик.
     */
    public function getActualVersion(): int
    {
        return $this->actualVersion;
    }

    /**
     * Видимость.
     *
     * @return array<string, mixed> Объект.
     */
    public function getVisibility(): array
    {
        return $this->visibility;
    }

    /**
     * Целое поле.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Ключ.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если не целое.
     */
    private static function intOf(array $fields, string $key): int
    {
        $value = $fields[$key] ?? null;
        if (!is_int($value)) {
            throw new GameInvalidException('NPC row is invalid');
        }

        return $value;
    }

    /**
     * Строковое поле.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Ключ.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если не строка.
     */
    private static function stringOf(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new GameInvalidException('NPC row is invalid');
        }

        return $value;
    }
}
