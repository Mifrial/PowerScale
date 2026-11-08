<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterPathStudy;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterRequirementGate;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterStudyPaths;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

final class CharacterPathStudyTest extends TestCase
{
    /**
     * Грант пути открывает изучение карточки на этом пути.
     *
     * @return void
     */
    public function testGrantOpensStudyOnPath(): void
    {
        $problems = $this->study(
            [
                $this->rule('arcanist', 'magic_path', []),
                $this->rule('opener', 'ability', ['grants' => [$this->studyGrant(['arcanist'], 2)]]),
                $this->studyCard('discharge', 1),
            ],
            [
                $this->ability('opener', 1),
                $this->ability('discharge', 1, 'arcanist'),
            ],
        );

        self::assertSame([], $this->codes($problems));
    }

    /**
     * Без гранта изучения карточка не покупается.
     *
     * @return void
     */
    public function testWithoutGrantStudyIsRejected(): void
    {
        $problems = $this->study(
            [
                $this->rule('arcanist', 'magic_path', []),
                $this->studyCard('discharge', 1),
            ],
            [$this->ability('discharge', 1, 'arcanist')],
        );

        self::assertSame(['CHARACTER_STUDY'], $this->codes($problems));
    }

    /**
     * Цена первого уровня выше потолка гранта — отказ.
     *
     * @return void
     */
    public function testCostAboveGrantLimitIsRejected(): void
    {
        $problems = $this->study(
            [
                $this->rule('arcanist', 'magic_path', []),
                $this->rule('opener', 'ability', ['grants' => [$this->studyGrant(['arcanist'], 1)]]),
                $this->studyCard('discharge', 2),
            ],
            [
                $this->ability('opener', 1),
                $this->ability('discharge', 1, 'arcanist'),
            ],
        );

        self::assertSame(['CHARACTER_STUDY'], $this->codes($problems));
    }

    /**
     * Карточка не открывает изучение сама себе.
     *
     * @return void
     */
    public function testOwnGrantDoesNotAuthorizeCard(): void
    {
        $spec = $this->studyGrant(['arcanist'], 4);
        $spec['study'] = ['paths' => ['arcanist']];
        $spec['domain_ref'] = 'magic-path';
        $problems = $this->study(
            [
                $this->rule('arcanist', 'magic_path', []),
                $this->rule('advanced', 'ability', $spec + ['zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]]]),
            ],
            [$this->ability('advanced', 1, 'arcanist')],
        );

        self::assertSame(['CHARACTER_STUDY'], $this->codes($problems));
    }

    /**
     * Лимит экземпляров гранта считается по изученным карточкам на пути.
     *
     * @return void
     */
    public function testMaxInstancesRejectsSecondCard(): void
    {
        $problems = $this->study(
            [
                $this->rule('psionic', 'magic_path', []),
                $this->rule('awakening', 'ability', ['grants' => [$this->studyGrant(['psionic'], 1, 1)]]),
                $this->studyCard('bolt', 1),
                $this->studyCard('spark', 1),
            ],
            [
                $this->ability('awakening', 1),
                $this->ability('bolt', 1, 'psionic'),
                $this->ability('spark', 1, 'psionic'),
            ],
        );

        self::assertSame(['CHARACTER_STUDY'], $this->codes($problems));
        self::assertSame('abilities.2', $problems[0]->getPath());
    }

    /**
     * Изучение без magic_path разрешено, если грант его даёт.
     *
     * @return void
     */
    public function testStudyWithoutGrantedPathIsAllowed(): void
    {
        $problems = $this->study(
            [
                $this->rule('psionic', 'magic_path', []),
                $this->rule('awakening', 'ability', ['grants' => [$this->studyGrant(['psionic'], 1, 1)]]),
                $this->studyCard('bolt', 1),
            ],
            [
                $this->ability('awakening', 1),
                $this->ability('bolt', 1, 'psionic'),
            ],
        );

        self::assertSame([], $this->codes($problems));
    }

    /**
     * Прежний path_code читается как один путь.
     *
     * @return void
     */
    public function testLegacyPathCodeStillOpensStudy(): void
    {
        $problems = $this->study(
            [
                $this->rule('arcanist', 'magic_path', []),
                $this->rule('opener', 'ability', ['grants' => [[
                    'level' => 1,
                    'grants' => [[
                        'type' => 'magic_study',
                        'scope' => 'spell',
                        'max_cost' => 2,
                        'path_code' => 'arcanist',
                    ]],
                ]]]),
                $this->studyCard('discharge', 1),
            ],
            [
                $this->ability('opener', 1),
                $this->ability('discharge', 1, 'arcanist'),
            ],
        );

        self::assertSame([], $this->codes($problems));
    }

    /**
     * Опыт пути включает изученное на включённых путях.
     *
     * @return void
     */
    public function testExperienceIncludesIncludedPaths(): void
    {
        $slice = $this->slice([
            $this->rule('shaman', 'magic_path', ['includes_path_codes' => ['psionic']]),
            $this->rule('psionic', 'magic_path', []),
            $this->studyCard('bolt', [1, 2]),
        ]);
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('bolt', 2, 'psionic'),
        ], [], [], [], true);
        $experience = (new CharacterStudyPaths())->experience($slice, $choices, ['bolt' => 2]);

        self::assertSame(3, $experience['psionic']);
        self::assertSame(3, $experience['shaman']);
    }

    /**
     * Опыт пути считает и карточки с признаком пути, а не только с доменом.
     *
     * @return void
     */
    public function testExperienceCountsPathKeywordCards(): void
    {
        $slice = $this->slice([
            $this->rule('arcanist', 'magic_path', []),
            $this->rule('becoming-arcanist', 'ability', [
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
            ], ['arcanist']),
            $this->studyCard('discharge', 1),
        ]);
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('becoming-arcanist', 1),
            $this->ability('discharge', 1, 'arcanist'),
        ], [], [], [], true);
        $experience = (new CharacterStudyPaths())->experience(
            $slice,
            $choices,
            ['becoming-arcanist' => 1, 'discharge' => 1],
        );

        self::assertSame(3, $experience['arcanist']);
    }

    /**
     * Карточка с доменом и признаком пути не считается дважды.
     *
     * @return void
     */
    public function testExperienceDoesNotDoubleCountOneCard(): void
    {
        $slice = $this->slice([
            $this->rule('arcanist', 'magic_path', []),
            $this->studyCard('discharge', 2, ['arcanist']),
        ]);
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('discharge', 1, 'arcanist'),
        ], [], [], [], true);
        $experience = (new CharacterStudyPaths())->experience($slice, $choices, ['discharge' => 1]);

        self::assertSame(2, $experience['arcanist']);
    }

    /**
     * Новая покупка без выполненных требований отклоняется, изученная — нет.
     *
     * @return void
     */
    public function testRequirementGateOnlyRejectsNewPurchase(): void
    {
        $gate = new CharacterRequirementGate();
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('mage', 1),
        ], [], [], [], true);
        $sheet = ['abilityLevels' => ['mage' => 0], 'characteristics' => []];

        self::assertSame(['CHARACTER_REQUIREMENT'], $this->codes($gate->problems($choices, $sheet, [])));
        self::assertSame([], $gate->problems($choices, $sheet, [['code' => 'mage', 'level' => 1, 'domainCode' => '']]));
    }

    /**
     * Смена пути у уже купленной карточки — новая покупка.
     *
     * @return void
     */
    public function testChangedPathIsNotExempt(): void
    {
        $slice = $this->slice([
            $this->rule('arcanist', 'magic_path', []),
            $this->rule('psionic', 'magic_path', []),
            $this->studyCard('discharge', 1),
        ]);
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('discharge', 1, 'arcanist'),
        ], [], [], [], true);
        $problems = (new CharacterPathStudy())->problems(
            $slice,
            $choices,
            ['abilityLevels' => ['discharge' => 1], 'characteristics' => []],
            [['code' => 'discharge', 'level' => 1, 'domainCode' => 'psionic']],
        );

        self::assertSame(['CHARACTER_STUDY'], $this->codes($problems));
    }

    /**
     * Прогон гейта.
     *
     * @param array<int, CharacterResolvedRule> $rules Правила.
     * @param array<int, CharacterAbilityChoice> $abilities Выборы.
     *
     * @return array<int, CharacterProblem> Отказы.
     */
    private function study(array $rules, array $abilities): array
    {
        $slice = $this->slice($rules);
        $choices = new CharacterChoices('Имя', 'human', $abilities, [], [], [], true);
        $levels = [];
        foreach ($abilities as $ability) {
            $levels[$ability->getRuleCode()] = $ability->getLevel();
        }

        return (new CharacterPathStudy())->problems(
            $slice,
            $choices,
            ['abilityLevels' => $levels, 'characteristics' => []],
            [],
        );
    }

    /**
     * Срез ревизии.
     *
     * @param array<int, CharacterResolvedRule> $rules Правила.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $rules): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 46, 'world', $rules, []);
    }

    /**
     * Карточка с правилом study.
     *
     * @param string $code Код.
     * @param int|array<int, int> $cost Цена первого уровня или лестница цен.
     * @param array<int, string> $keywords Признаки.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function studyCard(string $code, int|array $cost, array $keywords = []): CharacterResolvedRule
    {
        return $this->rule($code, 'ability', [
            'domain_ref' => 'magic-path',
            'study' => ['paths' => 'any'],
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => is_int($cost) ? [$cost] : $cost]],
        ], $keywords);
    }

    /**
     * Грант изучения.
     *
     * @param array<int, string> $paths Пути.
     * @param int $maxCost Потолок.
     * @param int|null $maxInstances Лимит.
     *
     * @return array<string, mixed> Блок грантов.
     */
    private function studyGrant(array $paths, int $maxCost, ?int $maxInstances = null): array
    {
        $grant = ['type' => 'magic_study', 'paths' => $paths, 'max_cost' => $maxCost];
        if ($maxInstances !== null) {
            $grant['max_instances'] = $maxInstances;
        }

        return ['level' => 1, 'grants' => [$grant]];
    }

    /**
     * Выбор способности.
     *
     * @param string $code Код.
     * @param int $level Уровень.
     * @param string $domainCode Путь.
     *
     * @return CharacterAbilityChoice Выбор.
     */
    private function ability(string $code, int $level, string $domainCode = ''): CharacterAbilityChoice
    {
        return new CharacterAbilityChoice($code, $level, '', $domainCode, null, null, null);
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
            new RuleVersionRecord(10, 1, $code, true, $type, $code, '', $spec, [], [], 'needs_work', '', DateTime::now()),
            $keywords,
        );
    }

    /**
     * Коды отказов.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     *
     * @return array<int, string> Коды.
     */
    private function codes(array $problems): array
    {
        return array_map(static fn (CharacterProblem $problem): string => $problem->getCode(), $problems);
    }
}
