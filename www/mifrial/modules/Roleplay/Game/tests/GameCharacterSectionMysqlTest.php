<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameNpcTable;
use PHPUnit\Framework\TestCase;

final class GameCharacterSectionMysqlTest extends TestCase
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
     * Запись секций не трогает лист, snapshot и NPC.
     *
     * @return void
     */
    public function testWritesSectionsWithoutSheetOrNpc(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->addGame($spaceId);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'play', 'name' => 'Play']);
        $characterId = $this->addCharacter($spaceId, $playerId);
        $this->setActor($playerId, []);
        $submitted = $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        self::assertSame([], $submitted['data']['sectionVisibility']);
        $before = $this->characterFacade()->get($characterId);
        $gmId = $this->gameUserAccounts()->addFromInput(['login' => 'gm', 'name' => 'Gm']);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $gmId,
            'role' => 'gm',
        ]));
        $this->setActor($gmId, []);
        $written = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['inventory', 'race'],
        ]);
        self::assertTrue($written['success']);
        self::assertSame(['inventory', 'race'], $written['data']['sectionVisibility']);
        self::assertSame(1, $written['data']['membershipRevision']);
        self::assertNull($written['data']['approvedCharacterVersion']);
        $cleared = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => [],
        ]);
        self::assertSame([], $cleared['data']['sectionVisibility']);
        $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['abilities'],
        ]);
        $after = $this->characterFacade()->get($characterId);
        self::assertSame($before->getChoices(), $after->getChoices());
        self::assertSame($before->getSheet(), $after->getSheet());
        self::assertSame($before->getActualVersion(), $after->getActualVersion());
        self::assertSame($before->getVisibilityFields(), $after->getVisibilityFields());
        self::assertSame($before->isPublic(), $after->isPublic());
        $this->setActor($this->ownerUserId, []);
        $approved = $this->dispatch('game.approveCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'actualVersion' => 1,
            'membershipRevision' => 1,
        ]);
        self::assertSame(['abilities'], $approved['data']['sectionVisibility']);
        $bonus = $this->dispatch('game.setCharacterBonus', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'membershipRevision' => 2,
            'osBonus' => 1,
            'orBonus' => 0,
            'olBonus' => 0,
        ]);
        self::assertSame(['abilities'], $bonus['data']['sectionVisibility']);
        self::assertSame(1, $bonus['data']['osBonus']);
        self::assertSame(0, $this->npcCount());
    }

    /**
     * Отказы актора, статуса и кодов.
     *
     * @return void
     */
    public function testRejectsActorsStatusAndCodes(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->addGame($spaceId);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'own', 'name' => 'Own']);
        $characterId = $this->addCharacter($spaceId, $playerId);
        $this->setActor($playerId, []);
        $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        $denied = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['race'],
        ]);
        self::assertSame('GAME_NOT_FOUND', $denied['error']['code']);
        $this->setActor(null, []);
        $anonymous = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => [],
        ]);
        self::assertSame('AUTH_REQUIRED', $anonymous['error']['code']);
        $editorId = $this->gameUserAccounts()->addFromInput(['login' => 'ed', 'name' => 'Ed']);
        $this->setActor($editorId, [GamePermissionKeys::EDIT_ALL]);
        $duplicate = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['race', 'race'],
        ]);
        self::assertSame('INVALID_PARAMS', $duplicate['error']['code']);
        $object = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['audience' => 'all', 'sections' => ['race']],
        ]);
        self::assertSame('INVALID_PARAMS', $object['error']['code']);
        $unknown = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['tags'],
        ]);
        self::assertSame('INVALID_PARAMS', $unknown['error']['code']);
        self::assertSame([], $this->membershipFacade()->get($gameId, $characterId)->getSectionVisibility());
        $this->setActor($playerId, []);
        $this->dispatch('game.leaveCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'membershipRevision' => 1,
        ]);
        $this->setActor($editorId, [GamePermissionKeys::EDIT_ALL]);
        $left = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'sectionVisibility' => ['race'],
        ]);
        self::assertSame('GAME_INVALID', $left['error']['code']);
        $secondId = $this->addCharacter($spaceId, $playerId);
        $this->setActor($playerId, []);
        $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $secondId,
        ]);
        $this->gameFacade()->update($gameId, GamePatch::fromNormalized([
            'name' => 'Tale',
            'status' => 'completed',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
            'rulesRevision' => 1,
        ]));
        $this->setActor($editorId, [GamePermissionKeys::EDIT_ALL]);
        $closed = $this->dispatch('game.setCharacterSectionVisibility', [
            'gameId' => $gameId,
            'characterId' => $secondId,
            'sectionVisibility' => ['race'],
        ]);
        self::assertSame('GAME_INVALID', $closed['error']['code']);
        self::assertSame([], $this->membershipFacade()->get($gameId, $secondId)->getSectionVisibility());
    }

    /**
     * Игра фикстуры.
     *
     * @param int $spaceId Мир.
     *
     * @return int Id.
     */
    private function addGame(int $spaceId): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
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
        ]));
    }

    /**
     * Персонаж игрока.
     *
     * @param int $spaceId Мир.
     * @param int $ownerUserId Владелец.
     *
     * @return int Id.
     */
    private function addCharacter(int $spaceId, int $ownerUserId): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => 'Hero',
            'choices' => [],
            'sheet' => [],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
    }

    /**
     * Число строк NPC.
     *
     * @return int Строки.
     */
    private function npcCount(): int
    {
        $gateway = $this->gameApplication()->getLocator()->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $total = $gateway->open(GameNpcTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            true,
            null,
        ))->total();

        return $total ?? 0;
    }

    /**
     * Актор HTTP. null снимает актора.
     *
     * @param int|null $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(?int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        if ($userId === null) {
            $this->requestContext->setActor(null);

            return;
        }

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
