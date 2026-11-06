<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapTerrainRecord;
use Mifrial\Roleplay\Map\Repository\MapTerrainRepository;

/**
 * Глобальный справочник террейнов.
 */
final class MapTerrainService
{
    /**
     * Создаёт сервис.
     *
     * @param MapTerrainRepository $terrainRepository Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly MapTerrainRepository $terrainRepository,
    ) {
    }

    /**
     * Все террейны.
     *
     * @return array<int, MapTerrainRecord> Справочник.
     */
    public function getAll(): array
    {
        return $this->terrainRepository->findAll();
    }

    /**
     * Террейн по коду.
     *
     * @param string $code Код.
     *
     * @return MapTerrainRecord Террейн.
     */
    public function requireByCode(string $code): MapTerrainRecord
    {
        return $this->terrainRepository->getByCode($code);
    }
}
