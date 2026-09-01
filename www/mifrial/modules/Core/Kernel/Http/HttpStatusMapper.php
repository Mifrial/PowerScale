<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Mifrial\Core\Kernel\Dto\ActionResponse;

/**
 * Сопоставление конверта действия HTTP-коду.
 */
final class HttpStatusMapper
{
    /**
     * Возвращает HTTP-код для конверта действия.
     *
     * @param ActionResponse $response Конверт ответа.
     *
     * @return int Код HTTP.
     */
    public function statusFor(ActionResponse $response): int
    {
        if ($response->success) {
            return 200;
        }

        $errorCode = is_array($response->error) ? ($response->error['code'] ?? '') : '';

        return match ($errorCode) {
            'CSRF' => 403,
            'INTERNAL' => 500,
            default => 400,
        };
    }
}
