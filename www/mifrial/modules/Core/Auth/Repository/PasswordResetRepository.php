<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Repository;

use Closure;
use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `auth_password_reset`.
 */
final class PasswordResetRepository
{
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
        return $this->write(function () use ($userId, $tokenHash, $expiresAt): int {
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
        $this->write(function () use ($resetId): mixed {
            $this->resetRecords->delete($resetId);

            return null;
        });
    }

    /**
     * Мапит ошибки строки.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат.
     *
     * @throws AuthDuplicateException Если unique.
     * @throws AuthInvalidException Если поле/карта/нет строки.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new AuthDuplicateException($exception);
        } catch (RowNotFoundException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (FieldRequiredException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (FieldInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (MapInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        }
    }
}
