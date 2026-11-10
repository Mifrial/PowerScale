<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto;

/**
 * Страница локаций.
 */
final class ScenePage
{
    /**
     * Создаёт страницу.
     *
     * @param array<int, SceneRecord> $items Локации.
     * @param int $total Всего.
     *
     * @return void
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
    ) {
    }

    /**
     * Локации страницы.
     *
     * @return array<int, SceneRecord> Локации.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Всего локаций.
     *
     * @return int Всего.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
