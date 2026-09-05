<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Экземпляр в срезе: конверт часов и поля карты версии.
 */
final class VersionRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $versionId Идентификатор экземпляра.
     * @param int $entityId Идентификатор сущности.
     * @param bool $active Маркер активности.
     * @param array<string, mixed> $fields Колонки тела.
     * @param DateTime $createdAt Создание строки.
     *
     * @return void
     */
    public function __construct(
        private readonly int $versionId,
        private readonly int $entityId,
        private readonly bool $active,
        private readonly array $fields,
        private readonly DateTime $createdAt,
    ) {
    }

    /**
     * Собирает Record из строки ST.
     *
     * @param array<string, mixed> $row Колонки версии.
     *
     * @return self Экземпляр.
     *
     * @throws SpaceInvalidException Если конверт неполный.
     */
    public static function fromNormalized(array $row): self
    {
        $fields = $row;
        unset($fields['id'], $fields['entity_id'], $fields['active'], $fields['created_at']);

        return new self(
            RecordValue::requireInt($row, 'id'),
            RecordValue::requireInt($row, 'entity_id'),
            RecordValue::requireBool($row, 'active'),
            $fields,
            RecordValue::requireDateTime($row, 'created_at'),
        );
    }

    /**
     * Идентификатор экземпляра.
     *
     * @return int Номер версии.
     */
    public function getVersionId(): int
    {
        return $this->versionId;
    }

    /**
     * Идентификатор сущности.
     *
     * @return int Номер identity.
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * Marker снимка.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Поля тела экземпляра (не конверт).
     *
     * @return array<string, mixed> Колонки карты версии.
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Создание строки версии.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
