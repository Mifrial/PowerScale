<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Cache;

use Closure;
use Mifrial\Core\Cache\Exception\CacheInvalidException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\SmartTable\Dto\CacheHit;
use Mifrial\Core\SmartTable\Exception\Cache\CacheConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Mifrial\Core\SmartTable\Service\Query\ListCacheFieldTags;
use Throwable;

/**
 * Сценарий кэша get и запросов: ключи, теги OR, pending транзакции, fail-soft.
 */
final class TableCache
{
    private readonly CachePayload $cachePayload;

    private readonly CacheFailSoft $failSoft;

    /**
     * @var array<int, array{tags?: array<int, string>, keys?: array<int, string>}>
     */
    private array $pendingOps = [];

    /**
     * Создаёт кэш таблицы.
     *
     * @param ICacheStore $cacheStore Драйвер Core/Cache.
     * @param bool $debug Кидать I/O или глотать.
     * @param Closure $transactionLevel Уровень транзакции соединения.
     *
     * @return void
     */
    public function __construct(
        private readonly ICacheStore $cacheStore,
        bool $debug,
        private readonly Closure $transactionLevel,
    ) {
        $this->cachePayload = new CachePayload();
        $this->failSoft = new CacheFailSoft($debug);
    }

    /**
     * Читает кэш строки.
     *
     * @param string $tableName Физическое имя.
     * @param int $rowId Идентификатор.
     *
     * @return CacheHit Попадание или промах.
     *
     * @throws CacheConfigInvalidException Если store непригоден.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function lookupGet(string $tableName, int $rowId): CacheHit
    {
        return $this->isTransactionOpen()
            ? new CacheHit(false, null)
            : $this->lookupValue($tableName . ':get:' . $rowId);
    }

    /**
     * Пишет кэш строки, включая null.
     *
     * @param string $tableName Физическое имя.
     * @param int $rowId Идентификатор.
     * @param array<string, mixed>|null $row Строка.
     * @param int $cacheTtl Секунды жизни.
     *
     * @return void
     *
     * @throws CacheConfigInvalidException Если store непригоден.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function saveGet(string $tableName, int $rowId, ?array $row, int $cacheTtl): void
    {
        if (!$this->isTransactionOpen()) {
            $this->saveValue($tableName . ':get:' . $rowId, $row, $cacheTtl, ['st:' . $tableName . ':rows']);
        }
    }

    /**
     * Читает кэш запроса по готовому ключу.
     *
     * @param string $cacheKey Ключ.
     *
     * @return CacheHit Попадание или промах.
     *
     * @throws CacheConfigInvalidException Если store непригоден.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function lookupTagged(string $cacheKey): CacheHit
    {
        return $this->isTransactionOpen()
            ? new CacheHit(false, null)
            : $this->lookupValue($cacheKey);
    }

    /**
     * Пишет кэш запроса с тегами стола и полей.
     *
     * @param string $tableName Физическое имя своей карты.
     * @param string $cacheKey Ключ.
     * @param mixed $value Результат.
     * @param int $cacheTtl Секунды жизни.
     * @param array<int, string> $fieldTags Поля или пары table:field.
     *
     * @return void
     *
     * @throws CacheConfigInvalidException Если store непригоден.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function saveTagged(
        string $tableName,
        string $cacheKey,
        mixed $value,
        int $cacheTtl,
        array $fieldTags,
    ): void {
        if ($this->isTransactionOpen()) {
            return;
        }

        $tagNames = (new ListCacheFieldTags())->storeTags($tableName, $fieldTags);
        $this->saveValue($cacheKey, $value, $cacheTtl, $tagNames);
    }

    /**
     * Копит или сразу сбрасывает тег стола после add.
     *
     * @param string $tableName Физическое имя.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    public function noteAdd(string $tableName): void
    {
        $this->queueOrFlush(['tags' => ['st:' . $tableName]]);
    }

    /**
     * Копит или сразу сбрасывает get и теги изменённых полей.
     *
     * @param string $tableName Физическое имя.
     * @param int $rowId Идентификатор.
     * @param array<int, string> $fieldNames Изменённые поля.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    public function noteUpdate(string $tableName, int $rowId, array $fieldNames): void
    {
        $tagNames = [];
        foreach ($fieldNames as $fieldName) {
            $tagNames[] = 'st:' . $tableName . ':' . $fieldName;
        }

        $this->queueOrFlush([
            'keys' => [$tableName . ':get:' . $rowId],
            'tags' => $tagNames,
        ]);
    }

    /**
     * Копит или сразу сбрасывает тег стола и ключ get.
     *
     * @param string $tableName Физическое имя.
     * @param int $rowId Идентификатор.
     * @param Closure|null $dependentTableNames Имена столов CASCADE/SET NULL или null.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    public function noteDelete(string $tableName, int $rowId, ?Closure $dependentTableNames = null): void
    {
        $tagNames = array_merge(
            ['st:' . $tableName],
            DependentRowCacheTags::names($dependentTableNames, $this->cacheStore->isUsable()),
        );
        $this->queueOrFlush([
            'keys' => [$tableName . ':get:' . $rowId],
            'tags' => $tagNames,
        ]);
    }

    /**
     * Копит или сразу сбрасывает тег стола после DDL.
     *
     * @param string $tableName Физическое имя.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    public function noteDdl(string $tableName): void
    {
        $this->pendingOps[] = ['tags' => ['st:' . $tableName]];
        if (!$this->isTransactionOpen()) {
            $this->flushPending();
        }
    }

    /**
     * После commit сбрасывает pending; после rollback забывает.
     *
     * @param bool $committed True после успешного SQL commit.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс после commit упал.
     */
    public function settleTransaction(bool $committed): void
    {
        if ($committed) {
            $this->flushPending();

            return;
        }

        $this->pendingOps = [];
    }

    /**
     * Открыта ли транзакция соединения.
     *
     * @return bool True, если уровень > 0.
     */
    private function isTransactionOpen(): bool
    {
        return ($this->transactionLevel)() > 0;
    }

    /**
     * Кладёт операцию в pending или исполняет сразу.
     *
     * @param array{tags?: array<int, string>, keys?: array<int, string>} $operation Сброс.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    private function queueOrFlush(array $operation): void
    {
        if ($this->isTransactionOpen()) {
            $this->pendingOps[] = $operation;

            return;
        }

        $this->runFlush([$operation]);
    }

    /**
     * Исполняет накопленный сброс.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    private function flushPending(): void
    {
        $operations = $this->pendingOps;
        $this->pendingOps = [];
        $this->runFlush($operations);
    }

    /**
     * Исполняет сброс тегов и ключей.
     *
     * @param array<int, array{tags?: array<int, string>, keys?: array<int, string>}> $operations Операции.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    private function runFlush(array $operations): void
    {
        if ($operations === [] || !$this->cacheStore->isUsable()) {
            return;
        }

        $tagNames = [];
        $cacheKeys = [];
        foreach ($operations as $operation) {
            foreach ($operation['tags'] ?? [] as $tagName) {
                $tagNames[$tagName] = true;
            }

            foreach ($operation['keys'] ?? [] as $cacheKey) {
                $cacheKeys[$cacheKey] = true;
            }
        }

        $this->flushStore(array_keys($tagNames), array_keys($cacheKeys));
    }

    /**
     * Пишет сброс в store.
     *
     * @param array<int, string> $tagNames Теги.
     * @param array<int, string> $cacheKeys Ключи get.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug и сброс упал.
     */
    private function flushStore(array $tagNames, array $cacheKeys): void
    {
        try {
            if ($tagNames !== []) {
                $this->cacheStore->flushTags($tagNames);
            }

            if ($cacheKeys !== []) {
                $this->cacheStore->deleteKeys($cacheKeys);
            }
        } catch (CacheInvalidException) {
            return;
        } catch (Throwable $throwable) {
            $this->failSoft->write($throwable);
        }
    }

    /**
     * Читает и декодирует слот.
     *
     * @param string $cacheKey Ключ.
     *
     * @return CacheHit Попадание или промах.
     *
     * @throws CacheConfigInvalidException Если store непригоден.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    private function lookupValue(string $cacheKey): CacheHit
    {
        $this->assertStoreUsable();
        try {
            $payload = $this->cacheStore->read($cacheKey);
            if ($payload === null) {
                return new CacheHit(false, null);
            }

            $cacheHit = $this->cachePayload->decode($payload);
            if (!$cacheHit->found()) {
                $this->cacheStore->deleteKeys([$cacheKey]);
            }

            return $cacheHit;
        } catch (CacheInvalidException $exception) {
            throw new CacheConfigInvalidException($exception->getMessage());
        } catch (Throwable $throwable) {
            return $this->failSoft->read($throwable);
        }
    }

    /**
     * Кодирует и пишет слот.
     *
     * @param string $cacheKey Ключ.
     * @param mixed $value Значение.
     * @param int $cacheTtl Секунды.
     * @param array<int, string> $tagNames Теги.
     *
     * @return void
     *
     * @throws CacheConfigInvalidException Если store непригоден.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    private function saveValue(string $cacheKey, mixed $value, int $cacheTtl, array $tagNames): void
    {
        $this->assertStoreUsable();
        try {
            $this->cacheStore->write($cacheKey, $this->cachePayload->encode($value), $cacheTtl, $tagNames);
        } catch (CacheInvalidException $exception) {
            throw new CacheConfigInvalidException($exception->getMessage());
        } catch (Throwable $throwable) {
            $this->failSoft->write($throwable);
        }
    }

    /**
     * Дырявый конфиг — всегда ошибка, не fail-soft.
     *
     * @return void
     *
     * @throws CacheConfigInvalidException Если isUsable false.
     */
    private function assertStoreUsable(): void
    {
        if (!$this->cacheStore->isUsable()) {
            throw new CacheConfigInvalidException();
        }
    }
}
