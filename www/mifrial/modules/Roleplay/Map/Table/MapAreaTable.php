<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_area`: полигон ландшафта или объект.
 */
final class MapAreaTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_area';
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

        return [
            new IdField(),
            new ReferenceField('map_id', $indexed, MapTable::class, 'cascade'),
            new StringField('layer', FieldSettings::fromOptions(['required' => true, 'default' => 'object']), 16),
            new ReferenceField('terrain_id', FieldSettings::fromOptions([]), MapTerrainTable::class, 'setNull'),
            new StringField('kind', FieldSettings::fromOptions(['required' => true, 'default' => 'other']), 32),
            new StringField('label', $required, 255),
            new ReferenceField('node_id', FieldSettings::fromOptions([]), MapNodeTable::class, 'setNull'),
            new BoolField('blocks_movement', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
            ])),
        ];
    }
}
