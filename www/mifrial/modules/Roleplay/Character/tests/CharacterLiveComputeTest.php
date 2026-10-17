<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterActualMutations;
use Mifrial\Roleplay\Character\Service\Runtime\Character;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Save\CharacterSheetDocument;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use PHPUnit\Framework\TestCase;

/**
 * Полный пересчёт листа: innate, экипировка, требования.
 */
final class CharacterLiveComputeTest extends TestCase
{
    /**
     * Авто-ресурс с characteristic_size без закупок получает размер automatic.
     *
     * @return void
     */
    public function testInnateSizeRaisesAutoResourceLimit(): void
    {
        $slice = $this->slice([
            $this->characteristic('dexterity', ['base' => 3, 'size' => 1]),
            $this->resource(),
        ]);
        $character = Character::fromDocument($slice, ['money' => 0], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 2]],
        ], $this->sheets());

        $row = $character->getSheet()['resources'][0];
        self::assertSame('action-points', $row['ruleCode']);
        self::assertSame(5, $row['base']);
        self::assertSame([['sourceRuleCode' => 'innate', 'delta' => 1]], $row['bonuses']);
        self::assertSame(2, $row['current']);

        $patched = $this->mutations($slice)->applyToDocument(1, 1, ['money' => 0], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 2]],
        ], [['kind' => 'setResourceCurrent', 'resourceCode' => 'action-points', 'current' => 99]]);
        self::assertSame(6, $patched['sheet']['resources'][0]['current']);
        self::assertSame(5, $patched['sheet']['resources'][0]['base']);
        self::assertSame([['sourceRuleCode' => 'innate', 'delta' => 1]], $patched['sheet']['resources'][0]['bonuses']);

        $npc = $this->mutations($slice)->applyToDocument(1, 1, ['money' => 0], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 2]],
        ], [['kind' => 'setMoney', 'amount' => 0]]);
        self::assertSame(5, $npc['sheet']['resources'][0]['base']);
        self::assertSame([['sourceRuleCode' => 'innate', 'delta' => 1]], $npc['sheet']['resources'][0]['bonuses']);
        self::assertSame(2, $npc['sheet']['resources'][0]['current']);
    }

    /**
     * Грант надетого предмета меняет характеристику; рюкзак — нет.
     *
     * @return void
     */
    public function testEquippedItemGrantAppliesOnlyWhenEquipped(): void
    {
        $item = $this->item('mail', [
            'grants' => [[
                'type' => 'characteristic_modify',
                'characteristic_code' => 'strength',
                'source_code' => 'mail',
                'check_codes' => [],
                'amount' => -2,
                'permanent' => true,
            ]],
        ]);
        $slice = $this->slice([$this->characteristic('strength', ['base' => 4, 'size' => 0]), $item]);
        $worn = Character::fromDocument($slice, [
            'inventory' => [['id' => 1, 'ruleCode' => 'mail', 'quantity' => 1, 'equipped' => true, 'custom' => false]],
        ], [], $this->sheets());
        $packed = Character::fromDocument($slice, [
            'inventory' => [['id' => 1, 'ruleCode' => 'mail', 'quantity' => 1, 'equipped' => false, 'custom' => false]],
        ], [], $this->sheets());

        self::assertSame(-2, $this->deltaOf($worn->getSheet(), 'strength'));
        self::assertSame(0, $this->deltaOf($packed->getSheet(), 'strength'));
        $spec = $slice->findLive('mail')?->getSpec();
        self::assertInstanceOf(ItemSpec::class, $spec);
        self::assertInstanceOf(CharacteristicModifyGrant::class, $spec->getGrants()[0]);
    }

    /**
     * Состояние characteristic_modify гасит способность по characteristic_value.
     *
     * @return void
     */
    public function testStateModifyDropsAbilityGrantsWhenRequirementFails(): void
    {
        $slice = $this->slice([
            $this->characteristic('might', ['base' => 3, 'size' => 0]),
            $this->ability('boost', [
                'type' => 'trait',
                'requirements' => [[
                    'level' => 1,
                    'requirements' => [[
                        'type' => 'characteristic_value',
                        'characteristic_code' => 'might',
                        'min' => ['base' => 3, 'size' => 0],
                    ]],
                ]],
                'grants' => [[
                    'level' => 1,
                    'grants' => [[
                        'type' => 'characteristic_modify',
                        'characteristic_code' => 'might',
                        'source_code' => 'boost',
                        'check_codes' => [],
                        'amount' => 4,
                        'permanent' => true,
                    ]],
                ]],
            ]),
            $this->rule('burden', 'state', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
                'effects' => [[
                    'type' => 'characteristic_modify',
                    'characteristic_code' => 'might',
                    'amount' => -10,
                    'per_unit' => false,
                ]],
            ]),
        ]);
        $choices = ['abilities' => [['ruleCode' => 'boost', 'level' => 1]]];
        $clear = Character::fromDocument($slice, $choices, [], $this->sheets());
        $burdened = Character::fromDocument($slice, $choices, [
            'states' => [['id' => 1, 'stateRuleCode' => 'burden']],
        ], $this->sheets());

        self::assertSame(4, $this->deltaOf($clear->getSheet(), 'might'));
        self::assertSame(1, $clear->getSheet()['abilityLevels']['boost']);
        self::assertSame(0, $burdened->getSheet()['abilityLevels']['boost']);
        self::assertSame(-10, $this->deltaOf($burdened->getSheet(), 'might'));
    }

    /**
     * Мод characteristic_modify меняет грант; strength_penalty лист не двигает.
     *
     * @return void
     */
    public function testEquippedModifierOpsChangeGrantAndIgnoreLegacyPenalty(): void
    {
        $item = $this->item('mail', [
            'grants' => [[
                'type' => 'characteristic_modify',
                'characteristic_code' => 'strength',
                'source_code' => 'mail',
                'check_codes' => [],
                'amount' => -1,
                'permanent' => true,
            ]],
            'armor' => [
                'characteristic_limits' => [[
                    'characteristic_code' => 'agility',
                    'limit' => ['type' => 'dimensional', 'base' => 4, 'size' => 0],
                ]],
            ],
        ]);
        $mass = $this->rule('mass', 'item_modifier', [
            'type_code' => 'item-weight',
            'operations' => [[
                'type' => 'characteristic_modify',
                'characteristic_code' => 'strength',
                'add' => -2,
                'source_code' => 'mass',
            ], [
                'type' => 'characteristic_limit',
                'characteristic_code' => 'agility',
                'delta' => -1,
                'source_code' => 'mass',
            ]],
        ]);
        $legacy = $this->rule('legacy-mass', 'item_modifier', [
            'type_code' => 'item-weight',
            'operations' => [[
                'type' => 'strength_penalty',
                'add' => -4,
                'source_code' => 'mass',
            ]],
        ]);
        $agility = $this->characteristic('agility', ['base' => 8, 'size' => 0]);
        $strength = $this->characteristic('strength', ['base' => 4, 'size' => 0]);
        $inventory = [
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'mail',
                'quantity' => 1,
                'equipped' => true,
                'custom' => false,
                'modifiers' => ['mass'],
            ]],
        ];
        $live = Character::fromDocument(
            $this->slice([$strength, $agility, $item, $mass]),
            $inventory,
            [],
            $this->sheets(),
        );
        $stale = Character::fromDocument(
            $this->slice([$strength, $agility, $item, $legacy]),
            [
                'inventory' => [[
                    'id' => 1,
                    'ruleCode' => 'mail',
                    'quantity' => 1,
                    'equipped' => true,
                    'custom' => false,
                    'modifiers' => ['legacy-mass'],
                ]],
            ],
            [],
            $this->sheets(),
        );

        self::assertSame(-3, $this->deltaOf($live->getSheet(), 'strength'));
        self::assertSame(3, $this->valueBase($live->getSheet(), 'agility'));
        self::assertSame(-1, $this->deltaOf($stale->getSheet(), 'strength'));
    }

    /**
     * Параметр врождённой черты на расе даёт размер ловкости и лимит ОД.
     *
     * @return void
     */
    public function testRacialInnateParameterRaisesDexteritySizeAndActionPoints(): void
    {
        $slice = $this->slice([
            $this->characteristic('dexterity', ['base' => 3, 'size' => 0]),
            $this->resource(),
            $this->ability('innate-dexterity', [
                'type' => 'trait',
                'parameters' => [[
                    'code' => 'x',
                    'label' => 'X',
                    'kind' => 'scalar',
                    'resolution' => 'purchase',
                    'default' => 0,
                    'min' => -3,
                    'max' => 3,
                ]],
                'grants' => [[
                    'level' => 1,
                    'grants' => [[
                        'type' => 'characteristic_modify',
                        'characteristic_code' => 'dexterity',
                        'source_code' => 'innate',
                        'check_codes' => [],
                        'amount' => ['type' => 'parameter', 'parameter_code' => 'x', 'per_unit' => 1],
                        'permanent' => true,
                    ]],
                ]],
            ]),
            $this->rule('human', 'race', [
                'cost_os' => 0,
                'characteristics' => [[
                    'characteristic_code' => 'dexterity',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [],
                ]],
                'abilities' => [[
                    'ability_code' => 'innate-dexterity',
                    'automatic' => true,
                    'parameters' => ['x' => ['base' => 3, 'size' => 0]],
                ]],
            ]),
        ]);
        $character = Character::fromDocument($slice, ['raceCode' => 'human'], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 6]],
        ], $this->sheets());

        self::assertSame(3, $this->valueBase($character->getSheet(), 'dexterity'));
        self::assertSame(1, $this->valueSize($character->getSheet(), 'dexterity'));
        self::assertSame(3, $this->deltaOf($character->getSheet(), 'dexterity'));
        self::assertSame(5, $character->getSheet()['resources'][0]['base']);
        self::assertSame(
            [['sourceRuleCode' => 'innate', 'delta' => 1]],
            $character->getSheet()['resources'][0]['bonuses'],
        );
        self::assertSame(6, $character->getSheet()['resources'][0]['current']);
    }

    /**
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return CharacterActualMutations Порт.
     */
    private function mutations(CharacterRuleSlice $slice): CharacterActualMutations
    {
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($slice);

        return new CharacterActualMutations(
            $this->createMock(ICharacters::class),
            $slices,
            $this->sheets(),
            new CharacterChoiceAssembler(),
            new CharacterSheetDocument(),
        );
    }

    /**
     * @return ICharacterSheets Мок без problems.
     */
    private function sheets(): ICharacterSheets
    {
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturnCallback(
            function ($slice, $model): CharacterValidation {
                $levels = [];
                foreach ($model->getAbilities() as $ability) {
                    $levels[$ability->getRuleCode()] = $ability->getLevel();
                }

                return new CharacterValidation([], $levels, [], 0, [], [], [], true);
            },
        );

        return $sheets;
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
     * @param string $code Код.
     * @param array{base: int, size: int} $automatic База.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function characteristic(string $code, array $automatic): CharacterResolvedRule
    {
        return $this->rule($code, 'characteristic', [
            'automatic' => ['value' => $automatic],
            'damage_endurance' => false,
            'willpower' => false,
            'concentration_threshold' => false,
            'concentration_token' => false,
            'unstable_roll' => false,
            'initiative' => false,
            'dodge_soak' => false,
        ]);
    }

    /**
     * @return CharacterResolvedRule Ресурс.
     */
    private function resource(): CharacterResolvedRule
    {
        return $this->rule('action-points', 'resource', [
            'is_dimensional' => false,
            'auto_add' => true,
            'check_token' => false,
            'limit' => [
                'base' => 5,
                'adjustments' => [[
                    'value' => ['type' => 'characteristic_size', 'characteristic_code' => 'dexterity'],
                    'source_code' => 'innate',
                ]],
            ],
        ]);
    }

    /**
     * @param string $code Код.
     * @param array<string, mixed> $spec Spec.
     *
     * @return CharacterResolvedRule Предмет.
     */
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
     * @param string $type Тип.
     * @param array<string, mixed> $spec Документ.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(1, 1, $code, true, $type, 'Name', '', $spec, [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * @param array<string, mixed> $sheet Sheet.
     * @param string $code Код.
     *
     * @return int Сумма дельт.
     */
    private function deltaOf(array $sheet, string $code): int
    {
        foreach ($sheet['characteristics'] ?? [] as $row) {
            if (is_array($row) && ($row['characteristicCode'] ?? null) === $code) {
                return (int) ($row['delta'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $sheet Sheet.
     * @param string $code Код.
     *
     * @return int База живого значения.
     */
    private function valueBase(array $sheet, string $code): int
    {
        foreach ($sheet['characteristics'] ?? [] as $row) {
            if (is_array($row) && ($row['characteristicCode'] ?? null) === $code) {
                $value = $row['value'] ?? [];

                return is_array($value) ? (int) ($value['base'] ?? 0) : 0;
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $sheet Sheet.
     * @param string $code Код.
     *
     * @return int Размер живого значения.
     */
    private function valueSize(array $sheet, string $code): int
    {
        foreach ($sheet['characteristics'] ?? [] as $row) {
            if (is_array($row) && ($row['characteristicCode'] ?? null) === $code) {
                $value = $row['value'] ?? [];

                return is_array($value) ? (int) ($value['size'] ?? 0) : 0;
            }
        }

        return 0;
    }
}
