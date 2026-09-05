<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Query SSE: live или пара (since, afterId).
 */
final class ChatSseQuery
{
    /**
     * Собирает курсор запроса.
     *
     * @param bool $isLive Нет since — hello, не dump.
     * @param int $sinceUnix Нижняя unix-секунда; 0 при live.
     * @param int $afterId Id в секунде since.
     *
     * @return void
     */
    public function __construct(
        public readonly bool $isLive,
        public readonly int $sinceUnix,
        public readonly int $afterId,
    ) {
    }

    /**
     * Читает GET since / afterId.
     *
     * @param IHttpRequest $httpRequest Снимок.
     *
     * @return self Курсор.
     *
     * @throws ChatInvalidException Если значение кривое или ISO.
     */
    public static function fromRequest(IHttpRequest $httpRequest): self
    {
        $afterId = self::readAfterId($httpRequest->getQueryValue('afterId'));
        $sinceRaw = $httpRequest->getQueryValue('since');
        if ($sinceRaw === null || $sinceRaw === '') {
            return new self(true, 0, $afterId);
        }

        return new self(false, self::readUnix($sinceRaw, 'since'), $afterId);
    }

    /**
     * afterId или 0, если ключа нет.
     *
     * @param mixed $raw Query.
     *
     * @return int ≥ 0.
     *
     * @throws ChatInvalidException Если не целое.
     */
    private static function readAfterId(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        return self::readUnix($raw, 'afterId');
    }

    /**
     * Неотрицательное целое из query.
     *
     * @param mixed $raw Значение.
     * @param string $field Имя поля для текста ошибки.
     *
     * @return int ≥ 0.
     *
     * @throws ChatInvalidException Если не unix/id.
     */
    private static function readUnix(mixed $raw, string $field): int
    {
        if (is_int($raw) && $raw >= 0) {
            return $raw;
        }

        if (is_string($raw) && preg_match('/^[0-9]+$/', $raw) === 1) {
            return (int) $raw;
        }

        throw new ChatInvalidException('Sync ' . $field . ' is invalid');
    }
}
