<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Общие проверки формы конверта game.runAction.
 */
final class GameRunActionFields
{
    /**
     * Ровно названные ключи.
     *
     * @param array<string, mixed> $body Тело.
     * @param list<string> $allowed Ключи.
     * @param string $subject Подлежащее.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function assertKeys(array $body, array $allowed, string $subject = 'action'): void
    {
        $keys = array_keys($body);
        sort($keys);
        sort($allowed);
        if ($keys !== $allowed) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $subject);
        }
    }

    /**
     * Участник с ключом type.
     *
     * @param mixed $side Объект.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function side(mixed $side): array
    {
        if (!is_array($side)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        $this->assertKeys($side, ['id', 'type'], 'side');
        $type = $side['type'] ?? null;
        $id = $side['id'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        if (!is_int($id) || $id < 1) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Участник с ключом kind.
     *
     * @param mixed $pair Объект.
     *
     * @return array{kind: string, id: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function pair(mixed $pair): array
    {
        if (!is_array($pair)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: pair');
        }

        $this->assertKeys($pair, ['id', 'kind'], 'pair');
        $kind = $pair['kind'] ?? null;
        $id = $pair['id'] ?? null;
        if (!in_array($kind, ['character', 'npc'], true)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: pair');
        }

        if (!is_int($id) || $id < 1) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: pair');
        }

        return ['kind' => $kind, 'id' => $id];
    }

    /**
     * Контекст процесса.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array{processRuleCode: string|null, processStepCode: string|null} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function process(array $context): array
    {
        $code = $this->code($context, 'processRuleCode');
        $step = $this->code($context, 'processStepCode');
        if ($code === null && $step !== null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: process context');
        }

        return ['processRuleCode' => $code, 'processStepCode' => $step];
    }

    /**
     * Код процесса или null.
     *
     * @param array<string, mixed> $context Контекст.
     * @param string $key Ключ.
     *
     * @return string|null Код или null.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function code(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: process context');
        }

        return $value;
    }
}
