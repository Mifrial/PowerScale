<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\File\Field\FileReferenceField;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта `map_variant`: эпоха и подложка карты.
 */
final class MapVariantTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_variant';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $required = FieldSettings::fromOptions(['required' => true]);
        $indexed = FieldSettings::fromOptions(['required' => true, 'indexed' => true]);
        $now = FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]);

        return [
            new IdField(),
            new ReferenceField('map_id', $indexed, MapTable::class, 'cascade'),
            new StringField('label', $required, 255),
            new IntField('from_minutes', FieldSettings::fromOptions([]), 0, null),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new FileReferenceField('base_file_id', FieldSettings::fromOptions([]), 'setNull'),
            new IntField('width', FieldSettings::fromOptions([]), 1, 20000),
            new IntField('height', FieldSettings::fromOptions([]), 1, 20000),
            new IntField('millimeters_per_pixel', FieldSettings::fromOptions([]), 1, 100000000),
            new BoolField('is_default', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new DateTimeField('created_at', $now),
        ];
    }

    /**
     * Подпись эпохи уникальна внутри карты.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['map_id', 'label'],
        ];
    }
}
