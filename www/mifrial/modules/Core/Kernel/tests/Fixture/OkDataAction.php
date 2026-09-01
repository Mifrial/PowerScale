<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

final class OkDataAction implements IActionHandler
{
    /**
     * Возвращает данные успеха без конверта.
     *
     * @return array{ok: bool} Данные.
     */
    public function handle(): array
    {
        return ['ok' => true];
    }
}
