<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;
use PHPUnit\Framework\TestCase;

/**
 * Область эффекта: компонент и первые попадания.
 */
final class AttackScopeTest extends TestCase
{
    /**
     * Чужой компонент не проходит даже при all.
     *
     * @return void
     */
    public function testForeignComponentDoesNotApply(): void
    {
        $scope = new AttackScope(['strike'], 'all');

        self::assertTrue($scope->appliesTo('strike', 1));
        self::assertFalse($scope->appliesTo('throw', 1));
        self::assertFalse($scope->appliesTo('shoot', 3));
    }

    /**
     * hit_count — число первых попаданий, а не номер.
     *
     * @return void
     */
    public function testHitCountCoversFirstHits(): void
    {
        $single = new AttackScope(['strike'], 1);
        self::assertTrue($single->appliesTo('strike', 1));
        self::assertFalse($single->appliesTo('strike', 2));

        $pair = new AttackScope(['strike'], 2);
        self::assertTrue($pair->appliesTo('strike', 1));
        self::assertTrue($pair->appliesTo('strike', 2));
        self::assertFalse($pair->appliesTo('strike', 3));
    }

    /**
     * all накрывает любое попадание, ноль — ни одного.
     *
     * @return void
     */
    public function testAllCoversEveryHitAndZeroCoversNone(): void
    {
        $all = new AttackScope(['strike'], 'all');
        self::assertTrue($all->appliesTo('strike', 1));
        self::assertTrue($all->appliesTo('strike', 7));

        $none = new AttackScope([], 0);
        self::assertFalse($none->appliesTo('strike', 1));
    }
}
