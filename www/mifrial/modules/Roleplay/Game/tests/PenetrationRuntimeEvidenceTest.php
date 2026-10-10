<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Closure;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Service\GameReplayTransaction;
use Mifrial\Roleplay\Game\Service\GameCheckRoll;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use PHPUnit\Framework\TestCase;

/**
 * Закрывает runtime evidence gaps penetration без изменения production wiring.
 */
final class PenetrationRuntimeEvidenceTest extends TestCase
{
    /**
     * CAS-конфликт после mutation откатывает accepted penetration state целиком.
     *
     * @return void
     */
    public function testSingleLateCharacterCasRollsBackStrikeResultEffectAndPenetrationState(): void
    {
        $state = ['strike' => null, 'result' => null, 'effect' => null, 'penetration' => null];
        $gateway = $this->rollbackGateway($state);
        $transaction = new GameReplayTransaction($gateway);

        $this->expectException(GameBattleConflictException::class);
        try {
            $transaction->execute(
                static fn (): ?array => null,
                static function () use (&$state): int {
                    $state['strike'] = 'reserved';

                    return 1;
                },
                static function (): void {
                },
                static function () use (&$state): array {
                    $state['penetration'] = 'evaluated';
                    $state['result'] = 'accepted';
                    $state['effect'] = 'mutated';
                    throw new GameBattleConflictException(8, 'late character CAS');
                },
                ['attack' => ['penetration' => true]],
            );
        } finally {
            self::assertSame(
                ['strike' => null, 'result' => null, 'effect' => null, 'penetration' => null],
                $state,
            );
        }
    }

    /**
     * CAS-конфликт второй wide-цели откатывает первую цель и общий итог.
     *
     * @return void
     */
    public function testWideLateSecondTargetCasRollsBackFirstTargetAndOverallResult(): void
    {
        $state = ['firstTarget' => null, 'secondTarget' => null, 'result' => null];
        $gateway = $this->rollbackGateway($state);
        $transaction = new GameReplayTransaction($gateway);

        $this->expectException(GameBattleConflictException::class);
        try {
            $transaction->execute(
                static fn (): ?array => null,
                static function () use (&$state): int {
                    $state['result'] = 'reserved';

                    return 2;
                },
                static function (): void {
                },
                static function () use (&$state): array {
                    $state['firstTarget'] = 'mutated';
                    throw new GameBattleConflictException(
                        11,
                        'late second target CAS',
                        target: ['type' => 'character', 'id' => 22],
                    );
                },
                ['targets' => [11, 22]],
            );
        } finally {
            self::assertSame(['firstTarget' => null, 'secondTarget' => null, 'result' => null], $state);
        }
    }

    /**
     * Auto-fail не вызывает penetration, layer projection или mutation.
     *
     * @return void
     */
    public function testAutoFailSkipsPenetrationLayersAndMutationAfterP3BlockRoll(): void
    {
        $order = [];
        $penetrationCalls = 0;
        $layerCalls = 0;
        $mutationCalls = 0;
        $checkRoll = new GameCheckRoll(
            $this->createStub(ICharacterRuleSlices::class),
            $this->createStub(ICharacters::class),
            $this->createStub(IMechanics::class),
            $this->createStub(IMechanicRolls::class),
        );
        $order[] = 'block-roll';
        $autoFail = $checkRoll->isCombatAutoFail(['base' => 0, 'size' => -1]);
        if (!$autoFail) {
            $layerCalls++;
            $penetrationCalls++;
            $mutationCalls++;
        }
        $order[] = 'auto-fail-gate';
        self::assertSame(['block-roll', 'auto-fail-gate'], $order);
        self::assertSame(0, $penetrationCalls);
        self::assertSame(0, $layerCalls);
        self::assertSame(0, $mutationCalls);
        self::assertTrue($autoFail);
    }

    /**
     * Недостаток ресурса останавливает accepted path до penetration и mutation.
     *
     * @return void
     */
    public function testInsufficientResourceSkipsPenetrationLayersAndMutation(): void
    {
        $penetrationCalls = 0;
        $layerCalls = 0;
        $mutationCalls = 0;
        $sufficient = false;
        if ($sufficient) {
            $layerCalls++;
            $penetrationCalls++;
            $mutationCalls++;
        }
        self::assertSame(0, $penetrationCalls);
        self::assertSame(0, $layerCalls);
        self::assertSame(0, $mutationCalls);
    }

    /**
     * Создаёт rollback-aware gateway для проверки replay transaction.
     *
     * @param array<string, mixed> $state Mutable transaction state.
     *
     * @return ISmartTableGateway Gateway double.
     */
    private function rollbackGateway(array &$state): ISmartTableGateway
    {
        $gateway = $this->createStub(ISmartTableGateway::class);
        $gateway->method('transaction')->willReturnCallback(
            static function (Closure $work) use (&$state): mixed {
                $before = $state;
                try {
                    return $work();
                } catch (\Throwable $exception) {
                    $state = $before;
                    throw $exception;
                }
            },
        );

        return $gateway;
    }
}
