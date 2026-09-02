<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Service\Setup\TableSetupOrder;
use Mifrial\Core\Kernel\Tests\Fixture\CycleLeftTable;
use Mifrial\Core\Kernel\Tests\Fixture\CycleRightTable;
use Mifrial\Core\Kernel\Tests\Fixture\DupNameAlphaTable;
use Mifrial\Core\Kernel\Tests\Fixture\DupNameBetaTable;
use Mifrial\Core\Kernel\Tests\Fixture\NoneChildTable;
use Mifrial\Core\Kernel\Tests\Fixture\NoneParentTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathChildTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathOwnerTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathParentTable;
use Mifrial\Core\SmartTable\Tests\Fixture\SelfRefTable;
use Mifrial\Core\User\Schema\UserSchema;
use PHPUnit\Framework\TestCase;

final class TableSetupOrderTest extends TestCase
{
    /**
     * Child в списке раньше parent: физика parent (и owner) раньше.
     *
     * @return void
     */
    public function testChildListedFirstStillCreatesParentsFirst(): void
    {
        $ordered = (new TableSetupOrder())->order([
            PathChildTable::class,
            PathParentTable::class,
            PathOwnerTable::class,
        ]);
        $names = [];
        foreach ($ordered as $definition) {
            $names[] = $definition->getName();
        }

        self::assertSame(['st_path_owner', 'st_path_parent', 'st_path_child'], $names);
    }

    /**
     * Карты User: членство после user и группы.
     *
     * @return void
     */
    public function testUserMemberAfterParents(): void
    {
        $ordered = (new TableSetupOrder())->order(UserSchema::tableClasses());
        $names = [];
        foreach ($ordered as $definition) {
            $names[] = $definition->getName();
        }

        self::assertSame(['user', 'user_group', 'user_group_member'], $names);
    }

    /**
     * Self-ref не блокирует стол.
     *
     * @return void
     */
    public function testSelfReferenceIsReady(): void
    {
        $ordered = (new TableSetupOrder())->order([SelfRefTable::class]);

        self::assertSame('st_ref_self', $ordered[0]->getName());
    }

    /**
     * onDelete none не даёт ребра: child может быть раньше parent.
     *
     * @return void
     */
    public function testNoneOnDeleteIsNotAnEdge(): void
    {
        $ordered = (new TableSetupOrder())->order([NoneChildTable::class, NoneParentTable::class]);
        $names = [];
        foreach ($ordered as $definition) {
            $names[] = $definition->getName();
        }

        self::assertSame(['st_setup_none_child', 'st_setup_none_parent'], $names);
    }

    /**
     * Два класса, одно физ. имя — отказ до DDL.
     *
     * @return void
     */
    public function testDuplicatePhysicalNameFails(): void
    {
        try {
            (new TableSetupOrder())->order([DupNameAlphaTable::class, DupNameBetaTable::class]);
            self::fail('Expected SetupException');
        } catch (SetupException $exception) {
            self::assertSame('SETUP_DUPLICATE_TABLE', $exception->getErrorCode());
        }
    }

    /**
     * Цикл A→B и B→A — отказ.
     *
     * @return void
     */
    public function testCycleFails(): void
    {
        try {
            (new TableSetupOrder())->order([CycleLeftTable::class, CycleRightTable::class]);
            self::fail('Expected SetupException');
        } catch (SetupException $exception) {
            self::assertSame('SETUP_CYCLE', $exception->getErrorCode());
        }
    }
}
