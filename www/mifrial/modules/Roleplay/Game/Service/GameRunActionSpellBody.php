<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Контекст заклинания в game.runAction.
 */
final class GameRunActionSpellBody
{
    /**
     * Создаёт разбор контекста.
     *
     * @param GameRunActionFields $fields Общие проверки.
     *
     * @return void
     */
    public function __construct(private readonly GameRunActionFields $fields)
    {
    }

    /**
     * Контекст каста.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $context): array
    {
        $this->fields->assertKeys($context, $this->keys($context), 'spell context');

        $expectedSheetVersion = $context['expectedSheetVersion'] ?? null;
        $pathCode = $context['pathCode'] ?? null;
        $parameters = $context['parameters'] ?? null;
        $usedPower = $context['usedPower'] ?? null;
        if (!is_int($expectedSheetVersion) || $expectedSheetVersion < 0) {
            throw $this->invalid();
        }

        if (!is_string($pathCode) || $pathCode === '') {
            throw $this->invalid();
        }

        if (!is_array($parameters) || ($parameters !== [] && array_is_list($parameters))) {
            throw $this->invalid();
        }

        if (!is_array($usedPower) || array_is_list($usedPower)) {
            throw $this->invalid();
        }

        $target = array_key_exists('target', $context) && $context['target'] !== null
            ? $this->fields->side($context['target'])
            : null;
        $expectedTargetVersion = $context['expectedTargetVersion'] ?? null;
        if (($target !== null) !== ($expectedTargetVersion !== null)) {
            throw $this->invalid();
        }

        if ($expectedTargetVersion !== null && (!is_int($expectedTargetVersion) || $expectedTargetVersion < 0)) {
            throw $this->invalid();
        }

        $distanceIpari = $context['distanceIpari'] ?? 0;
        if (!is_int($distanceIpari) || $distanceIpari < 0) {
            throw $this->invalid();
        }

        $chargeSpellId = $context['chargeSpellId'] ?? null;
        if ($chargeSpellId !== null) {
            if (is_int($chargeSpellId) && $chargeSpellId >= 1) {
                $chargeSpellId = (string) $chargeSpellId;
            }

            if (!is_string($chargeSpellId) || preg_match('/^\d+$/', $chargeSpellId) !== 1) {
                throw $this->invalid();
            }
        }

        return [
            'expectedSheetVersion' => $expectedSheetVersion,
            'pathCode' => $pathCode,
            'parameters' => $parameters,
            'usedPower' => $usedPower,
            'target' => $target,
            'expectedTargetVersion' => $expectedTargetVersion,
            'distanceIpari' => $distanceIpari,
            'upgrades' => (new GameSpellCastUpgradeBody())->parse($context['upgrades'] ?? null),
            'chargeSpellId' => $chargeSpellId,
        ];
    }

    /**
     * Контекст фазы resolve, chain, chainStop или abort.
     *
     * @param array<string, mixed> $context Контекст.
     * @param string $phase Фаза.
     *
     * @return array{castId: string, saturationSteps?: int, burstTargets?: mixed, chainTarget?: array{type: string, id: int, distanceIpari: int}} Контекст.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException INVALID_PARAMS.
     */
    public function phase(array $context, string $phase): array
    {
        $allowed = match ($phase) {
            'resolve' => ['castId', 'saturationSteps'],
            'chain' => ['castId', 'chainTarget'],
            default => ['castId'],
        };
        if ($phase === 'resolve' && array_key_exists('burstTargets', $context)) {
            $allowed[] = 'burstTargets';
        }

        $this->fields->assertKeys($context, $allowed, 'spell phase');
        $castId = $context['castId'] ?? null;
        if (!is_string($castId) || $castId === '') {
            throw $this->invalid();
        }

        if ($phase === 'resolve') {
            return [
                'castId' => $castId,
                'saturationSteps' => (new GameSpellCastSaturationSteps())->parse($context['saturationSteps'] ?? null),
                'burstTargets' => $context['burstTargets'] ?? null,
            ];
        }

        if ($phase === 'chain') {
            return [
                'castId' => $castId,
                'chainTarget' => $this->chainTarget($context['chainTarget'] ?? null),
            ];
        }

        return ['castId' => $castId];
    }

    /**
     * Цель прыжка цепи.
     *
     * @param mixed $value Значение.
     *
     * @return array{type: string, id: int, distanceIpari: int} Цель.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function chainTarget(mixed $value): array
    {
        if (!is_array($value)) {
            throw $this->invalid();
        }

        $this->fields->assertKeys($value, ['type', 'id', 'distanceIpari'], 'chain target');
        $type = $value['type'] ?? null;
        $id = $value['id'] ?? null;
        $distance = $value['distanceIpari'] ?? null;
        if (
            !in_array($type, ['character', 'npc'], true)
            || !is_int($id)
            || $id < 1
            || !is_int($distance)
            || $distance < 0
        ) {
            throw $this->invalid();
        }

        return ['type' => $type, 'id' => $id, 'distanceIpari' => $distance];
    }

    /**
     * Разрешённые ключи контекста.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return list<string> Ключи.
     */
    private function keys(array $context): array
    {
        $allowed = ['expectedSheetVersion', 'pathCode', 'parameters', 'usedPower'];
        foreach (['target', 'expectedTargetVersion', 'distanceIpari', 'upgrades', 'chargeSpellId'] as $key) {
            if (array_key_exists($key, $context)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Ошибка формы.
     *
     * @return ActionException Исключение.
     */
    private function invalid(): ActionException
    {
        return new ActionException('INVALID_PARAMS', 'Invalid parameter: spell context');
    }
}
