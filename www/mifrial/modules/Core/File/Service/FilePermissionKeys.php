<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

/**
 * Ключ просмотра файлов.
 */
final class FilePermissionKeys
{
    public const VIEW = 'file.view';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
