<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_variant_edge`: ребро маршрута с террейном.
 */
final class MapVariantEdgeTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_variant_edge';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $indexed = FieldSettings::fromOptions(['required' => true, 'indexed' => true]);
        $required = FieldSettings::fromOptions(['required' => true]);

        return [
            new IdField(),
            new ReferenceField('variant_id', $indexed, MapVariantTable::class, 'cascade'),
            new ReferenceField('from_node_id', $indexed, MapNodeTable::class, 'cascade'),
            new ReferenceField('to_node_id', $indexed, MapNodeTable::class, 'cascade'),
            new ReferenceField('terrain_id', $indexed, MapTerrainTable::class, 'restrict'),
            new JsonField('path', $required),
            new IntField('distance_meters', FieldSettings::fromOptions([]), 0, null),
            new BoolField('is_directed', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new StringField('label', $required, 255),
        ];
    }
}
