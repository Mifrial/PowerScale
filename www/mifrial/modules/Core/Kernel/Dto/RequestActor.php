<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Снимок аутентифицированного пользователя на запрос: id и права, не JSON User.
 */
final class RequestActor
{
    /**
     * Создаёт снимок актора.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи активных групп.
     * @param bool $hasBypass Обход ACL.
     *
     * @return void
     */
    public function __construct(
        private readonly int $userId,
        private readonly array $permissionKeys,
        private readonly bool $hasBypass,
    ) {
    }

    /**
     * Возвращает id учётки.
     *
     * @return int Id.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Возвращает ключи прав.
     *
     * @return array<int, string> Ключи.
     */
    public function getPermissionKeys(): array
    {
        return $this->permissionKeys;
    }

    /**
     * Есть ли bypass.
     *
     * @return bool true, если обход ACL.
     */
    public function hasBypass(): bool
    {
        return $this->hasBypass;
    }

    /**
     * Есть ли ключ или bypass.
     *
     * @param string $permissionKey Ключ вроде user.view.
     *
     * @return bool true, если доступен.
     */
    public function hasKey(string $permissionKey): bool
    {
        return $this->hasBypass || in_array($permissionKey, $this->permissionKeys, true);
    }
}
