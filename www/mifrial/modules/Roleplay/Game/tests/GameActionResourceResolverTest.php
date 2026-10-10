<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameActionResourceResolver;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет server-owned resource resolution.
 */
final class GameActionResourceResolverTest extends TestCase
{
    /**
     * Валидная scalar chosen сумма становится typed spend.
     *
     * @return void
     */
    public function testResolvesValidChosenScalar(): void
    {
        $spends = (new GameActionResourceResolver())->resolve(
            $this->slice(),
            'action',
            1,
            'sword',
            ['inventory' => [$this->inventoryRow()]],
            ['resources' => [['ruleCode' => 'ap', 'current' => 3]]],
            ['ap' => 2],
        );

        self::assertCount(1, $spends);
        self::assertSame(2, $spends[0]->getAmount()->toNative());
    }

    /**
     * Одна выбранная строка не читает modifiers другой строки того же кода.
     *
     * @return void
     */
    public function testResolvesOnlySelectedInventoryInstance(): void
    {
        $spends = (new GameActionResourceResolver())->resolve(
            $this->slice(),
            'action',
            2,
            'sword',
            [
                'inventory' => [
                    ['id' => 1, 'equipped' => true, 'ruleCode' => 'sword', 'modifiers' => ['broken']],
                    ['id' => 2, 'equipped' => true, 'ruleCode' => 'sword', 'modifiers' => []],
                ],
            ],
            ['resources' => [['ruleCode' => 'ap', 'current' => 3]]],
            ['ap' => 2],
        );

        self::assertSame(2, $spends[0]->getAmount()->toNative());
    }

    /**
     * Zero, negative and overflow chosen values are invalid before mutation.
     *
     * @return void
     */
    public function testRejectsInvalidChosenScalar(): void
    {
        $resolver = new GameActionResourceResolver();
        foreach ([0, -1, 4, '2', ['base' => 2]] as $value) {
            try {
                $resolver->resolve(
                    $this->slice(),
                    'action',
                    1,
                    'sword',
                    ['inventory' => [$this->inventoryRow()]],
                    ['resources' => [['ruleCode' => 'ap', 'current' => 3]]],
                    ['ap' => $value],
                );
                self::fail('Expected invalid chosen amount');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * Missing and ambiguous chosen maps are rejected.
     *
     * @return void
     */
    public function testRejectsMissingAndAmbiguousChosenAmount(): void
    {
        $resolver = new GameActionResourceResolver();
        $sheet = ['resources' => [['ruleCode' => 'ap', 'current' => 3]]];
        $choices = ['inventory' => [$this->inventoryRow()]];

        foreach ([[], [2], ['ap' => 2, 'other' => 1]] as $chosenAmounts) {
            try {
                $resolver->resolve($this->slice(), 'action', 1, 'sword', $choices, $sheet, $chosenAmounts);
                self::fail('Expected invalid chosen amount map');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * Missing, unequipped, duplicate and mismatched selected rows are invalid.
     *
     * @return void
     */
    public function testRejectsInvalidSelectedInventoryInstance(): void
    {
        $resolver = new GameActionResourceResolver();
        $sheet = ['resources' => [['ruleCode' => 'ap', 'current' => 3]]];
        $cases = [
            99 => [['id' => 1, 'equipped' => true, 'ruleCode' => 'sword', 'modifiers' => []]],
            1 => [['id' => 1, 'equipped' => false, 'ruleCode' => 'sword', 'modifiers' => []]],
            2 => [
                ['id' => 2, 'equipped' => true, 'ruleCode' => 'sword', 'modifiers' => []],
                ['id' => 2, 'equipped' => true, 'ruleCode' => 'sword', 'modifiers' => []],
            ],
            3 => [['id' => 3, 'equipped' => true, 'ruleCode' => 'axe', 'modifiers' => []]],
        ];

        foreach ($cases as $inventoryId => $inventory) {
            try {
                $resolver->resolve(
                    $this->slice(),
                    'action',
                    $inventoryId,
                    'sword',
                    ['inventory' => $inventory],
                    $sheet,
                    ['ap' => 2],
                );
                self::fail('Expected invalid selected inventory instance');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * Строит минимальный live slice для chosen scalar.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        $action = new RuleVersionRecord(
            1,
            1,
            'action',
            true,
            'ability',
            'Action',
            '',
            [
                'type' => 'action',
                'components' => [[
                    'type' => 'resource',
                    'resource_code' => 'ap',
                    'amount' => ['type' => 'chosen', 'max' => 'available'],
                ]],
                'distinct_weapons' => false,
                'same_weapon' => false,
                'lift_parent_max_weapons' => false,
                'peak_concentration' => false,
                'will_focus' => false,
                'long_tension' => false,
                'multiple' => false,
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
        $resource = new RuleVersionRecord(
            2,
            2,
            'ap',
            true,
            'resource',
            'Action points',
            '',
            [
                'is_dimensional' => false,
                'auto_add' => false,
                'check_token' => false,
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
        $item = new RuleVersionRecord(
            3,
            3,
            'sword',
            true,
            'item',
            'Sword',
            '',
            [
                'category' => 'weapon',
                'innate' => false,
                'special_rule_codes' => [],
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
                new CharacterResolvedRule($action, []),
                new CharacterResolvedRule($resource, []),
                new CharacterResolvedRule($item, []),
            ],
            [],
        );
    }

    /**
     * Возвращает минимальную equipped строку предмета.
     *
     * @return array{id: int, equipped: bool, ruleCode: string, modifiers: array<int, string>} Строка.
     */
    private function inventoryRow(): array
    {
        return ['id' => 1, 'equipped' => true, 'ruleCode' => 'sword', 'modifiers' => []];
    }
}
