<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Map\Repository\MapTerrainRepository;
use Mifrial\Roleplay\Map\Schema\MapSchema;
use Mifrial\Roleplay\Map\Table\MapTerrainTable;

/**
 * Карты и seed террейнов для CLI setup.
 */
final class MapModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз таблиц.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return MapSchema::getTableClasses();
    }

    /**
     * Seed глобальных террейнов.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [
            new SeedMapTerrainsStep(
                new MapTerrainRepository(
                    $this->smartTableGateway->open(MapTerrainTable::class)->records(),
                ),
            ),
        ];
    }
}
