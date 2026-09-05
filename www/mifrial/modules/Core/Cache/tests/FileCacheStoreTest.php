<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Tests;

use Mifrial\Core\Cache\Exception\CacheInvalidException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Cache\Service\FileCacheStore;
use Mifrial\Core\Cache\Service\UnusableCacheStore;
use PHPUnit\Framework\TestCase;

final class FileCacheStoreTest extends TestCase
{
    private string $cachePath = '';

    private int $now = 1_700_000_000;

    /**
     * Готовит каталог.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/mifrial-cache-' . uniqid('', true);
    }

    /**
     * Чистит каталог.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDirectory($this->cachePath);
    }

    /**
     * Пишет, читает, сохраняет переводы строк в payload, expire по часам.
     *
     * @return void
     */
    public function testWriteReadNewlinePayloadAndExpire(): void
    {
        $store = $this->store();
        $payload = "a\nb\n" . serialize(['ok' => true]);
        $store->write('k1', $payload, 10, ['tag-a']);
        self::assertSame($payload, $store->read('k1'));
        $this->now += 11;
        self::assertNull($store->read('k1'));
    }

    /**
     * flushTags снимает ключи тега.
     *
     * @return void
     */
    public function testFlushTagsRemovesMembers(): void
    {
        $store = $this->store();
        $store->write('k1', 'one', 60, ['t1']);
        $store->write('k2', 'two', 60, ['t2']);
        $store->flushTags(['t1']);
        self::assertNull($store->read('k1'));
        self::assertSame('two', $store->read('k2'));
    }

    /**
     * Пустые delete/flush — no-op; пустой ключ и TTL вне диапазона — отказ.
     *
     * @return void
     */
    public function testEmptyOpsAndInvalidArgs(): void
    {
        $store = $this->store();
        $store->deleteKeys([]);
        $store->flushTags([]);
        try {
            $store->write('', 'x', 10);
            self::fail('empty key must throw');
        } catch (CacheInvalidException $exception) {
            self::assertSame('CACHE_INVALID', $exception->getErrorCode());
        }

        try {
            $store->write('k', 'x', 0);
            self::fail('ttl 0 must throw');
        } catch (CacheInvalidException $exception) {
            self::assertSame('CACHE_INVALID', $exception->getErrorCode());
        }

        try {
            $store->write('k', 'x', ICacheStore::MAX_TTL_SECONDS + 1);
            self::fail('ttl above max must throw');
        } catch (CacheInvalidException $exception) {
            self::assertSame('CACHE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Непригодный store не бросает на get-фабрике.
     *
     * @return void
     */
    public function testUnusableSkipsEmptyAndThrowsOnWrite(): void
    {
        $store = new UnusableCacheStore();
        self::assertFalse($store->isUsable());
        $store->deleteKeys([]);
        $store->flushTags([]);
        try {
            $store->read('k');
            self::fail('unusable read must throw');
        } catch (CacheInvalidException $exception) {
            self::assertSame('CACHE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * File-store с часами теста.
     *
     * @return FileCacheStore Store.
     */
    private function store(): FileCacheStore
    {
        return new FileCacheStore($this->cachePath, fn (): int => $this->now);
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
