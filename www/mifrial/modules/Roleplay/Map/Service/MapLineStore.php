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
     * @param array<string, mixed> $fields Поля строки.
     *
     * @return int Id.
     */
    public function addLine(array $fields): int
    {
        return $this->lineRepository->add($fields);
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
