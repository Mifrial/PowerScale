<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use PHPUnit\Framework\TestCase;

final class KeywordPortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад.
     *
     * @return void
     */
    public function testBootResolvesKeywords(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $keywords = $application->getLocator()->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        self::assertFalse(method_exists(IKeywords::class, 'delete'));
        self::assertArrayHasKey(
            'keyword.getList',
            $application->getModuleManager()->getRoutes(),
        );
    }
}
