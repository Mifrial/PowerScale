<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры полного профиля — публичный смысл Record; параметры ctor — поля, не порты.

namespace Mifrial\Core\User\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Прочитанная учётка: смысл свойств, не мешок колонок.
 */
final class UserRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'login',
        'email',
        'name',
        'surname',
        'nickname',
        'active',
        'registered_at',
        'deactivated_until',
        'deactivate_reason',
    ];

    /**
     * Создаёт запись из свойств профиля.
     *
     * @param int $id Идентификатор.
     * @param string $login Логин.
     * @param string|null $email Почта.
     * @param string $name Имя.
     * @param string|null $surname Фамилия.
     * @param string|null $nickname Ник.
     * @param bool $active Учётка включена.
     * @param DateTime $registeredAt Регистрация.
     * @param DateTime|null $deactivatedUntil Срок бана.
     * @param string|null $deactivateReason Причина бана.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $login,
        private readonly ?string $email,
        private readonly string $name,
        private readonly ?string $surname,
        private readonly ?string $nickname,
        private readonly bool $active,
        private readonly DateTime $registeredAt,
        private readonly ?DateTime $deactivatedUntil,
        private readonly ?string $deactivateReason,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства учётки.
     *
     * @return self Учётка.
     *
     * @throws UserInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireString($fields['login']),
            self::nullableString($fields['email']),
            self::requireString($fields['name']),
            self::nullableString($fields['surname']),
            self::nullableString($fields['nickname']),
            self::requireBool($fields['active']),
            self::requireDateTime($fields['registered_at']),
            self::optionalDateTime($fields['deactivated_until']),
            self::nullableString($fields['deactivate_reason']),
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
     * Логин.
     *
     * @return string Логин.
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Почта.
     *
     * @return string|null Почта или null.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Фамилия.
     *
     * @return string|null Фамилия или null.
     */
    public function getSurname(): ?string
    {
        return $this->surname;
    }

    /**
     * Ник.
     *
     * @return string|null Ник или null.
     */
    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    /**
     * Учётка включена.
     *
     * @return bool true, если активна.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Момент регистрации.
     *
     * @return DateTime UTC.
     */
    public function getRegisteredAt(): DateTime
    {
        return $this->registeredAt;
    }

    /**
     * Срок деактивации.
     *
     * @return DateTime|null UTC или null.
     */
    public function getDeactivatedUntil(): ?DateTime
    {
        return $this->deactivatedUntil;
    }

    /**
     * Причина деактивации.
     *
     * @return string|null Текст или null.
     */
    public function getDeactivateReason(): ?string
    {
        return $this->deactivateReason;
    }

    /**
     * Все ключи Record на месте.
     *
     * @param array<string, mixed> $fields Карта строки.
     *
     * @return void
     *
     * @throws UserInvalidException Если ключа нет.
     */
    private static function assertComplete(array $fields): void
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new UserInvalidException('User record is incomplete');
            }
        }
    }

    /**
     * Целое, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return int Целое.
     *
     * @throws UserInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new UserInvalidException('User record is incomplete');
        }

        return $value;
    }

    /**
     * Строка, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return string Строка.
     *
     * @throws UserInvalidException Если не строка.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new UserInvalidException('User record is incomplete');
        }

        return $value;
    }

    /**
     * Bool, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return bool Флаг.
     *
     * @throws UserInvalidException Если не bool.
     */
    private static function requireBool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new UserInvalidException('User record is incomplete');
        }

        return $value;
    }

    /**
     * DateTime ядра, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return DateTime UTC.
     *
     * @throws UserInvalidException Если не DateTime.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new UserInvalidException('User record is incomplete');
        }

        return $value;
    }

    /**
     * DateTime или null, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return DateTime|null UTC или null.
     *
     * @throws UserInvalidException Если не DateTime и не null.
     */
    private static function optionalDateTime(mixed $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        return self::requireDateTime($value);
    }

    /**
     * Строка или null, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return string|null Строка или null.
     *
     * @throws UserInvalidException Если не строка и не null.
     */
    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new UserInvalidException('User record is incomplete');
        }

        return $value;
    }
}
