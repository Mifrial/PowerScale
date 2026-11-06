<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Map\Dto\Action\FindMapPageInput;
use Mifrial\Roleplay\Map\Dto\Action\MapIdInput;

/**
 * HTTP-сценарии каталога карт.
 */
final class MapCatalogHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapCatalogService $catalogService Карты.
     * @param MapDetailService $detailService Снимок карты.
     * @param MapViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapCatalogService $catalogService,
        private readonly MapDetailService $detailService,
        private readonly MapViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Страница карт.
     *
     * @param FindMapPageInput $input JSON.
     *
     * @return array<string, mixed> Страница.
     */
    public function findPage(FindMapPageInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $page = $this->catalogService->findPage(
            $actor,
            $input->limit,
            $input->offset,
            $input->q->isPresent() ? $input->q->getValue() : null,
            $input->ownerId->isPresent() ? $input->ownerId->getValue() : null,
            $input->isArchived->isPresent() && $input->isArchived->getValue() === true,
        );
        $items = [];
        foreach ($page->getItems() as $mapRecord) {
            $items[] = $this->viewAssembler->assembleSummary($mapRecord);
        }

        return ['items' => $items, 'total' => $page->getTotal()];
    }

    /**
     * Карта с графом.
     *
     * @param MapIdInput $input JSON.
     *
     * @return array<string, mixed> Карта.
     */
    public function get(MapIdInput $input): array
    {
        $actor = $this->accessService->requireActor();

        return $this->detail($actor, $input->id);
    }

    /**
     * Архивирует карту.
     *
     * @param MapIdInput $input JSON.
     *
     * @return array<string, mixed> Пустой результат.
     */
    public function archive(MapIdInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $this->catalogService->archive($actor, $input->id);

        return [];
    }

    /**
     * Снимок карты.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     *
     * @return array<string, mixed> JSON.
     */
    private function detail(RequestActor $actor, int $mapId): array
    {
        return $this->viewAssembler->assembleDetail($this->detailService->load($actor, $mapId));
    }
}
