<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;

/**
 * Карта открытого удара.
 */
final class GameStrikeSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $strikeSchema DDL удара.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $strikeSchema,
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
            GameStrikeTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->strikeSchema->exists()) {
            $this->strikeSchema->updateTable();

            return;
        }

        $this->strikeSchema->createTable();
    }
}
