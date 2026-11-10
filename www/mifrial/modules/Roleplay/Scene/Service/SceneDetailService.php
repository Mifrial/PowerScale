<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Scene\Dto\SceneBoardRecord;
use Mifrial\Roleplay\Scene\Dto\SceneDetail;
use Mifrial\Roleplay\Scene\Dto\SceneRecord;
use Mifrial\Roleplay\Scene\Repository\SceneBoardRepository;
use Mifrial\Roleplay\Scene\Repository\SceneElementRepository;
use Mifrial\Roleplay\Scene\Repository\SceneEnclosureRepository;

/**
 * Собирает полный снимок локации без JSON-маппинга.
 */
final class SceneDetailService
{
    /**
     * Создаёт сервис.
     *
     * @param SceneAccessService $accessService Доступ.
     * @param SceneBoardRepository $boardRepository Поля.
     * @param SceneElementRepository $elementRepository Элементы.
     * @param SceneEnclosureRepository $enclosureRepository Вложения.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneAccessService $accessService,
        private readonly SceneBoardRepository $boardRepository,
        private readonly SceneElementRepository $elementRepository,
        private readonly SceneEnclosureRepository $enclosureRepository,
    ) {
    }

    /**
     * Загружает локацию.
     *
     * @param RequestActor $actor Актор.
     * @param int $sceneId Локация.
     *
     * @return SceneDetail Снимок.
     */
    public function load(RequestActor $actor, int $sceneId): SceneDetail
    {
        $sceneRecord = $this->accessService->requireSceneForView($actor, $sceneId);
        $boards = $this->boardRepository->findByLocation($sceneId);
        $boardIds = $this->boardIds($boards);

        return new SceneDetail(
            $sceneRecord,
            $boards,
            $this->elementRepository->findByBoardIds($boardIds),
            $this->enclosureRepository->findByParentBoardIds($boardIds),
            $this->accessService->canEdit($actor, $sceneRecord),
        );
    }

    /**
     * Id полей.
     *
     * @param array<int, SceneBoardRecord> $boards Поля.
     *
     * @return array<int, int> Id.
     */
    private function boardIds(array $boards): array
    {
        $ids = [];
        foreach ($boards as $boardRecord) {
            $ids[] = $boardRecord->getId();
        }

        return $ids;
    }
}
