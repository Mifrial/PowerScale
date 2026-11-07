<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Map\Service\MapSpeed;

/**
 * Карта `map_area`: полигон поверхности или объекта.
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
            new StringField('color', FieldSettings::fromOptions(['required' => true, 'default' => '#8d8d8d']), 16),
            new JsonField('decor', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new IntField('speed', FieldSettings::fromOptions([
                'required' => true,
                'default' => MapSpeed::DEFAULT,
            ]), 0, MapSpeed::MAX),
            new StringField('label', $required, 255),
            new ReferenceField('node_id', FieldSettings::fromOptions([]), MapNodeTable::class, 'setNull'),
            new IntField('sort_order', FieldSettings::fromOptions([
                'required' => true,
                'default' => 0,
            ]), 0, null),
        ];
    }
}
