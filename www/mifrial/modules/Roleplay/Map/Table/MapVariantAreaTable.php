<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `map_variant_area`: полигон области в конкретном варианте.
 */
final class MapVariantAreaTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'map_variant_area';
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
                'area_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                MapAreaTable::class,
                'cascade',
            ),
            new JsonField('path', FieldSettings::fromOptions(['required' => true])),
            new BoolField('is_present', FieldSettings::fromOptions([
                'required' => true,
                'default' => true,
            ])),
        ];
    }

    /**
     * У области одна форма на вариант.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['variant_id', 'area_id'],
        ];
    }
}
