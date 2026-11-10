<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Scene\Dto\Action\FindScenePageInput;
use Mifrial\Roleplay\Scene\Dto\Action\SceneIdInput;

/**
 * HTTP-сценарии каталога локаций.
 */
final class SceneCatalogHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param SceneAccessService $accessService Доступ.
     * @param SceneCatalogService $catalogService Локации.
     * @param SceneDetailService $detailService Снимок локации.
     * @param SceneViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneAccessService $accessService,
        private readonly SceneCatalogService $catalogService,
        private readonly SceneDetailService $detailService,
        private readonly SceneViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Страница локаций.
     *
     * @param FindScenePageInput $input JSON.
     *
     * @return array<string, mixed> Страница.
     */
    public function findPage(FindScenePageInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $page = $this->catalogService->findPage(
            $actor,
            $input->limit,
            $input->offset,
            $input->q->isPresent() ? $input->q->getValue() : null,
            $input->isArchived->isPresent() && $input->isArchived->getValue() === true,
        );
        $items = [];
        foreach ($page->getItems() as $sceneRecord) {
            $items[] = $this->viewAssembler->assembleSummary($sceneRecord);
        }

        return ['items' => $items, 'total' => $page->getTotal()];
    }

    /**
     * Локация с полями и элементами.
     *
     * @param SceneIdInput $input JSON.
     *
     * @return array<string, mixed> Локация.
     */
    public function get(SceneIdInput $input): array
    {
        $actor = $this->accessService->requireActor();

        return $this->detail($actor, $input->id);
    }

    /**
     * Архивирует локацию.
     *
     * @param SceneIdInput $input JSON.
     *
     * @return array<string, mixed> Пустой результат.
     */
    public function archive(SceneIdInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $this->catalogService->archive($actor, $input->id);

        return [];
    }

    /**
     * Снимок локации.
     *
     * @param RequestActor $actor Актор.
     * @param int $sceneId Локация.
     *
     * @return array<string, mixed> JSON.
     */
    private function detail(RequestActor $actor, int $sceneId): array
    {
        return $this->viewAssembler->assembleDetail($this->detailService->load($actor, $sceneId));
    }
}
