<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\File\Interface\Service\IFileStorage;
use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Сценарий байтового entrypoint: доступ, затем эмиттер.
 */
final class FileByteService
{
    /**
     * Создаёт сценарий.
     *
     * @param IFiles $files Метаданные и доступ.
     * @param IFileStorage $fileStorage Диск.
     * @param FileByteEmitter $fileByteEmitter Отправитель.
     *
     * @return void
     */
    public function __construct(
        private readonly IFiles $files,
        private readonly IFileStorage $fileStorage,
        private readonly FileByteEmitter $fileByteEmitter,
    ) {
    }

    /**
     * Отдаёт байты по public_id или голый статус.
     *
     * @param string $publicId Внешний id.
     * @param string|null $ifNoneMatch Заголовок клиента.
     *
     * @return void
     */
    public function emit(string $publicId, ?string $ifNoneMatch): void
    {
        try {
            $fileRecord = $this->files->requireReadable($publicId);
        } catch (ActionException $exception) {
            $this->fileByteEmitter->emitError($this->statusFor($exception));

            return;
        }

        if ($fileRecord->isArchived()) {
            $this->fileByteEmitter->emitError(404);

            return;
        }

        $this->fileByteEmitter->emit($fileRecord, $this->fileStorage, $ifNoneMatch);
    }

    /**
     * HTTP-код для ожидаемого отказа порта.
     *
     * @param ActionException $exception Отказ.
     *
     * @return int 403, 404 или 500.
     */
    private function statusFor(ActionException $exception): int
    {
        return match ($exception->getErrorCode()) {
            'AUTH_REQUIRED', 'AUTH_DENIED', 'FILE_DENIED' => 403,
            'FILE_NOT_FOUND' => 404,
            default => 500,
        };
    }
}
