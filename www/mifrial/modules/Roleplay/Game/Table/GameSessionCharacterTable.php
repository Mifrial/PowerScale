<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Снимок персонажей, вошедших в текущую сессию.
 */
final class GameSessionCharacterTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_session_character';
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
            ReferenceField::forTable(
                'character_id',
                FieldSettings::fromOptions(['required' => true]),
                'character',
            ),
        ];
    }

    /**
     * Персонаж один раз в сессии.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['session_id', 'character_id'],
        ];
    }
}
