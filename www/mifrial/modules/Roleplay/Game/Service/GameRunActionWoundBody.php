<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Контекст раны: зажим, отпускание или перевязка.
 */
final class GameRunActionWoundBody
{
    private const OPERATIONS = ['hold', 'release', 'bandage'];

    /**
     * Создаёт разбор раны.
     *
     * @param GameRunActionFields $fields Общие проверки.
     *
     * @return void
     */
    public function __construct(private readonly GameRunActionFields $fields)
    {
    }

    /**
     * Контекст раны.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $context): array
    {
        $this->fields->assertKeys(
            $context,
            ['expectedSheetVersion', 'operation', 'stateId', 'target'],
            'wound context',
        );
        $operation = $context['operation'] ?? null;
        $stateId = $context['stateId'] ?? null;
        $expected = $context['expectedSheetVersion'] ?? null;
        $target = $this->fields->pair($context['target'] ?? null);
        $this->assertContext($operation, $stateId, $expected);

        return [
            'operation' => $operation,
            'stateId' => $stateId,
            'expectedSheetVersion' => $expected,
            'target' => $target,
        ];
    }

    /**
     * Проверяет поля раны.
     *
     * @param mixed $operation Операция.
     * @param mixed $stateId Состояние.
     * @param mixed $expected Версия листа.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertContext(mixed $operation, mixed $stateId, mixed $expected): void
    {
        if (!is_string($operation) || !in_array($operation, self::OPERATIONS, true)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: wound context');
        }

        if (!is_int($stateId) || $stateId < 1) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: wound context');
        }

        if (!is_int($expected)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: wound context');
        }
    }
}
