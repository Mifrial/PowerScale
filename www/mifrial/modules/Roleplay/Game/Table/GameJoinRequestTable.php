<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Заявка на вступление. Не приглашение и не участник.
 */
final class GameJoinRequestTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_join_request';
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
            ReferenceField::forTable(
                'user_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'user',
            ),
            new StringField('status', FieldSettings::fromOptions(['required' => true]), 32),
            new DateTimeField('created_at', FieldSettings::fromOptions(['required' => true])),
            new DateTimeField('updated_at', FieldSettings::fromOptions(['required' => true])),
        ];
    }
}
