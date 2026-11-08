<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Notes\Interface\Container\INotesContainer;
use Mifrial\Messages\Notes\Interface\Service\INotes;
use Mifrial\Messages\Notes\Repository\NoteRepository;
use Mifrial\Messages\Notes\Table\NoteTable;

/**
 * Сборка фасада и HTTP заметок.
 */
final class NotePortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return INotes Фасад.
     *
     * @throws KernelException Если нет шлюза таблиц.
     */
    public function create(IServiceLocator $serviceLocator): INotes
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $records = $gateway->open(NoteTable::class)->records();

        return new Notes(new NoteRepository($gateway, $records));
    }

    /**
     * Создаёт HTTP-сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return NoteHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createHttp(IServiceLocator $serviceLocator): NoteHttpService
    {
        return new NoteHttpService(
            $this->userAccess($serviceLocator),
            $this->notes($serviceLocator),
            new NoteViewAssembler($this->userAccounts($serviceLocator)),
        );
    }

    /**
     * Берёт HTTP-сценарий из контейнера Notes.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return NoteHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): NoteHttpService
    {
        $notesContainer = $serviceLocator->get(INotesContainer::class);
        $noteHttpService = $notesContainer->get(NoteHttpService::class);
        if (!$noteHttpService instanceof NoteHttpService) {
            throw new KernelException('PORT_TYPE', 'Notes HTTP service has a wrong type');
        }

        return $noteHttpService;
    }

    /**
     * Шлюз таблиц через контейнер SmartTable.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Notes requires ISmartTableGateway');
        }

        return $gateway;
    }

    /**
     * Учётки через контейнер User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccounts Фасад учётки.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function userAccounts(IServiceLocator $serviceLocator): IUserAccounts
    {
        $userAccounts = $serviceLocator->get(IUserContainer::class)->get(IUserAccounts::class);
        if (!$userAccounts instanceof IUserAccounts) {
            throw new KernelException('PORT_TYPE', 'Notes requires IUserAccounts');
        }

        return $userAccounts;
    }

    /**
     * Guard через контейнер User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Notes HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Фасад из контейнера Notes.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return INotes Фасад.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function notes(IServiceLocator $serviceLocator): INotes
    {
        $notes = $serviceLocator->get(INotesContainer::class)->get(INotes::class);
        if (!$notes instanceof INotes) {
            throw new KernelException('PORT_TYPE', 'Notes HTTP requires INotes');
        }

        return $notes;
    }
}
