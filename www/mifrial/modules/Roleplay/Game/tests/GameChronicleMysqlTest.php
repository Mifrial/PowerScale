<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use PHPUnit\Framework\TestCase;

final class GameChronicleMysqlTest extends TestCase
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
     * Нормализация и порядок по смещению.
     *
     * @return void
     */
    public function testOffsetAndOrder(): void
    {
        $gameId = $this->openGame('all');
        $header = $this->dispatch('game.getChronicle', ['gameId' => $gameId]);
        self::assertTrue($header['success']);
        self::assertSame($gameId, $header['data']['id']);
        self::assertSame('adventure_start', $header['data']['epoch']);
        self::assertNull($header['data']['name']);
        self::assertSame([], $this->dispatch('game.getChronicleEntries', ['gameId' => $gameId])['data']);

        $later = $this->createEntry($gameId, 'Later', $this->offset(['years' => 1]));
        $months = $this->createEntry($gameId, 'Months', $this->offset(['months' => 13]));
        self::assertSame(1, $months['offset']['years']);
        self::assertSame(3, $months['offset']['months']);
        self::assertSame(0, $months['offset']['minutes']);
        $hour = $this->createEntry($gameId, 'Hour', $this->offset(['minutes' => 61]));
        self::assertSame(1, $hour['offset']['hours']);
        self::assertSame(1, $hour['offset']['minutes']);
        $zero = $this->createEntry($gameId, 'Zero', $this->offset([]));
        self::assertSame(0, $zero['offset']['years']);
        self::assertSame(0, $zero['offset']['minutes']);
        $tie = $this->createEntry($gameId, 'Tie', $this->offset([]));

        self::assertSame('GAME_INVALID', $this->dispatch('game.createChronicleEntry', [
            'gameId' => $gameId,
            'title' => 'Bad',
            'content' => '',
            'offset' => $this->offset(['minutes' => -1]),
        ])['error']['code']);
        self::assertSame('GAME_INVALID', $this->dispatch('game.createChronicleEntry', [
            'gameId' => $gameId,
            'title' => 'Frac',
            'content' => '',
            'offset' => $this->offset(['minutes' => 1.5]),
        ])['error']['code']);

        $ids = array_column($this->dispatch('game.getChronicleEntries', ['gameId' => $gameId])['data'], 'id');
        self::assertSame(
            [$zero['id'], $tie['id'], $hour['id'], $later['id'], $months['id']],
            $ids,
        );
        $again = $this->dispatch('game.getChronicle', ['gameId' => $gameId]);
        self::assertSame($gameId, $again['data']['id']);
        self::assertCount(5, $this->dispatch('game.getChronicleEntries', ['gameId' => $gameId])['data']);
    }

    /**
     * Владелец, gm, edit_all пишут. player нет. Чужой не видит.
     *
     * @return void
     */
    public function testRights(): void
    {
        $gameId = $this->openGame('players');
        $gmId = $this->gameUserAccounts()->addFromInput(['login' => 'gm', 'name' => 'Gm']);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'pl', 'name' => 'Pl']);
        $strangerId = $this->gameUserAccounts()->addFromInput(['login' => 'out', 'name' => 'Out']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.addMember', [
            'gameId' => $gameId,
            'userId' => $gmId,
            'role' => 'gm',
        ])['success']);
        self::assertTrue($this->dispatch('game.addMember', [
            'gameId' => $gameId,
            'userId' => $playerId,
            'role' => 'player',
        ])['success']);

        $this->setActor($playerId, []);
        self::assertSame([], $this->dispatch('game.getChronicleEntries', ['gameId' => $gameId])['data']);
        self::assertSame('AUTH_DENIED', $this->dispatch('game.createChronicleEntry', [
            'gameId' => $gameId,
            'title' => 'No',
            'content' => '',
            'offset' => $this->offset([]),
        ])['error']['code']);
        self::assertSame([], $this->dispatch('game.getChronicleEntries', ['gameId' => $gameId])['data']);

        $this->setActor($strangerId, []);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.getChronicle', ['gameId' => $gameId])['error']['code']);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.createChronicleEntry', [
            'gameId' => $gameId,
            'title' => 'Hide',
            'content' => '',
            'offset' => $this->offset([]),
        ])['error']['code']);

        $this->setActor($gmId, []);
        $created = $this->createEntry($gameId, 'Gm note', $this->offset(['days' => 1]));
        $updated = $this->dispatch('game.updateChronicleEntry', [
            'entryId' => $created['id'],
            'title' => 'Gm edited',
            'content' => '[[character:1]] and [[character:1]]',
            'offset' => $this->offset(['days' => 2]),
        ]);
        self::assertTrue($updated['success']);
        self::assertSame([['kind' => 'character', 'id' => 1]], $updated['data']['related']);
        self::assertSame($gmId, $updated['data']['createdBy']);

        $this->setActor($strangerId, [GamePermissionKeys::EDIT_ALL]);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.deleteChronicleEntry', [
            'entryId' => $created['id'],
        ])['error']['code']);
        $this->setActor($strangerId, [GamePermissionKeys::VIEW_ALL, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.deleteChronicleEntry', ['entryId' => $created['id']])['success']);
        self::assertSame([], $this->dispatch('game.getChronicleEntries', ['gameId' => $gameId])['data']);
    }

    /**
     * Create не пишет лист и не запускает сессию.
     *
     * @return void
     */
    public function testCreateLeavesSheetAndSession(): void
    {
        $gameId = $this->openGame('all');
        $created = $this->createEntry($gameId, 'Note', $this->offset([]));
        self::assertIsInt($created['createdAt']);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertFalse($card['data']['sessionRunning']);
        self::assertSame([], $this->dispatch('game.getCharacterList', ['gameId' => $gameId])['data']);
    }

    /**
     * Открытая игра владельца.
     *
     * @param string $visibility Видимость.
     *
     * @return int Id.
     */
    private function openGame(string $visibility): int
    {
        $worldId = $this->addWorldWithRevision('razrabotka')->getId();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $created = $this->dispatch('game.create', [
            'name' => 'Tale',
            'shortDescription' => '',
            'description' => '',
            'status' => 'recruiting',
            'visibility' => $visibility,
            'joinPolicy' => 'invite_only',
            'spaceId' => $worldId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]);
        self::assertTrue($created['success']);

        return $created['data']['id'];
    }

    /**
     * Пишет запись от текущего актора.
     *
     * @param int $gameId Игра.
     * @param string $title Заголовок.
     * @param array<string, int|float> $offset Время.
     *
     * @return array<string, mixed> Запись.
     */
    private function createEntry(int $gameId, string $title, array $offset): array
    {
        $created = $this->dispatch('game.createChronicleEntry', [
            'gameId' => $gameId,
            'title' => $title,
            'content' => '',
            'offset' => $offset,
        ]);
        self::assertTrue($created['success'], json_encode($created));

        return $created['data'];
    }

    /**
     * Шесть единиц, нули по умолчанию.
     *
     * @param array<string, int|float> $partial Часть.
     *
     * @return array<string, int|float> Объект.
     */
    private function offset(array $partial): array
    {
        return $partial + [
            'years' => 0,
            'months' => 0,
            'decades' => 0,
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
        ];
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
