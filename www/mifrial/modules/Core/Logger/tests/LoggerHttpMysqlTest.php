<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Logger\Repository\LogRepository;
use Mifrial\Core\Logger\Schema\LoggerSchema;
use Mifrial\Core\Logger\Service\LoggerPermissionKeys;
use Mifrial\Core\Logger\Table\LogTable;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use PHPUnit\Framework\TestCase;

final class LoggerHttpMysqlTest extends TestCase
{
    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectPorts();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Logger HTTP tests');
        }

        $this->dropTables();
        $this->installSchemas();
    }

    /**
     * Снос таблиц.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
    }

    /**
     * Гость AUTH_REQUIRED; без ключа DENIED; bypass и ключ проходят.
     *
     * @return void
     */
    public function testAuthRequiredDeniedAndBypass(): void
    {
        self::assertArrayHasKey('logger.findPage', $this->application()->getModuleManager()->getRoutes());
        $anon = $this->dispatch('logger.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertFalse($anon['success']);
        self::assertSame('AUTH_REQUIRED', $anon['error']['code']);
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $denied = $this->dispatch('logger.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertFalse($denied['success']);
        self::assertSame('AUTH_DENIED', $denied['error']['code']);
        $this->setActor($alice, [], true);
        $ok = $this->dispatch('logger.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertTrue($ok['success']);
        self::assertSame(0, $ok['data']['total']);
        $this->setActor($alice, [LoggerPermissionKeys::VIEW]);
        $withKey = $this->dispatch('logger.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertTrue($withKey['success']);
    }

    /**
     * Страница, порядок, фильтры, get, allowlist.
     *
     * @return void
     */
    public function testFindPageFiltersAndGet(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [LoggerPermissionKeys::VIEW]);
        $firstId = $this->addLog(100, 'error', 'one', 'a%b', 'INTERNAL');
        $secondId = $this->addLog(200, 'warning', 'two', 'mail.flush', 'MAIL_FAIL');
        $thirdId = $this->addLog(300, 'error', 'three', 'user.create', 'INTERNAL');
        $page = $this->dispatch('logger.findPage', ['limit' => 2, 'offset' => 0]);
        self::assertTrue($page['success']);
        self::assertSame(3, $page['data']['total']);
        self::assertSame([$thirdId, $secondId], array_column($page['data']['items'], 'id'));
        $offset = $this->dispatch('logger.findPage', ['limit' => 2, 'offset' => 2]);
        self::assertSame([$firstId], array_column($offset['data']['items'], 'id'));
        $level = $this->dispatch('logger.findPage', ['limit' => 10, 'offset' => 0, 'level' => 'warning']);
        self::assertSame([$secondId], array_column($level['data']['items'], 'id'));
        $source = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'source' => 'mail.flush',
            'sourceMode' => 'equals',
        ]);
        self::assertSame([$secondId], array_column($source['data']['items'], 'id'));
        $contains = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'source' => 'flush',
            'sourceMode' => 'contains',
        ]);
        self::assertSame([$secondId], array_column($contains['data']['items'], 'id'));
        $literal = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'source' => 'a%b',
            'sourceMode' => 'contains',
        ]);
        self::assertSame([$firstId], array_column($literal['data']['items'], 'id'));
        $underscoreId = $this->addLog(250, 'info', 'under', 'a_b', 'UNDER');
        $this->addLog(240, 'info', 'wild', 'axb', 'WILD');
        $underscore = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'source' => 'a_b',
            'sourceMode' => 'contains',
        ]);
        self::assertSame([$underscoreId], array_column($underscore['data']['items'], 'id'));
        $errorEquals = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'errorCode' => 'INTERNAL',
            'errorCodeMode' => 'equals',
        ]);
        self::assertSame([$thirdId, $firstId], array_column($errorEquals['data']['items'], 'id'));
        $errorContains = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'errorCode' => 'MAIL',
            'errorCodeMode' => 'contains',
        ]);
        self::assertSame([$secondId], array_column($errorContains['data']['items'], 'id'));
        $range = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'from' => 200,
            'to' => 200,
        ]);
        self::assertSame([$secondId], array_column($range['data']['items'], 'id'));
        $got = $this->dispatch('logger.get', ['id' => $firstId]);
        self::assertTrue($got['success']);
        self::assertSame($firstId, $got['data']['id']);
        self::assertSame('Application.php', $got['data']['context']['file']);
        self::assertArrayNotHasKey('sql', $got['data']['context']);
        $missing = $this->dispatch('logger.get', ['id' => 999]);
        self::assertFalse($missing['success']);
        self::assertSame('LOGGER_NOT_FOUND', $missing['error']['code']);
    }

    /**
     * Недопустимые фильтры и границы.
     *
     * @return void
     */
    public function testInvalidQuery(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [LoggerPermissionKeys::VIEW]);
        $limit = $this->dispatch('logger.findPage', ['limit' => 0, 'offset' => 0]);
        self::assertSame('LOGGER_INVALID', $limit['error']['code']);
        $over = $this->dispatch('logger.findPage', ['limit' => 101, 'offset' => 0]);
        self::assertSame('LOGGER_INVALID', $over['error']['code']);
        $level = $this->dispatch('logger.findPage', ['limit' => 10, 'offset' => 0, 'level' => 'debug']);
        self::assertSame('LOGGER_INVALID', $level['error']['code']);
        $mode = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'sourceMode' => 'contains',
        ]);
        self::assertSame('LOGGER_INVALID', $mode['error']['code']);
        $unknownMode = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'source' => 'mail',
            'sourceMode' => 'regex',
        ]);
        self::assertSame('LOGGER_INVALID', $unknownMode['error']['code']);
        $range = $this->dispatch('logger.findPage', [
            'limit' => 10,
            'offset' => 0,
            'from' => 20,
            'to' => 10,
        ]);
        self::assertSame('LOGGER_INVALID', $range['error']['code']);
    }

    /**
     * boot и порты.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $userAccounts = $application->getLocator()->get(IUserContainer::class)->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * User + log.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->smartTableGateway();
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
        ))->install();
        (new LoggerSchema($gateway->open(LogTable::class)->schema()))->install();
    }

    /**
     * Снос.
     *
     * @return void
     */
    private function dropTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $this->deleteIfExists($this->smartTableGateway->open(LogTable::class));
        UserMysqlTables::drop($this->smartTableGateway);
    }

    /**
     * deleteTable если есть.
     *
     * @param IOpenedTable $openedTable Карта.
     *
     * @return void
     */
    private function deleteIfExists(IOpenedTable $openedTable): void
    {
        if ($openedTable->schema()->exists()) {
            $openedTable->schema()->deleteTable();
        }
    }

    /**
     * Строка журнала.
     *
     * @param int $createdAt Unix.
     * @param string $level Уровень.
     * @param string $message Текст.
     * @param string $source Источник.
     * @param string $errorCode Код.
     *
     * @return int Id.
     */
    private function addLog(
        int $createdAt,
        string $level,
        string $message,
        string $source,
        string $errorCode,
    ): int {
        $repository = new LogRepository($this->smartTableGateway()->open(LogTable::class)->records());

        return $repository->add([
            'created_at' => DateTime::fromUnix($createdAt),
            'level' => $level,
            'message' => $message,
            'source' => $source,
            'error_code' => $errorCode,
            'context' => [
                'file' => 'Application.php',
                'sql' => 'select 1',
            ],
        ]);
    }

    /**
     * Учётка.
     *
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function addUser(string $login): int
    {
        return $this->userAccounts()->addFromInput([
            'login' => $login,
            'name' => $login,
        ]);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     * @param bool $hasBypass Обход.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys = [], bool $hasBypass = false): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, $permissionKeys, $hasBypass));
    }

    /**
     * Action.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->application()->dispatch($action, $payload)->toArray();
    }

    /**
     * Приложение.
     *
     * @return IApplication Приложение.
     */
    private function application(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Контекст.
     *
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);

        return $this->requestContext;
    }

    /**
     * Учётки.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Шлюз.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
