<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\Action\CastGameSpellInput;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Объявление заклинания в едином входе действий.
 */
final class GameRunActionSpellDeclare
{
    /**
     * Создаёт сценарий.
     *
     * @param GameSpellCast $cast Команда каста.
     * @param GameSpellCastBody $body Разбор тела каста.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellCast $cast,
        private readonly GameSpellCastBody $body,
    ) {
    }

    /**
     * Объявляет каст.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если каст отвергнут.
     */
    public function declare(array $parsed, GameRunActionCall $call): array
    {
        $context = $parsed['context'];
        $actor = $parsed['actor'];
        $code = $parsed['code'];
        if (!is_array($actor) || !is_string($code) || $code === '') {
            throw new GameInvalidException('Game spell actor is invalid');
        }

        $body = $this->body->parse(new CastGameSpellInput(
            $call->gameId,
            $call->battleId,
            $call->idempotencyKey,
            $call->expectedVersion,
            $context['expectedSheetVersion'],
            $actor,
            $code,
            $context['pathCode'],
            $context['parameters'],
            $context['usedPower'],
            $context['expectedTargetVersion'],
            $context['target'],
            $context['distanceIpari'],
            $context['upgrades'] ?? [],
        ));
        $result = $this->cast->cast(
            $call->gameId,
            $call->actorUserId,
            $call->editAll,
            $call->viewAll,
            $body,
        );

        return array_merge($result, [
            'kind' => 'spell',
            'phase' => 'declare',
            'battleId' => $call->battleId,
            'version' => $call->expectedVersion,
        ]);
    }
}
