<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameActiveSpellTable;

/**
 * Карта поддерживаемых заклинаний боя.
 */
final class GameActiveSpellSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $activeSpellSchema DDL таблицы.
     *
     * @return void
     */
    public function __construct(private readonly IOpenedSchema $activeSpellSchema)
    {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [GameActiveSpellTable::class];
    }

    /**
     * Создаёт или обновляет таблицу.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->activeSpellSchema->exists()) {
            $this->activeSpellSchema->updateTable();

            return;
        }

        $this->activeSpellSchema->createTable();
    }
}
