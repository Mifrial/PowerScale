<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Service\GamePendingActionCosts;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет жизненный цикл ожидания действия без карточек.
 */
final class GamePendingActionCostsTest extends TestCase
{
    /**
     * Надбавка к следующей атаке снимается любым действием, но применяется только к атаке.
     *
     * @return void
     */
    public function testDeclareAppliesAttackCostOnlyForAttack(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = new CharacterRuleSlice(1, 1, 'test', [], []);
        $pending = [
            'character:1' => [[
                'sourceRuleCode' => 'fast',
                'effect' => [
                    'type' => 'next_action_attack_cost',
                    'resource_code' => 'action-points',
                    'delta' => 1,
                ],
            ]],
        ];

        $attack = $costs->declare($slice, [], 'fast', 'character', 1, $pending, ['action-points' => 2], true);
        self::assertSame(3, $attack['amounts']['action-points']);
        self::assertSame([], $attack['pending']['character:1'] ?? []);

        $wait = $costs->declare($slice, [], 'wait', 'character', 1, $pending, ['action-points' => 2], false);
        self::assertSame(2, $wait['amounts']['action-points']);
        self::assertSame([], $wait['pending']['character:1'] ?? []);
    }

    /**
     * Объявление действия не трогает висящую проверку.
     *
     * @return void
     */
    public function testDeclareKeepsAfterActionCheckModifier(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = new CharacterRuleSlice(1, 1, 'test', [], []);
        $pending = [
            'character:1' => [[
                'sourceRuleCode' => 'synthetic',
                'effect' => [
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['check-hit'],
                    'delta' => -2,
                ],
            ]],
        ];

        $declared = $costs->declare($slice, [], 'synthetic', 'character', 1, $pending, ['action-points' => 2]);

        self::assertCount(1, $declared['pending']['character:1'] ?? []);
    }

    /**
     * Завершение уменьшает старую проверку и только потом дописывает новую.
     *
     * @return void
     */
    public function testCompleteReducesOldAfterActionAndAppendsNew(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = $this->sliceWithAfterAction();
        $pending = [
            'character:1' => [[
                'sourceRuleCode' => 'old',
                'effect' => [
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['check-hit'],
                    'delta' => -2,
                    'applies_to' => 'attacker_hit',
                ],
            ]],
        ];

        $partial = $costs->complete(
            $slice,
            [],
            'synthetic',
            'character',
            1,
            $pending,
            ['action-points' => 1],
        );
        $effects = array_map(
            static fn (array $entry): array => $entry['effect'],
            $partial['character:1'] ?? [],
        );
        self::assertCount(2, $effects);
        self::assertSame(1, $effects[0]['amount']);
        self::assertSame(2, $effects[1]['amount']);

        $full = $costs->complete(
            $slice,
            [],
            'synthetic',
            'character',
            1,
            $pending,
            ['action-points' => 2],
        );
        $effects = array_map(
            static fn (array $entry): array => $entry['effect'],
            $full['character:1'] ?? [],
        );
        self::assertCount(1, $effects);
        self::assertSame(2, $effects[0]['amount']);
    }

    /**
     * Проверка защиты не получает эффект attacker_hit, а без области — получает.
     *
     * @return void
     */
    public function testCheckAdvantagesFiltersSide(): void
    {
        $costs = new GamePendingActionCosts();
        $pending = [
            'character:1' => [
                [
                    'sourceRuleCode' => 'old',
                    'effect' => [
                        'type' => 'after_action_until_resource_spent_check_modifier',
                        'resource_code' => 'action-points',
                        'amount' => 2,
                        'check_codes' => ['check-hit'],
                        'delta' => -2,
                        'applies_to' => 'attacker_hit',
                    ],
                ],
                [
                    'sourceRuleCode' => 'old',
                    'effect' => [
                        'type' => 'after_action_until_resource_spent_check_modifier',
                        'resource_code' => 'action-points',
                        'amount' => 2,
                        'check_codes' => ['check-hit'],
                        'delta' => -2,
                    ],
                ],
            ],
        ];

        $attacker = $costs->checkAdvantages($pending, 'character', 1, 'check-hit', 'attacker');
        self::assertCount(2, $attacker);
        self::assertNull($attacker[0]->getSourceCode());
        self::assertNull($attacker[1]->getSourceCode());

        $defender = $costs->checkAdvantages($pending, 'character', 1, 'check-hit', 'defender');
        self::assertCount(1, $defender);
        self::assertNull($defender[0]->getSourceCode());
    }

    /**
     * Завершение одного актора не затирает ожидание другого.
     *
     * @return void
     */
    public function testCompletePreservesOtherActors(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = new CharacterRuleSlice(1, 1, 'test', [], []);
        $pending = [
            'character:1' => [[
                'sourceRuleCode' => 'old',
                'effect' => [
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['check-hit'],
                    'delta' => -2,
                ],
            ]],
            'character:2' => [[
                'sourceRuleCode' => 'old',
                'effect' => [
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['check-hit'],
                    'delta' => -2,
                ],
            ]],
        ];

        $first = $costs->complete(
            $slice,
            [],
            'synthetic',
            'character',
            1,
            $pending,
            ['action-points' => 2],
        );
        self::assertArrayNotHasKey('character:1', $first);
        self::assertArrayHasKey('character:2', $first);

        $second = $costs->complete(
            $slice,
            [],
            'synthetic',
            'character',
            2,
            $first,
            ['action-points' => 2],
        );
        self::assertSame([], $second);
    }

    /**
     * Завершение пишет хвосты следующей атаки с целью.
     *
     * @return void
     */
    public function testCompleteWritesAttackTailForSameTarget(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = $this->sliceWithAttackTail();

        $pending = $costs->complete(
            $slice,
            [],
            'synthetic',
            'character',
            1,
            [],
            ['action-points' => 2],
            ['npc:7'],
        );

        $effects = array_map(
            static fn (array $entry): array => $entry['effect'],
            $pending['character:1'] ?? [],
        );
        self::assertCount(2, $effects);
        self::assertSame('next_action_attack_accuracy', $effects[0]['type']);
        self::assertSame(1, $effects[0]['delta']);
        self::assertSame('npc:7', $effects[0]['targetKey']);
        self::assertSame(['components' => ['strike'], 'hit_count' => 1], $effects[0]['scope']);
        self::assertSame('next_action_attack_score_adjust', $effects[1]['type']);
        self::assertSame(-1, $effects[1]['oneDelta']);
        self::assertSame('npc:7', $effects[1]['targetKey']);
    }

    /**
     * Следующее действие снимает чужие хвосты атаки и не трогает прочие.
     *
     * @return void
     */
    public function testCompleteConsumesPreviousAttackTails(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = new CharacterRuleSlice(1, 1, 'test', [], []);
        $pending = [
            'character:1' => [
                [
                    'sourceRuleCode' => 'old',
                    'effect' => [
                        'type' => 'next_action_attack_accuracy',
                        'delta' => 1,
                        'same_target' => false,
                        'scope' => ['components' => ['strike'], 'hit_count' => 1],
                    ],
                ],
                [
                    'sourceRuleCode' => 'old',
                    'effect' => [
                        'type' => 'next_action_attack_score_adjust',
                        'oneDelta' => -1,
                        'faceDelta' => 0,
                        'same_target' => false,
                    ],
                ],
                [
                    'sourceRuleCode' => 'old',
                    'effect' => [
                        'type' => 'after_action_until_resource_spent_check_modifier',
                        'resource_code' => 'action-points',
                        'amount' => 2,
                        'check_codes' => ['check-hit'],
                        'delta' => -2,
                    ],
                ],
            ],
        ];

        $result = $costs->complete($slice, [], 'wait', 'character', 1, $pending, []);

        self::assertCount(1, $result['character:1'] ?? []);
        self::assertSame(
            'after_action_until_resource_spent_check_modifier',
            $result['character:1'][0]['effect']['type'],
        );
    }

    /**
     * Хвосты атаки фильтруются по компоненту, попаданию и цели.
     *
     * @return void
     */
    public function testAttackAdjustmentFiltersByScopeHitAndTarget(): void
    {
        $costs = new GamePendingActionCosts();
        $pending = [
            'character:1' => [
                [
                    'sourceRuleCode' => 'a',
                    'effect' => [
                        'type' => 'next_action_attack_accuracy',
                        'delta' => 1,
                        'same_target' => true,
                        'targetKey' => 'npc:1',
                        'scope' => ['components' => ['strike'], 'hit_count' => 1],
                    ],
                ],
                [
                    'sourceRuleCode' => 'b',
                    'effect' => [
                        'type' => 'next_action_attack_accuracy',
                        'delta' => 2,
                        'same_target' => false,
                        'scope' => ['components' => ['shoot'], 'hit_count' => 1],
                    ],
                ],
                [
                    'sourceRuleCode' => 'c',
                    'effect' => [
                        'type' => 'next_action_attack_accuracy',
                        'delta' => 4,
                        'same_target' => false,
                        'scope' => ['components' => ['strike'], 'hit_count' => 'all'],
                    ],
                ],
                [
                    'sourceRuleCode' => 'd',
                    'effect' => [
                        'type' => 'next_action_attack_score_adjust',
                        'oneDelta' => -1,
                        'faceDelta' => 0,
                        'same_target' => true,
                        'targetKey' => 'npc:2',
                    ],
                ],
                [
                    'sourceRuleCode' => 'e',
                    'effect' => [
                        'type' => 'next_action_attack_score_adjust',
                        'oneDelta' => -2,
                        'faceDelta' => 1,
                        'same_target' => false,
                    ],
                ],
            ],
        ];

        $tail = $costs->attackAdjustment($pending, 'character', 1, 'strike', ['npc:1']);

        self::assertSame(5, $tail['accuracy']);
        self::assertSame(-2, $tail['adjustment']->getOneDelta());
        self::assertSame(1, $tail['adjustment']->getFaceDelta());
        self::assertSame([], $tail['adjustment']->getRemap());
    }

    /**
     * Срез с одной карточкой хвостов следующей атаки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function sliceWithAttackTail(): CharacterRuleSlice
    {
        $record = new RuleVersionRecord(
            1,
            1,
            'synthetic',
            true,
            'ability',
            'Synthetic',
            '',
            [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                'requirements' => [],
                'grants' => [],
                'parent_ability_code' => null,
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'label' => 'Действие',
                ]],
                'action_effects' => [[
                    'type' => 'next_action_attack_accuracy',
                    'delta' => 1,
                    'same_target' => true,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ], [
                    'type' => 'next_action_attack_score_adjust',
                    'oneDelta' => -1,
                    'faceDelta' => 0,
                    'same_target' => true,
                ]],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );

        return new CharacterRuleSlice(
            1,
            1,
            'test',
            [new CharacterResolvedRule($record, [])],
            [],
        );
    }

    /**
     * Хвост Стремительного ставится завершением подготовки.
     *
     * @return void
     */
    public function testCompleteWritesDodgeSoakTail(): void
    {
        $costs = new GamePendingActionCosts();
        $pending = $costs->complete(
            $this->sliceWithDodgeSoakTail(),
            [],
            'synthetic',
            'character',
            1,
            [],
            ['action-points' => 1],
        );

        $effect = $pending['character:1'][0]['effect'] ?? [];
        self::assertSame('next_action_attack_dodge_soak_from_reaction', $effect['type'] ?? null);
        self::assertSame(2, $effect['max_total_action_cost'] ?? null);
        self::assertSame(['components' => ['strike'], 'hit_count' => 1], $effect['scope'] ?? null);
        self::assertArrayNotHasKey('armed', $effect);
    }

    /**
     * Хвост взводится только атакой в пределах потолка ОД.
     *
     * @return void
     */
    public function testDeclareArmsDodgeSoakTailWithinCost(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = $this->sliceWithDodgeSoakTail();
        $pending = $this->dodgeSoakPending();

        $attack = $costs->declare($slice, [], 'synthetic', 'character', 1, $pending, ['action-points' => 2], true);
        self::assertTrue($attack['pending']['character:1'][0]['effect']['armed'] ?? false);

        $tooExpensive = $costs->declare($slice, [], 'synthetic', 'character', 1, $pending, ['action-points' => 3], true);
        self::assertSame([], $tooExpensive['pending']['character:1'] ?? []);

        $wait = $costs->declare($slice, [], 'wait', 'character', 1, $pending, ['action-points' => 1], false);
        self::assertSame([], $wait['pending']['character:1'] ?? []);
    }

    /**
     * Атака снимает взведённый хвост после расчёта.
     *
     * @return void
     */
    public function testCompleteConsumesArmedDodgeSoakTail(): void
    {
        $costs = new GamePendingActionCosts();
        $slice = $this->sliceWithDodgeSoakTail();
        $pending = $costs->declare(
            $slice,
            [],
            'synthetic',
            'character',
            1,
            $this->dodgeSoakPending(),
            ['action-points' => 1],
            true,
        )['pending'];

        $result = $costs->complete($slice, [], 'wait', 'character', 1, $pending, ['action-points' => 1]);

        self::assertSame([], $result['character:1'] ?? []);
    }

    /**
     * Хвост смягчения без взвода.
     *
     * @return array<string, mixed> Карта.
     */
    private function dodgeSoakPending(): array
    {
        return [
            'character:1' => [[
                'sourceRuleCode' => 'synthetic',
                'effect' => [
                    'type' => 'next_action_attack_dodge_soak_from_reaction',
                    'max_total_action_cost' => 2,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ],
            ]],
        ];
    }

    /**
     * Срез с одной карточкой хвоста смягчения.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function sliceWithDodgeSoakTail(): CharacterRuleSlice
    {
        $record = new RuleVersionRecord(
            1,
            1,
            'synthetic',
            true,
            'ability',
            'Synthetic',
            '',
            [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                'requirements' => [],
                'grants' => [],
                'parent_ability_code' => null,
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 1,
                    'label' => 'Действие',
                ]],
                'action_effects' => [[
                    'type' => 'next_action_attack_dodge_soak_from_reaction',
                    'max_total_action_cost' => 2,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ]],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );

        return new CharacterRuleSlice(
            1,
            1,
            'test',
            [new CharacterResolvedRule($record, [])],
            [],
        );
    }

    /**
     * Срез с одной карточкой after_action.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function sliceWithAfterAction(): CharacterRuleSlice
    {
        $record = new RuleVersionRecord(
            1,
            1,
            'synthetic',
            true,
            'ability',
            'Synthetic',
            '',
            [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                'requirements' => [],
                'grants' => [],
                'parent_ability_code' => null,
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'label' => 'Действие',
                ]],
                'action_effects' => [[
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['check-hit'],
                    'delta' => -2,
                    'applies_to' => 'attacker_hit',
                ]],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );

        return new CharacterRuleSlice(
            1,
            1,
            'test',
            [new CharacterResolvedRule($record, [])],
            [],
        );
    }
}
