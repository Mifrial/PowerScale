<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Save\CharacterItemInstances;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Пересчёт параметров экземпляра предмета из живого параметра донора.
 */
final class CharacterItemInstancesTest extends TestCase
{
    /**
     * Клиентское значение перезаписывается параметром способности-донора.
     *
     * @return void
     */
    public function testNormalizeTakesDonorParameter(): void
    {
        $slice = $this->slice([$this->capacity()]);
        $result = (new CharacterItemInstances())->normalize($slice, [
            'abilities' => [[
                'ruleCode' => 'magic-core-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 4, 'size' => -1]],
            ]],
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'magic-core',
                'grantedBy' => ['ruleCode' => 'magic-core-capacity'],
                'parameters' => ['power' => ['base' => 5, 'size' => 1]],
            ]],
        ]);

        self::assertSame([], $result['problems']);
        self::assertSame(
            ['power' => ['base' => 4, 'size' => -1]],
            $result['choices']['inventory'][0]['parameters'],
        );
    }

    /**
     * Без выбранного значения берётся default параметра.
     *
     * @return void
     */
    public function testNormalizeUsesParameterDefault(): void
    {
        $slice = $this->slice([$this->capacity()]);
        $result = (new CharacterItemInstances())->normalize($slice, [
            'abilities' => [['ruleCode' => 'magic-core-capacity', 'level' => 1]],
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'magic-core',
                'grantedBy' => ['ruleCode' => 'magic-core-capacity'],
            ]],
        ]);

        self::assertSame([], $result['problems']);
        self::assertSame(
            ['power' => ['base' => 3, 'size' => 0]],
            $result['choices']['inventory'][0]['parameters'],
        );
    }

    /**
     * Ссылка на отсутствующего донора — отказ, чужие параметры снимаются.
     *
     * @return void
     */
    public function testNormalizeRejectsMissingDonor(): void
    {
        $slice = $this->slice([$this->capacity()]);
        $result = (new CharacterItemInstances())->normalize($slice, [
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'magic-core',
                'grantedBy' => ['ruleCode' => 'magic-core-capacity'],
                'parameters' => ['power' => ['base' => 5, 'size' => 1]],
            ]],
        ]);

        self::assertCount(1, $result['problems']);
        self::assertSame('CHARACTER_ITEM_DONOR', $result['problems'][0]->getCode());
        self::assertArrayNotHasKey('parameters', $result['choices']['inventory'][0]);
    }

    /**
     * Строка без ссылки на донора не трогается.
     *
     * @return void
     */
    public function testNormalizeKeepsManualItem(): void
    {
        $slice = $this->slice([$this->capacity()]);
        $result = (new CharacterItemInstances())->normalize($slice, [
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'sword',
                'parameters' => ['power' => ['base' => 4, 'size' => 0]],
            ]],
        ]);

        self::assertSame([], $result['problems']);
        self::assertSame(
            ['power' => ['base' => 4, 'size' => 0]],
            $result['choices']['inventory'][0]['parameters'],
        );
    }

    /**
     * Предпросмотр создаёт строку предмета из активного гранта.
     *
     * @return void
     */
    public function testPreviewCreatesGrantedItem(): void
    {
        $slice = $this->slice([$this->capacity(), $this->core()]);
        $result = (new CharacterItemInstances())->preview($slice, [
            'abilities' => [[
                'ruleCode' => 'magic-core-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 4, 'size' => 0]],
            ]],
            'inventory' => [],
        ]);

        self::assertTrue($result['changed']);
        self::assertSame([[
            'id' => 1,
            'ruleCode' => 'magic-core',
            'quantity' => 1,
            'equipped' => true,
            'parameters' => ['power' => ['base' => 4, 'size' => 0]],
            'grantedBy' => ['ruleCode' => 'magic-core-capacity'],
        ]], $result['choices']['inventory']);
    }

    /**
     * Грант кристалла связывает и параметры, и поля payload.
     *
     * @return void
     */
    public function testPreviewBindsStoragePayload(): void
    {
        $slice = $this->slice([$this->crystalCapacity(), $this->crystal()]);
        $result = (new CharacterItemInstances())->preview($slice, [
            'abilities' => [[
                'ruleCode' => 'magic-crystal-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 4, 'size' => 0], 'volume' => 30],
            ]],
            'inventory' => [],
        ]);

        self::assertTrue($result['changed']);
        self::assertSame([[
            'id' => 1,
            'ruleCode' => 'magic-crystal',
            'quantity' => 1,
            'equipped' => true,
            'parameters' => ['max_power' => ['base' => 4, 'size' => 0]],
            'grantedBy' => ['ruleCode' => 'magic-crystal-capacity'],
            'payload' => ['volume' => 30],
        ]], $result['choices']['inventory']);
    }

    /**
     * Повторный предпросмотр не меняет ни id, ни значения.
     *
     * @return void
     */
    public function testPreviewIsIdempotent(): void
    {
        $slice = $this->slice([$this->capacity(), $this->core()]);
        $instances = new CharacterItemInstances();
        $choices = [
            'abilities' => [[
                'ruleCode' => 'magic-core-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 4, 'size' => 0]],
            ]],
            'inventory' => [],
        ];
        $first = $instances->preview($slice, $choices);
        $second = $instances->preview($slice, $first['choices']);

        self::assertFalse($second['changed']);
        self::assertSame($first['choices']['inventory'], $second['choices']['inventory']);
    }

    /**
     * Свободная строка того же кода подхватывается, а не дублируется.
     *
     * @return void
     */
    public function testPreviewAdoptsUnboundRow(): void
    {
        $slice = $this->slice([$this->capacity(), $this->core()]);
        $result = (new CharacterItemInstances())->preview($slice, [
            'abilities' => [[
                'ruleCode' => 'magic-core-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 3, 'size' => -1]],
            ]],
            'inventory' => [[
                'id' => 7,
                'ruleCode' => 'magic-core',
                'quantity' => 1,
                'equipped' => false,
            ]],
        ]);

        self::assertTrue($result['changed']);
        self::assertCount(1, $result['choices']['inventory']);
        self::assertSame(7, $result['choices']['inventory'][0]['id']);
        self::assertSame(['power' => ['base' => 3, 'size' => -1]], $result['choices']['inventory'][0]['parameters']);
        self::assertSame(['ruleCode' => 'magic-core-capacity'], $result['choices']['inventory'][0]['grantedBy']);
    }

    /**
     * Автоматическая ссылка расы — донор: мощь берётся из её параметра (4↓).
     *
     * @return void
     */
    public function testNormalizeBindsAutomaticRacialGift(): void
    {
        $slice = $this->slice([$this->crystalCapacity(), $this->crystal(), $this->yappi()]);
        $result = (new CharacterItemInstances())->normalize($slice, [
            'raceCode' => 'yappi',
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'magic-crystal',
                'grantedBy' => ['ruleCode' => 'magic-crystal-capacity'],
                'parameters' => ['max_power' => ['base' => 5, 'size' => 1]],
                'payload' => ['volume' => 20],
            ]],
        ]);

        self::assertSame([], $result['problems']);
        self::assertSame(
            ['max_power' => ['base' => 4, 'size' => -1]],
            $result['choices']['inventory'][0]['parameters'],
        );
        self::assertSame(['volume' => 20], $result['choices']['inventory'][0]['payload']);
    }

    /**
     * Выбранный экземпляр перекрывает automatic-мощь расы.
     *
     * @return void
     */
    public function testSelectedGiftOverridesAutomaticPower(): void
    {
        $slice = $this->slice([$this->crystalCapacity(), $this->crystal(), $this->yappi()]);
        $result = (new CharacterItemInstances())->normalize($slice, [
            'raceCode' => 'yappi',
            'abilities' => [[
                'ruleCode' => 'magic-crystal-capacity',
                'level' => 1,
                'parameters' => ['x' => ['base' => 5, 'size' => 0]],
            ]],
            'inventory' => [[
                'id' => 1,
                'ruleCode' => 'magic-crystal',
                'grantedBy' => ['ruleCode' => 'magic-crystal-capacity'],
                'payload' => ['volume' => 20],
            ]],
        ]);

        self::assertSame(
            ['max_power' => ['base' => 5, 'size' => 0]],
            $result['choices']['inventory'][0]['parameters'],
        );
    }

    /**
     * Предпросмотр создаёт врождённый кристалл из automatic-ссылки расы.
     *
     * @return void
     */
    public function testPreviewCreatesAutomaticRacialGift(): void
    {
        $slice = $this->slice([$this->crystalCapacity(), $this->crystal(), $this->yappi()]);
        $result = (new CharacterItemInstances())->preview($slice, ['raceCode' => 'yappi', 'inventory' => []]);

        self::assertTrue($result['changed']);
        self::assertSame([[
            'id' => 1,
            'ruleCode' => 'magic-crystal',
            'quantity' => 1,
            'equipped' => true,
            'parameters' => ['max_power' => ['base' => 4, 'size' => -1]],
            'grantedBy' => ['ruleCode' => 'magic-crystal-capacity'],
            'payload' => ['volume' => 20],
        ]], $result['choices']['inventory']);
    }

    /**
     * Срез с одной способностью.
     *
     * @param array<int, CharacterResolvedRule> $live Правила.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $live): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', $live, []);
    }

    /**
     * Раса Яппи: врождённый кристалл с мощью 4↓ и потолком 5.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function yappi(): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(5, 1, 'yappi', true, 'race', 'Яппи', '', [
                'parent_race_code' => null,
                'cost_os' => 0,
                'characteristics' => [],
                'abilities' => [[
                    'automatic' => true,
                    'ability_code' => 'magic-crystal-capacity',
                    'parameters' => ['x' => ['base' => 4, 'size' => -1]],
                ], [
                    'automatic' => false,
                    'ability_code' => 'magic-crystal-capacity',
                    'parameters' => ['x' => ['base' => 5, 'size' => 0]],
                ]],
                'movement_step' => ['base' => 1, 'size' => 0],
            ], [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * Карточка «Врождённое магическое ядро».
     *
     * @return CharacterResolvedRule Правило.
     */
    private function capacity(): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(1, 1, 'magic-core-capacity', true, 'ability', 'Ядро', '', [
                'type' => 'trait',
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
            ], [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * Карточка «Врождённый кристалл».
     *
     * @return CharacterResolvedRule Правило.
     */
    private function crystalCapacity(): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(3, 1, 'magic-crystal-capacity', true, 'ability', 'Кристалл', '', [
                'type' => 'trait',
                'parameters' => [[
                    'code' => 'x',
                    'label' => 'X',
                    'kind' => 'dimensional',
                    'resolution' => 'purchase',
                    'default' => ['base' => 3, 'size' => 0],
                    'min' => ['base' => 3, 'size' => -1],
                    'max' => ['base' => 5, 'size' => 1],
                ], [
                    'code' => 'volume',
                    'label' => 'Объём',
                    'kind' => 'scalar',
                    'resolution' => 'purchase',
                    'default' => 20,
                    'min' => 20,
                    'max' => 200,
                ]],
                'grants' => [[
                    'level' => 1,
                    'grants' => [[
                        'type' => 'item',
                        'item_code' => 'magic-crystal',
                        'quantity' => 1,
                        'parameters' => ['max_power' => 'x'],
                        'payload' => ['volume' => 'volume'],
                    ]],
                ]],
            ], [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * Карточка кристалла-хранилища.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function crystal(): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(4, 1, 'magic-crystal', true, 'item', 'Кристалл', '', [
                'category' => 'equipment',
                'innate' => true,
                'special_rule_codes' => [],
                'storage' => [
                    'power_parameter' => 'max_power',
                    'volume_parameter' => 'volume',
                    'recorded_parameter' => 'recorded_spells',
                    'volume_base' => 20,
                    'volume_increment' => 10,
                    'min_volume_power_delta' => 3,
                ],
            ], [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * Карточка магического ядра.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function core(): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(2, 1, 'magic-core', true, 'item', 'Магическое ядро', '', [
                'category' => 'equipment',
                'innate' => true,
                'special_rule_codes' => [],
                'grants' => [[
                    'type' => 'characteristic_parameter',
                    'characteristic_code' => 'magic-power',
                    'parameter_code' => 'power',
                    'per_unit' => 1,
                    'permanent' => true,
                ]],
            ], [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }
}
