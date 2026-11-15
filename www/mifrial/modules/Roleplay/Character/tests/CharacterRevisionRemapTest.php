<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\CharacterRevisionRemap;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Перенос choices: дар без ссылки расы снимается вместе с выданным предметом.
 */
final class CharacterRevisionRemapTest extends TestCase
{
    /**
     * Дар живой, но раса на него не ссылается — строка и предмет снимаются.
     *
     * @return void
     */
    public function testDropsGiftWithoutRaceReference(): void
    {
        $slice = $this->slice(false);

        $result = (new CharacterRevisionRemap())->remap($this->stored(), $slice, $slice);

        self::assertTrue($result['changed']);
        self::assertSame([], $result['choices']['abilities']);
        self::assertCount(1, $result['choices']['inventory']);
        self::assertSame('sword', $result['choices']['inventory'][0]['ruleCode']);
    }

    /**
     * Раса ссылается на дар — строка и выданный предмет остаются.
     *
     * @return void
     */
    public function testKeepsGiftReferencedByRace(): void
    {
        $slice = $this->slice(true);

        $result = (new CharacterRevisionRemap())->remap($this->stored(), $slice, $slice);

        self::assertFalse($result['changed']);
        self::assertCount(1, $result['choices']['abilities']);
        self::assertCount(2, $result['choices']['inventory']);
    }

    /**
     * Выборы персонажа с даром и предметом.
     *
     * @return array<string, mixed> Документ.
     */
    private function stored(): array
    {
        return [
            'name' => 'Колдунец',
            'raceCode' => 'kogir',
            'abilities' => [[
                'ruleCode' => 'magic-core-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 4, 'size' => 0]],
            ]],
            'inventory' => [
                [
                    'id' => 1,
                    'ruleCode' => 'magic-core',
                    'quantity' => 1,
                    'equipped' => true,
                    'grantedBy' => ['ruleCode' => 'magic-core-capacity'],
                ],
                [
                    'id' => 2,
                    'ruleCode' => 'sword',
                    'quantity' => 1,
                    'equipped' => false,
                ],
            ],
            'limits' => [],
        ];
    }

    /**
     * Срез с расой, даром и предметами.
     *
     * @param bool $withReference Раса ссылается на дар.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(bool $withReference): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 53, 'world', [
            $this->rule('kogir', 'race', [
                'parent_race_code' => null,
                'cost_os' => 0,
                'characteristics' => [],
                'abilities' => $withReference
                    ? [['ability_code' => 'magic-core-capacity', 'automatic' => false]]
                    : [],
            ]),
            $this->rule('magic-core-capacity', 'ability', [
                'type' => 'trait',
                'zones' => ['os' => ['kind' => 'parameter_table', 'parameter_code' => 'x', 'costs' => ['4' => 6]]],
                'parameters' => [[
                    'code' => 'x',
                    'label' => 'X',
                    'kind' => 'dimensional',
                    'resolution' => 'purchase',
                    'default' => ['base' => 3, 'size' => 0],
                    'min' => ['base' => 3, 'size' => -1],
                    'max' => ['base' => 5, 'size' => 1],
                ]],
                'grants' => [[
                    'level' => 1,
                    'grants' => [[
                        'type' => 'item',
                        'item_code' => 'magic-core',
                        'quantity' => 1,
                        'parameters' => ['power' => 'x'],
                    ]],
                ]],
                'requirements' => [],
                'parent_ability_code' => null,
            ], ['gift']),
            $this->rule('magic-core', 'item', [
                'category' => 'equipment',
                'weight' => null,
                'special_rule_codes' => [],
            ]),
            $this->rule('sword', 'item', [
                'category' => 'other',
                'cost_gm' => 10,
                'weight' => null,
                'special_rule_codes' => [],
            ]),
        ], []);
    }

    /**
     * Живое правило среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Spec.
     * @param array<int, string> $keywords Коды признаков.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec, array $keywords = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                $code,
                '',
                $spec,
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            $keywords,
        );
    }
}
