<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_variant_node`: координаты узла в конкретном варианте.
 */
final class MapVariantNodeTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_variant_node';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'variant_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                MapVariantTable::class,
                'cascade',
            ),
            new ReferenceField(
                'node_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                MapNodeTable::class,
                'cascade',
            ),
            new IntField('x', FieldSettings::fromOptions(['required' => true]), 0, 20000),
            new IntField('y', FieldSettings::fromOptions(['required' => true]), 0, 20000),
            new BoolField('is_present', FieldSettings::fromOptions([
                'required' => true,
                'default' => true,
            ])),
            new StringField('label_override', FieldSettings::fromOptions(['required' => true]), 255),
        ];
    }

    /**
     * У узла одно размещение на вариант.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['variant_id', 'node_id'],
        ];
    }
}
