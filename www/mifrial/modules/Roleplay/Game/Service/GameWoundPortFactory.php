<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Сборка команд зажима и перевязки.
 */
final class GameWoundPortFactory
{
    /**
     * Доменные команды раны.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameWoundActions Команды.
     *
     * @throws KernelException Если порта нет.
     */
    public function createActions(IServiceLocator $serviceLocator): GameWoundActions
    {
        $ports = $this->ports($serviceLocator);
        $card = new GameCardAccess(
            new GameRepository($ports['gateway']),
            new GameMemberRepository($ports['gateway']),
            new GameInvitationRepository($ports['gateway']),
        );
        GameDeliveryListener::register($ports['events'], new GameDelivery(
            $ports['gateway'],
            $ports['games'],
            $card,
        ));

        return new GameWoundActions(
            $ports['gateway'],
            $ports['games'],
            $card,
            (new GameStrikePortFactory())->strikeRulesForWound($serviceLocator),
            $ports['mutations'],
            $ports['chats'],
            $ports['events'],
        );
    }

    /**
     * Порты сценария.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return array{
     *     gateway: ISmartTableGateway,
     *     games: IGames,
     *     mutations: ICharacterActualMutations,
     *     chats: IChats,
     *     events: IEventManager
     * } Порты.
     *
     * @throws KernelException Если порта нет.
     */
    private function ports(IServiceLocator $serviceLocator): array
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        $games = $serviceLocator->get(IGameContainer::class)->get(IGames::class);
        $mutations = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        $chats = $serviceLocator->get(IChatContainer::class)->get(IChats::class);
        $events = $serviceLocator->get(IEventContainer::class)->get(IEventManager::class);
        $ports = $gateway instanceof ISmartTableGateway
            && $games instanceof IGames
            && $mutations instanceof ICharacterActualMutations
            && $chats instanceof IChats
            && $events instanceof IEventManager;
        if (!$ports) {
            throw new KernelException('PORT_TYPE', 'Game wound action requires its ports');
        }

        return [
            'gateway' => $gateway,
            'games' => $games,
            'mutations' => $mutations,
            'chats' => $chats,
            'events' => $events,
        ];
    }
}
