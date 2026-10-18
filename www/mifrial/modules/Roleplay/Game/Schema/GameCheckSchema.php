<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameCheckTable;

/**
 * Карта проверки.
 */
final class GameCheckSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $checkSchema DDL проверки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $checkSchema,
    ) {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            GameCheckTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->checkSchema->exists()) {
            $this->checkSchema->updateTable();

            return;
        }

        $this->checkSchema->createTable();
    }
}
