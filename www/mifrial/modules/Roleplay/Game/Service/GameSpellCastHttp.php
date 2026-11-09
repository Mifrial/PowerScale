<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\CastGameSpellInput;

/**
 * HTTP-сценарий game.castSpell.
 */
final class GameSpellCastHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameSpellCastBody $body Разбор тела.
     * @param GameSpellCast $cast Команда.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameSpellCastBody $body,
        private readonly GameSpellCast $cast,
    ) {
    }

    /**
     * Выполняет каст.
     *
     * @param CastGameSpellInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED, AUTH_DENIED или INVALID_PARAMS.
     */
    public function run(CastGameSpellInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $body = $this->body->parse($input);

        return $this->cast->cast(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $body,
        );
    }
}
