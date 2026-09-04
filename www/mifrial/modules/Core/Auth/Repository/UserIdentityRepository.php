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
 * Строки `user_identity`.
 */
final class UserIdentityRepository
{
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
        return $this->write(function () use ($userId, $secretHash): int {
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
        $this->write(function () use ($identityId, $usedAt): mixed {
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
        $this->write(function () use ($identityId, $secretHash): mixed {
            $this->identityRecords->update($identityId, ['secret_hash' => $secretHash]);

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
