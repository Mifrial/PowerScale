<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор конверта game.runAction. Готовые числа не принимаются.
 */
final class GameRunActionBody
{
    private const PHASES = ['declare', 'respond'];

    private const KINDS = ['action', 'strike', 'reaction', 'wound'];

    private readonly GameRunActionFields $fields;

    private readonly GameRunActionActionBody $actions;

    private readonly GameRunActionStrikeBody $strikes;

    private readonly GameRunActionDefenseBody $defenses;

    private readonly GameRunActionWoundBody $wounds;

    /**
     * Создаёт разбор конверта.
     *
     * @return void
     */
    public function __construct()
    {
        $this->fields = new GameRunActionFields();
        $this->actions = new GameRunActionActionBody($this->fields);
        $this->strikes = new GameRunActionStrikeBody($this->fields);
        $this->defenses = new GameRunActionDefenseBody($this->fields);
        $this->wounds = new GameRunActionWoundBody($this->fields);
    }

    /**
     * Разбирает действие.
     *
     * @param array<string, mixed> $action Конверт.
     *
     * @return array{
     *     phase: string,
     *     kind: string,
     *     code: string|null,
     *     actor: array{type: string, id: int}|null,
     *     context: array<string, mixed>
     * } Действие.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $action): array
    {
        $envelope = $this->envelope($action);

        return [
            'phase' => $envelope['phase'],
            'kind' => $envelope['kind'],
            'code' => $envelope['code'],
            'actor' => $this->actor($action, $envelope['phase'], $envelope['kind']),
            'context' => $this->context($envelope['kind'], $envelope['phase'], $envelope['context']),
        ];
    }

    /**
     * Проверяет конверт и достаёт обязательные поля.
     *
     * @param array<string, mixed> $action Конверт.
     *
     * @return array{phase: string, kind: string, code: string|null, context: array<string, mixed>} Поля.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function envelope(array $action): array
    {
        $phase = $this->phase($action);

        return [
            'phase' => $phase,
            'kind' => $this->kind($action),
            'code' => $this->code($action, $phase),
            'context' => $this->contextBody($action),
        ];
    }

    /**
     * Фаза и её ключи.
     *
     * @param array<string, mixed> $action Конверт.
     *
     * @return string Фаза.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function phase(array $action): string
    {
        $phase = $action['phase'] ?? null;
        $keys = ['context', 'kind', 'phase'];
        if ($phase === 'declare') {
            $keys[] = 'actor';
            $keys[] = 'code';
        }

        $this->fields->assertKeys($action, $keys);
        if (!is_string($phase) || !in_array($phase, self::PHASES, true)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action');
        }

        return $phase;
    }

    /**
     * Вид действия.
     *
     * @param array<string, mixed> $action Конверт.
     *
     * @return string Вид.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function kind(array $action): string
    {
        $kind = $action['kind'] ?? null;
        if (!is_string($kind) || !in_array($kind, self::KINDS, true)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action');
        }

        return $kind;
    }

    /**
     * Код объявления.
     *
     * @param array<string, mixed> $action Конверт.
     * @param string $phase Фаза.
     *
     * @return string|null Код или null.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function code(array $action, string $phase): ?string
    {
        $code = $action['code'] ?? null;
        if ($phase === 'declare' && (!is_string($code) || $code === '')) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action');
        }

        return is_string($code) ? $code : null;
    }

    /**
     * Тело контекста.
     *
     * @param array<string, mixed> $action Конверт.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function contextBody(array $action): array
    {
        $context = $action['context'] ?? null;
        if (!is_array($context)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action');
        }

        return $context;
    }

    /**
     * Актор объявления.
     *
     * @param array<string, mixed> $action Конверт.
     * @param string $phase Фаза.
     * @param string $kind Вид.
     *
     * @return array{type: string, id: int}|null Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function actor(array $action, string $phase, string $kind): ?array
    {
        $actor = array_key_exists('actor', $action) ? $this->fields->side($action['actor']) : null;
        if ($phase === 'respond' && $actor !== null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action actor');
        }

        if ($phase === 'respond' && $kind !== 'strike') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action respond');
        }

        return $actor;
    }

    /**
     * Контекст по виду и фазе.
     *
     * @param string $kind Вид.
     * @param string $phase Фаза.
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function context(string $kind, string $phase, array $context): array
    {
        return match ($kind) {
            'action' => $this->actions->parse($context),
            'wound' => $this->wounds->parse($context),
            'reaction' => $this->actions->reaction($context),
            default => $phase === 'declare' ? $this->strikes->parse($context) : $this->defenses->parse($context),
        };
    }
}
