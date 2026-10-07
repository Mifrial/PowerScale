<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Позиция магазина игры. Обмен отдельным складом не хранится.
 */
final class GameShopPositionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_shop_position';
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
                'game_id',
                FieldSettings::fromOptions(['required' => true]),
                GameTable::class,
            ),
            new StringField('rule_code', FieldSettings::fromOptions(['required' => true]), 255),
            new IntField('buy_price', FieldSettings::fromOptions(['required' => true]), 0, null),
            new IntField('sell_price', FieldSettings::fromOptions(['required' => false]), 0, null),
            new IntField('quantity', FieldSettings::fromOptions(['required' => true]), 0, null),
            new IntField('version', FieldSettings::fromOptions(['required' => true, 'default' => 1]), 1, null),
        ];
    }

    /**
     * Пара игра+код уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['game_id', 'rule_code'],
        ];
    }
}
