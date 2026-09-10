<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

use Mifrial\Core\Logger\Dto\LogRecord;

/**
 * JSON-вид записи журнала с allowlist context.
 */
final class LogViewAssembler
{
    /**
     * @var array<string, true>
     */
    private const CONTEXT_KEYS = [
        'file' => true,
        'line' => true,
        'message' => true,
        'jobId' => true,
        'attempts' => true,
    ];

    /**
     * Собирает JSON-вид.
     *
     * @param LogRecord $logRecord Запись.
     *
     * @return array<string, mixed> LogEntry.
     */
    public function assemble(LogRecord $logRecord): array
    {
        return [
            'id' => $logRecord->getId(),
            'createdAt' => $logRecord->getCreatedAt()->toUnix(),
            'level' => $logRecord->getLevel(),
            'message' => $logRecord->getMessage(),
            'source' => $logRecord->getSource(),
            'userId' => $logRecord->getUserId(),
            'exceptionClass' => $logRecord->getExceptionClass(),
            'errorCode' => $logRecord->getErrorCode(),
            'context' => $this->assembleContext($logRecord->getContext()),
        ];
    }

    /**
     * Оставляет безопасные скаляры context.
     *
     * @param array<string, mixed>|null $context Остаток json.
     *
     * @return array<string, bool|int|float|string>|null Вид.
     */
    private function assembleContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $safeContext = [];
        foreach ($context as $contextKey => $contextValue) {
            if (!is_string($contextKey) || !isset(self::CONTEXT_KEYS[$contextKey])) {
                continue;
            }

            $scalar = $this->scalarContextValue($contextValue);
            if ($scalar !== null) {
                $safeContext[$contextKey] = $scalar;
            }
        }

        return $safeContext === [] ? null : $safeContext;
    }

    /**
     * Скаляр для JSON или null.
     *
     * @param mixed $contextValue Значение.
     *
     * @return bool|int|float|string|null Скаляр.
     */
    private function scalarContextValue(mixed $contextValue): bool|int|float|string|null
    {
        if (is_bool($contextValue) || is_int($contextValue) || is_float($contextValue)) {
            return $contextValue;
        }

        if (is_string($contextValue)) {
            return strlen($contextValue) <= 200 ? $contextValue : substr($contextValue, 0, 200);
        }

        return null;
    }
}
