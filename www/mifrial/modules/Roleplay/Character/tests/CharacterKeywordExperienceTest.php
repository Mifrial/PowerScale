<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterKeywordExperience;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет сумму опыта признака по активным способностям.
 */
final class CharacterKeywordExperienceTest extends TestCase
{
    /**
     * Считаются только карточки с признаком, уровни берутся из карты или экземпляра.
     *
     * @return void
     */
    public function testSumsCostsAndPrefersEffectiveLevels(): void
    {
        $slice = $this->slice([
            $this->ability('blade', [2, 3], ['section-melee']),
            $this->ability('axe', [1], ['section-melee']),
            $this->ability('spell', [4], ['electromancy']),
        ]);
        $choices = $this->choices([
            ['ruleCode' => 'blade', 'level' => 2],
            ['ruleCode' => 'axe', 'level' => 1],
            ['ruleCode' => 'spell', 'level' => 1],
        ]);
        $experience = new CharacterKeywordExperience();

        self::assertSame(6, $experience->experience($slice, $choices, 'section-melee'));
        self::assertSame(3, $experience->experience(
            $slice,
            $choices,
            'section-melee',
            ['blade' => 1, 'axe' => 1],
        ));
        self::assertSame(0, $experience->experience(
            $slice,
            $choices,
            'section-melee',
            ['blade' => 0, 'axe' => 0],
        ));
        self::assertSame(4, $experience->experience($slice, $choices, 'electromancy'));
        self::assertSame(0, $experience->experience($slice, $choices, 'missing'));
    }

    /**
     * Собирает выборы.
     *
     * @param array<int, array<string, mixed>> $abilities Способности.
     *
     * @return CharacterChoices Выборы.
     */
    private function choices(array $abilities): CharacterChoices
    {
        return (new CharacterChoiceAssembler())->assemble('Hero', 'human', $abilities, [], [], [], null);
    }

    /**
     * Срез.
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
     * Способность с ценой уровней.
     *
     * @param string $code Код.
     * @param array<int, int> $costs Цены уровней.
     * @param array<int, string> $keywords Признаки.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function ability(string $code, array $costs, array $keywords): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                'ability',
                'Name',
                '',
                ['type' => 'trait', 'zones' => ['or' => ['kind' => 'array', 'levels_cost' => $costs]]],
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
