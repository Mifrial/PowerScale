<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameChecks;
use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Сборка проверки. Регистрирует outbox для события check.
 */
final class GameCheckPortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IGameChecks Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    public function create(IServiceLocator $serviceLocator): IGameChecks
    {
        $gateway = $this->gateway($serviceLocator);
        $characters = $this->port($serviceLocator, ICharacterContainer::class, ICharacters::class, 'ICharacters');
        $events = $this->events($serviceLocator);
        $card = new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
        GameDeliveryListener::register($events, new GameDelivery($gateway, $this->games($serviceLocator), $card));

        return new GameChecks(
            $gateway,
            new GameCheckScope(
                $this->games($serviceLocator),
                $card,
                $this->processes($serviceLocator),
            ),
            $this->checkRoll($serviceLocator, $characters),
            $this->checkWrite($serviceLocator, $gateway, $characters, $events),
        );
    }

    /**
     * HTTP проверки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameCheckHttp Сценарий.
     *
     * @throws KernelException Если порта нет.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameCheckHttp
    {
        $access = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$access instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Game check requires IUserAccess');
        }

        return new GameCheckHttp($access, $this->create($serviceLocator));
    }

    /**
     * Расчёт проверки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param ICharacters $characters Персонажи.
     *
     * @return GameCheckRoll Расчёт.
     *
     * @throws KernelException Если порта нет.
     */
    private function checkRoll(IServiceLocator $serviceLocator, ICharacters $characters): GameCheckRoll
    {
        return new GameCheckRoll(
            $this->port($serviceLocator, ICharacterContainer::class, ICharacterRuleSlices::class, 'ICharacterRuleSlices'),
            $characters,
            $this->port($serviceLocator, IMechanicContainer::class, IMechanics::class, 'IMechanics'),
            $this->port($serviceLocator, IMechanicContainer::class, IMechanicRolls::class, 'IMechanicRolls'),
            $this->port($serviceLocator, ICharacterContainer::class, ICharacterFormulaContexts::class, 'ICharacterFormulaContexts'),
            $this->port($serviceLocator, ICharacterContainer::class, ICharacterSheets::class, 'ICharacterSheets'),
        );
    }

    /**
     * Запись, чат и доставка проверки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param ISmartTableGateway $gateway Шлюз.
     * @param ICharacters $characters Персонажи.
     * @param IEventManager $events События.
     *
     * @return GameCheckWrite Порты записи.
     *
     * @throws KernelException Если порта нет.
     */
    private function checkWrite(
        IServiceLocator $serviceLocator,
        ISmartTableGateway $gateway,
        ICharacters $characters,
        IEventManager $events,
    ): GameCheckWrite {
        return new GameCheckWrite(
            $this->port($serviceLocator, ICharacterContainer::class, ICharacterActualMutations::class, 'ICharacterActualMutations'),
            new GameCheckChat(
                $this->chats($serviceLocator),
                new GameCheckSpeakers($characters, new GameNpcRepository($gateway)),
            ),
            new GameConcentration(),
            new GameConcentrationCycle(),
            new GameDeliverySignal($events),
        );
    }



    /**
     * Шлюз.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порта нет.
     */
    private function gateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Game check requires ISmartTableGateway');
        }

        return $gateway;
    }

    /**
     * События.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IEventManager Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function events(IServiceLocator $serviceLocator): IEventManager
    {
        $events = $serviceLocator->get(IEventContainer::class)->get(IEventManager::class);
        if (!$events instanceof IEventManager) {
            throw new KernelException('PORT_TYPE', 'Game check requires IEventManager');
        }

        return $events;
    }

    /**
     * Строка игры.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IGames Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function games(IServiceLocator $serviceLocator): IGames
    {
        $games = $serviceLocator->get(IGameContainer::class)->get(IGames::class);
        if (!$games instanceof IGames) {
            throw new KernelException('PORT_TYPE', 'Game check requires IGames');
        }

        return $games;
    }

    /**
     * Чат игры.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IChats Сервис чата.
     *
     * @throws KernelException Если порта нет.
     */
    private function chats(IServiceLocator $serviceLocator): IChats
    {
        $chats = $serviceLocator->get(IChatContainer::class)->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Game check requires IChats');
        }

        return $chats;
    }

    /**
     * Строка process.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IGameProcesses Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function processes(IServiceLocator $serviceLocator): IGameProcesses
    {
        $processes = $serviceLocator->get(IGameContainer::class)->get(IGameProcesses::class);
        if (!$processes instanceof IGameProcesses) {
            throw new KernelException('PORT_TYPE', 'Game check requires IGameProcesses');
        }

        return $processes;
    }

    /**
     * Порт контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param class-string $container Контейнер.
     * @param class-string $port Порт.
     * @param string $name Имя в ошибке.
     *
     * @return object Реализация.
     *
     * @throws KernelException Если порта нет.
     */
    private function port(IServiceLocator $serviceLocator, string $container, string $port, string $name): object
    {
        $resolved = $serviceLocator->get($container)->get($port);
        if (!$resolved instanceof $port) {
            throw new KernelException('PORT_TYPE', 'Game check requires ' . $name);
        }

        return $resolved;
    }
}
