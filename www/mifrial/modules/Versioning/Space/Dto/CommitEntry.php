<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Пункт commit: keep, create или change.
 */
final class CommitEntry
{
    /**
     * Ключи конверта, которые нельзя передать в картах тела.
     *
     * @var array<int, string>
     */
    private const RESERVED_KEYS = ['id', 'entity_id', 'created_at', 'active'];

    /**
     * Создаёт пункт.
     *
     * @param int|null $versionId Keep: id экземпляра.
     * @param int|null $entityId Change: identity; create — null.
     * @param array<string, mixed> $identityFields Create: колонки identity без id.
     * @param array<string, mixed> $versionFields Create/change: колонки версии без конверта.
     * @param bool $active Marker снимка.
     *
     * @return void
     */
    private function __construct(
        private readonly ?int $versionId,
        private readonly ?int $entityId,
        private readonly array $identityFields,
        private readonly array $versionFields,
        private readonly bool $active,
    ) {
    }

    /**
     * Существующий экземпляр в новом составе.
     *
     * @param int $versionId Id версии.
     *
     * @return self Пункт keep.
     */
    public static function keep(int $versionId): self
    {
        return new self($versionId, null, [], [], true);
    }

    /**
     * Новая identity и первый экземпляр.
     *
     * @param array<string, mixed> $identityFields Колонки identity.
     * @param array<string, mixed> $versionFields Колонки версии.
     * @param bool $active Marker.
     *
     * @return self Пункт create.
     *
     * @throws SpaceInvalidException Если в картах служебные ключи.
     */
    public static function create(array $identityFields, array $versionFields, bool $active = true): self
    {
        self::assertColumnMap($identityFields);
        self::assertColumnMap($versionFields);

        return new self(null, null, $identityFields, $versionFields, $active);
    }

    /**
     * Новый экземпляр существующей identity.
     *
     * @param int $entityId Identity.
     * @param array<string, mixed> $versionFields Колонки версии.
     * @param bool $active Marker.
     *
     * @return self Пункт change.
     *
     * @throws SpaceInvalidException Если в карте служебные ключи.
     */
    public static function change(int $entityId, array $versionFields, bool $active = true): self
    {
        self::assertColumnMap($versionFields);

        return new self(null, $entityId, [], $versionFields, $active);
    }

    /**
     * Id keep-версии или null.
     *
     * @return int|null Id или null.
     */
    public function getVersionId(): ?int
    {
        return $this->versionId;
    }

    /**
     * Identity для change или null.
     *
     * @return int|null Id или null.
     */
    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    /**
     * Колонки новой identity.
     *
     * @return array<string, mixed> Карта.
     */
    public function getIdentityFields(): array
    {
        return $this->identityFields;
    }

    /**
     * Колонки нового экземпляра без конверта.
     *
     * @return array<string, mixed> Карта.
     */
    public function getVersionFields(): array
    {
        return $this->versionFields;
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
     * Карта без служебных ключей часов.
     *
     * @param array<string, mixed> $columnMap Поля.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если ключ зарезервирован или не строка.
     */
    private static function assertColumnMap(array $columnMap): void
    {
        foreach ($columnMap as $columnName => $columnValue) {
            unset($columnValue);
            if (!is_string($columnName) || in_array($columnName, self::RESERVED_KEYS, true)) {
                throw new SpaceInvalidException('Commit column map is invalid');
            }
        }
    }
}
