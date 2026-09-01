<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

final class CodedFailAction implements IActionHandler
{
    /**
     * Сигналит доменную ошибку через исключение с кодом.
     *
     * @return never Исключение всегда прерывает выполнение.
     *
     * @throws ActionException Ожидаемая ошибка действия.
     */
    public function handle(): never
    {
        throw new ActionException('NOT_FOUND', 'Item not found');
    }
}
