<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\CharacterHands;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithComponents;
use PHPUnit\Framework\TestCase;

/**
 * Руки персонажа: занятость надетого, зажим строки и требование действия.
 */
final class CharacterHandsTest extends TestCase
{
    /**
     * Без надетого свободны обе руки.
     *
     * @return void
     */
    public function testFreeHandsWithoutEquipment(): void
    {
        $hands = new CharacterHands($this->slice([]), []);

        self::assertSame(2, $hands->getTotalHands());
        self::assertSame(2, $hands->getFreeHands());
        self::assertSame(0, $hands->getOccupiedHands());
    }

    /**
     * Строка занимает выбранное число рук; значение зажимается в диапазон.
     *
     * @return void
     */
    public function testEquippedItemUsesRowOccupancyAndClamps(): void
    {
        $slice = $this->slice([$this->item('staff', ['occupy_hands' => ['min' => 1, 'max' => 2]])]);
        $choices = ['inventory' => [
            ['id' => 1, 'ruleCode' => 'staff', 'equipped' => true, 'occupyHands' => 5],
        ]];

        $hands = new CharacterHands($slice, $choices);

        self::assertSame(2, $hands->getItemOccupancy('staff', 5));
        self::assertSame(2, $hands->getOccupiedHands());
        self::assertSame(0, $hands->getFreeHands());
        self::assertSame(1, $hands->getItemOccupancy('staff', 0));
        self::assertSame(1, $hands->getItemOccupancy('staff'));
    }

    /**
     * Щит без occupy_hands занимает одну руку.
     *
     * @return void
     */
    public function testShieldWithoutOccupyHandsOccupiesOne(): void
    {
        $slice = $this->slice([$this->item('buckler', ['shield' => []])]);
        $choices = ['inventory' => [
            ['id' => 1, 'ruleCode' => 'buckler', 'equipped' => true],
        ]];

        $hands = new CharacterHands($slice, $choices);

        self::assertSame(1, $hands->getItemOccupancy('buckler'));
        self::assertSame(1, $hands->getFreeHands());
    }

    /**
     * Врождённый предмет не занимает рук.
     *
     * @return void
     */
    public function testInnateItemDoesNotOccupy(): void
    {
        $slice = $this->slice([
            $this->item('claws', ['innate' => true, 'occupy_hands' => ['min' => 1, 'max' => 1]]),
        ]);
        $choices = ['inventory' => [
            ['id' => 1, 'ruleCode' => 'claws', 'equipped' => true],
        ]];

        $hands = new CharacterHands($slice, $choices);

        self::assertSame(0, $hands->getItemOccupancy('claws'));
        self::assertSame(2, $hands->getFreeHands());
    }

    /**
     * Ненадетый предмет не занимает рук.
     *
     * @return void
     */
    public function testUnequippedItemDoesNotOccupy(): void
    {
        $slice = $this->slice([$this->item('sword', ['occupy_hands' => ['min' => 1, 'max' => 1]])]);
        $choices = ['inventory' => [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => false, 'occupyHands' => 1],
        ]];

        $hands = new CharacterHands($slice, $choices);

        self::assertSame(2, $hands->getFreeHands());
    }

    /**
     * Внешние занятые руки уменьшают свободные и не уводят их ниже нуля.
     *
     * @return void
     */
    public function testExtraOccupiedReducesFreeHands(): void
    {
        $slice = $this->slice([]);

        self::assertSame(1, (new CharacterHands($slice, [], 1))->getFreeHands());
        self::assertSame(0, (new CharacterHands($slice, [], 5))->getFreeHands());
    }

    /**
     * Требование действия — сумма соматических компонентов.
     *
     * @return void
     */
    public function testRequiredHandsSumsSomaticComponents(): void
    {
        $slice = $this->slice([
            $this->ability('gesture', [
                'type' => 'action',
                'action_components' => [
                    ['type' => 'somatic', 'note' => 'указать', 'occupy_hands' => 1],
                    ['type' => 'somatic', 'note' => 'второй жест', 'occupy_hands' => 1],
                ],
            ]),
            $this->ability('touch', [
                'type' => 'action',
                'action_components' => [
                    ['type' => 'somatic', 'note' => 'касание'],
                ],
            ]),
        ]);

        self::assertSame(2, CharacterHands::getRequiredHandsOf($this->components($slice, 'gesture')));
        self::assertSame(0, CharacterHands::getRequiredHandsOf($this->components($slice, 'touch')));
    }

    /**
     * Ресурсный компонент не требует рук.
     *
     * @return void
     */
    public function testRequiredHandsIgnoresResourceComponents(): void
    {
        $slice = $this->slice([
            $this->ability('cast', [
                'type' => 'action',
                'action_components' => [
                    ['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 4],
                ],
            ]),
        ]);

        self::assertSame(0, CharacterHands::getRequiredHandsOf($this->components($slice, 'cast')));
    }

    /**
     * canEquip учитывает свободные руки и исключённую строку.
     *
     * @return void
     */
    public function testCanEquipRespectsFreeHandsAndExcludedRow(): void
    {
        $slice = $this->slice([
            $this->item('knife', ['occupy_hands' => ['min' => 1, 'max' => 1]]),
            $this->item('great', ['occupy_hands' => ['min' => 2, 'max' => 2]]),
        ]);
        $choices = ['inventory' => [
            ['id' => 1, 'ruleCode' => 'knife', 'equipped' => true, 'occupyHands' => 1],
            ['id' => 2, 'ruleCode' => 'knife', 'equipped' => true, 'occupyHands' => 1],
        ]];
        $hands = new CharacterHands($slice, $choices);

        self::assertFalse($hands->canEquip('knife'));
        self::assertTrue($hands->canEquip('knife', null, 1));
        self::assertFalse($hands->canEquip('great', null, 1));
    }

    /**
     * Неизвестный код предмета не занимает рук.
     *
     * @return void
     */
    public function testUnknownItemDoesNotOccupy(): void
    {
        $hands = new CharacterHands($this->slice([]), []);

        self::assertSame(0, $hands->getItemOccupancy('missing'));
    }

    /**
     * @param array<int, CharacterResolvedRule> $live Правила.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $live): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', $live, []);
    }

    /**
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return AbilityWithComponents Компоненты способности.
     */
    private function components(CharacterRuleSlice $slice, string $code): AbilityWithComponents
    {
        $spec = $slice->findLive($code)?->getSpec();
        self::assertInstanceOf(AbilityWithComponents::class, $spec);

        return $spec;
    }

    /**
     * @param string $code Код.
     * @param array<string, mixed> $spec Spec.
     *
     * @return CharacterResolvedRule Предмет.
     */
    private function item(string $code, array $spec): CharacterResolvedRule
    {
        return $this->rule($code, 'item', array_merge([
            'category' => 'equipment',
            'innate' => false,
            'special_rule_codes' => [],
        ], $spec));
    }

    /**
     * @param string $code Код.
     * @param array<string, mixed> $spec Spec.
     *
     * @return CharacterResolvedRule Способность.
     */
    private function ability(string $code, array $spec): CharacterResolvedRule
    {
        return $this->rule($code, 'ability', $spec);
    }

    /**
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Документ.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                'Name',
                '',
                $spec,
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
