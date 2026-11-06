<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapNodeRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Repository\MapNodeRepository;
use Mifrial\Roleplay\Map\Repository\MapPlacementRepository;

/**
 * Узлы и их размещения: один вход для графа.
 */
final class MapNodeStore
{
    /**
     * Создаёт стор.
     *
     * @param MapNodeRepository $nodeRepository Узлы.
     * @param MapPlacementRepository $placementRepository Размещения.
     *
     * @return void
     */
    public function __construct(
        private readonly MapNodeRepository $nodeRepository,
        private readonly MapPlacementRepository $placementRepository,
    ) {
    }

    /**
     * Узлы карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapNodeRecord> Узлы.
     */
    public function findNodes(int $mapId): array
    {
        return $this->nodeRepository->findByMap($mapId);
    }

    /**
     * Размещения варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapPlacementRecord> Размещения.
     */
    public function findPlacements(int $variantId): array
    {
        return $this->placementRepository->findByVariant($variantId);
    }

    /**
     * Одно размещение.
     *
     * @param int $variantId Вариант.
     * @param int $nodeId Узел.
     *
     * @return MapPlacementRecord|null Размещение или null.
     */
    public function findPlacement(int $variantId, int $nodeId): ?MapPlacementRecord
    {
        return $this->placementRepository->findOne($variantId, $nodeId);
    }

    /**
     * Узел в границах карты.
     *
     * @param int $mapId Карта.
     * @param int $nodeId Узел.
     *
     * @return MapNodeRecord Узел.
     */
    public function findNode(int $mapId, int $nodeId): MapNodeRecord
    {
        return $this->nodeRepository->getForMap($mapId, $nodeId);
    }

    /**
     * Добавляет узел.
     *
     * @param int $mapId Карта.
     * @param string $name Имя.
     * @param string $shortInfo Короткая информация.
     * @param string $publicInfo Раскрываемая информация.
     * @param string $gmInfo Информация ведущего.
     * @param int|null $linkedMapId Связанная карта.
     * @param string $icon Иконка.
     *
     * @return int Id.
     */
    public function addNode(
        int $mapId,
        string $name,
        string $shortInfo,
        string $publicInfo,
        string $gmInfo,
        ?int $linkedMapId,
        string $icon,
    ): int {
        return $this->nodeRepository->add(
            $mapId,
            $name,
            $shortInfo,
            $publicInfo,
            $gmInfo,
            $linkedMapId,
            $icon,
        );
    }

    /**
     * Пишет поля узла.
     *
     * @param int $nodeId Узел.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     */
    public function updateNode(int $nodeId, array $fields): void
    {
        $this->nodeRepository->update($nodeId, $fields);
    }

    /**
     * Ставит размещение.
     *
     * @param int $variantId Вариант.
     * @param int $nodeId Узел.
     * @param int $x X.
     * @param int $y Y.
     * @param bool $isPresent Присутствует.
     * @param string $labelOverride Подпись варианта.
     *
     * @return void
     */
    public function upsertPlacement(
        int $variantId,
        int $nodeId,
        int $x,
        int $y,
        bool $isPresent,
        string $labelOverride,
    ): void {
        $this->placementRepository->upsert($variantId, $nodeId, $x, $y, $isPresent, $labelOverride);
    }

    /**
     * Удаляет узел и его размещения.
     *
     * @param int $nodeId Узел.
     *
     * @return void
     */
    public function deleteNode(int $nodeId): void
    {
        $this->placementRepository->deleteByNode($nodeId);
        $this->nodeRepository->delete($nodeId);
    }

    /**
     * Удаляет размещения варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return void
     */
    public function deletePlacementsByVariant(int $variantId): void
    {
        $this->placementRepository->deleteByVariant($variantId);
    }
}
