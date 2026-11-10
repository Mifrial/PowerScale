<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Roleplay\Scene\Dto\SceneBoardRecord;
use Mifrial\Roleplay\Scene\Dto\SceneDetail;
use Mifrial\Roleplay\Scene\Dto\SceneElementRecord;
use Mifrial\Roleplay\Scene\Dto\SceneEnclosureRecord;
use Mifrial\Roleplay\Scene\Dto\SceneRecord;

/**
 * JSON-представление локаций, полей, элементов и вложений.
 */
final class SceneViewAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param IFiles $files Файлы подложек и декора.
     *
     * @return void
     */
    public function __construct(
        private readonly IFiles $files,
    ) {
    }

    /**
     * Краткая локация для списка.
     *
     * @param SceneRecord $sceneRecord Локация.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleSummary(SceneRecord $sceneRecord): array
    {
        return [
            'id' => $sceneRecord->getId(),
            'ownerId' => $sceneRecord->getOwnerId(),
            'name' => $sceneRecord->getName(),
            'description' => $sceneRecord->getDescription(),
            'resolution' => $sceneRecord->getResolution(),
            'schemaVersion' => $sceneRecord->getSchemaVersion(),
            'isArchived' => $sceneRecord->isArchived(),
            'version' => $sceneRecord->getVersion(),
            'updatedBy' => $sceneRecord->getUpdatedBy(),
            'createdAt' => $sceneRecord->getCreatedAt()->toUnix(),
            'updatedAt' => $sceneRecord->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Полная локация.
     *
     * @param SceneDetail $detail Снимок.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleDetail(SceneDetail $detail): array
    {
        $publicIds = $this->publicIds($detail);

        return $this->assembleSummary($detail->getScene()) + [
            'canEdit' => $detail->canEdit(),
            'boards' => $this->boardViews($detail, $publicIds),
            'elements' => $this->elementViews($detail, $publicIds),
            'enclosures' => $this->enclosureViews($detail),
        ];
    }

    /**
     * Поле в JSON.
     *
     * @param SceneBoardRecord $boardRecord Поле.
     * @param string|null $publicId Внешний id подложки.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleBoard(SceneBoardRecord $boardRecord, ?string $publicId): array
    {
        $baseFile = null;
        if ($publicId !== null) {
            $baseFile = [
                'publicId' => $publicId,
                'width' => $boardRecord->getBaseFileWidth(),
                'height' => $boardRecord->getBaseFileHeight(),
            ];
        }

        return [
            'id' => $boardRecord->getId(),
            'name' => $boardRecord->getName(),
            'sortOrder' => $boardRecord->getSortOrder(),
            'baseFile' => $baseFile,
            'boundWidth' => $boardRecord->getBoundWidth(),
            'boundHeight' => $boardRecord->getBoundHeight(),
            'occupancyCell' => $boardRecord->getOccupancyCell(),
            'zQuantum' => $boardRecord->getZQuantum(),
            'gridStep' => $boardRecord->getGridStep(),
        ];
    }

    /**
     * Элемент в JSON.
     *
     * @param SceneElementRecord $elementRecord Элемент.
     * @param string|null $assetPublicId Внешний id ассета.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleElement(SceneElementRecord $elementRecord, ?string $assetPublicId): array
    {
        return [
            'id' => $elementRecord->getId(),
            'boardId' => $elementRecord->getBoardId(),
            'kind' => $elementRecord->getKind(),
            'shape' => $this->object($elementRecord->getShape()),
            'props' => $this->object($elementRecord->getProps()),
            'sortOrder' => $elementRecord->getSortOrder(),
            'targetBoardId' => $elementRecord->getTargetBoardId(),
            'asset' => $assetPublicId === null ? null : ['publicId' => $assetPublicId],
        ];
    }

    /**
     * Вложение в JSON.
     *
     * @param SceneEnclosureRecord $enclosureRecord Вложение.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleEnclosure(SceneEnclosureRecord $enclosureRecord): array
    {
        return [
            'id' => $enclosureRecord->getId(),
            'parentBoardId' => $enclosureRecord->getParentBoardId(),
            'childBoardId' => $enclosureRecord->getChildBoardId(),
            'footprint' => $this->object($enclosureRecord->getFootprint()),
            'sortOrder' => $enclosureRecord->getSortOrder(),
        ];
    }

    /**
     * Поля в JSON.
     *
     * @param SceneDetail $detail Снимок.
     * @param array<int, string> $publicIds Id файла → public_id.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function boardViews(SceneDetail $detail, array $publicIds): array
    {
        $views = [];
        foreach ($detail->getBoards() as $boardRecord) {
            $fileId = $boardRecord->getBaseFileId();

            $views[] = $this->assembleBoard(
                $boardRecord,
                $fileId === null ? null : ($publicIds[$fileId] ?? null),
            );
        }

        return $views;
    }

    /**
     * Элементы в JSON.
     *
     * @param SceneDetail $detail Снимок.
     * @param array<int, string> $publicIds Id файла → public_id.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function elementViews(SceneDetail $detail, array $publicIds): array
    {
        $views = [];
        foreach ($detail->getElements() as $elementRecord) {
            $fileId = $elementRecord->getAssetFileId();

            $views[] = $this->assembleElement(
                $elementRecord,
                $fileId === null ? null : ($publicIds[$fileId] ?? null),
            );
        }

        return $views;
    }

    /**
     * Вложения в JSON.
     *
     * @param SceneDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function enclosureViews(SceneDetail $detail): array
    {
        $views = [];
        foreach ($detail->getEnclosures() as $enclosureRecord) {
            $views[] = $this->assembleEnclosure($enclosureRecord);
        }

        return $views;
    }

    /**
     * Внешние id файлов снимка.
     *
     * @param SceneDetail $detail Снимок.
     *
     * @return array<int, string> Id файла → public_id.
     */
    private function publicIds(SceneDetail $detail): array
    {
        $fileIds = [];
        foreach ($detail->getBoards() as $boardRecord) {
            $fileId = $boardRecord->getBaseFileId();
            if ($fileId !== null) {
                $fileIds[$fileId] = true;
            }
        }

        foreach ($detail->getElements() as $elementRecord) {
            $fileId = $elementRecord->getAssetFileId();
            if ($fileId !== null) {
                $fileIds[$fileId] = true;
            }
        }

        return $fileIds === [] ? [] : $this->files->activePublicIds(array_keys($fileIds));
    }

    /**
     * Пустой массив как JSON-объект.
     *
     * @param array<string|int, mixed> $value Массив.
     *
     * @return array<string|int, mixed>|object Массив или пустой объект.
     */
    private function object(array $value): array|object
    {
        return $value === [] ? (object) [] : $value;
    }
}
