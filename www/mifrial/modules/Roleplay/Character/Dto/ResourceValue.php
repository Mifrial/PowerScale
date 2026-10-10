<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Типизированное native-значение строки ресурса.
 */
interface ResourceValue
{
    /**
     * Возвращает native-значение JSON.
     *
     * @return int|array{base: int, size: int} Native value.
     */
    public function toNative(): int|array;
}
