<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests;

use Mifrial\Versioning\Space\Dto\CommitEntry;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use PHPUnit\Framework\TestCase;

final class CommitEntryTest extends TestCase
{
    /**
     * Keep хранит version id.
     *
     * @return void
     */
    public function testKeepStoresVersionId(): void
    {
        $entry = CommitEntry::keep(4);
        self::assertSame(4, $entry->getVersionId());
        self::assertNull($entry->getEntityId());
        self::assertSame([], $entry->getIdentityFields());
        self::assertSame([], $entry->getVersionFields());
    }

    /**
     * Create хранит карты колонок.
     *
     * @return void
     */
    public function testCreateStoresMaps(): void
    {
        $entry = CommitEntry::create(['code' => 'n1'], ['title' => 'Note', 'body' => 'text'], false);
        self::assertNull($entry->getVersionId());
        self::assertNull($entry->getEntityId());
        self::assertSame(['code' => 'n1'], $entry->getIdentityFields());
        self::assertSame(['title' => 'Note', 'body' => 'text'], $entry->getVersionFields());
        self::assertFalse($entry->isActive());
    }

    /**
     * Change хранит entity id.
     *
     * @return void
     */
    public function testChangeStoresEntityId(): void
    {
        $entry = CommitEntry::change(9, ['title' => 'T', 'body' => '']);
        self::assertSame(9, $entry->getEntityId());
        self::assertNull($entry->getVersionId());
        self::assertTrue($entry->isActive());
    }

    /**
     * Служебные ключи в карте → SPACE_INVALID.
     *
     * @return void
     */
    public function testReservedColumnIsInvalid(): void
    {
        try {
            CommitEntry::create([], ['entity_id' => 1]);
            self::fail('reserved column must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }
    }
}
