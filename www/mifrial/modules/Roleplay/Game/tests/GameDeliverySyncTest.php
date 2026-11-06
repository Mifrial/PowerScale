<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Interface\Service\IGameDelivery;
use Mifrial\Roleplay\Game\Service\GameDeliverySync;
use PHPUnit\Framework\TestCase;

/**
 * Потолок жизни потока: без него отключённый клиент занимает воркер навсегда.
 */
final class GameDeliverySyncTest extends TestCase
{
    /**
     * Цикл закрывается сам, а журнал догоняется один раз на входе.
     *
     * @return void
     */
    public function testLoopStopsAtLifetimeCapAndCatchesUpOnce(): void
    {
        $delivery = $this->createMock(IGameDelivery::class);
        $delivery->expects(self::once())->method('catchUp')->with(5);
        $delivery->method('tail')->willReturn(['cursor' => 0, 'events' => []]);

        $userAccess = $this->createStub(IUserAccess::class);
        $userAccess->method('requireActor')->willReturn(new RequestActor(3, [], true));

        $request = $this->createStub(IHttpRequest::class);
        $request->method('getQueryValue')->willReturnCallback(
            static fn (string $name): mixed => match ($name) {
                'gameId' => '5',
                'lastCursor' => '0',
                default => null,
            },
        );

        $clock = new FakeGameSyncClock(1000);
        $chunks = '';
        $sync = new GameDeliverySync($userAccess, $delivery, $clock);
        $sync->run($request, new SseEmitter(
            static function (string $chunk) use (&$chunks): void {
                $chunks .= $chunk;
            },
            false,
        ));

        self::assertGreaterThanOrEqual(30, $clock->ticks);
        self::assertLessThanOrEqual(35, $clock->ticks);
        self::assertStringContainsString('event: sync', $chunks);
    }
}
