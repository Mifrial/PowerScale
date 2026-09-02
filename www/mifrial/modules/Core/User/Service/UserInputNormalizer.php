<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Dto\UserPatch;
use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Разбор входа в NewUser / UserPatch.
 */
final class UserInputNormalizer
{
    /**
     * @var array<int, string>
     */
    private const NEW_USER_KEYS = ['login', 'name', 'email', 'surname', 'nickname', 'active'];

    /**
     * @var array<int, string>
     */
    private const PATCH_KEYS = [
        'login',
        'name',
        'email',
        'surname',
        'nickname',
        'active',
        'deactivated_until',
        'deactivate_reason',
    ];

    /**
     * Собирает NewUser из присутствующих ключей.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return NewUser Новая учётка.
     *
     * @throws UserInvalidException Если ключи или значения недопустимы.
     */
    public function newUser(array $values): NewUser
    {
        $this->assertAllowed($values, self::NEW_USER_KEYS);
        $this->assertHasLoginAndName($values);
        $normalizedValues = $this->normalizePresent($values);
        if (!array_key_exists('active', $normalizedValues)) {
            $normalizedValues['active'] = true;
        }

        return NewUser::fromNormalized($normalizedValues);
    }

    /**
     * Собирает patch из присутствующих ключей.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return UserPatch Patch.
     *
     * @throws UserInvalidException Если набор пуст или недопустим.
     */
    public function patch(array $values): UserPatch
    {
        if ($values === []) {
            throw new UserInvalidException('User patch is empty');
        }

        if (array_key_exists('id', $values) || array_key_exists('registered_at', $values)) {
            throw new UserInvalidException('User patch contains a forbidden key');
        }

        $this->assertAllowed($values, self::PATCH_KEYS);

        return UserPatch::fromNormalized($this->normalizePresent($values));
    }

    /**
     * Отвергает неизвестные ключи.
     *
     * @param array<string, mixed> $values Вход.
     * @param array<int, string> $allowedKeys Разрешённые имена.
     *
     * @return void
     *
     * @throws UserInvalidException Если ключ неизвестен.
     */
    private function assertAllowed(array $values, array $allowedKeys): void
    {
        foreach (array_keys($values) as $fieldName) {
            if (!in_array($fieldName, $allowedKeys, true)) {
                throw new UserInvalidException('User field is unknown');
            }
        }
    }

    /**
     * Требует login и name.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return void
     *
     * @throws UserInvalidException Если нет login или name.
     */
    private function assertHasLoginAndName(array $values): void
    {
        if (!array_key_exists('login', $values) || !array_key_exists('name', $values)) {
            throw new UserInvalidException('User login and name are required');
        }
    }

    /**
     * Нормализует только переданные поля.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return array<string, mixed> Нормализованные поля.
     *
     * @throws UserInvalidException Если значение недопустимо.
     */
    private function normalizePresent(array $values): array
    {
        $normalizedValues = [];
        foreach ($values as $fieldName => $fieldValue) {
            $normalizedValues[$fieldName] = $this->normalizeField($fieldName, $fieldValue);
        }

        return $normalizedValues;
    }

    /**
     * Нормализует одно поле.
     *
     * @param string $fieldName Имя.
     * @param mixed $fieldValue Вход.
     *
     * @return mixed Нормализованное значение.
     *
     * @throws UserInvalidException Если значение недопустимо.
     */
    private function normalizeField(string $fieldName, mixed $fieldValue): mixed
    {
        $nullableStringNames = ['email', 'surname', 'nickname', 'deactivate_reason'];
        $normalizedValue = $fieldValue;
        if ($fieldName === 'login' || $fieldName === 'name') {
            $normalizedValue = $this->requiredString($fieldValue);
        } elseif (in_array($fieldName, $nullableStringNames, true)) {
            $normalizedValue = $this->nullableString($fieldValue);
        } elseif ($fieldName === 'active') {
            $normalizedValue = $this->boolValue($fieldValue);
        } else {
            $normalizedValue = $this->optionalDateTime($fieldValue);
        }

        return $normalizedValue;
    }

    /**
     * Trim обязательной строки.
     *
     * @param mixed $fieldValue Вход.
     *
     * @return string Непустая строка.
     *
     * @throws UserInvalidException Если не строка или пусто.
     */
    private function requiredString(mixed $fieldValue): string
    {
        if (!is_string($fieldValue)) {
            throw new UserInvalidException('User string field is invalid');
        }

        $trimmedValue = trim($fieldValue);
        if ($trimmedValue === '') {
            throw new UserInvalidException('User string field is empty');
        }

        return $trimmedValue;
    }

    /**
     * Trim необязательной строки; пустое — null.
     *
     * @param mixed $fieldValue Вход.
     *
     * @return string|null Строка или null.
     *
     * @throws UserInvalidException Если не строка и не null.
     */
    private function nullableString(mixed $fieldValue): ?string
    {
        if ($fieldValue === null) {
            return null;
        }

        if (!is_string($fieldValue)) {
            throw new UserInvalidException('User string field is invalid');
        }

        $trimmedValue = trim($fieldValue);
        if ($trimmedValue === '') {
            return null;
        }

        return $trimmedValue;
    }

    /**
     * Проверяет bool.
     *
     * @param mixed $fieldValue Вход.
     *
     * @return bool Флаг.
     *
     * @throws UserInvalidException Если не bool.
     */
    private function boolValue(mixed $fieldValue): bool
    {
        if (!is_bool($fieldValue)) {
            throw new UserInvalidException('User active must be bool');
        }

        return $fieldValue;
    }

    /**
     * Проверяет DateTime или null.
     *
     * @param mixed $fieldValue Вход.
     *
     * @return DateTime|null Момент или null.
     *
     * @throws UserInvalidException Если тип неверен.
     */
    private function optionalDateTime(mixed $fieldValue): ?DateTime
    {
        if ($fieldValue === null) {
            return null;
        }

        if (!$fieldValue instanceof DateTime) {
            throw new UserInvalidException('User datetime must be DateTime');
        }

        return $fieldValue;
    }
}
