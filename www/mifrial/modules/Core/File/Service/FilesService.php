<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Exception\FileDeniedException;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\File\Interface\Service\IFileStorage;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Порт файлов для соседей: чтение, архив, производные байты, проекция.
 */
final class FilesService implements IFiles
{
    /**
     * Создаёт порт.
     *
     * @param FileReadService $readService Чтение и архив.
     * @param FileRepository $fileRepository Строки.
     * @param IFileStorage $fileStorage Диск.
     * @param FileIntake $fileIntake Приём готовых байтов.
     * @param IUserAccess $userAccess Guard.
     *
     * @return void
     */
    public function __construct(
        private readonly FileReadService $readService,
        private readonly FileRepository $fileRepository,
        private readonly IFileStorage $fileStorage,
        private readonly FileIntake $fileIntake,
        private readonly IUserAccess $userAccess,
    ) {
    }

    /**
     * Строка по public_id для текущего актора.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Запись; архивная тоже читается.
     */
    public function requireReadable(string $publicId): FileRecord
    {
        return $this->readService->requireReadable($publicId);
    }

    /**
     * Строка, если владелец — текущий актор.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Своя запись.
     */
    public function requireOwned(string $publicId): FileRecord
    {
        return $this->readService->requireOwned($publicId);
    }

    /**
     * Архив владельцем.
     *
     * @param string $publicId Внешний id.
     *
     * @return void
     */
    public function archive(string $publicId): void
    {
        $this->readService->archive($publicId);
    }

    /**
     * Копирует байты своей активной строки во внешний файл.
     *
     * @param string $publicId Внешний id.
     * @param string $targetPath Абсолютный путь назначения.
     *
     * @return FileRecord Своя активная запись.
     *
     * @throws FileDeniedException Если строка чужая или архивная.
     */
    public function copyOwnedTo(string $publicId, string $targetPath): FileRecord
    {
        $fileRecord = $this->readService->requireOwned($publicId);
        if ($fileRecord->isArchived()) {
            throw new FileDeniedException();
        }

        $this->fileStorage->exportTo($fileRecord->getStoragePath(), $targetPath);

        return $fileRecord;
    }

    /**
     * Кладёт готовые байты новой строкой от имени текущего актора.
     *
     * @param string $sourcePublicId Внешний id исходника.
     * @param string $sourcePath Абсолютный путь к готовым байтам.
     * @param string $originalName Имя строки; пусто — имя исходника.
     *
     * @return FileRecord Новая строка.
     *
     * @throws FileDeniedException Если исходник чужой или архивный.
     */
    public function storeDerived(
        string $sourcePublicId,
        string $sourcePath,
        string $originalName,
    ): FileRecord {
        $sourceRecord = $this->readService->requireOwned($sourcePublicId);
        if ($sourceRecord->isArchived()) {
            throw new FileDeniedException();
        }

        $requestActor = $this->userAccess->requireActor();
        $name = $originalName !== '' ? $originalName : $sourceRecord->getOriginalName();

        return $this->fileIntake->insert($requestActor->getUserId(), $sourcePath, $name, true);
    }

    /**
     * Проекция id → public_id для живых строк.
     *
     * @param array<int, int> $fileIds Внутренние id.
     *
     * @return array<int, string> id → public_id.
     */
    public function activePublicIds(array $fileIds): array
    {
        return $this->fileRepository->activePublicIds($fileIds);
    }
}
