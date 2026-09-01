<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Обработчик ping доступности ядра.
 */
final class PingAction implements IActionHandler
{
    /**
     * Возвращает признак доступности ядра.
     *
     * @return array{ok: bool} Данные успешного ping.
     */
    public function handle(): array
    {
        return ['ok' => true];
    }
}
