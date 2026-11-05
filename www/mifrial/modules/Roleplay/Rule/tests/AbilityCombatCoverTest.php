<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;
use PHPUnit\Framework\TestCase;

/**
 * Прикрытие: помехи, разрешённые защиты и стратегии переноса цели.
 */
final class AbilityCombatCoverTest extends TestCase
{
    /**
     * Собирает узел прикрытия.
     *
     * @param array<string, mixed> $overrides Переопределения.
     *
     * @return array<string, mixed> Документ.
     */
    private function document(array $overrides = []): array
    {
        return [
            'type' => 'action',
            'cover_ally' => array_merge([
                'defense_advantages' => [
                    ['delta' => -1, 'source_code' => 'circumstances'],
                ],
                'reactions' => ['block'],
                'resolution' => [
                    'success' => 'highest_rating',
                    'failure' => 'attacker_choice',
                    'becomes' => 'actual_target',
                ],
                'distance' => 'declared',
            ], $overrides),
        ];
    }

    /**
     * Новая форма читает несколько записей.
     *
     * @return void
     */
    public function testReadsDefenseAdvantages(): void
    {
        $cover = AbilityCombat::cover($this->document([
            'defense_advantages' => [
                ['delta' => -1, 'source_code' => 'circumstances'],
                ['delta' => 1, 'source_code' => 'training'],
            ],
        ]));

        self::assertNotNull($cover);
        $advantages = $cover->getDefenseAdvantages();
        self::assertCount(2, $advantages);
        self::assertSame(-1, $advantages[0]->getDelta());
        self::assertSame('circumstances', $advantages[0]->getSourceCode());
        self::assertSame(1, $advantages[1]->getDelta());
        self::assertSame('training', $advantages[1]->getSourceCode());
    }

    /**
     * Пустой список помех допустим: их может не быть.
     *
     * @return void
     */
    public function testEmptyDefenseAdvantages(): void
    {
        $cover = AbilityCombat::cover($this->document(['defense_advantages' => []]));

        self::assertNotNull($cover);
        self::assertSame([], $cover->getDefenseAdvantages());
        self::assertSame(['block'], $cover->getReactions());
        self::assertSame('declared', $cover->getDistance());
    }

    /**
     * Разрешённые защиты и стратегии читаются как есть.
     *
     * @return void
     */
    public function testReadsReactionsAndResolution(): void
    {
        $cover = AbilityCombat::cover($this->document(['reactions' => ['block', 'parry']]));

        self::assertNotNull($cover);
        self::assertSame(['block', 'parry'], $cover->getReactions());
        self::assertSame([
            'success' => 'highest_rating',
            'failure' => 'attacker_choice',
            'becomes' => 'actual_target',
        ], $cover->getResolution());
    }

    /**
     * Старая форма отклоняется.
     *
     * @return void
     */
    public function testRejectsLegacyForm(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        AbilityCombat::cover([
            'type' => 'action',
            'cover_ally' => ['circumstance_delta' => -1],
        ]);
    }

    /**
     * Источник обязателен: величину и источник не склеиваем.
     *
     * @return void
     */
    public function testRejectsMissingSource(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        AbilityCombat::cover($this->document([
            'defense_advantages' => [['delta' => -1]],
        ]));
    }

    /**
     * Пустой список защит не даёт прикрывать.
     *
     * @return void
     */
    public function testRejectsEmptyReactions(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        AbilityCombat::cover($this->document(['reactions' => []]));
    }

    /**
     * Неизвестная стратегия переноса отклоняется.
     *
     * @return void
     */
    public function testRejectsUnknownResolution(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        AbilityCombat::cover($this->document([
            'resolution' => [
                'success' => 'highest_rating',
                'failure' => 'nobody',
                'becomes' => 'actual_target',
            ],
        ]));
    }

    /**
     * Неизвестный источник не роняет разбор.
     *
     * @return void
     */
    public function testKeepsUnknownSource(): void
    {
        $cover = AbilityCombat::cover($this->document([
            'defense_advantages' => [['delta' => -1, 'source_code' => 'unknown']],
        ]));

        self::assertNotNull($cover);
        self::assertSame('unknown', $cover->getDefenseAdvantages()[0]->getSourceCode());
    }

    /**
     * Без узла прикрытия нет.
     *
     * @return void
     */
    public function testAbsentNodeIsNull(): void
    {
        self::assertNull(AbilityCombat::cover(['type' => 'action']));
    }
}
