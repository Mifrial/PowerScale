<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\DropGameSpellInput;
use Mifrial\Roleplay\Game\Dto\Action\SustainGameSpellInput;

/**
 * HTTP поддержания и прекращения заклинания.
 */
final class GameActiveSpellHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameTurns $turns Команды хода.
     * @param GameActiveSpellBody $body Разбор тела.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameTurns $turns,
        private readonly GameActiveSpellBody $body,
    ) {
    }

    /**
     * Продолжает поддержание.
     *
     * @param SustainGameSpellInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED, AUTH_DENIED или INVALID_PARAMS.
     */
    public function sustain(SustainGameSpellInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $body = $this->body->sustain($input);

        return $this->turns->sustainSpell(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $body,
        );
    }

    /**
     * Прекращает поддержание.
     *
     * @param DropGameSpellInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED, AUTH_DENIED или INVALID_PARAMS.
     */
    public function drop(DropGameSpellInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $body = $this->body->drop($input);

        return $this->turns->dropSpell(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $body,
        );
    }
}
