<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterActualMutations;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Save\CharacterSheetDocument;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * putState на документе: карточка среза и список sheet.states.
 */
final class CharacterActualStateTest extends TestCase
{
    /**
     * Flag, number и dimensional дописываются. Choices не меняются.
     *
     * @return void
     */
    public function testPutStateAppendsRowsByValueType(): void
    {
        $choices = ['money' => 4];
        $patched = $this->mutations($this->slice([
            $this->state('flagged', 'flag'),
            $this->state('counted', 'number'),
            $this->state('sized', 'dimensional'),
        ]))->applyToDocument(1, 1, $choices, ['marker' => 'stay'], [
            ['kind' => 'putState', 'stateRuleCode' => 'flagged'],
            ['kind' => 'putState', 'stateRuleCode' => 'counted', 'value' => 3],
            ['kind' => 'putState', 'stateRuleCode' => 'sized', 'value' => ['base' => 1, 'size' => 2]],
            ['kind' => 'putState', 'stateRuleCode' => 'counted', 'value' => 5],
            ['kind' => 'setMoney', 'amount' => 8],
        ]);

        self::assertSame($choices, ['money' => 4]);
        self::assertSame(8, $patched['choices']['money']);
        self::assertSame('stay', $patched['sheet']['marker']);
        self::assertSame(8, $patched['sheet']['money']);
        self::assertSame([
            ['stateRuleCode' => 'flagged'],
            ['stateRuleCode' => 'counted', 'value' => 3],
            ['stateRuleCode' => 'sized', 'value' => ['base' => 1, 'size' => 2]],
            ['stateRuleCode' => 'counted', 'value' => 5],
        ], $patched['sheet']['states']);
    }

    /**
     * Resource spend is applied in the same prepared document mutation.
     *
     * @return void
     */
    public function testSpendResourceChangesOnlyNativeCurrent(): void
    {
        $patched = $this->mutations($this->slice([
            $this->rule('action-points', 'resource', [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'limit' => ['base' => 5, 'adjustments' => []],
            ]),
        ]))->applyToDocument(1, 1, ['money' => 4], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 5]],
        ], [[
            'kind' => 'spendResources',
            'spends' => [new ResourceSpend('action-points', new ScalarResourceValue(2))],
        ]]);

        self::assertSame([
            ['ruleCode' => 'action-points', 'current' => 3],
        ], $patched['sheet']['resources']);
    }

    /**
     * Без putState ключ не появляется и лежащий список остаётся.
     *
     * @return void
     */
    public function testMoneyDoesNotCreateOrClearStates(): void
    {
        $kept = [['stateRuleCode' => 'flagged']];
        $mutations = $this->mutations($this->slice([$this->state('flagged', 'flag')]));
        $created = $mutations->applyToDocument(1, 1, ['money' => 1], ['marker' => 'stay'], [
            ['kind' => 'setMoney', 'amount' => 2],
        ]);
        $keptSheet = $mutations->applyToDocument(1, 1, ['money' => 1], ['states' => $kept], [
            ['kind' => 'setMoney', 'amount' => 2],
        ]);

        self::assertArrayNotHasKey('states', $created['sheet']);
        self::assertSame($kept, $keptSheet['sheet']['states']);
    }

    /**
     * Остаток заменяет строку, частное прибавляется. Ноль истощение не трогает.
     *
     * @return void
     */
    public function testDamageSplitReplacesRemainderAndAddsExhaustion(): void
    {
        $slice = $this->slice([
            $this->state('other', 'flag'),
            $this->marked('hurt', 'dimensional', 'damage_remainder'),
            $this->marked('tired', 'number', 'damage_exhaustion'),
        ]);
        $sheet = ['states' => [
            ['stateRuleCode' => 'other'],
            ['stateRuleCode' => 'hurt', 'value' => ['base' => 9, 'size' => 9], 'wound' => 1],
            ['stateRuleCode' => 'tired', 'value' => 4],
        ]];
        $patched = $this->mutations($slice)->applyToDocument(1, 1, ['money' => 1], $sheet, [
            $this->split(['base' => 0, 'size' => 0], 3),
        ]);
        $kept = $this->mutations($slice)->applyToDocument(1, 1, ['money' => 1], $sheet, [
            $this->split(['base' => -1, 'size' => 2], 0),
        ]);

        self::assertSame(['money' => 1], $patched['choices']);
        self::assertSame([
            ['stateRuleCode' => 'other'],
            ['stateRuleCode' => 'hurt', 'value' => ['base' => 0, 'size' => 0]],
            ['stateRuleCode' => 'tired', 'value' => 7],
        ], $patched['sheet']['states']);
        self::assertSame([
            ['stateRuleCode' => 'other'],
            ['stateRuleCode' => 'hurt', 'value' => ['base' => -1, 'size' => 2]],
            ['stateRuleCode' => 'tired', 'value' => 4],
        ], $kept['sheet']['states']);
    }

    /**
     * Обеих строк не было: сначала остаток, затем частное.
     *
     * @return void
     */
    public function testDamageSplitCreatesRemainderBeforeExhaustion(): void
    {
        $slice = $this->slice([
            $this->marked('hurt', 'dimensional', 'damage_remainder'),
            $this->marked('tired', 'number', 'damage_exhaustion'),
        ]);
        $patched = $this->mutations($slice)->applyToDocument(1, 1, [], ['states' => [
            ['stateRuleCode' => 'other'],
        ]], [
            $this->split(['base' => 1, 'size' => 2], 5),
        ]);

        self::assertSame([
            ['stateRuleCode' => 'other'],
            ['stateRuleCode' => 'hurt', 'value' => ['base' => 1, 'size' => 2]],
            ['stateRuleCode' => 'tired', 'value' => 5],
        ], $patched['sheet']['states']);
    }

    /**
     * putState того же кода до замены даёт две строки. После — дописывает ещё одну.
     *
     * @return void
     */
    public function testDamageSplitOrdersWithPutState(): void
    {
        $slice = $this->slice([
            $this->marked('hurt', 'dimensional', 'damage_remainder'),
            $this->marked('tired', 'number', 'damage_exhaustion'),
        ]);
        $mutations = $this->mutations($slice);
        $after = $mutations->applyToDocument(1, 1, [], [], [
            $this->split(['base' => 1, 'size' => 0], 0),
            ['kind' => 'putState', 'stateRuleCode' => 'hurt', 'value' => ['base' => 3, 'size' => 0]],
        ]);

        self::assertSame([
            ['stateRuleCode' => 'hurt', 'value' => ['base' => 1, 'size' => 0]],
            ['stateRuleCode' => 'hurt', 'value' => ['base' => 3, 'size' => 0]],
        ], $after['sheet']['states']);

        try {
            $mutations->applyToDocument(1, 1, [], ['states' => [
                ['stateRuleCode' => 'hurt', 'value' => ['base' => 1, 'size' => 0]],
            ]], [
                ['kind' => 'putState', 'stateRuleCode' => 'hurt', 'value' => ['base' => 2, 'size' => 0]],
                $this->split(['base' => 4, 'size' => 0], 0),
            ]);
            self::fail('second row must reject');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет карточки, две карточки, оба флага, чужой тип значения, две строки, битая форма.
     *
     * @return void
     */
    public function testDamageSplitRejects(): void
    {
        $hurt = $this->marked('hurt', 'dimensional', 'damage_remainder');
        $tired = $this->marked('tired', 'number', 'damage_exhaustion');
        $both = $this->rule('both', 'state', [
            'value_type' => 'dimensional',
            'damage_remainder' => true,
            'damage_exhaustion' => true,
        ]);
        $cases = [
            [$this->slice([$tired]), $this->split(['base' => 1, 'size' => 0], 1), []],
            [$this->slice([
                $hurt,
                $this->marked('hurt-b', 'dimensional', 'damage_remainder'),
                $tired,
            ]), $this->split(['base' => 1, 'size' => 0], 1), []],
            [$this->slice([
                $hurt,
                $tired,
                $this->marked('tired-b', 'number', 'damage_exhaustion'),
            ]), $this->split(['base' => 1, 'size' => 0], 1), []],
            [$this->slice([$both]), $this->split(['base' => 1, 'size' => 0], 0), []],
            [$this->slice([
                $this->marked('hurt', 'number', 'damage_remainder'),
                $tired,
            ]), $this->split(['base' => 1, 'size' => 0], 0), []],
            [$this->slice([
                $hurt,
                $this->marked('tired', 'flag', 'damage_exhaustion'),
            ]), $this->split(['base' => 1, 'size' => 0], 0), []],
            [$this->slice([$hurt, $tired]), ['kind' => 'putDamageSplit', 'remainder' => ['base' => 1], 'quotient' => 1], []],
            [$this->slice([$hurt, $tired]), ['kind' => 'putDamageSplit', 'quotient' => 1], []],
        ];
        foreach ($cases as [$slice, $operation]) {
            try {
                $this->mutations($slice)->applyToDocument(1, 1, [], ['marker' => 'stay'], [$operation]);
                self::fail('invalid damage split must reject');
            } catch (CharacterInvalidException $exception) {
                self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
            }
        }

        try {
            $this->mutations($this->slice([$hurt, $tired]))->applyToDocument(1, 1, [], ['states' => [
                ['stateRuleCode' => 'hurt', 'value' => ['base' => 1, 'size' => 0]],
                ['stateRuleCode' => 'hurt', 'value' => ['base' => 2, 'size' => 0]],
            ]], [$this->split(['base' => 3, 'size' => 0], 0)]);
            self::fail('duplicate row must reject');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $this->mutations($this->slice([$hurt, $tired]))->applyToDocument(1, 1, [], ['states' => [
                ['stateRuleCode' => 'tired', 'value' => 'x'],
            ]], [$this->split(['base' => 1, 'size' => 0], 0)]);
            self::fail('exhaustion value must be int');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $this->mutations($this->slice([$hurt, $tired]))->applyToDocument(1, 1, [], ['states' => [
                ['stateRuleCode' => 'tired', 'value' => 'x'],
            ]], [$this->split(['base' => 1, 'size' => 0], 2)]);
            self::fail('exhaustion value must be int');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет карточки, tombstone, чужой тип, битый spec, чужой value_type, битое значение, лишний ключ и не-список.
     *
     * @return void
     */
    public function testSliceAndShapeReject(): void
    {
        $flag = $this->state('flagged', 'flag');
        $cases = [
            [$this->slice([]), ['kind' => 'putState', 'stateRuleCode' => 'flagged'], []],
            [$this->slice([]), ['kind' => 'putState', 'stateRuleCode' => 'gone'], ['gone' => true]],
            [$this->slice([$this->rule('poisoned', 'poison', [])]), ['kind' => 'putState', 'stateRuleCode' => 'poisoned'], []],
            [$this->slice([$this->rule('broken', 'state', ['damage_remainder' => 'no'])]), ['kind' => 'putState', 'stateRuleCode' => 'broken'], []],
            [$this->slice([$this->state('other', 'text')]), ['kind' => 'putState', 'stateRuleCode' => 'other'], []],
            [$this->slice([$flag]), ['kind' => 'putState', 'stateRuleCode' => 'flagged', 'value' => 1], []],
            [$this->slice([$this->state('counted', 'number')]), ['kind' => 'putState', 'stateRuleCode' => 'counted', 'value' => 'x'], []],
            [$this->slice([$flag]), ['kind' => 'putState', 'stateRuleCode' => 'flagged', 'wound' => 1], []],
            [$this->slice([$flag]), ['kind' => 'putState', 'stateRuleCode' => ''], []],
            [$this->slice([$flag]), ['kind' => 'replaceSection', 'section' => 'states', 'value' => []], []],
        ];
        foreach ($cases as [$slice, $operation, $tombstones]) {
            if ($tombstones !== []) {
                $slice = new CharacterRuleSlice(1, 1, 'world', [], $tombstones);
            }

            try {
                $this->mutations($slice)->applyToDocument(1, 1, [], ['marker' => 'stay'], [$operation]);
                self::fail('invalid state must reject');
            } catch (CharacterInvalidException $exception) {
                self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
            }
        }

        try {
            $this->mutations($this->slice([$flag]))->applyToDocument(1, 1, [], ['states' => ['stateRuleCode' => 'flagged']], [
                ['kind' => 'putState', 'stateRuleCode' => 'flagged'],
            ]);
            self::fail('states must be a list');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет ревизии — отказ среза, документ не нужен.
     *
     * @return void
     */
    public function testMissingRevisionIsNotFound(): void
    {
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willThrowException(new CharacterNotFoundException());
        $mutations = new CharacterActualMutations(
            $this->createMock(ICharacters::class),
            $slices,
            $this->createMock(ICharacterSheets::class),
            new CharacterChoiceAssembler(),
            new CharacterSheetDocument(),
        );

        $this->expectException(CharacterNotFoundException::class);
        $mutations->applyToDocument(1, 1, [], [], [
            ['kind' => 'putState', 'stateRuleCode' => 'flagged'],
        ]);
    }

    /**
     * Порт с заданным срезом.
     *
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
            $this->createMock(ICharacterSheets::class),
            new CharacterChoiceAssembler(),
            new CharacterSheetDocument(),
        );
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
     * @param string $code Код фикстуры.
     * @param string $valueType value_type.
     *
     * @return CharacterResolvedRule Карточка state.
     */
    /**
     * @param array{base: int, size: int} $remainder Остаток.
     * @param int $quotient Частное.
     *
     * @return array{kind: string, remainder: array{base: int, size: int}, quotient: int} Операция.
     */
    private function split(array $remainder, int $quotient): array
    {
        return ['kind' => 'putDamageSplit', 'remainder' => $remainder, 'quotient' => $quotient];
    }

    /**
     * @param string $code Код фикстуры.
     * @param string $valueType value_type.
     * @param string $flag damage_remainder или damage_exhaustion.
     *
     * @return CharacterResolvedRule Карточка state.
     */
    private function marked(string $code, string $valueType, string $flag): CharacterResolvedRule
    {
        return $this->rule($code, 'state', ['value_type' => $valueType, $flag => true]);
    }

    private function state(string $code, string $valueType): CharacterResolvedRule
    {
        return $this->rule($code, 'state', ['value_type' => $valueType]);
    }

    /**
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Документ spec.
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
}
