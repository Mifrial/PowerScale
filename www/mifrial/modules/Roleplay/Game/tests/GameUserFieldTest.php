<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\SmartTable\Service\Query\FieldPathWalker;
use Mifrial\Core\User\Field\UserLinkSetField;
use Mifrial\Core\User\Field\UserReferenceField;
use Mifrial\Roleplay\Game\Table\GameTable;
use PHPUnit\Framework\TestCase;

final class GameUserFieldTest extends TestCase
{
    /**
     * Ссылка на владельца и белый список объявлены типами User.
     *
     * @return void
     */
    public function testUserFieldsUseModuleTypes(): void
    {
        $map = (new GameTable())->getMap();
        self::assertInstanceOf(UserReferenceField::class, $map['owner_id']);
        self::assertInstanceOf(UserLinkSetField::class, $map['whitelist']);
        self::assertSame('user', $map['owner_id']->targetTableName());
    }

    /**
     * Hop owner_id.login идёт по class-string, без словаря.
     *
     * @return void
     */
    public function testOwnerLoginHopResolvesByClass(): void
    {
        $path = (new FieldPathWalker())->resolve(new GameTable(), 'owner_id.login');
        self::assertSame('login', $path->leafField()->name());
        self::assertSame('user', $path->leafTable()->getName());
    }
}
