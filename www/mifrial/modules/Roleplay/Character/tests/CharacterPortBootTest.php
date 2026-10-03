<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use PHPUnit\Framework\TestCase;

final class CharacterPortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад и маршруты save.
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
        $osSteps = $characterContainer->get(ICharacterOsSteps::class);
        self::assertInstanceOf(ICharacterOsSteps::class, $osSteps);
        $sheets = $characterContainer->get(ICharacterSheets::class);
        self::assertInstanceOf(ICharacterSheets::class, $sheets);
        $routes = $application->getModuleManager()->getRoutes();
        self::assertArrayHasKey('character.create', $routes);
        self::assertArrayHasKey('character.update', $routes);
        self::assertArrayHasKey('character.validate', $routes);
        self::assertArrayHasKey('character.migrate', $routes);
        self::assertArrayHasKey('character.getList', $routes);
        self::assertArrayHasKey('character.get', $routes);
        self::assertArrayHasKey('character.updateVisibility', $routes);
        self::assertArrayHasKey('character.updateOwnerNotes', $routes);
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
