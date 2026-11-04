<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\IncomingUpload;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\File\Exception\FileTooLargeException;
use Mifrial\Core\File\Exception\FileUploadBrokenException;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Приём multipart-файла: проверка кода upload и передача в FileIntake.
 */
final class FileUploadService
{
    /**
     * Создаёт сервис загрузки.
     *
     * @param IUserAccess $userAccess Guard.
     * @param FileIntake $fileIntake Приём локального файла.
     * @param FileViewAssembler $fileViewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly FileIntake $fileIntake,
        private readonly FileViewAssembler $fileViewAssembler,
    ) {
    }

    /**
     * Принимает файл актора и возвращает JSON-вид строки.
     *
     * @param IncomingUpload $incomingUpload Вход multipart.
     *
     * @return array<string, mixed> FileEntry.
     *
     * @throws FileInvalidException Если файл или имя недопустимы.
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileUploadBrokenException Если PHP не принял файл.
     */
    public function upload(IncomingUpload $incomingUpload): array
    {
        $requestActor = $this->userAccess->requireActor();
        $temporaryPath = $this->acceptedTemporaryPath($incomingUpload);

        return $this->fileViewAssembler->assemble(
            $this->fileIntake->insert(
                $requestActor->getUserId(),
                $temporaryPath,
                $incomingUpload->getOriginalName(),
            ),
        );
    }

    /**
     * Проверяет код UPLOAD_ERR_* и возвращает tmp.
     *
     * @param IncomingUpload $incomingUpload Вход.
     *
     * @return string Временный путь.
     *
     * @throws FileInvalidException Если файла нет.
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileUploadBrokenException Если PHP потерял файл.
     */
    private function acceptedTemporaryPath(IncomingUpload $incomingUpload): string
    {
        $this->assertUploadError($incomingUpload);
        $temporaryPath = $incomingUpload->getTemporaryPath();
        if ($temporaryPath === null) {
            throw new FileUploadBrokenException('Uploaded file has no temporary path');
        }

        if (!is_file($temporaryPath)) {
            throw new FileUploadBrokenException('Uploaded file is missing');
        }

        return $temporaryPath;
    }

    /**
     * Переводит UPLOAD_ERR_* в доменную ошибку.
     *
     * @param IncomingUpload $incomingUpload Вход.
     *
     * @return void
     *
     * @throws FileInvalidException Если файла нет.
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileUploadBrokenException Если PHP потерял тело запроса.
     */
    private function assertUploadError(IncomingUpload $incomingUpload): void
    {
        $uploadError = $incomingUpload->getUploadError();
        if ($uploadError === UPLOAD_ERR_OK) {
            return;
        }

        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new FileTooLargeException($this->fileIntake->maxSize());
        }

        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            throw new FileUploadBrokenException('PHP upload error: ' . $uploadError);
        }

        if ($incomingUpload->getContentLength() > $this->fileIntake->maxSize()) {
            throw new FileTooLargeException($this->fileIntake->maxSize());
        }

        if ($incomingUpload->getContentLength() > 0) {
            throw new FileUploadBrokenException('PHP dropped the upload body');
        }

        throw new FileInvalidException('No file uploaded');
    }
}
