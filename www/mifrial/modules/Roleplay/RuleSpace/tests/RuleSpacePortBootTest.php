<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use PHPUnit\Framework\TestCase;

final class RuleSpacePortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад.
     *
     * @return void
     */
    public function testBootResolvesRuleSpaces(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        self::assertArrayHasKey('ruleSpace.getList', $application->getModuleManager()->getRoutes());
        $ruleSpaces = $application->getLocator()->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        self::assertInstanceOf(IRuleSpaces::class, $ruleSpaces);
    }
}
