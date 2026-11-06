<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Map\Dto\MapPage;
use Mifrial\Roleplay\Map\Dto\MapPageQuery;
use Mifrial\Roleplay\Map\Dto\MapRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Repository\MapRepository;

/**
 * Карты: каталог и архив. Создание и правка — в `map.save`.
 */
final class MapCatalogService
{
    /**
     * Создаёт сервис.
     *
     * @param MapRepository $mapRepository Строки карт.
     * @param MapAccessService $accessService Доступ.
     *
     * @return void
     */
    public function __construct(
        private readonly MapRepository $mapRepository,
        private readonly MapAccessService $accessService,
    ) {
    }

    /**
     * Страница карт.
     *
     * @param RequestActor $actor Актор.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока имени.
     * @param int|null $ownerId Владелец.
     * @param bool $isArchived Архив.
     *
     * @return MapPage Страница.
     *
     * @throws MapInvalidException Если границы неверны.
     */
    public function findPage(
        RequestActor $actor,
        int $limit,
        int $offset,
        ?string $searchQuery,
        ?int $ownerId,
        bool $isArchived,
    ): MapPage {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new MapInvalidException('Map page bounds are invalid');
        }

        if ($ownerId !== null && $ownerId < 1) {
            throw new MapInvalidException('Map owner filter is invalid');
        }

        $trimmedQuery = $searchQuery === null ? null : trim($searchQuery);

        return $this->mapRepository->findPage(new MapPageQuery(
            $actor->getUserId(),
            $this->accessService->canViewAll($actor),
            $limit,
            $offset,
            $trimmedQuery === '' ? null : $trimmedQuery,
            $ownerId,
            $isArchived,
        ));
    }

    /**
     * Карта для просмотра.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     *
     * @return MapRecord Карта.
     */
    public function get(RequestActor $actor, int $mapId): MapRecord
    {
        return $this->accessService->requireMapForView($actor, $mapId);
    }

    /**
     * Архивирует карту.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     *
     * @return void
     */
    public function archive(RequestActor $actor, int $mapId): void
    {
        $mapRecord = $this->accessService->requireMapForEdit($actor, $mapId);
        $this->mapRepository->update($mapId, [
            'is_archived' => true,
            'version' => $mapRecord->getVersion() + 1,
            'updated_at' => DateTime::now(),
            'updated_by' => $actor->getUserId(),
        ]);
    }
}
