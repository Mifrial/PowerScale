<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * HTTP-сценарий глобального справочника террейнов.
 */
final class MapTerrainHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapTerrainService $terrainService Справочник.
     * @param MapViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapTerrainService $terrainService,
        private readonly MapViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Все террейны.
     *
     * @return array<int, array<string, mixed>> Террейны.
     */
    public function getTerrains(): array
    {
        $this->accessService->requireActor();
        $views = [];
        foreach ($this->terrainService->getAll() as $terrainRecord) {
            $views[] = $this->viewAssembler->assembleTerrain($terrainRecord);
        }

        return $views;
    }
}
