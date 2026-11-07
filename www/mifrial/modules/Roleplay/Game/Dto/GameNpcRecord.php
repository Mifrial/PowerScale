<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная строка NPC.
 */
final class GameNpcRecord
{
    private const INVALID = 'NPC row is invalid';

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
            throw new GameInvalidException(self::INVALID);
        }

        return new self(
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'name', self::INVALID),
            $version,
            GameRowFields::int($fields, 'actual_version', self::INVALID),
            $visibility,
        );
    }

    /**
     * Id строки NPC.
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




}
