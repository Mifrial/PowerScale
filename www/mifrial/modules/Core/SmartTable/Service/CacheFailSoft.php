<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Dto\CacheHit;
use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Throwable;

/**
 * I/O кэша: на debug исключение, без debug miss/no-op.
 */
final class CacheFailSoft
{
    /**
     * Создаёт политику.
     *
     * @param bool $debug Кидать I/O.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $debug,
    ) {
    }

    /**
     * Чтение: miss без debug.
     *
     * @param Throwable $throwable Причина.
     *
     * @return CacheHit Промах.
     *
     * @throws CacheDriverFailedException Если debug.
     */
    public function read(Throwable $throwable): CacheHit
    {
        $this->write($throwable);

        return new CacheHit(false, null);
    }

    /**
     * Запись: no-op без debug.
     *
     * @param Throwable $throwable Причина.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если debug.
     */
    public function write(Throwable $throwable): void
    {
        if (!$this->debug) {
            return;
        }

        throw $throwable instanceof CacheDriverFailedException
            ? $throwable
            : new CacheDriverFailedException($throwable);
    }
}
