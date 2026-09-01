<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Exception\ModuleManager;

/**
 * Модуль не найден на диске.
 */
final class ModuleNotFoundException extends ModuleManagerException
{
    /**
     * Формирует сообщение об отсутствии модуля.
     *
     * @param string $moduleKey Составной ключ модуля.
     *
     * @return string Текст исключения.
     */
    protected function buildMessage(string $moduleKey): string
    {
        return 'Module not found: ' . $moduleKey;
    }
}
