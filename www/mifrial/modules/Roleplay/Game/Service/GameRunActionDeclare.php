<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Объявление карточки действия, реакции и раны.
 */
final class GameRunActionDeclare
{
    /**
     * Создаёт сценарий объявления.
     *
     * @param GameActionDeclare $actions Карточки действий.
     * @param GameWoundActions $wounds Зажим и перевязка.
     * @param GameRunActionSpellDeclare $spells Заклинания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionDeclare $actions,
        private readonly GameWoundActions $wounds,
        private readonly GameRunActionSpellDeclare $spells,
    ) {
    }

    /**
     * Объявляет карточку, реакцию или рану.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED.
     * @throws GameInvalidException Если действие отвергнуто.
     */
    public function declare(array $parsed, GameRunActionCall $call): array
    {
        return match ($parsed['kind']) {
            'spell' => $this->spells->declare($parsed, $call),
            'wound' => $this->wound($parsed, $call),
            'reaction' => $this->reaction($parsed, $call),
            default => $this->action($parsed, $call),
        };
    }

    /**
     * Карточка действия.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED.
     * @throws GameInvalidException Если действие отвергнуто.
     */
    private function action(array $parsed, GameRunActionCall $call): array
    {
        $context = $parsed['context'];
        $result = $this->actions->act($call->gameId, $call->actorUserId, $call->editAll, $call->viewAll, [
            'battleId' => $call->battleId,
            'idempotencyKey' => $call->idempotencyKey,
            'expectedVersion' => $call->expectedVersion,
            'expectedSheetVersion' => $context['expectedSheetVersion'],
            'actor' => $parsed['actor'],
            'actionRuleCode' => $parsed['code'],
            'followUpTargetKey' => $context['followUpTargetKey'],
            'chosenAmounts' => $context['chosenAmounts'],
            'processRuleCode' => $context['processRuleCode'],
            'processStepCode' => $context['processStepCode'],
        ]);

        return $this->result(
            $result,
            'action',
            $result['battleId'] ?? $call->battleId,
            $result['version'] ?? $call->expectedVersion,
        );
    }

    /**
     * Реакция в открытом ударе.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED.
     * @throws GameInvalidException Если действие отвергнуто.
     */
    private function reaction(array $parsed, GameRunActionCall $call): array
    {
        $result = $this->actions->reaction($call->gameId, $call->actorUserId, $call->editAll, $call->viewAll, [
            'battleId' => $call->battleId,
            'idempotencyKey' => $call->idempotencyKey,
            'expectedVersion' => $call->expectedVersion,
            'actor' => $parsed['actor'],
            'actionRuleCode' => $parsed['code'],
            'context' => $parsed['context'],
        ]);

        return $this->result(
            $result,
            'reaction',
            $result['battleId'] ?? $call->battleId,
            $result['version'] ?? $call->expectedVersion,
        );
    }

    /**
     * Зажим, отпускание или перевязка.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED.
     * @throws GameInvalidException Если рана отвергнута.
     */
    private function wound(array $parsed, GameRunActionCall $call): array
    {
        $context = $parsed['context'];
        $result = $this->wounds->act($call->gameId, $call->actorUserId, $call->editAll, $call->viewAll, [
            'idempotencyKey' => $call->idempotencyKey,
            'operation' => $context['operation'],
            'actor' => ['kind' => $parsed['actor']['type'], 'id' => $parsed['actor']['id']],
            'target' => $context['target'],
            'stateId' => $context['stateId'],
            'actionRuleCode' => $parsed['code'],
            'expectedSheetVersion' => $context['expectedSheetVersion'],
            'battleId' => $call->battleId,
            'expectedBattleVersion' => $call->expectedVersion,
        ]);
        $version = is_int($result['battleVersion'] ?? null) ? $result['battleVersion'] : $call->expectedVersion;

        return $this->result($result, 'wound', $call->battleId, $version);
    }

    /**
     * Дополняет итог объявления видом и фазой.
     *
     * @param array<string, mixed> $result Итог домена.
     * @param string $kind Вид.
     * @param mixed $battleId Бой.
     * @param mixed $version Версия.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(array $result, string $kind, mixed $battleId, mixed $version): array
    {
        return array_merge($result, [
            'kind' => $kind,
            'phase' => 'declare',
            'battleId' => $battleId,
            'version' => $version,
        ]);
    }
}
