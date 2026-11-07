<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбора атаки по нескольким целям.
 */
final class GameStrikeWideAttack
{
    private const KEYS = [
        'actionRuleCode',
        'attacker',
        'itemInventoryId',
        'itemRuleCode',
        'profileIndex',
        'profileType',
        'targets',
    ];

    /**
     * Создаёт разбор широкой атаки.
     *
     * @param GameStrikeBodyFields $fields Проверки формы.
     * @param GameStrikeTargets $targets Цели удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBodyFields $fields,
        private readonly GameStrikeTargets $targets,
    ) {
    }

    /**
     * Выбор атаки по нескольким целям.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS или GAME_INVALID.
     */
    public function parse(array $attack): array
    {
        $allowed = $this->fields->optional(
            $attack,
            self::KEYS,
            ['chosenAmounts', 'ammunitionInventoryId', 'instanceIndex'],
        );
        $this->fields->assertKeys($attack, $allowed, 'wide strike');
        $profileType = $attack['profileType'] ?? null;
        if (is_string($profileType) && ($profileType === 'shoot' || $profileType === 'throw')) {
            throw new ActionException('GAME_INVALID', 'Wide ranged strike is invalid');
        }

        $targetRows = $this->targets->parse($attack['targets'] ?? null);
        $values = $this->values($attack);
        $this->assert($values);

        return $this->choice($attack, $values, $targetRows);
    }

    /**
     * Значения широкой атаки.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Значения.
     */
    private function values(array $attack): array
    {
        return [
            'profileType' => $attack['profileType'] ?? null,
            'profileIndex' => $attack['profileIndex'] ?? null,
            'actionRuleCode' => $attack['actionRuleCode'] ?? null,
            'itemRuleCode' => $attack['itemRuleCode'] ?? null,
            'itemInventoryId' => $attack['itemInventoryId'] ?? null,
            'ammunitionInventoryId' => $attack['ammunitionInventoryId'] ?? null,
            'instanceIndex' => $attack['instanceIndex'] ?? 0,
            'chosenAmounts' => $attack['chosenAmounts'] ?? [],
        ];
    }

    /**
     * Проверяет значения широкой атаки.
     *
     * @param array<string, mixed> $values Значения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assert(array $values): void
    {
        $this->assertStrike($values);
        $this->assertAmmunition($values);
    }

    /**
     * Проверяет оружие и профиль широкого удара.
     *
     * @param array<string, mixed> $values Значения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertStrike(array $values): void
    {
        $itemInventoryId = $values['itemInventoryId'];
        $instanceIndex = $values['instanceIndex'];
        $profileIndex = $values['profileIndex'];
        $codes = is_string($values['actionRuleCode']) && is_string($values['itemRuleCode']);
        if (!is_int($itemInventoryId) || !is_int($instanceIndex) || !is_int($profileIndex) || !$codes) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }

        $profileType = $values['profileType'];
        if ($instanceIndex < 0 || !is_string($profileType) || $profileType !== 'strike') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }
    }

    /**
     * Проверяет суммы и отсутствие боеприпасов.
     *
     * @param array<string, mixed> $values Значения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertAmmunition(array $values): void
    {
        if (!is_array($values['chosenAmounts']) || $values['ammunitionInventoryId'] !== null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }
    }

    /**
     * Собирает выбор широкой атаки.
     *
     * @param array<string, mixed> $attack Тело.
     * @param array<string, mixed> $values Значения.
     * @param array{targets: list<array{type: string, id: int}>, conditions: array<string, array<string, mixed>>} $targetRows Цели.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function choice(array $attack, array $values, array $targetRows): array
    {
        return [
            'attacker' => $this->fields->side($attack['attacker'] ?? null),
            'targets' => $targetRows['targets'],
            'conditions' => $targetRows['conditions'],
            'actionRuleCode' => $values['actionRuleCode'],
            'itemInventoryId' => $values['itemInventoryId'],
            'itemRuleCode' => $values['itemRuleCode'],
            'profileType' => $values['profileType'],
            'profileIndex' => $values['profileIndex'],
            'instanceIndex' => $values['instanceIndex'],
            'chosenAmounts' => $values['chosenAmounts'],
        ];
    }
}
