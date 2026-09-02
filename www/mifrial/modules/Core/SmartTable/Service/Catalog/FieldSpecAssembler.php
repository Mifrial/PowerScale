<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Catalog;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\HtmlField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\RuntimeDefinition;

/**
 * Собирает BaseField из спеки словаря без IdField.
 */
final class FieldSpecAssembler
{
    private const COMMON_KEYS = [
        'name',
        'type',
        'required',
        'multiple',
        'indexed',
        'unique',
        'label',
        'default',
    ];

    /**
     * Собирает список полей спеки.
     *
     * @param array<int, mixed> $fieldSpecs Спеки.
     *
     * @return array<int, BaseField> Поля.
     *
     * @throws MapInvalidException Если спека некорректна.
     */
    public function assembleAll(array $fieldSpecs): array
    {
        $fields = [];
        $seenNames = [];
        foreach ($fieldSpecs as $fieldSpec) {
            if (!is_array($fieldSpec)) {
                throw new MapInvalidException('Field spec is invalid');
            }

            $field = $this->assembleOne($fieldSpec);
            if (isset($seenNames[$field->name()])) {
                throw new MapInvalidException('Duplicate field name');
            }

            $seenNames[$field->name()] = true;
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Собирает runtime-definition и проверяет карту.
     *
     * @param string $tableName Имя.
     * @param array<int, mixed> $fieldSpecs Спеки.
     *
     * @return RuntimeDefinition Определение.
     *
     * @throws MapInvalidException Если спеки или имя некорректны.
     */
    public function makeDefinition(string $tableName, array $fieldSpecs): RuntimeDefinition
    {
        $definition = new RuntimeDefinition($tableName, $this->assembleAll($fieldSpecs));
        $definition->getMap();

        return $definition;
    }

    /**
     * Собирает одно поле.
     *
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если спека некорректна.
     */
    public function assembleOne(array $fieldSpec): BaseField
    {
        $fieldName = $fieldSpec['name'] ?? null;
        $fieldType = $fieldSpec['type'] ?? null;
        if (!is_string($fieldName) || !is_string($fieldType) || $fieldName === 'id' || $fieldType === 'id') {
            throw new MapInvalidException('Field spec name or type is invalid');
        }

        $this->assertKeys($fieldSpec, $fieldType);
        $fieldSettings = FieldSettings::fromOptions($fieldSpec);

        return $this->makeField($fieldName, $fieldType, $fieldSettings, $fieldSpec);
    }

    /**
     * Проверяет ключи спеки для типа.
     *
     * @param array<string, mixed> $fieldSpec Спека.
     * @param string $fieldType Тип.
     *
     * @return void
     *
     * @throws MapInvalidException Если ключ недопустим.
     */
    private function assertKeys(array $fieldSpec, string $fieldType): void
    {
        $allowed = array_merge(self::COMMON_KEYS, $this->extraKeys($fieldType));
        foreach (array_keys($fieldSpec) as $specKey) {
            if (!in_array($specKey, $allowed, true)) {
                throw new MapInvalidException('Field spec key is invalid');
            }
        }
    }

    /**
     * Ключи спеки сверх общих флагов.
     *
     * @param string $fieldType Тип.
     *
     * @return array<int, string> Ключи.
     *
     * @throws MapInvalidException Если тип неизвестен.
     */
    private function extraKeys(string $fieldType): array
    {
        return match ($fieldType) {
            'string' => ['maxLength'],
            'int' => ['min', 'max'],
            'reference' => ['target', 'onDelete'],
            'bool', 'datetime', 'text', 'html', 'json' => [],
            default => throw new MapInvalidException('Field spec type is invalid'),
        };
    }

    /**
     * Создаёт поле по типу.
     *
     * @param string $fieldName Имя.
     * @param string $fieldType Тип.
     * @param FieldSettings $fieldSettings Настройки.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если параметры типа некорректны.
     */
    private function makeField(
        string $fieldName,
        string $fieldType,
        FieldSettings $fieldSettings,
        array $fieldSpec,
    ): BaseField {
        return match ($fieldType) {
            'string' => $this->makeString($fieldName, $fieldSettings, $fieldSpec),
            'int' => $this->makeInt($fieldName, $fieldSettings, $fieldSpec),
            'reference' => $this->makeReference($fieldName, $fieldSettings, $fieldSpec),
            default => $this->makePlain($fieldName, $fieldType, $fieldSettings),
        };
    }

    /**
     * Собирает тип без доп. ключей конструктора.
     *
     * @param string $fieldName Имя.
     * @param string $fieldType Тип.
     * @param FieldSettings $fieldSettings Настройки.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если тип неизвестен.
     */
    private function makePlain(string $fieldName, string $fieldType, FieldSettings $fieldSettings): BaseField
    {
        return match ($fieldType) {
            'bool' => new BoolField($fieldName, $fieldSettings),
            'datetime' => new DateTimeField($fieldName, $fieldSettings),
            'text' => new TextField($fieldName, $fieldSettings),
            'html' => new HtmlField($fieldName, $fieldSettings),
            'json' => new JsonField($fieldName, $fieldSettings, null),
            default => throw new MapInvalidException('Field spec type is invalid'),
        };
    }

    /**
     * Собирает string.
     *
     * @param string $fieldName Имя.
     * @param FieldSettings $fieldSettings Настройки.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return StringField Поле.
     *
     * @throws MapInvalidException Если maxLength не int.
     */
    private function makeString(string $fieldName, FieldSettings $fieldSettings, array $fieldSpec): StringField
    {
        if (!array_key_exists('maxLength', $fieldSpec)) {
            return new StringField($fieldName, $fieldSettings);
        }

        $maxLength = $fieldSpec['maxLength'];
        if (!is_int($maxLength)) {
            throw new MapInvalidException('String maxLength must be int');
        }

        return new StringField($fieldName, $fieldSettings, $maxLength);
    }

    /**
     * Собирает int.
     *
     * @param string $fieldName Имя.
     * @param FieldSettings $fieldSettings Настройки.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return IntField Поле.
     *
     * @throws MapInvalidException Если min/max не int.
     */
    private function makeInt(string $fieldName, FieldSettings $fieldSettings, array $fieldSpec): IntField
    {
        return new IntField(
            $fieldName,
            $fieldSettings,
            $this->optionalInt($fieldSpec, 'min'),
            $this->optionalInt($fieldSpec, 'max'),
        );
    }

    /**
     * Собирает reference по физическому имени цели.
     *
     * @param string $fieldName Имя.
     * @param FieldSettings $fieldSettings Настройки.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return ReferenceField Поле.
     *
     * @throws MapInvalidException Если target нет или onDelete не строка.
     */
    private function makeReference(
        string $fieldName,
        FieldSettings $fieldSettings,
        array $fieldSpec,
    ): ReferenceField {
        $targetName = $fieldSpec['target'] ?? null;
        if (!is_string($targetName) || $targetName === '') {
            throw new MapInvalidException('Reference target is required');
        }

        $onDelete = $fieldSpec['onDelete'] ?? 'restrict';
        if (!is_string($onDelete)) {
            throw new MapInvalidException('Reference onDelete is invalid');
        }

        return ReferenceField::forTable($fieldName, $fieldSettings, $targetName, $onDelete);
    }

    /**
     * Читает опциональный int из спеки.
     *
     * @param array<string, mixed> $fieldSpec Спека.
     * @param string $optionKey Ключ.
     *
     * @return int|null Значение или null.
     *
     * @throws MapInvalidException Если значение не int.
     */
    private function optionalInt(array $fieldSpec, string $optionKey): ?int
    {
        if (!array_key_exists($optionKey, $fieldSpec)) {
            return null;
        }

        $optionValue = $fieldSpec[$optionKey];
        if (!is_int($optionValue)) {
            throw new MapInvalidException('Int bound must be int');
        }

        return $optionValue;
    }
}
