<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ConditionalCas;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\ConditionalMultipleProbeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\CrudProbeTable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Conditional CAS одной строки.
 */
final class ConditionalUpdateMysqlTest extends TestCase
{
    private ?ISmartTableGateway $gateway = null;

    private ?IlluminateDatabaseConnection $databaseConnection = null;

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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for SmartTable tests');
        }

        $this->dropFixtureTables();
    }

    /**
     * Удаляет фикстуру.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropFixtureTables();
    }
    /**
     * Совпадение пишет payload и увеличивает счётчик. Несовпадение не пишет.
     *
     * @return void
     */
    public function testConditionalCasMatchAndMismatch(): void
    {
        $records = $this->gateway()->open(CrudProbeTable::class);
        $records->schema()->createTable();
        $rowId = $records->records()->add(['title' => 'a', 'age' => 1]);
        $updated = $records->conditionalRecords()->updateConditional(
            $rowId,
            new ConditionalCas('age', 1),
            ['title' => 'b'],
        );
        self::assertTrue($updated);
        $row = $records->records()->getById($rowId);
        self::assertSame('b', $row['title'] ?? null);
        self::assertSame(2, $row['age'] ?? null);
        self::assertFalse($records->conditionalRecords()->updateConditional(
            $rowId,
            new ConditionalCas('age', 1),
            ['title' => 'c'],
        ));
        $current = $records->conditionalRecords()->getCurrentById($rowId);
        self::assertSame('b', $current['title'] ?? null);
        self::assertSame(2, $current['age'] ?? null);
        $records->records()->update($rowId, ['title' => 'plain']);
        self::assertSame('plain', $records->records()->getById($rowId)['title'] ?? null);
        self::assertSame(2, $records->records()->getById($rowId)['age'] ?? null);
    }

    /**
     * CAS-поле нельзя передать в payload.
     *
     * @return void
     */
    public function testConditionalCasRejectsFieldInValues(): void
    {
        $records = $this->gateway()->open(CrudProbeTable::class);
        $records->schema()->createTable();
        $rowId = $records->records()->add(['title' => 'a', 'age' => 1]);
        $this->expectException(MapInvalidException::class);
        $records->conditionalRecords()->updateConditional(
            $rowId,
            new ConditionalCas('age', 1),
            ['age' => 9],
        );
    }

    /**
     * Две независимые connection принимают только один одинаковый expected version.
     *
     * @return void
     */
    public function testConditionalCasAcceptsOneOfTwoIndependentConnections(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for the concurrent CAS acceptance test');
        }

        $records = $this->gateway()->open(ConditionalMultipleProbeTable::class);
        $records->schema()->createTable();
        $rowId = $records->records()->add([
            'title' => 'base',
            'version' => 1,
            'tags' => ['base'],
        ]);

        $workers = [];
        foreach (['A', 'B'] as $workerName) {
            $sockets = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0);
            if ($sockets === false) {
                self::fail('Unable to create CAS worker socket pair');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork CAS worker');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                $this->runConcurrentCasWorker($sockets[1], $rowId, $workerName);
            }

            fclose($sockets[1]);
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        foreach ($workers as $worker) {
            self::assertSame("READY\n", fgets($worker['socket']));
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "GO\n");
        }

        $updates = [];
        foreach ($workers as $index => $worker) {
            $line = fgets($worker['socket']);
            self::assertIsString($line);
            $updates[$index] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('updated', $updates[$index]['phase'] ?? null);
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "READ\n");
        }

        $reads = [];
        foreach ($workers as $index => $worker) {
            $line = fgets($worker['socket']);
            self::assertIsString($line);
            $reads[$index] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        $this->databaseConnection = $this->independentDatabaseConnection();
        $this->databaseConnection->ping();
        $this->gateway = GatewayHarness::make($this->databaseConnection);
        $records = $this->gateway()->open(ConditionalMultipleProbeTable::class);

        self::assertCount(1, array_filter(
            $updates,
            static fn (array $update): bool => ($update['updated'] ?? false) === true,
        ));
        self::assertCount(1, array_filter(
            $updates,
            static fn (array $update): bool => ($update['updated'] ?? true) === false,
        ));

        $current = $records->conditionalRecords()->getCurrentById($rowId);
        self::assertIsArray($current);
        self::assertSame(2, $current['version'] ?? null);
        self::assertSame(1, $this->sidecarCount($rowId));

        $winnerTitle = $current['title'] ?? null;
        $winnerTags = $current['tags'] ?? null;
        self::assertContains($winnerTitle, ['winner-A', 'winner-B']);
        self::assertSame(['tag-' . substr((string) $winnerTitle, -1)], $winnerTags);

        foreach ($reads as $index => $read) {
            if (($updates[$index]['updated'] ?? false) === false) {
                self::assertSame($winnerTitle, $read['current']['title'] ?? null);
                self::assertSame(2, $read['current']['version'] ?? null);
                self::assertSame($winnerTags, $read['current']['tags'] ?? null);
            }
        }
    }

    /**
     * Откатывает scalar CAS и sidecar после ошибки sidecar.
     *
     * @return void
     */
    public function testConditionalCasRollsBackPayloadAndSidecarWhenMfvFails(): void
    {
        $records = $this->gateway()->open(ConditionalMultipleProbeTable::class);
        $records->schema()->createTable();
        $rowId = $records->records()->add([
            'title' => 'base',
            'version' => 1,
            'tags' => ['old'],
        ]);
        $records->records()->add([
            'title' => 'blocker',
            'version' => 1,
            'tags' => ['new'],
        ]);
        $this->installMfvFailureConstraint();

        try {
            $failed = false;
            try {
                $this->gateway()->transaction(function () use ($records, $rowId): void {
                    $records->conditionalRecords()->updateConditional(
                        $rowId,
                        new ConditionalCas('version', 1),
                        ['title' => 'changed', 'tags' => ['new']],
                    );
                });
            } catch (Throwable) {
                $failed = true;
            }

            self::assertTrue($failed);
            $current = $records->conditionalRecords()->getCurrentById($rowId);
            self::assertSame('base', $current['title'] ?? null);
            self::assertSame(1, $current['version'] ?? null);
            self::assertSame(['old'], $current['tags'] ?? null);
            self::assertSame(1, $this->sidecarCount($rowId));
        } finally {
            $this->dropMfvFailureConstraint();
        }
    }

    /**
     * Выполняет один worker конкурентного CAS.
     *
     * @param resource $socket IPC-сокет.
     * @param int $rowId Строка.
     * @param string $workerName Имя worker.
     *
     * @return never
     */
    private function runConcurrentCasWorker($socket, int $rowId, string $workerName): never
    {
        try {
            $records = $this->independentGateway()->open(ConditionalMultipleProbeTable::class);
            $read = $records->records()->getById($rowId);
            if (($read['version'] ?? null) !== 1) {
                throw new RuntimeException('CAS worker did not read expected version');
            }

            fwrite($socket, "READY\n");
            if (trim((string) fgets($socket)) !== 'GO') {
                throw new RuntimeException('CAS worker did not receive start barrier');
            }

            $updated = $records->conditionalRecords()->updateConditional(
                $rowId,
                new ConditionalCas('version', 1),
                [
                    'title' => 'winner-' . $workerName,
                    'tags' => ['tag-' . $workerName],
                ],
            );
            fwrite($socket, json_encode([
                'phase' => 'updated',
                'updated' => $updated,
            ], JSON_THROW_ON_ERROR) . "\n");

            if (trim((string) fgets($socket)) !== 'READ') {
                throw new RuntimeException('CAS worker did not receive read barrier');
            }

            $current = $records->conditionalRecords()->getCurrentById($rowId);
            fwrite($socket, json_encode([
                'phase' => 'read',
                'current' => $current,
            ], JSON_THROW_ON_ERROR) . "\n");
            fclose($socket);
            exit(0);
        } catch (Throwable $exception) {
            @fwrite($socket, json_encode([
                'phase' => 'error',
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR) . "\n");
            fclose($socket);
            exit(1);
        }
    }

    /**
     * Считает sidecar строки владельца.
     *
     * @param int $rowId Владелец.
     *
     * @return int Количество строк.
     */
    private function sidecarCount(int $rowId): int
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);

        return (int) $this->databaseConnection
            ->illuminateConnection()
            ->table('st_conditional_mfv_probe_mfv_tags')
            ->where('owner_id', $rowId)
            ->count();
    }

    /**
     * Ставит отказ sidecar insert.
     *
     * @return void
     */
    private function installMfvFailureConstraint(): void
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);
        $connection = $this->databaseConnection->illuminateConnection();
        $connection->unprepared(
            'ALTER TABLE st_conditional_mfv_probe_mfv_tags '
            . 'ADD UNIQUE KEY test_unique_value (value)',
        );
    }

    /**
     * Снимает отказ sidecar insert.
     *
     * @return void
     */
    private function dropMfvFailureConstraint(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        try {
            $this->databaseConnection
                ->illuminateConnection()
                ->unprepared('ALTER TABLE st_conditional_mfv_probe_mfv_tags DROP INDEX test_unique_value');
        } catch (Throwable) {
            return;
        }
    }

    /**
     * Снимает таблицы тестовых фикстур.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        $this->dropMfvFailureConstraint();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $schemaBuilder->dropIfExists('st_conditional_mfv_probe_mfv_tags');
        $schemaBuilder->dropIfExists('st_conditional_mfv_probe');
        $schemaBuilder->dropIfExists('st_crud_probe');
    }

    /**
     * Собирает шлюз из env или boot.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectGateway(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $this->databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $this->databaseConnection->ping();
            $this->gateway = GatewayHarness::make($this->databaseConnection);

            return;
        }

        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $container = $app->getLocator()->get(ISmartTableContainer::class);
        $connection = $container->get(IDatabaseConnection::class);
        if (!$connection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $connection->ping();
        $this->databaseConnection = $connection;
        $resolvedGateway = $container->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $resolvedGateway);
        $this->gateway = $resolvedGateway;
    }

    /**
     * Возвращает шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->gateway);

        return $this->gateway;
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

    /**
     * Создаёт второй SmartTable gateway с независимым DB connection.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws DatabaseException Если второе соединение недоступно.
     */
    private function independentGateway(): ISmartTableGateway
    {
        $connection = $this->independentDatabaseConnection();
        $connection->ping();

        return GatewayHarness::make($connection);
    }

    /**
     * Создаёт независимый адаптер по конфигурации тестового соединения.
     *
     * @return IlluminateDatabaseConnection Адаптер.
     *
     * @throws DatabaseException Если конфигурация или MySQL недоступны.
     */
    private function independentDatabaseConnection(): IlluminateDatabaseConnection
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);
        $config = $this->databaseConnection->illuminateConnection()->getConfig();
        $port = $config['port'] ?? 3306;
        $settings = DatabaseSettings::fromFields(
            (string) ($config['host'] ?? ''),
            is_int($port) ? $port : (int) $port,
            (string) ($config['database'] ?? ''),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            (string) ($config['charset'] ?? ''),
            false,
            (string) ($config['collation'] ?? ''),
            (string) ($config['timezone'] ?? ''),
        );

        return new IlluminateDatabaseConnection(new IlluminateConnectionFactory(), $settings);
    }
}
