<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Exception\ModuleManager;

use Mifrial\Core\Kernel\Exception\MifrialException;
use Throwable;

/**
 * Базовая ошибка менеджера модулей.
 */
abstract class ModuleManagerException extends MifrialException
{
    /**
     * Создаёт исключение с координатами модуля.
     *
     * @param string $group    Группа модуля.
     * @param string $name     Имя модуля.
     * @param Throwable|null $previous Предыдущее исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly string $group,
        private readonly string $name,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->buildMessage($this->getModuleKey()), 0, $previous);
    }

    /**
     * Возвращает группу модуля.
     *
     * @return string Группа модуля.
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * Возвращает имя модуля.
     *
     * @return string Имя модуля.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает составной ключ модуля.
     *
     * @return string Ключ в формате group/name.
     */
    public function getModuleKey(): string
    {
        return $this->group . '/' . $this->name;
    }

    /**
     * Формирует текст исключения для модуля.
     *
     * @param string $moduleKey Составной ключ модуля.
     *
     * @return string Текст исключения.
     */
    abstract protected function buildMessage(string $moduleKey): string;
}
