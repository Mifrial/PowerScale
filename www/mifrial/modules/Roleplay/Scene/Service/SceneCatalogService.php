<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Scene\Dto\ScenePage;
use Mifrial\Roleplay\Scene\Dto\ScenePageQuery;
use Mifrial\Roleplay\Scene\Dto\SceneRecord;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Repository\SceneLocationRepository;

/**
 * Локации: каталог и архив. Создание и правка — в `scene.save`.
 */
final class SceneCatalogService
{
    /**
     * Создаёт сервис.
     *
     * @param SceneLocationRepository $locationRepository Строки локаций.
     * @param SceneAccessService $accessService Доступ.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneLocationRepository $locationRepository,
        private readonly SceneAccessService $accessService,
    ) {
    }

    /**
     * Страница локаций.
     *
     * @param RequestActor $actor Актор.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока имени.
     * @param bool $isArchived Архив.
     *
     * @return ScenePage Страница.
     *
     * @throws SceneInvalidException Если границы неверны.
     */
    public function findPage(
        RequestActor $actor,
        int $limit,
        int $offset,
        ?string $searchQuery,
        bool $isArchived,
    ): ScenePage {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new SceneInvalidException('Scene page bounds are invalid');
        }

        $trimmedQuery = $searchQuery === null ? null : trim($searchQuery);

        return $this->locationRepository->findPage(new ScenePageQuery(
            $actor->getUserId(),
            $this->accessService->canViewAll($actor),
            $limit,
            $offset,
            $trimmedQuery === '' ? null : $trimmedQuery,
            $isArchived,
        ));
    }

    /**
     * Локация для просмотра.
     *
     * @param RequestActor $actor Актор.
     * @param int $sceneId Локация.
     *
     * @return SceneRecord Локация.
     */
    public function get(RequestActor $actor, int $sceneId): SceneRecord
    {
        return $this->accessService->requireSceneForView($actor, $sceneId);
    }

    /**
     * Архивирует локацию.
     *
     * @param RequestActor $actor Актор.
     * @param int $sceneId Локация.
     *
     * @return void
     */
    public function archive(RequestActor $actor, int $sceneId): void
    {
        $sceneRecord = $this->accessService->requireSceneForEdit($actor, $sceneId);
        $this->locationRepository->update($sceneId, [
            'is_archived' => true,
            'version' => $sceneRecord->getVersion() + 1,
            'updated_at' => DateTime::now(),
            'updated_by' => $actor->getUserId(),
        ]);
    }
}
