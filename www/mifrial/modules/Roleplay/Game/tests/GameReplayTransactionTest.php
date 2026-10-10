<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Closure;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Service\GameReplayTransaction;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет short-circuit replay до domain work.
 */
final class GameReplayTransactionTest extends TestCase
{
    /**
     * Повтор того же тела возвращает сохранённый итог без повторной работы.
     *
     * @return void
     */
    public function testReplaySkipsReservationCompletionAndWork(): void
    {
        $stored = null;
        $reserveCalls = 0;
        $completeCalls = 0;
        $workCalls = 0;
        $gateway = $this->createStub(ISmartTableGateway::class);
        $gateway->method('transaction')->willReturnCallback(
            static fn (Closure $work): array => $work(),
        );
        $transaction = new GameReplayTransaction($gateway);
        $body = ['defense' => ['reaction' => 'dodge']];
        $result = ['success' => 1, 'resistance' => ['base' => 2, 'size' => 0]];

        $first = $transaction->execute(
            static function () use (&$stored): ?array {
                return $stored;
            },
            static function () use (&$reserveCalls, &$stored, $body): int {
                $reserveCalls++;
                $stored = ['body' => $body, 'result' => ['success' => 1, 'resistance' => ['base' => 2, 'size' => 0]]];

                return 7;
            },
            static function (int $reservationId, array $resolved) use (&$completeCalls, &$stored): void {
                self::assertSame(7, $reservationId);
                $completeCalls++;
                $stored['result'] = $resolved;
            },
            static function () use (&$workCalls, $result): array {
                $workCalls++;

                return $result;
            },
            $body,
        );

        $second = $transaction->execute(
            static fn (): ?array => $stored,
            static function () use (&$reserveCalls): int {
                $reserveCalls++;

                return 8;
            },
            static function () use (&$completeCalls): void {
                $completeCalls++;
            },
            static function () use (&$workCalls): array {
                $workCalls++;

                return ['success' => 99];
            },
            $body,
        );

        self::assertFalse($first['replay']);
        self::assertTrue($second['replay']);
        self::assertSame($result, $second['result']);
        self::assertSame(1, $reserveCalls);
        self::assertSame(1, $completeCalls);
        self::assertSame(1, $workCalls);
    }
}
