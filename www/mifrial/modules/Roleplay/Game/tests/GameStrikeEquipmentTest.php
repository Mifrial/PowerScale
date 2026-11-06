<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeEquipment;
use Mifrial\Roleplay\Game\Service\GameStrikeSlices;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Снаряжение удара: руки, хват, выбранная строка и её предмет.
 */
final class GameStrikeEquipmentTest extends TestCase
{
    /**
     * Бонус удержания: 2–3 руки +2, 4+ +3, иначе ноль.
     *
     * @return void
     */
    public function testGripBonusFollowsHands(): void
    {
        $equipment = $this->equipment();

        self::assertSame(0, $equipment->gripBonus(0));
        self::assertSame(0, $equipment->gripBonus(1));
        self::assertSame(2, $equipment->gripBonus(2));
        self::assertSame(2, $equipment->gripBonus(3));
        self::assertSame(3, $equipment->gripBonus(4));
        self::assertSame(3, $equipment->gripBonus(6));
    }

    /**
     * Руки действия — удержание строки, зажатое границами, плюс extra_hands.
     *
     * @return void
     */
    public function testOccupyHandsAddsProfileExtraHands(): void
    {
        $equipment = $this->equipment();
        $choices = ['inventory' => [['id' => 7, 'ruleCode' => 'sword', 'equipped' => true]]];

        self::assertSame(2, $equipment->occupyHands(1, 1, $choices, 7, 'sword', 'strike', 0));

        $clamped = ['inventory' => [['id' => 7, 'ruleCode' => 'sword', 'equipped' => true, 'occupyHands' => 9]]];
        self::assertSame(3, $equipment->occupyHands(1, 1, $clamped, 7, 'sword', 'strike', 0));
    }

    /**
     * Врождённое оружие доступно в бою без equipped.
     *
     * @return void
     */
    public function testIsCarriedAcceptsInnateItem(): void
    {
        $equipment = $this->equipment();
        $item = $this->slices()->item(1, 1, 'claws');

        self::assertTrue($equipment->isCarried(['equipped' => false], $item));
        self::assertFalse($equipment->isCarried(['equipped' => false], $this->slices()->item(1, 1, 'sword')));
    }

    /**
     * Строка без equipped не становится выбранным предметом.
     *
     * @return void
     */
    public function testSelectedItemRejectsUnequippedRow(): void
    {
        $equipment = $this->equipment();
        $choices = ['inventory' => [['id' => 7, 'ruleCode' => 'sword', 'equipped' => false]]];

        $this->expectException(GameInvalidException::class);
        $equipment->selectedItem(1, 1, $choices, 7, 'sword');
    }

    /**
     * Дубли строк с одним id неоднозначны.
     *
     * @return void
     */
    public function testSelectedItemRejectsAmbiguousRows(): void
    {
        $equipment = $this->equipment();
        $choices = ['inventory' => [
            ['id' => 7, 'ruleCode' => 'sword', 'equipped' => true],
            ['id' => 7, 'ruleCode' => 'sword', 'equipped' => true],
        ]];

        $this->expectException(GameInvalidException::class);
        $equipment->selectedItem(1, 1, $choices, 7, 'sword');
    }

    /**
     * Собирает снаряжение поверх одного среза.
     *
     * @return GameStrikeEquipment Снаряжение.
     */
    private function equipment(): GameStrikeEquipment
    {
        return new GameStrikeEquipment($this->slices());
    }

    /**
     * Срезы поверх среза с предметами.
     *
     * @return GameStrikeSlices Срезы.
     */
    private function slices(): GameStrikeSlices
    {
        $source = $this->createMock(ICharacterRuleSlices::class);
        $source->method('get')->willReturn($this->slice());

        return new GameStrikeSlices($source);
    }

    /**
     * Срез с мечом и врождёнными когтями.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(
            1,
            1,
            'world',
            [
                $this->resolved($this->item(7, 'sword', ['min' => 1, 'max' => 2], false)),
                $this->resolved($this->item(8, 'claws', ['min' => 0, 'max' => 0], true)),
            ],
            [],
        );
    }

    /**
     * Карточка предмета.
     *
     * @param int $id Id.
     * @param string $code Код.
     * @param array{min: int, max: int} $hands Удержание.
     * @param bool $innate Врождённость.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function item(int $id, string $code, array $hands, bool $innate): RuleVersionRecord
    {
        return new RuleVersionRecord(
            $id,
            $id,
            $code,
            true,
            'item',
            $code,
            '',
            [
                'category' => 'weapon',
                'innate' => $innate,
                'occupy_hands' => $hands,
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'extra_hands' => 1,
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
    }

    /**
     * Оборачивает карточку в resolved rule.
     *
     * @param RuleVersionRecord $record Карточка.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function resolved(RuleVersionRecord $record): CharacterResolvedRule
    {
        return new CharacterResolvedRule($record, []);
    }
}
