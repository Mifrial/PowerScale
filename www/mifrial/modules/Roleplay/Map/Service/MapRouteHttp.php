<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\Action\RouteMapInput;

/**
 * HTTP-сценарий маршрута.
 */
final class MapRouteHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapRouteService $routeService Маршрут.
     * @param MapViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapRouteService $routeService,
        private readonly MapViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Строит маршрут.
     *
     * @param RouteMapInput $input JSON.
     *
     * @return array<string, mixed> Маршрут.
     */
    public function route(RouteMapInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $route = $this->routeService->route(
            $actor,
            $input->mapId,
            $input->variantId,
            $input->fromX,
            $input->fromY,
            $input->toX,
            $input->toY,
        );

        return $this->viewAssembler->assembleRoute($route);
    }
}
