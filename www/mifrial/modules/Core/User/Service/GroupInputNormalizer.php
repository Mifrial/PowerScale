<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Разбор входа в NewGroup / GroupPatch.
 */
final class GroupInputNormalizer
{
    /**
     * @var array<int, string>
     */
    private const NEW_GROUP_KEYS = ['name', 'active', 'bypass', 'assign_on_register', 'permissions'];

    /**
     * @var array<int, string>
     */
    private const PATCH_KEYS = ['name', 'active', 'bypass', 'assign_on_register', 'permissions'];

    /**
     * Собирает NewGroup из присутствующих ключей.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return NewGroup Новая группа.
     *
     * @throws UserInvalidException Если ключи или значения недопустимы.
     */
    public function newGroup(array $values): NewGroup
    {
        $this->assertAllowed($values, self::NEW_GROUP_KEYS);
        if (!array_key_exists('name', $values)) {
            throw new UserInvalidException('Group name is required');
        }

        $normalizedValues = $this->normalizePresent($values);
        if (!array_key_exists('active', $normalizedValues)) {
            $normalizedValues['active'] = true;
        }

        if (!array_key_exists('bypass', $normalizedValues)) {
            $normalizedValues['bypass'] = false;
        }

        if (!array_key_exists('assign_on_register', $normalizedValues)) {
            $normalizedValues['assign_on_register'] = false;
        }

        if (!array_key_exists('permissions', $normalizedValues)) {
            $normalizedValues['permissions'] = [];
        }

        return NewGroup::fromNormalized($normalizedValues);
    }

    /**
     * Собирает patch из присутствующих ключей.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return GroupPatch Patch.
     *
     * @throws UserInvalidException Если набор пуст или недопустим.
     */
    public function patch(array $values): GroupPatch
    {
        if ($values === []) {
            throw new UserInvalidException('Group patch is empty');
        }

        if (array_key_exists('id', $values) || array_key_exists('created_at', $values)) {
            throw new UserInvalidException('Group patch contains a forbidden key');
        }

        $this->assertAllowed($values, self::PATCH_KEYS);

        return GroupPatch::fromNormalized($this->normalizePresent($values));
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
                throw new UserInvalidException('Group field is unknown');
            }
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
        if ($fieldName === 'name') {
            return $this->requiredString($fieldValue);
        }

        if ($fieldName === 'permissions') {
            return $this->parsePermissionKeys($fieldValue);
        }

        return $this->boolValue($fieldValue);
    }

    /**
     * Trim обязательного имени.
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
            throw new UserInvalidException('Group string field is invalid');
        }

        $trimmedValue = trim($fieldValue);
        if ($trimmedValue === '') {
            throw new UserInvalidException('Group string field is empty');
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
            throw new UserInvalidException('Group flag must be bool');
        }

        return $fieldValue;
    }

    /**
     * Нормализует список ключей прав.
     *
     * @param mixed $fieldValue Вход.
     *
     * @return array<int, string> Ключи.
     *
     * @throws UserInvalidException Если список или ключ недопустимы.
     */
    private function parsePermissionKeys(mixed $fieldValue): array
    {
        if (!is_array($fieldValue)) {
            throw new UserInvalidException('Group permissions must be a list');
        }

        $normalizedKeys = [];
        foreach ($fieldValue as $permissionKey) {
            if (!is_string($permissionKey)) {
                throw new UserInvalidException('Group permission key is invalid');
            }

            $trimmedKey = trim($permissionKey);
            if ($trimmedKey === '' || preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $trimmedKey) !== 1) {
                throw new UserInvalidException('Group permission key is invalid');
            }

            $normalizedKeys[] = $trimmedKey;
        }

        if (count($normalizedKeys) !== count(array_unique($normalizedKeys))) {
            throw new UserInvalidException('Group permission key is duplicate');
        }

        return array_values($normalizedKeys);
    }
}
