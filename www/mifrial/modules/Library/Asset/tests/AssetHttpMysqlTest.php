<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Tests;

use Mifrial\Core\File\Schema\FileSchema;
use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserAvatarTable;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Library\Asset\Interface\Container\IAssetContainer;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Library\Asset\Schema\AssetSchema;
use Mifrial\Library\Asset\Table\AssetCollectionTable;
use PHPUnit\Framework\TestCase;

final class AssetHttpMysqlTest extends TestCase
{
    private const MODULE = 'Roleplay/Scene';

    private const COLLECTION = 'decor';

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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Asset HTTP tests');
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
     * Аноним, неизвестная коллекция и идемпотентный ensure.
     *
     * @return void
     */
    public function testAuthRequiredAndCollections(): void
    {
        $anonymous = $this->dispatch('asset.findPage', $this->pagePayload());
        self::assertFalse($anonymous['success']);
        self::assertSame('AUTH_REQUIRED', $anonymous['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $unknown = $this->dispatch('asset.findPage', $this->pagePayload());
        self::assertFalse($unknown['success']);
        self::assertSame('ASSET_INVALID', $unknown['error']['code']);

        $this->collections()->ensure(self::MODULE, self::COLLECTION, 'Декор');
        $this->collections()->ensure(self::MODULE, self::COLLECTION, 'Декор');
        self::assertSame(1, $this->countCollections());

        $empty = $this->dispatch('asset.findPage', $this->pagePayload());
        self::assertTrue($empty['success']);
        self::assertSame(0, $empty['data']['total']);
    }

    /**
     * Общая и личная части, права и архив.
     *
     * @return void
     */
    public function testSharedAndPersonalVisibility(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $admin = $this->addUser('admin');
        $this->collections()->ensure(self::MODULE, self::COLLECTION, 'Декор');

        $this->setActor($alice);
        $aliceFile = $this->addFile($alice);
        $personal = $this->dispatch('asset.create', $this->createPayload($aliceFile, 'personal'));
        self::assertTrue($personal['success']);
        self::assertSame('tree', $personal['data']['name']);
        self::assertFalse($personal['data']['isShared']);
        self::assertSame($alice, $personal['data']['ownerId']);
        self::assertSame($aliceFile, $personal['data']['filePublicId']);
        self::assertSame(256, $personal['data']['width']);
        self::assertSame(340, $personal['data']['height']);
        self::assertSame('image/png', $personal['data']['mime']);
        self::assertSame(self::MODULE, $personal['data']['collectionModule']);

        $sharedDenied = $this->dispatch('asset.create', $this->createPayload($aliceFile, 'shared'));
        self::assertFalse($sharedDenied['success']);
        self::assertSame('ASSET_DENIED', $sharedDenied['error']['code']);

        $this->setActor($bob);
        self::assertSame(0, $this->dispatch('asset.findPage', $this->pagePayload('personal'))['data']['total']);
        self::assertSame(0, $this->dispatch('asset.findPage', $this->pagePayload('shared'))['data']['total']);

        $this->setActor($admin, [], true);
        $adminFile = $this->addFile($admin);
        $shared = $this->dispatch('asset.create', $this->createPayload($adminFile, 'shared', 'Дерево'));
        self::assertTrue($shared['success']);
        self::assertTrue($shared['data']['isShared']);
        self::assertNull($shared['data']['ownerId']);
        self::assertSame('Дерево', $shared['data']['name']);
        self::assertSame(0, $this->dispatch('asset.findPage', $this->pagePayload('personal'))['data']['total']);

        $this->setActor($bob);
        $bobShared = $this->dispatch('asset.findPage', $this->pagePayload('shared'));
        self::assertSame(1, $bobShared['data']['total']);
        self::assertSame('Дерево', $bobShared['data']['items'][0]['name']);

        $deniedUpdate = $this->dispatch('asset.update', ['id' => $shared['data']['id'], 'name' => 'Куст']);
        self::assertFalse($deniedUpdate['success']);
        self::assertSame('ASSET_DENIED', $deniedUpdate['error']['code']);

        $this->setActor($alice);
        $updated = $this->dispatch('asset.update', ['id' => $personal['data']['id'], 'name' => 'Моё дерево']);
        self::assertTrue($updated['success']);
        self::assertSame('Моё дерево', $updated['data']['name']);

        $this->setActor($bob);
        $foreignUpdate = $this->dispatch('asset.update', ['id' => $personal['data']['id'], 'name' => 'Чужое']);
        self::assertFalse($foreignUpdate['success']);
        self::assertSame('ASSET_DENIED', $foreignUpdate['error']['code']);

        $this->setActor($admin, [], true);
        $renamed = $this->dispatch('asset.update', ['id' => $shared['data']['id'], 'name' => 'Большое дерево']);
        self::assertTrue($renamed['success']);
        self::assertSame('Большое дерево', $renamed['data']['name']);

        $this->setActor($alice);
        $archiveDenied = $this->dispatch('asset.archive', ['id' => $shared['data']['id']]);
        self::assertFalse($archiveDenied['success']);
        self::assertSame('ASSET_DENIED', $archiveDenied['error']['code']);

        $this->setActor($admin, [], true);
        $archived = $this->dispatch('asset.archive', ['id' => $shared['data']['id']]);
        self::assertTrue($archived['success']);
        self::assertSame(0, $this->dispatch('asset.findPage', $this->pagePayload('shared'))['data']['total']);
        self::assertTrue($this->hasFileRow($adminFile));

        $this->setActor($alice);
        $ownArchive = $this->dispatch('asset.archive', ['id' => $personal['data']['id']]);
        self::assertTrue($ownArchive['success']);
        self::assertSame(0, $this->dispatch('asset.findPage', $this->pagePayload('personal'))['data']['total']);
        self::assertTrue($this->hasFileRow($aliceFile));
    }

    /**
     * Ошибки файла, имени, границ, поиска и неизвестного ассета.
     *
     * @return void
     */
    public function testValidation(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $this->collections()->ensure(self::MODULE, self::COLLECTION, 'Декор');

        $gifFile = $this->addFile($alice, 'image/gif');
        $gif = $this->dispatch('asset.create', $this->createPayload($gifFile, 'personal'));
        self::assertFalse($gif['success']);
        self::assertSame('ASSET_FILE_INVALID', $gif['error']['code']);

        $archivedFile = $this->addFile($alice, 'image/png', true);
        $archived = $this->dispatch('asset.create', $this->createPayload($archivedFile, 'personal'));
        self::assertFalse($archived['success']);
        self::assertSame('ASSET_FILE_INVALID', $archived['error']['code']);

        $missingFile = $this->dispatch('asset.create', $this->createPayload(str_repeat('a', 32), 'personal'));
        self::assertFalse($missingFile['success']);
        self::assertSame('ASSET_FILE_INVALID', $missingFile['error']['code']);

        $file = $this->addFile($alice);
        $longName = $this->dispatch('asset.create', $this->createPayload($file, 'personal', str_repeat('a', 121)));
        self::assertFalse($longName['success']);
        self::assertSame('ASSET_INVALID', $longName['error']['code']);

        $invalidScope = $this->dispatch('asset.create', $this->createPayload($file, 'public'));
        self::assertFalse($invalidScope['success']);
        self::assertSame('ASSET_INVALID', $invalidScope['error']['code']);

        $unknownCollection = $this->createPayload($file, 'personal');
        $unknownCollection['collectionCode'] = 'unknown';
        $unknown = $this->dispatch('asset.create', $unknownCollection);
        self::assertFalse($unknown['success']);
        self::assertSame('ASSET_INVALID', $unknown['error']['code']);

        $badBounds = $this->pagePayload();
        $badBounds['limit'] = 0;
        self::assertSame('ASSET_INVALID', $this->dispatch('asset.findPage', $badBounds)['error']['code']);

        $longSearch = $this->pagePayload();
        $longSearch['q'] = str_repeat('a', 121);
        self::assertSame('ASSET_INVALID', $this->dispatch('asset.findPage', $longSearch)['error']['code']);

        $created = $this->dispatch('asset.create', $this->createPayload($file, 'personal', ' Дерево '));
        self::assertTrue($created['success']);
        self::assertSame('Дерево', $created['data']['name']);

        $emptyUpdate = $this->dispatch('asset.update', ['id' => $created['data']['id'], 'name' => '  ']);
        self::assertFalse($emptyUpdate['success']);
        self::assertSame('ASSET_INVALID', $emptyUpdate['error']['code']);

        $missingAsset = $this->dispatch('asset.archive', ['id' => 99999]);
        self::assertFalse($missingAsset['success']);
        self::assertSame('ASSET_NOT_FOUND', $missingAsset['error']['code']);
    }

    /**
     * Поиск по имени и выключенная коллекция.
     *
     * @return void
     */
    public function testSearchAndInactiveCollection(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $this->collections()->ensure(self::MODULE, self::COLLECTION, 'Декор');

        $this->dispatch('asset.create', $this->createPayload($this->addFile($alice), 'personal', 'Дерево'));
        $this->dispatch('asset.create', $this->createPayload($this->addFile($alice), 'personal', 'Камень'));

        $search = $this->pagePayload('personal');
        $search['q'] = 'дерево';
        $page = $this->dispatch('asset.findPage', $search);
        self::assertTrue($page['success']);
        self::assertSame(1, $page['data']['total']);
        self::assertSame('Дерево', $page['data']['items'][0]['name']);

        $this->setCollectionActive(false);
        $findInactive = $this->dispatch('asset.findPage', $this->pagePayload('personal'));
        self::assertFalse($findInactive['success']);
        self::assertSame('ASSET_INVALID', $findInactive['error']['code']);

        $createInactive = $this->dispatch('asset.create', $this->createPayload($this->addFile($alice), 'personal'));
        self::assertFalse($createInactive['success']);
        self::assertSame('ASSET_INVALID', $createInactive['error']['code']);

        $this->collections()->ensure(self::MODULE, self::COLLECTION, 'Декор');
        self::assertSame(1, $this->countCollections());
        self::assertSame('ASSET_INVALID', $this->dispatch('asset.findPage', $this->pagePayload('personal'))['error']['code']);
    }

    /**
     * Вход страницы.
     *
     * @param string $scope Часть библиотеки.
     *
     * @return array<string, mixed> Вход.
     */
    private function pagePayload(string $scope = 'shared'): array
    {
        return [
            'collectionModule' => self::MODULE,
            'collectionCode' => self::COLLECTION,
            'scope' => $scope,
            'limit' => 10,
            'offset' => 0,
        ];
    }

    /**
     * Вход создания.
     *
     * @param string $filePublicId Внешний id файла.
     * @param string $scope Часть библиотеки.
     * @param string|null $name Подпись.
     *
     * @return array<string, mixed> Вход.
     */
    private function createPayload(string $filePublicId, string $scope, ?string $name = null): array
    {
        $payload = [
            'collectionModule' => self::MODULE,
            'collectionCode' => self::COLLECTION,
            'filePublicId' => $filePublicId,
            'scope' => $scope,
        ];
        if ($name !== null) {
            $payload['name'] = $name;
        }

        return $payload;
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
        self::assertArrayHasKey('asset.findPage', $application->getModuleManager()->getRoutes());
        self::assertArrayHasKey('asset.create', $application->getModuleManager()->getRoutes());
        self::assertArrayHasKey('asset.update', $application->getModuleManager()->getRoutes());
        self::assertArrayHasKey('asset.archive', $application->getModuleManager()->getRoutes());
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
     * User + File + Asset.
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
            $gateway->open(UserAvatarTable::class)->schema(),
        ))->install();
        (new FileSchema($gateway->open(FileTable::class)->schema()))->install();
        foreach (AssetSchema::getTableClasses() as $tableClass) {
            $gateway->open($tableClass)->schema()->createTable();
        }
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
        foreach (array_reverse(AssetSchema::getTableClasses()) as $tableClass) {
            $this->deleteIfExists($gateway->open($tableClass));
        }
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
            'login' => $login . '_' . bin2hex(random_bytes(4)),
            'name' => $login,
        ]);
    }

    /**
     * Строка файла.
     *
     * @param int $ownerId Владелец.
     * @param string $mime Тип.
     * @param bool $isArchived Архив.
     *
     * @return string Внешний id.
     */
    private function addFile(int $ownerId, string $mime = 'image/png', bool $isArchived = false): string
    {
        $publicId = bin2hex(random_bytes(16));
        $this->smartTableGateway()->open(FileTable::class)->records()->add([
            'public_id' => $publicId,
            'owner_id' => $ownerId,
            'original_name' => 'tree.png',
            'mime' => $mime,
            'size' => 1024,
            'sha256' => hash('sha256', $publicId),
            'storage_path' => 'var/file/test/' . $publicId . '.png',
            'width' => 256,
            'height' => 340,
            'is_archived' => $isArchived,
            'is_derived' => false,
        ]);

        return $publicId;
    }

    /**
     * Файл ещё существует.
     *
     * @param string $publicId Внешний id.
     *
     * @return bool Флаг.
     */
    private function hasFileRow(string $publicId): bool
    {
        $listResult = $this->smartTableGateway()->open(FileTable::class)->records()->getList(
            ListQuery::fromOptions([
                'filter' => ['=public_id' => $publicId],
                'limit' => 1,
            ]),
        );

        return $listResult->rows() !== [];
    }

    /**
     * Число коллекций.
     *
     * @return int Всего.
     */
    private function countCollections(): int
    {
        return (int) $this->smartTableGateway()
            ->open(AssetCollectionTable::class)
            ->records()
            ->getList(ListQuery::fromOptions(['limit' => 100, 'countTotal' => true]))
            ->total();
    }

    /**
     * Включает или выключает коллекцию.
     *
     * @param bool $isActive Флаг.
     *
     * @return void
     */
    private function setCollectionActive(bool $isActive): void
    {
        $records = $this->smartTableGateway()->open(AssetCollectionTable::class)->records();
        $listResult = $records->getList(ListQuery::fromOptions([
            'filter' => [
                'LOGIC' => 'AND',
                ['=module' => self::MODULE],
                ['=code' => self::COLLECTION],
            ],
            'limit' => 1,
        ]));
        $row = $listResult->rows()[0] ?? null;
        self::assertIsArray($row);
        $records->update((int) $row['id'], ['is_active' => $isActive]);
    }

    /**
     * Порт коллекций.
     *
     * @return IAssetCollections Порт.
     */
    private function collections(): IAssetCollections
    {
        $collections = $this->application()
            ->getLocator()
            ->get(IAssetContainer::class)
            ->get(IAssetCollections::class);
        self::assertInstanceOf(IAssetCollections::class, $collections);

        return $collections;
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
     * @return IUserAccounts Порт.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Шлюз ST.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
