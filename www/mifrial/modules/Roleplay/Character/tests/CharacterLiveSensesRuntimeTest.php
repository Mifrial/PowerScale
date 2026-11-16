<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterLiveSenses;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterGrantParameters;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use PHPUnit\Framework\TestCase;

/**
 * Временные чувства из игры в живом листе.
 */
final class CharacterLiveSensesRuntimeTest extends TestCase
{
    /**
     * Временное чувство появляется, складывается и участвует в лучшем бонусе.
     *
     * @return void
     */
    public function testRuntimeSenseIsMerged(): void
    {
        $senses = $this->senses();

        $rows = $senses->rows($this->slice(), $this->choices(), [], [], [
            [
                'ruleCode' => 'magic-sensation',
                'value' => -3,
                'status' => 'imprecise',
                'radius' => ['base' => 4, 'size' => -1],
                'sourceRuleCode' => 'magic-perception',
            ],
        ]);

        self::assertCount(1, $rows);
        self::assertSame('magic-sensation', $rows[0]['ruleCode']);
        self::assertSame(-3, $rows[0]['value']);
        self::assertSame('imprecise', $rows[0]['status']);
        self::assertSame(['base' => 4, 'size' => -1], $rows[0]['radius']);
        self::assertSame(
            [['sourceRuleCode' => 'magic-perception', 'delta' => -3]],
            $rows[0]['modifiers'],
        );
        self::assertSame(['source' => 'magic-sensation', 'delta' => -3], $senses->bestDelta($rows));
    }

    /**
     * Два источника одного чувства складываются.
     *
     * @return void
     */
    public function testRuntimeSensesOfOneCodeAreSummed(): void
    {
        $rows = $this->senses()->rows($this->slice(), $this->choices(), [], [], [
            ['ruleCode' => 'magic-sensation', 'value' => -3],
            ['ruleCode' => 'magic-sensation', 'value' => 1],
        ]);

        self::assertCount(1, $rows);
        self::assertSame(-2, $rows[0]['value']);
    }

    /**
     * Без временных строк чувства нет: правило не auto_add и грантов нет.
     *
     * @return void
     */
    public function testNoRuntimeSenseLeavesSenseAbsent(): void
    {
        self::assertSame([], $this->senses()->rows($this->slice(), $this->choices(), [], []));
    }

    /**
     * Неизвестный код чувства — отказ, а не молчаливый пропуск.
     *
     * @return void
     */
    public function testUnknownRuntimeSenseIsRejected(): void
    {
        $this->expectException(CharacterInvalidException::class);

        $this->senses()->rows($this->slice(), $this->choices(), [], [], [
            ['ruleCode' => 'unknown-sense', 'value' => 1],
        ]);
    }

    /**
     * Битая строка радиуса — отказ.
     *
     * @return void
     */
    public function testBrokenRuntimeRadiusIsRejected(): void
    {
        $this->expectException(CharacterInvalidException::class);

        $this->senses()->rows($this->slice(), $this->choices(), [], [], [
            ['ruleCode' => 'magic-sensation', 'value' => 1, 'radius' => ['base' => 1]],
        ]);
    }

    /**
     * Сервис чувств на пустых формулах.
     *
     * @return CharacterLiveSenses Сервис.
     */
    private function senses(): CharacterLiveSenses
    {
        return new CharacterLiveSenses(
            $this->createMock(IFormulaEvaluations::class),
            new CharacterAbilityGrantReader(new CharacterDonorGrants(), new CharacterRaceAbilityCodes()),
            new CharacterGrantParameters(new CharacterRaceAbilityCodes()),
        );
    }

    /**
     * Срез с одним чувством.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            new CharacterResolvedRule(
                new RuleVersionRecord(
                    10,
                    1,
                    'magic-sensation',
                    true,
                    'sense',
                    'Ощущение магии',
                    '',
                    ['type' => 'sense', 'status' => 'imprecise', 'radius' => ['base' => 0, 'size' => 0]],
                    [],
                    [],
                    'needs_work',
                    '',
                    DateTime::now(),
                ),
                [],
            ),
        ], []);
    }

    /**
     * Выборы без способностей.
     *
     * @return CharacterChoices Выборы.
     */
    private function choices(): CharacterChoices
    {
        return new CharacterChoices('Имя', 'human', [], [], [], [], true);
    }
}
