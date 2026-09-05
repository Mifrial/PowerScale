<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Cache;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\CacheHit;
use Mifrial\Core\SmartTable\Dto\ListResult;

/**
 * Serialize ListResult, AggregateResult и ряда get без строки expire.
 */
final class CachePayload
{
    /**
     * Собирает байты значения ST.
     *
     * @param mixed $value Значение.
     *
     * @return string Байты serialize.
     */
    public function encode(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Разбирает payload; промах если битый serialize.
     *
     * @param string $payload Байты.
     *
     * @return CacheHit Попадание, в том числе value null.
     */
    public function decode(string $payload): CacheHit
    {
        $decoded = unserialize($payload, [
            'allowed_classes' => [ListResult::class, AggregateResult::class, UnixDateTime::class],
        ]);
        $broken = $decoded === false && $payload !== serialize(false);
        if ($broken) {
            return new CacheHit(false, null);
        }

        return new CacheHit(true, $decoded);
    }
}
