<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Доступ к карточке и подписка доставки ударного домена.
 */
final class GameStrikeCardWiring
{
    /**
     * Фильтр принадлежности карточки.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameCardAccess Фильтр.
     */
    public function card(IServiceLocator $serviceLocator): GameCardAccess
    {
        $gateway = (new GameStrikePorts($serviceLocator))->gateway();

        return new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
    }

    /**
     * Подписывает доставку игровых событий.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param GameCardAccess $card Фильтр карточки.
     *
     * @return void
     */
    public function delivery(IServiceLocator $serviceLocator, GameCardAccess $card): void
    {
        $ports = new GameStrikePorts($serviceLocator);
        GameDeliveryListener::register(
            $ports->events(),
            new GameDelivery($ports->gateway(), $ports->games(), $card),
        );
    }
}
