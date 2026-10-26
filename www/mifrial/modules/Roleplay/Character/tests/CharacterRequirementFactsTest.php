<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterRequirementFacts;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет сборку фактов листа для требований.
 */
final class CharacterRequirementFactsTest extends TestCase
{
    /**
     * Уровни, характеристики, лимиты и признаки попадают в факты.
     *
     * @return void
     */
    public function testForModelCollectsNumbersAndTraits(): void
    {
        $slice = $this->slice([
            $this->rule('human', 'race', ['cost_os' => 0, 'characteristics' => [], 'abilities' => []], ['human']),
            $this->rule('swing', 'ability', ['type' => 'trait'], ['melee', 'combat']),
            $this->rule('mastery', 'ability', ['type' => 'trait', 'domain_ref' => 'weapon-family'], ['training']),
            $this->rule('sword', 'item', [
                'category' => 'weapon',
                'innate' => false,
                'special_rule_codes' => [],
                'proficiency_family_code' => 'sword',
                'weapon' => ['weapon_profiles' => [[
                    'type' => 'strike',
                    'distance' => [
                        'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                        'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                    ],
                    'accuracy' => ['base' => 3, 'size' => 0],
                ]]],
            ], ['blade']),
            $this->rule('mail', 'item', [
                'category' => 'armor',
                'innate' => false,
                'special_rule_codes' => [],
                'proficiency_family_code' => 'heavy',
            ], ['plate']),
        ]);
        $facts = (new CharacterRequirementFacts())->forModel(
            $slice,
            $this->choices([
                ['ruleCode' => 'swing', 'level' => 2],
                ['ruleCode' => 'mastery', 'level' => 3, 'domainCode' => 'sword'],
            ]),
            ['swing' => 2, 'mastery' => 3, 'derived' => 1],
            ['strength' => new DimensionalNumber(4, 1)],
            ['focus' => 5],
        );

        self::assertSame(['swing' => 2, 'mastery' => 3, 'derived' => 1], $facts->numbers->levels);
        self::assertSame(4, $facts->numbers->characteristics['strength']->getBase());
        self::assertSame(['focus' => 5], $facts->numbers->resourceLimits);
        self::assertSame(['melee', 'combat'], $facts->traits->abilityKeywords['swing']);
        self::assertContains('human', $facts->traits->keywords);
        self::assertContains('melee', $facts->traits->keywords);
        self::assertNotContains('blade', $facts->traits->keywords);
        self::assertSame(['blade'], $facts->traits->weaponFamiliesByKeyword['sword']);
        self::assertArrayNotHasKey('heavy', $facts->traits->weaponFamiliesByKeyword);
        self::assertSame(['sword' => 3], $facts->traits->weaponMasteryLevels);
        self::assertNull($facts->unmodelled->speed);
        self::assertNull($facts->unmodelled->magicPaths);
        self::assertNull($facts->unmodelled->magicPathExperience);
    }

    /**
     * withLevels меняет только уровни.
     *
     * @return void
     */
    public function testWithLevelsKeepsOtherFacts(): void
    {
        $slice = $this->slice([
            $this->rule('swing', 'ability', ['type' => 'trait'], ['melee']),
        ]);
        $facts = (new CharacterRequirementFacts())->forModel(
            $slice,
            $this->choices([['ruleCode' => 'swing', 'level' => 1]]),
            ['swing' => 1],
            [],
            [],
        );
        $changed = $facts->withLevels(['swing' => 0]);

        self::assertSame(['swing' => 0], $changed->numbers->levels);
        self::assertSame(['melee'], $changed->traits->abilityKeywords['swing']);
        self::assertSame($facts->traits->keywords, $changed->traits->keywords);
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
     * Правило среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Документ.
     * @param array<int, string> $keywords Признаки.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec, array $keywords = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(1, 1, $code, true, $type, 'Name', '', $spec, [], [], 'needs_work', '', DateTime::now()),
            $keywords,
        );
    }
}
