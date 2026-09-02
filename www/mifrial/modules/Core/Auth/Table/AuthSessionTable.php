<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Table\UserTable;

/**
 * Карта сессии `auth_session`.
 */
final class AuthSessionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'auth_session';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            IdField::big(),
            new ReferenceField(
                'user_id',
                FieldSettings::fromOptions(['required' => true]),
                UserTable::class,
                'cascade',
            ),
            new StringField('token_hash', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new DateTimeField('expires_at', FieldSettings::fromOptions(['required' => true])),
        ];
    }
}
