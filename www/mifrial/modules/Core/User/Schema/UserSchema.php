<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;

/**
 * Сверка карт User с физикой. Не порт соседа.
 */
final class UserSchema
{
    /**
     * Создаёт установщик схемы модуля.
     *
     * @param IOpenedSchema $userSchema DDL `user`.
     * @param IOpenedSchema $groupSchema DDL `user_group`.
     * @param IOpenedSchema $memberSchema DDL `user_group_member`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $userSchema,
        private readonly IOpenedSchema $groupSchema,
        private readonly IOpenedSchema $memberSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля в зашитом порядке для тестов.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты User.
     */
    public static function tableClasses(): array
    {
        return [
            UserTable::class,
            UserGroupTable::class,
            UserGroupMemberTable::class,
        ];
    }

    /**
     * Приводит таблицы модуля к текущим картам.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->userSchema);
        $this->apply($this->groupSchema);
        $this->apply($this->memberSchema);
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
