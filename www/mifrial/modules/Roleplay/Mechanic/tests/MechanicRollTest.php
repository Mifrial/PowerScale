<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Closure;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Mechanic\Dto\CheckRating;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Dto\SizedBase;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use PHPUnit\Framework\TestCase;

final class MechanicRollTest extends TestCase
{
    private IMechanicRolls $rolls;

    /**
     * Порт из контейнера.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $rolls = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanicRolls::class);
        self::assertInstanceOf(IMechanicRolls::class, $rolls);
        $this->rolls = $rolls;
    }

    /**
     * Бросок по чистой спеке считает успех гранью не выше эффективности.
     *
     * @return void
     */
    public function testPlainSpecCountsSuccessAtOrBelowEfficiency(): void
    {
        $result = $this->rolls->roll(
            $this->spec(4),
            $this->rng([2, 4, 1, 5]),
            [],
            [],
            new ResolveActiveOptions(),
        );

        self::assertSame([2, 4, 1, 5], $result->getRolls());
        self::assertSame([2, 4, 1, 5], $result->getAdjustedRolls());
        self::assertSame([1, 0, 1, 0], $result->getSuccesses());
        self::assertSame(2, $result->getTotalSuccesses());
        self::assertNull($result->getAppliedNames());
    }

    /**
     * Преимущество и «6 и 1» меняют грани и попадают в имена.
     *
     * @return void
     */
    public function testAdvantageAndSixOneAdjustRoll(): void
    {
        $result = $this->rolls->roll(
            $this->spec(3, advantages: [new RollAdvantage('roll', 1)]),
            $this->rng([1, 6, 5, 6]),
            $this->revisionBindings(),
            $this->catalog(),
            new ResolveActiveOptions(),
        );

        self::assertSame([6], $result->getDroppedRolls());
        self::assertSame([6, 5, 1], $result->getAdjustedRolls());
        self::assertSame([-1, 0, 2], $result->getSuccesses());
        self::assertSame(1, $result->getTotalSuccesses());
        self::assertSame(['Помехи и преимущества', 'Правило 6 и 1'], $result->getAppliedNames());
    }

    /**
     * Грань снимает успех только если она выше эффективности.
     *
     * @return void
     */
    public function testFaceFailsOnlyBelowDieSize(): void
    {
        $low = $this->faceRoll(3);
        $high = $this->faceRoll(6);

        self::assertSame([-1], $low->getSuccesses());
        self::assertSame([1], $high->getSuccesses());
    }

    /**
     * Без правила «6 и 1» единица — обычный успех, грань — промах.
     *
     * @return void
     */
    public function testWithoutSixOneOneIsNormalSuccess(): void
    {
        $bindings = [$this->revisionBindings()[0], $this->revisionBindings()[2]];
        $result = $this->rolls->roll(
            $this->spec(2),
            $this->rng([1, 6]),
            $bindings,
            $this->catalog(),
            new ResolveActiveOptions(),
        );

        self::assertSame([1, 0], $result->getSuccesses());
        self::assertSame(1, $result->getTotalSuccesses());
        self::assertNull($result->getAppliedNames());
    }

    /**
     * Нулевое преимущество не помечает механику.
     *
     * @return void
     */
    public function testZeroAdvantageIsNotApplied(): void
    {
        $result = $this->rolls->roll(
            $this->spec(1),
            $this->rng([3]),
            $this->revisionBindings(),
            $this->catalog(),
            new ResolveActiveOptions(),
        );

        self::assertNull($result->getAppliedNames());
    }

    /**
     * Явный includeCodes подменяет sub_mechanics.
     *
     * @return void
     */
    public function testIncludeCodesReplaceSubMechanics(): void
    {
        $withSix = $this->faceRoll(3);
        $without = $this->rolls->roll(
            $this->spec(1),
            $this->rng([6]),
            $this->revisionBindings(),
            $this->catalog(),
            new ResolveActiveOptions(['advantage_disadvantage']),
        );

        self::assertSame([-1], $withSix->getSuccesses());
        self::assertSame([0], $without->getSuccesses());
    }

    /**
     * Дефолты «Бросок» не трогают число кубов и граней.
     *
     * @return void
     */
    public function testRollDefaultsLeaveDiceCountAndFaces(): void
    {
        $result = $this->rolls->roll(
            $this->spec(4, 8),
            $this->rng([3, 3, 3, 3], 8),
            [new MechanicBinding('roll', 5, new RollMechanicPayload(efficiency: 2, adv: 1, dieSize: 3))],
            $this->catalog(),
            new ResolveActiveOptions(),
        );

        self::assertSame(2, $result->getSpec()->getEfficiency());
        self::assertSame(1, $result->getSpec()->getAdvantages()[0]->getDelta());
        self::assertSame('roll', $result->getSpec()->getAdvantages()[0]->getSourceCode());
        self::assertSame(3, $result->getSpec()->getDieSize());
        self::assertSame(4, $result->getSpec()->getDiceCount());
        self::assertSame(8, $result->getSpec()->getDieFaces());
    }

    /**
     * Явные эффективность и преимущество дефолт не затирает.
     *
     * @return void
     */
    public function testExplicitEfficiencyAndAdvantageStay(): void
    {
        $result = $this->rolls->roll(
            $this->spec(4, efficiency: 5, advantages: [new RollAdvantage('manual', -2)]),
            $this->rng([3, 3, 3, 3]),
            [new MechanicBinding('roll', 5, new RollMechanicPayload(efficiency: 2, adv: 1))],
            $this->catalog(),
            new ResolveActiveOptions(),
        );

        self::assertSame(5, $result->getSpec()->getEfficiency());
        self::assertSame(-2, $result->getSpec()->getAdvantages()[0]->getDelta());
    }

    /**
     * Разные источники суммируются, один источник берёт крайние дельты.
     *
     * @return void
     */
    public function testSourceExtremesChangePool(): void
    {
        $result = $this->rolls->roll(
            $this->spec(2, advantages: [
                new RollAdvantage('tool', -1),
                new RollAdvantage('tool', -2),
                new RollAdvantage('manual', 1),
            ]),
            $this->rng([1, 2, 6, 3]),
            [
                new MechanicBinding('roll', 5, new RollMechanicPayload(subMechanics: ['advantage_disadvantage'])),
                new MechanicBinding('advantages', 2, null),
            ],
            $this->catalog(),
            new ResolveActiveOptions(),
        );

        self::assertCount(3, $result->getRolls());
        self::assertCount(1, $result->getDroppedRolls());
    }

    /**
     * Сравнение итога совпадает с проверкой на фронте.
     *
     * @return void
     */
    public function testRateMatchesCheckSuccessRating(): void
    {
        self::assertEquals(new CheckRating(true, 1), $this->rolls->rate(new SizedBase(3, 1), new SizedBase(2, 1)));
        self::assertEquals(new CheckRating(true, 2), $this->rolls->rate(new SizedBase(3, 1), new SizedBase(4, 0)));
        self::assertEquals(new CheckRating(false, -9), $this->rolls->rate(new SizedBase(1, -1), new SizedBase(5, 0)));
        self::assertEquals(new CheckRating(true, 9), $this->rolls->rate(new SizedBase(5, 0), new SizedBase(1, -1)));
        self::assertEquals(new CheckRating(true, 0), $this->rolls->rate(new SizedBase(-2, 0), new SizedBase(0, 0)));
        self::assertEquals(
            new CheckRating(true, 1),
            $this->rolls->rate(new SizedBase(5, -1), new SizedBase(8, -2), -1),
        );
    }

    /**
     * Одна грань при заданной эффективности.
     *
     * @param int $efficiency Эффективность.
     *
     * @return RollResult Итог.
     */
    private function faceRoll(int $efficiency): RollResult
    {
        return $this->rolls->roll(
            $this->spec(1, efficiency: $efficiency),
            $this->rng([6]),
            $this->revisionBindings(),
            $this->catalog(),
            new ResolveActiveOptions(),
        );
    }

    /**
     * Спека. Нейтральные эффективность 3 и размер 0.
     *
     * @param int $diceCount Число кубов.
     * @param int $dieFaces Грани.
     * @param int $efficiency Эффективность.
     * @param array<int, RollAdvantage> $advantages Преимущества.
     *
     * @return RollSpec Спека.
     */
    private function spec(
        int $diceCount,
        int $dieFaces = 6,
        int $efficiency = 3,
        array $advantages = [],
    ): RollSpec {
        return new RollSpec($diceCount, $dieFaces, $efficiency, 0, $advantages);
    }

    /**
     * Источник граней: (грань − 1) / число граней.
     *
     * @param array<int, int> $faces Грани по порядку.
     * @param int $dieFaces Делитель.
     *
     * @return Closure Источник.
     */
    private function rng(array $faces, int $dieFaces = 6): Closure
    {
        $index = 0;

        return static function () use ($faces, $dieFaces, &$index): float {
            $face = $faces[$index];
            $index++;

            return ($face - 1) / $dieFaces;
        };
    }

    /**
     * Binding ревизии: бросок, «6 и 1», преимущества.
     *
     * @return array<int, MechanicBinding> Срезы.
     */
    private function revisionBindings(): array
    {
        return [
            new MechanicBinding(
                'roll',
                5,
                new RollMechanicPayload(efficiency: 3, subMechanics: ['six_one_rule', 'advantage_disadvantage']),
            ),
            new MechanicBinding('rule-6-and-1', 1, null),
            new MechanicBinding('advantages', 2, null),
        ];
    }

    /**
     * Каталог поставок хендлеров.
     *
     * @return array<int, MechanicRecord> Строки.
     */
    private function catalog(): array
    {
        return [
            $this->record(1, 'six_one_rule', 'Правило 6 и 1', '4.5.0'),
            $this->record(2, 'advantage_disadvantage', 'Помехи и преимущества', '2.1.0'),
            $this->record(5, 'roll', 'Бросок', '1.0.0'),
        ];
    }

    /**
     * Строка каталога.
     *
     * @param int $id Id.
     * @param string $code Код.
     * @param string $name Имя.
     * @param string $version Версия хендлера.
     *
     * @return MechanicRecord Строка.
     */
    private function record(int $id, string $code, string $name, string $version): MechanicRecord
    {
        return MechanicRecord::fromNormalized([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'description' => '',
            'handler_version' => $version,
        ]);
    }
}
