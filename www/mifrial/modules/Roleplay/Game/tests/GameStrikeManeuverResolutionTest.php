<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeManeuverResolution;
use PHPUnit\Framework\TestCase;

/**
 * Выбор эффекта манёвра по РУ удара.
 */
final class GameStrikeManeuverResolutionTest extends TestCase
{
    /**
     * Ниже порога эффекта нет.
     *
     * @return void
     */
    public function testBelowThresholdHasNoEffects(): void
    {
        self::assertNull((new GameStrikeManeuverResolution())->select($this->maneuver(), 2));
    }

    /**
     * На пороге срабатывает базовый эффект.
     *
     * @return void
     */
    public function testThresholdReturnsBaseEffects(): void
    {
        $selected = (new GameStrikeManeuverResolution())->select($this->maneuver(), 3);

        self::assertNotNull($selected);
        self::assertFalse($selected['boosted']);
        self::assertSame(
            [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
            $selected['effects'],
        );
    }

    /**
     * Усиление заменяет базовый эффект, а не складывается.
     *
     * @return void
     */
    public function testBoostReplacesBaseEffects(): void
    {
        $selected = (new GameStrikeManeuverResolution())->select($this->maneuver(), 4);

        self::assertNotNull($selected);
        self::assertTrue($selected['boosted']);
        self::assertSame(
            [['kind' => 'state', 'state' => 'unstable', 'amount' => 2]],
            $selected['effects'],
        );
    }

    /**
     * Без списка замены эффекты усиления добавляются к базовым.
     *
     * @return void
     */
    public function testBoostWithoutReplacesAddsEffects(): void
    {
        $maneuver = $this->maneuver();
        $maneuver['boost']['replaces'] = [];

        $selected = (new GameStrikeManeuverResolution())->select($maneuver, 4);

        self::assertNotNull($selected);
        self::assertSame(
            [
                ['kind' => 'state', 'state' => 'unstable', 'amount' => 1],
                ['kind' => 'state', 'state' => 'unstable', 'amount' => 2],
            ],
            $selected['effects'],
        );
    }

    /**
     * Битый снимок отклоняется.
     *
     * @return void
     */
    public function testBrokenSnapshotIsRejected(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameStrikeManeuverResolution())->select(['ruleCode' => 'maneuver'], 4);
    }

    /**
     * Снимок с базовым и усиленным порогами.
     *
     * @return array<string, mixed> Снимок.
     */
    private function maneuver(): array
    {
        return [
            'ruleCode' => 'razbit-zaschitu',
            'minSuccess' => 3,
            'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
            'boost' => [
                'ruleCode' => 'vskryt-slabost',
                'minSuccess' => 4,
                'replaces' => [['kind' => 'state', 'state' => 'unstable']],
                'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 2]],
            ],
        ];
    }
}
