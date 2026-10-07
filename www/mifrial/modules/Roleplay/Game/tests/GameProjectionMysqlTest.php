<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use PHPUnit\Framework\TestCase;

final class GameProjectionMysqlTest extends TestCase
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
     * Roster без листа. NPC вне scope нет. left нет.
     *
     * @return void
     */
    public function testRosterOmitsSheetsAndHiddenNpc(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->openGame($spaceId);
        $heroOwner = $this->playerId($gameId, 'hero');
        $heroId = $this->addCharacter($spaceId, $heroOwner, 'Hero');
        $goneId = $this->addCharacter($spaceId, $this->ownerUserId, 'Gone');
        $this->setActor($heroOwner, []);
        $this->submit($gameId, $heroId);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $submitted = $this->submit($gameId, $goneId);
        $left = $this->dispatch('game.leaveCharacter', [
            'gameId' => $gameId,
            'characterId' => $goneId,
            'membershipRevision' => $submitted['membershipRevision'],
        ]);
        self::assertTrue($left['success']);
        $openNpc = $this->createNpc($gameId, 'Guard', 'all', []);
        $this->createNpc($gameId, 'Secret', 'gm', []);
        $this->setActor($this->memberId($gameId, 'reader'), []);
        $roster = $this->dispatch('game.getRoster', ['gameId' => $gameId]);
        self::assertTrue($roster['success']);
        $encoded = json_encode($roster['data']);
        self::assertIsString($encoded);
        self::assertStringNotContainsString('choices', $encoded);
        self::assertStringNotContainsString('approvedCharacterVersion', $encoded);
        self::assertSame(['Guard'], array_column($roster['data']['npcs'], 'name'));
        self::assertSame([$heroId], array_column($roster['data']['characters'], 'id'));
        self::assertSame($openNpc, $roster['data']['npcs'][0]['id']);
        $this->setActor($this->strangerId(), []);
        $hidden = $this->dispatch('game.getRoster', ['gameId' => $this->closedGame($spaceId)]);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
    }

    /**
     * Чужая секция не уходит. Каркас и мир остаются.
     *
     * @return void
     */
    public function testSheetsCutForeignSections(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->openGame($spaceId);
        $ownerId = $this->playerId($gameId, 'hero-owner');
        $characterId = $this->addCharacter($spaceId, $ownerId, 'Hero');
        $this->setActor($ownerId, []);
        $this->submit($gameId, $characterId);
        $this->setActor($this->ownerUserId, []);
        $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['abilities'],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npcId = $this->createNpc($gameId, 'Guard', 'all', []);
        $otherId = $this->createNpc($gameId, 'Other', 'all', ['abilities']);
        $readerId = $this->memberId($gameId, 'reader');
        $this->setActor($readerId, []);
        $read = $this->dispatch('game.getSheets', [
            'gameId' => $gameId,
            'keys' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npcId],
            ],
        ]);
        self::assertTrue($read['success']);
        self::assertCount(2, $read['data']['sheets']);
        $character = $read['data']['sheets'][0];
        self::assertArrayNotHasKey('osSurchargeTotal', $character['sheet']);
        self::assertArrayNotHasKey('racialAbilityCodes', $character['sheet']);
        self::assertArrayHasKey('abilityLevels', $character['sheet']);
        self::assertSame('Hero', $character['choices']['name']);
        self::assertSame(['os' => 1], $character['choices']['limits']);
        self::assertArrayHasKey('customRules', $character['choices']);
        self::assertSame($spaceId, $character['spaceId']);
        self::assertArrayNotHasKey('spaceCode', $character);
        self::assertArrayNotHasKey('ownerNotes', $character);
        self::assertArrayNotHasKey('osSurchargeTotal', $read['data']['sheets'][1]['sheet']);
        $npc = $this->dispatch('game.getNpc', ['gameId' => $gameId, 'npcId' => $npcId]);
        self::assertArrayNotHasKey('osSurchargeTotal', $npc['data']['version']['sheet']);
        $this->setActor($ownerId, []);
        $own = $this->dispatch('game.getSheets', [
            'gameId' => $gameId,
            'keys' => [['type' => 'character', 'id' => $characterId]],
        ]);
        self::assertSame(['sight'], $own['data']['sheets'][0]['sheet']['racialAbilityCodes']);
        self::assertSame(3, $own['data']['sheets'][0]['sheet']['osSurchargeTotal']);
        $this->setActor($readerId, []);
        $third = $this->dispatch('game.getSheets', [
            'gameId' => $gameId,
            'keys' => [['type' => 'npc', 'id' => $otherId]],
        ]);
        self::assertArrayHasKey('racialAbilityCodes', $third['data']['sheets'][0]['sheet']);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertArrayNotHasKey('characters', $card['data']);
        self::assertArrayNotHasKey('sheets', $card['data']);
    }

    /**
     * Пустые, длинные и повторные ключи.
     *
     * @return void
     */
    public function testSheetKeyLimits(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->openGame($spaceId);
        $this->setActor($this->ownerUserId, []);
        $empty = $this->dispatch('game.getSheets', ['gameId' => $gameId, 'keys' => []]);
        self::assertSame('INVALID_PARAMS', $empty['error']['code']);
        $keys = [];
        for ($index = 0; $index < 33; $index++) {
            $keys[] = ['type' => 'npc', 'id' => $index + 1];
        }
        $long = $this->dispatch('game.getSheets', ['gameId' => $gameId, 'keys' => $keys]);
        self::assertSame('INVALID_PARAMS', $long['error']['code']);
        $repeat = $this->dispatch('game.getSheets', [
            'gameId' => $gameId,
            'keys' => [
                ['type' => 'npc', 'id' => 1],
                ['id' => 1, 'type' => 'npc'],
            ],
        ]);
        self::assertSame('GAME_INVALID', $repeat['error']['code']);
        $missing = $this->dispatch('game.getSheets', [
            'gameId' => $gameId,
            'keys' => [['type' => 'npc', 'id' => 999]],
        ]);
        self::assertSame([], $missing['data']['sheets']);
        self::assertSame([['type' => 'npc', 'id' => 999]], $missing['data']['missing']);
    }

    /**
     * Боевой subset не тащит NPC вне состава и не кладёт roster в карточку.
     *
     * @return void
     */
    public function testBattleSubsetAndSessionCard(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->openGame($spaceId);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT]);
        $inside = $this->createNpc($gameId, 'Inside', 'all', []);
        $outside = $this->createNpc($gameId, 'Outside', 'all', []);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $started = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'one',
            'participants' => [['type' => 'npc', 'id' => $inside]],
        ]);
        self::assertTrue($started['success']);
        $other = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'two',
            'participants' => [['type' => 'npc', 'id' => $outside]],
        ]);
        $subset = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $started['data']['battleId'],
        ]);
        self::assertTrue($subset['success']);
        self::assertSame([], $subset['data']['characters']);
        self::assertSame([$inside], array_column($subset['data']['npcs'], 'id'));
        self::assertCount(1, $subset['data']['sheets']);
        $second = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $other['data']['battleId'],
        ]);
        self::assertSame([$outside], array_column($second['data']['npcs'], 'id'));
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertArrayNotHasKey('characters', $card['data']);
        self::assertArrayNotHasKey('npcs', $card['data']);
        $idle = $this->openGame($spaceId);
        $noBattle = $this->dispatch('game.getBattleSheets', ['gameId' => $idle, 'battleId' => 1]);
        self::assertSame('GAME_NOT_FOUND', $noBattle['error']['code']);
        $roster = $this->dispatch('game.getRoster', ['gameId' => $idle]);
        self::assertTrue($roster['success']);
    }

    /**
     * Игра, которую видит участник.
     *
     * @param int $spaceId Мир.
     *
     * @return int Id.
     */
    private function openGame(int $spaceId): int
    {
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'recruiting',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));

        return $gameId;
    }

    /**
     * Черновик чужому не виден.
     *
     * @param int $spaceId Мир.
     *
     * @return int Id.
     */
    private function closedGame(int $spaceId): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Closed',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
    }

    /**
     * Персонаж с секциями в документе.
     *
     * @param int $spaceId Мир.
     * @param int $ownerUserId Владелец.
     * @param string $name Имя.
     *
     * @return int Id.
     */
    private function addCharacter(int $spaceId, int $ownerUserId, string $name): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => $name,
            'choices' => [
                'name' => $name,
                'ageYears' => 20,
                'limits' => ['os' => 1],
                'customRules' => [],
                'raceCode' => 'human',
                'abilities' => [],
            ],
            'sheet' => [
                'osSurchargeTotal' => 3,
                'racialAbilityCodes' => ['sight'],
                'abilityLevels' => [],
                'active' => true,
            ],
            'visibilityFields' => [],
            'ownerNotes' => 'secret',
            'active' => true,
        ]));
    }

    /**
     * Участник-player.
     *
     * @param int $gameId Игра.
     * @param string $login Логин.
     *
     * @return int Учётка.
     */
    private function playerId(int $gameId, string $login): int
    {
        return $this->memberId($gameId, $login);
    }

    /**
     * Участник без роли владельца игры.
     *
     * @param int $gameId Игра.
     * @param string $login Логин.
     *
     * @return int Учётка.
     */
    private function memberId(int $gameId, string $login): int
    {
        $userId = $this->gameUserAccounts()->addFromInput(['login' => $login, 'name' => $login]);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $userId,
            'role' => 'player',
        ]));

        return $userId;
    }

    /**
     * Посторонний.
     *
     * @return int Учётка.
     */
    private function strangerId(): int
    {
        return $this->gameUserAccounts()->addFromInput(['login' => 'out', 'name' => 'Out']);
    }

    /**
     * Подача персонажа текущим актором.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return array<string, mixed> Строка.
     */
    private function submit(int $gameId, int $characterId): array
    {
        $result = $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        self::assertTrue($result['success']);

        return $result['data'];
    }

    /**
     * NPC.
     *
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param string $scope Scope.
     * @param array<int, string> $sections Секции.
     *
     * @return int Id.
     */
    private function createNpc(int $gameId, string $name, string $scope, array $sections): int
    {
        $created = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => $name,
            'visibility' => ['scope' => $scope, 'userIds' => [], 'sections' => $sections],
        ]);
        self::assertTrue($created['success']);

        return $created['data']['npcId'];
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
