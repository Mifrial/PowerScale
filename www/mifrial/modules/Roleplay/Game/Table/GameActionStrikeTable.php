<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Удар действия: предмет, профиль и цель одной строки.
 */
final class GameActionStrikeTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_action_strike';
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
                'action_id',
                FieldSettings::fromOptions(['required' => true]),
                GameActionTable::class,
                'cascade',
            ),
            new IntField('strike_index', FieldSettings::fromOptions(['required' => true]), 0, null),
            new IntField('instance_index', FieldSettings::fromOptions(['required' => true]), 0, null),
            new IntField('item_inventory_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('item_rule_code', FieldSettings::fromOptions(['required' => true]), 255),
            new StringField('profile_type', FieldSettings::fromOptions(['required' => true]), 32),
            new IntField('profile_index', FieldSettings::fromOptions(['required' => true]), 0, null),
            new JsonField('conditions', FieldSettings::fromOptions([])),
            new JsonField('after_strike_options', FieldSettings::fromOptions([])),
            new StringField('target_type', FieldSettings::fromOptions(['required' => true]), 32),
            new IntField('target_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('reaction', FieldSettings::fromOptions([]), 32),
            new IntField('block_item_inventory_id', FieldSettings::fromOptions([]), 1, null),
            new IntField('block_item_profile_index', FieldSettings::fromOptions([]), 0, null),
            new StringField('block_item_rule_code', FieldSettings::fromOptions([]), 255),
            new IntField('expected_sheet_version', FieldSettings::fromOptions([]), 0, null),
        ];
    }

    /**
     * Один удар на позицию действия.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['action_id', 'strike_index'],
        ];
    }
}
