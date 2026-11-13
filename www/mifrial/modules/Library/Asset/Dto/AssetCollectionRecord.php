<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto;

use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Прочитанная строка `asset_collection`.
 */
final class AssetCollectionRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param string $module Владелец назначения.
     * @param string $code Код внутри модуля.
     * @param string $title Подпись для UI.
     * @param bool $isActive Коллекция принимает и выдаёт ассеты.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $module,
        private readonly string $code,
        private readonly string $title,
        private readonly bool $isActive,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws AssetInvalidException Если строка битая.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            AssetValues::requireInt($fields, 'id'),
            AssetValues::requireString($fields, 'module'),
            AssetValues::requireString($fields, 'code'),
            AssetValues::requireString($fields, 'title'),
            AssetValues::requireBool($fields, 'is_active'),
        );
    }

    /**
     * Идентификатор.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Модуль-владелец назначения.
     *
     * @return string Например `Roleplay/Scene`.
     */
    public function getModule(): string
    {
        return $this->module;
    }

    /**
     * Код коллекции.
     *
     * @return string Например `decor`.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Подпись коллекции.
     *
     * @return string Подпись.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Коллекция активна.
     *
     * @return bool Флаг.
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }
}
