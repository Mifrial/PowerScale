<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Schema\MechanicSchema;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use PHPUnit\Framework\TestCase;

final class MechanicMysqlTest extends TestCase
{
    private ?IMechanics $mechanics = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectMechanic();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Mechanic tests');
        }

        $this->dropMechanicTable();
        $this->installSchema();
    }

    /**
     * Снимает таблицу.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropMechanicTable();
    }

    /**
     * Две поставки одного семейства и чтение.
     *
     * @return void
     */
    public function testAddTwoVersionsOfSameCode(): void
    {
        $mechanics = $this->mechanics();
        $firstId = $mechanics->add('  six_one_rule  ', '  6 и 1  ', '', '  1  ');
        $secondId = $mechanics->add('six_one_rule', '6 и 1', 'вторая', '2');
        self::assertNotSame($firstId, $secondId);
        $first = $mechanics->get($firstId);
        self::assertSame('six_one_rule', $first->getCode());
        self::assertSame('6 и 1', $first->getName());
        self::assertSame('', $first->getDescription());
        self::assertSame('1', $first->getHandlerVersion());
        $byPair = $mechanics->getByCodeVersion(' six_one_rule ', ' 2 ');
        self::assertSame($secondId, $byPair->getId());
        self::assertSame('вторая', $byPair->getDescription());
    }

    /**
     * Дубль пары code+version — MECHANIC_INVALID.
     *
     * @return void
     */
    public function testDuplicateCodeVersionIsInvalid(): void
    {
        $mechanics = $this->mechanics();
        $mechanics->add('six_one_rule', 'A', '', '1');
        try {
            $mechanics->add('six_one_rule', 'B', '', '1');
            self::fail('duplicate pair must fail');
        } catch (MechanicInvalidException $exception) {
            self::assertSame('MECHANIC_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустые поля после trim.
     *
     * @return void
     */
    public function testEmptyFieldsAreInvalid(): void
    {
        $mechanics = $this->mechanics();
        try {
            $mechanics->add('   ', 'Name', '', '1');
            self::fail('empty code must fail');
        } catch (MechanicInvalidException $exception) {
            self::assertSame('MECHANIC_INVALID', $exception->getErrorCode());
        }

        try {
            $mechanics->add('code', '  ', '', '1');
            self::fail('empty name must fail');
        } catch (MechanicInvalidException $exception) {
            self::assertSame('MECHANIC_INVALID', $exception->getErrorCode());
        }

        try {
            $mechanics->add('code', 'Name', '', '  ');
            self::fail('empty version must fail');
        } catch (MechanicInvalidException $exception) {
            self::assertSame('MECHANIC_INVALID', $exception->getErrorCode());
        }

        try {
            $mechanics->getByCodeVersion('  ', '1');
            self::fail('empty getByCodeVersion code must fail');
        } catch (MechanicInvalidException $exception) {
            self::assertSame('MECHANIC_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет строки — MECHANIC_NOT_FOUND.
     *
     * @return void
     */
    public function testMissingRowIsNotFound(): void
    {
        $mechanics = $this->mechanics();
        try {
            $mechanics->get(1);
            self::fail('missing id must fail');
        } catch (MechanicNotFoundException $exception) {
            self::assertSame('MECHANIC_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $mechanics->getByCodeVersion('missing', '1');
            self::fail('missing pair must fail');
        } catch (MechanicNotFoundException $exception) {
            self::assertSame('MECHANIC_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * getList sort id ASC; update name/description; code иммутабелен.
     *
     * @return void
     */
    public function testGetListAndUpdate(): void
    {
        $mechanics = $this->mechanics();
        $firstId = $mechanics->add('six_one_rule', 'A', '  note  ', '1');
        $secondId = $mechanics->add('roll', 'B', '', '1');
        $list = $mechanics->getList();
        self::assertCount(2, $list);
        self::assertSame($firstId, $list[0]->getId());
        self::assertSame($secondId, $list[1]->getId());
        self::assertSame('note', $list[0]->getDescription());

        $mechanics->update($firstId, '  Six  ', '  ');
        $updated = $mechanics->get($firstId);
        self::assertSame('Six', $updated->getName());
        self::assertSame('', $updated->getDescription());
        self::assertSame('six_one_rule', $updated->getCode());
        self::assertSame('1', $updated->getHandlerVersion());

        try {
            $mechanics->update($firstId, '  ', 'x');
            self::fail('empty name must fail');
        } catch (MechanicInvalidException $exception) {
            self::assertSame('MECHANIC_INVALID', $exception->getErrorCode());
        }

        try {
            $mechanics->update(999, 'Name', '');
            self::fail('missing update must fail');
        } catch (MechanicNotFoundException $exception) {
            self::assertSame('MECHANIC_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * boot() и ленивый get.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectMechanic(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $mechanicContainer = $application->getLocator()->get(IMechanicContainer::class);
        $mechanics = $mechanicContainer->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $this->mechanics = $mechanics;
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
     * Ставит таблицу mechanic.
     *
     * @return void
     */
    private function installSchema(): void
    {
        $schema = $this->smartTableGateway()->open(MechanicTable::class)->schema();
        (new MechanicSchema($schema))->install();
    }

    /**
     * Сносит mechanic, если есть.
     *
     * @return void
     */
    private function dropMechanicTable(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $schema = $this->smartTableGateway->open(MechanicTable::class)->schema();
        if ($schema->exists()) {
            $schema->deleteTable();
        }
    }

    /**
     * Фасад после setUp.
     *
     * @return IMechanics Фасад.
     */
    private function mechanics(): IMechanics
    {
        self::assertInstanceOf(IMechanics::class, $this->mechanics);

        return $this->mechanics;
    }

    /**
     * Шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
