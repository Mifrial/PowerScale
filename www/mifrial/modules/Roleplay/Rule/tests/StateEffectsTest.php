<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\State\SenseStatusEffect;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\StateEffects;
use PHPUnit\Framework\TestCase;

/**
 * Чтение эффектов состояния.
 */
final class StateEffectsTest extends TestCase
{
    /**
     * Статус чувства разбирается, по умолчанию — отсутствие.
     *
     * @return void
     */
    public function testSenseStatusIsParsed(): void
    {
        $effects = StateEffects::list([
            'effects' => [
                ['type' => 'sense_status', 'sense_code' => 'sense-vision'],
                ['type' => 'sense_status', 'sense_code' => 'sense-hearing', 'status' => 'vague'],
            ],
        ]);

        self::assertInstanceOf(SenseStatusEffect::class, $effects[0]);
        self::assertSame('sense-vision', $effects[0]->getSenseCode());
        self::assertSame('absent', $effects[0]->getStatus());
        self::assertInstanceOf(SenseStatusEffect::class, $effects[1]);
        self::assertSame('vague', $effects[1]->getStatus());
    }

    /**
     * Пустой код чувства — отказ.
     *
     * @return void
     */
    public function testEmptySenseCodeIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        StateEffects::list([
            'effects' => [['type' => 'sense_status', 'sense_code' => '']],
        ]);
    }

    /**
     * Неизвестный статус — отказ, а не молчаливое понижение.
     *
     * @return void
     */
    public function testUnknownStatusIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        StateEffects::list([
            'effects' => [[
                'type' => 'sense_status',
                'sense_code' => 'sense-vision',
                'status' => 'blind',
            ]],
        ]);
    }
}
