<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use PHPUnit\Framework\TestCase;

final class CharacterPortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад; HTTP character.* нет.
     *
     * @return void
     */
    public function testBootResolvesCharactersWithoutHttpRoutes(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $characterContainer = $application->getLocator()->get(ICharacterContainer::class);
        $characters = $characterContainer->get(ICharacters::class);
        self::assertInstanceOf(ICharacters::class, $characters);
        $ruleSlices = $characterContainer->get(ICharacterRuleSlices::class);
        self::assertInstanceOf(ICharacterRuleSlices::class, $ruleSlices);
        $routes = $application->getModuleManager()->getRoutes();
        self::assertArrayNotHasKey('character.create', $routes);
        self::assertArrayNotHasKey('character.update', $routes);
        $loadedCharacter = false;
        foreach ($application->getModuleManager()->getLoadedModules() as $loadedModule) {
            if ($loadedModule['group'] === 'Roleplay' && $loadedModule['name'] === 'Character') {
                $loadedCharacter = true;
                break;
            }
        }

        self::assertTrue($loadedCharacter);
    }
}
