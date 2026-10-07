<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResistanceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\BlockProfile;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\AbilityGrants;
use Mifrial\Roleplay\Rule\Spec\ItemSpecs;
use PHPUnit\Framework\TestCase;

/**
 * План 08: нет ключа durability — null, целое остаётся порогом.
 */
final class ItemSlotDurabilityTest extends TestCase
{
    /**
     * Целая прочность читается как раньше.
     *
     * @return void
     */
    public function testIntegerDurabilityStays(): void
    {
        $item = ItemSpecs::read($this->armor([
            'defense' => ['base' => 2, 'size' => 0],
            'durability' => 4,
        ], [
            'damage_type_code' => 'fire',
            'value' => ['base' => 1, 'size' => 0],
            'durability' => 3,
        ]));

        self::assertSame(4, $item->getArmor()?->getDefenseSlots()[0]->getDurability());
        self::assertSame(3, $item->getArmor()?->getResistanceSlots()[0]->getDurability());
    }

    /**
     * Нет ключа — null, документ разбирается.
     *
     * @return void
     */
    public function testMissingDurabilityIsNull(): void
    {
        $item = ItemSpecs::read($this->armor([
            'defense' => ['base' => 2, 'size' => 0],
        ], [
            'damage_type_code' => 'fire',
            'value' => ['base' => 1, 'size' => 0],
        ]));

        self::assertNull($item->getArmor()?->getDefenseSlots()[0]->getDurability());
        self::assertNull($item->getArmor()?->getResistanceSlots()[0]->getDurability());
    }

    /**
     * У защиты профиля блока нет прочности.
     *
     * @return void
     */
    public function testBlockDefenseHasNoDurability(): void
    {
        $item = ItemSpecs::read([
            'category' => 'equipment',
            'shield' => ['min_strength' => null],
            'block_profile' => [
                'efficiency' => ['base' => 1, 'size' => 0],
                'defense' => ['base' => 2, 'size' => 0],
            ],
        ]);
        $block = $item->getBlockProfile();

        self::assertInstanceOf(BlockProfile::class, $block);
        self::assertFalse(method_exists($block, 'getDurability'));
    }

    /**
     * Пустой профиль не становится нулями.
     *
     * @return void
     */
    public function testEmptyBlockProfileIsAbsent(): void
    {
        $item = ItemSpecs::read([
            'category' => 'equipment',
            'block_profile' => [],
        ]);

        self::assertNull($item->getBlockProfile());
    }

    /**
     * Разные старые профили оружия и щита не разбираются.
     *
     * @return void
     */
    public function testSplitBlockProfilesAreRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        ItemSpecs::read([
            'category' => 'equipment',
            'weapon' => [
                'block_profile' => [
                    'efficiency' => ['base' => 1, 'size' => 0],
                    'defense' => ['base' => 2, 'size' => 0],
                ],
            ],
            'shield' => [
                'block' => [
                    'efficiency' => ['base' => 3, 'size' => 0],
                    'defense' => ['base' => 4, 'size' => 0],
                ],
            ],
        ]);
    }

    /**
     * Грант сопротивления без ключа источника разбирается как раньше: пустая строка.
     *
     * @return void
     */
    public function testResistanceGrantWithoutSourceStaysEmpty(): void
    {
        $blocks = AbilityGrants::blocks([
            'grants' => [[
                'level' => 1,
                'grants' => [[
                    'type' => 'resistance',
                    'damage_type_code' => 'fire',
                    'value' => ['type' => 'fixed', 'value' => 1],
                ]],
            ]],
        ]);
        $grant = $blocks[0]->getGrants()[0];

        self::assertInstanceOf(ResistanceGrant::class, $grant);
        self::assertSame('', $grant->getSourceCode());
    }

    /**
     * Предмет с бронёй.
     *
     * @param array<string, mixed> $defense Слот защиты.
     * @param array<string, mixed> $resistance Слот сопротивления.
     *
     * @return array<string, mixed> Документ.
     */
    private function armor(array $defense, array $resistance): array
    {
        return [
            'category' => 'equipment',
            'armor' => [
                'defense_slots' => [$defense],
                'resistance_slots' => [$resistance],
            ],
        ];
    }
}
