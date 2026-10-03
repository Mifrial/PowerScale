<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры снимка правила — смысл Record; параметры ctor — поля, не порты.

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Versioning\Space\Dto\VersionRecord;

/**
 * Экземпляр правила в срезе.
 */
final class RuleVersionRecord
{
    private readonly ?RuleSpec $spec;

    private readonly bool $specBroken;

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
     * @param array<string|int, mixed> $specDocument Сырой JSON spec.
     * @param array<int, int> $keywordIds Признаки.
     * @param array<int, array<string, mixed>> $mechanics Список механик.
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
        private readonly array $specDocument,
        private readonly array $keywordIds,
        private readonly array $mechanics,
        private readonly string $contentStatus,
        private readonly string $contentNote,
        private readonly DateTime $createdAt,
    ) {
        $read = RuleSpecs::read($this->type, $this->specDocument);
        $this->spec = $read->getSpec();
        $this->specBroken = $read->isBroken();
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
            self::requireMechanicList($fields),
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
     * Контракт spec, если документ лёг в тип.
     *
     * @return RuleSpec|null DTO или null.
     */
    public function getSpec(): ?RuleSpec
    {
        return $this->spec;
    }

    /**
     * Сырой JSON spec.
     *
     * @return array<string|int, mixed> Документ.
     */
    public function getSpecDocument(): array
    {
        return $this->specDocument;
    }

    /**
     * Форма spec известного типа не разобралась.
     *
     * @return bool true, если версия битая.
     */
    public function isSpecBroken(): bool
    {
        return $this->specBroken;
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
     * Список механик снимка.
     *
     * @return array<int, array<string, mixed>> Строки mechanic_id и mechanic_payload.
     */
    public function getMechanics(): array
    {
        return $this->mechanics;
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
     * Список механик колонки mechanics. null — пустой список.
     *
     * @param array<string, mixed> $fields Карта.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws RuleInvalidException Если не список.
     */
    private static function requireMechanicList(array $fields): array
    {
        if (!array_key_exists('mechanics', $fields) || $fields['mechanics'] === null) {
            return [];
        }

        $value = $fields['mechanics'];
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuleInvalidException('Rule version record is incomplete');
        }

        return $value;
    }
}
