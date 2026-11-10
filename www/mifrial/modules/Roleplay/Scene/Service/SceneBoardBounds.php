<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

/**
 * Границы нормализованных полей снимка по id.
 */
final class SceneBoardBounds
{
    /**
     * Возвращает ширину и высоту полей.
     *
     * @param array<int, array<string, mixed>> $boards Нормализованные поля.
     *
     * @return array<int, array{width: int, height: int}> Границы.
     */
    public function byId(array $boards): array
    {
        $bounds = [];
        foreach ($boards as $board) {
            $bounds[(int) $board['id']] = [
                'width' => (int) $board['boundWidth'],
                'height' => (int) $board['boundHeight'],
            ];
        }

        return $bounds;
    }
}
