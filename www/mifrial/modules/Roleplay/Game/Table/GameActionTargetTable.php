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
 * Цель действия. Пара тип+id одна на действие.
 */
final class GameActionTargetTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_action_target';
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
     * Одна цель на действие.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['action_id', 'target_type', 'target_id'],
        ];
    }
}
