<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameActionModifierPresenter;
use Mifrial\Roleplay\Game\Service\GameActionModifiers;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use PHPUnit\Framework\TestCase;

/**
 * Модификаторы действия берутся из купленных детей карточки, а не из клиента.
 */
final class GameActionModifiersTest extends TestCase
{
    /**
     * Купленный ребёнок карточки даёт модификатор с серверной подписью.
     *
     * @return void
     */
    public function testAvailableListsOnlyOwnedChildrenOfCard(): void
    {
        $slice = $this->slice();
        $rows = (new GameActionModifiers())->available($slice, ['rush' => 1]);

        self::assertCount(1, $rows);
        self::assertSame('card', $rows[0]['actionCode']);
        self::assertSame('rush', $rows[0]['code']);
        self::assertSame('Яростный рывок', $rows[0]['name']);
        self::assertInstanceOf(ActionModifierEffect::class, $rows[0]['effect']);

        $summary = (new GameActionModifierPresenter())->summary($slice, $rows[0]['effect']);
        self::assertStringContainsString('Проверка на Силу воли против 3', $summary);
        self::assertStringContainsString('внутренний дробящий урон по себе ([сила удара]↓1)', $summary);
        self::assertStringContainsString('помеха действия снимается', $summary);
    }

    /**
     * Некулепленный ребёнок модификатором не становится.
     *
     * @return void
     */
    public function testAvailableSkipsUnownedChild(): void
    {
        self::assertSame([], (new GameActionModifiers())->available($this->slice(), ['rush' => 0]));
        self::assertSame([], (new GameActionModifiers())->available($this->slice(), []));
    }

    /**
     * Код ищется только у своей карточки.
     *
     * @return void
     */
    public function testFindRejectsForeignCard(): void
    {
        $modifiers = new GameActionModifiers();
        $found = $modifiers->find($this->slice(), 'card', ['rush' => 1], 'rush');
        self::assertSame('rush', $found['code']);
        self::assertInstanceOf(ActionModifierEffect::class, $found['effect']);

        $this->expectException(GameInvalidException::class);
        $modifiers->find($this->slice(), 'other-card', ['rush' => 1], 'rush');
    }

    /**
     * Модификатор применяется только к своей области.
     *
     * @return void
     */
    public function testScopeDecidesApplicability(): void
    {
        $rows = (new GameActionModifiers())->available($this->slice(), ['rush' => 1]);
        $effect = $rows[0]['effect'];
        self::assertInstanceOf(ActionModifierEffect::class, $effect);
        self::assertTrue($effect->appliesTo('strike', 1));
        self::assertFalse($effect->appliesTo('shoot', 1));
        self::assertFalse($effect->appliesTo('strike', 2));
    }

    /**
     * Срез с карточкой, ребёнком, проверкой и типом урона.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('card', 'ability', 'Размашистый удар', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
            ]),
            $this->rule('rush', 'ability', 'Яростный рывок', [
                'type' => 'skill',
                'zones' => ['or' => ['kind' => 'automatic']],
                'parent_ability_code' => 'card',
                'action_effects' => [[
                    'type' => 'action_modifier',
                    'trigger' => 'after_strike',
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                    'check' => ['code' => 'will-check', 'difficulty' => 3],
                    'effects' => [
                        [
                            'type' => 'self_damage',
                            'size_delta' => -1,
                            'damage_type_code' => 'blunt',
                            'ignore_defense' => true,
                        ],
                        ['type' => 'skip_after_action'],
                    ],
                ]],
            ]),
            $this->rule('will-check', 'check', 'Проверка на Силу воли', [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => false,
                'concentration_token' => false,
                'willpower' => true,
                'unstable_check' => false,
                'hit_check' => false,
                'initiative' => false,
                'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
            ]),
            $this->rule('blunt', 'damage_type', 'Дробящий', [
                'forms' => ['genitive' => 'дробящего урона', 'dative' => 'дробящему урону'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ]),
        ], []);
    }

    /**
     * Запись правила.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param string $name Имя.
     * @param array<string, mixed> $document Спека.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, string $name, array $document): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                $name,
                '',
                $document,
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
    }
}
