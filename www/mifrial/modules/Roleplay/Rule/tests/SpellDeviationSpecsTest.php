<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\SpellDeviationSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Чтение payload механики магического отклонения.
 */
final class SpellDeviationSpecsTest extends TestCase
{
    /**
     * Полный payload разбирается вместе со взрывом.
     *
     * @return void
     */
    public function testFullPayloadIsParsed(): void
    {
        $spec = SpellDeviationSpecs::read($this->payload());

        self::assertSame(2, $spec->getDiceCount());
        self::assertSame(6, $spec->getDieFaces());
        self::assertSame(7, $spec->getNeutralSum());
        self::assertSame('core-magic-deviation', $spec->getStateRuleCode());
        self::assertNotNull($spec->getBurst());
        self::assertSame('arcane', $spec->getBurst()->getDamageTypeCode());
        self::assertSame(1, $spec->getBurst()->getSuccessRating());
    }

    /**
     * Без взрыва остаётся только состояние.
     *
     * @return void
     */
    public function testPayloadWithoutBurstIsParsed(): void
    {
        $payload = $this->payload();
        unset($payload['data']['burst']);

        $spec = SpellDeviationSpecs::read($payload);

        self::assertNull($spec->getBurst());
        self::assertSame('core-magic-deviation', $spec->getStateRuleCode());
    }

    /**
     * Нет data — форма чужая.
     *
     * @return void
     */
    public function testPayloadWithoutDataIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        SpellDeviationSpecs::read(['type' => 'spell_deviation']);
    }

    /**
     * Ноль кубов недопустим.
     *
     * @return void
     */
    public function testZeroDiceCountIsRejected(): void
    {
        $payload = $this->payload();
        $payload['data']['dice_count'] = 0;
        $this->expectException(RuleSpecShapeException::class);

        SpellDeviationSpecs::read($payload);
    }

    /**
     * Одна грань не куб.
     *
     * @return void
     */
    public function testOneFaceDieIsRejected(): void
    {
        $payload = $this->payload();
        $payload['data']['die_faces'] = 1;
        $this->expectException(RuleSpecShapeException::class);

        SpellDeviationSpecs::read($payload);
    }

    /**
     * Пустой код состояния недопустим.
     *
     * @return void
     */
    public function testEmptyStateCodeIsRejected(): void
    {
        $payload = $this->payload();
        $payload['data']['state_rule_code'] = '';
        $this->expectException(RuleSpecShapeException::class);

        SpellDeviationSpecs::read($payload);
    }

    /**
     * Нулевой рейтинг взрыва недопустим.
     *
     * @return void
     */
    public function testZeroBurstRatingIsRejected(): void
    {
        $payload = $this->payload();
        $payload['data']['burst']['success_rating'] = 0;
        $this->expectException(RuleSpecShapeException::class);

        SpellDeviationSpecs::read($payload);
    }

    /**
     * Строка вместо числа в кубах недопустима.
     *
     * @return void
     */
    public function testStringDiceCountIsRejected(): void
    {
        $payload = $this->payload();
        $payload['data']['dice_count'] = '2';
        $this->expectException(RuleSpecShapeException::class);

        SpellDeviationSpecs::read($payload);
    }

    /**
     * Полный payload карточки.
     *
     * @return array<string, mixed> Payload.
     */
    private function payload(): array
    {
        return [
            'type' => 'spell_deviation',
            'data' => [
                'dice_count' => 2,
                'die_faces' => 6,
                'neutral_sum' => 7,
                'state_rule_code' => 'core-magic-deviation',
                'burst' => [
                    'damage_type_code' => 'arcane',
                    'success_rating' => 1,
                ],
            ],
        ];
    }
}
