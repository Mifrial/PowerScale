<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Tests;

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
use Mifrial\Roleplay\Map\Repository\MapTerrainRepository;
use Mifrial\Roleplay\Map\Schema\MapSchema;
use Mifrial\Roleplay\Map\Table\MapTerrainTable;
use PHPUnit\Framework\TestCase;

final class MapHttpMysqlTest extends TestCase
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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Map HTTP tests');
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
     * Создание публикацией, чтение и видимость.
     *
     * @return void
     */
    public function testSaveCreateReadAndVisibility(): void
    {
        $anonymous = $this->dispatch('map.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertFalse($anonymous['success']);
        self::assertSame('AUTH_REQUIRED', $anonymous['error']['code']);

        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice, ['map.edit']);

        $public = $this->dispatch('map.save', ['map' => $this->emptyPayload('Austan', 'public')]);
        self::assertTrue($public['success']);
        self::assertSame('Austan', $public['data']['name']);
        self::assertTrue($public['data']['canEdit']);
        self::assertSame(1, $public['data']['version']);
        self::assertCount(1, $public['data']['variants']);
        self::assertSame([], $public['data']['nodes']);
        self::assertSame([], $public['data']['areas']);
        self::assertSame([], $public['data']['lines']);
        self::assertSame([], $public['data']['edges']);
        self::assertSame([], $public['data']['warnings']);

        $private = $this->dispatch('map.save', ['map' => $this->emptyPayload('Тайна', 'private')]);
        self::assertTrue($private['success']);

        $this->setActor($bob);
        $publicView = $this->dispatch('map.get', ['id' => $public['data']['id']]);
        self::assertTrue($publicView['success']);
        self::assertFalse($publicView['data']['canEdit']);
        $privateView = $this->dispatch('map.get', ['id' => $private['data']['id']]);
        self::assertFalse($privateView['success']);
        self::assertSame('MAP_DENIED', $privateView['error']['code']);

        $page = $this->dispatch('map.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame(1, $page['data']['total']);

        $this->setActor($bob, ['map.view_all']);
        $allView = $this->dispatch('map.get', ['id' => $private['data']['id']]);
        self::assertTrue($allView['success']);
        self::assertFalse($allView['data']['canEdit']);
        $allPage = $this->dispatch('map.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame(2, $allPage['data']['total']);

        $terrains = $this->dispatch('map.getTerrains', null);
        self::assertTrue($terrains['success']);
        self::assertCount(7, $terrains['data']);
    }

    /**
     * Граф, блокеры и маршрут.
     *
     * @return void
     */
    public function testGraphRouteAndWarnings(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, ['map.edit']);
        $created = $this->dispatch('map.save', ['map' => $this->emptyPayload('Austan')]);
        $mapId = $created['data']['id'];

        $payload = $this->graphPayload();
        $saved = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $created['data']['version'],
            'map' => $payload,
        ]);
        self::assertTrue($saved['success']);
        self::assertCount(2, $saved['data']['nodes']);
        self::assertCount(1, $saved['data']['areas']);
        self::assertCount(1, $saved['data']['edges']);
        self::assertNotEmpty($saved['data']['warnings']);
        self::assertSame('MAP_EDGE_CROSSES_BLOCKER', $saved['data']['warnings'][0]['code']);

        $nodeAId = $saved['data']['nodes'][0]['id'];
        $nodeBId = $saved['data']['nodes'][1]['id'];
        $variantId = $saved['data']['variants'][0]['id'];
        self::assertSame($variantId, $saved['data']['lines'][0]['variantId']);
        self::assertSame([60, 0], $saved['data']['lines'][0]['handles'][0]['out']);
        self::assertSame([-60, 0], $saved['data']['lines'][0]['handles'][1]['in']);
        self::assertNull($saved['data']['edges'][0]['distanceMeters']);

        $route = $this->dispatch('map.route', [
            'mapId' => $mapId,
            'variantId' => $variantId,
            'fromNodeId' => $nodeAId,
            'toNodeId' => $nodeBId,
        ]);
        self::assertTrue($route['success']);
        self::assertSame(100, $route['data']['distanceMeters']);
        self::assertSame(100, $route['data']['costMeters']);
        self::assertCount(3, $route['data']['points']);

        $withoutEdge = $this->withIds($payload, $saved['data']);
        $withoutEdge['edges'] = [];
        $removed = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $saved['data']['version'],
            'map' => $withoutEdge,
        ]);
        self::assertTrue($removed['success']);
        self::assertSame([], $removed['data']['edges']);
        $removedNodeIds = array_column($removed['data']['nodes'], 'id');
        $noRoute = $this->dispatch('map.route', [
            'mapId' => $mapId,
            'variantId' => $variantId,
            'fromNodeId' => $removedNodeIds[0],
            'toNodeId' => $removedNodeIds[1],
        ]);
        self::assertFalse($noRoute['success']);
        self::assertSame('MAP_NO_ROUTE', $noRoute['error']['code']);
    }

    /**
     * Область ссылается на новую точку того же снимка.
     *
     * @return void
     */
    public function testAreaLinksNewNodeOfSameSnapshot(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, ['map.edit']);
        $created = $this->dispatch('map.save', ['map' => $this->emptyPayload('Austan')]);
        $mapId = $created['data']['id'];

        $payload = $this->emptyPayload('Austan');
        $payload['nodes'] = [
            [
                'id' => -1,
                'isPoi' => true,
                'name' => 'Ратуша',
                'shortInfo' => '',
                'publicInfo' => '',
                'gmInfo' => '',
                'linkedMapId' => null,
                'icon' => '',
                'placements' => [
                    ['variantId' => -1, 'x' => 50, 'y' => 50, 'isPresent' => true, 'labelOverride' => ''],
                ],
            ],
        ];
        $payload['areas'] = [
            [
                'id' => -1,
                'layer' => 'object',
                'terrainCode' => null,
                'kind' => 'building',
                'label' => 'Ратуша',
                'nodeId' => -1,
                'blocksMovement' => true,
                'shapes' => [
                    [
                        'variantId' => -1,
                        'path' => [[40, 40], [60, 40], [60, 60], [40, 60]],
                        'isPresent' => true,
                    ],
                ],
            ],
        ];

        $saved = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $created['data']['version'],
            'map' => $payload,
        ]);
        self::assertTrue($saved['success']);
        self::assertCount(1, $saved['data']['nodes']);
        self::assertGreaterThan(0, $saved['data']['areas'][0]['id']);
        self::assertSame($saved['data']['nodes'][0]['id'], $saved['data']['areas'][0]['nodeId']);

        $view = $this->dispatch('map.get', ['id' => $mapId]);
        self::assertTrue($view['success']);
        self::assertSame($saved['data']['nodes'][0]['id'], $view['data']['areas'][0]['nodeId']);
    }

    /**
     * Валидация снимка и неизменность id.
     *
     * @return void
     */
    public function testValidationAndStableIds(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, ['map.edit']);
        $created = $this->dispatch('map.save', ['map' => $this->emptyPayload('Austan')]);
        $mapId = $created['data']['id'];

        $payload = $this->graphPayload();
        $first = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $created['data']['version'],
            'map' => $payload,
        ]);
        self::assertTrue($first['success']);

        $secondPayload = $this->withIds($payload, $first['data']);
        $secondPayload['nodes'][0]['name'] = 'Базар 2';
        $second = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $first['data']['version'],
            'map' => $secondPayload,
        ]);
        self::assertTrue($second['success']);
        self::assertSame(
            array_column($first['data']['nodes'], 'id'),
            array_column($second['data']['nodes'], 'id'),
        );
        self::assertSame($first['data']['edges'][0]['id'], $second['data']['edges'][0]['id']);
        self::assertSame('Базар 2', $second['data']['nodes'][0]['name']);

        $selfEdge = $payload;
        $selfEdge['edges'][0]['toNodeId'] = -1;
        $bad = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $second['data']['version'],
            'force' => true,
            'map' => $selfEdge,
        ]);
        self::assertFalse($bad['success']);
        self::assertSame('MAP_INVALID', $bad['error']['code']);

        $negative = $payload;
        $negative['nodes'][0]['placements'][0]['x'] = -1;
        $badPoint = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $second['data']['version'],
            'force' => true,
            'map' => $negative,
        ]);
        self::assertFalse($badPoint['success']);
        self::assertSame('MAP_INVALID', $badPoint['error']['code']);

        $noVersion = $this->dispatch('map.save', [
            'id' => $mapId,
            'map' => $payload,
        ]);
        self::assertFalse($noVersion['success']);
        self::assertSame('MAP_INVALID', $noVersion['error']['code']);
    }

    /**
     * Конфликт версий, force и копия.
     *
     * @return void
     */
    public function testVersionConflictForceAndCopy(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, ['map.edit']);
        $created = $this->dispatch('map.save', ['map' => $this->emptyPayload('Austan')]);
        $mapId = $created['data']['id'];
        $staleVersion = $created['data']['version'];

        $updated = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $staleVersion,
            'map' => $this->emptyPayload('Austan 2'),
        ]);
        self::assertTrue($updated['success']);
        self::assertSame(2, $updated['data']['version']);

        $conflict = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $staleVersion,
            'map' => $this->emptyPayload('Austan 3'),
        ]);
        self::assertFalse($conflict['success']);
        self::assertSame('MAP_VERSION_CONFLICT', $conflict['error']['code']);
        self::assertSame(2, $conflict['error']['details']['currentVersion']);
        self::assertSame($alice, $conflict['error']['details']['updatedBy']);

        $forced = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $staleVersion,
            'force' => true,
            'map' => $this->emptyPayload('Austan 3'),
        ]);
        self::assertTrue($forced['success']);
        self::assertSame('Austan 3', $forced['data']['name']);

        $copy = $this->dispatch('map.save', ['map' => $this->emptyPayload('Austan (копия)')]);
        self::assertTrue($copy['success']);
        self::assertNotSame($mapId, $copy['data']['id']);
        self::assertSame(1, $copy['data']['version']);
    }

    /**
     * Секреты видны только редактору; edit_all и архив.
     *
     * @return void
     */
    public function testGmInfoAndEditAll(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice, ['map.edit']);
        $payload = $this->emptyPayload('Тайна', 'private');
        $payload['nodes'][] = [
            'id' => -1,
            'isPoi' => true,
            'name' => 'Склад',
            'shortInfo' => 'коротко',
            'publicInfo' => 'публично',
            'gmInfo' => 'секрет',
            'linkedMapId' => null,
            'icon' => '',
            'placements' => [],
        ];
        $created = $this->dispatch('map.save', ['map' => $payload]);
        $mapId = $created['data']['id'];

        $this->setActor($bob, ['map.view_all']);
        $view = $this->dispatch('map.get', ['id' => $mapId]);
        self::assertTrue($view['success']);
        self::assertFalse($view['data']['canEdit']);
        self::assertSame('коротко', $view['data']['nodes'][0]['shortInfo']);
        self::assertArrayNotHasKey('publicInfo', $view['data']['nodes'][0]);
        self::assertArrayNotHasKey('gmInfo', $view['data']['nodes'][0]);

        $denied = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $view['data']['version'],
            'map' => $this->emptyPayload('Тайна'),
        ]);
        self::assertFalse($denied['success']);
        self::assertSame('MAP_DENIED', $denied['error']['code']);

        $this->setActor($bob, ['map.edit_all']);
        $edited = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $view['data']['version'],
            'map' => $payload,
        ]);
        self::assertTrue($edited['success']);
        self::assertArrayHasKey('gmInfo', $edited['data']['nodes'][0]);

        $archived = $this->dispatch('map.archive', ['id' => $mapId]);
        self::assertTrue($archived['success']);
        $page = $this->dispatch('map.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame(0, $page['data']['total']);
        $archivePage = $this->dispatch('map.findPage', ['limit' => 10, 'offset' => 0, 'isArchived' => true]);
        self::assertSame(1, $archivePage['data']['total']);
    }

    /**
     * Снимок пустой карты.
     *
     * @param string $name Имя.
     * @param string $visibility Видимость.
     *
     * @return array<string, mixed> Данные.
     */
    private function emptyPayload(string $name, string $visibility = 'private'): array
    {
        return [
            'name' => $name,
            'description' => '',
            'visibility' => $visibility,
            'variants' => [
                [
                    'id' => -1,
                    'label' => 'Основная',
                    'fromMinutes' => null,
                    'sortOrder' => 0,
                    'filePublicId' => null,
                    'millimetersPerPixel' => 1000,
                    'isDefault' => true,
                ],
            ],
            'nodes' => [],
            'areas' => [],
            'lines' => [],
            'edges' => [],
        ];
    }

    /**
     * Подставляет реальные id из снимка, как это делает клиент.
     *
     * @param array<string, mixed> $payload Данные.
     * @param array<string, mixed> $snapshot Снимок.
     *
     * @return array<string, mixed> Данные.
     */
    private function withIds(array $payload, array $snapshot): array
    {
        foreach (['variants', 'nodes', 'areas', 'lines', 'edges'] as $key) {
            foreach ($payload[$key] as $index => $row) {
                $payload[$key][$index]['id'] = $snapshot[$key][$index]['id'];
            }
        }

        $variantId = $snapshot['variants'][0]['id'];
        $nodeIds = array_column($snapshot['nodes'], 'id');
        foreach ($payload['nodes'] as $index => $node) {
            foreach ($node['placements'] as $placementIndex => $placement) {
                $payload['nodes'][$index]['placements'][$placementIndex]['variantId'] = $variantId;
            }
        }
        foreach ($payload['areas'] as $index => $area) {
            foreach ($area['shapes'] as $shapeIndex => $shape) {
                $payload['areas'][$index]['shapes'][$shapeIndex]['variantId'] = $variantId;
            }
        }
        foreach ($payload['lines'] as $index => $line) {
            $payload['lines'][$index]['variantId'] = $variantId;
        }
        foreach ($payload['edges'] as $index => $edge) {
            $payload['edges'][$index]['variantId'] = $variantId;
            $payload['edges'][$index]['fromNodeId'] = $nodeIds[0];
            $payload['edges'][$index]['toNodeId'] = $nodeIds[1];
        }

        return $payload;
    }

    /**
     * Два узла, вода поперёк ребра и одна дорога.
     *
     * @return array<string, mixed> Данные.
     */
    private function graphPayload(): array
    {
        return [
            'name' => 'Austan',
            'description' => '',
            'visibility' => 'private',
            'variants' => [
                [
                    'id' => -1,
                    'label' => 'Основная',
                    'fromMinutes' => null,
                    'sortOrder' => 0,
                    'filePublicId' => null,
                    'millimetersPerPixel' => 1000,
                    'isDefault' => true,
                ],
            ],
            'nodes' => [
                [
                    'id' => -1,
                    'isPoi' => true,
                    'name' => 'Базар',
                    'shortInfo' => 'шумно',
                    'publicInfo' => '',
                    'gmInfo' => '',
                    'linkedMapId' => null,
                    'icon' => '',
                    'placements' => [
                        ['variantId' => -1, 'x' => 10, 'y' => 10, 'isPresent' => true, 'labelOverride' => ''],
                    ],
                ],
                [
                    'id' => -2,
                    'isPoi' => true,
                    'name' => 'Замок',
                    'shortInfo' => '',
                    'publicInfo' => '',
                    'gmInfo' => '',
                    'linkedMapId' => null,
                    'icon' => '',
                    'placements' => [
                        ['variantId' => -1, 'x' => 110, 'y' => 10, 'isPresent' => true, 'labelOverride' => ''],
                    ],
                ],
            ],
            'areas' => [
                [
                    'id' => -1,
                    'layer' => 'terrain',
                    'terrainCode' => 'water',
                    'kind' => 'other',
                    'label' => 'Ручей',
                    'nodeId' => null,
                    'blocksMovement' => false,
                    'shapes' => [
                        [
                            'variantId' => -1,
                            'path' => [[40, 0], [60, 0], [60, 20], [40, 20]],
                            'isPresent' => true,
                        ],
                    ],
                ],
            ],
            'lines' => [
                [
                    'id' => -1,
                    'variantId' => -1,
                    'terrainCode' => 'water',
                    'points' => [[0, 60], [200, 60]],
                    'widths' => [20, 20],
                    'handles' => [
                        ['in' => [0, 0], 'out' => [60, 0]],
                        ['in' => [-60, 0], 'out' => [0, 0]],
                    ],
                    'label' => 'Река',
                    'isPresent' => true,
                ],
            ],
            'edges' => [
                [
                    'id' => -1,
                    'variantId' => -1,
                    'fromNodeId' => -1,
                    'toNodeId' => -2,
                    'terrainCode' => 'road',
                    'path' => [[10, 10], [50, 10], [110, 10]],
                    'distanceMeters' => null,
                    'isDirected' => false,
                    'label' => '',
                ],
            ],
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
        self::assertArrayHasKey('map.save', $application->getModuleManager()->getRoutes());
        self::assertArrayNotHasKey('map.saveNode', $application->getModuleManager()->getRoutes());
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
     * User + Map + террейны.
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
        foreach (MapSchema::getTableClasses() as $tableClass) {
            $gateway->open($tableClass)->schema()->createTable();
        }

        (new MapTerrainRepository($gateway->open(MapTerrainTable::class)->records()))
            ->ensureDefaults(MapTerrainTable::defaultTerrains());
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
        foreach (array_reverse(MapSchema::getTableClasses()) as $tableClass) {
            $this->deleteIfExists($gateway->open($tableClass));
        }

        $this->deleteIfExists($gateway->open(FileTable::class));
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
            'login' => $login . bin2hex(random_bytes(4)),
            'name' => $login,
        ]);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys = []): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, $permissionKeys, false));
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
