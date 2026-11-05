<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;
use PHPUnit\Framework\TestCase;

/**
 * Разбор узла maneuver.
 */
final class ManeuverSpecTest extends TestCase
{
    /**
     * Валидная спека отдаёт пары и метку.
     *
     * @return void
     */
    public function testReadsManeuver(): void
    {
        $maneuver = AbilityCombat::maneuver($this->document());

        self::assertNotNull($maneuver);
        self::assertSame('strike', $maneuver->getTrigger());
        self::assertSame('after_defense_before_check', $maneuver->getWindow());
        self::assertSame('auto_fail', $maneuver->getAttack());
        self::assertCount(1, $maneuver->getAttackerChecks());
        self::assertSame('deception', $maneuver->getAttackerChecks()[0]->getCheck());
        self::assertCount(2, $maneuver->getTargetChecks());
        self::assertTrue($maneuver->mayIgnore());
        self::assertCount(1, $maneuver->getMarks());
        self::assertSame(-2, $maneuver->getMarks()[0]->getValue());
        self::assertSame('circumstances', $maneuver->getMarks()[0]->getSourceCode());
    }

    /**
     * Старая форма без исходной метки отклоняется.
     *
     * @return void
     */
    public function testRejectsMarkWithoutSource(): void
    {
        $document = $this->document();
        unset($document['maneuver']['outcome']['on_target_failed'][0]['source_code']);

        $this->expectException(RuleSpecShapeException::class);
        AbilityCombat::maneuver($document);
    }

    /**
     * Неизвестное окно отклоняется.
     *
     * @return void
     */
    public function testRejectsUnknownWindow(): void
    {
        $document = $this->document();
        $document['maneuver']['window'] = 'before_defense';

        $this->expectException(RuleSpecShapeException::class);
        AbilityCombat::maneuver($document);
    }

    /**
     * Документ способности.
     *
     * @return array<string, mixed> Документ.
     */
    private function document(): array
    {
        return [
            'type' => 'skill',
            'maneuver' => [
                'trigger' => 'strike',
                'window' => 'after_defense_before_check',
                'attack' => 'auto_fail',
                'check' => [
                    'attacker' => [
                        ['check' => 'deception', 'characteristic' => 'communication'],
                    ],
                    'target' => [
                        ['check' => 'check-attention', 'characteristic' => 'attention'],
                        ['check' => 'check-melee-combat', 'characteristic' => 'melee-combat'],
                    ],
                ],
                'outcome' => [
                    'on_target_passed' => 'may_ignore',
                    'on_target_failed' => [
                        [
                            'kind' => 'advantage',
                            'value' => -2,
                            'source_code' => 'circumstances',
                            'scope' => 'defense_against_attacker',
                            'consumes' => 'first_defense',
                            'expires' => 'attacker_turn',
                        ],
                    ],
                ],
            ],
        ];
    }
}
