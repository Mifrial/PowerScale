<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GetGameActionOptionsInput;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * HTTP опций действий: сервер сам решает, что персонаж может объявить.
 */
final class GameActionOptionsHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameStrikeRules $rules Карточки.
     * @param GameStrikeSheets $sheets Листы.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Опции после удара для участника.
     *
     * @param GetGameActionOptionsInput $input JSON.
     *
     * @return array{afterStrikeOptions: list<array<string, mixed>>} Опции.
     *
     * @throws ActionException AUTH_REQUIRED, AUTH_DENIED или INVALID_PARAMS.
     */
    public function options(GetGameActionOptionsInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        if ($input->actorType !== 'character' && $input->actorType !== 'npc') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: actor');
        }

        $game = $this->games->get($input->gameId);
        if (!$this->cardAccess->isVisible($game, $actor->getUserId(), $actor->hasKey(GamePermissionKeys::VIEW_ALL))) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $levels = $this->sheets->abilityLevels($game, $input->actorType, $input->actorId);

        return ['afterStrikeOptions' => (new GameStrikeOptions())->available($slice, $levels)];
    }
}
