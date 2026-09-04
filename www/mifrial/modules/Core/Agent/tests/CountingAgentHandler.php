<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Tests;

use Mifrial\Core\Agent\Interface\Service\IAgentHandler;

/**
 * Счётчик вызовов для mysql-тика.
 */
final class CountingAgentHandler implements IAgentHandler
{
    public int $runs = 0;

    /**
     * Увеличивает счётчик.
     *
     * @return void
     */
    public function run(): void
    {
        $this->runs++;
    }
}
