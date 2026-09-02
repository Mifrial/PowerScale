<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Cache;

use Closure;
use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\SmartTable\Dto\CacheHit;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Cache\CacheConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Mifrial\Core\SmartTable\Service\Query\ListCacheFieldTags;
use Throwable;

/**
 * Сценарий кэша get/getList: ключи, теги OR, pending транзакции, fail-soft.
 *
 * Слоты и сброс делят один lazy store: отдельный invalidator давал бы
 * второй клиент Redis на те же ключи.
 */
final class TableCache
{
    private FileCacheStore|RedisCacheStore|null $cacheStore = null;

    private readonly CachePayload $cachePayload;

    private readonly ListCacheKey $listCacheKey;

    private readonly CacheStoreFactory $storeFactory;

    private readonly CacheFailSoft $failSoft;

    /**
     * @var array<int, array{tags?: array<int, string>, keys?: array<int, string>}>
     */
    private array $pendingOps = [];

    /**
     * Создаёт кэш таблицы.
     *
     * @param CacheSettings $cacheSettings Срез local.php.
     * @param bool $debug Кидать I/O или глотать.
     * @param Closure $transactionLevel Уровень транзакции соединения.
     * @param Closure|null $clock Секунды UTC или системные.
     *
     * @return void
     */
    public function __construct(
        CacheSettings $cacheSettings,
        bool $debug,
        private readonly Closure $transactionLevel,
        private readonly ?Closure $clock = null,
    ) {
        $this->cachePayload = new CachePayload();
        $this->listCacheKey = new ListCacheKey();
        $this->storeFactory = new CacheStoreFactory($cacheSettings);
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
     * @throws CacheConfigInvalidException Если store нельзя открыть.
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
     * @throws CacheConfigInvalidException Если store нельзя открыть.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function saveGet(string $tableName, int $rowId, ?array $row, int $cacheTtl): void
    {
        if (!$this->isTransactionOpen()) {
            $this->saveValue($tableName . ':get:' . $rowId, $row, $cacheTtl, ['st:' . $tableName . ':rows']);
        }
    }

    /**
     * Читает кэш списка.
     *
     * @param string $tableName Физическое имя.
     * @param ListQuery $listQuery Запрос.
     *
     * @return CacheHit Попадание или промах.
     *
     * @throws CacheConfigInvalidException Если store нельзя открыть.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function lookupList(string $tableName, ListQuery $listQuery): CacheHit
    {
        return $this->isTransactionOpen()
            ? new CacheHit(false, null)
            : $this->lookupValue($this->listCacheKey->make($tableName, $listQuery));
    }

    /**
     * Пишет кэш списка с тегами стола и полей.
     *
     * @param string $tableName Физическое имя.
     * @param ListQuery $listQuery Запрос.
     * @param ListResult $listResult Страница.
     * @param int $cacheTtl Секунды жизни.
     * @param array<int, string> $fieldNames Поля или пары table:field.
     *
     * @return void
     *
     * @throws CacheConfigInvalidException Если store нельзя открыть.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    public function saveList(
        string $tableName,
        ListQuery $listQuery,
        ListResult $listResult,
        int $cacheTtl,
        array $fieldNames,
    ): void {
        if ($this->isTransactionOpen()) {
            return;
        }

        $tagNames = (new ListCacheFieldTags())->storeTags($tableName, $fieldNames);
        $this->saveValue($this->listCacheKey->make($tableName, $listQuery), $listResult, $cacheTtl, $tagNames);
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
            DependentRowCacheTags::names($dependentTableNames, $this->storeFactory->canOpen()),
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
        if ($operations === [] || !$this->storeFactory->canOpen()) {
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
            $store = $this->store();
            if ($tagNames !== []) {
                $store->flushTags($tagNames);
            }

            if ($cacheKeys !== []) {
                $store->deleteKeys($cacheKeys);
            }
        } catch (CacheConfigInvalidException) {
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
     * @throws CacheConfigInvalidException Если store нельзя открыть.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    private function lookupValue(string $cacheKey): CacheHit
    {
        try {
            $payload = $this->store()->read($cacheKey);
            if ($payload === null) {
                return new CacheHit(false, null);
            }

            $cacheHit = $this->cachePayload->decode($payload, $this->now());
            if (!$cacheHit->found()) {
                $this->store()->deleteKeys([$cacheKey]);
            }

            return $cacheHit;
        } catch (CacheConfigInvalidException $exception) {
            throw $exception;
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
     * @throws CacheConfigInvalidException Если store нельзя открыть.
     * @throws CacheDriverFailedException Если debug и I/O упал.
     */
    private function saveValue(string $cacheKey, mixed $value, int $cacheTtl, array $tagNames): void
    {
        try {
            $this->store()->write(
                $cacheKey,
                $this->cachePayload->encode($value, $this->now() + $cacheTtl),
                $tagNames,
            );
        } catch (CacheConfigInvalidException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            $this->failSoft->write($throwable);
        }
    }

    /**
     * Открывает store лениво.
     *
     * @return FileCacheStore|RedisCacheStore Store.
     *
     * @throws CacheConfigInvalidException Если конфиг непригоден.
     */
    private function store(): FileCacheStore|RedisCacheStore
    {
        $this->cacheStore ??= $this->storeFactory->open();

        return $this->cacheStore;
    }

    /**
     * Текущие unix-секунды.
     *
     * @return int Секунды.
     */
    private function now(): int
    {
        return $this->clock instanceof Closure ? ($this->clock)() : time();
    }
}
