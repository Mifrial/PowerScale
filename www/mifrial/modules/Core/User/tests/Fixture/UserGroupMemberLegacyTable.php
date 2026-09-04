<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;

/**
 * Старая карта членства с member_key, то же физ. имя.
 */
final class UserGroupMemberLegacyTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'user_group_member';
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
            new ReferenceField(
                'group_id',
                FieldSettings::fromOptions(['required' => true]),
                UserGroupTable::class,
            ),
            new StringField('member_key', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
        ];
    }
}
