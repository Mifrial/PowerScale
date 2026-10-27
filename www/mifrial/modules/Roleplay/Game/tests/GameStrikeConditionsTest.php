<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeConditions;
use PHPUnit\Framework\TestCase;

/**
 * Условия удара: дефолты, ranged-дистанция и поворот.
 */
final class GameStrikeConditionsTest extends TestCase
{
    /**
     * Нет условий — удар по умолчанию.
     *
     * @return void
     */
    public function testDefaultsAreZeroStrike(): void
    {
        $conditions = (new GameStrikeConditions())->decode(null);

        self::assertSame('ipari', $conditions['distance']['type']);
        self::assertSame(['type' => 'fixed', 'value' => 0], $conditions['distance']['value']);
        self::assertSame(['base' => 0, 'size' => 0], $conditions['cover']);
        self::assertFalse($conditions['flank']);
        self::assertFalse($conditions['turn']);
    }

    /**
     * Дальний бой требует явную дистанцию.
     *
     * @return void
     */
    public function testRangedRequiresDistance(): void
    {
        $this->expectException(ActionException::class);
        (new GameStrikeConditions())->parse(null, true);
    }

    /**
     * Укрытие — размерная пара, не число.
     *
     * @return void
     */
    public function testCoverIsDimensionalAndNonNegative(): void
    {
        $conditions = (new GameStrikeConditions())->parse([
            'distance' => ['type' => 'step', 'value' => ['type' => 'fixed', 'value' => 1]],
            'cover' => ['base' => 1, 'size' => -1],
            'flank' => true,
        ]);

        self::assertSame(['base' => 1, 'size' => -1], $conditions['cover']);
        self::assertTrue($conditions['flank']);

        $this->expectException(ActionException::class);
        (new GameStrikeConditions())->parse(['cover' => ['base' => -1, 'size' => 0]]);
    }

    /**
     * Поворот снимает фланг.
     *
     * @return void
     */
    public function testTurnClearsFlank(): void
    {
        $conditions = (new GameStrikeConditions())->withTurn(
            (new GameStrikeConditions())->defaults(),
            true,
        );

        self::assertTrue($conditions['turn']);
        self::assertFalse($conditions['flank']);
    }

    /**
     * Битая строка отклоняется.
     *
     * @return void
     */
    public function testBrokenRowIsRejected(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameStrikeConditions())->decode(['distance' => '0']);
    }
}
