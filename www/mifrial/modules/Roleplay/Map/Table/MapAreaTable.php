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
use Mifrial\Roleplay\Map\Service\MapAreaStroke;
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
        return [
            ...$this->keyFields(),
            ...$this->valueFields(),
        ];
    }

    /**
     * Ключи области: карта, место и группа.
     *
     * @return array Список полей.
     */
    private function keyFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'map_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                MapTable::class,
                'cascade',
            ),
            new ReferenceField('node_id', FieldSettings::fromOptions([]), MapNodeTable::class, 'setNull'),
            new ReferenceField('group_id', FieldSettings::fromOptions(['indexed' => true]), MapAreaGroupTable::class, 'setNull'),
        ];
    }

    /**
     * Содержимое области: слой, цвета, декор, скорость и подпись.
     *
     * @return array Список полей.
     */
    private function valueFields(): array
    {
        return [
            new StringField('layer', FieldSettings::fromOptions(['required' => true, 'default' => 'object']), 16),
            new StringField('color', FieldSettings::fromOptions(['required' => true, 'default' => '#8d8d8d']), 16),
            new StringField('stroke', FieldSettings::fromOptions(['required' => true, 'default' => '']), 16),
            // 0 допустим только для старых строк: ALTER добавляет колонку без умолчания,
            // и прочитанная ширина 0 трактуется как умолчание.
            new IntField('stroke_width', FieldSettings::fromOptions([
                'required' => true,
                'default' => MapAreaStroke::DEFAULT_WIDTH,
            ]), 0, MapAreaStroke::MAX_WIDTH),
            new JsonField('decor', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new IntField('speed', FieldSettings::fromOptions([
                'required' => true,
                'default' => MapSpeed::DEFAULT,
            ]), 0, MapSpeed::MAX),
            new StringField('label', FieldSettings::fromOptions(['required' => true]), 255),
            new IntField('sort_order', FieldSettings::fromOptions([
                'required' => true,
                'default' => 0,
            ]), 0, null),
        ];
    }
}
