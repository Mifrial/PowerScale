<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Tests;

use Mifrial\Core\Agent\Interface\Service\IAgentHandler;
use RuntimeException;

/**
 * Падающий обработчик тика.
 */
final class ThrowingAgentHandler implements IAgentHandler
{
    /**
     * Бросает.
     *
     * @return void
     */
    public function run(): void
    {
        throw new RuntimeException('agent handler failed');
    }
}
