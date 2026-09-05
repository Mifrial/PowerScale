<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Tests;

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
use Mifrial\Roleplay\Keyword\Schema\KeywordSchema;
use Mifrial\Roleplay\Keyword\Service\KeywordPermissionKeys;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use PHPUnit\Framework\TestCase;

final class KeywordHttpMysqlTest extends TestCase
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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Keyword HTTP tests');
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
        $payload = $this->dispatch('keyword.getList', null);
        self::assertFalse($payload['success']);
        self::assertSame('AUTH_REQUIRED', $payload['error']['code']);
        $anonGet = $this->dispatch('keyword.get', ['id' => 1]);
        self::assertFalse($anonGet['success']);
        self::assertSame('AUTH_REQUIRED', $anonGet['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $empty = $this->dispatch('keyword.getList', null);
        self::assertTrue($empty['success']);
        self::assertSame([], $empty['data']);
        $missing = $this->dispatch('keyword.get', ['id' => 1]);
        self::assertFalse($missing['success']);
        self::assertSame('KEYWORD_NOT_FOUND', $missing['error']['code']);
        $createDenied = $this->dispatch('keyword.create', ['code' => 'melee', 'name' => 'Melee']);
        self::assertFalse($createDenied['success']);
        self::assertSame('AUTH_DENIED', $createDenied['error']['code']);
        $updateDenied = $this->dispatch('keyword.update', ['id' => 1, 'name' => 'X']);
        self::assertFalse($updateDenied['success']);
        self::assertSame('AUTH_DENIED', $updateDenied['error']['code']);
        $deactivateDenied = $this->dispatch('keyword.deactivate', ['id' => 1]);
        self::assertFalse($deactivateDenied['success']);
        self::assertSame('AUTH_DENIED', $deactivateDenied['error']['code']);
    }

    /**
     * CRUD, hyphen code, patch merge, extra keys.
     *
     * @return void
     */
    public function testCreateGetUpdateDeactivate(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, $this->editorKeys());
        $created = $this->dispatch('keyword.create', [
            'code' => '  wood-elf  ',
            'name' => '  Wood Elf  ',
        ]);
        self::assertTrue($created['success']);
        self::assertSame('wood-elf', $created['data']['code']);
        self::assertSame('Wood Elf', $created['data']['name']);
        self::assertSame('', $created['data']['description']);
        self::assertTrue($created['data']['active']);
        self::assertArrayNotHasKey('createdAt', $created['data']);
        $keywordId = $created['data']['id'];

        $list = $this->dispatch('keyword.getList', null);
        self::assertTrue($list['success']);
        self::assertCount(1, $list['data']);
        $got = $this->dispatch('keyword.get', ['id' => $keywordId]);
        self::assertTrue($got['success']);
        self::assertSame($keywordId, $got['data']['id']);

        $updated = $this->dispatch('keyword.update', ['id' => $keywordId, 'description' => 'note']);
        self::assertTrue($updated['success']);
        self::assertSame('Wood Elf', $updated['data']['name']);
        self::assertSame('note', $updated['data']['description']);
        self::assertSame('wood-elf', $updated['data']['code']);

        $emptyPatch = $this->dispatch('keyword.update', ['id' => $keywordId]);
        self::assertFalse($emptyPatch['success']);
        self::assertSame('KEYWORD_INVALID', $emptyPatch['error']['code']);
        $codePatch = $this->dispatch('keyword.update', ['id' => $keywordId, 'code' => 'other']);
        self::assertFalse($codePatch['success']);
        self::assertSame('INVALID_PARAMS', $codePatch['error']['code']);
        $activePatch = $this->dispatch('keyword.update', ['id' => $keywordId, 'active' => true]);
        self::assertFalse($activePatch['success']);
        self::assertSame('INVALID_PARAMS', $activePatch['error']['code']);

        $dup = $this->dispatch('keyword.create', ['code' => 'wood-elf', 'name' => 'Dup']);
        self::assertFalse($dup['success']);
        self::assertSame('KEYWORD_INVALID', $dup['error']['code']);

        $deactivated = $this->dispatch('keyword.deactivate', ['id' => $keywordId]);
        self::assertTrue($deactivated['success']);
        self::assertNull($deactivated['data']);
        $again = $this->dispatch('keyword.deactivate', ['id' => $keywordId]);
        self::assertTrue($again['success']);
        $after = $this->dispatch('keyword.get', ['id' => $keywordId]);
        self::assertTrue($after['success']);
        self::assertFalse($after['data']['active']);
        self::assertCount(1, $this->dispatch('keyword.getList', null)['data']);
        $missingDeactivate = $this->dispatch('keyword.deactivate', ['id' => 999]);
        self::assertFalse($missingDeactivate['success']);
        self::assertSame('KEYWORD_NOT_FOUND', $missingDeactivate['error']['code']);
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
        $created = $this->dispatch('keyword.create', ['code' => 'melee', 'name' => 'Melee']);
        self::assertTrue($created['success']);
        $keywordId = $created['data']['id'];
        $updated = $this->dispatch('keyword.update', ['id' => $keywordId, 'name' => 'Close']);
        self::assertTrue($updated['success']);
        $deactivated = $this->dispatch('keyword.deactivate', ['id' => $keywordId]);
        self::assertTrue($deactivated['success']);
    }

    /**
     * Больше 500 строк — KEYWORD_INVALID, не обрезка.
     *
     * @return void
     */
    public function testGetListOverCapIsInvalid(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [KeywordPermissionKeys::CREATE]);
        for ($index = 1; $index <= 501; ++$index) {
            $created = $this->dispatch('keyword.create', [
                'code' => 'k' . $index,
                'name' => 'K' . $index,
            ]);
            self::assertTrue($created['success']);
        }

        $this->setActor($alice);
        $list = $this->dispatch('keyword.getList', null);
        self::assertFalse($list['success']);
        self::assertSame('KEYWORD_INVALID', $list['error']['code']);
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
        self::assertArrayHasKey('keyword.getList', $application->getModuleManager()->getRoutes());
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
     * User + keyword.
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
        (new KeywordSchema($gateway->open(KeywordTable::class)->schema()))->install();
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
        $this->deleteIfExists($gateway->open(KeywordTable::class));
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
            KeywordPermissionKeys::CREATE,
            KeywordPermissionKeys::EDIT,
            KeywordPermissionKeys::DELETE,
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
