<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Container\KernelContainer;
use Mifrial\Core\Kernel\Container\ModuleContainer;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Service\LocalConfigLoader;
use Mifrial\Core\Kernel\Service\ModuleContainerFactory;
use Mifrial\Core\Kernel\Service\RuntimeConfig;
use Mifrial\Core\Kernel\Service\ServiceLocator;
use PHPUnit\Framework\TestCase;

final class RuntimeConfigTest extends TestCase
{
    /**
     * Проверяет чтение отсутствующего файла.
     *
     * @return void
     */
    public function testLoaderMissingFileDefaultsDebug(): void
    {
        $config = (new LocalConfigLoader())->loadFromFile(sys_get_temp_dir() . '/mifrial-missing-local.php');

        self::assertFalse($config['debug']);
        self::assertArrayNotHasKey('db', $config);
    }

    /**
     * Проверяет, что не-массив конфига даёт только debug.
     *
     * @return void
     */
    public function testLoaderRejectsNonArrayFile(): void
    {
        $configPath = $this->writeTempConfig('<?php return "nope";');
        $config = (new LocalConfigLoader())->loadFromFile($configPath);

        self::assertSame(['debug' => false], $config);
    }

    /**
     * Проверяет снимок без ключа db.
     *
     * @return void
     */
    public function testRuntimeConfigWithoutDatabase(): void
    {
        $runtimeConfig = RuntimeConfig::fromLocal(['debug' => false]);

        self::assertFalse($runtimeConfig->debug());
        self::assertSame('file', $runtimeConfig->cacheDriver());
        self::assertSame('', $runtimeConfig->cache()->path());
        self::assertSame('', $runtimeConfig->database()->host());
        self::assertFalse($runtimeConfig->database()->hasLegacyDsn());
    }

    /**
     * Проверяет разбор db и запрещённого dsn.
     *
     * @return void
     */
    public function testDatabaseSettingsFromConfig(): void
    {
        $settings = DatabaseSettings::fromConfig([
            'host' => '127.0.0.1',
            'port' => '3307',
            'database' => 'powerscale',
            'username' => 'powerscale',
            'password' => 'secret',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'timezone' => '+00:00',
            'dsn' => 'mysql:host=127.0.0.1',
        ]);

        self::assertSame('127.0.0.1', $settings->host());
        self::assertSame(3307, $settings->port());
        self::assertSame(
            [
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'timezone' => '+00:00',
            ],
            $settings->mysqlLocale(),
        );
        self::assertTrue($settings->hasLegacyDsn());
        self::assertSame('secret', $settings->password());
    }

    /**
     * Проверяет, что extra-порты попадают только в Kernel.
     *
     * @return void
     */
    public function testKernelExtraPortsDoNotLeakToOtherContainers(): void
    {
        $runtimeConfig = RuntimeConfig::fromLocal(['debug' => true]);
        $factory = new ModuleContainerFactory([
            IRuntimeConfig::class => static fn (): IRuntimeConfig => $runtimeConfig,
        ]);
        $serviceLocator = new ServiceLocator();

        $moduleContainer = $factory->create(ModuleContainer::class, $serviceLocator, ['ports' => []]);
        try {
            $moduleContainer->get(IRuntimeConfig::class);
            self::fail('Extra Kernel port must not appear on a generic module container');
        } catch (KernelException $exception) {
            self::assertSame('UNKNOWN_PORT', $exception->getErrorCode());
        }

        $kernelContainer = $factory->create(KernelContainer::class, $serviceLocator, ['ports' => []]);
        $resolvedConfig = $kernelContainer->get(IRuntimeConfig::class);

        self::assertInstanceOf(IRuntimeConfig::class, $resolvedConfig);
        self::assertTrue($resolvedConfig->debug());
    }

    /**
     * Пишет временный PHP-конфиг.
     *
     * @param string $contents Содержимое файла.
     *
     * @return string Путь к файлу.
     */
    private function writeTempConfig(string $contents): string
    {
        $configPath = tempnam(sys_get_temp_dir(), 'mifrial-local-');
        self::assertNotFalse($configPath);
        file_put_contents($configPath, $contents);

        return $configPath;
    }
}
