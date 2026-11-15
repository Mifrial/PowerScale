<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterActiveInput;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterInputChecks;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверки входа: дар без ссылки расы отвергается.
 */
final class CharacterInputChecksTest extends TestCase
{
    /**
     * Дар без ссылки расы — отказ `CHARACTER_ABILITY_RACE`.
     *
     * @return void
     */
    public function testGiftWithoutRaceReferenceIsRejected(): void
    {
        $problems = $this->check($this->slice(false));

        self::assertContains(
            'CHARACTER_ABILITY_RACE',
            array_map(static fn ($problem): string => $problem->getCode(), $problems->all()),
        );
    }

    /**
     * Дар со ссылкой расы проходит.
     *
     * @return void
     */
    public function testGiftReferencedByRaceIsAccepted(): void
    {
        $problems = $this->check($this->slice(true));

        self::assertSame([], $problems->all());
    }

    /**
     * Прогоняет проверки на выборе с ядром.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return CharacterProblemList Отказы.
     */
    private function check(CharacterRuleSlice $slice): CharacterProblemList
    {
        $problems = new CharacterProblemList();
        $choices = new CharacterChoices(
            'Колдунец',
            'kogir',
            [new CharacterAbilityChoice('magic-core-capacity', 1, '', '', null, null, null)],
            [],
            [],
            [],
            null,
        );
        (new CharacterInputChecks(new CharacterActiveInput()))->check($slice, $choices, $problems);

        return $problems;
    }

    /**
     * Срез с расой и даром.
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
                'zones' => ['os' => ['kind' => 'array', 'levels_cost' => [2]]],
                'requirements' => [],
                'grants' => [],
                'parent_ability_code' => null,
            ], ['gift']),
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
