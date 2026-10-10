<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\CharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\AbilityLevelScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizeGapScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizePositiveScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\CharacteristicSizeScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\FixedScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterFloorDivScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ToScalar;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Числа обхода совпадают с Vue FormulaEvaluationService.
 */
final class FormulaEvaluationTest extends TestCase
{
    private FormulaEvaluations $evaluations;

    /**
     * Обходчик.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->evaluations = new FormulaEvaluations();
    }

    /**
     * Контекст как в Vue-тесте.
     *
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int> $parameters Параметры.
     * @param array<string, array<string, DimensionalNumber>> $actionCharacteristics Базы действий.
     *
     * @return FormulaContext Контекст.
     */
    private function context(
        array $characteristics = [],
        array $parameters = [],
        array $actionCharacteristics = [],
    ): FormulaContext {
        return new FormulaContext(
            $characteristics === [] ? [
                'strength' => new CharacteristicNumber(5, 0),
                'dexterity' => new CharacteristicNumber(6, 0),
            ] : $characteristics,
            ['melee-fighting' => 3],
            $parameters,
            $actionCharacteristics,
        );
    }

    /**
     * Фиксированное число.
     *
     * @return void
     */
    public function testFixed(): void
    {
        self::assertSame(4, $this->evaluations->evaluateScalar(new FixedScalar(4), $this->context()));
    }

    /**
     * Модификатор характеристики переносит размер.
     *
     * @return void
     */
    public function testCharacteristicModifier(): void
    {
        $context = $this->context();
        $this->assertPair(new CharacteristicNode('strength', 2), $context, 4, 1, 8);
        $this->assertPair(new CharacteristicNode('strength', -3), $context, 5, -1, 2);
        $this->assertPair(new CharacteristicNode('strength', -1), $context, 4, 0, 4);
    }

    /**
     * Нет характеристики — отказ.
     *
     * @return void
     */
    public function testMissingCharacteristic(): void
    {
        $this->expectException(RuleInvalidException::class);
        $this->evaluations->evaluateDimensional(new CharacteristicNode('magic', 1), $this->context());
    }

    /**
     * Уровень способности.
     *
     * @return void
     */
    public function testAbilityLevel(): void
    {
        $context = $this->context();
        self::assertSame(7, $this->evaluations->evaluateScalar(new AbilityLevelScalar('melee-fighting', 2, 1), $context));
        self::assertSame(3, $this->evaluations->evaluateScalar(new AbilityLevelScalar('melee-fighting', null, null), $context));
        self::assertSame(0, $this->evaluations->evaluateScalar(new AbilityLevelScalar('missing', null, null), $context));
    }

    /**
     * Размерная пара сохраняет базу и размер. floor(база × 2^размер) — на паре.
     *
     * @return void
     */
    public function testDimensionalPair(): void
    {
        $context = $this->context();
        $this->assertPair(new DimensionalNode(new DimensionalNumber(3, 1)), $context, 3, 1, 6);
        $this->assertPair(new DimensionalNode(new DimensionalNumber(3, -1)), $context, 3, -1, 1);
        $this->assertPair(new FixedNode(4), $context, 4, 0, 4);
    }

    /**
     * Множитель силы действия умножает базу и сохраняет размер.
     *
     * @return void
     */
    public function testActionCharacteristicMultiplier(): void
    {
        $context = $this->context(['strength' => new CharacteristicNumber(4, 2)]);
        $node = new ActionCharacteristicNode('shoot', 'strength', 5, []);
        $this->assertPair($node, $context, 20, 2, 80);
    }

    /**
     * База действия подменяет характеристику персонажа.
     *
     * @return void
     */
    public function testActionCharacteristicOverride(): void
    {
        $context = $this->context(
            ['strength' => new CharacteristicNumber(5, 0)],
            [],
            ['shoot' => ['strength' => new CharacteristicNumber(3, 0)]],
        );
        $node = new ActionCharacteristicNode('shoot', 'strength', null, [
            new ActionCharacteristicModifier(1, null, null),
            new ActionCharacteristicModifier(1, null, null),
        ]);
        $this->assertPair($node, $context, 5, 0, 5);
    }

    /**
     * Делитель 0 и отсутствующий параметр — отказ.
     *
     * @return void
     */
    public function testParameterFloorDivZero(): void
    {
        $this->expectException(RuleInvalidException::class);
        $this->evaluations->evaluateScalar(new ParameterFloorDivScalar('strength', 0), $this->context());
    }

    /**
     * Параметр умножается на per_unit.
     *
     * @return void
     */
    public function testParameter(): void
    {
        self::assertSame(4, $this->evaluations->evaluateScalar(new ParameterScalar('x', 2), $this->context([], ['x' => 2])));
    }

    /**
     * Нет параметра — отказ.
     *
     * @return void
     */
    public function testMissingParameter(): void
    {
        $this->expectException(RuleInvalidException::class);
        $this->evaluations->evaluateScalar(new ParameterScalar('x', 3), $this->context());
    }

    /**
     * Целая часть параметра.
     *
     * @return void
     */
    public function testParameterFloorDiv(): void
    {
        $context = $this->context([], ['strength' => 5]);
        self::assertSame(2, $this->evaluations->evaluateScalar(new ParameterFloorDivScalar('strength', 2), $context));
    }

    /**
     * to_scalar берёт базу среднего размера, не floor(база × 2^размер).
     *
     * @return void
     */
    public function testToScalar(): void
    {
        $context = $this->context(['intellect' => new CharacteristicNumber(4, 1)]);
        $node = new ToScalar(new CharacteristicNode('intellect', 0));
        self::assertSame(4, $this->evaluations->evaluateScalar($node, $context));
    }

    /**
     * Размер характеристики. Нет ключа — 0.
     *
     * @return void
     */
    public function testCharacteristicSize(): void
    {
        $context = $this->context([
            'dexterity' => new CharacteristicNumber(3, -1),
            'strength' => new CharacteristicNumber(5, 1),
        ]);
        self::assertSame(-1, $this->evaluations->evaluateScalar(new CharacteristicSizeScalar('dexterity'), $context));
        self::assertSame(1, $this->evaluations->evaluateScalar(new CharacteristicSizeScalar('strength'), $context));
        self::assertSame(0, $this->evaluations->evaluateScalar(new CharacteristicSizeScalar('magic'), $this->context()));
    }

    /**
     * Отрицательный размер обрезается.
     *
     * @return void
     */
    public function testCharacteristicSizePositive(): void
    {
        $context = $this->context([
            'intellect' => new CharacteristicNumber(3, -1),
            'perception' => new CharacteristicNumber(5, 1),
        ]);
        self::assertSame(0, $this->evaluations->evaluateScalar(new CharacteristicSizePositiveScalar('intellect'), $context));
        self::assertSame(1, $this->evaluations->evaluateScalar(new CharacteristicSizePositiveScalar('perception'), $context));
    }

    /**
     * Полные размеры между двумя характеристиками.
     *
     * @return void
     */
    public function testCharacteristicSizeGap(): void
    {
        $context = $this->context([
            'strength' => new CharacteristicNumber(3, 1),
            'weight' => new CharacteristicNumber(5, 0),
        ]);
        $node = new CharacteristicSizeGapScalar('strength', 'weight');
        self::assertSame(0, $this->evaluations->evaluateScalar($node, $context));
        self::assertSame(1, $this->evaluations->evaluateScalar($node, $this->context([
            'strength' => new CharacteristicNumber(3, 1),
            'weight' => new CharacteristicNumber(3, 0),
        ])));
        self::assertSame(-1, $this->evaluations->evaluateScalar($node, $this->context([
            'strength' => new CharacteristicNumber(3, 0),
            'weight' => new CharacteristicNumber(3, 1),
        ])));
        self::assertSame(0, $this->evaluations->evaluateScalar($node, $this->context([
            'strength' => new CharacteristicNumber(3, 0),
        ])));
    }

    /**
     * Неизвестный узел — отказ.
     *
     * @return void
     */
    public function testUnknownNode(): void
    {
        $node = new class () implements ScalarFormula {
            public function getNode(): string
            {
                return 'other';
            }
        };
        $this->expectException(RuleInvalidException::class);
        $this->evaluations->evaluateScalar($node, $this->context());
    }

    /**
     * Пара и её прежнее целое.
     *
     * @param DimensionalFormula $node Узел.
     * @param FormulaContext $context Значения вызывающего.
     * @param int $base База.
     * @param int $size Размер.
     * @param int $integer floor(база × 2^размер).
     *
     * @return void
     */
    private function assertPair(
        DimensionalFormula $node,
        FormulaContext $context,
        int $base,
        int $size,
        int $integer,
    ): void {
        $number = $this->evaluations->evaluateDimensional($node, $context);
        self::assertSame($base, $number->getBase());
        self::assertSame($size, $number->getSize());
        self::assertSame($integer, $number->toInteger());
    }
}
