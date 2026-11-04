<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Exception\FileDeniedException;
use Mifrial\Core\File\Exception\FileNotFoundException;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Чтение и архив файла от имени текущего актора.
 */
final class FileReadService
{
    /**
     * Создаёт сервис.
     *
     * @param IUserAccess $userAccess Guard.
     * @param FileRepository $fileRepository Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly FileRepository $fileRepository,
    ) {
    }

    /**
     * Строка по public_id для текущего актора.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Запись; архивная тоже читается.
     *
     * @throws FileNotFoundException Если строки нет.
     */
    public function requireReadable(string $publicId): FileRecord
    {
        $this->userAccess->requireActor();

        return $this->requireRow($publicId);
    }

    /**
     * Строка, если владелец — текущий актор.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Своя запись.
     *
     * @throws FileNotFoundException Если строки нет.
     * @throws FileDeniedException Если владелец чужой.
     */
    public function requireOwned(string $publicId): FileRecord
    {
        $requestActor = $this->userAccess->requireActor();
        $fileRecord = $this->requireRow($publicId);
        if ($fileRecord->getOwnerId() !== $requestActor->getUserId()) {
            throw new FileDeniedException();
        }

        return $fileRecord;
    }

    /**
     * Архив владельцем.
     *
     * @param string $publicId Внешний id.
     *
     * @return void
     *
     * @throws FileNotFoundException Если строки нет.
     * @throws FileDeniedException Если владелец чужой.
     */
    public function archive(string $publicId): void
    {
        $fileRecord = $this->requireOwned($publicId);
        if ($fileRecord->isArchived()) {
            return;
        }

        $this->fileRepository->markArchived($fileRecord->getId(), DateTime::now());
    }

    /**
     * Строка по public_id или ошибка.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Запись.
     *
     * @throws FileNotFoundException Если строки нет.
     */
    private function requireRow(string $publicId): FileRecord
    {
        $fileRecord = $this->fileRepository->findByPublicId($publicId);
        if (!$fileRecord instanceof FileRecord) {
            throw new FileNotFoundException();
        }

        return $fileRecord;
    }
}
