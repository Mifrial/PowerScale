<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта членства `user_group_member`.
 */
final class UserGroupMemberTable extends SmartTableDefinition
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
            new UserReferenceField(
                'user_id',
                FieldSettings::fromOptions(['required' => true]),
            ),
            new ReferenceField(
                'group_id',
                FieldSettings::fromOptions(['required' => true]),
                UserGroupTable::class,
            ),
        ];
    }

    /**
     * Пара учётка+группа уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['user_id', 'group_id'],
        ];
    }
}
