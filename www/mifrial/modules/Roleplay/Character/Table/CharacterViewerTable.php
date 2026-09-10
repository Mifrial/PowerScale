<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Именные зрители листа `character_viewer`.
 */
final class CharacterViewerTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'character_viewer';
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
                'character_id',
                FieldSettings::fromOptions(['required' => true]),
                CharacterTable::class,
                'cascade',
            ),
            ReferenceField::forTable(
                'user_id',
                FieldSettings::fromOptions(['required' => true]),
                'user',
            ),
            new JsonField('fields', FieldSettings::fromOptions(['required' => true])),
        ];
    }

    /**
     * Пара персонаж+зритель уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['character_id', 'user_id'],
        ];
    }
}
