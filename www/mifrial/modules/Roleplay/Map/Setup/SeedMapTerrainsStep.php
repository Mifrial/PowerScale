<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Setup;

use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Roleplay\Map\Repository\MapTerrainRepository;
use Mifrial\Roleplay\Map\Table\MapTerrainTable;

/**
 * Досевает стандартные террейны, не трогая существующие коды.
 */
final class SeedMapTerrainsStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param MapTerrainRepository $terrainRepository Строки террейнов.
     *
     * @return void
     */
    public function __construct(
        private readonly MapTerrainRepository $terrainRepository,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Roleplay/Map:seed.terrains';
    }

    /**
     * Досевает отсутствующие коды.
     *
     * @return void
     */
    public function run(): void
    {
        $this->terrainRepository->ensureDefaults(MapTerrainTable::defaultTerrains());
    }
}
