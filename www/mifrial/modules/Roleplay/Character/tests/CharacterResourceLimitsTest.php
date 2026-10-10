<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceLimits;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет initialization, preserve и clamp resource rows.
 */
final class CharacterResourceLimitsTest extends TestCase
{
    /**
     * Initializes a missing row from the effective limit.
     *
     * @return void
     */
    public function testInitialCurrentUsesEffectiveLimit(): void
    {
        $rows = $this->limits()->buildRows(
            $this->slice(5),
            $this->validation(),
            $this->choices(),
        );

        self::assertCount(1, $rows);
        self::assertSame(5, $rows[0]->getCurrent()->toNative());
    }

    /**
     * Preserves current when the effective limit grows.
     *
     * @return void
     */
    public function testCurrentIsPreservedWhenLimitGrows(): void
    {
        $rows = $this->limits()->buildRows(
            $this->slice(8),
            $this->validation(),
            $this->choices(),
            [new ResourceRow('action-points', new ScalarResourceValue(3))],
        );

        self::assertSame(3, $rows[0]->getCurrent()->toNative());
    }

    /**
     * Clamps current when the effective limit shrinks.
     *
     * @return void
     */
    public function testCurrentIsClampedWhenLimitShrinks(): void
    {
        $rows = $this->limits()->buildRows(
            $this->slice(2),
            $this->validation(),
            $this->choices(),
            [new ResourceRow('action-points', new ScalarResourceValue(3))],
        );

        self::assertSame(2, $rows[0]->getCurrent()->toNative());
    }

    /**
     * Chooses the largest grant base and adds its permanent modifier.
     *
     * @return void
     */
    public function testPermanentGrantsBuildEffectiveLimit(): void
    {
        $choices = new CharacterChoices(
            '',
            '',
            [new CharacterAbilityChoice('grant', 1, '', '', null, null, null)],
            [],
            [],
            [],
            true,
        );
        $validation = new CharacterValidation([], ['grant' => 1], [], 0, [], [], [], true);
        $slice = new CharacterRuleSlice(
            1,
            1,
            'space',
            [$this->resourceRule(5), $this->grantRule()],
            [],
        );

        $rows = $this->limits()->buildRows($slice, $validation, $choices);

        self::assertSame(10, $rows[0]->getCurrent()->toNative());
    }

    /**
     * Creates the resource calculator.
     *
     * @return CharacterResourceLimits Calculator.
     */
    private function limits(): CharacterResourceLimits
    {
        return new CharacterResourceLimits(
            new FormulaEvaluations(),
            new CharacterResourceGrantReader(new CharacterDonorGrants()),
            new CharacterResourceArithmetic(),
        );
    }

    /**
     * Creates empty valid choices.
     *
     * @return CharacterChoices Choices.
     */
    private function choices(): CharacterChoices
    {
        return new CharacterChoices('', '', [], [], [], [], true);
    }

    /**
     * Creates an empty validation snapshot.
     *
     * @return CharacterValidation Validation.
     */
    private function validation(): CharacterValidation
    {
        return new CharacterValidation([], [], [], 0, [], [], [], true);
    }

    /**
     * Creates a scalar resource rule.
     *
     * @param int $base Limit base.
     *
     * @return CharacterRuleSlice Live slice.
     */
    private function slice(int $base): CharacterRuleSlice
    {
        return new CharacterRuleSlice(
            1,
            1,
            'space',
            [
                $this->resourceRule($base),
            ],
            [],
        );
    }

    /**
     * Creates the resource rule used by tests.
     *
     * @param int $base Limit base.
     *
     * @return CharacterResolvedRule Resource rule.
     */
    private function resourceRule(int $base): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                'action-points',
                true,
                'resource',
                'Action points',
                '',
                [
                    'is_dimensional' => false,
                    'auto_add' => true,
                    'check_token' => false,
                    'limit' => ['base' => $base, 'adjustments' => []],
                ],
                [],
                [],
                'published',
                '',
                DateTime::fromUnix(0),
            ),
            [],
        );
    }

    /**
     * Creates an ability with resource grants.
     *
     * @return CharacterResolvedRule Ability rule.
     */
    private function grantRule(): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                2,
                2,
                'grant',
                true,
                'ability',
                'Grant',
                '',
                [
                    'type' => 'trait',
                    'grants' => [
                        [
                            'level' => 1,
                            'grants' => [
                                [
                                    'type' => 'resource',
                                    'resource_code' => 'action-points',
                                    'limit' => 8,
                                    'permanent' => true,
                                ],
                                [
                                    'type' => 'resource_limit_change',
                                    'resource_code' => 'action-points',
                                    'source_code' => 'grant',
                                    'amount' => 2,
                                    'permanent' => true,
                                ],
                            ],
                        ],
                    ],
                ],
                [],
                [],
                'published',
                '',
                DateTime::fromUnix(0),
            ),
            [],
        );
    }
}
