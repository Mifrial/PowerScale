<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Closure;

/**
 * Одна единица работы на соединении вызывающего.
 */
interface ITransactionRunner
{
    /**
     * Выполняет работу атомарно и возвращает её результат.
     *
     * @param Closure $work Работа.
     *
     * @return mixed Результат $work.
     */
    public function run(Closure $work): mixed;
}
