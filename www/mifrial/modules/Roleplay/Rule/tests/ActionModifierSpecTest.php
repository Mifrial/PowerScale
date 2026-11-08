<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ModifierOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamageOutcomeEffect;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\ActionEffects;
use PHPUnit\Framework\TestCase;

/**
 * Разбор модификатора действия и режима урона по себе.
 */
final class ActionModifierSpecTest extends TestCase
{
    /**
     * Без mode урон по себе берёт сильнейшую покрытую строку.
     *
     * @return void
     */
    public function testSelfDamageDefaultsToStrongest(): void
    {
        self::assertSame(SelfDamageOutcomeEffect::MODE_STRONGEST, $this->selfDamage([])->getMode());
    }

    /**
     * Явный each читается из спеки.
     *
     * @return void
     */
    public function testSelfDamageModeIsParsed(): void
    {
        self::assertSame(SelfDamageOutcomeEffect::MODE_EACH, $this->selfDamage(['mode' => 'each'])->getMode());
        self::assertSame(
            SelfDamageOutcomeEffect::MODE_STRONGEST,
            $this->selfDamage(['mode' => 'strongest'])->getMode(),
        );
    }

    /**
     * Без internal урон по себе остаётся внешним.
     *
     * @return void
     */
    public function testSelfDamageDefaultsToExternalWound(): void
    {
        self::assertFalse($this->selfDamage([])->isInternal());
    }

    /**
     * Явный internal читается из спеки.
     *
     * @return void
     */
    public function testSelfDamageInternalIsParsed(): void
    {
        self::assertTrue($this->selfDamage(['internal' => true])->isInternal());
    }

    /**
     * Неизвестный режим ломает только эту версию.
     *
     * @return void
     */
    public function testUnknownModeIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->selfDamage(['mode' => 'sum']);
    }

    /**
     * Собирает self_damage внутри action_modifier.
     *
     * @param array<string, mixed> $extra Поля self_damage.
     *
     * @return SelfDamageOutcomeEffect Последствие.
     */
    private function selfDamage(array $extra): SelfDamageOutcomeEffect
    {
        $effect = ActionEffects::one([
            'type' => 'action_modifier',
            'trigger' => 'after_strike',
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
            'check' => ['code' => 'willpower-check', 'difficulty' => 3],
            'effects' => [
                $extra + [
                    'type' => 'self_damage',
                    'size_delta' => -1,
                    'damage_type_code' => 'blunt',
                    'ignore_defense' => true,
                ],
            ],
        ], 'action_effects');

        self::assertInstanceOf(ActionModifierEffect::class, $effect);
        $outcomes = $effect->getEffects();
        self::assertCount(1, $outcomes);
        self::assertInstanceOf(SelfDamageOutcomeEffect::class, $outcomes[0]);
        self::assertInstanceOf(ModifierOutcomeEffect::class, $outcomes[0]);

        return $outcomes[0];
    }
}
