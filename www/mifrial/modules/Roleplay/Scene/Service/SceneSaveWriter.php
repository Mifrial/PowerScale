<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок локации собирается из разделов: поля, элементы и вложения — разные
// сущности со своим порядком записи.

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Scene\Dto\SceneBoardRecord;
use Mifrial\Roleplay\Scene\Dto\SceneElementRecord;
use Mifrial\Roleplay\Scene\Dto\SceneEnclosureRecord;
use Mifrial\Roleplay\Scene\Repository\SceneBoardRepository;
use Mifrial\Roleplay\Scene\Repository\SceneElementRepository;
use Mifrial\Roleplay\Scene\Repository\SceneEnclosureRepository;
use Mifrial\Roleplay\Scene\Repository\SceneLocationRepository;

/**
 * Запись снимка локации: строка локации, поля, элементы и вложения.
 */
final class SceneSaveWriter
{
    /**
     * Создаёт писатель.
     *
     * @param SceneLocationRepository $locationRepository Строки локаций.
     * @param SceneBoardRepository $boardRepository Поля.
     * @param SceneElementRepository $elementRepository Элементы.
     * @param SceneEnclosureRepository $enclosureRepository Вложения.
     * @param SceneSaveRows $rows Общие операции над строками снимка.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneLocationRepository $locationRepository,
        private readonly SceneBoardRepository $boardRepository,
        private readonly SceneElementRepository $elementRepository,
        private readonly SceneEnclosureRepository $enclosureRepository,
        private readonly SceneSaveRows $rows,
    ) {
    }

    /**
     * Пишет снимок локации.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $sceneId Локация или null для новой.
     * @param array<string, mixed> $data Нормализованные данные.
     *
     * @return int Id локации.
     */
    public function write(RequestActor $actor, ?int $sceneId, array $data): int
    {
        $isNew = $sceneId === null;
        $sceneId = $this->writeScene($actor, $sceneId, $data);
        $existingBoards = $isNew ? [] : $this->boardRepository->findByLocation($sceneId);
        $boardIds = $this->boardIds($existingBoards);
        $existingElements = $isNew ? [] : $this->elementRepository->findByBoardIds($boardIds);
        $existingEnclosures = $isNew ? [] : $this->enclosureRepository->findByParentBoardIds($boardIds);
        $this->deleteStaleElements($existingElements, $data['elements']);
        $this->deleteStaleEnclosures($existingEnclosures, $data['enclosures']);
        $boardMap = $this->writeBoards($sceneId, $data['boards'], $existingBoards);
        $this->writeElements($data['elements'], $existingElements, $boardMap);
        $this->writeEnclosures($data['enclosures'], $existingEnclosures, $boardMap);
        $this->touchScene($sceneId, $actor, $isNew);

        return $sceneId;
    }

    /**
     * Создаёт или правит строку локации.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $sceneId Локация.
     * @param array<string, mixed> $data Данные.
     *
     * @return int Id локации.
     */
    private function writeScene(RequestActor $actor, ?int $sceneId, array $data): int
    {
        if ($sceneId === null) {
            return $this->locationRepository->add(
                $actor->getUserId(),
                $data['name'],
                $data['description'],
                $data['resolution'],
                $data['schemaVersion'],
            );
        }

        $this->locationRepository->update($sceneId, $this->sceneFields($data));

        return $sceneId;
    }

    /**
     * Поля строки локации.
     *
     * @param array<string, mixed> $data Данные.
     *
     * @return array<string, mixed> Поля.
     */
    private function sceneFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'],
            'resolution' => $data['resolution'],
            'schema_version' => $data['schemaVersion'],
        ];
    }

    /**
     * Версия, время и автор сохранения.
     *
     * @param int $sceneId Локация.
     * @param RequestActor $actor Актор.
     * @param bool $isNew Новая локация.
     *
     * @return void
     */
    private function touchScene(int $sceneId, RequestActor $actor, bool $isNew): void
    {
        $sceneRecord = $this->locationRepository->getById($sceneId);
        $this->locationRepository->update($sceneId, [
            'version' => $isNew ? 1 : $sceneRecord->getVersion() + 1,
            'updated_at' => DateTime::now(),
            'updated_by' => $actor->getUserId(),
        ]);
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

    /**
     * Пишет поля и возвращает карту временных id.
     *
     * @param int $sceneId Локация.
     * @param array<int, array<string, mixed>> $boards Поля снимка.
     * @param array<int, SceneBoardRecord> $existing Существующие поля.
     *
     * @return array<int, int> Id снимка → реальный id.
     */
    private function writeBoards(int $sceneId, array $boards, array $existing): array
    {
        $existingById = $this->boardIndex($existing);
        [$updates, $inserts] = $this->rows->split($boards, $existingById, 'Scene board is unknown');
        foreach ($updates as $id => $board) {
            $this->boardRepository->update($id, $this->boardFields($board));
        }

        foreach ($this->rows->stale($existingById, $updates) as $id) {
            $this->boardRepository->delete($id);
        }

        $idMap = $this->rows->ids($updates);
        foreach ($inserts as $board) {
            $idMap[$board['id']] = $this->insertBoard($sceneId, $board);
        }

        return $idMap;
    }

    /**
     * Поля строки поля.
     *
     * @param array<string, mixed> $board Поле снимка.
     *
     * @return array<string, mixed> Поля.
     */
    private function boardFields(array $board): array
    {
        return [
            'name' => $board['name'],
            'sort_order' => $board['sortOrder'],
            'base_file_id' => $board['baseFileId'],
            'base_file_width' => $board['baseFileWidth'],
            'base_file_height' => $board['baseFileHeight'],
            'bound_width' => $board['boundWidth'],
            'bound_height' => $board['boundHeight'],
            'occupancy_cell' => $board['occupancyCell'],
            'z_quantum' => $board['zQuantum'],
            'grid_step' => $board['gridStep'],
        ];
    }

    /**
     * Вставляет поле.
     *
     * @param int $sceneId Локация.
     * @param array<string, mixed> $board Поле снимка.
     *
     * @return int Реальный id.
     */
    private function insertBoard(int $sceneId, array $board): int
    {
        return $this->boardRepository->add(
            $sceneId,
            $board['name'],
            $board['sortOrder'],
            $board['baseFileId'],
            $board['baseFileWidth'],
            $board['baseFileHeight'],
            $board['boundWidth'],
            $board['boundHeight'],
            $board['occupancyCell'],
            $board['zQuantum'],
            $board['gridStep'],
        );
    }

    /**
     * Индекс полей.
     *
     * @param array<int, SceneBoardRecord> $boards Поля.
     *
     * @return array<int, true> Id → true.
     */
    private function boardIndex(array $boards): array
    {
        $index = [];
        foreach ($boards as $boardRecord) {
            $index[$boardRecord->getId()] = true;
        }

        return $index;
    }

    /**
     * Удаляет элементы, которых нет в снимке.
     *
     * @param array<int, SceneElementRecord> $existing Существующие элементы.
     * @param array<int, array<string, mixed>> $rows Элементы снимка.
     *
     * @return void
     */
    private function deleteStaleElements(array $existing, array $rows): void
    {
        $existingById = $this->elementIndex($existing);
        [$updates] = $this->rows->split($rows, $existingById, 'Scene element is unknown');
        foreach ($this->rows->stale($existingById, $updates) as $id) {
            $this->elementRepository->delete($id);
        }
    }

    /**
     * Пишет элементы.
     *
     * @param array<int, array<string, mixed>> $rows Элементы снимка.
     * @param array<int, SceneElementRecord> $existing Существующие элементы.
     * @param array<int, int> $boardMap Id поля → реальный id.
     *
     * @return void
     */
    private function writeElements(array $rows, array $existing, array $boardMap): void
    {
        $existingById = $this->elementIndex($existing);
        [$updates, $inserts] = $this->rows->split($rows, $existingById, 'Scene element is unknown');
        foreach ($updates as $id => $element) {
            $this->elementRepository->update($id, $this->elementFields($element, $boardMap));
        }

        foreach ($inserts as $element) {
            $fields = $this->elementFields($element, $boardMap);
            $this->elementRepository->add(
                $fields['board_id'],
                $fields['kind'],
                $fields['shape'],
                $fields['props'],
                $fields['sort_order'],
                $fields['target_board_id'],
                $fields['asset_file_id'],
            );
        }
    }

    /**
     * Поля строки элемента.
     *
     * @param array<string, mixed> $element Элемент снимка.
     * @param array<int, int> $boardMap Id поля → реальный id.
     *
     * @return array<string, mixed> Поля.
     */
    private function elementFields(array $element, array $boardMap): array
    {
        $targetBoardId = $element['targetBoardId'];

        return [
            'board_id' => $boardMap[$element['boardId']],
            'kind' => $element['kind'],
            'shape' => $element['shape'],
            'props' => $element['props'],
            'sort_order' => $element['sortOrder'],
            'target_board_id' => $targetBoardId === null ? null : $boardMap[$targetBoardId],
            'asset_file_id' => $element['assetFileId'],
        ];
    }

    /**
     * Индекс элементов.
     *
     * @param array<int, SceneElementRecord> $elements Элементы.
     *
     * @return array<int, true> Id → true.
     */
    private function elementIndex(array $elements): array
    {
        $index = [];
        foreach ($elements as $elementRecord) {
            $index[$elementRecord->getId()] = true;
        }

        return $index;
    }

    /**
     * Удаляет вложения, которых нет в снимке.
     *
     * @param array<int, SceneEnclosureRecord> $existing Существующие вложения.
     * @param array<int, array<string, mixed>> $rows Вложения снимка.
     *
     * @return void
     */
    private function deleteStaleEnclosures(array $existing, array $rows): void
    {
        $existingById = $this->enclosureIndex($existing);
        [$updates] = $this->rows->split($rows, $existingById, 'Scene enclosure is unknown');
        foreach ($this->rows->stale($existingById, $updates) as $id) {
            $this->enclosureRepository->delete($id);
        }
    }

    /**
     * Пишет вложения.
     *
     * @param array<int, array<string, mixed>> $rows Вложения снимка.
     * @param array<int, SceneEnclosureRecord> $existing Существующие вложения.
     * @param array<int, int> $boardMap Id поля → реальный id.
     *
     * @return void
     */
    private function writeEnclosures(array $rows, array $existing, array $boardMap): void
    {
        $existingById = $this->enclosureIndex($existing);
        [$updates, $inserts] = $this->rows->split($rows, $existingById, 'Scene enclosure is unknown');
        foreach ($updates as $id => $enclosure) {
            $this->enclosureRepository->update($id, $this->enclosureFields($enclosure, $boardMap));
        }

        foreach ($inserts as $enclosure) {
            $fields = $this->enclosureFields($enclosure, $boardMap);
            $this->enclosureRepository->add(
                $fields['parent_board_id'],
                $fields['child_board_id'],
                $fields['footprint'],
                $fields['sort_order'],
            );
        }
    }

    /**
     * Поля строки вложения.
     *
     * @param array<string, mixed> $enclosure Вложение снимка.
     * @param array<int, int> $boardMap Id поля → реальный id.
     *
     * @return array<string, mixed> Поля.
     */
    private function enclosureFields(array $enclosure, array $boardMap): array
    {
        return [
            'parent_board_id' => $boardMap[$enclosure['parentBoardId']],
            'child_board_id' => $boardMap[$enclosure['childBoardId']],
            'footprint' => $enclosure['footprint'],
            'sort_order' => $enclosure['sortOrder'],
        ];
    }

    /**
     * Индекс вложений.
     *
     * @param array<int, SceneEnclosureRecord> $enclosures Вложения.
     *
     * @return array<int, true> Id → true.
     */
    private function enclosureIndex(array $enclosures): array
    {
        $index = [];
        foreach ($enclosures as $enclosureRecord) {
            $index[$enclosureRecord->getId()] = true;
        }

        return $index;
    }
}
