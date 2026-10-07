<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use PHPUnit\Framework\TestCase;

final class GamePortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад и четыре маршрута.
     *
     * @return void
     */
    public function testBootResolvesGames(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $games = $application->getLocator()->get(IGameContainer::class)->get(IGames::class);
        self::assertInstanceOf(IGames::class, $games);
        $routes = $application->getModuleManager()->getRoutes();
        self::assertArrayHasKey('game.create', $routes);
        self::assertArrayHasKey('game.get', $routes);
        self::assertArrayHasKey('game.getList', $routes);
        self::assertArrayHasKey('game.update', $routes);
        self::assertArrayHasKey('game.addMember', $routes);
        self::assertArrayHasKey('game.updateMember', $routes);
        self::assertArrayHasKey('game.removeMember', $routes);
        self::assertArrayHasKey('game.getMemberList', $routes);
        $config = require dirname(__DIR__) . '/module.config.php';
        self::assertSame([], $config['events']);
        $loadedGame = false;
        foreach ($application->getModuleManager()->getLoadedModules() as $loadedModule) {
            if ($loadedModule['group'] === 'Roleplay' && $loadedModule['name'] === 'Game') {
                $loadedGame = true;
                break;
            }
        }

        self::assertTrue($loadedGame);
    }
}
