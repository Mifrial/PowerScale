<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Setup;

use Mifrial\Core\File\Interface\Service\IFileStorage;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\File\Schema\FileSchema;
use Mifrial\Core\Image\Interface\Service\IImageFrame;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта File для CLI setup.
 */
final class FileModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
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
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return FileSchema::getTableClasses();
    }

    /**
     * Досчёт видимой рамки у старых файлов.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [
            new BackfillContentBoxStep($this->fileRepository, $this->fileStorage, $this->imageFrame),
        ];
    }
}
