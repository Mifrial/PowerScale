<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_terrain`: глобальный справочник поверхностей.
 */
final class MapTerrainTable extends SmartTableDefinition
{
    /**
     * Стандартные поверхности мира.
     *
     * @return array<int, array{code: string, label: string, speed_factor: int, color: string, sort_order: int}> Строки.
     */
    public static function defaultTerrains(): array
    {
        return [
            ['code' => 'road', 'label' => 'Дорога', 'speed_factor' => 100, 'color' => '#C5C6C6', 'sort_order' => 10],
            ['code' => 'path', 'label' => 'Тропа', 'speed_factor' => 70, 'color' => '#B8A57A', 'sort_order' => 20],
            ['code' => 'grass', 'label' => 'Трава', 'speed_factor' => 50, 'color' => '#9AC385', 'sort_order' => 30],
            ['code' => 'forest', 'label' => 'Лес', 'speed_factor' => 30, 'color' => '#4E7A4E', 'sort_order' => 40],
            ['code' => 'swamp', 'label' => 'Болото', 'speed_factor' => 15, 'color' => '#6B7050', 'sort_order' => 50],
            ['code' => 'water', 'label' => 'Вода', 'speed_factor' => 0, 'color' => '#8FB8C9', 'sort_order' => 60],
            ['code' => 'wall', 'label' => 'Стена', 'speed_factor' => 0, 'color' => '#5B5B5B', 'sort_order' => 70],
        ];
    }

    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_terrain';
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
            new StringField('code', FieldSettings::fromOptions([
                'required' => true,
                'unique' => true,
            ]), 64),
            new StringField('label', FieldSettings::fromOptions(['required' => true]), 255),
            new IntField('speed_factor', FieldSettings::fromOptions(['required' => true]), 0, 100),
            new StringField('color', FieldSettings::fromOptions(['required' => true]), 16),
            new IntField('sort_order', FieldSettings::fromOptions([
                'required' => true,
                'default' => 0,
            ]), 0, null),
        ];
    }
}
