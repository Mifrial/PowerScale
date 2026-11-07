<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\User\Dto\UserAvatarRecord;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Repository\Support\UserWriteGuard;

/**
 * Ссылка учётка → файл аватара (`user_avatar`), без публичного порта.
 */
final class UserAvatarRepository
{
    private readonly UserWriteGuard $writeGuard;

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
        $this->writeGuard = new UserWriteGuard('Avatar');
    }

    /**
     * Строка аватара учётки.
     *
     * @param int $userId Учётка.
     *
     * @return UserAvatarRecord|null Запись или null.
     *
     * @throws UserInvalidException Если карта недопустима.
     */
    public function find(int $userId): ?UserAvatarRecord
    {
        $row = $this->readUnique(ListQuery::fromOptions([
            'filter' => ['user_id' => $userId],
            'limit' => 1,
            'select' => ['file_id', 'source_file_id', 'crop_x', 'crop_y', 'crop_size'],
        ]));

        return is_array($row) ? UserAvatarRecord::fromRow($row) : null;
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
            $pageRows = $this->readList(ListQuery::fromOptions([
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
     * Ставит аватар: вставка или замена ссылки и кропа.
     *
     * @param int $userId Учётка.
     * @param int $fileId Производная.
     * @param int|null $sourceFileId Исходник.
     * @param int|null $cropX Левый край.
     * @param int|null $cropY Верхний край.
     * @param int|null $cropSize Сторона квадрата.
     *
     * @return void
     *
     * @throws UserDuplicateException Если строка уже есть.
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserNotFoundException Если учётки нет.
     */
    public function save(
        int $userId,
        int $fileId,
        ?int $sourceFileId,
        ?int $cropX,
        ?int $cropY,
        ?int $cropSize,
    ): void {
        $fields = [
            'file_id' => $fileId,
            'source_file_id' => $sourceFileId,
            'crop_x' => $cropX,
            'crop_y' => $cropY,
            'crop_size' => $cropSize,
        ];
        $rowId = $this->findRowId($userId);
        if ($rowId === null) {
            $this->writeGuard->run(fn (): int => $this->avatarRecords->add(['user_id' => $userId] + $fields));

            return;
        }

        $this->writeGuard->run(function () use ($rowId, $fields): mixed {
            $this->avatarRecords->update($rowId, $fields);

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

        $this->writeGuard->run(function () use ($rowId): mixed {
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
        $row = $this->readUnique(ListQuery::fromOptions([
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
     * Читает одну строку и мапит отказ карты в User.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<string, mixed>|null Строка или null.
     *
     * @throws UserInvalidException Если карта недопустима.
     */
    private function readUnique(ListQuery $listQuery): ?array
    {
        try {
            $row = $this->avatarRecords->getUnique($listQuery);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('Avatar map is invalid', $exception);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Читает страницу строк и мапит отказ карты в User.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return ListResult Страница.
     *
     * @throws UserInvalidException Если карта недопустима.
     */
    private function readList(ListQuery $listQuery): ListResult
    {
        try {
            return $this->avatarRecords->getList($listQuery);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('Avatar list query is invalid', $exception);
        }
    }
}
