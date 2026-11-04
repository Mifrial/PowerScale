<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта одноразового токена сброса пароля.
 */
final class AuthPasswordResetTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'auth_password_reset';
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
            new UserReferenceField(
                'user_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'cascade',
            ),
            new StringField('token_hash', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new DateTimeField('expires_at', FieldSettings::fromOptions(['required' => true])),
            new DateTimeField('used_at', FieldSettings::fromOptions([])),
        ];
    }
}
