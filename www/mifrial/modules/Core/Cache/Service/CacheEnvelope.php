<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

/**
 * Конверт store: unix истечения плюс сырой payload.
 */
final class CacheEnvelope
{
    /**
     * Кладёт срок в первую строку.
     *
     * @param string $payload Байты потребителя.
     * @param int $expiresAt Unix истечения.
     *
     * @return string Байты на диск или в Redis.
     */
    public function pack(string $payload, int $expiresAt): string
    {
        return $expiresAt . "\n" . $payload;
    }

    /**
     * Снимает конверт; промах если срок прошёл или формат битый.
     *
     * @param string $raw Байты store.
     * @param int $now Unix сейчас.
     *
     * @return string|null Payload или null.
     */
    public function unpack(string $raw, int $now): ?string
    {
        $separator = strpos($raw, "\n");
        if ($separator === false) {
            return null;
        }

        $expiresAt = (int) substr($raw, 0, $separator);
        if ($expiresAt <= $now) {
            return null;
        }

        return substr($raw, $separator + 1);
    }
}
