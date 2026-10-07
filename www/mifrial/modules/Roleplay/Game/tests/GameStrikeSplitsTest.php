<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\CharacterFormulaContexts;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeSplits;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Деление повреждения на стойкость без записи листа.
 */
final class GameStrikeSplitsTest extends TestCase
{
    /**
     * Частное и остаток берутся из divide и не складываются заново.
     *
     * @return void
     */
    public function testOperationUsesDivide(): void
    {
        $injury = new DimensionalNumber(5, 0);
        $endurance = new DimensionalNumber(2, 0);
        $split = $injury->divide($endurance);
        $operation = (new GameStrikeSplits())->operation(
            $this->slice(['stamina']),
            $this->contexts(),
            $this->sheet(2),
            $injury,
        );

        self::assertSame([
            'kind' => 'putDamageSplit',
            'remainder' => [
                'base' => $split->getRemainder()->getBase(),
                'size' => $split->getRemainder()->getSize(),
            ],
            'quotient' => $split->getQuotient(),
        ], $operation);
    }

    /**
     * Нет карточки, две карточки, нет закупки и нулевая база — отказ.
     *
     * @return void
     */
    public function testRejectsMissingSliceAndZeroDivisor(): void
    {
        $splits = new GameStrikeSplits();
        $contexts = $this->contexts();
        $injury = new DimensionalNumber(1, 0);

        foreach ([
            [$this->slice([]), $this->sheet(2)],
            [$this->slice(['stamina', 'grit']), $this->sheet(2)],
            [$this->slice(['stamina']), $this->sheet(null)],
            [$this->slice(['stamina']), $this->sheet(0)],
        ] as [$slice, $sheet]) {
            try {
                $splits->operation($slice, $contexts, $sheet, $injury);
                self::fail('endurance must be rejected');
            } catch (GameInvalidException $exception) {
                self::assertSame('GAME_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * Срез с карточками стойкости.
     *
     * @param array<int, string> $codes Коды.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $codes): CharacterRuleSlice
    {
        $rules = [];
        foreach ($codes as $code) {
            $rules[] = new CharacterResolvedRule(
                new RuleVersionRecord(1, 1, $code, true, 'characteristic', 'Name', '', [
                    'damage_endurance' => true,
                ], [], [], 'needs_work', '', DateTime::now()),
                [],
            );
        }

        return new CharacterRuleSlice(1, 1, 'world', $rules, []);
    }

    /**
     * Разбор закупок листа.
     *
     * @return CharacterFormulaContexts Контекст.
     */
    private function contexts(): CharacterFormulaContexts
    {
        return new CharacterFormulaContexts($this->createMock(ICharacters::class));
    }

    /**
     * Лист с одной закупкой или без неё.
     *
     * @param int|null $base База стойкости или null, если закупки нет.
     *
     * @return array<string, mixed> Sheet.
     */
    private function sheet(?int $base): array
    {
        $purchases = $base === null ? [] : [[
            'characteristicCode' => 'stamina',
            'value' => ['base' => $base, 'size' => 0],
        ]];

        return [
            'abilityLevels' => [],
            'characteristicPurchases' => $purchases,
        ];
    }
}
