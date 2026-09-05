<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceCommitAssembler;
use PHPUnit\Framework\TestCase;

final class RuleSpaceCommitAssemblerTest extends TestCase
{
    /**
     * Put на месте, keep соседа, новый code в конце.
     *
     * @return void
     */
    public function testPutKeepAndAppendNew(): void
    {
        $human = $this->versionRecord('human', 10);
        $orc = $this->versionRecord('orc', 11);
        $slice = $this->slice([$human, $orc]);
        $selection = RuleSpaceSelection::fromParts(1, [
            RuleCommitEntry::put('human', $this->body('Human 2')),
            RuleCommitEntry::put('elf', $this->body('Elf')),
        ]);
        $entries = (new RuleSpaceCommitAssembler())->assemble($slice, $selection);
        self::assertCount(3, $entries);
        self::assertSame('human', $entries[0]->getCode());
        self::assertSame(11, $entries[1]->getVersionId());
        self::assertSame('elf', $entries[2]->getCode());
    }

    /**
     * Tombstone телом базы; неизвестный removed.
     *
     * @return void
     */
    public function testTombstoneAndUnknownRemoved(): void
    {
        $human = $this->versionRecord('human', 10);
        $slice = $this->slice([$human]);
        $entries = (new RuleSpaceCommitAssembler())->assemble(
            $slice,
            RuleSpaceSelection::fromParts(1, [], ['human']),
        );
        self::assertFalse($entries[0]->isActive());
        self::assertSame('human', $entries[0]->getCode());
        try {
            (new RuleSpaceCommitAssembler())->assemble(
                $slice,
                RuleSpaceSelection::fromParts(1, [], ['missing']),
            );
            self::fail('unknown removed must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Срез из пунктов.
     *
     * @param array<int, RuleVersionRecord> $items Пункты.
     *
     * @return RuleRevisionSlice Срез.
     */
    private function slice(array $items): RuleRevisionSlice
    {
        return new RuleRevisionSlice(
            new RuleRevisionRecord(1, 1, 1, DateTime::fromUnix(1)),
            $items,
        );
    }

    /**
     * Экземпляр в срезе.
     *
     * @param string $code Ключ.
     * @param int $versionId Version.
     *
     * @return RuleVersionRecord Пункт.
     */
    private function versionRecord(string $code, int $versionId): RuleVersionRecord
    {
        return new RuleVersionRecord(
            $versionId,
            1,
            $code,
            true,
            'ability',
            'Name',
            '',
            [],
            [],
            null,
            [],
            'needs_work',
            DateTime::fromUnix(1),
        );
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
