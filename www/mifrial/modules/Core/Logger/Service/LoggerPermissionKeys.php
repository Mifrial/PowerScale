<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

/**
 * Ключ просмотра технического журнала.
 */
final class LoggerPermissionKeys
{
    public const VIEW = 'logger.view';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
