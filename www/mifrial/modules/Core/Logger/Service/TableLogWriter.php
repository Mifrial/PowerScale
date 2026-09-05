<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Mifrial\Core\Kernel\Service\ErrorLogLogger;
use Mifrial\Core\Logger\Repository\LogRepository;
use Throwable;

/**
 * ILogger в таблицу `log`; debug и сбой insert — error_log.
 */
final class TableLogWriter implements ILogger
{
    /**
     * Создаёт писателя.
     *
     * @param LogRepository $logRepository Строки.
     * @param ILogger $fallback Запасной канал.
     *
     * @return void
     */
    public function __construct(
        private readonly LogRepository $logRepository,
        private readonly ILogger $fallback = new ErrorLogLogger(),
    ) {
    }

    /**
     * Пишет ошибку в таблицу.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->writeRow('error', $message, $context);
    }

    /**
     * Пишет предупреждение в таблицу.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->writeRow('warning', $message, $context);
    }

    /**
     * Пишет информацию в таблицу.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->writeRow('info', $message, $context);
    }

    /**
     * Пишет отладку только в fallback.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->fallback->debug($message, $context);
    }

    /**
     * Insert или fallback.
     *
     * @param string $level error|warning|info.
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    private function writeRow(string $level, string $message, array $context): void
    {
        try {
            $this->logRepository->add($this->buildFields($level, $message, $context));
        } catch (Throwable) {
            $this->writeFallback($level, $message, $context);
        }
    }

    /**
     * Собирает поля строки.
     *
     * @param string $level Уровень.
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Поля add.
     */
    private function buildFields(string $level, string $message, array $context): array
    {
        $fields = [
            'level' => $level,
            'message' => $message,
        ];
        $this->putOptionalString($fields, 'source', $context['source'] ?? null);
        $this->putOptionalString($fields, 'exception_class', $context['class'] ?? null);
        $this->putOptionalString($fields, 'error_code', $context['errorCode'] ?? null);
        $userId = $context['userId'] ?? null;
        if (is_int($userId)) {
            $fields['user_id'] = $userId;
        }

        $restContext = $this->filterScalarContext($context);
        if ($restContext !== []) {
            $fields['context'] = $restContext;
        }

        return $fields;
    }

    /**
     * Кладёт непустую строку в поля.
     *
     * @param array<string, mixed> $fields Поля.
     * @param string $column Колонка.
     * @param mixed $value Значение.
     *
     * @return void
     */
    private function putOptionalString(array &$fields, string $column, mixed $value): void
    {
        if (is_string($value) && $value !== '') {
            $fields[$column] = $value;
        }
    }

    /**
     * Оставляет в контексте только скаляры, без зарезервированных ключей.
     *
     * @param array<string, mixed> $context Вход.
     *
     * @return array<string, scalar|null> Остаток.
     */
    private function filterScalarContext(array $context): array
    {
        $reserved = ['class' => true, 'errorCode' => true, 'userId' => true, 'source' => true];
        $restContext = [];
        foreach ($context as $contextKey => $contextValue) {
            if (isset($reserved[$contextKey])) {
                continue;
            }

            if (is_scalar($contextValue) || $contextValue === null) {
                $restContext[$contextKey] = $contextValue;
            }
        }

        return $restContext;
    }

    /**
     * Запасной канал.
     *
     * @param string $level Уровень.
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    private function writeFallback(string $level, string $message, array $context): void
    {
        match ($level) {
            'warning' => $this->fallback->warning($message, $context),
            'info' => $this->fallback->info($message, $context),
            default => $this->fallback->error($message, $context),
        };
    }
}
