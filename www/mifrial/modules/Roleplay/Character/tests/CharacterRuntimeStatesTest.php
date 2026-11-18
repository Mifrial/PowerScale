<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterRuntimeStates;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Слияние временных состояний цели с хранимыми строками листа.
 */
final class CharacterRuntimeStatesTest extends TestCase
{
    /**
     * Хранимый флаг не получает вторую строку от эффекта.
     *
     * @return void
     */
    public function testStoredFlagIsNotDoubled(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'weak', 'id' => 3]]];

        $merged = $this->merge($sheet, [$this->runtime('weak')]);

        self::assertSame([['stateRuleCode' => 'weak', 'id' => 3]], $merged['states']);
    }

    /**
     * Временный флаг без хранимой строки остаётся временным.
     *
     * @return void
     */
    public function testRuntimeFlagWithoutStoredStaysRuntime(): void
    {
        $merged = $this->merge(['states' => []], [$this->runtime('weak')]);

        self::assertCount(1, $merged['states']);
        self::assertSame('weak', $merged['states'][0]['stateRuleCode']);
        self::assertTrue($merged['states'][0]['runtime']);
        self::assertArrayNotHasKey('id', $merged['states'][0]);
    }

    /**
     * Independent-флаг тоже схлопывается: флаг не имеет величины.
     *
     * @return void
     */
    public function testIndependentFlagCollapses(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'clumsy', 'id' => 5]]];

        $merged = $this->merge($sheet, [$this->runtime('clumsy')]);

        self::assertSame([['stateRuleCode' => 'clumsy', 'id' => 5]], $merged['states']);
    }

    /**
     * Старший ранг упадка остаётся один, слабость не добавляется.
     *
     * @return void
     */
    public function testStrongerStoredDeclineWins(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'disabled', 'id' => 2]]];

        $merged = $this->merge($sheet, [$this->runtime('weak')]);

        self::assertSame([['stateRuleCode' => 'disabled', 'id' => 2]], $merged['states']);
    }

    /**
     * Бессознательность не заменяется слабостью.
     *
     * @return void
     */
    public function testUnconsciousWinsOverRuntimeWeakness(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'unconscious', 'id' => 4]]];

        $merged = $this->merge($sheet, [$this->runtime('weak')]);

        self::assertSame([['stateRuleCode' => 'unconscious', 'id' => 4]], $merged['states']);
    }

    /**
     * Sum-состояние складывает значения в одну строку.
     *
     * @return void
     */
    public function testSumRowsAddValues(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'exhaustion', 'value' => 3, 'id' => 1]]];

        $merged = $this->merge($sheet, [$this->runtime('exhaustion', 2)]);

        self::assertSame([['stateRuleCode' => 'exhaustion', 'value' => 5, 'id' => 1]], $merged['states']);
    }

    /**
     * Max-состояние берёт большее значение.
     *
     * @return void
     */
    public function testMaxRowsKeepBiggerValue(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'doubt', 'value' => 2, 'id' => 2]]];

        $bigger = $this->merge($sheet, [$this->runtime('doubt', 4)]);
        $smaller = $this->merge($sheet, [$this->runtime('doubt', 1)]);

        self::assertSame([['stateRuleCode' => 'doubt', 'value' => 4, 'id' => 2]], $bigger['states']);
        self::assertSame([['stateRuleCode' => 'doubt', 'value' => 2, 'id' => 2]], $smaller['states']);
    }

    /**
     * Independent-значения остаются отдельными строками.
     *
     * @return void
     */
    public function testIndependentNumbersStaySeparate(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'wound', 'value' => 1, 'id' => 1]]];

        $merged = $this->merge($sheet, [$this->runtime('wound', 2)]);

        self::assertCount(2, $merged['states']);
        self::assertSame(1, $merged['states'][0]['value']);
        self::assertSame(2, $merged['states'][1]['value']);
    }

    /**
     * Временное состояние снимает подавленную хранимую строку.
     *
     * @return void
     */
    public function testSuppressedStoredRowIsDropped(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'unstable', 'id' => 4]]];

        $merged = $this->merge($sheet, [$this->runtime('lying')]);

        self::assertCount(1, $merged['states']);
        self::assertSame('lying', $merged['states'][0]['stateRuleCode']);
        self::assertTrue($merged['states'][0]['runtime']);
    }

    /**
     * Хранимое состояние подавляет временную строку.
     *
     * @return void
     */
    public function testStoredStateSuppressesRuntimeRow(): void
    {
        $sheet = ['states' => [['stateRuleCode' => 'lying', 'id' => 2]]];

        $merged = $this->merge($sheet, [$this->runtime('unstable')]);

        self::assertSame([['stateRuleCode' => 'lying', 'id' => 2]], $merged['states']);
    }

    /**
     * Тот же runtimeId не добавляется второй раз.
     *
     * @return void
     */
    public function testDuplicateRuntimeIdIsNotAddedTwice(): void
    {
        $row = $this->runtime('weak');
        $sheet = ['states' => [$row]];

        $merged = $this->merge($sheet, [$row]);

        self::assertSame([$row], $merged['states']);
    }

    /**
     * Без временных строк лист не меняется.
     *
     * @return void
     */
    public function testSheetWithoutRuntimeRowsIsUntouched(): void
    {
        $sheet = ['states' => [
            ['stateRuleCode' => 'weak', 'id' => 3],
            ['stateRuleCode' => 'weak', 'id' => 4],
        ]];

        self::assertSame($sheet, $this->merge($sheet, []));
    }

    /**
     * Срез состояний.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('weak', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
                'decline_weakness' => true,
                'effects' => [[
                    'type' => 'resource_limit_modify',
                    'resource_code' => 'action-points',
                    'amount' => -1,
                ]],
            ]),
            $this->rule('disabled', [
                'value_type' => 'flag',
                'aggregation' => 'max',
                'decline_disabled' => true,
            ]),
            $this->rule('unconscious', [
                'value_type' => 'flag',
                'aggregation' => 'max',
                'decline_unconscious' => true,
            ]),
            $this->rule('clumsy', ['value_type' => 'flag', 'aggregation' => 'independent']),
            $this->rule('exhaustion', ['value_type' => 'number', 'aggregation' => 'sum']),
            $this->rule('doubt', ['value_type' => 'number', 'aggregation' => 'max']),
            $this->rule('wound', ['value_type' => 'number', 'aggregation' => 'independent']),
            $this->rule('lying', ['value_type' => 'flag', 'suppressed_state_codes' => ['unstable']]),
            $this->rule('unstable', ['value_type' => 'flag']),
        ], []);
    }

    /**
     * Лист с временными состояниями.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, array<string, mixed>> $runtime Временные строки.
     *
     * @return array<string, mixed> Лист.
     */
    private function merge(array $sheet, array $runtime): array
    {
        return (new CharacterRuntimeStates())->merge($this->slice(), $sheet, $runtime);
    }

    /**
     * Временная строка состояния.
     *
     * @param string $code Код.
     * @param int|null $value Значение.
     * @param string $effectKey Ключ эффекта.
     *
     * @return array<string, mixed> Строка.
     */
    private function runtime(string $code, ?int $value = null, string $effectKey = 'effect:1:'): array
    {
        $row = [
            'stateRuleCode' => $code,
            'value' => $value ?? 1,
            'runtimeId' => $effectKey . $code,
            'runtime' => true,
        ];

        return $row;
    }

    /**
     * Карточка state.
     *
     * @param string $code Код.
     * @param array<string, mixed> $spec Документ spec.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, array $spec): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(1, 1, $code, true, 'state', 'Name', '', $spec, [], [], 'needs_work', '', DateTime::now()),
            [],
        );
    }
}
