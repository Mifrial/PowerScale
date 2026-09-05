<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Schema;

use Mifrial\Core\Logger\Table\LogTable;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сверка карты Logger с физикой.
 */
final class LoggerSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $logSchema DDL `log`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $logSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            LogTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->logSchema->exists()) {
            $this->logSchema->updateTable();

            return;
        }

        $this->logSchema->createTable();
    }
}
