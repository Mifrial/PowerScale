<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_area_group`: группа однотипных областей.
 */
final class MapAreaGroupTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_area_group';
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
            new ReferenceField('map_id', $indexed, MapTable::class, 'cascade'),
            new StringField('label', $required, 255),
        ];
    }
}
