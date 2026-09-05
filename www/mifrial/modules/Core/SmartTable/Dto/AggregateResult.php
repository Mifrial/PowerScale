<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Ряды агрегата: ключи group и меры, без total.
 */
final class AggregateResult
{
    /**
     * Создаёт результат агрегата.
     *
     * @param array<int, array<string, mixed>> $rows Гидратированные группы.
     *
     * @return void
     */
    public function __construct(
        private readonly array $rows,
    ) {
    }

    /**
     * Возвращает ряды групп.
     *
     * @return array<int, array<string, mixed>> Ключи как в select.
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
