<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use PHPUnit\Framework\TestCase;

final class GameMysqlTest extends TestCase
{
    use GameMysqlFixture;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectGameMysql();
    }

    /**
     * Снимает таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropGameTables();
    }

    /**
     * add+get копирует код мира и оставляет null потолка.
     *
     * @return void
     */
    public function testAddGetCopiesSpaceCodeAndNullLimit(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $gameId = $this->gameFacade()->add($this->newGame($world->getId(), [
            'name' => '  Tale  ',
            'osPointsLimit' => null,
        ]));
        $record = $this->gameFacade()->get($gameId);
        self::assertSame('Tale', $record->getName());
        self::assertSame('razrabotka', $record->getSpaceCode());
        self::assertNull($record->getOsPointsLimit());
        self::assertSame('draft', $record->getStatus());
        self::assertSame(1, $record->getRulesRevision());
        self::assertSame($this->ownerUserId, $record->getOwnerId());
    }

    /**
     * Нет user, мира и ревизии.
     *
     * @return void
     */
    public function testMissingOwnerWorldAndRevision(): void
    {
        $world = $this->addWorldWithRevision('world');
        $this->expectNotFound(fn () => $this->gameFacade()->add($this->newGame($world->getId(), [
            'ownerUserId' => 999,
        ])));
        $this->expectNotFound(fn () => $this->gameFacade()->add($this->newGame(999)));
        $this->expectNotFound(fn () => $this->gameFacade()->add($this->newGame($world->getId(), [
            'rulesRevision' => 9,
        ])));
    }

    /**
     * Выключенный мир не пишет строку.
     *
     * @return void
     */
    public function testInactiveWorldDoesNotInsert(): void
    {
        $world = $this->addWorldWithRevision('off');
        $this->gameRuleSpaces()->deactivate($world->getId());
        try {
            $this->gameFacade()->add($this->newGame($world->getId()));
            self::fail('inactive world must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }

        self::assertSame([], $this->gameFacade()->getListByOwnerOrAll($this->ownerUserId, true));
    }

    /**
     * Пустое имя, нулевая ревизия, playing, чужая visibility, отрицательный потолок.
     *
     * @return void
     */
    public function testInvalidInputs(): void
    {
        $world = $this->addWorldWithRevision('world');
        $this->expectInvalid(fn () => $this->gameFacade()->add($this->newGame($world->getId(), ['name' => '  '])));
        $this->expectInvalid(fn () => $this->gameFacade()->add($this->newGame($world->getId(), ['rulesRevision' => 0])));
        $this->expectInvalid(fn () => $this->gameFacade()->add($this->newGame($world->getId(), ['status' => 'playing'])));
        $this->expectInvalid(fn () => $this->gameFacade()->add($this->newGame($world->getId(), ['visibility' => 'secret'])));
        $this->expectInvalid(fn () => $this->gameFacade()->add($this->newGame($world->getId(), ['osPointsLimit' => -1])));
    }

    /**
     * Update меняет имя, статус и потолок и не трогает мир.
     *
     * @return void
     */
    public function testUpdateKeepsWorld(): void
    {
        $world = $this->addWorldWithRevision('world');
        $gameId = $this->gameFacade()->add($this->newGame($world->getId(), [
            'status' => 'paused',
            'osPointsLimit' => 3,
        ]));
        $updated = $this->gameFacade()->update($gameId, $this->patch([
            'name' => 'Next',
            'status' => 'recruiting',
            'osPointsLimit' => null,
        ]));
        self::assertSame('Next', $updated->getName());
        self::assertSame('recruiting', $updated->getStatus());
        self::assertNull($updated->getOsPointsLimit());
        self::assertSame($world->getId(), $updated->getSpaceId());
        self::assertSame(1, $updated->getRulesRevision());
    }

    /**
     * completed отвергает следующую запись.
     *
     * @return void
     */
    public function testCompletedRejectsNextWrite(): void
    {
        $world = $this->addWorldWithRevision('world');
        $gameId = $this->gameFacade()->add($this->newGame($world->getId()));
        $closed = $this->gameFacade()->update($gameId, $this->patch(['status' => 'completed']));
        self::assertTrue($closed->isCompleted());
        $this->expectInvalid(fn () => $this->gameFacade()->update($gameId, $this->patch(['name' => 'Later'])));
        self::assertSame('Tale', $this->gameFacade()->get($gameId)->getName());
    }

    /**
     * Два имени совпадают. Неизвестный id.
     *
     * @return void
     */
    public function testDuplicateNameAndMissingId(): void
    {
        $world = $this->addWorldWithRevision('world');
        $this->gameFacade()->add($this->newGame($world->getId()));
        $this->gameFacade()->add($this->newGame($world->getId()));
        $this->expectNotFound(fn () => $this->gameFacade()->get(999));
        $this->expectNotFound(fn () => $this->gameFacade()->update(999, $this->patch()));
    }

    /**
     * Список владельца и viewAll.
     *
     * @return void
     */
    public function testListOwnerOrAll(): void
    {
        $world = $this->addWorldWithRevision('world');
        $otherUserId = $this->gameUserAccounts()->addFromInput([
            'login' => 'other',
            'name' => 'Other',
        ]);
        $ownId = $this->gameFacade()->add($this->newGame($world->getId()));
        $foreignId = $this->gameFacade()->add($this->newGame($world->getId(), [
            'ownerUserId' => $otherUserId,
            'name' => 'Foreign',
        ]));
        $own = $this->gameFacade()->getListByOwnerOrAll($this->ownerUserId, false);
        self::assertSame([$ownId], array_map(static fn ($record): int => $record->getId(), $own));
        $all = $this->gameFacade()->getListByOwnerOrAll($this->ownerUserId, true);
        self::assertSame([$ownId, $foreignId], array_map(static fn ($record): int => $record->getId(), $all));
    }

    /**
     * New с дефолтами.
     *
     * @param int $spaceId Мир.
     * @param array<string, mixed> $overrides Поля.
     *
     * @return NewGame DTO.
     */
    private function newGame(int $spaceId, array $overrides = []): NewGame
    {
        return NewGame::fromNormalized(array_merge([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ], $overrides));
    }

    /**
     * Patch с дефолтами.
     *
     * @param array<string, mixed> $overrides Поля.
     *
     * @return GamePatch DTO.
     */
    private function patch(array $overrides = []): GamePatch
    {
        return GamePatch::fromNormalized(array_merge([
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
            'rulesRevision' => 1,
        ], $overrides));
    }

    /**
     * Ожидает NOT_FOUND.
     *
     * @param callable $action Вызов.
     *
     * @return void
     */
    private function expectNotFound(callable $action): void
    {
        try {
            $action();
            self::fail('missing row must fail');
        } catch (GameNotFoundException $exception) {
            self::assertSame('GAME_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Ожидает INVALID.
     *
     * @param callable $action Вызов.
     *
     * @return void
     */
    private function expectInvalid(callable $action): void
    {
        try {
            $action();
            self::fail('invalid input must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }
    }
}
