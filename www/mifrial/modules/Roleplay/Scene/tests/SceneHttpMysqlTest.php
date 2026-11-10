<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Tests;

use Mifrial\Core\File\Schema\FileSchema;
use Mifrial\Core\File\Table\FileTable;
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
use Mifrial\Core\User\Table\UserAvatarTable;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Roleplay\Scene\Schema\SceneSchema;
use Mifrial\Roleplay\Scene\Service\ScenePermissionKeys;
use PHPUnit\Framework\TestCase;

final class SceneHttpMysqlTest extends TestCase
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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Scene HTTP tests');
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
     * Аноним и пользователь без ключей.
     *
     * @return void
     */
    public function testAuthRequiredAndDenied(): void
    {
        $anonymous = $this->dispatch('scene.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertFalse($anonymous['success']);
        self::assertSame('AUTH_REQUIRED', $anonymous['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $denied = $this->dispatch('scene.save', ['scene' => $this->payload()]);
        self::assertFalse($denied['success']);
        self::assertSame('AUTH_DENIED', $denied['error']['code']);

        $empty = $this->dispatch('scene.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertTrue($empty['success']);
        self::assertSame(0, $empty['data']['total']);
    }

    /**
     * Создание, чтение, правка, конфликт, архив и доступ.
     *
     * @return void
     */
    public function testCreateReadUpdateConflictArchiveAndAccess(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice, [ScenePermissionKeys::EDIT]);
        $created = $this->dispatch('scene.save', ['scene' => $this->payload()]);
        self::assertTrue($created['success']);
        self::assertSame('Таверна', $created['data']['name']);
        self::assertTrue($created['data']['canEdit']);
        self::assertSame(1, $created['data']['version']);
        self::assertCount(2, $created['data']['boards']);
        self::assertCount(6, $created['data']['elements']);
        self::assertCount(1, $created['data']['enclosures']);
        $sceneId = $created['data']['id'];
        $boardIds = [$created['data']['boards'][0]['id'], $created['data']['boards'][1]['id']];
        self::assertSame($boardIds[1], $created['data']['elements'][5]['targetBoardId']);
        self::assertSame($boardIds[0], $created['data']['enclosures'][0]['parentBoardId']);
        self::assertSame($boardIds[1], $created['data']['enclosures'][0]['childBoardId']);

        $got = $this->dispatch('scene.get', ['id' => $sceneId]);
        self::assertTrue($got['success']);
        self::assertCount(6, $got['data']['elements']);

        $page = $this->dispatch('scene.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertTrue($page['success']);
        self::assertSame(1, $page['data']['total']);

        $savePayload = $this->savePayload($created['data']);
        $savePayload['name'] = 'Таверна 2';
        array_pop($savePayload['elements']);
        $savePayload['elements'][] = [
            'id' => -30,
            'boardId' => $boardIds[1],
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 100, 'y' => 100],
            'props' => ['label' => 'Стол'],
            'sortOrder' => 10,
            'targetBoardId' => null,
        ];
        $updated = $this->dispatch('scene.save', [
            'id' => $sceneId,
            'expectedVersion' => $created['data']['version'],
            'scene' => $savePayload,
        ]);
        self::assertTrue($updated['success']);
        self::assertSame('Таверна 2', $updated['data']['name']);
        self::assertSame(2, $updated['data']['version']);
        self::assertCount(6, $updated['data']['elements']);

        $conflict = $this->dispatch('scene.save', [
            'id' => $sceneId,
            'expectedVersion' => 1,
            'scene' => $savePayload,
        ]);
        self::assertFalse($conflict['success']);
        self::assertSame('SCENE_VERSION_CONFLICT', $conflict['error']['code']);
        self::assertSame(2, $conflict['error']['details']['currentVersion']);

        $this->setActor($bob);
        $denied = $this->dispatch('scene.get', ['id' => $sceneId]);
        self::assertFalse($denied['success']);
        self::assertSame('SCENE_DENIED', $denied['error']['code']);

        $this->setActor($bob, [ScenePermissionKeys::VIEW_ALL]);
        $viewAll = $this->dispatch('scene.get', ['id' => $sceneId]);
        self::assertTrue($viewAll['success']);
        self::assertFalse($viewAll['data']['canEdit']);

        $this->setActor($alice, [ScenePermissionKeys::EDIT]);
        $archived = $this->dispatch('scene.archive', ['id' => $sceneId]);
        self::assertTrue($archived['success']);
        $hidden = $this->dispatch('scene.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame(0, $hidden['data']['total']);
        $archivedPage = $this->dispatch('scene.findPage', [
            'limit' => 10,
            'offset' => 0,
            'isArchived' => true,
        ]);
        self::assertSame(1, $archivedPage['data']['total']);
    }

    /**
     * Валидация снимка.
     *
     * @return void
     */
    public function testValidation(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [ScenePermissionKeys::EDIT]);

        $outside = $this->payload();
        $outside['elements'][0]['shape']['x'] = 3000;
        $this->assertInvalid($outside);

        $unknownKind = $this->payload();
        $unknownKind['elements'][0]['kind'] = 'tree';
        $this->assertInvalid($unknownKind);

        $duplicateBoards = $this->payload();
        $duplicateBoards['boards'][1]['name'] = $duplicateBoards['boards'][0]['name'];
        $this->assertInvalid($duplicateBoards);

        $unknownTarget = $this->payload();
        $unknownTarget['elements'][5]['targetBoardId'] = -99;
        $this->assertInvalid($unknownTarget);

        $cycle = $this->payload();
        $cycle['enclosures'][] = [
            'id' => -21,
            'parentBoardId' => -2,
            'childBoardId' => -1,
            'footprint' => ['type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 100],
            'sortOrder' => 1,
        ];
        $this->assertInvalid($cycle);
    }

    /**
     * Снимок недопустим.
     *
     * @param array<string|int, mixed> $payload Снимок.
     *
     * @return void
     */
    private function assertInvalid(array $payload): void
    {
        $result = $this->dispatch('scene.save', ['scene' => $payload]);
        self::assertFalse($result['success']);
        self::assertSame('SCENE_INVALID', $result['error']['code']);
    }

    /**
     * Полный снимок для теста.
     *
     * @return array<string, mixed> Снимок.
     */
    private function payload(): array
    {
        return [
            'name' => 'Таверна',
            'description' => '',
            'resolution' => 64,
            'schemaVersion' => 1,
            'boards' => [
                [
                    'id' => -1,
                    'name' => 'Двор',
                    'sortOrder' => 0,
                    'filePublicId' => null,
                    'boundWidth' => 2560,
                    'boundHeight' => 2560,
                    'occupancyCell' => 16,
                    'zQuantum' => 16,
                    'gridStep' => 32,
                ],
                [
                    'id' => -2,
                    'name' => '1 этаж',
                    'sortOrder' => 1,
                    'filePublicId' => null,
                    'boundWidth' => 1280,
                    'boundHeight' => 1024,
                    'occupancyCell' => 16,
                    'zQuantum' => 16,
                    'gridStep' => 0,
                ],
            ],
            'elements' => [
                [
                    'id' => -10,
                    'boardId' => -1,
                    'kind' => 'obstacle',
                    'shape' => ['type' => 'rect', 'x' => 100, 'y' => 100, 'w' => 200, 'h' => 50],
                    'props' => ['blocksMovement' => true, 'blocksVision' => true, 'height' => 100],
                    'sortOrder' => 0,
                    'targetBoardId' => null,
                ],
                [
                    'id' => -11,
                    'boardId' => -1,
                    'kind' => 'support',
                    'shape' => ['type' => 'rect', 'x' => 400, 'y' => 100, 'w' => 300, 'h' => 120],
                    'props' => ['kind' => 'deck', 'height' => 50],
                    'sortOrder' => 1,
                    'targetBoardId' => null,
                ],
                [
                    'id' => -12,
                    'boardId' => -1,
                    'kind' => 'terrain',
                    'shape' => ['type' => 'circle', 'cx' => 600, 'cy' => 600, 'r' => 100],
                    'props' => ['kind' => 'mud', 'label' => 'Грязь'],
                    'sortOrder' => 2,
                    'targetBoardId' => null,
                ],
                [
                    'id' => -13,
                    'boardId' => -1,
                    'kind' => 'decor',
                    'shape' => ['type' => 'point', 'x' => 800, 'y' => 800],
                    'props' => ['rotation' => 0, 'scale' => 100, 'label' => 'Бочка'],
                    'sortOrder' => 3,
                    'targetBoardId' => null,
                ],
                [
                    'id' => -14,
                    'boardId' => -1,
                    'kind' => 'marker',
                    'shape' => ['type' => 'point', 'x' => 900, 'y' => 900],
                    'props' => ['role' => 'entrance', 'label' => 'Вход'],
                    'sortOrder' => 4,
                    'targetBoardId' => null,
                ],
                [
                    'id' => -15,
                    'boardId' => -1,
                    'kind' => 'opening',
                    'shape' => ['type' => 'wall', 'points' => [[100, 500], [300, 500]], 'width' => 16],
                    'props' => ['kind' => 'door', 'state' => 'open', 'blocksVision' => false],
                    'sortOrder' => 5,
                    'targetBoardId' => -2,
                ],
            ],
            'enclosures' => [
                [
                    'id' => -20,
                    'parentBoardId' => -1,
                    'childBoardId' => -2,
                    'footprint' => ['type' => 'rect', 'x' => 1000, 'y' => 1000, 'w' => 600, 'h' => 400],
                    'sortOrder' => 0,
                ],
            ],
        ];
    }

    /**
     * Снимок детали в форму сохранения.
     *
     * @param array<string, mixed> $detail Деталь.
     *
     * @return array<string, mixed> Снимок.
     */
    private function savePayload(array $detail): array
    {
        $boards = [];
        foreach ($detail['boards'] as $board) {
            $boards[] = [
                'id' => $board['id'],
                'name' => $board['name'],
                'sortOrder' => $board['sortOrder'],
                'filePublicId' => $board['baseFile']['publicId'] ?? null,
                'boundWidth' => $board['boundWidth'],
                'boundHeight' => $board['boundHeight'],
                'occupancyCell' => $board['occupancyCell'],
                'zQuantum' => $board['zQuantum'],
                'gridStep' => $board['gridStep'],
            ];
        }

        $elements = [];
        foreach ($detail['elements'] as $element) {
            $elements[] = [
                'id' => $element['id'],
                'boardId' => $element['boardId'],
                'kind' => $element['kind'],
                'shape' => $element['shape'],
                'props' => $element['props'] + (($element['asset']['publicId'] ?? null) === null
                    ? []
                    : ['assetPublicId' => $element['asset']['publicId']]),
                'sortOrder' => $element['sortOrder'],
                'targetBoardId' => $element['targetBoardId'],
            ];
        }

        $enclosures = [];
        foreach ($detail['enclosures'] as $enclosure) {
            $enclosures[] = [
                'id' => $enclosure['id'],
                'parentBoardId' => $enclosure['parentBoardId'],
                'childBoardId' => $enclosure['childBoardId'],
                'footprint' => $enclosure['footprint'],
                'sortOrder' => $enclosure['sortOrder'],
            ];
        }

        return [
            'name' => $detail['name'],
            'description' => $detail['description'],
            'resolution' => $detail['resolution'],
            'schemaVersion' => $detail['schemaVersion'],
            'boards' => $boards,
            'elements' => $elements,
            'enclosures' => $enclosures,
        ];
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
        self::assertArrayHasKey('scene.findPage', $application->getModuleManager()->getRoutes());
        self::assertArrayHasKey('scene.get', $application->getModuleManager()->getRoutes());
        self::assertArrayHasKey('scene.save', $application->getModuleManager()->getRoutes());
        self::assertArrayHasKey('scene.archive', $application->getModuleManager()->getRoutes());
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
     * User + File + Scene.
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
        foreach (SceneSchema::getTableClasses() as $tableClass) {
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
        foreach (array_reverse(SceneSchema::getTableClasses()) as $tableClass) {
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
