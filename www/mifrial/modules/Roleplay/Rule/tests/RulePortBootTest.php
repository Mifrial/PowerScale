<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use PHPUnit\Framework\TestCase;

final class RulePortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад.
     *
     * @return void
     */
    public function testBootResolvesRules(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $rules = $application->getLocator()->get(IRuleContainer::class)->get(IRules::class);
        self::assertInstanceOf(IRules::class, $rules);
    }
}
