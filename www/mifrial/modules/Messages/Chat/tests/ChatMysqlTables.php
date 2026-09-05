<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;

/**
 * Снос таблиц Chat до DROP user (FK).
 */
final class ChatMysqlTables
{
    /**
     * Удаляет таблицы Chat, если есть.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    public static function drop(ISmartTableGateway $smartTableGateway): void
    {
        $tableClasses = [
            ChatMessageTable::class,
            ChatMemberTable::class,
            ChatTable::class,
        ];
        foreach ($tableClasses as $tableClass) {
            $openedTable = $smartTableGateway->open($tableClass);
            if ($openedTable->schema()->exists()) {
                $openedTable->schema()->deleteTable();
            }
        }
    }
}
