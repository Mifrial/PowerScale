<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameInputNormalizer;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Service\GameWorldGate;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use PHPUnit\Framework\TestCase;

final class GameUnitTest extends TestCase
{
    /**
     * Trim имени, пустой потолок и enum.
     *
     * @return void
     */
    public function testNormalizerTrimsNameAndKeepsNullLimit(): void
    {
        $normalizer = new GameInputNormalizer();
        self::assertSame('Hero', $normalizer->name('  Hero  '));
        self::assertNull($normalizer->limit(null));
        self::assertSame(0, $normalizer->limit(0));
        self::assertSame('paused', $normalizer->status('paused'));
        try {
            $normalizer->status('playing');
            self::fail('playing must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * completed — признак записи.
     *
     * @return void
     */
    public function testCompletedFlag(): void
    {
        $patch = GamePatch::fromNormalized([
            'name' => 'Done',
            'status' => 'completed',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
            'rulesRevision' => 1,
        ]);
        self::assertSame('completed', $patch->getStatus());
    }

    /**
     * Выключенный мир не читает ревизию.
     *
     * @return void
     */
    public function testInactiveWorldSkipsRevision(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->expects(self::once())->method('get')->with(4)->willReturn($this->inactiveWorld());
        $ruleSpaces->expects(self::never())->method('getRevision');
        $gate = new GameWorldGate($ruleSpaces);
        try {
            $gate->requireCode(4, 1);
            self::fail('inactive world must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустое имя.
     *
     * @return void
     */
    public function testEmptyName(): void
    {
        $newGame = NewGame::fromNormalized([
            'ownerUserId' => 1,
            'name' => '   ',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => 1,
            'rulesRevision' => 1,
        ]);
        try {
            (new GameInputNormalizer())->name($newGame->getName());
            self::fail('empty name must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Роль gm/player и вывод ключей.
     *
     * @return void
     */
    public function testMemberRoleAndKeys(): void
    {
        $normalizer = new GameInputNormalizer();
        self::assertSame('gm', $normalizer->memberRole('gm'));
        self::assertSame('player', $normalizer->memberRole('player'));
        try {
            $normalizer->memberRole('owner');
            self::fail('owner role must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }

        self::assertSame(
            [GamePermissionKeys::EDIT, GamePermissionKeys::MODERATE, GamePermissionKeys::MANAGE],
            GamePermissionKeys::keysFor(true, null),
        );
        self::assertSame(
            [GamePermissionKeys::EDIT, GamePermissionKeys::MODERATE],
            GamePermissionKeys::keysFor(false, 'gm'),
        );
        self::assertSame([], GamePermissionKeys::keysFor(false, 'player'));
    }

    /**
     * Выключенный мир.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function inactiveWorld(): RuleSpaceRecord
    {
        return RuleSpaceRecord::fromNormalized([
            'space_id' => 4,
            'code' => 'off',
            'name' => 'Off',
            'owner_id' => 1,
            'description' => '',
            'active' => false,
            'created_at' => DateTime::fromUnix(1),
        ]);
    }
}
