<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_node`: узел маршрута или точка интереса.
 */
final class MapNodeTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_node';
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
            new BoolField('is_poi', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new StringField('name', $required, 255),
            new TextField('short_info', $required),
            new TextField('public_info', $required),
            new TextField('gm_info', $required),
            new IntField('linked_map_id', FieldSettings::fromOptions([]), 1, null),
            new StringField('icon', $required, 64),
        ];
    }
}
