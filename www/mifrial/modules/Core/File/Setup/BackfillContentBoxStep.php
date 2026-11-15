<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Setup;

use Mifrial\Core\File\Dto\FilePageQuery;
use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Interface\Service\IFileStorage;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\Image\Interface\Service\IImageFrame;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Throwable;

/**
 * Досчитывает видимую рамку у файлов, загруженных до её появления.
 *
 * Шаг одноразовый и идемпотентный: повторный запуск не попадёт в реестр.
 * Битая строка не валит установку — у неё просто не будет рамки, и потребитель
 * нарисует картинку целиком.
 */
final class BackfillContentBoxStep implements ISetupStep
{
    private const PAGE_SIZE = 100;

    /**
     * Создаёт шаг.
     *
     * @param FileRepository $fileRepository Строки файлов.
     * @param IFileStorage $fileStorage Диск.
     * @param IImageFrame $imageFrame Видимая рамка картинки.
     *
     * @return void
     */
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly IFileStorage $fileStorage,
        private readonly IImageFrame $imageFrame,
    ) {
    }

    /**
     * Стабильный id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Core/File:backfill.content-box';
    }

    /**
     * Считает рамку для всех картинок без неё.
     *
     * @return void
     */
    public function run(): void
    {
        $offset = 0;
        do {
            $page = $this->fileRepository->findPage(
                new FilePageQuery(self::PAGE_SIZE, $offset, null, null, null, null, null, null),
            );
            foreach ($page->getRecords() as $fileRecord) {
                $this->fillFrame($fileRecord);
            }

            $offset += self::PAGE_SIZE;
        } while (count($page->getRecords()) === self::PAGE_SIZE);
    }

    /**
     * Считает и сохраняет рамку одной картинки.
     *
     * @param FileRecord $fileRecord Строка.
     *
     * @return void
     */
    private function fillFrame(FileRecord $fileRecord): void
    {
        if ($fileRecord->getContentBox() !== null || !str_starts_with($fileRecord->getMime(), 'image/')) {
            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mifrial-frame-');
        if ($temporaryPath === false) {
            return;
        }

        try {
            $this->fileStorage->exportTo($fileRecord->getStoragePath(), $temporaryPath);
            $this->fileRepository->updateContentBox($fileRecord->getId(), $this->imageFrame->frame($temporaryPath));
        } catch (Throwable $failure) {
            // Файл мог переехать или не читаться драйвером: строка остаётся без
            // рамки, установка продолжается.
            unset($failure);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
