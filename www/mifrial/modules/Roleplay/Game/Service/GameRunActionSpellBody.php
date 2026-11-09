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

        return [
            'expectedSheetVersion' => $expectedSheetVersion,
            'pathCode' => $pathCode,
            'parameters' => $parameters,
            'usedPower' => $usedPower,
            'target' => $target,
            'expectedTargetVersion' => $expectedTargetVersion,
            'distanceIpari' => $distanceIpari,
            'upgrades' => (new GameSpellCastUpgradeBody())->parse($context['upgrades'] ?? null),
        ];
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
        foreach (['target', 'expectedTargetVersion', 'distanceIpari', 'upgrades'] as $key) {
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
