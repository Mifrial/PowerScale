<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\File\Interface\Container\IFileContainer;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\Image\Interface\Container\IImageContainer;
use Mifrial\Core\Image\Interface\Service\IImageTransform;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserAvatar;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Interface\Service\IUserViews;
use Mifrial\Core\User\Repository\UserAvatarRepository;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Table\UserAvatarTable;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;

/**
 * Сборка HTTP-сценария учётки.
 */
final class UserHttpServiceFactory
{
    /**
     * Создаёт сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function create(IServiceLocator $serviceLocator): UserHttpService
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $userAccess = $userContainer->get(IUserAccess::class);
        $userViews = $userContainer->get(IUserViews::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        if (
            !$userAccess instanceof IUserAccess
            || !$userViews instanceof IUserViews
            || !$userAccounts instanceof IUserAccounts
            || !$userGroups instanceof IUserGroups
        ) {
            throw new KernelException('PORT_TYPE', 'User HTTP ports have a wrong type');
        }

        return new UserHttpService(
            $userAccess,
            $userViews,
            $userAccounts,
            new UserMembershipSync($userAccess, $userGroups),
        );
    }

    /**
     * Создаёт guard.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если нет контекста.
     */
    public function createAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $kernelContainer = $serviceLocator->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        if (!$requestContext instanceof IRequestContext) {
            throw new KernelException('PORT_TYPE', 'User requires IRequestContext');
        }

        return new UserAccess($requestContext);
    }

    /**
     * Создаёт сборщик JSON.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserViews Сборщик.
     *
     * @throws KernelException Если нет шлюза SmartTable.
     */
    public function createViews(IServiceLocator $serviceLocator): IUserViews
    {
        $smartTableGateway = $this->smartTableGateway($serviceLocator);

        return new UserViewAssembler(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
            $this->avatarRepository($serviceLocator),
            $this->files($serviceLocator),
        );
    }

    /**
     * Сервис аватара учётки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAvatar Порт аватара.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createAvatar(IServiceLocator $serviceLocator): IUserAvatar
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $userAccess = $userContainer->get(IUserAccess::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userViews = $userContainer->get(IUserViews::class);
        if (
            !$userAccess instanceof IUserAccess
            || !$userAccounts instanceof IUserAccounts
            || !$userViews instanceof IUserViews
        ) {
            throw new KernelException('PORT_TYPE', 'User avatar ports have a wrong type');
        }

        return new UserAvatarService(
            $userAccess,
            $userAccounts,
            $userViews,
            $this->avatarRepository($serviceLocator),
            $this->files($serviceLocator),
            $this->imageTransform($serviceLocator),
        );
    }

    /**
     * Берёт HTTP-сценарий из контейнера User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): UserHttpService
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $userHttpService = $userContainer->get(UserHttpService::class);
        if (!$userHttpService instanceof UserHttpService) {
            throw new KernelException('PORT_TYPE', 'User HTTP service has a wrong type');
        }

        return $userHttpService;
    }

    /**
     * Шлюз SmartTable.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'User requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }

    /**
     * Ссылки аватаров.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserAvatarRepository Репозиторий.
     *
     * @throws KernelException Если шлюза нет.
     */
    private function avatarRepository(IServiceLocator $serviceLocator): UserAvatarRepository
    {
        return new UserAvatarRepository(
            $this->smartTableGateway($serviceLocator)->open(UserAvatarTable::class)->records(),
        );
    }

    /**
     * Порт файлов соседа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IFiles Порт.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function files(IServiceLocator $serviceLocator): IFiles
    {
        $files = $serviceLocator->get(IFileContainer::class)->get(IFiles::class);
        if (!$files instanceof IFiles) {
            throw new KernelException('PORT_TYPE', 'User requires IFiles');
        }

        return $files;
    }

    /**
     * Порт производных картинок.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IImageTransform Порт.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function imageTransform(IServiceLocator $serviceLocator): IImageTransform
    {
        $imageTransform = $serviceLocator->get(IImageContainer::class)->get(IImageTransform::class);
        if (!$imageTransform instanceof IImageTransform) {
            throw new KernelException('PORT_TYPE', 'User requires IImageTransform');
        }

        return $imageTransform;
    }
}
