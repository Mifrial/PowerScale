<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Service\GameCheckRoll;
use Mifrial\Roleplay\Game\Service\GameStrikeAmounts;
use Mifrial\Roleplay\Game\Service\GameStrikeLayers;
use Mifrial\Roleplay\Game\Service\GameStrikeRules;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use PHPUnit\Framework\TestCase;

/**
 * Помеха широкого удара берётся из спека карточки, а не из константы.
 */
final class GameStrikeRulesTest extends TestCase
{
    /**
     * Дельта карточки умножается на число целей сверх первой.
     *
     * @return void
     */
    public function testWidePenaltyScalesFromCard(): void
    {
        $rules = $this->rules(-1);

        $three = $rules->wideTargetCheckAdvantage(1, 1, 'wide-card', 3);
        self::assertNotNull($three);
        self::assertSame('circumstances', $three->getSourceCode());
        self::assertSame(-2, $three->getDelta());
        self::assertSame(-1, $rules->wideTargetCheckAdvantage(1, 1, 'wide-card', 2)?->getDelta());
        self::assertNull($rules->wideTargetCheckAdvantage(1, 1, 'wide-card', 1));
    }

    /**
     * Карточка с дельтой −2 даёт вдвое большую помеху.
     *
     * @return void
     */
    public function testWidePenaltyUsesCardDelta(): void
    {
        self::assertSame(-4, $this->rules(-2)->wideTargetCheckAdvantage(1, 1, 'wide-card', 3)?->getDelta());
    }

    /**
     * Без поля в карточке помехи нет.
     *
     * @return void
     */
    public function testWidePenaltyIsAbsentWithoutCardField(): void
    {
        self::assertNull($this->rules(null)->wideTargetCheckAdvantage(1, 1, 'wide-card', 3));
    }

    /**
     * Модификатор отсекается по типу урона и занятым рукам.
     *
     * @return void
     */
    public function testCharacteristicModifierFiltersByHandsAndDamageType(): void
    {
        $slice = $this->filterSlice();
        $rules = $this->rulesFor($slice);

        self::assertSame(
            9,
            $rules->actionCharacteristicModifier(
                $slice,
                'strike-card',
                'strike',
                1,
                ['damageTypeCode' => 'slashing', 'occupyHands' => 2],
            ),
        );
        self::assertSame(
            6,
            $rules->actionCharacteristicModifier(
                $slice,
                'strike-card',
                'strike',
                1,
                ['damageTypeCode' => 'slashing', 'occupyHands' => 1],
            ),
        );
        self::assertSame(
            5,
            $rules->actionCharacteristicModifier(
                $slice,
                'strike-card',
                'strike',
                1,
                ['damageTypeCode' => 'blunt', 'occupyHands' => 2],
            ),
        );
        self::assertSame(
            2,
            $rules->actionCharacteristicModifier($slice, 'strike-card', 'strike', 1),
        );
        self::assertSame(
            0,
            $rules->actionCharacteristicModifier($slice, 'strike-card', 'throw', 1),
        );
    }

    /**
     * Контекст берёт тип урона профиля и руки выбранной строки инвентаря.
     *
     * @return void
     */
    public function testAttackFilterContextReadsProfileAndInventoryHands(): void
    {
        $slice = $this->filterSlice();
        $rules = $this->rulesFor($slice);
        $choices = ['inventory' => [['id' => 7, 'ruleCode' => 'sword', 'equipped' => true, 'occupyHands' => 2]]];

        $context = $rules->attackFilterContext(1, 1, 'sword', 'strike', 0, $choices, 7);
        self::assertSame('slashing', $context['damageTypeCode']);
        self::assertSame(2, $context['occupyHands']);

        $default = $rules->attackFilterContext(
            1,
            1,
            'sword',
            'strike',
            0,
            ['inventory' => [['id' => 7, 'ruleCode' => 'sword', 'equipped' => true]]],
            7,
        );
        self::assertSame(1, $default['occupyHands']);
    }

    /**
     * Дополнительные руки профиля прибавляются к удержанию.
     *
     * @return void
     */
    public function testAttackFilterContextAddsProfileExtraHands(): void
    {
        $slice = $this->filterSlice();
        $rules = $this->rulesFor($slice);
        $choices = ['inventory' => [['id' => 9, 'ruleCode' => 'bow', 'equipped' => true]]];

        $context = $rules->attackFilterContext(1, 1, 'bow', 'shoot', 1, $choices, 9);
        self::assertSame(2, $context['occupyHands']);
        self::assertSame(
            2,
            $rules->occupyHands(1, 1, $choices, 9, 'bow', 'shoot', 1),
        );
        self::assertSame(
            1,
            $rules->occupyHands(1, 1, $choices, 9, 'bow', 'strike', 0),
        );
    }

    /**
     * Эффект купленного дочернего навыка подмешивается и проходит фильтр.
     *
     * @return void
     */
    public function testCharacteristicModifierAppliesOwnedChildAbility(): void
    {
        $slice = $this->filterSlice();
        $rules = $this->rulesFor($slice);
        $context = ['damageTypeCode' => 'slashing', 'occupyHands' => 2];

        self::assertSame(
            9,
            $rules->actionCharacteristicModifier($slice, 'strike-card', 'strike', 1, $context),
        );
        self::assertSame(
            10,
            $rules->actionCharacteristicModifier($slice, 'strike-card', 'strike', 1, $context, ['double-power' => 1]),
        );
        self::assertSame(
            9,
            $rules->actionCharacteristicModifier($slice, 'strike-card', 'strike', 1, $context, ['double-power' => 0]),
        );
        self::assertSame(
            5,
            $rules->actionCharacteristicModifier(
                $slice,
                'strike-card',
                'strike',
                1,
                ['damageTypeCode' => 'piercing', 'occupyHands' => 2],
                ['double-power' => 1],
            ),
        );
        self::assertSame(
            6,
            $rules->actionCharacteristicModifier(
                $slice,
                'strike-card',
                'strike',
                1,
                ['damageTypeCode' => 'slashing', 'occupyHands' => 1],
                ['double-power' => 1],
            ),
        );
    }

    /**
     * Собирает правила с одной карточкой действия.
     *
     * @param int|null $wideAdvantage Дельта помехи.
     *
     * @return GameStrikeRules Правила.
     */
    private function rules(?int $wideAdvantage): GameStrikeRules
    {
        return $this->rulesFor(new CharacterRuleSlice(1, 1, 'world', [$this->card($wideAdvantage)], []));
    }

    /**
     * Собирает правила поверх готового среза.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return GameStrikeRules Правила.
     */
    private function rulesFor(CharacterRuleSlice $slice): GameStrikeRules
    {
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($slice);
        $characters = $this->createMock(ICharacters::class);
        $mechanics = $this->createMock(IMechanics::class);
        $sheets = $this->createMock(ICharacterSheets::class);
        $checkRoll = new GameCheckRoll(
            $slices,
            $characters,
            $mechanics,
            $this->createMock(IMechanicRolls::class),
            $sheets,
        );
        $layers = new GameStrikeLayers(
            $this->createMock(ICharacterCombatLayers::class),
            $this->createMock(IMechanicEngine::class),
            $mechanics,
            new GameStrikeAmounts(),
        );

        return new GameStrikeRules(
            $slices,
            $characters,
            $this->createMock(ICharacterFormulaContexts::class),
            $this->createMock(IFormulaEvaluations::class),
            $checkRoll,
            $layers,
            $sheets,
        );
    }

    /**
     * Карточка широкого удара.
     *
     * @param int|null $wideAdvantage Дельта помехи.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function card(?int $wideAdvantage): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                'wide-card',
                true,
                'ability',
                'Широкий удар',
                '',
                [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                    'requirements' => [],
                    'grants' => [],
                    'attack_mode' => 'wide',
                    'max_targets' => 3,
                    'wide_target_check_advantage' => $wideAdvantage,
                ],
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
    }

    /**
     * Срез с карточкой-фильтром, предметом и профилем урона.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function filterSlice(): CharacterRuleSlice
    {
        $card = new RuleVersionRecord(
            1,
            1,
            'strike-card',
            true,
            'ability',
            'Удар',
            '',
            [
                'type' => 'action',
                'action_effects' => [
                    [
                        'type' => 'current_action_attack_characteristic_modifier',
                        'delta' => 2,
                        'scope' => ['components' => ['strike'], 'hit_count' => 'all'],
                    ],
                    [
                        'type' => 'current_action_attack_characteristic_modifier',
                        'delta' => 3,
                        'scope' => ['components' => ['strike'], 'hit_count' => 'all'],
                        'min_occupy_hands' => 2,
                    ],
                    [
                        'type' => 'current_action_attack_characteristic_modifier',
                        'delta' => 4,
                        'scope' => ['components' => ['strike'], 'hit_count' => 'all'],
                        'damage_type_codes' => ['slashing'],
                    ],
                ],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
        $item = new RuleVersionRecord(
            2,
            2,
            'sword',
            true,
            'item',
            'Sword',
            '',
            [
                'category' => 'weapon',
                'occupy_hands' => ['min' => 1, 'max' => 2],
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'distance' => [
                            'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                            'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
                        ],
                        'damage' => ['damage_type_code' => 'slashing'],
                    ]],
                ],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
        $bow = new RuleVersionRecord(
            3,
            3,
            'bow',
            true,
            'item',
            'Bow',
            '',
            [
                'category' => 'weapon',
                'occupy_hands' => ['min' => 1, 'max' => 1],
                'weapon' => [
                    'weapon_profiles' => [
                        [
                            'type' => 'strike',
                            'distance' => [
                                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
                            ],
                            'damage' => ['damage_type_code' => 'blunt'],
                        ],
                        [
                            'type' => 'shoot',
                            'extra_hands' => 1,
                            'distance' => [
                                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
                                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 10]],
                            ],
                            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 3]],
                            'ammunition' => ['mode' => 'item', 'keyword_codes' => ['arrow']],
                            'damage' => ['damage_type_code' => 'piercing'],
                        ],
                    ],
                ],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );

        $child = new RuleVersionRecord(
            4,
            4,
            'double-power',
            true,
            'ability',
            'Double power',
            '',
            [
                'type' => 'skill',
                'parent_ability_code' => 'strike-card',
                'action_effects' => [[
                    'type' => 'current_action_attack_characteristic_modifier',
                    'delta' => 1,
                    'scope' => ['components' => ['strike'], 'hit_count' => 'all'],
                    'min_occupy_hands' => 2,
                    'damage_type_codes' => ['slashing'],
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
            'world',
            [
                new CharacterResolvedRule($card, []),
                new CharacterResolvedRule($item, []),
                new CharacterResolvedRule($bow, []),
                new CharacterResolvedRule($child, []),
            ],
            [],
        );
    }
}
