<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellCharge;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellCharge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет операции заряда на источнике.
 */
final class GameSpellChargeTest extends TestCase
{
    /**
     * Выдача заменяет прежний заряд и не плодит строки.
     *
     * @return void
     */
    public function testGrantReplacesBoundRows(): void
    {
        $charge = $this->charge();
        $sheet = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
            ['id' => 4, 'stateRuleCode' => 'electrocharge', 'value' => 2, 'boundSourceKey' => 'characteristic:magic-power'],
            ['id' => 5, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'inventory:9'],
        ]];

        self::assertSame(
            [
                ['kind' => 'patchState', 'id' => 3, 'value' => 1],
                ['kind' => 'removeState', 'id' => 4],
            ],
            (new GameSpellCharge())->grant($sheet, $charge, 'characteristic:magic-power', 3, true),
        );
        self::assertSame(
            1,
            (new GameSpellCharge())->value($sheet, 'electrocharge', 'inventory:9'),
        );
    }

    /**
     * Пустой лист получает новую строку с привязкой к источнику.
     *
     * @return void
     */
    public function testGrantAddsRowToEmptySheet(): void
    {
        self::assertSame(
            [[
                'kind' => 'putState',
                'stateRuleCode' => 'electrocharge',
                'value' => 1,
                'boundSourceKey' => 'characteristic:magic-power',
            ]],
            (new GameSpellCharge())->grant([], $this->charge(), 'characteristic:magic-power', 2, true),
        );
    }

    /**
     * Обновление поддержания прибавляет заряд до капа.
     *
     * @return void
     */
    public function testGrantAddsUpToCap(): void
    {
        $sheet = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
        ]];

        self::assertSame(
            [['kind' => 'patchState', 'id' => 3, 'value' => 2]],
            (new GameSpellCharge())->grant($sheet, $this->charge(), 'characteristic:magic-power', 2, false),
        );
        self::assertSame(
            [],
            (new GameSpellCharge())->grant($sheet, $this->charge(), 'characteristic:magic-power', 1, false),
        );
    }

    /**
     * Трата уменьшает заряд, а ноль снимает строку.
     *
     * @return void
     */
    public function testSpendDecrementsAndRemovesZero(): void
    {
        $service = new GameSpellCharge();
        $two = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 2, 'boundSourceKey' => 'characteristic:magic-power'],
        ]];
        $one = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
        ]];

        self::assertSame(
            [['kind' => 'patchState', 'id' => 3, 'value' => 1]],
            $service->spend($two, $this->charge(), 'characteristic:magic-power'),
        );
        self::assertSame(
            [['kind' => 'removeState', 'id' => 3]],
            $service->spend($one, $this->charge(), 'characteristic:magic-power'),
        );
    }

    /**
     * Пустой заряд не тратится.
     *
     * @return void
     */
    public function testSpendRejectsEmptyCharge(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameSpellCharge())->spend([], $this->charge(), 'characteristic:magic-power');
    }

    /**
     * Снятие убирает только строки этого источника.
     *
     * @return void
     */
    public function testRemoveOnlyBoundRows(): void
    {
        $sheet = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
            ['id' => 5, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'inventory:9'],
        ]];

        self::assertSame(
            [['kind' => 'removeState', 'id' => 3]],
            (new GameSpellCharge())->remove($sheet, 'electrocharge', 'characteristic:magic-power'),
        );
    }

    /**
     * Карточка заряда контента.
     *
     * @return SpellCharge Заряд.
     */
    private function charge(): SpellCharge
    {
        return new SpellCharge('electrocharge', 1, 1, 2, 1, 'electromancy', ['sustained'], 'turn_ap');
    }

    /**
     * Без выученного улучшения потолок — `default_cap`.
     *
     * @return void
     */
    public function testCapWithoutUpgradeIsDefault(): void
    {
        self::assertSame(1, (new GameSpellCharge())->cap(
            new CharacterRuleSlice(1, 1, 'world', [], []),
            $this->choices([]),
            [],
            'lightning-generator',
            $this->charge(),
        ));
    }

    /**
     * Отрицательный `default_cap` не даёт отрицательного потолка.
     *
     * @return void
     */
    public function testCapNeverNegative(): void
    {
        $charge = new SpellCharge('electrocharge', 1, -2, 2, 1, 'electromancy', ['sustained'], 'turn_ap');

        self::assertSame(0, (new GameSpellCharge())->cap(
            new CharacterRuleSlice(1, 1, 'world', [], []),
            $this->choices([]),
            [],
            'lightning-generator',
            $charge,
        ));
    }

    /**
     * Потолок растёт по опыту признака: база, ступень `cap`, затем `per_experience`.
     *
     * @param int $experience Опыт электромансии.
     * @param int $expected Ожидаемый потолок.
     *
     * @return void
     */
    #[DataProvider('experienceProvider')]
    public function testCapCountsExperienceSteps(int $experience, int $expected): void
    {
        self::assertSame($expected, $this->capOf($experience));
    }

    /**
     * Ступени опыта электромансии.
     *
     * @return iterable<string, array{int, int}> Наборы.
     */
    public static function experienceProvider(): iterable
    {
        yield 'no experience' => [0, 2];
        yield 'below first step' => [19, 2];
        yield 'first step' => [20, 3];
        yield 'before second step' => [39, 3];
        yield 'second step' => [40, 4];
        yield 'fraction of second step' => [45, 4];
        yield 'third ten' => [55, 5];
    }

    /**
     * Улучшение чужого заклинания не поднимает потолок.
     *
     * @return void
     */
    public function testCapIgnoresUpgradeOfOtherSpell(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->capRule('other-spell'),
            $this->experienceRule(),
        ], []);
        $abilities = [
            $this->ability('charge-accumulation', 1),
            $this->ability('electromancy-xp', 40),
        ];

        self::assertSame(1, (new GameSpellCharge())->cap(
            $slice,
            $this->choices($abilities),
            ['charge-accumulation' => 1, 'electromancy-xp' => 40],
            'lightning-generator',
            $this->charge(),
        ));
    }

    /**
     * Карточка нулевого уровня не поднимает потолок.
     *
     * @return void
     */
    public function testCapIgnoresZeroLevelUpgrade(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->capRule('lightning-generator'),
            $this->experienceRule(),
        ], []);
        $abilities = [
            $this->ability('charge-accumulation', 0),
            $this->ability('electromancy-xp', 40),
        ];

        self::assertSame(1, (new GameSpellCharge())->cap(
            $slice,
            $this->choices($abilities),
            ['charge-accumulation' => 0, 'electromancy-xp' => 40],
            'lightning-generator',
            $this->charge(),
        ));
    }

    /**
     * Потолок по изученному улучшению генератора.
     *
     * @param int $experience Опыт электромансии.
     *
     * @return int Потолок.
     */
    private function capOf(int $experience): int
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->capRule('lightning-generator'),
            $this->experienceRule(),
        ], []);
        $abilities = [
            $this->ability('charge-accumulation', 1),
            $this->ability('electromancy-xp', $experience),
        ];

        return (new GameSpellCharge())->cap(
            $slice,
            $this->choices($abilities),
            ['charge-accumulation' => 1, 'electromancy-xp' => $experience],
            'lightning-generator',
            $this->charge(),
        );
    }

    /**
     * Выборы кастера с указанными способностями.
     *
     * @param array<int, CharacterAbilityChoice> $abilities Способности.
     *
     * @return CharacterChoices Выборы.
     */
    private function choices(array $abilities): CharacterChoices
    {
        return new CharacterChoices('Caster', null, $abilities, [], [], [], null);
    }

    /**
     * Выбор способности.
     *
     * @param string $code Код.
     * @param int $level Уровень.
     *
     * @return CharacterAbilityChoice Выбор.
     */
    private function ability(string $code, int $level): CharacterAbilityChoice
    {
        return new CharacterAbilityChoice($code, $level, 'arcanist', 'arcanist', null, null, null);
    }

    /**
     * Карточка «Накопления зарядов».
     *
     * @param string $parent Заклинание улучшения.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function capRule(string $parent): CharacterResolvedRule
    {
        return $this->rule('charge-accumulation', [
            'type' => 'skill',
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
            'grants' => [],
            'requirements' => [],
            'multiple' => true,
            'parent_ability_code' => $parent,
            'spell_upgrade' => [
                'action_point_delta' => 0,
                'charge_cap' => [
                    'base' => 2,
                    'steps' => [
                        ['cap' => 3, 'min_experience' => 20],
                        ['min_experience' => 40, 'per_experience' => 10],
                    ],
                    'experience_keyword_code' => 'electromancy',
                ],
            ],
        ]);
    }

    /**
     * Карточка электромансии: цена уровня равна опыту.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function experienceRule(): CharacterResolvedRule
    {
        return $this->rule('electromancy-xp', [
            'type' => 'skill',
            'zones' => ['or' => ['kind' => 'progression', 'max_level' => 60, 'base_cost' => 1, 'step' => 0]],
            'grants' => [],
            'requirements' => [],
        ], ['electromancy']);
    }

    /**
     * Карточка среза.
     *
     * @param string $code Код.
     * @param array<string, mixed> $spec Спека.
     * @param array<int, string> $keywords Признаки.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function rule(string $code, array $spec, array $keywords = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                'ability',
                'Name',
                '',
                $spec,
                [],
                [],
                'ready',
                '',
                DateTime::now(),
            ),
            $keywords,
        );
    }
}
