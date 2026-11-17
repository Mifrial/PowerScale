<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Форма spell_upgrade: дальность и её сочетания.
 */
final class SpellUpgradeSpecTest extends TestCase
{
    /**
     * Дальность разбирается в шаги мощи и добавку контроля.
     *
     * @return void
     */
    public function testRangeIsParsed(): void
    {
        $read = RuleSpecs::read('ability', $this->skill([
            'action_point_delta' => 0,
            'keyword_codes' => ['nolomancy'],
            'range' => ['power_size_per_step' => 1, 'control_base' => 2],
        ]));

        self::assertFalse($read->isBroken(), (string) $read->getShapeKey());
        $spec = $read->getSpec();
        self::assertInstanceOf(AbilityWithBase::class, $spec);
        $range = $spec->getBase()->getSpellUpgrade()?->getRange();
        self::assertNotNull($range);
        self::assertSame(1, $range->getPowerSizePerStep());
        self::assertSame(2, $range->getControlBase());
    }

    /**
     * Нулевой шаг мощи или отрицательный контроль ломают форму.
     *
     * @return void
     */
    public function testDegenerateRangeIsBroken(): void
    {
        $zeroStep = RuleSpecs::read('ability', $this->skill([
            'action_point_delta' => 0,
            'range' => ['power_size_per_step' => 0, 'control_base' => 2],
        ]));
        $negativeControl = RuleSpecs::read('ability', $this->skill([
            'action_point_delta' => 0,
            'range' => ['power_size_per_step' => 1, 'control_base' => -1],
        ]));

        self::assertTrue($zeroStep->isBroken());
        self::assertTrue($negativeControl->isBroken());
    }

    /**
     * Одно значение не может быть и пробитием, и дальностью.
     *
     * @return void
     */
    public function testRangeWithPenetrationIsBroken(): void
    {
        $read = RuleSpecs::read('ability', $this->skill([
            'action_point_delta' => 1,
            'resistance_penetration_per_step' => 1,
            'range' => ['power_size_per_step' => 1, 'control_base' => 2],
        ]));

        self::assertTrue($read->isBroken());
    }

    /**
     * Минимальная карточка навыка с улучшением.
     *
     * @param array<string, mixed> $upgrade Улучшение.
     *
     * @return array<string, mixed> Документ spec.
     */
    private function skill(array $upgrade): array
    {
        return [
            'type' => 'skill',
            'study' => ['paths' => 'any'],
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
            'grants' => [],
            'multiple' => true,
            'domain_ref' => 'magic-path',
            'requirements' => [],
            'spell_upgrade' => $upgrade,
            'parent_ability_code' => null,
        ];
    }
}
