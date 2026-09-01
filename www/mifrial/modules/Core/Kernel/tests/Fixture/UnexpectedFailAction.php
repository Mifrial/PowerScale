<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use RuntimeException;

final class UnexpectedFailAction implements IActionHandler
{
    /**
     * Бросает непредвиденную ошибку без кода действия.
     *
     * @return never Исключение всегда прерывает выполнение.
     *
     * @throws RuntimeException Непредвиденный сбой.
     */
    public function handle(): never
    {
        throw new RuntimeException('boom');
    }
}
