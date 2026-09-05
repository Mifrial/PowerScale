<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Агрегат COUNT(*) в select, не колонка карты.
 */
final class CountField
{
    /**
     * Создаёт COUNT(*) с alias ряда.
     *
     * @param string $alias Имя ключа в результате.
     *
     * @return void
     *
     * @throws MapInvalidException Если alias не шаблон.
     */
    public function __construct(
        private readonly string $alias,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $alias) !== 1) {
            throw new MapInvalidException('Aggregate alias is invalid');
        }
    }

    /**
     * Возвращает alias ряда.
     *
     * @return string Ключ результата.
     */
    public function alias(): string
    {
        return $this->alias;
    }
}
