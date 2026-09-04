<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\Auth\Table\AuthGroupSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthPasswordResetTable;
use Mifrial\Core\Auth\Table\AuthSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;

/**
 * Снос User и FK-детей Auth перед DROP user_group.
 */
final class UserMysqlTables
{
    /**
     * Удаляет таблицы Auth, затем User, если есть.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    public static function drop(ISmartTableGateway $smartTableGateway): void
    {
        $tableClasses = [
            AuthPasswordResetTable::class,
            AuthSessionTable::class,
            UserIdentityTable::class,
            AuthGroupSecurityPolicyTable::class,
            AuthSecurityPolicyTable::class,
            UserGroupMemberTable::class,
            UserGroupTable::class,
            UserTable::class,
        ];
        foreach ($tableClasses as $tableClass) {
            $openedTable = $smartTableGateway->open($tableClass);
            if ($openedTable->schema()->exists()) {
                $openedTable->schema()->deleteTable();
            }
        }
    }
}
