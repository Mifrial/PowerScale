<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\ApplyGameEconomyInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameShopInput;
use Mifrial\Roleplay\Game\Dto\Action\ReplaceGameShopInput;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameEconomy;

/**
 * HTTP экономики. В handle action только этот сценарий.
 */
final class GameEconomyHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameEconomy $economy Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameEconomy $economy,
    ) {
    }

    /**
     * Проводит операцию.
     *
     * @param ApplyGameEconomyInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function apply(ApplyGameEconomyInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->economy->apply(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->idempotencyKey,
            $input->parts,
            $input->expectedVersions,
        );
    }

    /**
     * Читает магазин видимой карточки.
     *
     * @param GetGameShopInput $input JSON.
     *
     * @return array<string, mixed> Позиции.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function getShop(GetGameShopInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->economy->getShop(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
        );
    }

    /**
     * Заменяет магазин.
     *
     * @param ReplaceGameShopInput $input JSON.
     *
     * @return array<string, mixed> Позиции.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function replaceShop(ReplaceGameShopInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->economy->replaceShop(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->positions,
            $input->expectedPositions,
        );
    }
}
