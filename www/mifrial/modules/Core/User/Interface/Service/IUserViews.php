<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Interface\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\UserRecord;

/**
 * JSON-профиль User для HTTP и Auth.
 */
interface IUserViews
{
    /**
     * Собирает объект User.
     *
     * @param UserRecord $userRecord Профиль.
     * @param DateTime|null $lastLogin Метка last_used_at или null (ключ опустить).
     *
     * @return array<string, mixed> JSON-поля.
     */
    public function assemble(UserRecord $userRecord, ?DateTime $lastLogin): array;

    /**
     * Собирает JSON нескольких учёток без lastLogin.
     *
     * @param array<int, UserRecord> $userRecords Профили.
     *
     * @return array<int, array<string, mixed>> User[].
     */
    public function assembleMany(array $userRecords): array;
}
