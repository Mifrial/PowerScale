<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Service\ConfiguredLoggerResolver;
use Mifrial\Core\Kernel\Service\ModuleManager;
use Mifrial\Core\Kernel\Service\ServiceLocator;
use PHPUnit\Framework\TestCase;

final class ConfiguredLoggerResolverTest extends TestCase
{
    /**
     * Не строка — нет адаптера.
     *
     * @return void
     */
    public function testNonStringPortIsNull(): void
    {
        $resolver = new ConfiguredLoggerResolver();
        $manager = new ModuleManager(sys_get_temp_dir() . '/mifrial-logcfg-' . uniqid());

        self::assertNull($resolver->resolve(null, $manager, new ServiceLocator()));
        self::assertNull($resolver->resolve(['x'], $manager, new ServiceLocator()));
    }

    /**
     * Два модуля с одним ключом ports — нет адаптера.
     *
     * @return void
     */
    public function testDuplicatePortKeyIsNull(): void
    {
        $modulesRoot = sys_get_temp_dir() . '/mifrial-logdup-' . uniqid();
        mkdir($modulesRoot . '/Core/One', 0777, true);
        mkdir($modulesRoot . '/Core/Two', 0777, true);
        $config = '<?php return ["locator" => "L", "ports" => ["H" => static fn () => new stdClass()]];';
        file_put_contents($modulesRoot . '/Core/One/module.config.php', $config);
        file_put_contents($modulesRoot . '/Core/Two/module.config.php', $config);
        $manager = new ModuleManager($modulesRoot);
        $manager->loadCore();

        self::assertNull((new ConfiguredLoggerResolver())->resolve('H', $manager, new ServiceLocator()));
    }
}
