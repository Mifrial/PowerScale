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
 * Строки `auth_password_reset`.
 */
final class PasswordResetRepository
{
    private readonly AuthWriteGuard $writeGuard;

    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $resetRecords Строки токенов.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $resetRecords,
    ) {
        $this->writeGuard = new AuthWriteGuard();
    }

    /**
     * Ищет по хешу токена.
     *
     * @param string $tokenHash SHA-256 сырого токена.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        return $this->resetRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['token_hash' => $tokenHash],
            'limit' => 1,
        ]));
    }

    /**
     * Удаляет неиспользованные токены учётки.
     *
     * @param int $userId Учётка.
     *
     * @return void
     */
    public function deleteUnusedForUser(int $userId): void
    {
        $listResult = $this->resetRecords->getList(ListQuery::fromOptions([
            'filter' => ['user_id' => $userId],
            'limit' => 500,
        ]));
        foreach ($listResult->rows() as $resetRow) {
            if (($resetRow['used_at'] ?? null) !== null) {
                continue;
            }

            $this->resetRecords->delete((int) $resetRow['id']);
        }
    }

    /**
     * Создаёт токен.
     *
     * @param int $userId Учётка.
     * @param string $tokenHash Хеш.
     * @param DateTime $expiresAt Срок.
     *
     * @return int Новый id.
     *
     * @throws AuthDuplicateException Если хеш занят.
     * @throws AuthInvalidException Если поля недопустимы.
     */
    public function add(int $userId, string $tokenHash, DateTime $expiresAt): int
    {
        return $this->writeGuard->run(function () use ($userId, $tokenHash, $expiresAt): int {
            return $this->resetRecords->add([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'used_at' => null,
            ]);
        });
    }

    /**
     * Забирает токен: delete по id. Второй параллельный вызов получает RowNotFound.
     *
     * @param int $resetId Id строки.
     *
     * @return void
     *
     * @throws AuthInvalidException Если строки уже нет.
     */
    public function consume(int $resetId): void
    {
        $this->writeGuard->run(function () use ($resetId): mixed {
            $this->resetRecords->delete($resetId);

            return null;
        });
    }
}
