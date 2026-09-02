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
 * Карта способа входа `user_identity`.
 */
final class UserIdentityTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'user_identity';
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
                'user_id',
                FieldSettings::fromOptions(['required' => true]),
                UserTable::class,
            ),
            new StringField('kind', FieldSettings::fromOptions(['required' => true])),
            new StringField('identity_key', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new StringField('secret_hash', FieldSettings::fromOptions(['required' => true])),
            new DateTimeField('last_used_at', FieldSettings::fromOptions([])),
        ];
    }
}
