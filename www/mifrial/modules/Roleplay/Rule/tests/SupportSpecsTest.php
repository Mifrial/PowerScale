<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\SupportSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Узел support: стороны, преимущество и ограничения.
 */
final class SupportSpecsTest extends TestCase
{
    /**
     * Минимальный узел получает источник обстоятельств.
     *
     * @return void
     */
    public function testMinimalSupportDefaults(): void
    {
        $support = SupportSpecs::read([
            'type' => 'action',
            'support' => ['sides' => ['attacker', 'defender'], 'advantage' => 2],
        ]);

        self::assertNotNull($support);
        self::assertSame(['attacker', 'defender'], $support->getSides());
        self::assertSame(2, $support->getAdvantage());
        self::assertNull($support->getProfile());
        self::assertNull($support->getMinMastery());
        self::assertSame('circumstances', $support->getSource());
    }

    /**
     * Профиль и минимум мастерства читаются из спеки.
     *
     * @return void
     */
    public function testSupportReadsProfileAndMastery(): void
    {
        $support = SupportSpecs::read([
            'type' => 'action',
            'support' => [
                'sides' => ['attacker'],
                'advantage' => 2,
                'profile' => 'strike',
                'min_mastery' => ['base' => 4, 'size' => 0],
                'source' => 'training',
            ],
        ]);

        self::assertNotNull($support);
        self::assertSame('strike', $support->getProfile());
        self::assertSame(4, $support->getMinMastery()?->getBase());
        self::assertSame(0, $support->getMinMastery()?->getSize());
        self::assertSame('training', $support->getSource());
    }

    /**
     * Чужая сторона или профиль — битая спека.
     *
     * @return void
     */
    public function testSupportRejectsUnknownSideAndProfile(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        SupportSpecs::read([
            'type' => 'action',
            'support' => ['sides' => ['bystander'], 'advantage' => 1],
        ]);
    }

    /**
     * Чужой профиль — битая спека.
     *
     * @return void
     */
    public function testSupportRejectsUnknownProfile(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        SupportSpecs::read([
            'type' => 'action',
            'support' => ['sides' => ['attacker'], 'advantage' => 1, 'profile' => 'magic'],
        ]);
    }
}
