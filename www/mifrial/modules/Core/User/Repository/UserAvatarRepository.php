<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Repository;

use Closure;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Ссылка учётка → файл аватара (`user_avatar`), без публичного порта.
 */
final class UserAvatarRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $avatarRecords Строки `user_avatar`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $avatarRecords,
    ) {
    }

    /**
     * Файл аватара учётки.
     *
     * @param int $userId Учётка.
     *
     * @return int|null file id или null.
     *
     * @throws UserInvalidException Если карта недопустима.
     */
    public function findFileId(int $userId): ?int
    {
        $row = $this->avatarRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['user_id' => $userId],
            'limit' => 1,
            'select' => ['file_id'],
        ]));
        if (!is_array($row)) {
            return null;
        }

        return (int) $row['file_id'];
    }

    /**
     * Файлы аватаров нескольких учёток.
     *
     * @param array<int, int> $userIds Учётки.
     *
     * @return array<int, int> user id → file id; без аватара ключа нет.
     *
     * @throws UserInvalidException Если карта недопустима.
     */
    public function getFileIdsByUserIds(array $userIds): array
    {
        $uniqueIds = [];
        foreach ($userIds as $userId) {
            $uniqueIds[$userId] = $userId;
        }

        if ($uniqueIds === []) {
            return [];
        }

        $fileIdsByUserId = [];
        $offset = 0;
        do {
            $pageRows = $this->avatarRecords->getList(ListQuery::fromOptions([
                'filter' => ['user_id' => array_values($uniqueIds)],
                'limit' => 500,
                'offset' => $offset,
                'select' => ['user_id', 'file_id'],
            ]))->rows();
            foreach ($pageRows as $avatarRow) {
                $fileIdsByUserId[(int) $avatarRow['user_id']] = (int) $avatarRow['file_id'];
            }

            $offset += 500;
        } while (count($pageRows) === 500);

        return $fileIdsByUserId;
    }

    /**
     * Ставит аватар: вставка или замена ссылки.
     *
     * @param int $userId Учётка.
     * @param int $fileId Файл.
     *
     * @return void
     *
     * @throws UserDuplicateException Если строка уже есть.
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserNotFoundException Если учётки нет.
     */
    public function save(int $userId, int $fileId): void
    {
        $rowId = $this->findRowId($userId);
        if ($rowId === null) {
            $this->write(fn (): int => $this->avatarRecords->add([
                'user_id' => $userId,
                'file_id' => $fileId,
            ]));

            return;
        }

        $this->write(function () use ($rowId, $fileId): mixed {
            $this->avatarRecords->update($rowId, ['file_id' => $fileId]);

            return null;
        });
    }

    /**
     * Снимает аватар.
     *
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserNotFoundException Если строки нет.
     */
    public function clear(int $userId): void
    {
        $rowId = $this->findRowId($userId);
        if ($rowId === null) {
            return;
        }

        $this->write(function () use ($rowId): mixed {
            $this->avatarRecords->delete($rowId);

            return null;
        });
    }

    /**
     * Id строки аватара.
     *
     * @param int $userId Учётка.
     *
     * @return int|null Id или null.
     */
    private function findRowId(int $userId): ?int
    {
        $row = $this->avatarRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['user_id' => $userId],
            'limit' => 1,
            'select' => ['id'],
        ]));
        if (!is_array($row)) {
            return null;
        }

        return (int) $row['id'];
    }

    /**
     * Выполняет запись и мапит ошибки строки в User.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат $work.
     *
     * @throws UserDuplicateException Если unique.
     * @throws UserInvalidException Если поле/карта.
     * @throws UserNotFoundException Если строки нет.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new UserDuplicateException($exception);
        } catch (RowNotFoundException $exception) {
            throw new UserNotFoundException($exception);
        } catch (FieldRequiredException $exception) {
            throw new UserInvalidException('Avatar field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new UserInvalidException('Avatar field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('Avatar map is invalid', $exception);
        }
    }
}
