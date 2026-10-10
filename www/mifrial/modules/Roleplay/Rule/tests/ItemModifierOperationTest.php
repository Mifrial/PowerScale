<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ArmorBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\BlockProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DefenseSlot;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ShieldBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponDamage;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperations;
use Mifrial\Roleplay\Rule\Spec\ItemModifiers;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Срез 1: operations меняют spec, абзацы effects — нет.
 */
final class ItemModifierOperationTest extends TestCase
{
    /**
     * Один множитель веса применяется один раз при обоих признаках.
     *
     * @return void
     */
    public function testWeightFactorAppliesOnce(): void
    {
        $item = $this->item(true, true, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'weight', 'factor' => 1.25, 'source_code' => 'mass'],
        ])], ['weapon', 'shield-item']);

        self::assertSame(10, $changed->getWeight()->getBase());
        self::assertSame(8, $item->getWeight()->getBase());
    }

    /**
     * Две прибавки одной силы с одним источником оставляют сильнейшую.
     *
     * @return void
     */
    public function testSameSourceKeepsStrongerDelta(): void
    {
        $item = $this->item(true, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'min_strength', 'delta' => 2, 'when' => ['keyword_all' => ['weapon']], 'source_code' => 'mass'],
            ['type' => 'min_strength', 'delta' => 1, 'when' => ['keyword_all' => ['weapon']], 'source_code' => 'mass'],
        ])], ['weapon']);

        self::assertSame(5, $changed->getWeapon()->getMinStrength()->getBase());
    }

    /**
     * Множители одного источника: сильнейший бонус и сильнейший штраф.
     *
     * @return void
     */
    public function testSameSourceMultipliesStrongestFactorPair(): void
    {
        $item = $this->item(true, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'weight', 'factor' => 0.2, 'source_code' => 'mass'],
            ['type' => 'weight', 'factor' => 0.5, 'source_code' => 'mass'],
            ['type' => 'weight', 'factor' => 2, 'source_code' => 'mass'],
        ])], ['weapon']);

        self::assertSame(3, $changed->getWeight()->getBase());
    }

    /**
     * Пустые источники не схлопываются.
     *
     * @return void
     */
    public function testMissingSourceStaysUnique(): void
    {
        $item = $this->item(true, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'min_strength', 'delta' => 2, 'when' => ['keyword_all' => ['weapon']]],
            ['type' => 'min_strength', 'delta' => 1, 'when' => ['keyword_all' => ['weapon']]],
        ])], ['weapon']);

        self::assertSame(1, $changed->getWeapon()->getMinStrength()->getSize());
        self::assertSame(3, $changed->getWeapon()->getMinStrength()->getBase());
    }

    /**
     * Minimum resource operation stays outside item numeric collapse.
     *
     * @return void
     */
    public function testMinResourceCostDoesNotChangeItemSpec(): void
    {
        $item = $this->item(true, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            [
                'type' => 'min_resource_cost',
                'resource_code' => 'action-points',
                'minimum' => 2,
            ],
        ])], ['weapon']);

        self::assertSame($item, $changed);
    }

    /**
     * Блок оружия не меняется без признака weapon.
     *
     * @return void
     */
    public function testWeaponBlockRequiresKeyword(): void
    {
        $item = $this->item(true, true, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'block', 'factor' => 2, 'when' => ['keyword_all' => ['weapon']], 'source_code' => 'mass'],
        ])], ['shield-item']);

        self::assertSame(4, $changed->getBlockProfile()->getDefense()->getBase());
    }

    /**
     * Признак щита меняет профиль блока предмета.
     *
     * @return void
     */
    public function testShieldKeywordChangesShieldBlock(): void
    {
        $item = $this->item(true, true, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'block', 'factor' => 2, 'when' => ['keyword_all' => ['shield-item']], 'source_code' => 'mass'],
        ])], ['shield-item']);

        self::assertSame(8, $changed->getBlockProfile()->getDefense()->getBase());
    }

    /**
     * Нет профиля блока — операция его не создаёт.
     *
     * @return void
     */
    public function testMissingBlockProfileIsNotCreated(): void
    {
        $item = $this->item(false, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'block', 'factor' => 2, 'when' => ['keyword_all' => ['weapon']], 'source_code' => 'mass'],
        ])], ['weapon']);

        self::assertNull($changed->getBlockProfile());
    }

    /**
     * Нет блока доспеха — слот защиты не создаётся.
     *
     * @return void
     */
    public function testMissingArmorIsNotCreated(): void
    {
        $item = $this->item(false, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            ['type' => 'defense', 'factor' => 2, 'when' => ['keyword_all' => ['armor-item']], 'source_code' => 'mass'],
        ])], ['armor-item']);

        self::assertNull($changed->getArmor());
    }

    /**
     * Абзац effects без operations числа не применяет.
     *
     * @return void
     */
    public function testEffectLabelDoesNotChangeNumbers(): void
    {
        $item = $this->item(true, false, false);
        $modifier = ItemModifiers::read([
            'type_code' => 'item-weight',
            'effects' => [[
                'label' => 'Оружие',
                'text' => 'Вес увеличен.',
                'ops' => [['type' => 'weight', 'factor' => 2]],
            ]],
        ]);
        $changed = ItemModifierOperations::apply($item, [$modifier], ['weapon']);

        self::assertSame($item, $changed);
        self::assertSame([], $modifier->getOperations());
    }

    /**
     * Прибавка урона несёт source операции и не трогает чужой профиль.
     *
     * @return void
     */
    public function testActionStrengthKeepsOperationSource(): void
    {
        $item = $this->item(true, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            [
                'type' => 'action_strength',
                'field' => 'damage',
                'delta' => 1,
                'profiles' => ['strike'],
                'when' => ['keyword_all' => ['weapon']],
                'source_code' => 'mass',
            ],
        ])], ['weapon']);
        $strike = $changed->getWeapon()->getWeaponProfiles()[0]->getDamage()->getFormula();
        $shoot = $changed->getWeapon()->getWeaponProfiles()[1]->getDamage()->getFormula();

        self::assertInstanceOf(ActionCharacteristicNode::class, $strike);
        self::assertSame('mass', $strike->getModifiers()[0]->getSourceCode());
        self::assertSame(1, $strike->getModifiers()[0]->getDelta());
        self::assertInstanceOf(FixedNode::class, $shoot);
    }

    /**
     * Поле penetration пишет пробитие и не пишет урон.
     *
     * @return void
     */
    public function testActionStrengthPenetrationDoesNotChangeDamage(): void
    {
        $item = $this->item(true, false, false);
        $changed = ItemModifierOperations::apply($item, [$this->modifier([
            [
                'type' => 'action_strength',
                'field' => 'penetration',
                'delta' => 2,
                'profiles' => ['strike'],
                'when' => ['keyword_all' => ['weapon']],
                'source_code' => 'mass',
            ],
        ])], ['weapon']);
        $profile = $changed->getWeapon()->getWeaponProfiles()[0];
        $damage = $profile->getDamage()->getFormula();
        $penetration = $profile->getPenetration();

        self::assertInstanceOf(ActionCharacteristicNode::class, $damage);
        self::assertSame([], $damage->getModifiers());
        self::assertInstanceOf(ActionCharacteristicNode::class, $penetration);
        self::assertSame('mass', $penetration->getModifiers()[0]->getSourceCode());
        self::assertSame(2, $penetration->getModifiers()[0]->getDelta());
    }

    /**
     * Предмет с оружием, щитом и доспехом.
     *
     * @param bool $weapon Блок оружия.
     * @param bool $shield Блок щита.
     * @param bool $armor Блок доспеха.
     *
     * @return ItemSpec Предмет.
     */
    private function item(bool $weapon, bool $shield, bool $armor): ItemSpec
    {
        return new ItemSpec(
            'equipment',
            null,
            false,
            new DimensionalNumber(8, 0),
            [],
            null,
            null,
            null,
            [],
            [],
            null,
            $weapon ? $this->weapon() : null,
            $armor ? new ArmorBlock(null, null, [], [new DefenseSlot(new DimensionalNumber(4, 0), 1, null)], []) : null,
            $shield ? new ShieldBlock(new DimensionalNumber(3, 0), null, [], []) : null,
            $weapon || $shield ? $this->block() : null,
        );
    }

    /**
     * Оружие с силой, блоком и двумя профилями.
     *
     * @return WeaponBlock Блок.
     */
    private function weapon(): WeaponBlock
    {
        $distance = new FixedNode(0);

        return new WeaponBlock(
            new DimensionalNumber(3, 0),
            [
                new WeaponProfile(
                    'strike',
                    $distance,
                    null,
                    new WeaponDamage(new ActionCharacteristicNode('strike', 'strength', null, []), null),
                    new ActionCharacteristicNode('strike', 'strength', null, []),
                    new DimensionalNumber(3, 0),
                    [],
                    null,
                    null,
                ),
                new WeaponProfile(
                    'shoot',
                    $distance,
                    null,
                    new WeaponDamage(new FixedNode(1), null),
                    $distance,
                    new DimensionalNumber(3, 0),
                    [],
                    null,
                    null,
                ),
            ],
            null,
            null,
        );
    }

    /**
     * Профиль блока с защитой 4.
     *
     * @return BlockProfile Профиль.
     */
    private function block(): BlockProfile
    {
        return new BlockProfile(new DimensionalNumber(3, 0), new DimensionalNumber(4, 0), []);
    }

    /**
     * Модификатор из списка operations.
     *
     * @param array<int, array<string, mixed>> $operations Операции.
     *
     * @return \Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec Spec.
     */
    private function modifier(array $operations): \Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec
    {
        return ItemModifiers::read([
            'type_code' => 'item-weight',
            'operations' => $operations,
        ]);
    }
}
