<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikeSuccessModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicFromSuccessRatingEffect;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет арифметику бонуса к характеристике урона от рейтинга успеха.
 */
final class GameStrikeSuccessModifierTest extends TestCase
{
    /**
     * Шаг бонуса и потолок по РУ.
     *
     * @return void
     */
    public function testBonusStepsAndCap(): void
    {
        $modifier = new GameStrikeSuccessModifier();
        $effects = [$this->effect(2, 3, ['strike'], 1)];

        self::assertSame(0, $modifier->fromEffects($effects, 'strike', 1, 0));
        self::assertSame(0, $modifier->fromEffects($effects, 'strike', 1, 1));
        self::assertSame(2, $modifier->fromEffects($effects, 'strike', 1, 4));
        self::assertSame(2, $modifier->fromEffects($effects, 'strike', 1, 5));
        self::assertSame(3, $modifier->fromEffects($effects, 'strike', 1, 8));
        self::assertSame(3, $modifier->fromEffects($effects, 'strike', 1, 9));
    }

    /**
     * Потолок меньше шага и отрицательный РУ.
     *
     * @return void
     */
    public function testCapWinsAndNegativeSuccessIsZero(): void
    {
        $modifier = new GameStrikeSuccessModifier();
        $effects = [$this->effect(2, 1, ['strike'], 1)];

        self::assertSame(1, $modifier->fromEffects($effects, 'strike', 1, 8));
        self::assertSame(0, $modifier->fromEffects($effects, 'strike', 1, -4));
    }

    /**
     * Нулевой делитель не делит.
     *
     * @return void
     */
    public function testZeroFloorDivGivesZero(): void
    {
        $modifier = new GameStrikeSuccessModifier();
        $effects = [$this->effect(0, 3, ['strike'], 1)];

        self::assertSame(0, $modifier->fromEffects($effects, 'strike', 1, 8));
    }

    /**
     * Чужой компонент и попадание за пределами scope не считаются.
     *
     * @return void
     */
    public function testScopeFiltersComponentAndHit(): void
    {
        $modifier = new GameStrikeSuccessModifier();
        $effects = [
            $this->effect(2, 3, ['throw'], 1),
            $this->effect(2, 3, ['strike'], 1),
        ];

        self::assertSame(0, $modifier->fromEffects($effects, 'shoot', 1, 8));
        self::assertSame(3, $modifier->fromEffects($effects, 'strike', 1, 8));
        self::assertSame(0, $modifier->fromEffects($effects, 'strike', 2, 8));
        self::assertSame(3, $modifier->fromEffects([$this->effect(2, 3, ['strike'], 'all')], 'strike', 2, 8));
    }

    /**
     * Несколько эффектов складываются.
     *
     * @return void
     */
    public function testEffectsSum(): void
    {
        $modifier = new GameStrikeSuccessModifier();
        $effects = [
            $this->effect(2, 3, ['strike'], 'all'),
            $this->effect(4, 2, ['strike'], 'all'),
        ];

        self::assertSame(5, $modifier->fromEffects($effects, 'strike', 1, 8));
    }

    /**
     * Эффект-ветка.
     *
     * @param int $floorDiv Делитель.
     * @param int $cap Потолок.
     * @param array<int, string> $components Компоненты.
     * @param int|string $hitCount Попадания.
     *
     * @return CurrentActionAttackCharacteristicFromSuccessRatingEffect Эффект.
     */
    private function effect(
        int $floorDiv,
        int $cap,
        array $components,
        int|string $hitCount,
    ): CurrentActionAttackCharacteristicFromSuccessRatingEffect {
        return new CurrentActionAttackCharacteristicFromSuccessRatingEffect(
            $floorDiv,
            $cap,
            new AttackScope($components, $hitCount),
        );
    }
}
