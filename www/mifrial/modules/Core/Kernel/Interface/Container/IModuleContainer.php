<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Container;

/**
 * Контракт контейнера портов модуля.
 */
interface IModuleContainer
{
    /**
     * Возвращает порт модуля по интерфейсу или классу.
     *
     * @param string $port Интерфейс или класс порта.
     *
     * @return object Реализация порта.
     */
    public function get(string $port): object;

    /**
     * Подменяет порт до первого разрешения графа.
     *
     * @param string $port Интерфейс или класс порта.
     * @param object $implementation Подмена реализации.
     *
     * @return void
     */
    public function override(string $port, object $implementation): void;

    /**
     * Запрещает дальнейшие подмены портов.
     *
     * @return void
     */
    public function freeze(): void;
}
