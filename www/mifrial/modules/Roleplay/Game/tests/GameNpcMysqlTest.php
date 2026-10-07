<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;

final class GameNpcMysqlTest extends TestCase
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
     * Создание, CAS, видимость и чужая ревизия.
     *
     * @return void
     */
    public function testCreateUpdateAndVisibility(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $gameId = $this->addGame($world->getId(), 1);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'pl', 'name' => 'Pl']);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $playerId,
            'role' => 'player',
        ]));
        $created = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]);
        self::assertTrue($created['success']);
        self::assertSame(1, $created['data']['actualVersion']);
        self::assertSame(1, $created['data']['version']['rulesRevision']);
        self::assertArrayHasKey('money', $created['data']['version']['sheet']);
        $second = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Other',
            'visibility' => ['scope' => 'gm', 'userIds' => [], 'sections' => []],
        ]);
        self::assertTrue($second['success']);
        self::assertNotSame($created['data']['npcId'], $second['data']['npcId']);
        $this->setActor($playerId, []);
        $hidden = $this->dispatch('game.getNpc', ['gameId' => $gameId, 'npcId' => $second['data']['npcId']]);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
        $open = $this->dispatch('game.getNpc', ['gameId' => $gameId, 'npcId' => $created['data']['npcId']]);
        self::assertTrue($open['success']);
        self::assertSame('Guard', $open['data']['name']);
        self::assertArrayNotHasKey('money', $open['data']['version']['sheet']);
        self::assertArrayNotHasKey('abilities', $open['data']['version']['choices']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $sheet = $created['data']['version']['sheet'];
        $choices = $created['data']['version']['choices'];
        $choices['raceCode'] = 'ghost';
        $stale = $this->dispatch('game.updateNpc', $this->updateBody($gameId, $created['data'], $choices, $sheet, 2));
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        $dead = $this->dispatch('game.updateNpc', $this->updateBody($gameId, $created['data'], $choices, $sheet, 1));
        self::assertSame('conflicts', $dead['data']['kind']);
        self::assertSame(1, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $created['data']['npcId'],
        ])['data']['actualVersion']);
        $moved = $this->dispatch('game.updateNpc', $this->updateBody(
            $gameId,
            $created['data'],
            $created['data']['version']['choices'],
            $sheet,
            1,
            ['rulesRevision' => 2],
        ));
        self::assertSame('GAME_INVALID', $moved['error']['code']);
        $saved = $this->dispatch('game.updateNpc', $this->updateBody(
            $gameId,
            $created['data'],
            $created['data']['version']['choices'],
            $sheet,
            1,
        ));
        self::assertTrue($saved['success']);
        self::assertSame(2, $saved['data']['actualVersion']);
        self::assertSame(1, $saved['data']['version']['rulesRevision']);
        $staleSheet = $saved['data']['version']['sheet'];
        $nextChoices = $saved['data']['version']['choices'];
        $nextChoices['money'] = 3;
        $rejected = $this->dispatch('game.updateNpc', $this->updateBody(
            $gameId,
            $saved['data'],
            $nextChoices,
            $staleSheet,
            2,
        ));
        self::assertSame('conflicts', $rejected['data']['kind']);
        $badChoices = $saved['data']['version']['choices'];
        $badChoices['abilities'] = 'sword';
        $bad = $this->dispatch('game.updateNpc', $this->updateBody(
            $gameId,
            $saved['data'],
            $badChoices,
            $saved['data']['version']['sheet'],
            2,
        ));
        self::assertSame('INVALID_PARAMS', $bad['error']['code']);
        self::assertSame(2, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $created['data']['npcId'],
        ])['data']['actualVersion']);
    }

    /**
     * Перевод не зовёт character.migrate. Старт сессии NPC не берёт.
     *
     * @return void
     */
    public function testTranslateAndSessionGate(): void
    {
        $world = $this->worldWithSecondRevision();
        $gameId = $this->addGame($world->getId(), 1);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $created = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'gm', 'userIds' => [], 'sections' => ['resources']],
        ]);
        self::assertTrue($created['success']);
        $this->dispatch('game.update', [
            'id' => $gameId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $world->getId(),
            'rulesRevision' => 2,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]);
        self::assertSame(1, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $created['data']['npcId'],
        ])['data']['version']['rulesRevision']);
        $translated = $this->dispatch('game.translateNpc', [
            'gameId' => $gameId,
            'npcId' => $created['data']['npcId'],
            'expectedNpcActualVersion' => 1,
        ]);
        self::assertTrue($translated['success'], json_encode($translated));
        self::assertSame(2, $translated['data']['version']['rulesRevision']);
        self::assertSame(2, $translated['data']['actualVersion']);
        $again = $this->dispatch('game.translateNpc', [
            'gameId' => $gameId,
            'npcId' => $created['data']['npcId'],
            'expectedNpcActualVersion' => 2,
        ]);
        self::assertSame('GAME_INVALID', $again['error']['code']);
        $started = $this->dispatch('game.startSession', ['gameId' => $gameId]);
        self::assertTrue($started['success']);
        $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        self::assertSame(2, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $created['data']['npcId'],
        ])['data']['actualVersion']);
    }

    /**
     * Мир и вторая ревизия без новой расы.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithSecondRevision(): RuleSpaceRecord
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('note', new RuleVersionBody('trait', 'Note', '', [], [], [], 'needs_work')),
        ]);

        return $world;
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
     * Тело update.
     *
     * @param int $gameId Игра.
     * @param array<string, mixed> $created Ответ создания.
     * @param array<string, mixed> $choices Документ.
     * @param array<string, mixed> $sheet Снимок.
     * @param int $expectedVersion CAS.
     * @param array<string, mixed> $extra Сверка.
     *
     * @return array<string, mixed> JSON.
     */
    private function updateBody(
        int $gameId,
        array $created,
        array $choices,
        array $sheet,
        int $expectedVersion,
        array $extra = [],
    ): array {
        return array_merge([
            'gameId' => $gameId,
            'npcId' => $created['npcId'],
            'name' => 'Guard',
            'visibility' => $created['visibility'],
            'choices' => $choices,
            'sheet' => $sheet,
            'expectedNpcActualVersion' => $expectedVersion,
        ], $extra);
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
