<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Schema;

use Mifrial\Core\Agent\Table\AgentTable;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сверка карты Agent с физикой. Не порт соседа.
 */
final class AgentSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $agentSchema DDL `agent`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $agentSchema,
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
            AgentTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->agentSchema->exists()) {
            $this->agentSchema->updateTable();

            return;
        }

        $this->agentSchema->createTable();
    }
}
