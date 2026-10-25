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

    private const KINDS = ['action', 'strike', 'wound'];

    private const OPERATIONS = ['hold', 'release', 'bandage'];

    /**
     * Разбирает действие.
     *
     * @param array<string, mixed> $action Конверт.
     *
     * @return array{
     *     phase: string,
     *     kind: string,
     *     code: string,
     *     actor: array{type: string, id: int}|null,
     *     context: array<string, mixed>
     * } Действие.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $action): array
    {
        $phase = $action['phase'] ?? null;
        $allowed = ['context', 'kind', 'phase'];
        if ($phase === 'declare') {
            $allowed[] = 'actor';
            $allowed[] = 'code';
        }

        $this->assertKeys($action, $allowed);
        $kind = $action['kind'] ?? null;
        $code = $action['code'] ?? null;
        $context = $action['context'] ?? null;
        if (
            !is_string($phase) || !in_array($phase, self::PHASES, true)
            || !is_string($kind) || !in_array($kind, self::KINDS, true)
            || !is_array($context)
            || ($phase === 'declare' && (!is_string($code) || $code === ''))
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action');
        }

        $actor = array_key_exists('actor', $action) ? $this->side($action['actor']) : null;
        if ($phase === 'respond' && $actor !== null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action actor');
        }

        if ($phase === 'respond' && $kind !== 'strike') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action respond');
        }

        return [
            'phase' => $phase,
            'kind' => $kind,
            'code' => $code,
            'actor' => $actor,
            'context' => $this->context($kind, $phase, $context),
        ];
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
        if ($kind === 'action') {
            return $this->actionContext($context);
        }

        if ($kind === 'wound') {
            return $this->woundContext($context);
        }

        return $phase === 'declare' ? $this->strikeContext($context) : $this->defenseContext($context);
    }

    /**
     * Контекст карточки действия.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function actionContext(array $context): array
    {
        $allowed = ['expectedSheetVersion', 'followUpTargetKey'];
        if (array_key_exists('chosenAmounts', $context)) {
            $allowed[] = 'chosenAmounts';
        }

        $this->assertKeys($context, $allowed, 'action context');
        $expected = $context['expectedSheetVersion'] ?? null;
        $followUp = $context['followUpTargetKey'] ?? null;
        $chosen = $context['chosenAmounts'] ?? [];
        if (
            !is_int($expected)
            || ($followUp !== null && !is_string($followUp))
            || !is_array($chosen)
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: action context');
        }

        return [
            'expectedSheetVersion' => $expected,
            'followUpTargetKey' => $followUp,
            'chosenAmounts' => $chosen,
        ];
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
    private function woundContext(array $context): array
    {
        $this->assertKeys(
            $context,
            ['expectedSheetVersion', 'operation', 'stateId', 'target'],
            'wound context',
        );
        $operation = $context['operation'] ?? null;
        $stateId = $context['stateId'] ?? null;
        $expected = $context['expectedSheetVersion'] ?? null;
        $target = $this->pair($context['target'] ?? null);
        if (
            !is_string($operation) || !in_array($operation, self::OPERATIONS, true)
            || !is_int($stateId) || $stateId < 1
            || !is_int($expected)
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: wound context');
        }

        return [
            'operation' => $operation,
            'stateId' => $stateId,
            'expectedSheetVersion' => $expected,
            'target' => $target,
        ];
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
    private function strikeContext(array $context): array
    {
        $allowed = ['strikes'];
        if (array_key_exists('chosenAmounts', $context)) {
            $allowed[] = 'chosenAmounts';
        }

        $this->assertKeys($context, $allowed, 'strike context');
        $rows = $context['strikes'] ?? null;
        $chosen = $context['chosenAmounts'] ?? [];
        if (!is_array($rows) || $rows === [] || !array_is_list($rows) || !is_array($chosen)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike context');
        }

        $strikes = [];
        foreach ($rows as $row) {
            $strikes[] = $this->strikeRow($row);
        }

        return ['strikes' => $strikes, 'chosenAmounts' => $chosen];
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
    private function strikeRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        $allowed = [
            'itemInventoryId',
            'itemRuleCode',
            'profileIndex',
            'profileType',
            'target',
        ];
        foreach (['ammunitionInventoryId', 'instanceIndex'] as $key) {
            if (array_key_exists($key, $row)) {
                $allowed[] = $key;
            }
        }

        $this->assertKeys($row, $allowed, 'strike row');
        $itemInventoryId = $row['itemInventoryId'] ?? null;
        $itemRuleCode = $row['itemRuleCode'] ?? null;
        $profileType = $row['profileType'] ?? null;
        $profileIndex = $row['profileIndex'] ?? null;
        $ammunitionInventoryId = $row['ammunitionInventoryId'] ?? null;
        $instanceIndex = $row['instanceIndex'] ?? 0;
        $target = $this->side($row['target'] ?? null);
        if (
            !is_int($itemInventoryId)
            || !is_string($itemRuleCode) || $itemRuleCode === ''
            || !is_string($profileType) || $profileType === ''
            || !is_int($profileIndex)
            || !is_int($instanceIndex) || $instanceIndex < 0
            || !is_int($ammunitionInventoryId) && $ammunitionInventoryId !== null
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike row');
        }

        return [
            'itemInventoryId' => $itemInventoryId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
            'instanceIndex' => $instanceIndex,
            'target' => $target,
            'ammunitionInventoryId' => $ammunitionInventoryId,
        ];
    }

    /**
     * Контекст защиты.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function defenseContext(array $context): array
    {
        $this->assertKeys($context, ['defenses'], 'defense context');
        $rows = $context['defenses'] ?? null;
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense context');
        }

        $defenses = [];
        $seen = [];
        foreach ($rows as $row) {
            $defense = $this->defenseRow($row);
            $target = $defense['target'];
            $key = $target === null ? '' : $target['type'] . ':' . $target['id'];
            if ($key !== '' && isset($seen[$key])) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense target');
            }

            if ($key !== '') {
                $seen[$key] = true;
            }

            $defenses[] = $defense;
        }

        return ['defenses' => $defenses];
    }

    /**
     * Строка защиты.
     *
     * @param mixed $row Элемент.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function defenseRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        $allowed = ['expectedSheetVersion', 'reaction'];
        if (array_key_exists('target', $row)) {
            $allowed[] = 'target';
        }

        if (
            array_key_exists('blockItemInventoryId', $row)
            || array_key_exists('blockItemProfileIndex', $row)
        ) {
            $allowed[] = 'blockItemInventoryId';
            $allowed[] = 'blockItemProfileIndex';
            if (array_key_exists('blockItemRuleCode', $row)) {
                $allowed[] = 'blockItemRuleCode';
            }
        } elseif (array_key_exists('blockItemRuleCode', $row)) {
            $allowed[] = 'blockItemRuleCode';
        }

        $this->assertKeys($row, $allowed, 'defense row');
        $reaction = $row['reaction'] ?? null;
        $expected = $row['expectedSheetVersion'] ?? null;
        $inventoryId = $row['blockItemInventoryId'] ?? null;
        $profileIndex = $row['blockItemProfileIndex'] ?? null;
        $ruleCode = $row['blockItemRuleCode'] ?? null;
        $target = array_key_exists('target', $row) ? $this->side($row['target']) : null;
        $block = ($inventoryId === null || is_int($inventoryId))
            && ($profileIndex === null || is_int($profileIndex))
            && ($ruleCode === null || is_string($ruleCode));
        if (
            !is_string($reaction) || $reaction === ''
            || !is_int($expected)
            || !$block
            || ($reaction === 'block' && (!is_int($inventoryId) || !is_int($profileIndex)))
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        return [
            'target' => $target,
            'reaction' => $reaction,
            'blockItemInventoryId' => $inventoryId,
            'blockItemProfileIndex' => $profileIndex,
            'blockItemRuleCode' => $ruleCode,
            'expectedSheetVersion' => $expected,
        ];
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
    private function side(mixed $side): array
    {
        if (!is_array($side)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        $keys = array_keys($side);
        sort($keys);
        $type = $side['type'] ?? null;
        $id = $side['id'] ?? null;
        if ($keys !== ['id', 'type'] || !is_string($type) || $type === '' || !is_int($id) || $id < 1) {
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
    private function pair(mixed $pair): array
    {
        if (!is_array($pair)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: pair');
        }

        $keys = array_keys($pair);
        sort($keys);
        $kind = $pair['kind'] ?? null;
        $id = $pair['id'] ?? null;
        if (
            $keys !== ['id', 'kind']
            || ($kind !== 'character' && $kind !== 'npc')
            || !is_int($id) || $id < 1
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: pair');
        }

        return ['kind' => $kind, 'id' => $id];
    }

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
    private function assertKeys(array $body, array $allowed, string $subject = 'action'): void
    {
        $keys = array_keys($body);
        sort($keys);
        sort($allowed);
        if ($keys !== $allowed) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $subject);
        }
    }
}
