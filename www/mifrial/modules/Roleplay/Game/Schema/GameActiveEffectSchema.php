<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameActiveEffectTable;

/**
 * Карта наложенных длительных эффектов боя.
 */
final class GameActiveEffectSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $activeEffectSchema DDL таблицы.
     *
     * @return void
     */
    public function __construct(private readonly IOpenedSchema $activeEffectSchema)
    {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [GameActiveEffectTable::class];
    }

    /**
     * Создаёт или обновляет таблицу.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->activeEffectSchema->exists()) {
            $this->activeEffectSchema->updateTable();

            return;
        }

        $this->activeEffectSchema->createTable();
    }
}
