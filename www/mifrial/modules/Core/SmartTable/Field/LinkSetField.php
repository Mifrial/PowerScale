<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use ReflectionClass;

/**
 * Множество связей на id другой таблицы; не hop.
 */
final class LinkSetField extends IntField
{
    /**
     * Создаёт поле linkset.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param string $targetClass Класс определения цели.
     * @param string $onDelete Режим restrict или none.
     * @param bool $targetIsPhysicalName True, если $targetClass — физ. имя.
     *
     * @return void
     *
     * @throws MapInvalidException Если цель или onDelete недопустимы.
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        private readonly string $targetClass,
        private readonly string $onDelete = 'restrict',
        private readonly bool $targetIsPhysicalName = false,
    ) {
        parent::__construct($fieldName, $fieldSettings, 1, null);
        $this->assertLinkSetSettings();
        if ($this->targetIsPhysicalName) {
            $this->assertPhysicalTargetName($this->targetClass);

            return;
        }

        $this->assertTargetDefinition();
    }

    /**
     * Ссылка на физическое имя таблицы без PHP-класса цели.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param string $tableName Физическое имя цели.
     * @param string $onDelete Режим restrict или none.
     *
     * @return self Поле.
     *
     * @throws MapInvalidException Если имя цели или onDelete недопустимы.
     */
    public static function forTable(
        string $fieldName,
        FieldSettings $fieldSettings,
        string $tableName,
        string $onDelete = 'restrict',
    ): self {
        return new self($fieldName, $fieldSettings, $tableName, $onDelete, true);
    }

    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'linkset';
    }

    /**
     * Возвращает режим удаления цели.
     *
     * @return string restrict или none.
     */
    public function onDelete(): string
    {
        return $this->onDelete;
    }

    /**
     * Возвращает физическое имя таблицы цели.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя цели недопустимо.
     */
    public function targetTableName(): string
    {
        if ($this->targetIsPhysicalName) {
            return $this->targetClass;
        }

        $targetTable = new $this->targetClass();

        return $targetTable->getName();
    }

    /**
     * Возвращает имя колонки цели: всегда id.
     *
     * @return string id.
     */
    public function targetIdField(): string
    {
        return 'id';
    }

    /**
     * Class-string цели или null для словаря.
     *
     * @return string|null Класс definition.
     */
    public function targetDefinitionClass(): ?string
    {
        if ($this->targetIsPhysicalName) {
            return null;
        }

        return $this->targetClass;
    }

    /**
     * Проверяет флаги и onDelete.
     *
     * @return void
     *
     * @throws MapInvalidException Если сочетание недопустимо.
     */
    private function assertLinkSetSettings(): void
    {
        $fieldSettings = $this->settings();
        if ($fieldSettings->multiple()) {
            throw new MapInvalidException('Linkset cannot set multiple');
        }

        if ($fieldSettings->indexed() || $fieldSettings->unique()) {
            throw new MapInvalidException('Linkset cannot be indexed or unique');
        }

        if (!in_array($this->onDelete, ['restrict', 'none'], true)) {
            throw new MapInvalidException('Linkset onDelete is invalid');
        }
    }

    /**
     * Проверяет class-string цели без getMap.
     *
     * @return void
     *
     * @throws MapInvalidException Если цели нет или это не definition.
     */
    private function assertTargetDefinition(): void
    {
        if (!class_exists($this->targetClass) || !is_subclass_of($this->targetClass, SmartTableDefinition::class)) {
            throw new MapInvalidException('Linkset target must be a table definition');
        }

        $targetReflection = new ReflectionClass($this->targetClass);
        if (!$targetReflection->isInstantiable()) {
            throw new MapInvalidException('Linkset target must be a table definition');
        }

        $this->targetTableName();
    }

    /**
     * Проверяет физическое имя цели как имя таблицы.
     *
     * @param string $tableName Имя.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    private function assertPhysicalTargetName(string $tableName): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $tableName) !== 1) {
            throw new MapInvalidException('Linkset target table name is invalid');
        }
    }
}
