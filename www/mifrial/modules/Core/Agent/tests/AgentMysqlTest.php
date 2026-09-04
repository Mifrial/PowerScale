<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Tests;

use Mifrial\Core\Agent\Exception\AgentException;
use Mifrial\Core\Agent\Repository\AgentRepository;
use Mifrial\Core\Agent\Schema\AgentSchema;
use Mifrial\Core\Agent\Service\AgentService;
use Mifrial\Core\Agent\Table\AgentTable;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use PHPUnit\Framework\TestCase;

final class AgentMysqlTest extends TestCase
{
    private ?AgentService $agentService = null;

    private ?AgentRepository $agentRepository = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectAgent();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Agent tests');
        }

        $this->dropAgentTable();
        (new AgentSchema($this->smartTableGateway()->open(AgentTable::class)->schema()))->install();
    }

    /**
     * Сносит таблицу.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropAgentTable();
    }

    /**
     * ensure идемпотентен; пустой code отказ.
     *
     * @return void
     */
    public function testEnsureAgent(): void
    {
        $this->agentService()->ensureAgent('mail.flush', 60);
        $this->agentService()->ensureAgent('mail.flush', 15);
        $row = $this->agentRepository()->findByCode('mail.flush');
        self::assertIsArray($row);
        self::assertSame(60, (int) $row['interval_sec']);
        try {
            $this->agentService()->ensureAgent('  ', 60);
            self::fail('empty code');
        } catch (AgentException $exception) {
            self::assertSame('AGENT_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Due без last_run; повтор сразу при interval 3600 не зовёт.
     *
     * @return void
     */
    public function testTickDueAndInterval(): void
    {
        $handler = new CountingAgentHandler();
        $this->agentService()->ensureAgent('due.one', 3600);
        $this->agentService()->bindHandler('due.one', $handler);
        $this->agentService()->tick();
        self::assertSame(1, $handler->runs);
        $this->agentService()->tick();
        self::assertSame(1, $handler->runs);
    }

    /**
     * Неактивный агент не зовётся.
     *
     * @return void
     */
    public function testInactiveSkipped(): void
    {
        $handler = new CountingAgentHandler();
        $this->agentService()->ensureAgent('quiet', 60);
        $this->agentService()->bindHandler('quiet', $handler);
        $row = $this->agentRepository()->findByCode('quiet');
        self::assertIsArray($row);
        $this->smartTableGateway()->open(AgentTable::class)->records()->update((int) $row['id'], [
            'active' => false,
        ]);
        $this->agentService()->tick();
        self::assertSame(0, $handler->runs);
    }

    /**
     * Нет handler — last_run не двигается. Exception не блокирует соседний агент.
     *
     * @return void
     */
    public function testMissingHandlerAndException(): void
    {
        $okHandler = new CountingAgentHandler();
        $this->agentService()->ensureAgent('ghost', 60);
        $this->agentService()->ensureAgent('boom', 60);
        $this->agentService()->ensureAgent('ok', 60);
        $this->agentService()->bindHandler('boom', new ThrowingAgentHandler());
        $this->agentService()->bindHandler('ok', $okHandler);
        $this->agentService()->tick();
        self::assertNull($this->agentRepository()->findByCode('ghost')['last_run_at'] ?? null);
        self::assertNull($this->agentRepository()->findByCode('boom')['last_run_at'] ?? null);
        self::assertInstanceOf(DateTime::class, $this->agentRepository()->findByCode('ok')['last_run_at'] ?? null);
        self::assertSame(1, $okHandler->runs);
    }

    /**
     * Подключение MySQL.
     *
     * @return void
     */
    private function connectAgent(): void
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
     * Сервис на шлюзе.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindOnGateway(ISmartTableGateway $smartTableGateway): void
    {
        $this->smartTableGateway = $smartTableGateway;
        $this->agentRepository = new AgentRepository(
            $smartTableGateway->open(AgentTable::class)->records(),
        );
        $this->agentService = new AgentService($this->agentRepository);
    }

    /**
     * @return AgentService Фасад.
     */
    private function agentService(): AgentService
    {
        self::assertInstanceOf(AgentService::class, $this->agentService);

        return $this->agentService;
    }

    /**
     * @return AgentRepository Репозиторий.
     */
    private function agentRepository(): AgentRepository
    {
        self::assertInstanceOf(AgentRepository::class, $this->agentRepository);

        return $this->agentRepository;
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
     * Drop `agent`.
     *
     * @return void
     */
    private function dropAgentTable(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $openedSchema = $this->smartTableGateway->open(AgentTable::class)->schema();
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
