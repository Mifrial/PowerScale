<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\CacheHit;
use Mifrial\Core\SmartTable\Dto\ListResult;

/**
 * Кодирование слота: expire плюс serialize допустимых типов.
 */
final class CachePayload
{
    /**
     * Собирает байты слота.
     *
     * @param mixed $value Значение.
     * @param int $expiresAt Unix истечения.
     *
     * @return string Байты.
     */
    public function encode(mixed $value, int $expiresAt): string
    {
        return $expiresAt . "\n" . serialize($value);
    }

    /**
     * Разбирает payload; промах если истёк или битый.
     *
     * @param string $payload Байты.
     * @param int $now Unix сейчас.
     *
     * @return CacheHit Попадание, в том числе value null.
     */
    public function decode(string $payload, int $now): CacheHit
    {
        $separator = strpos($payload, "\n");
        if ($separator === false) {
            return new CacheHit(false, null);
        }

        $expiresAt = (int) substr($payload, 0, $separator);
        $body = substr($payload, $separator + 1);
        $decoded = unserialize($body, ['allowed_classes' => [ListResult::class, UnixDateTime::class]]);
        $broken = $decoded === false && $body !== serialize(false);
        if ($expiresAt <= $now || $broken) {
            return new CacheHit(false, null);
        }

        return new CacheHit(true, $decoded);
    }
}
