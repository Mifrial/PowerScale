<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;

/**
 * Проверка полей, элементов и вложений снимка локации.
 */
final class SceneSaveValidator
{
    /**
     * Создаёт валидатор.
     *
     * @param SceneSaveFields $fields Скалярные поля.
     * @param SceneSaveFileResolver $files Файлы подложек.
     * @param SceneElementValidator $elementValidator Элементы.
     * @param SceneBoardBounds $bounds Границы полей.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneSaveFields $fields,
        private readonly SceneSaveFileResolver $files,
        private readonly SceneElementValidator $elementValidator,
        private readonly SceneBoardBounds $bounds,
    ) {
    }

    /**
     * Проверяет снимок.
     *
     * @param array<string|int, mixed> $payload Тело `scene`.
     *
     * @return array<string, mixed> Нормализованные данные.
     *
     * @throws SceneInvalidException Если снимок недопустим.
     */
    public function validate(array $payload): array
    {
        $scene = $this->sceneFields($payload);
        $boards = $this->boards($payload, $scene['resolution']);
        $boundsById = $this->bounds->byId($boards);

        return $scene + [
            'boards' => $boards,
            'elements' => $this->elementValidator->validate(
                $this->fields->rows($payload, 'elements', 'scene.elements'),
                $boards,
            ),
            'enclosures' => $this->enclosures($payload, $boundsById),
        ];
    }

    /**
     * Поля локации.
     *
     * @param array<string|int, mixed> $payload Тело.
     *
     * @return array<string, mixed> Поля.
     *
     * @throws SceneInvalidException Если поля недопустимы.
     */
    private function sceneFields(array $payload): array
    {
        $name = trim($this->fields->stringValue($payload, 'name', 'scene.name'));
        if ($name === '' || mb_strlen($name) > 255) {
            throw new SceneInvalidException('scene.name is invalid');
        }

        return [
            'name' => $name,
            'description' => trim($this->fields->stringValue($payload, 'description', 'scene.description')),
            'resolution' => $this->fields->intOrDefault($payload, 'resolution', 'scene.resolution', 64, 1, 1024),
            'schemaVersion' => $this->fields->intOrDefault(
                $payload,
                'schemaVersion',
                'scene.schemaVersion',
                1,
                1,
                1000,
            ),
        ];
    }

    /**
     * Поля локации.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param int $resolution Разрешение локации.
     *
     * @return array<int, array<string, mixed>> Поля.
     *
     * @throws SceneInvalidException Если поля недопустимы.
     */
    private function boards(array $payload, int $resolution): array
    {
        $rows = $this->fields->rows($payload, 'boards', 'scene.boards');
        if ($rows === []) {
            throw new SceneInvalidException('scene.boards is empty');
        }

        $maxBounds = $resolution * 200;
        $names = [];
        $boards = [];
        foreach ($rows as $index => $row) {
            $board = $this->boardRow($row, $index, $resolution, $maxBounds);
            if (isset($names[$board['name']])) {
                throw new SceneInvalidException('scene.boards have a duplicate name');
            }

            $names[$board['name']] = true;
            $boards[] = $board;
        }

        return $boards;
    }

    /**
     * Одно поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param int $resolution Разрешение локации.
     * @param int $maxBounds Максимальная граница.
     *
     * @return array<string, mixed> Поле.
     *
     * @throws SceneInvalidException Если поле недопустимо.
     */
    private function boardRow(array $row, int $index, int $resolution, int $maxBounds): array
    {
        $path = 'boards[' . $index . ']';
        $name = trim($this->fields->stringValue($row, 'name', $path . '.name'));
        if ($name === '' || mb_strlen($name) > 255) {
            throw new SceneInvalidException($path . '.name is invalid');
        }

        $filePublicId = $this->fields->optionalStringValue($row, 'filePublicId', $path . '.filePublicId');
        $fileRecord = $this->files->resolve($filePublicId, $path . '.filePublicId');
        $boundWidth = $this->fields->intValue($row, 'boundWidth', $path . '.boundWidth', 1, $maxBounds);
        $boundHeight = $this->fields->intValue($row, 'boundHeight', $path . '.boundHeight', 1, $maxBounds);
        $defaultCell = max(1, intdiv($resolution, 4));
        $maxCell = max($boundWidth, $boundHeight);

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'name' => $name,
            'sortOrder' => $this->fields->intOrDefault($row, 'sortOrder', $path . '.sortOrder', 0, 0, 1000000),
            'baseFileId' => $fileRecord?->getId(),
            'baseFileWidth' => $fileRecord?->getWidth(),
            'baseFileHeight' => $fileRecord?->getHeight(),
            'boundWidth' => $boundWidth,
            'boundHeight' => $boundHeight,
            'occupancyCell' => $this->fields->intOrDefault(
                $row,
                'occupancyCell',
                $path . '.occupancyCell',
                $defaultCell,
                1,
                $maxCell,
            ),
            'zQuantum' => $this->fields->intOrDefault(
                $row,
                'zQuantum',
                $path . '.zQuantum',
                $defaultCell,
                1,
                $maxCell,
            ),
            'gridStep' => $this->fields->intOrDefault($row, 'gridStep', $path . '.gridStep', 0, 0, 1000000),
        ];
    }

    /**
     * Вложения полей.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param array<int, array{width: int, height: int}> $boundsById Границы полей.
     *
     * @return array<int, array<string, mixed>> Вложения.
     *
     * @throws SceneInvalidException Если вложение недопустимо.
     */
    private function enclosures(array $payload, array $boundsById): array
    {
        $rows = $this->fields->rows($payload, 'enclosures', 'scene.enclosures');
        $childParents = [];
        $enclosures = [];
        foreach ($rows as $index => $row) {
            $enclosure = $this->enclosureRow($row, $index, $boundsById, $childParents);
            $childParents[$enclosure['childBoardId']] = $enclosure['parentBoardId'];
            $enclosures[] = $enclosure;
        }

        $this->assertNoCycles($childParents);

        return $enclosures;
    }

    /**
     * Одно вложение.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, array{width: int, height: int}> $boundsById Границы полей.
     * @param array<int, int> $childParents Уже прочитанные связи.
     *
     * @return array<string, mixed> Вложение.
     *
     * @throws SceneInvalidException Если вложение недопустимо.
     */
    private function enclosureRow(array $row, int $index, array $boundsById, array $childParents): array
    {
        $path = 'enclosures[' . $index . ']';
        [$parentBoardId, $childBoardId] = $this->enclosureBoards($row, $path, $boundsById, $childParents);

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'parentBoardId' => $parentBoardId,
            'childBoardId' => $childBoardId,
            'footprint' => $this->elementValidator->validateShape(
                $this->fields->arrayValue($row, 'footprint', $path . '.footprint'),
                $boundsById[$parentBoardId],
                $path . '.footprint',
                ['rect', 'polygon', 'wall'],
            ),
            'elevation' => $this->fields->intOrDefault(
                $row,
                'elevation',
                $path . '.elevation',
                0,
                -100000,
                100000,
            ),
            'label' => $this->enclosureLabel($row, $path),
            'sortOrder' => $this->fields->intOrDefault($row, 'sortOrder', $path . '.sortOrder', 0, 0, 1000000),
        ];
    }

    /**
     * Идентификаторы и проверка связей вложения.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param array<int, array{width: int, height: int}> $boundsById Границы полей.
     * @param array<int, int> $childParents Уже прочитанные связи.
     *
     * @return array{0: int, 1: int} Родитель и ребёнок.
     *
     * @throws SceneInvalidException Если ссылки недопустимы.
     */
    private function enclosureBoards(array $row, string $path, array $boundsById, array $childParents): array
    {
        $parentBoardId = $this->fields->identifier($row, 'parentBoardId', $path . '.parentBoardId');
        $childBoardId = $this->fields->identifier($row, 'childBoardId', $path . '.childBoardId');
        if (!isset($boundsById[$parentBoardId]) || !isset($boundsById[$childBoardId])) {
            throw new SceneInvalidException($path . ' references an unknown board');
        }

        if ($parentBoardId === $childBoardId) {
            throw new SceneInvalidException($path . ' contains itself');
        }

        if (isset($childParents[$childBoardId])) {
            throw new SceneInvalidException($path . '.childBoardId has a second parent');
        }

        return [$parentBoardId, $childBoardId];
    }

    /**
     * Имя строения или этажа у вложения.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     *
     * @return string|null Подпись или null.
     *
     * @throws SceneInvalidException Если строка слишком длинная.
     */
    private function enclosureLabel(array $row, string $path): ?string
    {
        $label = $this->fields->optionalStringValue($row, 'label', $path . '.label');
        if ($label === null) {
            return null;
        }

        $label = trim($label);
        if (mb_strlen($label) > 255) {
            throw new SceneInvalidException($path . '.label is too long');
        }

        return $label === '' ? null : $label;
    }

    /**
     * Запрещает циклы вложений.
     *
     * @param array<int, int> $childParents Ребёнок → родитель.
     *
     * @return void
     *
     * @throws SceneInvalidException Если найден цикл.
     */
    private function assertNoCycles(array $childParents): void
    {
        foreach (array_keys($childParents) as $childBoardId) {
            $seen = [];
            $current = $childBoardId;
            while (isset($childParents[$current])) {
                if (isset($seen[$current])) {
                    throw new SceneInvalidException('scene.enclosures contain a cycle');
                }

                $seen[$current] = true;
                $current = $childParents[$current];
            }
        }
    }
}
