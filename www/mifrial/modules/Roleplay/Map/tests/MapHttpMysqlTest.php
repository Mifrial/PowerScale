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
use Mifrial\Roleplay\Map\Schema\MapSchema;
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
    }

    /**
     * Скорость, сегменты и маршрут по зоне.
     *
     * @return void
     */
    public function testSpeedRouteAndSegments(): void
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
        self::assertSame([], $saved['data']['warnings']);

        $variantId = $saved['data']['variants'][0]['id'];
        self::assertSame($variantId, $saved['data']['lines'][0]['variantId']);
        self::assertSame([60, 0], $saved['data']['lines'][0]['handles'][0]['out']);
        self::assertSame([-60, 0], $saved['data']['lines'][0]['handles'][1]['in']);
        self::assertSame('#5a7f9a', $saved['data']['areas'][0]['color']);
        self::assertSame(0, $saved['data']['areas'][0]['speed']);
        self::assertSame(
            array_fill(0, 4, ['in' => [0, 0], 'out' => [0, 0]]),
            $saved['data']['areas'][0]['shapes'][0]['handles'],
        );
        self::assertSame(0, $saved['data']['areas'][0]['sortOrder']);
        self::assertSame([], $saved['data']['lines'][0]['segments']);
        self::assertSame(400, $saved['data']['lines'][0]['speed']);
        self::assertSame(0, $saved['data']['lines'][0]['sortOrder']);

        $aroundWater = $this->dispatch('map.route', [
            'mapId' => $mapId,
            'variantId' => $variantId,
            'fromX' => 10,
            'fromY' => 10,
            'toX' => 110,
            'toY' => 10,
        ]);
        self::assertTrue($aroundWater['success']);
        self::assertGreaterThan(100, $aroundWater['data']['distancePixels']);
        self::assertGreaterThan(0, $aroundWater['data']['distanceMeters']);
        self::assertGreaterThan(2, count($aroundWater['data']['points']));

        $bridge = $this->withIds($payload, $saved['data']);
        $bridge['areas'][] = [
            'id' => -2,
            'layer' => 'object',
            'color' => '#9a8b73',
            'speed' => 10000,
            'label' => 'Мост',
            'nodeId' => null,
            'sortOrder' => 9,
            'shapes' => [
                [
                    'variantId' => -1,
                    'path' => [[40, 0], [60, 0], [60, 20], [40, 20]],
                    'handles' => [
                        ['in' => [0, 0], 'out' => [0, 4]],
                        ['in' => [0, 4], 'out' => [0, 0]],
                        ['in' => [0, 0], 'out' => [0, 0]],
                        ['in' => [0, 0], 'out' => [0, 0]],
                    ],
                    'isPresent' => true,
                ],
            ],
        ];
        $bridge['areas'][1]['shapes'][0]['variantId'] = $variantId;
        $bridgeSaved = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $saved['data']['version'],
            'map' => $bridge,
        ]);
        self::assertTrue($bridgeSaved['success']);
        self::assertSame([0, 4], $bridgeSaved['data']['areas'][1]['shapes'][0]['handles'][0]['out']);

        $overBridge = $this->dispatch('map.route', [
            'mapId' => $mapId,
            'variantId' => $variantId,
            'fromX' => 10,
            'fromY' => 10,
            'toX' => 110,
            'toY' => 10,
        ]);
        self::assertTrue($overBridge['success']);
        self::assertLessThan($aroundWater['data']['distancePixels'], $overBridge['data']['distancePixels']);

        $withRoad = $this->withIds($payload, $bridgeSaved['data']);
        $withRoad['lines'][] = [
            'id' => -2,
            'variantId' => -1,
            'color' => '#C5C6C6',
            'speed' => 10000,
            'points' => [[0, 10], [200, 10]],
            'widths' => [8, 8],
            'handles' => [],
            'segments' => [],
            'label' => 'Дорога',
            'isPresent' => true,
            'sortOrder' => 1,
        ];
        $withRoad['lines'][1]['variantId'] = $variantId;
        $roadSaved = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $bridgeSaved['data']['version'],
            'map' => $withRoad,
        ]);
        self::assertTrue($roadSaved['success']);

        $alongRoad = $this->dispatch('map.route', [
            'mapId' => $mapId,
            'variantId' => $variantId,
            'fromX' => 10,
            'fromY' => 10,
            'toX' => 110,
            'toY' => 10,
        ]);
        self::assertTrue($alongRoad['success']);
        self::assertLessThan($aroundWater['data']['distancePixels'], $alongRoad['data']['distancePixels']);

        $withSegments = $this->withIds($payload, $roadSaved['data']);
        $withSegments['lines'][0]['segments'] = [['speed' => 1000, 'color' => '#B7A98A']];
        $segmentsSaved = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $roadSaved['data']['version'],
            'map' => $withSegments,
        ]);
        self::assertTrue($segmentsSaved['success']);
        self::assertSame(
            [['speed' => 1000, 'color' => '#B7A98A']],
            $segmentsSaved['data']['lines'][0]['segments'],
        );

        $blocked = $this->withIds($payload, $segmentsSaved['data']);
        $blocked['areas'][] = [
            'id' => -3,
            'layer' => 'object',
            'color' => '#000000',
            'speed' => 0,
            'label' => 'Стена',
            'nodeId' => null,
            'sortOrder' => 10,
            'shapes' => [
                [
                    'variantId' => -1,
                    'path' => [[0, 0], [200, 0], [200, 200], [0, 200]],
                    'isPresent' => true,
                ],
            ],
        ];
        $blocked['areas'][1]['shapes'][0]['variantId'] = $variantId;
        $blockedSaved = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $segmentsSaved['data']['version'],
            'map' => $blocked,
        ]);
        self::assertTrue($blockedSaved['success']);

        $noRoute = $this->dispatch('map.route', [
            'mapId' => $mapId,
            'variantId' => $variantId,
            'fromX' => 10,
            'fromY' => 10,
            'toX' => 110,
            'toY' => 10,
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
                'color' => '#9e9e9e',
                'speed' => 0,
                'label' => 'Ратуша',
                'nodeId' => -1,
                'sortOrder' => 0,
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
        self::assertSame('Базар 2', $second['data']['nodes'][0]['name']);

        $secondPayload['nodes'][0]['name'] = '';
        $bad = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $second['data']['version'],
            'force' => true,
            'map' => $secondPayload,
        ]);
        self::assertFalse($bad['success']);
        self::assertSame('MAP_INVALID', $bad['error']['code']);

        $secondPayload['nodes'][0]['name'] = 'Базар 2';
        $secondPayload['areas'][0]['color'] = 'red';
        $badColor = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $second['data']['version'],
            'force' => true,
            'map' => $secondPayload,
        ]);
        self::assertFalse($badColor['success']);
        self::assertSame('MAP_INVALID', $badColor['error']['code']);

        $secondPayload['areas'][0]['color'] = '#5a7f9a';
        $secondPayload['areas'][0]['speed'] = 20000;
        $badSpeed = $this->dispatch('map.save', [
            'id' => $mapId,
            'expectedVersion' => $second['data']['version'],
            'force' => true,
            'map' => $secondPayload,
        ]);
        self::assertFalse($badSpeed['success']);
        self::assertSame('MAP_INVALID', $badSpeed['error']['code']);

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
        foreach (['variants', 'nodes', 'areas', 'lines'] as $key) {
            foreach ($payload[$key] as $index => $row) {
                $payload[$key][$index]['id'] = $snapshot[$key][$index]['id'];
            }
        }

        $variantId = $snapshot['variants'][0]['id'];
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
        return $payload;
    }

    /**
     * Два места, вода и река.
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
                    'layer' => 'surface',
                    'color' => '#5a7f9a',
                    'speed' => 0,
                    'label' => 'Ручей',
                    'nodeId' => null,
                    'sortOrder' => 0,
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
                    'color' => '#3F6F8F',
                    'speed' => 400,
                    'points' => [[0, 60], [200, 60]],
                    'widths' => [20, 20],
                    'handles' => [
                        ['in' => [0, 0], 'out' => [60, 0]],
                        ['in' => [-60, 0], 'out' => [0, 0]],
                    ],
                    'segments' => [],
                    'label' => 'Река',
                    'isPresent' => true,
                    'sortOrder' => 0,
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
        self::assertArrayNotHasKey('map.getTerrains', $application->getModuleManager()->getRoutes());
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
     * User + Map.
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
