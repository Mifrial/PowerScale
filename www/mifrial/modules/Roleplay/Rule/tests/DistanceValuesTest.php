<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DistanceValue;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\ItemWeapons;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use PHPUnit\Framework\TestCase;

/**
 * Новый формат дистанции профиля и отказ старому.
 */
final class DistanceValuesTest extends TestCase
{
    /**
     * min/max и falloff читаются как DistanceValue.
     *
     * @return void
     */
    public function testReadsDistanceRangeAndFalloff(): void
    {
        $block = ItemWeapons::block($this->weapon([
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
            ],
            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
        ]));

        self::assertNotNull($block);
        $profile = $block->getWeaponProfiles()[0];
        $min = $profile->getDistance()->getMin();
        $max = $profile->getDistance()->getMax();
        $falloff = $profile->getFalloff();

        self::assertSame(DistanceValue::TYPE_IPARI, $min->getType());
        self::assertInstanceOf(FixedNode::class, $min->getValue());
        self::assertSame(1, $min->getValue()->getValue());
        self::assertNotNull($max);
        self::assertSame(5, $max->getValue()->getValue());
        self::assertNotNull($falloff);
        self::assertSame(5, $falloff->getValue()->getValue());
    }

    /**
     * У броска max может быть null.
     *
     * @return void
     */
    public function testThrowAllowsNullMax(): void
    {
        $block = ItemWeapons::block($this->weapon([
            'type' => 'throw',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 2]],
                'max' => null,
            ],
            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 3]],
        ]));

        self::assertNotNull($block);
        self::assertNull($block->getWeaponProfiles()[0]->getDistance()->getMax());
    }

    /**
     * Старое поле distance без min/max отвергается, fallback запрещён.
     *
     * @return void
     */
    public function testRejectsOldDistanceShape(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block($this->weapon([
            'distance' => ['type' => 'fixed', 'value' => 1],
            'falloff' => ['base' => 5, 'size' => 0],
        ]));
    }

    /**
     * min_strength читается как CharacteristicNumber.
     *
     * @return void
     */
    public function testReadsMinStrengthAsCharacteristicNumber(): void
    {
        $block = ItemWeapons::block([
            'weapon' => [
                'min_strength' => ['base' => 4, 'size' => 0],
                'weapon_profiles' => [[
                    'type' => 'throw',
                    'distance' => [
                        'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 2]],
                        'max' => null,
                    ],
                    'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 3]],
                    'accuracy' => ['base' => 3, 'size' => 0],
                ]],
            ],
        ]);

        self::assertNotNull($block);
        self::assertInstanceOf(CharacteristicNumber::class, $block->getMinStrength());
        self::assertSame(4, $block->getMinStrength()->getBase());
    }

    /**
     * У броска не может быть максимума, у выстрела — может.
     *
     * @return void
     */
    public function testRejectsThrowMax(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block($this->weapon([
            'type' => 'throw',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 2]],
            ],
            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 3]],
        ]));
    }

    /**
     * Выстрел без дальнобойности отвергается, fallback запрещён.
     *
     * @return void
     */
    public function testRejectsShootWithoutFalloff(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block($this->weapon([
            'type' => 'shoot',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 10]],
            ],
        ]));
    }

    /**
     * Удар не имеет дальнобойности.
     *
     * @return void
     */
    public function testRejectsStrikeFalloff(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block($this->weapon([
            'type' => 'strike',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
            ],
            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 3]],
        ]));
    }

    /**
     * База минимальной силы вне 3–5 отвергается.
     *
     * @return void
     */
    public function testRejectsMinStrengthOutsideCharacteristicScale(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block([
            'weapon' => [
                'min_strength' => ['base' => 6, 'size' => 0],
                'weapon_profiles' => [],
            ],
        ]);
    }

    /**
     * Неизвестный тип профиля отвергается.
     *
     * @return void
     */
    public function testRejectsUnknownProfileType(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block($this->weapon([
            'type' => 'cast',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
            ],
            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
        ]));
    }

    /**
     * Неизвестный тип дистанции отвергается.
     *
     * @return void
     */
    public function testRejectsUnknownDistanceType(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemWeapons::block($this->weapon([
            'distance' => [
                'min' => ['type' => 'meter', 'value' => ['type' => 'fixed', 'value' => 1]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 1]],
            ],
        ]));
    }

    /**
     * Документ с одним профилем.
     *
     * @param array<string, mixed> $profile Поля профиля.
     *
     * @return array<string, mixed> Документ.
     */
    private function weapon(array $profile): array
    {
        return [
            'weapon' => [
                'weapon_profiles' => [array_merge([
                    'type' => 'shoot',
                    'accuracy' => ['base' => 3, 'size' => 0],
                ], $profile)],
            ],
        ];
    }
}
