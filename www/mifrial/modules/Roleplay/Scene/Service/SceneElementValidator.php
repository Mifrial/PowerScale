<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;

/**
 * Проверка элементов поля: kind, shape и props.
 */
final class SceneElementValidator
{
    private const KINDS = ['support', 'obstacle', 'opening', 'terrain', 'decor', 'marker'];

    /**
     * @var array<string, array<int, string>>
     */
    private const SHAPES = [
        'support' => ['rect', 'circle', 'wall'],
        'obstacle' => ['rect', 'circle', 'wall'],
        'opening' => ['wall'],
        'terrain' => ['rect', 'circle', 'polygon'],
        'decor' => ['point', 'rect', 'circle'],
        'marker' => ['point'],
    ];

    private const SUPPORT_KINDS = ['ground', 'deck', 'roof', 'tabletop', 'bridge'];

    private const TERRAIN_KINDS = ['grass', 'dirt', 'stone', 'sand', 'mud', 'water', 'ice'];

    private const OPENING_KINDS = ['door', 'window', 'gate', 'stairs', 'teleport'];

    private const OPENING_STATES = ['open', 'closed'];

    private const MARKER_ROLES = ['player_start', 'npc_spawn', 'entrance', 'exit', 'loot', 'cover'];

    /**
     * Создаёт валидатор.
     *
     * @param SceneSaveFields $fields Скалярные поля.
     * @param SceneSaveFileResolver $files Файлы декора.
     * @param SceneBoardBounds $bounds Границы полей.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneSaveFields $fields,
        private readonly SceneSaveFileResolver $files,
        private readonly SceneBoardBounds $bounds,
    ) {
    }

    /**
     * Проверяет элементы снимка.
     *
     * @param array<int, array<string|int, mixed>> $rows Элементы.
     * @param array<int, array<string, mixed>> $boards Нормализованные поля.
     *
     * @return array<int, array<string, mixed>> Элементы.
     *
     * @throws SceneInvalidException Если элемент недопустим.
     */
    public function validate(array $rows, array $boards): array
    {
        $boundsById = $this->bounds->byId($boards);
        $elements = [];
        foreach ($rows as $index => $row) {
            $elements[] = $this->elementRow($row, $index, $boundsById);
        }

        return $elements;
    }

    /**
     * Один элемент.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, array{width: int, height: int}> $boundsById Границы полей.
     *
     * @return array<string, mixed> Элемент.
     *
     * @throws SceneInvalidException Если элемент недопустим.
     */
    private function elementRow(array $row, int $index, array $boundsById): array
    {
        $path = 'elements[' . $index . ']';
        $boardId = $this->fields->identifier($row, 'boardId', $path . '.boardId');
        if (!isset($boundsById[$boardId])) {
            throw new SceneInvalidException($path . '.boardId is unknown');
        }

        $kind = $this->fields->stringValue($row, 'kind', $path . '.kind');
        if (!in_array($kind, self::KINDS, true)) {
            throw new SceneInvalidException($path . '.kind is unknown');
        }

        $shape = $this->validateShape(
            $this->fields->arrayValue($row, 'shape', $path . '.shape'),
            $boundsById[$boardId],
            $path . '.shape',
            self::SHAPES[$kind],
        );
        $props = $this->props(
            $kind,
            $this->fields->optionalArrayValue($row, 'props', $path . '.props'),
            $path . '.props',
        );

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'boardId' => $boardId,
            'kind' => $kind,
            'shape' => $shape,
            'props' => $props['props'],
            'sortOrder' => $this->fields->intValue($row, 'sortOrder', $path . '.sortOrder', 0, 1000000),
            'targetBoardId' => $this->targetBoardId($row, $kind, $boardId, $boundsById, $path),
            'assetFileId' => $props['assetFileId'],
        ];
    }

    /**
     * Целевое поле проёма.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $kind Тип элемента.
     * @param int $boardId Поле элемента.
     * @param array<int, array{width: int, height: int}> $boundsById Границы полей.
     * @param string $path Путь.
     *
     * @return int|null Id или null.
     *
     * @throws SceneInvalidException Если ссылка недопустима.
     */
    private function targetBoardId(array $row, string $kind, int $boardId, array $boundsById, string $path): ?int
    {
        $targetBoardId = $this->fields->optionalIdentifier($row, 'targetBoardId', $path . '.targetBoardId');
        if ($targetBoardId === null) {
            return null;
        }

        if ($kind !== 'opening') {
            throw new SceneInvalidException($path . '.targetBoardId is only allowed for opening');
        }

        if ($targetBoardId === $boardId || !isset($boundsById[$targetBoardId])) {
            throw new SceneInvalidException($path . '.targetBoardId is invalid');
        }

        return $targetBoardId;
    }

    /**
     * Проверяет shape по разрешённым типам.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array{width: int, height: int} $bounds Границы поля.
     * @param string $path Путь.
     * @param array<int, string> $allowedTypes Разрешённые типы.
     *
     * @return array<string, mixed> Нормализованная геометрия.
     *
     * @throws SceneInvalidException Если геометрия недопустима.
     */
    public function validateShape(array $shape, array $bounds, string $path, array $allowedTypes): array
    {
        $type = $this->fields->stringValue($shape, 'type', $path . '.type');
        if (!in_array($type, $allowedTypes, true)) {
            throw new SceneInvalidException($path . '.type is not allowed');
        }

        return match ($type) {
            'rect' => $this->rectShape($shape, $bounds, $path),
            'circle' => $this->circleShape($shape, $bounds, $path),
            'wall' => $this->wallShape($shape, $bounds, $path),
            'polygon' => $this->polygonShape($shape, $bounds, $path),
            'point' => $this->pointShape($shape, $bounds, $path),
            default => throw new SceneInvalidException($path . '.type is not allowed'),
        };
    }

    /**
     * Прямоугольник.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array{width: int, height: int} $bounds Границы.
     * @param string $path Путь.
     *
     * @return array<string, mixed> Геометрия.
     *
     * @throws SceneInvalidException Если геометрия недопустима.
     */
    private function rectShape(array $shape, array $bounds, string $path): array
    {
        $x = $this->fields->intValue($shape, 'x', $path . '.x', 0, $bounds['width']);
        $y = $this->fields->intValue($shape, 'y', $path . '.y', 0, $bounds['height']);
        $width = $this->fields->intValue($shape, 'w', $path . '.w', 1, $bounds['width']);
        $height = $this->fields->intValue($shape, 'h', $path . '.h', 1, $bounds['height']);
        if ($x + $width > $bounds['width'] || $y + $height > $bounds['height']) {
            throw new SceneInvalidException($path . ' is outside the board');
        }

        return ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $width, 'h' => $height];
    }

    /**
     * Круг.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array{width: int, height: int} $bounds Границы.
     * @param string $path Путь.
     *
     * @return array<string, mixed> Геометрия.
     *
     * @throws SceneInvalidException Если геометрия недопустима.
     */
    private function circleShape(array $shape, array $bounds, string $path): array
    {
        $centerX = $this->fields->intValue($shape, 'cx', $path . '.cx', 0, $bounds['width']);
        $centerY = $this->fields->intValue($shape, 'cy', $path . '.cy', 0, $bounds['height']);
        $radius = $this->fields->intValue($shape, 'r', $path . '.r', 1, min($bounds['width'], $bounds['height']));
        if ($centerX - $radius < 0 || $centerY - $radius < 0) {
            throw new SceneInvalidException($path . ' is outside the board');
        }

        if ($centerX + $radius > $bounds['width'] || $centerY + $radius > $bounds['height']) {
            throw new SceneInvalidException($path . ' is outside the board');
        }

        return ['type' => 'circle', 'cx' => $centerX, 'cy' => $centerY, 'r' => $radius];
    }

    /**
     * Стена-полоса.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array{width: int, height: int} $bounds Границы.
     * @param string $path Путь.
     *
     * @return array<string, mixed> Геометрия.
     *
     * @throws SceneInvalidException Если геометрия недопустима.
     */
    private function wallShape(array $shape, array $bounds, string $path): array
    {
        $points = $this->points($shape, $path, 2, $bounds);
        $width = $this->fields->intValue($shape, 'width', $path . '.width', 1, 100000);

        return ['type' => 'wall', 'points' => $points, 'width' => $width];
    }

    /**
     * Полигон.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array{width: int, height: int} $bounds Границы.
     * @param string $path Путь.
     *
     * @return array<string, mixed> Геометрия.
     *
     * @throws SceneInvalidException Если геометрия недопустима.
     */
    private function polygonShape(array $shape, array $bounds, string $path): array
    {
        return ['type' => 'polygon', 'points' => $this->points($shape, $path, 3, $bounds)];
    }

    /**
     * Точка.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array{width: int, height: int} $bounds Границы.
     * @param string $path Путь.
     *
     * @return array<string, mixed> Геометрия.
     *
     * @throws SceneInvalidException Если геометрия недопустима.
     */
    private function pointShape(array $shape, array $bounds, string $path): array
    {
        return [
            'type' => 'point',
            'x' => $this->fields->intValue($shape, 'x', $path . '.x', 0, $bounds['width']),
            'y' => $this->fields->intValue($shape, 'y', $path . '.y', 0, $bounds['height']),
        ];
    }

    /**
     * Список точек внутри поля.
     *
     * @param array<string|int, mixed> $shape Геометрия.
     * @param string $path Путь.
     * @param int $min Минимум точек.
     * @param array{width: int, height: int} $bounds Границы.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     *
     * @throws SceneInvalidException Если точки недопустимы.
     */
    private function points(array $shape, string $path, int $min, array $bounds): array
    {
        $points = $shape['points'] ?? null;
        if (!is_array($points) || !array_is_list($points) || count($points) < $min) {
            throw new SceneInvalidException($path . '.points is invalid');
        }

        $normalized = [];
        foreach ($points as $index => $point) {
            if (!is_array($point) || !array_is_list($point) || count($point) !== 2) {
                throw new SceneInvalidException($path . '.points[' . $index . '] is invalid');
            }

            $x = $this->pointInt($point[0], $path . '.points[' . $index . '][0]');
            $y = $this->pointInt($point[1], $path . '.points[' . $index . '][1]');
            if ($x > $bounds['width'] || $y > $bounds['height']) {
                throw new SceneInvalidException($path . '.points[' . $index . '] is outside the board');
            }

            $normalized[] = [$x, $y];
        }

        return $normalized;
    }

    /**
     * Координата точки.
     *
     * @param mixed $value Значение.
     * @param string $path Путь.
     *
     * @return int Координата.
     *
     * @throws SceneInvalidException Если значение неверно.
     */
    private function pointInt(mixed $value, string $path): int
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 0) {
            throw new SceneInvalidException($path . ' is not an integer');
        }

        return $value;
    }

    /**
     * Свойства элемента по типу.
     *
     * @param string $kind Тип.
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства и ассет.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function props(string $kind, array $props, string $path): array
    {
        return match ($kind) {
            'support' => $this->supportProps($props, $path),
            'obstacle' => $this->obstacleProps($props, $path),
            'opening' => $this->openingProps($props, $path),
            'terrain' => $this->terrainProps($props, $path),
            'decor' => $this->decorProps($props, $path),
            'marker' => $this->markerProps($props, $path),
            default => throw new SceneInvalidException($path . ' kind is not allowed'),
        };
    }

    /**
     * Свойства опоры.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function supportProps(array $props, string $path): array
    {
        $supportKind = $this->stringOrDefault($props, 'kind', $path . '.kind', 'ground');
        if (!in_array($supportKind, self::SUPPORT_KINDS, true)) {
            throw new SceneInvalidException($path . '.kind is unknown');
        }

        return [
            'props' => [
                'kind' => $supportKind,
                'height' => $this->intOrDefault($props, 'height', $path . '.height', 0, 0, 100000),
                'elevation' => $this->intOrDefault($props, 'elevation', $path . '.elevation', 0, 0, 100000),
            ],
            'assetFileId' => null,
        ];
    }

    /**
     * Свойства препятствия.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function obstacleProps(array $props, string $path): array
    {
        return [
            'props' => [
                'blocksMovement' => $this->boolOrDefault($props, 'blocksMovement', $path . '.blocksMovement', true),
                'blocksVision' => $this->boolOrDefault($props, 'blocksVision', $path . '.blocksVision', true),
                'height' => $this->intOrDefault($props, 'height', $path . '.height', 0, 0, 100000),
            ],
            'assetFileId' => null,
        ];
    }

    /**
     * Свойства проёма.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function openingProps(array $props, string $path): array
    {
        $openingKind = $this->stringOrDefault($props, 'kind', $path . '.kind', 'door');
        if (!in_array($openingKind, self::OPENING_KINDS, true)) {
            throw new SceneInvalidException($path . '.kind is unknown');
        }

        $state = $this->stringOrDefault($props, 'state', $path . '.state', 'closed');
        if (!in_array($state, self::OPENING_STATES, true)) {
            throw new SceneInvalidException($path . '.state is invalid');
        }

        return [
            'props' => [
                'kind' => $openingKind,
                'state' => $state,
                'blocksVision' => $this->boolOrDefault($props, 'blocksVision', $path . '.blocksVision', false),
            ],
            'assetFileId' => null,
        ];
    }

    /**
     * Свойства террейна.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function terrainProps(array $props, string $path): array
    {
        $terrainKind = $this->stringOrDefault($props, 'kind', $path . '.kind', 'grass');
        if (!in_array($terrainKind, self::TERRAIN_KINDS, true)) {
            throw new SceneInvalidException($path . '.kind is unknown');
        }

        $assetPublicId = $this->fields->optionalStringValue($props, 'assetPublicId', $path . '.assetPublicId');
        $fileRecord = $this->files->resolve($assetPublicId, $path . '.assetPublicId');

        return [
            'props' => [
                'kind' => $terrainKind,
                'label' => $this->label($props, $path . '.label'),
            ],
            'assetFileId' => $fileRecord?->getId(),
        ];
    }

    /**
     * Свойства декора.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function decorProps(array $props, string $path): array
    {
        $assetPublicId = $this->fields->optionalStringValue($props, 'assetPublicId', $path . '.assetPublicId');
        $fileRecord = $this->files->resolve($assetPublicId, $path . '.assetPublicId');

        return [
            'props' => [
                'rotation' => $this->intOrDefault($props, 'rotation', $path . '.rotation', 0, 0, 359),
                'scale' => $this->intOrDefault($props, 'scale', $path . '.scale', 100, 1, 1000),
                'label' => $this->label($props, $path . '.label'),
            ],
            'assetFileId' => $fileRecord?->getId(),
        ];
    }

    /**
     * Свойства маркера.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return array{props: array<string, mixed>, assetFileId: int|null} Свойства.
     *
     * @throws SceneInvalidException Если свойства недопустимы.
     */
    private function markerProps(array $props, string $path): array
    {
        $role = $this->stringOrDefault($props, 'role', $path . '.role', 'entrance');
        if (!in_array($role, self::MARKER_ROLES, true)) {
            throw new SceneInvalidException($path . '.role is unknown');
        }

        return [
            'props' => [
                'role' => $role,
                'label' => $this->label($props, $path . '.label'),
            ],
            'assetFileId' => null,
        ];
    }

    /**
     * Целое с умолчанием.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $default Умолчание.
     * @param int $min Минимум.
     * @param int|null $max Максимум.
     *
     * @return int Значение.
     *
     * @throws SceneInvalidException Если значение неверно.
     */
    private function intOrDefault(array $props, string $key, string $path, int $default, int $min, ?int $max): int
    {
        if (!array_key_exists($key, $props) || $props[$key] === null) {
            return $default;
        }

        return $this->fields->intValue($props, $key, $path, $min, $max);
    }

    /**
     * Строка с умолчанием.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param string $default Умолчание.
     *
     * @return string Значение.
     *
     * @throws SceneInvalidException Если значение не строка.
     */
    private function stringOrDefault(array $props, string $key, string $path, string $default): string
    {
        $value = $this->fields->optionalStringValue($props, $key, $path);

        return $value ?? $default;
    }

    /**
     * Bool с умолчанием.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param bool $default Умолчание.
     *
     * @return bool Значение.
     *
     * @throws SceneInvalidException Если значение не bool.
     */
    private function boolOrDefault(array $props, string $key, string $path, bool $default): bool
    {
        if (!array_key_exists($key, $props) || $props[$key] === null) {
            return $default;
        }

        return $this->fields->boolValue($props, $key, $path);
    }

    /**
     * Подпись элемента.
     *
     * @param array<string|int, mixed> $props Свойства.
     * @param string $path Путь.
     *
     * @return string Подпись.
     *
     * @throws SceneInvalidException Если подпись слишком длинная.
     */
    private function label(array $props, string $path): string
    {
        $label = $this->fields->optionalStringValue($props, 'label', $path) ?? '';
        if (mb_strlen($label) > 255) {
            throw new SceneInvalidException($path . ' is too long');
        }

        return $label;
    }
}
