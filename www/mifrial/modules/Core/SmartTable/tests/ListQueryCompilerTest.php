<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Closure;
use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Service\Query\ListQueryCompiler;
use Mifrial\Core\SmartTable\Tests\Fixture\ChildRestrictTable;
use Mifrial\Core\SmartTable\Tests\Fixture\CrudProbeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ListMultipleTable;
use PHPUnit\Framework\TestCase;

final class ListQueryCompilerTest extends TestCase
{
    /**
     * Проверяет отказы compiler по карте без SQL.
     *
     * @return void
     */
    public function testCompilerRejectsUnknownAndTypeMismatches(): void
    {
        $compiler = new ListQueryCompiler();
        $table = new CrudProbeTable();
        $cases = [
            ['limit' => 10, 'filter' => ['nope' => 1]],
            ['limit' => 10, 'filter' => ['%age' => '%x%']],
            ['limit' => 10, 'filter' => ['id' => []]],
            ['limit' => 10, 'filter' => ['active' => [true]]],
            ['limit' => 10, 'filter' => ['@title' => 'a']],
            ['limit' => 10, 'filter' => ['!=id' => [1, 2]]],
            ['limit' => 10, 'filter' => ['<id' => [1, 2]]],
            ['limit' => 10, 'filter' => ['%title' => ['a', 'b']]],
        ];
        foreach ($cases as $options) {
            try {
                $compiler->applyWhere($this->invokingBuilder(), ListQuery::fromOptions($options), $table);
                self::fail('compiler must reject options');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * JSON list остаётся равенством, не IN.
     *
     * @return void
     */
    public function testJsonListUsesEquality(): void
    {
        $compiler = new ListQueryCompiler();
        $query = $this->invokingBuilder();
        $query->expects(self::never())->method('whereIn');
        $compiler->applyWhere(
            $query,
            ListQuery::fromOptions(['limit' => 10, 'filter' => ['payload' => []]]),
            new CrudProbeTable(),
        );
    }

    /**
     * Int list вызывает whereIn.
     *
     * @return void
     */
    public function testIntListUsesWhereIn(): void
    {
        $compiler = new ListQueryCompiler();
        $query = $this->invokingBuilder();
        $query->expects(self::once())->method('whereIn');
        $compiler->applyWhere(
            $query,
            ListQuery::fromOptions(['limit' => 10, 'filter' => ['id' => [1, 2]]]),
            new CrudProbeTable(),
        );
    }

    /**
     * Contains на reference недопустим.
     *
     * @return void
     */
    public function testReferenceRejectsContains(): void
    {
        $compiler = new ListQueryCompiler();
        try {
            $compiler->applyWhere(
                $this->invokingBuilder(),
                ListQuery::fromOptions(['limit' => 10, 'filter' => ['%parent_id' => '%1%']]),
                new ChildRestrictTable(),
            );
            self::fail('contains on reference must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Sort и @ на multiple.
     *
     * @return void
     */
    public function testMultipleSortAndFilter(): void
    {
        $compiler = new ListQueryCompiler();
        $table = new ListMultipleTable();
        $query = $this->invokingBuilder();

        try {
            $compiler->applyOrder(
                $query,
                ListQuery::fromOptions(['limit' => 10, 'sort' => ['tags' => 'asc']]),
                $table,
            );
            self::fail('sort multiple must fail');
        } catch (FieldMultipleUnsupportedException $exception) {
            self::assertSame('FIELD_MULTIPLE_UNSUPPORTED', $exception->getErrorCode());
        }

        try {
            $compiler->applyWhere(
                $this->invokingBuilder(),
                ListQuery::fromOptions(['limit' => 10, 'filter' => ['!=tags' => 'a']]),
                $table,
            );
            self::fail('!= multiple must fail');
        } catch (FieldMultipleUnsupportedException $exception) {
            self::assertSame('FIELD_MULTIPLE_UNSUPPORTED', $exception->getErrorCode());
        }

        $query = $this->invokingBuilder();
        $query->expects(self::once())->method('whereExists');
        $compiler->applyWhere(
            $query,
            ListQuery::fromOptions(['limit' => 10, 'filter' => ['@tags' => 'a']]),
            $table,
        );

        $listQuery = $this->invokingBuilder();
        $listQuery->expects(self::exactly(2))->method('whereExists');
        $compiler->applyWhere(
            $listQuery,
            ListQuery::fromOptions(['limit' => 10, 'filter' => ['LOGIC' => 'OR', ['@tags' => ['a', 'b']]]]),
            $table,
        );

        $equalsQuery = $this->invokingBuilder();
        $equalsQuery->expects(self::once())->method('whereRaw');
        $compiler->applyWhere(
            $equalsQuery,
            ListQuery::fromOptions(['limit' => 10, 'filter' => ['LOGIC' => 'OR', ['tags' => ['a', 'b']]]]),
            $table,
        );
    }

    /**
     * Мок билдера, который исполняет nested where-замыкания.
     *
     * @return Builder&object Мок.
     */
    private function invokingBuilder(): Builder
    {
        $query = $this->createMock(Builder::class);
        $invokeNested = static function (mixed $column) use ($query): Builder {
            if ($column instanceof Closure) {
                $column($query);
            }

            return $query;
        };
        $query->method('where')->willReturnCallback($invokeNested);
        $query->method('orWhere')->willReturnCallback($invokeNested);
        $query->method('whereIn')->willReturn($query);
        $query->method('whereNull')->willReturn($query);
        $query->method('whereNotNull')->willReturn($query);
        $query->method('whereBetween')->willReturn($query);
        $query->method('whereExists')->willReturnCallback($invokeNested);
        $query->method('whereNotExists')->willReturnCallback($invokeNested);
        $query->method('whereRaw')->willReturn($query);
        $query->method('from')->willReturn($query);
        $query->method('whereColumn')->willReturn($query);

        return $query;
    }
}
