<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Repository\MapEdgeRepository;

/**
 * Рёбра варианта: чтение и запись для редактора и маршрута.
 */
final class MapEdgeStore
{
    /**
     * Создаёт стор.
     *
     * @param MapEdgeRepository $edgeRepository Строки рёбер.
     *
     * @return void
     */
    public function __construct(
        private readonly MapEdgeRepository $edgeRepository,
    ) {
    }

    /**
     * Рёбра варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapEdgeRecord> Рёбра.
     */
    public function findEdges(int $variantId): array
    {
        return $this->edgeRepository->findByVariant($variantId);
    }

    /**
     * Ребро по id.
     *
     * @param int $edgeId Id.
     *
     * @return MapEdgeRecord Ребро.
     */
    public function findEdge(int $edgeId): MapEdgeRecord
    {
        return $this->edgeRepository->getById($edgeId);
    }

    /**
     * Добавляет ребро.
     *
     * @param int $variantId Вариант.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     * @param int $terrainId Террейн.
     * @param array<int, array{0: int, 1: int}> $path Полилиния; пусто — прямая.
     * @param int|null $distanceMeters Расстояние.
     * @param bool $isDirected Направленное.
     * @param string $label Подпись.
     *
     * @return int Id.
     */
    public function addEdge(
        int $variantId,
        int $fromNodeId,
        int $toNodeId,
        int $terrainId,
        array $path,
        ?int $distanceMeters,
        bool $isDirected,
        string $label,
    ): int {
        return $this->edgeRepository->add(
            $variantId,
            $fromNodeId,
            $toNodeId,
            $terrainId,
            $path,
            $distanceMeters,
            $isDirected,
            $label,
        );
    }

    /**
     * Пишет поля ребра.
     *
     * @param int $edgeId Ребро.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     */
    public function updateEdge(int $edgeId, array $fields): void
    {
        $this->edgeRepository->update($edgeId, $fields);
    }

    /**
     * Удаляет ребро.
     *
     * @param int $edgeId Ребро.
     *
     * @return void
     */
    public function deleteEdge(int $edgeId): void
    {
        $this->edgeRepository->delete($edgeId);
    }

    /**
     * Удаляет рёбра узла.
     *
     * @param int $nodeId Узел.
     *
     * @return void
     */
    public function deleteByNode(int $nodeId): void
    {
        $this->edgeRepository->deleteByNode($nodeId);
    }
}
