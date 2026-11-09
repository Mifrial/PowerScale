<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Контекст объявления удара.
 */
final class GameRunActionStrikeBody
{
    /**
     * Создаёт разбор удара.
     *
     * @param GameRunActionFields $fields Общие проверки.
     *
     * @return void
     */
    public function __construct(private readonly GameRunActionFields $fields)
    {
    }

    /**
     * Контекст удара.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $context): array
    {
        $this->fields->assertKeys($context, $this->keys($context), 'strike context');
        $token = (new GameRunActionTokenSpend())->parse($context['tokenSpend'] ?? null);
        $maneuver = $context['maneuverRuleCode'] ?? null;
        if ($maneuver !== null && (!is_string($maneuver) || $maneuver === '')) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike context');
        }

        $process = $this->fields->process($context);
        $rows = $this->rows($context['strikes'] ?? null);
        $chosen = $this->chosen($context['chosenAmounts'] ?? []);
        $spellCast = (new GameSpellTouchBody())->parse($context['spellCast'] ?? null);
        $strikes = [];
        foreach ($rows as $row) {
            $strikes[] = $this->row($row);
        }

        return [
            'strikes' => $strikes,
            'chosenAmounts' => $chosen,
            'tokenSpend' => $token,
            'maneuverRuleCode' => $maneuver,
            'processRuleCode' => $process['processRuleCode'],
            'processStepCode' => $process['processStepCode'],
            'spellCast' => $spellCast,
        ];
    }

    /**
     * Список строк удара.
     *
     * @param mixed $rows Значение.
     *
     * @return list<mixed> Строки.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike context');
        }

        return $rows;
    }

    /**
     * Выбранные суммы.
     *
     * @param mixed $chosen Значение.
     *
     * @return array<string, mixed> Суммы.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function chosen(mixed $chosen): array
    {
        if (!is_array($chosen)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike context');
        }

        return $chosen;
    }

    /**
     * Ключи контекста удара.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return list<string> Ключи.
     */
    private function keys(array $context): array
    {
        $allowed = ['strikes'];
        foreach (
            ['chosenAmounts', 'processRuleCode', 'processStepCode', 'tokenSpend', 'maneuverRuleCode', 'spellCast']
            as $key
        ) {
            if (array_key_exists($key, $context)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Строка удара.
     *
     * @param mixed $row Элемент.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function row(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        $this->fields->assertKeys($row, $this->rowKeys($row), 'strike row');
        $conditions = $this->conditions($row);
        $target = $this->fields->side($row['target'] ?? null);
        $values = $this->rowValues($row);

        return $values + [
            'target' => $target,
            'conditions' => $conditions,
            'modifiers' => $this->modifiers($row['modifiers'] ?? null),
        ];
    }

    /**
     * Условия строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function conditions(array $row): array
    {
        $profileType = $row['profileType'] ?? null;
        $conditions = (new GameStrikeConditions())->parse(
            $row['conditions'] ?? null,
            $profileType === 'shoot' || $profileType === 'throw',
        );
        if ($conditions['turn'] === true) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        return $conditions;
    }

    /**
     * Проверенные поля строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Поля.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function rowValues(array $row): array
    {
        $values = [
            'itemInventoryId' => $row['itemInventoryId'] ?? null,
            'itemRuleCode' => $row['itemRuleCode'] ?? null,
            'profileType' => $row['profileType'] ?? null,
            'profileIndex' => $row['profileIndex'] ?? null,
            'instanceIndex' => $row['instanceIndex'] ?? 0,
            'ammunitionInventoryId' => $row['ammunitionInventoryId'] ?? null,
        ];
        $this->assertRowTypes(
            $values['itemInventoryId'],
            $values['itemRuleCode'],
            $values['profileType'],
            $values['profileIndex'],
            $values['instanceIndex'],
            $values['ammunitionInventoryId'],
        );

        return $values;
    }

    /**
     * Ключи строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return list<string> Ключи.
     */
    private function rowKeys(array $row): array
    {
        $allowed = ['itemInventoryId', 'itemRuleCode', 'profileIndex', 'profileType', 'target'];
        foreach (['modifiers', 'ammunitionInventoryId', 'conditions', 'instanceIndex'] as $key) {
            if (array_key_exists($key, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Проверяет типы полей строки удара.
     *
     * @param mixed $itemInventoryId Строка предмета.
     * @param mixed $itemRuleCode Код предмета.
     * @param mixed $profileType Вид профиля.
     * @param mixed $profileIndex Индекс профиля.
     * @param mixed $instanceIndex Индекс экземпляра.
     * @param mixed $ammunitionInventoryId Строка боеприпаса.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertRowTypes(
        mixed $itemInventoryId,
        mixed $itemRuleCode,
        mixed $profileType,
        mixed $profileIndex,
        mixed $instanceIndex,
        mixed $ammunitionInventoryId,
    ): void {
        $this->assertItem($itemInventoryId, $itemRuleCode, $profileType, $profileIndex);
        if (!is_int($instanceIndex) || $instanceIndex < 0) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        if (!is_int($ammunitionInventoryId) && $ammunitionInventoryId !== null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }
    }

    /**
     * Проверяет предмет и профиль строки.
     *
     * @param mixed $itemInventoryId Строка предмета.
     * @param mixed $itemRuleCode Код предмета.
     * @param mixed $profileType Вид профиля.
     * @param mixed $profileIndex Индекс профиля.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertItem(
        mixed $itemInventoryId,
        mixed $itemRuleCode,
        mixed $profileType,
        mixed $profileIndex,
    ): void {
        if (!is_int($itemInventoryId)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        if (!is_string($itemRuleCode) || $itemRuleCode === '') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        if (!is_string($profileType) || $profileType === '') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        if (!is_int($profileIndex)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }
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
