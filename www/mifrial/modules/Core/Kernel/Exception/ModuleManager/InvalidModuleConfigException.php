<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Exception\ModuleManager;

use Throwable;

/**
 * Некорректная конфигурация модуля.
 */
final class InvalidModuleConfigException extends ModuleManagerException
{
    /**
     * Создаёт исключение о некорректной конфигурации модуля.
     *
     * @param string $group Группа модуля.
     * @param string $name Имя модуля.
     * @param string $configError Уточнение ошибки конфигурации.
     * @param Throwable|null $previous Предыдущее исключение.
     *
     * @return void
     */
    public function __construct(
        string $group,
        string $name,
        private readonly string $configError = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($group, $name, $previous);
    }

    /**
     * Формирует сообщение о некорректной конфигурации модуля.
     *
     * @param string $moduleKey Составной ключ модуля.
     *
     * @return string Текст исключения.
     */
    protected function buildMessage(string $moduleKey): string
    {
        $message = 'Invalid module.config.php: ' . $moduleKey;
        if ($this->configError === '') {
            return $message;
        }

        return $message . ': ' . $this->configError;
    }
}
