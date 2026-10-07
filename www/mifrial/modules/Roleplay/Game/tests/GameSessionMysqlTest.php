<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;

final class GameSessionMysqlTest extends TestCase
{
    use GameMysqlFixture;

    private ?IRequestContext $requestContext = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectGameMysql();
        $requestContext = $this->gameApplication()->getLocator()->get(IKernelContainer::class)->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
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
     * Старт берёт допущенных, stop не трогает лист, повторный старт конфликтует.
     *
     * @return void
     */
    public function testStartStopAndRoster(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId(), 2);
        $firstId = $this->admit($world->getId(), $gameId, 'One');
        $secondId = $this->admit($world->getId(), $gameId, 'Two');
        $blockedId = $this->addCharacter($world->getId(), 'Blocked');
        $this->membershipFacade()->submit($gameId, $blockedId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $blockedId, 1, 1);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $started = $this->dispatch('game.startSession', ['gameId' => $gameId]);
        self::assertTrue($started['success']);
        self::assertTrue($started['data']['sessionRunning']);
        self::assertSame('draft', $started['data']['status']);
        self::assertSame(2, $started['data']['rulesRevision']);
        $first = $this->membershipFacade()->get($gameId, $firstId);
        $blocked = $this->membershipFacade()->get($gameId, $secondId);
        self::assertTrue($first->isActiveSessionParticipant());
        self::assertTrue($blocked->isActiveSessionParticipant());
        self::assertFalse($this->membershipFacade()->get($gameId, $blockedId)->isActiveSessionParticipant());
        $again = $this->dispatch('game.startSession', ['gameId' => $gameId]);
        self::assertSame('GAME_CONFLICT', $again['error']['code']);
        $version = $this->characterFacade()->get($firstId)->getActualVersion();
        $migrate = $this->dispatch('character.migrate', [
            'id' => $firstId,
            'revision' => 2,
            'expectedVersion' => $version,
        ]);
        self::assertSame('CHARACTER_INVALID', $migrate['error']['code']);
        self::assertSame($version, $this->characterFacade()->get($firstId)->getActualVersion());
        $sheet = $this->characterFacade()->get($firstId)->getSheet();
        $stopped = $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        self::assertTrue($stopped['success']);
        self::assertFalse($stopped['data']['sessionRunning']);
        self::assertSame('draft', $stopped['data']['status']);
        self::assertSame($sheet, $this->characterFacade()->get($firstId)->getSheet());
        self::assertFalse($this->membershipFacade()->get($gameId, $firstId)->isActiveSessionParticipant());
        $missing = $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        self::assertSame('GAME_INVALID', $missing['error']['code']);
    }

    /**
     * Пустая сессия, права, completed и замок ревизии.
     *
     * @return void
     */
    public function testEmptySessionPermissionsAndLock(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $gameId = $this->addGame($world->getId(), 1);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $empty = $this->dispatch('game.startSession', ['gameId' => $gameId]);
        self::assertTrue($empty['success']);
        self::assertTrue($empty['data']['sessionRunning']);
        $extra = $this->dispatch('game.stopSession', ['gameId' => $gameId, 'targetStatus' => 'completed']);
        self::assertSame('INVALID_PARAMS', $extra['error']['code']);
        self::assertTrue($this->dispatch('game.get', ['id' => $gameId])['data']['sessionRunning']);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'pl', 'name' => 'Pl']);
        $this->dispatch('game.addMember', ['gameId' => $gameId, 'userId' => $playerId, 'role' => 'player']);
        $this->setActor($playerId, []);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.stopSession', ['gameId' => $gameId])['error']['code']);
        $strangerId = $this->gameUserAccounts()->addFromInput(['login' => 'st', 'name' => 'St']);
        $this->setActor($strangerId, []);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.startSession', ['gameId' => $gameId])['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $paused = $this->dispatch('game.update', $this->card($world->getId(), $gameId, ['status' => 'paused']));
        self::assertTrue($paused['success']);
        self::assertSame('paused', $paused['data']['status']);
        self::assertTrue($paused['data']['sessionRunning']);
        $closed = $this->dispatch('game.update', $this->card($world->getId(), $gameId, ['status' => 'completed']));
        self::assertSame('GAME_INVALID', $closed['error']['code']);
        $revision = $this->dispatch('game.update', $this->card($world->getId(), $gameId, ['rulesRevision' => 1]));
        self::assertTrue($revision['success']);
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('ability', 'Human 2', '', [], [], [], 'needs_work')),
        ]);
        $moved = $this->dispatch('game.update', $this->card($world->getId(), $gameId, ['rulesRevision' => 2]));
        self::assertSame('GAME_INVALID', $moved['error']['code']);
        self::assertSame(1, $this->gameFacade()->get($gameId)->getRulesRevision());
        $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        $after = $this->dispatch('game.update', $this->card($world->getId(), $gameId, ['rulesRevision' => 2]));
        self::assertTrue($after['success']);
        self::assertSame(2, $after['data']['rulesRevision']);
    }

    /**
     * gm и edit_all стартуют. completed отвергает старт и stop.
     *
     * @return void
     */
    public function testEditorsAndCompleted(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $gameId = $this->addGame($world->getId(), 1);
        $gmId = $this->gameUserAccounts()->addFromInput(['login' => 'gm', 'name' => 'Gm']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $this->dispatch('game.addMember', ['gameId' => $gameId, 'userId' => $gmId, 'role' => 'gm']);
        $this->setActor($gmId, []);
        $started = $this->dispatch('game.startSession', ['gameId' => $gameId]);
        self::assertTrue($started['success']);
        self::assertTrue($started['data']['sessionRunning']);
        $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $this->dispatch('game.update', $this->card($world->getId(), $gameId, ['status' => 'completed']));
        self::assertSame('GAME_INVALID', $this->dispatch('game.startSession', ['gameId' => $gameId])['error']['code']);
        self::assertSame('GAME_INVALID', $this->dispatch('game.stopSession', ['gameId' => $gameId])['error']['code']);
        $otherId = $this->addGame($world->getId(), 1);
        $editorId = $this->gameUserAccounts()->addFromInput(['login' => 'ed', 'name' => 'Ed']);
        $this->setActor($editorId, [GamePermissionKeys::EDIT_ALL]);
        $foreign = $this->dispatch('game.startSession', ['gameId' => $otherId]);
        self::assertTrue($foreign['success']);
        self::assertTrue($foreign['data']['sessionRunning']);
    }

    /**
     * Мир с расой на второй ревизии.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithRace(): RuleSpaceRecord
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
        ]);

        return $world;
    }

    /**
     * Допущенный active на ревизии игры.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param string $name Имя.
     *
     * @return int Персонаж.
     */
    private function admit(int $spaceId, int $gameId, string $name): int
    {
        $characterId = $this->addCharacter($spaceId, $name);
        $this->characterFacade()->replaceMigrated($characterId, $name, true, $this->storedChoices($name), [], 2, 1);
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    /**
     * Черновик.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return int Игра.
     */
    private function addGame(int $spaceId, int $rulesRevision): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => $rulesRevision,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
    }

    /**
     * Персонаж фикстуры.
     *
     * @param int $spaceId Мир.
     * @param string $name Имя.
     *
     * @return int Id.
     */
    private function addCharacter(int $spaceId, string $name): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => $name,
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
    }

    /**
     * Выборы, которые принимает лист.
     *
     * @param string $name Имя.
     *
     * @return array<string, mixed> choices.
     */
    private function storedChoices(string $name): array
    {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => [],
            'inventory' => [],
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'active' => true,
        ];
    }

    /**
     * Тело update.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param array<string, mixed> $overrides Поля.
     *
     * @return array<string, mixed> JSON.
     */
    private function card(int $spaceId, int $gameId, array $overrides): array
    {
        return array_merge([
            'id' => $gameId,
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
        ], $overrides);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $permissionKeys, false));
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
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }
}
