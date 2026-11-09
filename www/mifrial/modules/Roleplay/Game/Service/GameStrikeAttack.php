<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбора одиночной атаки.
 */
final class GameStrikeAttack
{
    private const KEYS = [
        'actionRuleCode',
        'attacker',
        'defender',
        'itemInventoryId',
        'itemRuleCode',
        'profileIndex',
        'profileType',
    ];

    private const OPTIONAL = [
        'conditions',
        'chosenAmounts',
        'ammunitionInventoryId',
        'instanceIndex',
        'modifiers',
        'processRuleCode',
        'processStepCode',
        'tokenSpend',
        'maneuverRuleCode',
        'spellCast',
    ];

    /**
     * Создаёт разбор атаки.
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
     * Выбор атаки.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $attack): array
    {
        $allowed = $this->fields->optional($attack, self::KEYS, self::OPTIONAL);
        $this->fields->assertKeys($attack, $allowed);
        $attacker = $this->fields->side($attack['attacker'] ?? null);
        $defender = $this->fields->side($attack['defender'] ?? null);
        $conditions = $this->conditions($attack['conditions'] ?? null, $attack['profileType'] ?? null);
        $values = $this->values($attack);
        $this->assert($values);

        return $this->choice($attack, $values, $attacker, $defender, $conditions);
    }

    /**
     * Условия удара с признаком дистанции.
     *
     * @param mixed $raw Сырые условия.
     * @param mixed $profileType Профиль.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function conditions(mixed $raw, mixed $profileType): array
    {
        $conditions = $this->conditions->parse(
            $raw,
            $profileType === 'shoot' || $profileType === 'throw',
        );
        if ($conditions['turn'] === true) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack conditions');
        }

        return $conditions;
    }

    /**
     * Значения атаки.
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
            'processRuleCode' => $attack['processRuleCode'] ?? null,
            'processStepCode' => $attack['processStepCode'] ?? null,
            'spellCast' => (new GameSpellTouchBody())->parse($attack['spellCast'] ?? null),
        ];
    }

    /**
     * Проверяет значения атаки.
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
        $this->assertProcess($values);
        $this->assertAmmunition($values);
    }

    /**
     * Проверяет оружие и профиль.
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

        if ($instanceIndex < 0 || !is_string($values['profileType']) || !is_array($values['chosenAmounts'])) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }
    }

    /**
     * Проверяет коды шага процесса.
     *
     * @param array<string, mixed> $values Значения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertProcess(array $values): void
    {
        $processRuleCode = $values['processRuleCode'];
        $processStepCode = $values['processStepCode'];
        if ($this->badCode($processRuleCode) || $this->badCode($processStepCode)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }

        if ($processRuleCode === null && $processStepCode !== null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }
    }

    /**
     * Проверяет боеприпасы стрельбы.
     *
     * @param array<string, mixed> $values Значения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertAmmunition(array $values): void
    {
        $ok = $values['profileType'] === 'shoot'
            ? is_int($values['ammunitionInventoryId'])
            : $values['ammunitionInventoryId'] === null;
        if (!$ok) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }
    }

    /**
     * Пустой необязательный код.
     *
     * @param mixed $code Код.
     *
     * @return bool Признак.
     */
    private function badCode(mixed $code): bool
    {
        return $code !== null && (!is_string($code) || $code === '');
    }

    /**
     * Собирает выбор атаки.
     *
     * @param array<string, mixed> $attack Тело.
     * @param array<string, mixed> $values Значения.
     * @param array{type: string, id: int} $attacker Атакующий.
     * @param array{type: string, id: int} $defender Защитник.
     * @param array<string, mixed> $conditions Условия.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function choice(
        array $attack,
        array $values,
        array $attacker,
        array $defender,
        array $conditions,
    ): array {
        return [
            'attacker' => $attacker,
            'defender' => $defender,
            'actionRuleCode' => $values['actionRuleCode'],
            'itemInventoryId' => $values['itemInventoryId'],
            'itemRuleCode' => $values['itemRuleCode'],
            'profileType' => $values['profileType'],
            'profileIndex' => $values['profileIndex'],
            ...($values['profileType'] === 'shoot' ? ['ammunitionInventoryId' => $values['ammunitionInventoryId']] : []),
            'instanceIndex' => $values['instanceIndex'],
            'conditions' => $conditions,
            'chosenAmounts' => $values['chosenAmounts'],
            'modifiers' => $this->modifiers($attack['modifiers'] ?? null),
            'tokenSpend' => $attack['tokenSpend'] ?? null,
            'maneuverRuleCode' => is_string($attack['maneuverRuleCode'] ?? null)
                ? $attack['maneuverRuleCode']
                : null,
            'processRuleCode' => $values['processRuleCode'],
            'processStepCode' => $values['processStepCode'],
            ...($values['spellCast'] !== null ? ['spellCast' => $values['spellCast']] : []),
        ];
    }

    /**
     * Коды модификаторов действия.
     *
     * @param mixed $value Значение.
     *
     * @return list<string> Коды.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function modifiers(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action modifiers');
        }

        $codes = [];
        foreach ($value as $code) {
            if (!is_string($code) || $code === '' || in_array($code, $codes, true)) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action modifiers');
            }

            $codes[] = $code;
        }

        return $codes;
    }
}
