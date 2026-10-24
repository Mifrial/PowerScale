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
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameBattles;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Сборка боя. Не двенадцатый метод GamePortFactory.
 */
final class GameBattlePortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameBattles Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IGameBattles
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $order = new GameBattleOrder($this->checkRoll($serviceLocator), new GameNpcRepository($gateway));

        return new GameBattles(
            $gateway,
            $this->games($serviceLocator),
            $this->cardAccess($gateway),
            new GameMemberRepository($gateway),
            new GameSessionRepository($gateway),
            new GameBattleMutator(
                new GameBattleRepository($gateway),
                new GameCommandRepository($gateway),
                new GameNpcRepository($gateway),
                new GameSessionRoster(new GameSessionRepository($gateway), new GameBattleRepository($gateway)),
                new GameProcessRepository($gateway),
                $order,
            ),
            $this->notices($serviceLocator),
            new GameDeliverySignal($this->events($serviceLocator)),
        );
    }

    /**
     * HTTP боя.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameBattleHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameBattleHttp
    {
        return new GameBattleHttp($this->userAccess($serviceLocator), $this->create($serviceLocator));
    }

    /**
     * HTTP инициативы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameBattleInitiativeHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createInitiativeHttp(IServiceLocator $serviceLocator): GameBattleInitiativeHttp
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new GameBattleInitiativeHttp(
            $this->userAccess($serviceLocator),
            new GameBattleInitiatives(
                $gateway,
                $this->games($serviceLocator),
                $this->cardAccess($gateway),
                new GameMemberRepository($gateway),
                new GameSessionRepository($gateway),
                new GameBattleOrder($this->checkRoll($serviceLocator), new GameNpcRepository($gateway)),
            ),
        );
    }

    /**
     * Шлюз таблиц.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порта нет.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Game requires ISmartTableGateway');
        }

        return $gateway;
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
            throw new KernelException('PORT_TYPE', 'Game battle requires IGames');
        }

        return $games;
    }

    /**
     * Видимость карточки.
     *
     * @param ISmartTableGateway $gateway Шлюз.
     *
     * @return GameCardAccess Фильтр.
     */
    private function cardAccess(ISmartTableGateway $gateway): GameCardAccess
    {
        return new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
    }

    /**
     * Тот же бросок, что у проверки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameCheckRoll Расчёт.
     *
     * @throws KernelException Если порта нет.
     */
    private function checkRoll(IServiceLocator $serviceLocator): GameCheckRoll
    {
        return new GameCheckRoll(
            $this->port($serviceLocator, ICharacterContainer::class, ICharacterRuleSlices::class),
            $this->port($serviceLocator, ICharacterContainer::class, ICharacters::class),
            $this->port($serviceLocator, IMechanicContainer::class, IMechanics::class),
            $this->port($serviceLocator, IMechanicContainer::class, IMechanicRolls::class),
            $this->port($serviceLocator, ICharacterContainer::class, ICharacterSheets::class),
        );
    }

    /**
     * Порт соседнего модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param class-string $container Контейнер.
     * @param class-string $port Порт.
     *
     * @return mixed Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function port(IServiceLocator $serviceLocator, string $container, string $port): mixed
    {
        $resolved = $serviceLocator->get($container)->get($port);
        if (!$resolved instanceof $port) {
            throw new KernelException('PORT_TYPE', 'Game battle requires ' . $port);
        }

        return $resolved;
    }

    /**
     * Актор запроса.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Доступ.
     *
     * @throws KernelException Если порта нет.
     */
    private function events(IServiceLocator $serviceLocator): IEventManager
    {
        $events = $serviceLocator->get(IEventContainer::class)->get(IEventManager::class);
        if (!$events instanceof IEventManager) {
            throw new KernelException('PORT_TYPE', 'Game battle requires IEventManager');
        }

        return $events;
    }

    private function notices(IServiceLocator $serviceLocator): GameCombatNotices
    {
        $chats = $serviceLocator->get(IChatContainer::class)->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Chat service has a wrong type');
        }

        return new GameCombatNotices($chats);
    }

    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Game battle requires IUserAccess');
        }

        return $userAccess;
    }
}
