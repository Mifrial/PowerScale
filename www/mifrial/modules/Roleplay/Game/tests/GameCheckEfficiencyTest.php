<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Game\Service\GameCheckChain;
use Mifrial\Roleplay\Game\Service\GameCheckEfficiency;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use PHPUnit\Framework\TestCase;

/**
 * Эффективность проверки из грантов check_efficiency.
 */
final class GameCheckEfficiencyTest extends TestCase
{
    /**
     * Без грантов эффективность нейтральна.
     *
     * @return void
     */
    public function testNeutralWithoutGrants(): void
    {
        $result = $this->service()->resolve($this->slice(), $this->choices(), [], 'cast-check');

        self::assertSame(['base' => 3, 'size' => 0], $this->pair($result->getEfficiency()));
        self::assertFalse($result->isExplicit());
        self::assertSame([], $result->getAdjustments());
    }

    /**
     * Один грант поднимает эффективность и даёт вклад источника.
     *
     * @return void
     */
    public function testSingleGrant(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', true, ['cast-check']),
        ]);

        $result = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 1], 'cast-check');

        self::assertSame(['base' => 4, 'size' => 0], $this->pair($result->getEfficiency()));
        self::assertTrue($result->isExplicit());
        self::assertSame([[
            'source_code' => 'mastery',
            'source_label' => 'Skill-one',
            'delta' => 1,
        ]], $result->getAdjustments());
    }

    /**
     * Два гранта одного источника не складываются: остаётся сильнейший.
     *
     * @return void
     */
    public function testSameSourceKeepsStrongest(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', true, ['cast-check']),
            $this->ability('skill-two', 2, 'mastery', true, ['cast-check']),
        ]);

        $result = $this->service()->resolve(
            $slice,
            $this->choices(),
            ['skill-one' => 1, 'skill-two' => 1],
            'cast-check',
        );

        self::assertSame(['base' => 5, 'size' => 0], $this->pair($result->getEfficiency()));
        self::assertSame([[
            'source_code' => 'mastery',
            'source_label' => 'Skill-two',
            'delta' => 2,
        ]], $result->getAdjustments());
    }

    /**
     * Разные источники складываются.
     *
     * @return void
     */
    public function testDifferentSourcesSum(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', true, ['cast-check']),
            $this->ability('skill-two', 1, 'talent', true, ['cast-check']),
        ]);

        $result = $this->service()->resolve(
            $slice,
            $this->choices(),
            ['skill-one' => 1, 'skill-two' => 1],
            'cast-check',
        );

        self::assertSame(['base' => 5, 'size' => 0], $this->pair($result->getEfficiency()));
        self::assertCount(2, $result->getAdjustments());
    }

    /**
     * Чужой код проверки не применяется.
     *
     * @return void
     */
    public function testForeignCheckCodeIsIgnored(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', true, ['other-check']),
        ]);

        $result = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 1], 'cast-check');

        self::assertSame(['base' => 3, 'size' => 0], $this->pair($result->getEfficiency()));
        self::assertSame([], $result->getAdjustments());
    }

    /**
     * Непостоянный грант действует только на своём уровне.
     *
     * @return void
     */
    public function testNonPermanentGrantIsLevelBound(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', false, ['cast-check']),
        ]);

        $first = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 1], 'cast-check');
        $second = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 2], 'cast-check');

        self::assertSame(['base' => 4, 'size' => 0], $this->pair($first->getEfficiency()));
        self::assertSame(['base' => 3, 'size' => 0], $this->pair($second->getEfficiency()));
    }

    /**
     * Постоянный грант действует и на следующем уровне.
     *
     * @return void
     */
    public function testPermanentGrantSurvivesLevel(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', true, ['cast-check']),
        ]);

        $result = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 2], 'cast-check');

        self::assertSame(['base' => 4, 'size' => 0], $this->pair($result->getEfficiency()));
    }

    /**
     * Размер эффективности переходит на следующую базу.
     *
     * @return void
     */
    public function testSizeMovesAfterFive(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 3, 'mastery', true, ['cast-check']),
        ]);

        $result = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 1], 'cast-check');

        self::assertSame(['base' => 3, 'size' => 1], $this->pair($result->getEfficiency()));
        self::assertTrue($result->isExplicit());
    }

    /**
     * Явная база проверки сохраняется.
     *
     * @return void
     */
    public function testDefaultEfficiencyIsExplicit(): void
    {
        $slice = $this->slice([], 4);

        $result = $this->service()->resolve($slice, $this->choices(), [], 'cast-check');

        self::assertSame(['base' => 4, 'size' => 0], $this->pair($result->getEfficiency()));
        self::assertTrue($result->isExplicit());
    }

    /**
     * Без source_code источником становится донор.
     *
     * @return void
     */
    public function testMissingSourceFallsBackToDonor(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, null, true, ['cast-check']),
        ]);

        $result = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 1], 'cast-check');

        self::assertSame([[
            'source_code' => 'skill-one',
            'source_label' => 'Skill-one',
            'delta' => 1,
        ]], $result->getAdjustments());
    }

    /**
     * Синтетический код проверки работает так же, как контентный.
     *
     * @return void
     */
    public function testSyntheticCheckCodeWorks(): void
    {
        $slice = $this->slice([
            $this->ability('skill-one', 1, 'mastery', true, ['synthetic-check']),
        ], null, 'synthetic-check');

        $result = $this->service()->resolve($slice, $this->choices(), ['skill-one' => 1], 'synthetic-check');

        self::assertSame(['base' => 4, 'size' => 0], $this->pair($result->getEfficiency()));
    }

    /**
     * Строит сервис.
     *
     * @return GameCheckEfficiency Сервис.
     */
    private function service(): GameCheckEfficiency
    {
        return new GameCheckEfficiency(
            new CharacterAbilityGrantReader(new CharacterDonorGrants(), new CharacterRaceAbilityCodes()),
            new GameCheckChain(),
        );
    }

    /**
     * Пара для сравнения.
     *
     * @param CharacteristicNumber $value Значение.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pair(CharacteristicNumber $value): array
    {
        return ['base' => $value->getBase(), 'size' => $value->getSize()];
    }

    /**
     * Пустые выборы.
     *
     * @return CharacterChoices Выборы.
     */
    private function choices(): CharacterChoices
    {
        return new CharacterChoices('Hero', null, [], [], [], [], true);
    }

    /**
     * Срез с проверкой и способностями.
     *
     * @param array<int, CharacterResolvedRule> $abilities Способности.
     * @param int|null $defaultEfficiency База проверки.
     * @param string $checkCode Код проверки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(
        array $abilities = [],
        ?int $defaultEfficiency = null,
        string $checkCode = 'cast-check',
    ): CharacterRuleSlice {
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'difficulty_input' => ['kind' => 'none'],
        ];
        if ($defaultEfficiency !== null) {
            $check['default_efficiency'] = $defaultEfficiency;
        }

        return new CharacterRuleSlice(
            1,
            1,
            'world',
            [
                new CharacterResolvedRule($this->record('check', $checkCode, 'Cast', $check), []),
                ...$abilities,
            ],
            [],
        );
    }

    /**
     * Способность с грантом эффективности.
     *
     * @param string $code Код.
     * @param int $amount Величина.
     * @param string|null $source Источник.
     * @param bool $permanent Постоянный.
     * @param array<int, string> $checks Коды проверок.
     *
     * @return CharacterResolvedRule Способность.
     */
    private function ability(
        string $code,
        int $amount,
        ?string $source,
        bool $permanent,
        array $checks,
    ): CharacterResolvedRule {
        $grant = [
            'type' => 'check_efficiency',
            'amount' => $amount,
            'check_codes' => $checks,
            'permanent' => $permanent,
        ];
        if ($source !== null) {
            $grant['source_code'] = $source;
        }

        return new CharacterResolvedRule($this->record('ability', $code, ucfirst($code), [
            'type' => 'skill',
            'zones' => ['or' => ['kind' => 'automatic']],
            'grants' => [[
                'level' => 1,
                'grants' => [$grant],
            ]],
        ]), []);
    }

    /**
     * Живая версия правила.
     *
     * @param string $type Тип.
     * @param string $code Код.
     * @param string $name Имя.
     * @param array<string, mixed> $spec Spec.
     *
     * @return RuleVersionRecord Версия.
     */
    private function record(string $type, string $code, string $name, array $spec): RuleVersionRecord
    {
        return new RuleVersionRecord(
            1,
            1,
            $code,
            true,
            $type,
            $name,
            '',
            $spec,
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
    }
}
