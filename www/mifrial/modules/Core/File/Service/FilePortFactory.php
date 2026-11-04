<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\File\Interface\Container\IFileContainer;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Сборка сервисов File из локатора.
 */
final class FilePortFactory
{
    /**
     * Порт метаданных и доступа для соседей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IFiles Порт.
     */
    public function create(IServiceLocator $serviceLocator): IFiles
    {
        $fileRepository = $this->fileRepository($serviceLocator);
        $userAccess = $this->userAccess($serviceLocator);
        $fileSettings = $this->fileSettings($serviceLocator);
        $fileStorage = (new FileStorageFactory())->create($fileSettings);

        return new FilesService(
            new FileReadService($userAccess, $fileRepository),
            $fileRepository,
            $fileStorage,
            new FileIntake($fileRepository, $fileStorage, $fileSettings),
            $userAccess,
        );
    }

    /**
     * HTTP-сценарий из контейнера File.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return FileHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): FileHttpService
    {
        $fileContainer = $serviceLocator->get(IFileContainer::class);
        $fileHttpService = $fileContainer->get(FileHttpService::class);
        if (!$fileHttpService instanceof FileHttpService) {
            throw new KernelException('PORT_TYPE', 'File HTTP service has a wrong type');
        }

        return $fileHttpService;
    }

    /**
     * HTTP-сценарий из локатора.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return FileHttpService Сценарий.
     */
    public function createHttp(IServiceLocator $serviceLocator): FileHttpService
    {
        return new FileHttpService(
            $this->userAccess($serviceLocator),
            $this->fileRepository($serviceLocator),
            new FilePageQueryParser(),
            new FileViewAssembler(),
            $this->createUpload($serviceLocator),
            $this->createByte($serviceLocator),
        );
    }

    /**
     * Сервис загрузки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return FileUploadService Сервис.
     */
    public function createUpload(IServiceLocator $serviceLocator): FileUploadService
    {
        $fileSettings = $this->fileSettings($serviceLocator);

        return new FileUploadService(
            $this->userAccess($serviceLocator),
            new FileIntake(
                $this->fileRepository($serviceLocator),
                (new FileStorageFactory())->create($fileSettings),
                $fileSettings,
            ),
            new FileViewAssembler(),
        );
    }

    /**
     * Сервис отдачи байтов.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return FileByteService Сервис.
     */
    public function createByte(IServiceLocator $serviceLocator): FileByteService
    {
        return new FileByteService(
            $this->create($serviceLocator),
            (new FileStorageFactory())->create($this->fileSettings($serviceLocator)),
            new FileByteEmitter(),
        );
    }

    /**
     * Репозиторий строк.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return FileRepository Строки.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function fileRepository(IServiceLocator $serviceLocator): FileRepository
    {
        return new FileRepository($this->smartTableGateway($serviceLocator)->open(FileTable::class)->records());
    }

    /**
     * Настройки `files` из local.php.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return FileSettings Настройки.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function fileSettings(IServiceLocator $serviceLocator): FileSettings
    {
        $runtimeConfig = $serviceLocator->get(IKernelContainer::class)->get(IRuntimeConfig::class);
        if (!$runtimeConfig instanceof IRuntimeConfig) {
            throw new KernelException('PORT_TYPE', 'File requires IRuntimeConfig');
        }

        return FileSettings::fromSection(
            $runtimeConfig->section('files'),
            dirname(__DIR__, 4) . '/var/file',
        );
    }

    /**
     * Guard учёток.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если тип чужой.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'File requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'File requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
