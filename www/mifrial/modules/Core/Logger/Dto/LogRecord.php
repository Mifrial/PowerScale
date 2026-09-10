<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры полной строки журнала; параметры ctor — поля, не порты.

namespace Mifrial\Core\Logger\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Logger\Exception\LoggerInvalidException;

/**
 * Прочитанная строка журнала.
 */
final class LogRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Идентификатор.
     * @param DateTime $createdAt Момент.
     * @param string $level error|warning|info.
     * @param string $message Текст.
     * @param string|null $source Источник.
     * @param int|null $userId Актор.
     * @param string|null $exceptionClass Класс исключения.
     * @param string|null $errorCode Код.
     * @param array<string, mixed>|null $context Остаток json.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly DateTime $createdAt,
        private readonly string $level,
        private readonly string $message,
        private readonly ?string $source,
        private readonly ?int $userId,
        private readonly ?string $exceptionClass,
        private readonly ?string $errorCode,
        private readonly ?array $context,
    ) {
    }

    /**
     * Собирает Record из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws LoggerInvalidException Если обязательные поля неверны.
     */
    public static function fromNormalized(array $fields): self
    {
        $createdAt = $fields['created_at'] ?? null;
        if (!$createdAt instanceof DateTime) {
            throw new LoggerInvalidException('Log record created_at is invalid');
        }

        $id = $fields['id'] ?? null;
        $level = $fields['level'] ?? null;
        $message = $fields['message'] ?? null;
        if (!is_int($id) || !is_string($level) || !is_string($message)) {
            throw new LoggerInvalidException('Log record is incomplete');
        }

        return new self(
            $id,
            $createdAt,
            $level,
            $message,
            self::optionalString($fields['source'] ?? null),
            self::optionalInt($fields['user_id'] ?? null),
            self::optionalString($fields['exception_class'] ?? null),
            self::optionalString($fields['error_code'] ?? null),
            self::optionalContext($fields['context'] ?? null),
        );
    }

    /**
     * Идентификатор.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Момент записи.
     *
     * @return DateTime Время.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Уровень.
     *
     * @return string error|warning|info.
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Текст.
     *
     * @return string Сообщение.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Источник.
     *
     * @return string|null Код action или агента.
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * Актор на момент записи.
     *
     * @return int|null Id учётки.
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Класс исключения.
     *
     * @return string|null Имя класса.
     */
    public function getExceptionClass(): ?string
    {
        return $this->exceptionClass;
    }

    /**
     * Код ошибки.
     *
     * @return string|null Код.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Остаток context.
     *
     * @return array<string, mixed>|null Json.
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Строка или null.
     *
     * @param mixed $value Значение.
     *
     * @return string|null Строка.
     *
     * @throws LoggerInvalidException Если не строка и не null.
     */
    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new LoggerInvalidException('Log record is incomplete');
        }

        return $value;
    }

    /**
     * Целое или null.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Целое.
     *
     * @throws LoggerInvalidException Если не int и не null.
     */
    private static function optionalInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw new LoggerInvalidException('Log record is incomplete');
        }

        return $value;
    }

    /**
     * Context json или null.
     *
     * @param mixed $value Значение.
     *
     * @return array<string, mixed>|null Карта.
     *
     * @throws LoggerInvalidException Если не массив и не null.
     */
    private static function optionalContext(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new LoggerInvalidException('Log record context is invalid');
        }

        return $value;
    }
}
