<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Открытый бой текущей сессии. Unique по сессии нет.
 */
final class GameBattleTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_battle';
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
                'session_id',
                FieldSettings::fromOptions(['required' => true]),
                GameSessionTable::class,
            ),
            new IntField(
                'state_version',
                FieldSettings::fromOptions(['required' => true, 'default' => 1]),
                1,
                null,
            ),
            new JsonField('turn_order', FieldSettings::fromOptions([])),
        ];
    }
}
