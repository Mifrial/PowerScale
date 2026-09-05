<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use PHPUnit\Framework\TestCase;

final class RuleSpaceUnitTest extends TestCase
{
    /**
     * Листья ошибок RULESPACE_*.
     *
     * @return void
     */
    public function testErrorCodes(): void
    {
        self::assertSame('RULESPACE_INVALID', (new RuleSpaceInvalidException())->getErrorCode());
        self::assertSame('RULESPACE_NOT_FOUND', (new RuleSpaceNotFoundException())->getErrorCode());
    }

    /**
     * Patch отвергает неизвестный ключ и не-строку.
     *
     * @return void
     */
    public function testPatchRejectsUnknownField(): void
    {
        try {
            RuleSpacePatch::fromNormalized(['code' => 'x']);
            self::fail('unknown patch key must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            RuleSpacePatch::fromNormalized(['name' => 1]);
            self::fail('non-string patch must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Selection: keep в puts, пересечение с removed, пустой code.
     *
     * @return void
     */
    public function testSelectionRejectsKeepAndOverlap(): void
    {
        try {
            RuleSpaceSelection::fromParts(0, []);
            self::fail('base 0 must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            RuleSpaceSelection::fromParts(1, [RuleCommitEntry::keep(1)]);
            self::fail('keep in puts must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        $put = RuleCommitEntry::put('human', $this->body('Human'));
        try {
            RuleSpaceSelection::fromParts(1, [$put], ['human']);
            self::fail('put and removed overlap must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            RuleSpaceSelection::fromParts(1, [], ['  ']);
            self::fail('empty removed code must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Цикл parent_code и неизвестная секция.
     *
     * @return void
     */
    public function testCatalogRejectsCycle(): void
    {
        try {
            RuleSpaceCatalog::fromParts([
                new RuleSpaceCatalogSection('a', 'A', 'b', 0, null),
                new RuleSpaceCatalogSection('b', 'B', 'a', 1, null),
            ], []);
            self::fail('cycle must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            RuleSpaceCatalog::fromParts(
                [new RuleSpaceCatalogSection('a', 'A', null, 0, null)],
                [new RuleSpaceCatalogPlacement('human', 'missing', 0)],
            );
            self::fail('unknown section must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Тело put.
     *
     * @param string $name Подпись.
     *
     * @return RuleVersionBody Снимок.
     */
    private function body(string $name): RuleVersionBody
    {
        return new RuleVersionBody('ability', $name, '', [], [], null, [], 'needs_work');
    }
}
