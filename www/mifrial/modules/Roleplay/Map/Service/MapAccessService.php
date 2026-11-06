<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Map\Dto\MapRecord;
use Mifrial\Roleplay\Map\Exception\MapDeniedException;
use Mifrial\Roleplay\Map\Repository\MapRepository;

/**
 * Доступ к картам: владелец, public, view_all/edit_all.
 */
final class MapAccessService
{
    /**
     * Создаёт guard.
     *
     * @param IUserAccess $userAccess Guard учёток.
     * @param MapRepository $mapRepository Строки карт.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly MapRepository $mapRepository,
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
     * Требует право создавать карты.
     *
     * @return RequestActor Актор.
     */
    public function requireCreate(): RequestActor
    {
        return $this->userAccess->requireKey(MapPermissionKeys::EDIT);
    }

    /**
     * Видит все карты.
     *
     * @param RequestActor $actor Актор.
     *
     * @return bool Флаг.
     */
    public function canViewAll(RequestActor $actor): bool
    {
        return $actor->hasKey(MapPermissionKeys::VIEW_ALL) || $actor->hasKey(MapPermissionKeys::EDIT_ALL);
    }

    /**
     * Может смотреть карту.
     *
     * @param RequestActor $actor Актор.
     * @param MapRecord $mapRecord Карта.
     *
     * @return bool Флаг.
     */
    public function canView(RequestActor $actor, MapRecord $mapRecord): bool
    {
        return $this->canViewAll($actor)
            || $mapRecord->getOwnerId() === $actor->getUserId()
            || $mapRecord->getVisibility() === 'public';
    }

    /**
     * Может править карту.
     *
     * @param RequestActor $actor Актор.
     * @param MapRecord $mapRecord Карта.
     *
     * @return bool Флаг.
     */
    public function canEdit(RequestActor $actor, MapRecord $mapRecord): bool
    {
        return $actor->hasKey(MapPermissionKeys::EDIT_ALL)
            || $mapRecord->getOwnerId() === $actor->getUserId();
    }

    /**
     * Карта для просмотра или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     *
     * @return MapRecord Карта.
     *
     * @throws MapDeniedException Если доступа нет.
     */
    public function requireMapForView(RequestActor $actor, int $mapId): MapRecord
    {
        $mapRecord = $this->mapRepository->getById($mapId);
        if (!$this->canView($actor, $mapRecord)) {
            throw new MapDeniedException();
        }

        return $mapRecord;
    }

    /**
     * Карта для правки или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     *
     * @return MapRecord Карта.
     *
     * @throws MapDeniedException Если правки нет.
     */
    public function requireMapForEdit(RequestActor $actor, int $mapId): MapRecord
    {
        $mapRecord = $this->mapRepository->getById($mapId);
        if (!$this->canEdit($actor, $mapRecord)) {
            throw new MapDeniedException();
        }

        return $mapRecord;
    }
}
