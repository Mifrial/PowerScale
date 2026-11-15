<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameSpellStability;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Spec\AbilitySpecs;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use PHPUnit\Framework\TestCase;

/**
 * План M10: стабильность поддерживаемого эффекта от превышения мощи.
 */
final class GameSpellStabilityTest extends TestCase
{
    /**
     * База `{3|0}` плюс превышение используемой мощи над мощью заклинания.
     *
     * @return void
     */
    public function testStabilityGrowsWithPowerExcess(): void
    {
        $stability = new GameSpellStability();
        $card = $this->card();

        self::assertPair(['base' => 3, 'size' => 0], $stability->value($card, ['x' => ['base' => 3, 'size' => 0]], new CharacteristicNumber(3, 0)));
        self::assertPair(['base' => 4, 'size' => 0], $stability->value($card, ['x' => ['base' => 3, 'size' => 0]], new CharacteristicNumber(4, 0)));
        self::assertPair(['base' => 5, 'size' => 0], $stability->value($card, ['x' => ['base' => 3, 'size' => 0]], new CharacteristicNumber(5, 0)));
    }

    /**
     * Мощь ниже заклинания не уменьшает стабильность.
     *
     * @return void
     */
    public function testStabilityDoesNotFallBelowBase(): void
    {
        $value = (new GameSpellStability())->value(
            $this->card(),
            ['x' => ['base' => 4, 'size' => 0]],
            new CharacteristicNumber(3, 0),
        );

        self::assertPair(['base' => 3, 'size' => 0], $value);
    }

    /**
     * Карточка с мощью-параметром X и поддерживаемой длительностью.
     *
     * @return AbilitySpellSpec Карточка.
     */
    private function card(): AbilitySpellSpec
    {
        $spec = AbilitySpecs::read([
            'type' => 'spell',
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
            'spell' => [
                'power' => ['type' => 'parameter', 'parameter_code' => 'x'],
                'control' => ['base' => 3, 'size' => -1],
                'duration' => [
                    'type' => 'sustained',
                    'power' => ['type' => 'parameter', 'parameter_code' => 'x'],
                ],
            ],
            'parameters' => [[
                'code' => 'x',
                'label' => 'X',
                'kind' => 'dimensional',
                'resolution' => 'activation',
                'default' => ['base' => 3, 'size' => 0],
                'min' => ['base' => 3, 'size' => -1],
                'max' => ['base' => 5, 'size' => 1],
            ]],
            'hit_resolution' => ['type' => 'none'],
        ]);
        self::assertInstanceOf(AbilitySpellSpec::class, $spec);

        return $spec;
    }

    /**
     * Сверяет размерную пару.
     *
     * @param array{base: int, size: int} $expected Ожидание.
     * @param \Mifrial\Roleplay\Rule\Value\DimensionalNumber $actual Значение.
     *
     * @return void
     */
    private static function assertPair(array $expected, \Mifrial\Roleplay\Rule\Value\DimensionalNumber $actual): void
    {
        self::assertSame($expected, ['base' => $actual->getBase(), 'size' => $actual->getSize()]);
    }
}
