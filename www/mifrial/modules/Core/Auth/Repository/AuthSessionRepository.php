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
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `auth_session`.
 */
final class AuthSessionRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $sessionRecords Строки сессии.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $sessionRecords,
    ) {
    }

    /**
     * Ищет сессию по хешу токена.
     *
     * @param string $tokenHash SHA-256 сырого токена.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        return $this->sessionRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['token_hash' => $tokenHash],
            'limit' => 1,
        ]));
    }

    /**
     * Создаёт сессию.
     *
     * @param int $userId Учётка.
     * @param string $tokenHash Хеш токена.
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
            return $this->sessionRecords->add([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    /**
     * Удаляет сессию по id, если строка есть.
     *
     * @param int $sessionId Id.
     *
     * @return void
     */
    public function deleteById(int $sessionId): void
    {
        if ($this->sessionRecords->getById($sessionId) === null) {
            return;
        }

        $this->sessionRecords->delete($sessionId);
    }

    /**
     * Мапит ошибки строки.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат.
     *
     * @throws AuthDuplicateException Если unique.
     * @throws AuthInvalidException Если поле/карта.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new AuthDuplicateException($exception);
        } catch (FieldRequiredException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (FieldInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (MapInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        }
    }
}
