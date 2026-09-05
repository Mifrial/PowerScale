<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Service;

use Closure;
use Mifrial\Core\Cache\Exception\CacheException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Versioning\Space\Dto\RevisionSlice;
use Mifrial\Versioning\Space\Dto\RevisionSliceKey;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Кэш готового среза: fail-soft без тегов ST.
 */
final class RevisionSliceStore
{
    /**
     * Создаёт store срезов.
     *
     * @param ICacheStore $cacheStore Драйвер.
     * @param bool $debug Кидать I/O.
     * @param string $identityTableName Физическое имя identity.
     * @param RevisionSliceCodec $revisionSliceCodec JSON.
     * @param Closure $isTransactionOpen TX соединения ST.
     *
     * @return void
     */
    public function __construct(
        private readonly ICacheStore $cacheStore,
        private readonly bool $debug,
        private readonly string $identityTableName,
        private readonly RevisionSliceCodec $revisionSliceCodec,
        private readonly Closure $isTransactionOpen,
    ) {
    }

    /**
     * Читает срез или промах.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     *
     * @return RevisionSlice|null Срез.
     *
     * @throws SpaceInvalidException Если debug и I/O или JSON упали.
     */
    public function find(int $spaceId, int $revision): ?RevisionSlice
    {
        if (($this->isTransactionOpen)()) {
            return null;
        }

        $payload = $this->readPayload($spaceId, $revision);
        if ($payload === null) {
            return null;
        }

        return $this->decodeOrMiss($payload);
    }

    /**
     * Пишет срез после успешного commit.
     *
     * @param RevisionSlice $revisionSlice Срез.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если debug и запись упала.
     */
    public function save(RevisionSlice $revisionSlice): void
    {
        if (!$this->cacheStore->isUsable() || ($this->isTransactionOpen)()) {
            return;
        }

        $revisionRecord = $revisionSlice->getRevision();
        $cacheKey = RevisionSliceKey::make(
            $this->identityTableName,
            $revisionRecord->getSpaceId(),
            $revisionRecord->getRevision(),
        );
        try {
            $this->cacheStore->write(
                $cacheKey,
                $this->revisionSliceCodec->encode($revisionSlice),
                ICacheStore::MAX_TTL_SECONDS,
                [],
            );
        } catch (CacheException $exception) {
            $this->failSoft($exception);
        }
    }

    /**
     * Читает байты ключа.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     *
     * @return string|null Payload.
     *
     * @throws SpaceInvalidException Если debug и I/O упал.
     */
    private function readPayload(int $spaceId, int $revision): ?string
    {
        if (!$this->cacheStore->isUsable()) {
            return null;
        }

        try {
            return $this->cacheStore->read(
                RevisionSliceKey::make($this->identityTableName, $spaceId, $revision),
            );
        } catch (CacheException $exception) {
            $this->failSoft($exception);

            return null;
        }
    }

    /**
     * Декодирует или промах без debug.
     *
     * @param string $payload JSON.
     *
     * @return RevisionSlice|null Срез.
     *
     * @throws SpaceInvalidException Если debug и JSON битый.
     */
    private function decodeOrMiss(string $payload): ?RevisionSlice
    {
        try {
            return $this->revisionSliceCodec->decode($payload);
        } catch (SpaceInvalidException $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return null;
        }
    }

    /**
     * Debug кидает, иначе no-op.
     *
     * @param CacheException $exception I/O.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если debug.
     */
    private function failSoft(CacheException $exception): void
    {
        if ($this->debug) {
            throw new SpaceInvalidException($exception->getMessage(), $exception);
        }
    }
}
