<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Table;

use Mifrial\Core\File\Field\FileReferenceField;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта `scene_board`: одно 2D-поле внутри локации.
 */
final class SceneBoardTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'scene_board';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $required = FieldSettings::fromOptions(['required' => true]);
        $now = FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]);

        return [
            new IdField(),
            new ReferenceField(
                'location_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                SceneLocationTable::class,
                'cascade',
            ),
            new StringField('name', $required, 255),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, 1000000),
            new FileReferenceField('base_file_id', FieldSettings::fromOptions([]), 'setNull'),
            new IntField('base_file_width', FieldSettings::fromOptions([]), 1, 50000),
            new IntField('base_file_height', FieldSettings::fromOptions([]), 1, 50000),
            new IntField('bound_width', FieldSettings::fromOptions(['required' => true]), 1, 1000000),
            new IntField('bound_height', FieldSettings::fromOptions(['required' => true]), 1, 1000000),
            new IntField(
                'occupancy_cell',
                FieldSettings::fromOptions(['required' => true, 'default' => 16]),
                1,
                1000000,
            ),
            new IntField(
                'z_quantum',
                FieldSettings::fromOptions(['required' => true, 'default' => 16]),
                1,
                1000000,
            ),
            new IntField('grid_step', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, 1000000),
            new DateTimeField('created_at', $now),
        ];
    }

    /**
     * Имя поля уникально внутри локации.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['location_id', 'name'],
        ];
    }
}
