<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Service\GameSpellSourceBonus;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use PHPUnit\Framework\TestCase;

/**
 * Бонус `spell_source_power`: производные способности и область источника.
 */
final class GameSpellSourceBonusTest extends TestCase
{
    /**
     * Производная способность без записи в выборах всё равно даёт бонус.
     *
     * @return void
     */
    public function testDerivedAbilityGivesBonus(): void
    {
        $bonus = $this->bonus(
            [],
            ['core-flow-optimization' => 1],
            ['kind' => 'item', 'inventoryId' => 1],
            'item',
        );

        self::assertSame(1, $bonus);
    }

    /**
     * Область `source` без выбранного предмета не даёт бонус.
     *
     * @return void
     */
    public function testSourceScopeNeedsChosenItem(): void
    {
        $bonus = $this->bonus(
            [],
            ['core-flow-optimization' => 1],
            ['kind' => 'item', 'inventoryId' => 1],
            'source',
        );

        self::assertSame(0, $bonus);
    }

    /**
     * Область `source` действует на выбранный предмет.
     *
     * @return void
     */
    public function testSourceScopeMatchesChosenItem(): void
    {
        $ability = new CharacterAbilityChoice(
            'core-flow-optimization',
            1,
            '',
            '',
            null,
            null,
            null,
            [],
            ['core' => 1],
        );
        $bonus = $this->bonus(
            [$ability],
            ['core-flow-optimization' => 1],
            ['kind' => 'item', 'inventoryId' => 1],
            'source',
        );

        self::assertSame(1, $bonus);
    }

    /**
     * Считает бонус на узком срезе.
     *
     * @param array<int, CharacterAbilityChoice> $abilities Выборы.
     * @param array<string, int> $levels Уровни листа.
     * @param array<string, mixed> $source Источник.
     * @param string $scopeKind Область формы.
     *
     * @return int Бонус.
     */
    private function bonus(array $abilities, array $levels, array $source, string $scopeKind): int
    {
        $scope = $scopeKind === 'source'
            ? ['kind' => 'source', 'target_code' => 'core']
            : ['kind' => 'item', 'item_code' => 'magic-core'];
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('core-flow-optimization', 'ability', [
                'type' => 'skill',
                'spell_source_power' => [
                    'scope' => $scope,
                    'path_scoped' => true,
                    'amount' => [
                        'type' => 'ability_level',
                        'ability_code' => 'core-flow-optimization',
                        'multiplier' => 1,
                    ],
                ],
            ]),
            $this->rule('magic-core', 'item', [
                'category' => 'equipment',
                'innate' => true,
                'special_rule_codes' => [],
            ]),
        ], []);
        $model = new CharacterChoices('Hero', 'human', $abilities, [], [], [], true);
        $choices = [
            'inventory' => [['id' => 1, 'ruleCode' => 'magic-core', 'equipped' => true]],
        ];
        $sheet = [
            'abilityLevels' => $levels,
            'characteristics' => [
                ['characteristicCode' => 'magic-power', 'value' => ['base' => 4, 'size' => 0]],
            ],
        ];
        $path = new MagicPathSpec(null, 'check-spell-cast', 'magic-power', 'magic-control', null, []);

        return (new GameSpellSourceBonus(new FormulaEvaluations()))->bonus(
            $slice,
            $model,
            $sheet,
            $path,
            $source,
            $choices,
        );
    }

    /**
     * Карточка правила.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Спека.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(1, 1, $code, true, $type, 'Name', '', $spec, [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }
}
