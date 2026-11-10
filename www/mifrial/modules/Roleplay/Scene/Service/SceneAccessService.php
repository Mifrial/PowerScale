<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Scene\Dto\SceneRecord;
use Mifrial\Roleplay\Scene\Exception\SceneDeniedException;
use Mifrial\Roleplay\Scene\Repository\SceneLocationRepository;

/**
 * Доступ к локациям: владелец, view_all и edit_all.
 */
final class SceneAccessService
{
    /**
     * Создаёт guard.
     *
     * @param IUserAccess $userAccess Guard учёток.
     * @param SceneLocationRepository $locationRepository Строки локаций.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly SceneLocationRepository $locationRepository,
    ) {
    }

    /**
     * Требует актора.
     *
     * @return RequestActor Актор.
     */
    public function requireActor(): RequestActor
    {
        return $this->userAccess->requireActor();
    }

    /**
     * Требует право создавать локации.
     *
     * @return RequestActor Актор.
     */
    public function requireCreate(): RequestActor
    {
        return $this->userAccess->requireKey(ScenePermissionKeys::EDIT);
    }

    /**
     * Видит все локации.
     *
     * @param RequestActor $actor Актор.
     *
     * @return bool Флаг.
     */
    public function canViewAll(RequestActor $actor): bool
    {
        return $actor->hasKey(ScenePermissionKeys::VIEW_ALL)
            || $actor->hasKey(ScenePermissionKeys::EDIT_ALL);
    }

    /**
     * Может смотреть локацию.
     *
     * @param RequestActor $actor Актор.
     * @param SceneRecord $sceneRecord Локация.
     *
     * @return bool Флаг.
     */
    public function canView(RequestActor $actor, SceneRecord $sceneRecord): bool
    {
        return $this->canViewAll($actor) || $sceneRecord->getOwnerId() === $actor->getUserId();
    }

    /**
     * Может править локацию.
     *
     * @param RequestActor $actor Актор.
     * @param SceneRecord $sceneRecord Локация.
     *
     * @return bool Флаг.
     */
    public function canEdit(RequestActor $actor, SceneRecord $sceneRecord): bool
    {
        return $actor->hasKey(ScenePermissionKeys::EDIT_ALL)
            || $sceneRecord->getOwnerId() === $actor->getUserId();
    }

    /**
     * Локация для просмотра или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param int $sceneId Локация.
     *
     * @return SceneRecord Локация.
     *
     * @throws SceneDeniedException Если доступа нет.
     */
    public function requireSceneForView(RequestActor $actor, int $sceneId): SceneRecord
    {
        $sceneRecord = $this->locationRepository->getById($sceneId);
        if (!$this->canView($actor, $sceneRecord)) {
            throw new SceneDeniedException();
        }

        return $sceneRecord;
    }

    /**
     * Локация для правки или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param int $sceneId Локация.
     *
     * @return SceneRecord Локация.
     *
     * @throws SceneDeniedException Если правки нет.
     */
    public function requireSceneForEdit(RequestActor $actor, int $sceneId): SceneRecord
    {
        $sceneRecord = $this->locationRepository->getById($sceneId);
        if (!$this->canEdit($actor, $sceneRecord)) {
            throw new SceneDeniedException();
        }

        return $sceneRecord;
    }
}
