<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

/**
 * Один data-шаг установки модуля. Идемпотентен при повторном CLI.
 */
interface ISetupStep
{
    /**
     * Возвращает стабильный id шага для реестра в этой базе.
     *
     * @return string Ключ вида Group/Name:имя.
     */
    public function id(): string;

    /**
     * Выполняет шаг. Вызывается, только если id ещё нет в реестре.
     *
     * @return void
     */
    public function run(): void;
}
