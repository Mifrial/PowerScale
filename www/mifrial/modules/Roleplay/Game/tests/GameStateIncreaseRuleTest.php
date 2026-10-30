<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStateIncreaseRule;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Связка «состояние -> проверка -> исход» из спеки.
 */
final class GameStateIncreaseRuleTest extends TestCase
{
    /**
     * Сложность берётся из состояния, исход — из карточки проверки.
     *
     * @return void
     */
    public function testResolvesCheckAndOutcome(): void
    {
        $slice = $this->slice();
        $rule = new GameStateIncreaseRule();
        $check = $rule->check($slice, 'unstable', 'check-unstable');

        self::assertSame('check', $check->getRuleType());
        self::assertSame('from_state', $check->getDifficulty()->getKind());
        self::assertSame('unstable', $check->getDifficulty()->getStateCode());
        self::assertSame('lying', $rule->outcome($slice, $check, 0));
        self::assertSame('lying', $rule->outcome($slice, $check, -2));
        self::assertNull($rule->outcome($slice, $check, 1));
    }

    /**
     * Исход без состояния ничего не пишет.
     *
     * @return void
     */
    public function testOutcomeWithoutStateIsNull(): void
    {
        $slice = $this->slice([
            ['code' => 'check-unstable', 'type' => 'check', 'spec' => [
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
                'outcomes' => [['max_rating' => 0]],
            ]],
        ]);

        $rule = new GameStateIncreaseRule();
        $check = $rule->check($slice, 'unstable', 'check-unstable');

        self::assertNull($rule->outcome($slice, $check, 0));
    }

    /**
     * Нет карточки проверки — отказ.
     *
     * @return void
     */
    public function testRejectsUnknownCheck(): void
    {
        $slice = $this->slice([
            ['code' => 'unstable', 'type' => 'state', 'spec' => [
                'value_type' => 'number',
                'aggregation' => 'sum',
                'increase_check_code' => 'missing',
            ]],
        ]);

        $this->expectException(GameInvalidException::class);
        (new GameStateIncreaseRule())->check($slice, 'unstable', 'missing');
    }

    /**
     * Сложность не из этого состояния — отказ.
     *
     * @return void
     */
    public function testRejectsForeignDifficulty(): void
    {
        $slice = $this->slice([
            ['code' => 'check-unstable', 'type' => 'check', 'spec' => [
                'difficulty_input' => ['kind' => 'ask'],
            ]],
        ]);

        $this->expectException(GameInvalidException::class);
        (new GameStateIncreaseRule())->check($slice, 'unstable', 'check-unstable');
    }

    /**
     * Флаг не может носить проверку прироста.
     *
     * @return void
     */
    public function testRejectsNonNumberState(): void
    {
        $slice = $this->slice([
            ['code' => 'unstable', 'type' => 'state', 'spec' => [
                'value_type' => 'flag',
                'aggregation' => 'sum',
                'increase_check_code' => 'check-unstable',
            ]],
        ]);

        $this->expectException(GameInvalidException::class);
        (new GameStateIncreaseRule())->check($slice, 'unstable', 'check-unstable');
    }

    /**
     * Проверка прироста требует суммируемого числа.
     *
     * @return void
     */
    public function testRejectsNonSumState(): void
    {
        $slice = $this->slice([
            ['code' => 'unstable', 'type' => 'state', 'spec' => [
                'value_type' => 'number',
                'aggregation' => 'max',
                'increase_check_code' => 'check-unstable',
            ]],
        ]);

        $this->expectException(GameInvalidException::class);
        (new GameStateIncreaseRule())->check($slice, 'unstable', 'check-unstable');
    }

    /**
     * Состояние исхода должно быть живым.
     *
     * @return void
     */
    public function testRejectsMissingOutcomeState(): void
    {
        $slice = $this->slice([
            ['code' => 'check-unstable', 'type' => 'check', 'spec' => [
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
                'outcomes' => [['max_rating' => 0, 'state_rule_code' => 'missing']],
            ]],
        ]);
        $rule = new GameStateIncreaseRule();
        $check = $rule->check($slice, 'unstable', 'check-unstable');

        $this->expectException(GameInvalidException::class);
        $rule->outcome($slice, $check, 0);
    }

    /**
     * Срез по умолчанию и переопределения.
     *
     * @param array<int, array{code: string, type: string, spec: array<string, mixed>}> $overrides Замены.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $overrides = []): CharacterRuleSlice
    {
        $rules = [
            ['code' => 'unstable', 'type' => 'state', 'spec' => [
                'value_type' => 'number',
                'aggregation' => 'sum',
                'unstable' => true,
                'increase_check_code' => 'check-unstable',
            ]],
            ['code' => 'lying', 'type' => 'state', 'spec' => [
                'value_type' => 'flag',
                'aggregation' => 'max',
                'lying' => true,
            ]],
            ['code' => 'check-unstable', 'type' => 'check', 'spec' => [
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
                'outcomes' => [['max_rating' => 0, 'state_rule_code' => 'lying']],
            ]],
        ];
        foreach ($overrides as $override) {
            $rules = array_values(array_filter(
                $rules,
                static fn (array $rule): bool => $rule['code'] !== $override['code'],
            ));
            $rules[] = $override;
        }

        $live = [];
        foreach ($rules as $rule) {
            $live[] = $this->rule($rule['code'], $rule['type'], $rule['spec']);
        }

        return new CharacterRuleSlice(1, 1, 'world', $live, []);
    }

    /**
     * Запись правила.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $document Спека.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $document): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                'Name',
                '',
                $document,
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
    }
}
