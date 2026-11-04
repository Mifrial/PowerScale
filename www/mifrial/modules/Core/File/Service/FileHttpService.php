<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\Action\FindFilePageInput;
use Mifrial\Core\File\Dto\IncomingUpload;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * HTTP-сценарии файлов: админ-страница, upload и байты.
 */
final class FileHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard админских действий.
     * @param FileRepository $fileRepository Строки.
     * @param FilePageQueryParser $filePageQueryParser Разбор входа.
     * @param FileViewAssembler $fileViewAssembler JSON.
     * @param FileUploadService $fileUploadService Загрузка.
     * @param FileByteService $fileByteService Отдача байтов.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly FileRepository $fileRepository,
        private readonly FilePageQueryParser $filePageQueryParser,
        private readonly FileViewAssembler $fileViewAssembler,
        private readonly FileUploadService $fileUploadService,
        private readonly FileByteService $fileByteService,
    ) {
    }

    /**
     * Страница файлов.
     *
     * @param FindFilePageInput $input JSON.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function findPage(FindFilePageInput $input): array
    {
        $this->userAccess->requireKey(FilePermissionKeys::VIEW);
        $page = $this->fileRepository->findPage($this->filePageQueryParser->parse($input));
        $items = [];
        foreach ($page->getRecords() as $fileRecord) {
            $items[] = $this->fileViewAssembler->assemble($fileRecord);
        }

        return [
            'items' => $items,
            'total' => $page->getTotal(),
        ];
    }

    /**
     * Одна запись.
     *
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> FileEntry.
     */
    public function get(int $id): array
    {
        $this->userAccess->requireKey(FilePermissionKeys::VIEW);

        return $this->fileViewAssembler->assemble($this->fileRepository->getById($id));
    }

    /**
     * Приём файла актора.
     *
     * @param IncomingUpload $incomingUpload Вход multipart.
     *
     * @return array<string, mixed> FileEntry.
     */
    public function upload(IncomingUpload $incomingUpload): array
    {
        return $this->fileUploadService->upload($incomingUpload);
    }

    /**
     * Отдача байтов по public_id.
     *
     * @param string $publicId Внешний id.
     * @param string|null $ifNoneMatch Заголовок клиента.
     *
     * @return void
     */
    public function sendBytes(string $publicId, ?string $ifNoneMatch): void
    {
        $this->fileByteService->emit($publicId, $ifNoneMatch);
    }
}
