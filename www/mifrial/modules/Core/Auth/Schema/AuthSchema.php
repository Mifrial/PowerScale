<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Schema;

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
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $identitySchema,
        private readonly IOpenedSchema $sessionSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты Auth.
     */
    public static function tableClasses(): array
    {
        return [
            UserIdentityTable::class,
            AuthSessionTable::class,
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
