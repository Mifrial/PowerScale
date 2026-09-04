<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Interface\Service;

/**
 * Обработчик тика агента. Класс донора, не PHP в БД.
 */
interface IAgentHandler
{
    /**
     * Выполняет работу агента.
     *
     * @return void
     */
    public function run(): void;
}
