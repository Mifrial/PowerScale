<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Service\GameRollAdjustment;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Настройка броска из карточки: ремап, дельты и фильтр области.
 */
final class GameRollAdjustmentTest extends TestCase
{
    /**
     * Ремап и дельты собираются из одной карточки.
     *
     * @return void
     */
    public function testCollectsRemapAndScoreDeltas(): void
    {
        $slice = $this->slice([$this->card([
            $this->remap(5, 6),
            $this->remap(2, 1),
            $this->score(1, -1),
        ])]);

        $adjustment = (new GameRollAdjustment())->fromCard($slice, 'karta', 'strike');

        self::assertSame([5 => 6, 2 => 1], $adjustment->getRemap());
        self::assertSame(1, $adjustment->getOneDelta());
        self::assertSame(-1, $adjustment->getFaceDelta());
        self::assertFalse($adjustment->isEmpty());
    }

    /**
     * Область фильтрует компонент и первое попадание.
     *
     * @return void
     */
    public function testScopeFiltersComponentAndHitNumber(): void
    {
        $slice = $this->slice([$this->card([
            $this->remap(5, 6, ['components' => ['throw'], 'hit_count' => 1]),
            $this->score(1, -1, ['components' => ['strike'], 'hit_count' => 1]),
        ])]);

        $adjustment = (new GameRollAdjustment())->fromCard($slice, 'karta', 'strike');

        self::assertSame([], $adjustment->getRemap());
        self::assertSame(1, $adjustment->getOneDelta());

        $second = (new GameRollAdjustment())->fromCard($slice, 'karta', 'strike', 2);
        self::assertTrue($second->isEmpty());
    }

    /**
     * Первые два попадания накрываются, третье — нет.
     *
     * @return void
     */
    public function testHitCountCoversFirstHits(): void
    {
        $slice = $this->slice([$this->card([
            $this->score(1, -1, ['components' => ['strike'], 'hit_count' => 2]),
        ])]);

        $adjustment = new GameRollAdjustment();
        self::assertSame(1, $adjustment->fromCard($slice, 'karta', 'strike', 1)->getOneDelta());
        self::assertSame(1, $adjustment->fromCard($slice, 'karta', 'strike', 2)->getOneDelta());
        self::assertTrue($adjustment->fromCard($slice, 'karta', 'strike', 3)->isEmpty());
    }

    /**
     * Хвост следующего действия сюда не входит.
     *
     * @return void
     */
    public function testIgnoresNextActionEffects(): void
    {
        $slice = $this->slice([$this->card([[
            'type' => 'next_action_attack_score_adjust',
            'oneDelta' => -1,
            'faceDelta' => 0,
            'same_target' => true,
        ]])]);

        self::assertTrue((new GameRollAdjustment())->fromCard($slice, 'karta', 'strike')->isEmpty());
    }

    /**
     * Пустой код и чужая карточка дают пустую настройку.
     *
     * @return void
     */
    public function testUnknownCardGivesEmptyAdjustment(): void
    {
        $slice = $this->slice([$this->card([$this->remap(5, 6)])]);
        $service = new GameRollAdjustment();

        self::assertTrue($service->fromCard($slice, '', 'strike')->isEmpty());
        self::assertTrue($service->fromCard($slice, 'net-takoy', 'strike')->isEmpty());
    }

    /**
     * Карточка действия с эффектами.
     *
     * @param array<int, array<string, mixed>> $effects Эффекты.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function card(array $effects): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                'karta',
                true,
                'ability',
                'Карточка',
                '',
                [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                    'requirements' => [],
                    'grants' => [],
                    'action_effects' => $effects,
                ],
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
    }

    /**
     * Эффект ремапа.
     *
     * @param int $from Откуда.
     * @param int $to Куда.
     * @param array<string, mixed> $scope Область.
     *
     * @return array<string, mixed> Эффект.
     */
    private function remap(int $from, int $to, array $scope = ['components' => ['strike'], 'hit_count' => 1]): array
    {
        return [
            'type' => 'current_action_roll_face_remap',
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ];
    }

    /**
     * Эффект дельт успехов.
     *
     * @param int $oneDelta Дельта единицы.
     * @param int $faceDelta Дельта грани.
     * @param array<string, mixed> $scope Область.
     *
     * @return array<string, mixed> Эффект.
     */
    private function score(
        int $oneDelta,
        int $faceDelta,
        array $scope = ['components' => ['strike'], 'hit_count' => 1],
    ): array {
        return [
            'type' => 'current_action_roll_score_adjust',
            'oneDelta' => $oneDelta,
            'faceDelta' => $faceDelta,
            'scope' => $scope,
        ];
    }

    /**
     * Срез из живых карточек.
     *
     * @param array<int, CharacterResolvedRule> $live Карточки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $live): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', $live, []);
    }
}
