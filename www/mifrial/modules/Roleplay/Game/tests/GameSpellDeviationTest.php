<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellDeviation;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use PHPUnit\Framework\TestCase;

/**
 * Отклонение: 2d6, состояние на источнике и штраф к потолку мощи.
 */
final class GameSpellDeviationTest extends TestCase
{
    public const MECHANIC_ID = 18;

    /**
     * Сила — сумма граней минус порог; дубль ловится по граням.
     *
     * @return void
     */
    public function testRollCountsStrengthAndDoubles(): void
    {
        $spec = $this->service()->spec($this->slice(), 'check-spell-cast');
        self::assertNotNull($spec);

        $outcome = $this->service()->roll($spec, $this->faces([4, 4]));

        self::assertSame([4, 4], $outcome['faces']);
        self::assertSame(8, $outcome['faceSum']);
        self::assertSame(1, $outcome['strength']);
        self::assertTrue($outcome['hasEffect']);
        self::assertTrue($outcome['isDoubles']);
        self::assertSame(4, $outcome['dieDigit']);
        self::assertSame(6, $outcome['dieFaces']);
    }

    /**
     * Низкая сумма даёт отрицательную силу и отсутствие эффекта.
     *
     * @return void
     */
    public function testLowSumHasNoEffect(): void
    {
        $spec = $this->service()->spec($this->slice(), 'check-spell-cast');
        self::assertNotNull($spec);

        $outcome = $this->service()->roll($spec, $this->faces([2, 2]));

        self::assertSame(-3, $outcome['strength']);
        self::assertFalse($outcome['hasEffect']);
        self::assertTrue($outcome['isDoubles']);
    }

    /**
     * Ровно порог — отклонения нет и дубля нет.
     *
     * @return void
     */
    public function testNeutralSumHasNoEffectAndNoDoubles(): void
    {
        $spec = $this->service()->spec($this->slice(), 'check-spell-cast');
        self::assertNotNull($spec);

        $outcome = $this->service()->roll($spec, $this->faces([3, 4]));

        self::assertSame(0, $outcome['strength']);
        self::assertFalse($outcome['hasEffect']);
        self::assertFalse($outcome['isDoubles']);
    }

    /**
     * Без строки на источнике состояние добавляется с привязкой.
     *
     * @return void
     */
    public function testOperationsAppendStateWithSource(): void
    {
        $spec = $this->service()->spec($this->slice(), 'check-spell-cast');
        self::assertNotNull($spec);

        $operations = $this->service()->operations($spec, ['states' => []], 'item:5', 3);

        self::assertSame([[
            'kind' => 'putState',
            'stateRuleCode' => 'core-magic-deviation',
            'value' => 3,
            'boundSourceKey' => 'item:5',
        ]], $operations);
    }

    /**
     * Строка того же источника складывается, чужой источник не трогается.
     *
     * @return void
     */
    public function testOperationsAccumulateOnSameSource(): void
    {
        $spec = $this->service()->spec($this->slice(), 'check-spell-cast');
        self::assertNotNull($spec);

        $sheet = ['states' => [
            ['id' => 1, 'stateRuleCode' => 'core-magic-deviation', 'value' => 2, 'boundSourceKey' => 'item:5'],
            ['id' => 2, 'stateRuleCode' => 'core-magic-deviation', 'value' => 4, 'boundSourceKey' => 'item:6'],
        ]];

        self::assertSame(
            [['kind' => 'patchState', 'id' => 1, 'value' => 5]],
            $this->service()->operations($spec, $sheet, 'item:5', 3),
        );
    }

    /**
     * Без эффекта операций нет.
     *
     * @return void
     */
    public function testOperationsSkipNonPositiveStrength(): void
    {
        $spec = $this->service()->spec($this->slice(), 'check-spell-cast');
        self::assertNotNull($spec);

        self::assertSame([], $this->service()->operations($spec, ['states' => []], 'item:5', 0));
    }

    /**
     * Сила на источнике суммируется только по его строкам.
     *
     * @return void
     */
    public function testStrengthOnSourceSumsOnlyOwnRows(): void
    {
        $sheet = ['states' => [
            ['id' => 1, 'stateRuleCode' => 'core-magic-deviation', 'value' => 2, 'boundSourceKey' => 'item:5'],
            ['id' => 2, 'stateRuleCode' => 'core-magic-deviation', 'value' => 4, 'boundSourceKey' => 'item:6'],
            ['id' => 3, 'stateRuleCode' => 'core-magic-deviation', 'value' => 1],
        ]];

        self::assertSame(
            2,
            $this->service()->strengthOnSource($this->slice(), 'check-spell-cast', $sheet, 'item:5'),
        );
    }

    /**
     * Потолок мощи падает на силу и обнуляется ниже маленького размера.
     *
     * @return void
     */
    public function testPenalizedCap(): void
    {
        $cap = $this->service()->penalizedCap(new CharacteristicNumber(5, 0), 1);
        self::assertSame(4, $cap->getBase());
        self::assertSame(0, $cap->getSize());

        self::assertSame(
            -1,
            $this->service()->penalizedCap(new CharacteristicNumber(3, 0), 3)->getSize(),
        );
        self::assertSame(
            0,
            $this->service()->penalizedCap(new CharacteristicNumber(3, 0), 4)->getBase(),
        );
    }

    /**
     * Карточки нет — отклонения нет; битое состояние — отказ.
     *
     * @return void
     */
    public function testSpecRequiresLiveCards(): void
    {
        self::assertNull($this->service()->spec($this->slice(), 'no-such-check'));

        $this->expectException(GameInvalidException::class);
        $this->service()->spec($this->slice([], true), 'check-spell-cast');
    }

    /**
     * Сервис.
     *
     * @return GameSpellDeviation Сервис.
     */
    private function service(): GameSpellDeviation
    {
        return new GameSpellDeviation($this->mechanics());
    }

    /**
     * Каталог с одной механикой.
     *
     * @return IMechanics Каталог.
     */
    private function mechanics(): IMechanics
    {
        return new class implements IMechanics {
            /**
             * Список механик.
             *
             * @return list<MechanicRecord> Каталог.
             */
            public function getList(): array
            {
                return [MechanicRecord::fromNormalized([
                    'id' => GameSpellDeviationTest::MECHANIC_ID,
                    'code' => 'spell_deviation',
                    'name' => 'Магическое отклонение',
                    'description' => '',
                    'handler_version' => '1.0.0',
                ])];
            }

            /**
             * Не нужен.
             *
             * @param string $code Код.
             * @param string $name Имя.
             * @param string $description Описание.
             * @param string $handlerVersion Версия.
             *
             * @return int Id.
             */
            public function add(string $code, string $name, string $description, string $handlerVersion): int
            {
                throw new MechanicInvalidException('not used');
            }

            /**
             * Не нужен.
             *
             * @param int $id Id.
             *
             * @return MechanicRecord Запись.
             */
            public function get(int $id): MechanicRecord
            {
                throw new MechanicNotFoundException('not used');
            }

            /**
             * Не нужен.
             *
             * @param string $code Код.
             * @param string $handlerVersion Версия.
             *
             * @return MechanicRecord Запись.
             */
            public function getByCodeVersion(string $code, string $handlerVersion): MechanicRecord
            {
                throw new MechanicNotFoundException('not used');
            }

            /**
             * Не нужен.
             *
             * @param int $id Id.
             * @param string $name Имя.
             * @param string $description Описание.
             *
             * @return void
             */
            public function update(int $id, string $name, string $description): void
            {
                throw new MechanicInvalidException('not used');
            }
        };
    }

    /**
     * Срез с проверкой, отклонением, состоянием и типом урона.
     *
     * @param array<int, CharacterResolvedRule> $extra Дополнительные карточки.
     * @param bool $brokenState Сломать состояние.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $extra = [], bool $brokenState = false): CharacterRuleSlice
    {
        $state = $brokenState
            ? $this->rule('core-magic-deviation', 'state', ['value_type' => 'number', 'aggregation' => 'independent'])
            : $this->rule('core-magic-deviation', 'state', [
                'value_type' => 'number',
                'aggregation' => 'independent',
                'magic_deviation' => true,
            ]);

        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('check-spell-cast', 'check', [
                'allowed_modes' => 'both',
                'difficulty_input' => ['kind' => 'ask'],
                'failure_rule_codes' => ['spell-deviation'],
            ]),
            $this->rule('spell-deviation', 'simple', ['type' => 'simple'], [[
                'mechanic_id' => self::MECHANIC_ID,
                'mechanic_payload' => [
                    'type' => 'spell_deviation',
                    'data' => [
                        'dice_count' => 2,
                        'die_faces' => 6,
                        'neutral_sum' => 7,
                        'state_rule_code' => 'core-magic-deviation',
                        'burst' => ['damage_type_code' => 'arcane', 'success_rating' => 1],
                    ],
                ],
            ]]),
            $state,
            $this->rule('arcane', 'damage_type', [
                'forms' => ['genitive' => 'арканного урона', 'dative' => 'арканному урону'],
                'defense_ignored' => true,
                'modifies_spell_difficulty' => true,
            ]),
            ...$extra,
        ], []);
    }

    /**
     * Карточка среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Spec.
     * @param array<int, array<string, mixed>> $mechanics Механики.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function rule(string $code, string $type, array $spec, array $mechanics = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                'Name',
                '',
                $spec,
                [],
                $mechanics,
                'ready',
                '',
                DateTime::now(),
            ),
            [],
        );
    }

    /**
     * RNG с заданными гранями.
     *
     * @param array<int, int> $faces Грани.
     *
     * @return \Closure(): float Источник.
     */
    private function faces(array $faces): \Closure
    {
        $index = 0;

        return static function () use (&$index, $faces): float {
            $face = $faces[$index] ?? 1;
            $index++;

            return ($face - 0.5) / 6;
        };
    }
}
