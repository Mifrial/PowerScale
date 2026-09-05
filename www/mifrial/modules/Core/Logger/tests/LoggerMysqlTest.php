<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Logger\Repository\LogRepository;
use Mifrial\Core\Logger\Schema\LoggerSchema;
use Mifrial\Core\Logger\Service\TableLogWriter;
use Mifrial\Core\Logger\Table\LogTable;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use PHPUnit\Framework\TestCase;

final class LoggerMysqlTest extends TestCase
{
    private ?ISmartTableGateway $smartTableGateway = null;

    private ?LogRepository $logRepository = null;

    private ?TableLogWriter $tableLogWriter = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectLogger();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Logger tests');
        }

        $this->dropLogTable();
        (new LoggerSchema($this->smartTableGateway()->open(LogTable::class)->schema()))->install();
    }

    /**
     * Сносит таблицу.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropLogTable();
    }

    /**
     * error/warning/info пишут строки; debug не пишет; reserved ключи в колонках.
     *
     * @return void
     */
    public function testWritesLevelsAndContextColumns(): void
    {
        $writer = $this->tableLogWriter();
        $writer->error('boom', [
            'source' => 'user.create',
            'class' => 'RuntimeException',
            'errorCode' => 'USER_INVALID',
            'userId' => 7,
            'file' => 'a.php',
            'nested' => ['x' => 1],
        ]);
        $writer->warning('miss', ['source' => 'mail.flush']);
        $writer->info('note', []);
        $writer->debug('noise', ['source' => 'nope']);
        $rows = $this->logRepository()->listRecent(10);
        self::assertCount(3, $rows);
        $byLevel = [];
        foreach ($rows as $row) {
            $byLevel[(string) $row['level']] = $row;
        }

        self::assertSame('boom', $byLevel['error']['message']);
        self::assertSame('user.create', $byLevel['error']['source']);
        self::assertSame('RuntimeException', $byLevel['error']['exception_class']);
        self::assertSame('USER_INVALID', $byLevel['error']['error_code']);
        self::assertSame(7, (int) $byLevel['error']['user_id']);
        self::assertIsArray($byLevel['error']['context']);
        self::assertArrayHasKey('file', $byLevel['error']['context']);
        self::assertArrayNotHasKey('source', $byLevel['error']['context']);
        self::assertArrayNotHasKey('nested', $byLevel['error']['context']);
        self::assertSame('miss', $byLevel['warning']['message']);
        self::assertSame('note', $byLevel['info']['message']);
    }

    /**
     * Нет таблицы — error не бросает.
     *
     * @return void
     */
    public function testMissingTableDoesNotThrow(): void
    {
        $this->dropLogTable();
        $this->tableLogWriter()->error('gone', ['source' => 'mifrial.ping']);
        self::assertTrue(true);
    }

    /**
     * Подключение MySQL.
     *
     * @return void
     */
    private function connectLogger(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $databaseConnection->ping();
            $this->bindOnGateway(GatewayHarness::make($databaseConnection));

            return;
        }

        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $this->bindOnGateway($smartTableGateway);
    }

    /**
     * Репозиторий на шлюзе.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindOnGateway(ISmartTableGateway $smartTableGateway): void
    {
        $this->smartTableGateway = $smartTableGateway;
        $this->logRepository = new LogRepository(
            $smartTableGateway->open(LogTable::class)->records(),
        );
        $this->tableLogWriter = new TableLogWriter($this->logRepository);
    }

    /**
     * @return TableLogWriter Писатель.
     */
    private function tableLogWriter(): TableLogWriter
    {
        self::assertInstanceOf(TableLogWriter::class, $this->tableLogWriter);

        return $this->tableLogWriter;
    }

    /**
     * @return LogRepository Репозиторий.
     */
    private function logRepository(): LogRepository
    {
        self::assertInstanceOf(LogRepository::class, $this->logRepository);

        return $this->logRepository;
    }

    /**
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }

    /**
     * Сносит `log`.
     *
     * @return void
     */
    private function dropLogTable(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $openedSchema = $this->smartTableGateway->open(LogTable::class)->schema();
        if ($openedSchema->exists()) {
            $openedSchema->deleteTable();
        }
    }

    /**
     * Настройки из env.
     *
     * @param string $host Хост.
     *
     * @return DatabaseSettings Настройки.
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
