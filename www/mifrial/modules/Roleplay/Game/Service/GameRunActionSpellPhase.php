<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Фазы resolve и abort отложенного заклинания.
 */
final class GameRunActionSpellPhase
{
    /**
     * Создаёт сценарий.
     *
     * @param GameSpellCast $cast Команда каста.
     *
     * @return void
     */
    public function __construct(private readonly GameSpellCast $cast)
    {
    }

    /**
     * Применяет выбранные шаги насыщения.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     */
    public function resolve(array $parsed, GameRunActionCall $call): array
    {
        return $this->run(
            $parsed,
            $call,
            'resolve',
            fn (array $body): array => $this->cast->resolve(
                $call->gameId,
                $call->actorUserId,
                $call->editAll,
                $call->viewAll,
                $body,
            ),
        );
    }

    /**
     * Прерывает отложенный каст.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     */
    public function abort(array $parsed, GameRunActionCall $call): array
    {
        return $this->run(
            $parsed,
            $call,
            'abort',
            fn (array $body): array => $this->cast->abort(
                $call->gameId,
                $call->actorUserId,
                $call->editAll,
                $call->viewAll,
                $body,
            ),
        );
    }

    /**
     * Собирает тело фазы и зовёт команду.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param GameRunActionCall $call Снимок вызова.
     * @param string $phase resolve или abort.
     * @param callable(array<string, mixed>): array<string, mixed> $command Команда.
     *
     * @return array<string, mixed> Итог.
     */
    private function run(
        array $parsed,
        GameRunActionCall $call,
        string $phase,
        callable $command,
    ): array {
        $context = $parsed['context'];
        $castId = is_string($context['castId'] ?? null) ? $context['castId'] : '';
        $body = [
            'gameId' => $call->gameId,
            'battleId' => $call->battleId,
            'idempotencyKey' => $castId . ':' . $phase,
            'expectedVersion' => $call->expectedVersion,
            'castId' => $castId,
        ];
        if ($phase === 'resolve') {
            $body['saturationSteps'] = $context['saturationSteps'] ?? 0;
        }

        $result = $command($body);

        return array_merge($result, [
            'battleId' => $call->battleId,
            'version' => $result['version'] ?? $call->expectedVersion,
        ]);
    }
}
