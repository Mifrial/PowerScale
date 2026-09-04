<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Параметры ctor — поля группы, не порты DI.

namespace Mifrial\Core\User\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Прочитанная группа: смысл свойств, не мешок колонок.
 */
final class GroupRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'name',
        'active',
        'bypass',
        'assign_on_register',
        'created_at',
        'permissions',
    ];

    /**
     * Создаёт запись из свойств группы.
     *
     * @param int $id Идентификатор.
     * @param string $name Имя.
     * @param bool $active Группа включена.
     * @param bool $bypass Обход ACL.
     * @param bool $assignOnRegister Автовыдача.
     * @param DateTime $createdAt Создание.
     * @param array<int, string> $permissionKeys Ключи прав.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly bool $active,
        private readonly bool $bypass,
        private readonly bool $assignOnRegister,
        private readonly DateTime $createdAt,
        private readonly array $permissionKeys,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства группы.
     *
     * @return self Группа.
     *
     * @throws UserInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireString($fields['name']),
            self::requireBool($fields['active']),
            self::requireBool($fields['bypass']),
            self::requireBool($fields['assign_on_register']),
            self::requireDateTime($fields['created_at']),
            self::parsePermissionKeys($fields['permissions']),
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
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Группа включена.
     *
     * @return bool true, если активна.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Флаг обхода ACL.
     *
     * @return bool true, если bypass.
     */
    public function isBypass(): bool
    {
        return $this->bypass;
    }

    /**
     * Автовыдача при пустом groups.
     *
     * @return bool true, если выдавать.
     */
    public function isAssignOnRegister(): bool
    {
        return $this->assignOnRegister;
    }

    /**
     * Момент создания.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Ключи прав группы.
     *
     * @return array<int, string> Ключи.
     */
    public function getPermissionKeys(): array
    {
        return $this->permissionKeys;
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
                throw new UserInvalidException('Group record is incomplete');
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
            throw new UserInvalidException('Group record is incomplete');
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
            throw new UserInvalidException('Group record is incomplete');
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
            throw new UserInvalidException('Group record is incomplete');
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
            throw new UserInvalidException('Group record is incomplete');
        }

        return $value;
    }

    /**
     * Ключи прав из поля multiple.
     *
     * @param mixed $permissions Значение строки.
     *
     * @return array<int, string> Ключи.
     *
     * @throws UserInvalidException Если не список строк.
     */
    private static function parsePermissionKeys(mixed $permissions): array
    {
        if (!is_array($permissions)) {
            throw new UserInvalidException('Group record is incomplete');
        }

        $permissionKeys = [];
        foreach ($permissions as $permissionKey) {
            if (!is_string($permissionKey)) {
                throw new UserInvalidException('Group record is incomplete');
            }

            $permissionKeys[] = $permissionKey;
        }

        return $permissionKeys;
    }
}
