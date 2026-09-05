<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Cache\CacheConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use PHPUnit\Framework\TestCase;

final class TableCacheTest extends TestCase
{
    private string $cachePath = '';

    private int $now = 1_700_000_000;

    /**
     * Готовит каталог file-store.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/mifrial-st-cache-' . uniqid('', true);
    }

    /**
     * Чистит каталог file-store.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDirectory($this->cachePath);
        $blocked = $this->cachePath . '-blocked';
        if (is_file($blocked)) {
            unlink($blocked);
        }
    }

    /**
     * Попадание, промах, expire по подставленным часам.
     *
     * @return void
     */
    public function testHitMissAndExpire(): void
    {
        $tableCache = $this->makeCache(true);
        $tableCache->saveGet('users', 1, ['title' => 'a'], 10);
        self::assertTrue($tableCache->lookupGet('users', 1)->found());
        self::assertSame(['title' => 'a'], $tableCache->lookupGet('users', 1)->value());
        self::assertFalse($tableCache->lookupGet('users', 2)->found());

        $this->now += 11;
        self::assertFalse($tableCache->lookupGet('users', 1)->found());
    }

    /**
     * Тег поля сбрасывает свой список, не чужой.
     *
     * @return void
     */
    public function testFieldTagFlushIsOr(): void
    {
        $tableCache = $this->makeCache(true);
        $titleList = new ListResult([['title' => 'a']], null);
        $ageList = new ListResult([['age' => 1]], null);
        $tableCache->saveTagged('users', 'k-title', $titleList, 60, ['title']);
        $tableCache->saveTagged('users', 'k-age', $ageList, 60, ['age']);
        $tableCache->noteUpdate('users', 5, ['title']);

        self::assertFalse($tableCache->lookupTagged('k-title')->found());
        self::assertTrue($tableCache->lookupTagged('k-age')->found());
        self::assertFalse($tableCache->lookupGet('users', 5)->found());
    }

    /**
     * add бьёт списки тегом стола; DEL get.
     *
     * @return void
     */
    public function testAddFlushesListsAndDeleteRemovesGet(): void
    {
        $tableCache = $this->makeCache(true);
        $tableCache->saveTagged('users', 'k-list', new ListResult([], 0), 60, ['id', 'title']);
        $tableCache->saveGet('users', 3, ['title' => 'x'], 60);
        $tableCache->noteAdd('users');
        self::assertFalse($tableCache->lookupTagged('k-list')->found());
        self::assertTrue($tableCache->lookupGet('users', 3)->found());

        $tableCache->noteDelete('users', 3);
        self::assertFalse($tableCache->lookupGet('users', 3)->found());
    }

    /**
     * Delete родителя бьёт get и списки CASCADE/SET NULL-детей, не чужой стол.
     *
     * @return void
     */
    public function testDeleteFlushesDependentRowsAndLists(): void
    {
        $tableCache = $this->makeCache(true);
        $tableCache->saveTagged('st_child', 'k-child', new ListResult([['id' => 2]], null), 60, ['id']);
        $tableCache->saveGet('st_child', 2, ['parent_id' => 1], 60);
        $tableCache->saveGet('st_other', 9, ['x' => 1], 60);
        $tableCache->noteDelete('st_parent', 1, static fn (): array => ['st_child']);
        self::assertFalse($tableCache->lookupTagged('k-child')->found());
        self::assertFalse($tableCache->lookupGet('st_child', 2)->found());
        self::assertTrue($tableCache->lookupGet('st_other', 9)->found());
    }

    /**
     * null get кэшируется; пустой path не валит invalidate.
     *
     * @return void
     */
    public function testNullGetAndEmptyPathInvalidate(): void
    {
        $tableCache = $this->makeCache(true);
        $tableCache->saveGet('users', 9, null, 60);
        $cacheHit = $tableCache->lookupGet('users', 9);
        self::assertTrue($cacheHit->found());
        self::assertNull($cacheHit->value());

        $empty = new TableCache(
            CacheSettings::fromConfig(null),
            true,
            static fn (): int => 0,
            fn (): int => $this->now,
        );
        $empty->noteAdd('users');
        try {
            $empty->saveGet('users', 1, ['a' => 1], 10);
            self::fail('empty path must throw on TTL write');
        } catch (CacheConfigInvalidException $exception) {
            self::assertSame('CACHE_CONFIG_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Неизвестный driver — CONFIG всегда.
     *
     * @return void
     */
    public function testUnknownDriverThrowsConfigInvalid(): void
    {
        $tableCache = new TableCache(
            CacheSettings::fromConfig(['driver' => 'memcached', 'path' => $this->cachePath]),
            true,
            static fn (): int => 0,
            fn (): int => $this->now,
        );
        try {
            $tableCache->lookupGet('users', 1);
            self::fail('unknown driver must throw');
        } catch (CacheConfigInvalidException $exception) {
            self::assertSame('CACHE_CONFIG_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * AggregateResult с DateTime в ряду переживает слот.
     *
     * @return void
     */
    public function testTaggedAggregateResultKeepsDateTime(): void
    {
        $tableCache = $this->makeCache(true);
        $stamp = UnixDateTime::fromUnix(1_700_000_010);
        $tableCache->saveTagged(
            'st_agg_item',
            'k-agg',
            new AggregateResult([['group_id' => 10, 'last_at' => $stamp]]),
            60,
            ['created'],
        );
        $cacheHit = $tableCache->lookupTagged('k-agg');
        self::assertTrue($cacheHit->found());
        $value = $cacheHit->value();
        self::assertInstanceOf(AggregateResult::class, $value);
        $lastAt = $value->rows()[0]['last_at'];
        self::assertInstanceOf(UnixDateTime::class, $lastAt);
        self::assertSame(1_700_000_010, $lastAt->toUnix());
        $tableCache->noteUpdate('st_agg_item', 1, ['created']);
        self::assertFalse($tableCache->lookupTagged('k-agg')->found());
    }

    /**
     * В транзакции не читаем и не пишем; rollback забывает pending.
     *
     * @return void
     */
    public function testTransactionPendingAndRollback(): void
    {
        $level = 0;
        $tableCache = new TableCache(
            CacheSettings::fromConfig(['driver' => 'file', 'path' => $this->cachePath]),
            true,
            static function () use (&$level): int {
                return $level;
            },
            fn (): int => $this->now,
        );
        $tableCache->saveTagged('users', 'k-title', new ListResult([['title' => 'a']], null), 60, ['title']);
        $level = 1;
        $tableCache->saveGet('users', 1, ['title' => 'b'], 60);
        self::assertFalse($tableCache->lookupGet('users', 1)->found());
        $tableCache->noteAdd('users');
        $tableCache->settleTransaction(false);
        $level = 0;
        self::assertTrue($tableCache->lookupTagged('k-title')->found());

        $level = 1;
        $tableCache->noteAdd('users');
        $level = 0;
        $tableCache->settleTransaction(true);
        self::assertFalse($tableCache->lookupTagged('k-title')->found());
    }

    /**
     * I/O: debug кидает, без debug — miss/no-op.
     *
     * @return void
     */
    public function testDriverFailSoftDependsOnDebug(): void
    {
        $blocked = $this->cachePath . '-blocked';
        file_put_contents($blocked, 'not-a-dir');
        $blockedSettings = CacheSettings::fromConfig(['driver' => 'file', 'path' => $blocked]);
        $debugCache = new TableCache($blockedSettings, true, static fn (): int => 0);
        try {
            $debugCache->saveGet('users', 1, ['a' => 1], 10);
            self::fail('debug must throw on driver fail');
        } catch (CacheDriverFailedException $exception) {
            self::assertSame('CACHE_DRIVER_FAILED', $exception->getErrorCode());
        }

        $quietCache = new TableCache($blockedSettings, false, static fn (): int => 0);
        $quietCache->saveGet('users', 1, ['a' => 1], 10);
        self::assertFalse($quietCache->lookupGet('users', 1)->found());
    }

    /**
     * Собирает file TableCache.
     *
     * @param bool $debug Исключения I/O.
     *
     * @return TableCache Кэш.
     */
    private function makeCache(bool $debug): TableCache
    {
        return new TableCache(
            CacheSettings::fromConfig(['driver' => 'file', 'path' => $this->cachePath]),
            $debug,
            static fn (): int => 0,
            fn (): int => $this->now,
        );
    }

    /**
     * Рекурсивно удаляет каталог.
     *
     * @param string $directory Путь.
     *
     * @return void
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
