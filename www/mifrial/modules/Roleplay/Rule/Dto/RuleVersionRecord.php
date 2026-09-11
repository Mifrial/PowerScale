<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры снимка правила — смысл Record; параметры ctor — поля, не порты.

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Versioning\Space\Dto\VersionRecord;

/**
 * Экземпляр правила в срезе.
 */
final class RuleVersionRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $versionId Экземпляр.
     * @param int $entityId Identity.
     * @param string $code Семантический ключ.
     * @param bool $active Marker.
     * @param string $type Тип.
     * @param string $name Подпись.
     * @param string $description Текст.
     * @param array<string|int, mixed> $spec Spec.
     * @param array<int, int> $keywordIds Признаки.
     * @param int|null $mechanicId Механика.
     * @param array<string|int, mixed> $mechanicPayload Payload.
     * @param string $contentStatus Статус.
     * @param string $contentNote Комментарий разработки.
     * @param DateTime $createdAt Создание.
     *
     * @return void
     */
    public function __construct(
        private readonly int $versionId,
        private readonly int $entityId,
        private readonly string $code,
        private readonly bool $active,
        private readonly string $type,
        private readonly string $name,
        private readonly string $description,
        private readonly array $spec,
        private readonly array $keywordIds,
        private readonly ?int $mechanicId,
        private readonly array $mechanicPayload,
        private readonly string $contentStatus,
        private readonly string $contentNote,
        private readonly DateTime $createdAt,
    ) {
    }

    /**
     * Из экземпляра часов и code identity.
     *
     * @param VersionRecord $versionRecord Часы.
     * @param string $code Ключ.
     *
     * @return self Правило.
     *
     * @throws RuleInvalidException Если тело неполное.
     */
    public static function fromClock(VersionRecord $versionRecord, string $code): self
    {
        $fields = $versionRecord->getFields();

        return new self(
            $versionRecord->getVersionId(),
            $versionRecord->getEntityId(),
            $code,
            $versionRecord->isActive(),
            self::requireString($fields, 'type'),
            self::requireString($fields, 'name'),
            self::requireString($fields, 'description'),
            self::requireArray($fields, 'spec'),
            self::requireIntList($fields, 'keywords'),
            self::optionalInt($fields, 'mechanic_id'),
            self::requireArray($fields, 'mechanic_payload'),
            self::requireString($fields, 'content_status'),
            self::stringOrEmpty($fields, 'content_note'),
            $versionRecord->getCreatedAt(),
        );
    }

    /**
     * Id экземпляра.
     *
     * @return int Version id.
     */
    public function getVersionId(): int
    {
        return $this->versionId;
    }

    /**
     * Identity сущности.
     *
     * @return int Entity id.
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * Семантический ключ.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Маркер активности снимка.
     *
     * @return bool Активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Тип.
     *
     * @return string Код типа.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * JSON spec типа правила.
     *
     * @return array<string|int, mixed> JSON.
     */
    public function getSpec(): array
    {
        return $this->spec;
    }

    /**
     * Признаки.
     *
     * @return array<int, int> Id.
     */
    public function getKeywordIds(): array
    {
        return $this->keywordIds;
    }

    /**
     * Механика.
     *
     * @return int|null Id.
     */
    public function getMechanicId(): ?int
    {
        return $this->mechanicId;
    }

    /**
     * Payload механики.
     *
     * @return array<string|int, mixed> JSON.
     */
    public function getMechanicPayload(): array
    {
        return $this->mechanicPayload;
    }

    /**
     * Редакционный статус.
     *
     * @return string Статус.
     */
    public function getContentStatus(): string
    {
        return $this->contentStatus;
    }

    /**
     * Комментарий разработки.
     *
     * @return string Текст.
     */
    public function getContentNote(): string
    {
        return $this->contentNote;
    }

    /**
     * Создание.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Строка поля.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return string Значение.
     *
     * @throws RuleInvalidException Если нет строки.
     */
    private static function requireString(array $fields, string $fieldName): string
    {
        $value = $fields[$fieldName] ?? null;
        if (!is_string($value)) {
            throw new RuleInvalidException('Rule version record is incomplete');
        }

        return $value;
    }

    /**
     * Строка поля или пустая, если ключа нет.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return string Значение.
     *
     * @throws RuleInvalidException Если не строка.
     */
    private static function stringOrEmpty(array $fields, string $fieldName): string
    {
        if (!array_key_exists($fieldName, $fields) || $fields[$fieldName] === null) {
            return '';
        }

        $value = $fields[$fieldName];
        if (!is_string($value)) {
            throw new RuleInvalidException('Rule version record is incomplete');
        }

        return $value;
    }

    /**
     * Массив поля.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return array<string|int, mixed> JSON.
     *
     * @throws RuleInvalidException Если не массив.
     */
    private static function requireArray(array $fields, string $fieldName): array
    {
        $value = $fields[$fieldName] ?? null;
        if (!is_array($value)) {
            throw new RuleInvalidException('Rule version record is incomplete');
        }

        return $value;
    }

    /**
     * Список целых id признаков.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return array<int, int> Id.
     *
     * @throws RuleInvalidException Если не list int.
     */
    private static function requireIntList(array $fields, string $fieldName): array
    {
        $value = $fields[$fieldName] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuleInvalidException('Rule version record is incomplete');
        }

        $keywordIds = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new RuleInvalidException('Rule version record is incomplete');
            }

            $keywordIds[] = $item;
        }

        return $keywordIds;
    }

    /**
     * Необязательное целое поле.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Ключ.
     *
     * @return int|null Значение.
     *
     * @throws RuleInvalidException Если не int и не null.
     */
    private static function optionalInt(array $fields, string $fieldName): ?int
    {
        if (!array_key_exists($fieldName, $fields) || $fields[$fieldName] === null) {
            return null;
        }

        $value = $fields[$fieldName];
        if (!is_int($value)) {
            throw new RuleInvalidException('Rule version record is incomplete');
        }

        return $value;
    }
}
