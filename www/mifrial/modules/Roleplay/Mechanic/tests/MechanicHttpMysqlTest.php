<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
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
use Mifrial\Roleplay\Mechanic\Schema\MechanicSchema;
use Mifrial\Roleplay\Mechanic\Service\MechanicPermissionKeys;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use PHPUnit\Framework\TestCase;

final class MechanicHttpMysqlTest extends TestCase
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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Mechanic HTTP tests');
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
     * Нет актора — AUTH_REQUIRED; без ключей чтение ок, запись DENIED.
     *
     * @return void
     */
    public function testAuthRequiredAndReadWithoutKeys(): void
    {
        $payload = $this->dispatch('mechanic.getList', null);
        self::assertFalse($payload['success']);
        self::assertSame('AUTH_REQUIRED', $payload['error']['code']);
        $anonGet = $this->dispatch('mechanic.get', ['id' => 1]);
        self::assertFalse($anonGet['success']);
        self::assertSame('AUTH_REQUIRED', $anonGet['error']['code']);
        $anonCreate = $this->dispatch('mechanic.create', [
            'code' => 'six_one_rule',
            'name' => '6 и 1',
            'version' => '1',
        ]);
        self::assertFalse($anonCreate['success']);
        self::assertSame('AUTH_REQUIRED', $anonCreate['error']['code']);
        $anonUpdate = $this->dispatch('mechanic.update', ['id' => 1, 'name' => 'X']);
        self::assertFalse($anonUpdate['success']);
        self::assertSame('AUTH_REQUIRED', $anonUpdate['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $empty = $this->dispatch('mechanic.getList', null);
        self::assertTrue($empty['success']);
        self::assertSame([], $empty['data']);
        $missing = $this->dispatch('mechanic.get', ['id' => 1]);
        self::assertFalse($missing['success']);
        self::assertSame('MECHANIC_NOT_FOUND', $missing['error']['code']);
        $createDenied = $this->dispatch('mechanic.create', [
            'code' => 'six_one_rule',
            'name' => '6 и 1',
            'version' => '1',
        ]);
        self::assertFalse($createDenied['success']);
        self::assertSame('AUTH_DENIED', $createDenied['error']['code']);
        $updateDenied = $this->dispatch('mechanic.update', ['id' => 1, 'name' => 'X']);
        self::assertFalse($updateDenied['success']);
        self::assertSame('AUTH_DENIED', $updateDenied['error']['code']);
    }

    /**
     * CRUD двух поставок, patch, extra keys.
     *
     * @return void
     */
    public function testCreateGetUpdateTwoVersions(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, $this->editorKeys());
        $created = $this->dispatch('mechanic.create', [
            'code' => '  six_one_rule  ',
            'name' => '  6 и 1  ',
            'version' => '  1.0.0  ',
        ]);
        self::assertTrue($created['success']);
        self::assertSame('six_one_rule', $created['data']['code']);
        self::assertSame('6 и 1', $created['data']['name']);
        self::assertSame('', $created['data']['description']);
        self::assertSame('1.0.0', $created['data']['version']);
        self::assertArrayNotHasKey('handlerVersion', $created['data']);
        self::assertArrayNotHasKey('createdAt', $created['data']);
        $firstId = $created['data']['id'];

        $second = $this->dispatch('mechanic.create', [
            'code' => 'six_one_rule',
            'name' => '6 и 1',
            'version' => '2.0.0',
            'description' => 'вторая',
        ]);
        self::assertTrue($second['success']);
        self::assertNotSame($firstId, $second['data']['id']);

        $list = $this->dispatch('mechanic.getList', null);
        self::assertTrue($list['success']);
        self::assertCount(2, $list['data']);
        self::assertSame($firstId, $list['data'][0]['id']);
        $got = $this->dispatch('mechanic.get', ['id' => $firstId]);
        self::assertTrue($got['success']);
        self::assertSame($firstId, $got['data']['id']);

        $updated = $this->dispatch('mechanic.update', ['id' => $firstId, 'description' => 'note']);
        self::assertTrue($updated['success']);
        self::assertSame('6 и 1', $updated['data']['name']);
        self::assertSame('note', $updated['data']['description']);
        self::assertSame('six_one_rule', $updated['data']['code']);
        self::assertSame('1.0.0', $updated['data']['version']);

        $emptyPatch = $this->dispatch('mechanic.update', ['id' => $firstId]);
        self::assertFalse($emptyPatch['success']);
        self::assertSame('MECHANIC_INVALID', $emptyPatch['error']['code']);
        $codePatch = $this->dispatch('mechanic.update', ['id' => $firstId, 'code' => 'other']);
        self::assertFalse($codePatch['success']);
        self::assertSame('INVALID_PARAMS', $codePatch['error']['code']);
        $versionPatch = $this->dispatch('mechanic.update', ['id' => $firstId, 'version' => '9']);
        self::assertFalse($versionPatch['success']);
        self::assertSame('INVALID_PARAMS', $versionPatch['error']['code']);
        $handlerPatch = $this->dispatch('mechanic.create', [
            'code' => 'roll',
            'name' => 'Бросок',
            'handlerVersion' => '1',
        ]);
        self::assertFalse($handlerPatch['success']);
        self::assertSame('INVALID_PARAMS', $handlerPatch['error']['code']);

        $dup = $this->dispatch('mechanic.create', [
            'code' => 'six_one_rule',
            'name' => 'Dup',
            'version' => '1.0.0',
        ]);
        self::assertFalse($dup['success']);
        self::assertSame('MECHANIC_INVALID', $dup['error']['code']);
    }

    /**
     * Bypass пропускает ключи записи.
     *
     * @return void
     */
    public function testBypassSkipsWriteKeys(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [], true);
        $created = $this->dispatch('mechanic.create', [
            'code' => 'roll',
            'name' => 'Бросок',
            'version' => '1',
        ]);
        self::assertTrue($created['success']);
        $mechanicId = $created['data']['id'];
        $updated = $this->dispatch('mechanic.update', ['id' => $mechanicId, 'name' => 'Roll']);
        self::assertTrue($updated['success']);
        self::assertSame('Roll', $updated['data']['name']);
    }

    /**
     * Больше 500 строк — MECHANIC_INVALID, не обрезка.
     *
     * @return void
     */
    public function testGetListOverCapIsInvalid(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [MechanicPermissionKeys::CREATE]);
        for ($index = 1; $index <= 501; ++$index) {
            $created = $this->dispatch('mechanic.create', [
                'code' => 'k' . $index,
                'name' => 'K' . $index,
                'version' => '1',
            ]);
            self::assertTrue($created['success']);
        }

        $this->setActor($alice);
        $list = $this->dispatch('mechanic.getList', null);
        self::assertFalse($list['success']);
        self::assertSame('MECHANIC_INVALID', $list['error']['code']);
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
        self::assertArrayHasKey('mechanic.getList', $application->getModuleManager()->getRoutes());
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
     * User + mechanic.
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
        (new MechanicSchema($gateway->open(MechanicTable::class)->schema()))->install();
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

        $gateway = $this->smartTableGateway;
        $this->deleteIfExists($gateway->open(MechanicTable::class));
        UserMysqlTables::drop($gateway);
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
     * Ключи записи справочника.
     *
     * @return array<int, string> Ключи.
     */
    private function editorKeys(): array
    {
        return [
            MechanicPermissionKeys::CREATE,
            MechanicPermissionKeys::EDIT,
        ];
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
