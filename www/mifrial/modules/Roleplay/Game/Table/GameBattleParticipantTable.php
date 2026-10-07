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
 * Состав одного боя. Персонаж и NPC в одной колонке id без внешней ссылки.
 */
final class GameBattleParticipantTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_battle_participant';
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
                'battle_id',
                FieldSettings::fromOptions(['required' => true]),
                GameBattleTable::class,
            ),
            new StringField('kind', FieldSettings::fromOptions(['required' => true]), 32),
            new IntField('subject_id', FieldSettings::fromOptions(['required' => true]), 1, null),
        ];
    }

    /**
     * Пара тип+id один раз в бое.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['battle_id', 'kind', 'subject_id'],
        ];
    }
}
