<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Команда проверки. Stop сессии удаляет строки этой сессии.
 */
final class GameCheckCommandTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_check_command';
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
            new ReferenceField(
                'session_id',
                FieldSettings::fromOptions(['required' => true]),
                GameSessionTable::class,
            ),
            new StringField('idempotency_key', FieldSettings::fromOptions(['required' => true]), 255),
            new JsonField('body', FieldSettings::fromOptions(['required' => true])),
            new JsonField('result', FieldSettings::fromOptions(['required' => true])),
        ];
    }

    /**
     * Пара игра+ключ уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['game_id', 'idempotency_key'],
        ];
    }
}
