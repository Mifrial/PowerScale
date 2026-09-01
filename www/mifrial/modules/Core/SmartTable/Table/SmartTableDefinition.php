<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Table;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;

/**
 * Определение таблицы в PHP-классе: имя и карта полей.
 */
abstract class SmartTableDefinition
{
    /**
     * @var array<string, BaseField>|null
     */
    private ?array $fieldMap = null;

    /**
     * Возвращает физическое имя таблицы.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    public function getName(): string
    {
        $tableName = $this->tableName();
        if (preg_match('/^[a-z][a-z0-9_]*$/', $tableName) !== 1) {
            throw new MapInvalidException('Table name is invalid');
        }

        return $tableName;
    }

    /**
     * Возвращает карту полей по имени.
     *
     * @return array<string, BaseField> Поля.
     *
     * @throws MapInvalidException Если карта некорректна.
     */
    public function getMap(): array
    {
        if ($this->fieldMap !== null) {
            return $this->fieldMap;
        }

        $this->fieldMap = $this->indexFields($this->defineFields());

        return $this->fieldMap;
    }

    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    abstract protected function tableName(): string;

    /**
     * Перечисляет поля определения.
     *
     * @return array<int, BaseField> Список полей.
     */
    abstract protected function defineFields(): array;

    /**
     * Индексирует поля и проверяет id.
     *
     * @param array<int, BaseField> $fields Список полей.
     *
     * @return array<string, BaseField> Карта.
     *
     * @throws MapInvalidException Если дубль, нет id, id занят или флаги index недопустимы.
     */
    private function indexFields(array $fields): array
    {
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldName = $field->name();
            if (isset($fieldMap[$fieldName])) {
                throw new MapInvalidException('Duplicate field name');
            }

            if ($fieldName === 'id' && !$field instanceof IdField) {
                throw new MapInvalidException('id must be IdField');
            }

            $this->assertMultipleAllowed($field);
            $this->assertIndexFlags($field);
            $fieldMap[$fieldName] = $field;
        }

        if (!isset($fieldMap['id']) || !$fieldMap['id'] instanceof IdField) {
            throw new MapInvalidException('Map must include IdField id');
        }

        return $fieldMap;
    }

    /**
     * Проверяет тип и длину string для multiple.
     *
     * @param BaseField $field Поле карты.
     *
     * @return void
     *
     * @throws MapInvalidException Если multiple на этом типе нельзя.
     */
    private function assertMultipleAllowed(BaseField $field): void
    {
        if (!$field->settings()->multiple()) {
            return;
        }

        $fieldType = $field->type();
        if (!in_array($fieldType, ['string', 'int', 'bool', 'datetime'], true)) {
            throw new MapInvalidException('Multiple is not allowed for this field type');
        }

        if ($field instanceof StringField && $field->maxLength() > 255) {
            throw new MapInvalidException('Multiple string maxLength cannot exceed 255');
        }
    }

    /**
     * Проверяет indexed/unique для типа поля.
     *
     * @param BaseField $field Поле карты.
     *
     * @return void
     *
     * @throws MapInvalidException Если флаги на этом поле нельзя.
     */
    private function assertIndexFlags(BaseField $field): void
    {
        $fieldSettings = $field->settings();
        if (!$fieldSettings->indexed() && !$fieldSettings->unique()) {
            return;
        }

        if ($field instanceof IdField || $fieldSettings->multiple()) {
            throw new MapInvalidException('Index flags are not allowed on this field');
        }

        $fieldType = $field->type();
        if (!in_array($fieldType, ['string', 'int', 'bool', 'datetime', 'reference'], true)) {
            throw new MapInvalidException('Index flags are not allowed for this field type');
        }

        if ($field instanceof StringField && $field->maxLength() > 255) {
            throw new MapInvalidException('Indexed string maxLength cannot exceed 255');
        }
    }
}
