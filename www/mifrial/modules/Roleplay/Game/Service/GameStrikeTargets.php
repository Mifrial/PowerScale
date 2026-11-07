<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Список целей широкого удара.
 */
final class GameStrikeTargets
{
    /**
     * Создаёт разбор целей.
     *
     * @param GameStrikeBodyFields $fields Проверки формы.
     * @param GameStrikeConditions $conditions Условия удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBodyFields $fields,
        private readonly GameStrikeConditions $conditions,
    ) {
    }

    /**
     * Не меньше одной разной цели и их условия.
     *
     * @param mixed $targets Список.
     *
     * @return array{targets: list<array{type: string, id: int}>, conditions: array<string, array<string, mixed>>} Цели и условия.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(mixed $targets): array
    {
        if (!is_array($targets) || !array_is_list($targets) || count($targets) < 1) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: targets');
        }

        $sides = [];
        $conditions = [];
        foreach ($targets as $target) {
            $side = $this->side($target);
            $key = $side['type'] . ':' . $side['id'];
            if (isset($conditions[$key])) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: targets');
            }

            $sides[] = ['type' => $side['type'], 'id' => $side['id']];
            $conditions[$key] = $side['conditions'];
        }

        return ['targets' => $sides, 'conditions' => $conditions];
    }

    /**
     * Цель с необязательными условиями.
     *
     * @param mixed $target Объект.
     *
     * @return array{type: string, id: int, conditions: array<string, mixed>} Цель.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function side(mixed $target): array
    {
        if (!is_array($target)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: target');
        }

        $allowed = $this->fields->optional($target, ['id', 'type'], ['conditions']);
        $this->fields->assertKeys($target, $allowed, 'target');
        $side = $this->fields->side(['type' => $target['type'] ?? null, 'id' => $target['id'] ?? null]);

        return [
            'type' => $side['type'],
            'id' => $side['id'],
            'conditions' => $this->conditions->parse($target['conditions'] ?? null),
        ];
    }
}
