<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Table\UserGroupTable;

/**
 * Связь группы User с политикой Auth.
 */
final class AuthGroupSecurityPolicyTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'auth_group_security_policy';
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
                'group_id',
                FieldSettings::fromOptions(['required' => true, 'unique' => true]),
                UserGroupTable::class,
                'cascade',
            ),
            new ReferenceField(
                'policy_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                AuthSecurityPolicyTable::class,
                'restrict',
            ),
        ];
    }
}
