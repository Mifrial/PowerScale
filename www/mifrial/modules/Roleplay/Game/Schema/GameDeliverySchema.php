<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;

/**
 * Карта доставки.
 */
final class GameDeliverySchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $deliverySchema DDL доставки.
     *
     * @return void
     */
    public function __construct(private readonly IOpenedSchema $deliverySchema)
    {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [GameDeliveryTable::class];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->deliverySchema->exists()) {
            $this->deliverySchema->updateTable();

            return;
        }

        $this->deliverySchema->createTable();
    }
}
