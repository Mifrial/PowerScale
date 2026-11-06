<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Repository\MapLineRepository;

/**
 * Ленты варианта: чтение и запись.
 */
final class MapLineStore
{
    /**
     * Создаёт стор.
     *
     * @param MapLineRepository $lineRepository Строки лент.
     *
     * @return void
     */
    public function __construct(
        private readonly MapLineRepository $lineRepository,
    ) {
    }

    /**
     * Ленты варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapLineRecord> Ленты.
     */
    public function findLines(int $variantId): array
    {
        return $this->lineRepository->findByVariant($variantId);
    }

    /**
     * Добавляет ленту.
     *
     * @param int $variantId Вариант.
     * @param string $color Цвет.
     * @param int $speed Проходимость.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, int> $widths Ширина по точкам.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param array<int, array{speed: int, color: string|null}> $segments Стили сегментов.
     * @param string $label Подпись.
     * @param bool $isPresent Присутствует.
     * @param int $sortOrder Порядок среди лент.
     *
     * @return int Id.
     */
    public function addLine(
        int $variantId,
        string $color,
        int $speed,
        array $points,
        array $widths,
        array $handles,
        array $segments,
        string $label,
        bool $isPresent,
        int $sortOrder,
    ): int {
        return $this->lineRepository->add(
            $variantId,
            $color,
            $speed,
            $points,
            $widths,
            $handles,
            $segments,
            $label,
            $isPresent,
            $sortOrder,
        );
    }

    /**
     * Пишет поля ленты.
     *
     * @param int $lineId Лента.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     */
    public function updateLine(int $lineId, array $fields): void
    {
        $this->lineRepository->update($lineId, $fields);
    }

    /**
     * Удаляет ленту.
     *
     * @param int $lineId Лента.
     *
     * @return void
     */
    public function deleteLine(int $lineId): void
    {
        $this->lineRepository->delete($lineId);
    }
}
