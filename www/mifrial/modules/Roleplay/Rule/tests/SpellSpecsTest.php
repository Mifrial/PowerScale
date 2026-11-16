<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSenseEffect;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\SpellSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Чтение эффектов заклинания.
 */
final class SpellSpecsTest extends TestCase
{
    /**
     * Эффект чувства разбирается с радиусом и значением.
     *
     * @return void
     */
    public function testSenseEffectIsParsed(): void
    {
        $spec = SpellSpecs::read([
            'spell' => [
                'power' => ['base' => 4, 'size' => -1],
                'control' => ['base' => 4, 'size' => 0],
                'duration' => ['type' => 'sustained', 'power' => ['base' => 4, 'size' => -1]],
                'effects' => [
                    [
                        'type' => 'sense',
                        'sense_code' => 'magic-sensation',
                        'status' => 'imprecise',
                        'radius' => ['from' => 'sustain_power'],
                        'value' => [
                            'from' => 'control',
                            'reference' => ['base' => 4, 'size' => 1],
                        ],
                    ],
                ],
            ],
        ]);

        $effects = $spec->getEffects();
        self::assertCount(1, $effects);
        $effect = $effects[0];
        self::assertInstanceOf(SpellSenseEffect::class, $effect);
        self::assertSame('sense', $effect->getType());
        self::assertSame('magic-sensation', $effect->getSenseCode());
        self::assertSame('imprecise', $effect->getStatus());
        self::assertSame('sustain_power', $effect->getRadius()?->getFrom());
        self::assertSame('control', $effect->getValue()?->getFrom());
        self::assertSame(4, $effect->getValue()?->getReference()?->getBase());
        self::assertSame(1, $effect->getValue()?->getReference()?->getSize());
    }

    /**
     * Без effects список пуст.
     *
     * @return void
     */
    public function testNoEffectsIsEmpty(): void
    {
        $spec = SpellSpecs::read($this->spell());

        self::assertSame([], $spec->getEffects());
    }

    /**
     * Неизвестный тип эффекта — отказ.
     *
     * @return void
     */
    public function testUnknownEffectTypeIsRejected(): void
    {
        $spell = $this->spell();
        $spell['spell']['effects'] = [['type' => 'damage']];

        $this->expectException(RuleSpecShapeException::class);

        SpellSpecs::read($spell);
    }

    /**
     * Неизвестный источник величины — отказ.
     *
     * @return void
     */
    public function testUnknownEffectSourceIsRejected(): void
    {
        $spell = $this->spell();
        $spell['spell']['effects'] = [
            [
                'type' => 'sense',
                'sense_code' => 'magic-sensation',
                'radius' => ['from' => 'volume'],
            ],
        ];

        $this->expectException(RuleSpecShapeException::class);

        SpellSpecs::read($spell);
    }

    /**
     * Пустой код чувства — отказ.
     *
     * @return void
     */
    public function testEmptySenseCodeIsRejected(): void
    {
        $spell = $this->spell();
        $spell['spell']['effects'] = [['type' => 'sense', 'sense_code' => '']];

        $this->expectException(RuleSpecShapeException::class);

        SpellSpecs::read($spell);
    }

    /**
     * Параметр без кода — отказ.
     *
     * @return void
     */
    public function testParameterSourceWithoutCodeIsRejected(): void
    {
        $spell = $this->spell();
        $spell['spell']['effects'] = [
            [
                'type' => 'sense',
                'sense_code' => 'magic-sensation',
                'value' => ['from' => 'parameter'],
            ],
        ];

        $this->expectException(RuleSpecShapeException::class);

        SpellSpecs::read($spell);
    }

    /**
     * Код параметра у не-параметрического источника — отказ.
     *
     * @return void
     */
    public function testParameterCodeOnOtherSourceIsRejected(): void
    {
        $spell = $this->spell();
        $spell['spell']['effects'] = [
            [
                'type' => 'sense',
                'sense_code' => 'magic-sensation',
                'radius' => ['from' => 'power', 'parameter_code' => 'x'],
            ],
        ];

        $this->expectException(RuleSpecShapeException::class);

        SpellSpecs::read($spell);
    }

    /**
     * Минимальная карточка заклинания.
     *
     * @return array<string, mixed> Документ.
     */
    private function spell(): array
    {
        return [
            'spell' => [
                'power' => ['base' => 4, 'size' => -1],
                'control' => ['base' => 4, 'size' => 0],
                'duration' => ['type' => 'sustained'],
            ],
        ];
    }
}
