<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastNextCastSource;
use Mifrial\Roleplay\Game\Service\GameSpellCastNextCast;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Хвост next_spell_cast_difficulty: источники, peek, consume, queue.
 */
final class GameSpellCastNextCastTest extends TestCase
{
    /**
     * Изученные источники без повторов.
     *
     * @return void
     */
    public function testFindsSources(): void
    {
        $sources = $this->service()->sources(
            $this->slice([
                $this->rule('transfer', $this->nextCast(2, -1, 'training')),
                $this->rule('plain', ['type' => 'skill']),
            ]),
            $this->model([
                $this->ability('transfer', 1),
                $this->ability('transfer', 1),
                $this->ability('plain', 1),
                $this->ability('unlearned', 0),
            ]),
            ['transfer' => 1, 'plain' => 1, 'unlearned' => 0],
        );

        self::assertCount(1, $sources);
        self::assertSame('transfer', $sources[0]->getRuleCode());
        self::assertSame(2, $sources[0]->getMinRemainingRating());
        self::assertSame(-1, $sources[0]->getDelta());
        self::assertSame('training', $sources[0]->getSourceCode());
    }

    /**
     * Peek складывает только записи, проходящие по стоимости.
     *
     * @return void
     */
    public function testPeekSumsOnlyFittingTails(): void
    {
        $pending = [
            'character:1' => [
                $this->tail('a', -1, 5),
                $this->tail('b', -2, 2),
                $this->tail('c', -3, null),
            ],
        ];

        self::assertSame(-1, $this->service()->peek($pending, 'character:1', 4));
        self::assertSame(-3, $this->service()->peek($pending, 'character:1', 2));
    }

    /**
     * Без хвостов peek нейтрален.
     *
     * @return void
     */
    public function testPeekWithoutTails(): void
    {
        self::assertSame(0, $this->service()->peek(['character:1' => []], 'character:1', 5));
        self::assertSame(0, $this->service()->peek([], 'character:1', 5));
    }

    /**
     * Consume снимает все хвосты и сохраняет остальные записи.
     *
     * @return void
     */
    public function testConsumeRemovesOnlyNextCastTails(): void
    {
        $pending = [
            'character:1' => [
                $this->tail('a', -1, 5),
                ['sourceRuleCode' => 'other', 'effect' => ['type' => 'other_effect']],
                $this->tail('b', -2, 9),
            ],
            'npc:2' => [$this->tail('c', -1, 5)],
        ];

        $result = $this->service()->consume($pending, 'character:1');

        self::assertSame(
            [['sourceRuleCode' => 'other', 'effect' => ['type' => 'other_effect']]],
            $result['character:1'],
        );
        self::assertArrayHasKey('npc:2', $result);
    }

    /**
     * Consume убирает пустой ключ.
     *
     * @return void
     */
    public function testConsumeRemovesEmptyActor(): void
    {
        $result = $this->service()->consume(['character:1' => [$this->tail('a', -1, 5)]], 'character:1');

        self::assertArrayNotHasKey('character:1', $result);
    }

    /**
     * Queue ставит хвост только при достаточном остатке.
     *
     * @return void
     */
    public function testQueueChecksRemainingRating(): void
    {
        $sources = [$this->source('transfer', 2, -1, 'training')];

        $low = $this->service()->queue([], 'character:1', $sources, 5, 1);
        self::assertArrayNotHasKey('character:1', $low);

        $high = $this->service()->queue([], 'character:1', $sources, 5, 2);
        self::assertSame(
            [[
                'sourceRuleCode' => 'transfer',
                'effect' => [
                    'type' => 'next_spell_cast_difficulty',
                    'delta' => -1,
                    'max_total_action_cost' => 5,
                    'source_code' => 'training',
                ],
            ]],
            $high['character:1'],
        );
    }

    /**
     * Несколько источников дают несколько записей.
     *
     * @return void
     */
    public function testQueueAddsEachSource(): void
    {
        $sources = [
            $this->source('first', 2, -1, 'training'),
            $this->source('second', 1, -2, null),
        ];

        $result = $this->service()->queue([], 'character:1', $sources, 4, 3);

        self::assertCount(2, $result['character:1']);
        self::assertSame('first', $result['character:1'][0]['sourceRuleCode']);
        self::assertSame('second', $result['character:1'][1]['sourceRuleCode']);
        self::assertArrayNotHasKey('source_code', $result['character:1'][1]['effect']);
    }

    /**
     * Queue сохраняет чужие эффекты.
     *
     * @return void
     */
    public function testQueueKeepsOtherEffects(): void
    {
        $pending = [
            'character:1' => [['sourceRuleCode' => 'other', 'effect' => ['type' => 'other_effect']]],
        ];

        $result = $this->service()->queue(
            $pending,
            'character:1',
            [$this->source('transfer', 2, -1, 'training')],
            5,
            2,
        );

        self::assertCount(2, $result['character:1']);
        self::assertSame('other', $result['character:1'][0]['sourceRuleCode']);
        self::assertSame('transfer', $result['character:1'][1]['sourceRuleCode']);
    }

    /**
     * Сервис.
     *
     * @return GameSpellCastNextCast Сервис.
     */
    private function service(): GameSpellCastNextCast
    {
        return new GameSpellCastNextCast();
    }

    /**
     * Запись хвоста.
     *
     * @param string $code Код источника.
     * @param int $delta Сдвиг.
     * @param int|null $max Потолок ОД или null.
     *
     * @return array<string, mixed> Запись.
     */
    private function tail(string $code, int $delta, ?int $max): array
    {
        $effect = ['type' => 'next_spell_cast_difficulty', 'delta' => $delta];
        if ($max !== null) {
            $effect['max_total_action_cost'] = $max;
        }

        return ['sourceRuleCode' => $code, 'effect' => $effect];
    }

    /**
     * Источник для queue.
     *
     * @param string $code Код.
     * @param int $minRemainingRating Порог остатка.
     * @param int $delta Сдвиг.
     * @param string|null $sourceCode Источник.
     *
     * @return GameSpellCastNextCastSource Источник.
     */
    private function source(
        string $code,
        int $minRemainingRating,
        int $delta,
        ?string $sourceCode,
    ): GameSpellCastNextCastSource {
        $source = $this->service()->sources(
            $this->slice([$this->rule($code, $this->nextCast($minRemainingRating, $delta, $sourceCode))]),
            $this->model([$this->ability($code, 1)]),
            [$code => 1],
        );
        self::assertCount(1, $source);

        return $source[0];
    }

    /**
     * Срез.
     *
     * @param array<int, CharacterResolvedRule> $rules Карточки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $rules): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', $rules, []);
    }

    /**
     * Выборы.
     *
     * @param array<int, CharacterAbilityChoice> $abilities Способности.
     *
     * @return CharacterChoices Выборы.
     */
    private function model(array $abilities): CharacterChoices
    {
        return new CharacterChoices('Caster', null, $abilities, [], [], [], null);
    }

    /**
     * Карточка.
     *
     * @param string $code Код.
     * @param array<string, mixed> $spec Spec.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function rule(string $code, array $spec): CharacterResolvedRule
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
                $spec,
                [],
                [],
                'ready',
                '',
                DateTime::now(),
            ),
            [],
        );
    }

    /**
     * Форма снижения Сложности.
     *
     * @param int $minRemainingRating Порог остатка.
     * @param int $delta Сдвиг.
     * @param string|null $sourceCode Источник.
     *
     * @return array<string, mixed> Spec.
     */
    private function nextCast(int $minRemainingRating, int $delta, ?string $sourceCode): array
    {
        return [
            'type' => 'skill',
            'next_cast_difficulty' => [
                'min_remaining_rating' => $minRemainingRating,
                'delta' => $delta,
                'source_code' => $sourceCode,
            ],
        ];
    }

    /**
     * Экземпляр способности.
     *
     * @param string $code Код.
     * @param int $level Уровень.
     *
     * @return CharacterAbilityChoice Экземпляр.
     */
    private function ability(string $code, int $level): CharacterAbilityChoice
    {
        return new CharacterAbilityChoice($code, $level, 'arcanist', 'arcanist', null, null, null);
    }
}
