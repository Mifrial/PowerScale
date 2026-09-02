<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Repository\SetupStepRepository;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\Setup\ModuleSetupRunner;
use Mifrial\Core\Kernel\Table\SetupStepTable;
use Mifrial\Core\Kernel\Tests\Fixture\ArrayModuleSetup;
use Mifrial\Core\Kernel\Tests\Fixture\RecordingSetupStep;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\PathChildTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathOwnerTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathParentTable;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use PHPUnit\Framework\TestCase;

final class ModuleSetupMysqlTest extends TestCase
{
    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectGateway();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Kernel setup tests');
        }

        $this->dropFixtureTables();
    }

    /**
     * Снимает фикстурные столы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropFixtureTables();
    }

    /**
     * Child в списке карт раньше parent: create не падает, parent есть.
     *
     * @return void
     */
    public function testChildListedFirstCreatesParentPhysics(): void
    {
        $log = [];
        $step = new RecordingSetupStep('Core/Kernel:test.flag', $log);
        $runner = new ModuleSetupRunner($this->gateway(), [
            [
                'key' => 'Core/ZChild',
                'setup' => new ArrayModuleSetup([PathChildTable::class], [$step]),
            ],
            [
                'key' => 'Core/AParent',
                'setup' => new ArrayModuleSetup([
                    PathParentTable::class,
                    PathOwnerTable::class,
                    SetupStepTable::class,
                ]),
            ],
        ]);
        $runner->run();
        $runner->run();

        self::assertTrue($this->gateway()->open(PathOwnerTable::class)->schema()->exists());
        self::assertTrue($this->gateway()->open(PathParentTable::class)->schema()->exists());
        self::assertTrue($this->gateway()->open(PathChildTable::class)->schema()->exists());
        self::assertSame(['Core/Kernel:test.flag'], $log);
        self::assertTrue(
            (new SetupStepRepository(
                $this->gateway()->open(SetupStepTable::class)->records(),
            ))->has('Core/Kernel:test.flag'),
        );
    }

    /**
     * Дубль id шагов — отказ.
     *
     * @return void
     */
    public function testDuplicateStepIdFails(): void
    {
        $log = [];
        $first = new RecordingSetupStep('dup.step', $log);
        $second = new RecordingSetupStep('dup.step', $log);
        $runner = new ModuleSetupRunner($this->gateway(), [
            [
                'key' => 'Core/A',
                'setup' => new ArrayModuleSetup([SetupStepTable::class], [$first]),
            ],
            [
                'key' => 'Core/B',
                'setup' => new ArrayModuleSetup([], [$second]),
            ],
        ]);

        try {
            $runner->run();
            self::fail('Expected SetupException');
        } catch (SetupException $exception) {
            self::assertSame('SETUP_STEP_DUPLICATE', $exception->getErrorCode());
        }
    }

    /**
     * Подключается к тестовой БД.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectGateway(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $databaseConnection->ping();
            $this->smartTableGateway = GatewayHarness::make($databaseConnection);

            return;
        }

        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $container = $application->getLocator()->get(ISmartTableContainer::class);
        $connection = $container->get(IDatabaseConnection::class);
        if (!$connection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $connection->ping();
        $resolvedGateway = $container->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $resolvedGateway);
        $this->smartTableGateway = $resolvedGateway;
    }

    /**
     * Возвращает шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }

    /**
     * Удаляет столы фикстур прогона.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        foreach (
            [
                PathChildTable::class,
                PathParentTable::class,
                PathOwnerTable::class,
                SetupStepTable::class,
            ] as $tableClass
        ) {
            $openedSchema = $this->smartTableGateway->open($tableClass)->schema();
            if ($openedSchema->exists()) {
                $openedSchema->deleteTable();
            }
        }
    }

    /**
     * Собирает настройки из переменных окружения теста.
     *
     * @param string $host Хост из MIFRIAL_TEST_DB_HOST.
     *
     * @return DatabaseSettings Настройки тестовой БД.
     */
    private function settingsFromEnv(string $host): DatabaseSettings
    {
        $port = getenv('MIFRIAL_TEST_DB_PORT');
        $collation = getenv('MIFRIAL_TEST_DB_COLLATION');
        $timezone = getenv('MIFRIAL_TEST_DB_TIMEZONE');

        return DatabaseSettings::fromFields(
            $host,
            is_string($port) && ctype_digit($port) ? (int) $port : 3306,
            (string) getenv('MIFRIAL_TEST_DB_DATABASE'),
            (string) getenv('MIFRIAL_TEST_DB_USERNAME'),
            (string) getenv('MIFRIAL_TEST_DB_PASSWORD'),
            (string) getenv('MIFRIAL_TEST_DB_CHARSET'),
            false,
            is_string($collation) && $collation !== '' ? $collation : 'utf8mb4_unicode_ci',
            is_string($timezone) && $timezone !== '' ? $timezone : '+00:00',
        );
    }
}
