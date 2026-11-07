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
use Mifrial\Roleplay\Map\Service\MapSpeed;

/**
 * Карта `map_variant_line`: лента (река, ручей, дорога вне графа).
 */
final class MapVariantLineTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_variant_line';
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
            new StringField('color', FieldSettings::fromOptions(['required' => true, 'default' => '#4a7fa5']), 16),
            new IntField('speed', FieldSettings::fromOptions([
                'required' => true,
                'default' => MapSpeed::DEFAULT,
            ]), 0, MapSpeed::MAX),
            new JsonField('points', FieldSettings::fromOptions(['required' => true])),
            new JsonField('widths', FieldSettings::fromOptions(['required' => true])),
            new JsonField('handles', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new JsonField('segments', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new IntField('color_blend', FieldSettings::fromOptions([
                'required' => true,
                'default' => 16,
            ]), 0, 1000),
            new JsonField('decor', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new StringField('label', FieldSettings::fromOptions(['required' => true]), 255),
            new BoolField('is_present', FieldSettings::fromOptions([
                'required' => true,
                'default' => true,
            ])),
            new IntField('sort_order', FieldSettings::fromOptions([
                'required' => true,
                'default' => 0,
            ]), 0, null),
        ];
    }
}
