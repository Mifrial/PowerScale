<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Страница строк getList.
 */
final class ListResult
{
    /**
     * Создаёт результат списка.
     *
     * @param array<int, array<string, mixed>> $rows Гидратированные строки.
     * @param int|null $total COUNT или null, если не просили.
     *
     * @return void
     */
    public function __construct(
        private readonly array $rows,
        private readonly ?int $total,
    ) {
    }

    /**
     * Возвращает строки страницы.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Возвращает полное число совпадений.
     *
     * @return int|null COUNT или null.
     */
    public function total(): ?int
    {
        return $this->total;
    }
}
