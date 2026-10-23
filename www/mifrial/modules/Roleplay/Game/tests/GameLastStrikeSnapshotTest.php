<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameLastStrikeSnapshot;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет снимок прошлого удара и гейты добивания.
 */
final class GameLastStrikeSnapshotTest extends TestCase
{
    /**
     * Новый снимок заменяет старый и не трогает прочие хвосты.
     *
     * @return void
     */
    public function testStoreReplacesSnapshotAndKeepsOtherEffects(): void
    {
        $service = new GameLastStrikeSnapshot();
        $pending = [
            'character:1' => [
                [
                    'sourceRuleCode' => 'old',
                    'effect' => [
                        'type' => 'last_strike_snapshot',
                        'kind' => 'other',
                        'hits' => [['targetKey' => 'npc:9', 'attackSr' => 1]],
                    ],
                ],
                [
                    'sourceRuleCode' => 'cost',
                    'effect' => [
                        'type' => 'next_action_attack_cost',
                        'resource_code' => 'action-points',
                        'delta' => 1,
                    ],
                ],
            ],
        ];

        $stored = $service->store($pending, 'character', 1, 'swing', 'other', [[
            'targetKey' => 'npc:7',
            'attackSr' => 2,
            'reaction' => 'ignore',
            'damaged' => true,
        ]]);

        $waiting = $stored['character:1'] ?? [];
        self::assertCount(2, $waiting);
        self::assertSame('next_action_attack_cost', $waiting[0]['effect']['type']);
        self::assertSame('last_strike_snapshot', $waiting[1]['effect']['type']);
        self::assertSame('swing', $waiting[1]['sourceRuleCode']);
        self::assertSame('npc:7', $waiting[1]['effect']['hits'][0]['targetKey']);
    }

    /**
     * Читается последний снимок.
     *
     * @return void
     */
    public function testSnapshotOfTakesLast(): void
    {
        $service = new GameLastStrikeSnapshot();
        $pending = [
            'character:1' => [
                ['sourceRuleCode' => 'a', 'effect' => ['type' => 'last_strike_snapshot', 'kind' => 'other', 'hits' => []]],
                ['sourceRuleCode' => 'b', 'effect' => ['type' => 'last_strike_snapshot', 'kind' => 'lethal', 'hits' => []]],
            ],
        ];

        $snapshot = $service->snapshotOf($pending, 'character', 1);

        self::assertIsArray($snapshot);
        self::assertSame('lethal', $snapshot['kind']);
    }

    /**
     * Успех прошлого удара берётся по цели.
     *
     * @return void
     */
    public function testPreviousSrFindsTarget(): void
    {
        $service = new GameLastStrikeSnapshot();
        $pending = [
            'character:1' => [[
                'sourceRuleCode' => 'swing',
                'effect' => [
                    'type' => 'last_strike_snapshot',
                    'kind' => 'other',
                    'hits' => [
                        ['targetKey' => 'npc:1', 'attackSr' => 3],
                        ['targetKey' => 'npc:2', 'attackSr' => 5],
                    ],
                ],
            ]],
        ];

        self::assertSame(5, $service->previousSr($pending, 'character', 1, 'npc:2'));
        self::assertSame(0, $service->previousSr($pending, 'character', 1, 'npc:3'));
    }

    /**
     * Вид удара lethal только у карточки с добиванием успеха.
     *
     * @return void
     */
    public function testKindOfRecognizesLethal(): void
    {
        $service = new GameLastStrikeSnapshot();
        $slice = $this->slice();

        self::assertSame('lethal', $service->kindOf($slice, 'lethal'));
        self::assertSame('other', $service->kindOf($slice, 'critical'));
    }

    /**
     * Надбавка успеха от прошлого удара и её потолок.
     *
     * @return void
     */
    public function testBoostSuccessCapsAtDouble(): void
    {
        $service = new GameLastStrikeSnapshot();
        $slice = $this->slice();

        self::assertSame(2, $service->boostSuccess($slice, 'lethal', 1, 8));
        self::assertSame(5, $service->boostSuccess($slice, 'lethal', 4, 2));
        self::assertSame(0, $service->boostSuccess($slice, 'lethal', 0, 8));
        self::assertSame(3, $service->boostSuccess($slice, 'lethal', 3, 0));
        self::assertSame(4, $service->boostSuccess($slice, 'critical', 4, 8));
    }

    /**
     * Без снимка добивание запрещено.
     *
     * @return void
     */
    public function testFollowUpRequiresSnapshot(): void
    {
        $service = new GameLastStrikeSnapshot();

        $this->expectException(GameInvalidException::class);
        $service->assertFollowUp($this->slice(), 'critical', [], 'character', 1, ['npc:1']);
    }

    /**
     * all_damaged требует повреждения каждого удара.
     *
     * @return void
     */
    public function testFollowUpRequiresDamagedHits(): void
    {
        $service = new GameLastStrikeSnapshot();
        $pending = $this->pendingWithHits([['targetKey' => 'npc:1', 'attackSr' => 1, 'damaged' => false]]);

        $this->expectException(GameInvalidException::class);
        $service->assertFollowUp($this->slice(), 'critical', $pending, 'character', 1, ['npc:1']);
    }

    /**
     * Цель обязана быть среди целей прошлого удара.
     *
     * @return void
     */
    public function testFollowUpRejectsForeignTarget(): void
    {
        $service = new GameLastStrikeSnapshot();
        $pending = $this->pendingWithHits([['targetKey' => 'npc:1', 'attackSr' => 1, 'damaged' => true]]);

        $this->expectException(GameInvalidException::class);
        $service->assertFollowUp($this->slice(), 'critical', $pending, 'character', 1, ['npc:2']);
    }

    /**
     * single_strike запрещает широкую атаку.
     *
     * @return void
     */
    public function testFollowUpRejectsMultipleTargetsForSingleStrike(): void
    {
        $service = new GameLastStrikeSnapshot();
        $pending = $this->pendingWithHits([
            ['targetKey' => 'npc:1', 'attackSr' => 1, 'damaged' => true],
            ['targetKey' => 'npc:2', 'attackSr' => 1, 'damaged' => true],
        ]);

        $this->expectException(GameInvalidException::class);
        $service->assertFollowUp($this->slice(), 'critical', $pending, 'character', 1, ['npc:1', 'npc:2']);
    }

    /**
     * Смертельный удар не идёт после lethal и требует min_sr.
     *
     * @return void
     */
    public function testPreviousStrikeGateChecksKindAndSr(): void
    {
        $service = new GameLastStrikeSnapshot();
        $slice = $this->slice();
        $hits = [
            ['targetKey' => 'npc:1', 'attackSr' => 3, 'damaged' => true],
            ['targetKey' => 'npc:2', 'attackSr' => 4, 'damaged' => true],
        ];

        $service->assertFollowUp(
            $slice,
            'lethal',
            $this->pendingWithHits($hits, 'other'),
            'character',
            1,
            ['npc:2'],
        );

        try {
            $service->assertFollowUp(
                $slice,
                'lethal',
                $this->pendingWithHits($hits, 'other'),
                'character',
                1,
                ['npc:1'],
            );
            self::fail('Низкий успех прошлого удара должен быть запрещён');
        } catch (GameInvalidException) {
            self::assertTrue(true);
        }

        $this->expectException(GameInvalidException::class);
        $service->assertFollowUp(
            $slice,
            'lethal',
            $this->pendingWithHits($hits, 'lethal'),
            'character',
            1,
            ['npc:2'],
        );
    }

    /**
     * Карта боя со снимком.
     *
     * @param array<int, array<string, mixed>> $hits Удары.
     * @param string $kind Вид.
     *
     * @return array<string, mixed> Карта.
     */
    private function pendingWithHits(array $hits, string $kind = 'other'): array
    {
        return [
            'character:1' => [[
                'sourceRuleCode' => 'swing',
                'effect' => [
                    'type' => 'last_strike_snapshot',
                    'kind' => $kind,
                    'hits' => $hits,
                ],
            ]],
        ];
    }

    /**
     * Срез с карточками добивания.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'test', [
            new CharacterResolvedRule($this->record('critical', [[
                'type' => 'require_previous_attack',
                'all_damaged' => true,
                'same_target' => true,
                'single_strike' => true,
            ]]), []),
            new CharacterResolvedRule($this->record('lethal', [
                [
                    'type' => 'require_previous_strike',
                    'min_sr' => 4,
                    'not_kind' => 'lethal',
                    'same_target' => true,
                ],
                [
                    'type' => 'attack_sr_from_previous',
                    'floor_div' => 2,
                    'cap' => 'double_this',
                ],
            ]), []),
        ], []);
    }

    /**
     * Версия карточки.
     *
     * @param string $code Код.
     * @param array<int, array<string, mixed>> $effects Эффекты.
     *
     * @return RuleVersionRecord Запись.
     */
    private function record(string $code, array $effects): RuleVersionRecord
    {
        return new RuleVersionRecord(
            1,
            1,
            $code,
            true,
            'ability',
            ucfirst($code),
            '',
            [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                'requirements' => [],
                'grants' => [],
                'parent_ability_code' => null,
                'action_components' => [],
                'action_effects' => $effects,
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
    }
}
