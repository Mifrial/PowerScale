<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;

/**
 * Сверка карты Mechanic с физикой.
 */
final class MechanicSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $mechanicSchema DDL `mechanic`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $mechanicSchema,
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
            MechanicTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->mechanicSchema->exists()) {
            $this->mechanicSchema->updateTable();

            return;
        }

        $this->mechanicSchema->createTable();
    }
}
