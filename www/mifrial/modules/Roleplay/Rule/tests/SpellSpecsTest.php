<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSenseEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellStateEffect;
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
     * Эффект состояния разбирается с источником единиц.
     *
     * @return void
     */
    public function testStateEffectIsParsed(): void
    {
        $spec = SpellSpecs::read([
            'spell' => [
                'duration' => ['type' => 'lingering', 'limit' => ['value' => 1, 'unit' => 'turn']],
                'effects' => [[
                    'type' => 'state',
                    'state_code' => 'blindness',
                    'value' => ['from' => 'power'],
                    'max' => 3,
                ]],
            ],
        ]);

        $effect = $spec->getEffects()[0];
        self::assertInstanceOf(SpellStateEffect::class, $effect);
        self::assertSame('state', $effect->getType());
        self::assertSame('blindness', $effect->getStateCode());
        self::assertSame('power', $effect->getValue()?->getFrom());
        self::assertSame(3, $effect->getMax());
    }

    /**
     * Пустой код состояния — отказ.
     *
     * @return void
     */
    public function testEmptyStateCodeIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        SpellSpecs::read([
            'spell' => [
                'duration' => ['type' => 'lingering', 'limit' => ['value' => 1, 'unit' => 'turn']],
                'effects' => [['type' => 'state', 'state_code' => '']],
            ],
        ]);
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
     * Флаг сопротивления магии читается, по умолчанию false.
     *
     * @return void
     */
    public function testResistedByMagicIsParsed(): void
    {
        $spell = $this->spell();
        $spell['spell']['resisted_by_magic'] = true;
        self::assertTrue(SpellSpecs::read($spell)->isResistedByMagic());

        $plain = $this->spell();
        self::assertFalse(SpellSpecs::read($plain)->isResistedByMagic());
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
