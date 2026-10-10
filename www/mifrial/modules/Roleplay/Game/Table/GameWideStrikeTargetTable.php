<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Цель широкого удара. Без ссылки на удар: stop снимает сессию вместе с целями.
 */
final class GameWideStrikeTargetTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_wide_strike_target';
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
            new IntField('strike_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('defender_kind', FieldSettings::fromOptions(['required' => true]), 32),
            new IntField('defender_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('reaction', FieldSettings::fromOptions([]), 32),
            new IntField('block_item_inventory_id', FieldSettings::fromOptions([]), 1, null),
            new IntField('block_item_profile_index', FieldSettings::fromOptions([]), 0, null),
            new StringField('block_item_rule_code', FieldSettings::fromOptions([]), 255),
        ];
    }
}
