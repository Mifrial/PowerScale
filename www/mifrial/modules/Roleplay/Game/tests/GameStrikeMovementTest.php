<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Service\GameStrikeMovement;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Размерный шаг расы и её способностей.
 */
final class GameStrikeMovementTest extends TestCase
{
    /**
     * Без расы и способностей шаг — один ипари.
     *
     * @return void
     */
    public function testDefaultStepIsOneIpari(): void
    {
        $step = (new GameStrikeMovement())->step($this->slice([]), [], []);

        self::assertSame(1, $step->getBase());
        self::assertSame(0, $step->getSize());
    }

    /**
     * Шаг берётся из карточки расы.
     *
     * @return void
     */
    public function testRaceStepIsUsed(): void
    {
        $slice = $this->slice([
            $this->race('dwarf', 2),
        ]);

        $step = (new GameStrikeMovement())->stepOf($slice, [], 'dwarf');

        self::assertSame(2, $step->getBase());
        self::assertSame(0, $step->getSize());
    }

    /**
     * Раса ищется по raceCode, затем по race.
     *
     * @return void
     */
    public function testStepReadsRaceFromChoices(): void
    {
        $movement = new GameStrikeMovement();
        $slice = $this->slice([$this->race('dwarf', 2)]);

        $byCode = $movement->step($slice, [], ['raceCode' => 'dwarf']);
        $byRace = $movement->step($slice, [], ['race' => 'dwarf']);

        self::assertSame(2, $byCode->getBase());
        self::assertSame(2, $byRace->getBase());
    }

    /**
     * Расовые способности сдвигают размер шага.
     *
     * @return void
     */
    public function testRacialAbilityAddsSizeDelta(): void
    {
        $slice = $this->slice([
            $this->race('dwarf', 1),
            $this->ability('fast', 1),
        ]);

        $step = (new GameStrikeMovement())->stepOf(
            $slice,
            ['racialAbilityCodes' => ['fast']],
            'dwarf',
        );

        self::assertSame(1, $step->getBase());
        self::assertSame(1, $step->getSize());
    }

    /**
     * Неизвестная раса не ломает шаг.
     *
     * @return void
     */
    public function testUnknownRaceKeepsDefault(): void
    {
        $step = (new GameStrikeMovement())->stepOf($this->slice([]), [], 'unknown');

        self::assertSame(1, $step->getBase());
        self::assertSame(0, $step->getSize());
    }

    /**
     * Срез с переданными правилами.
     *
     * @param array<int, RuleVersionRecord> $records Карточки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $records): CharacterRuleSlice
    {
        $rules = [];
        foreach ($records as $record) {
            $rules[] = new CharacterResolvedRule($record, []);
        }

        return new CharacterRuleSlice(1, 1, 'world', $rules, []);
    }

    /**
     * Карточка расы.
     *
     * @param string $code Код.
     * @param int $step Шаг.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function race(string $code, int $step): RuleVersionRecord
    {
        return $this->record('race', $code, [
            'cost_os' => 0,
            'movement_step' => ['base' => $step, 'size' => 0],
        ]);
    }

    /**
     * Карточка способности.
     *
     * @param string $code Код.
     * @param int $delta Сдвиг размера шага.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function ability(string $code, int $delta): RuleVersionRecord
    {
        return $this->record('ability', $code, [
            'movement_step_size_delta' => $delta,
        ]);
    }

    /**
     * Карточка правила.
     *
     * @param string $type Тип.
     * @param string $code Код.
     * @param array<string, mixed> $spec Документ spec.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function record(string $type, string $code, array $spec): RuleVersionRecord
    {
        return new RuleVersionRecord(
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
        );
    }
}
