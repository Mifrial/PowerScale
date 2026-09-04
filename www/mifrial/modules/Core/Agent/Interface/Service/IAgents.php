<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Interface\Service;

/**
 * Расписание агентов и тик CLI.
 */
interface IAgents
{
    /**
     * Пишет строку агента, если кода ещё нет.
     *
     * @param string $code Стабильный ключ.
     * @param int $intervalSec Секунды ≥ 1.
     *
     * @return void
     */
    public function ensureAgent(string $code, int $intervalSec): void;

    /**
     * Вешает обработчик кода в этом процессе.
     *
     * @param string $code Ключ агента.
     * @param IAgentHandler $handler Обработчик донора.
     *
     * @return void
     */
    public function bindHandler(string $code, IAgentHandler $handler): void;

    /**
     * Вызывает due-агентов с обработчиком.
     *
     * @return void
     */
    public function tick(): void;
}
