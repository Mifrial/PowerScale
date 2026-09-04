<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Schema;

use Mifrial\Core\Auth\Table\AuthGroupSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthPasswordResetTable;
use Mifrial\Core\Auth\Table\AuthSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сверка карт Auth с физикой. Не порт соседа.
 */
final class AuthSchema
{
    /**
     * Создаёт установщик схемы модуля.
     *
     * @param IOpenedSchema $identitySchema DDL `user_identity`.
     * @param IOpenedSchema $sessionSchema DDL `auth_session`.
     * @param IOpenedSchema $policySchema DDL `auth_security_policy`.
     * @param IOpenedSchema $groupPolicySchema DDL `auth_group_security_policy`.
     * @param IOpenedSchema $resetSchema DDL `auth_password_reset`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $identitySchema,
        private readonly IOpenedSchema $sessionSchema,
        private readonly IOpenedSchema $policySchema,
        private readonly IOpenedSchema $groupPolicySchema,
        private readonly IOpenedSchema $resetSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты Auth.
     */
    public static function getTableClasses(): array
    {
        return [
            UserIdentityTable::class,
            AuthSessionTable::class,
            AuthSecurityPolicyTable::class,
            AuthGroupSecurityPolicyTable::class,
            AuthPasswordResetTable::class,
        ];
    }

    /**
     * Приводит таблицы модуля к текущим картам.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->identitySchema);
        $this->apply($this->sessionSchema);
        $this->apply($this->policySchema);
        $this->apply($this->groupPolicySchema);
        $this->apply($this->resetSchema);
    }

    /**
     * Создаёт или обновляет одну карту.
     *
     * @param IOpenedSchema $openedSchema DDL одной таблицы.
     *
     * @return void
     */
    private function apply(IOpenedSchema $openedSchema): void
    {
        if ($openedSchema->exists()) {
            $openedSchema->updateTable();

            return;
        }

        $openedSchema->createTable();
    }
}
