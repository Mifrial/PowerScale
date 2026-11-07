<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Repository;

use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Repository\Support\AuthWriteGuard;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `user_identity`.
 */
final class UserIdentityRepository
{
    private readonly AuthWriteGuard $writeGuard;

    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $identityRecords Строки identity.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $identityRecords,
    ) {
        $this->writeGuard = new AuthWriteGuard();
    }

    /**
     * Ищет password-identity учётки.
     *
     * @param int $userId Учётка.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    public function findPassword(int $userId): ?array
    {
        return $this->identityRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['identity_key' => 'password:' . $userId],
            'limit' => 1,
        ]));
    }

    /**
     * Создаёт password-identity.
     *
     * @param int $userId Учётка.
     * @param string $secretHash Hash пароля.
     *
     * @return int Новый id.
     *
     * @throws AuthDuplicateException Если ключ занят.
     * @throws AuthInvalidException Если поля недопустимы.
     */
    public function addPassword(int $userId, string $secretHash): int
    {
        return $this->writeGuard->run(function () use ($userId, $secretHash): int {
            return $this->identityRecords->add([
                'user_id' => $userId,
                'kind' => 'password',
                'identity_key' => 'password:' . $userId,
                'secret_hash' => $secretHash,
                'last_used_at' => null,
            ]);
        });
    }

    /**
     * Пишет last_used_at.
     *
     * @param int $identityId Строка.
     * @param DateTime $usedAt Момент.
     *
     * @return void
     *
     * @throws AuthInvalidException Если поля недопустимы.
     */
    public function markUsed(int $identityId, DateTime $usedAt): void
    {
        $this->writeGuard->run(function () use ($identityId, $usedAt): mixed {
            $this->identityRecords->update($identityId, ['last_used_at' => $usedAt]);

            return null;
        });
    }

    /**
     * Меняет hash пароля.
     *
     * @param int $identityId Строка identity.
     * @param string $secretHash Новый hash.
     *
     * @return void
     *
     * @throws AuthInvalidException Если поля недопустимы.
     */
    public function updateSecretHash(int $identityId, string $secretHash): void
    {
        $this->writeGuard->run(function () use ($identityId, $secretHash): mixed {
            $this->identityRecords->update($identityId, ['secret_hash' => $secretHash]);

            return null;
        });
    }
}
