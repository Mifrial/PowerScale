<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Exception\ModuleManager\ModuleManagerException;
use Mifrial\Core\Kernel\Exception\ModuleManager\ModuleNotFoundException;
use Mifrial\Core\Kernel\Service\ModuleManager;
use PHPUnit\Framework\TestCase;

final class ModuleManagerTest extends TestCase
{
    /**
     * Проверяет возврат false для отсутствующего модуля.
     *
     * @return void
     */
    public function testIncludeMissingReturnsFalse(): void
    {
        $manager = new ModuleManager(dirname(__DIR__, 4) . '/modules');

        self::assertFalse($manager->includeModule('Core', 'NoSuchModule'));
    }

    /**
     * Проверяет исключение при подключении отсутствующего модуля.
     *
     * @return void
     *
     * @throws ModuleManagerException Неожиданное исключение менеджера.
     */
    public function testRequireMissingThrows(): void
    {
        $manager = new ModuleManager(dirname(__DIR__, 4) . '/modules');

        try {
            $manager->requireModule('Core', 'NoSuchModule');
            self::fail('Expected ModuleNotFoundException');
        } catch (ModuleManagerException $e) {
            self::assertInstanceOf(ModuleNotFoundException::class, $e);
            self::assertSame('Core/NoSuchModule', $e->getModuleKey());
        }
    }
}
