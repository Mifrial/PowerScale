<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Разбор тела команды действия и реакции.
 */
final class GameActionInput
{
    /**
     * Разбирает тело команды.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    public function input(array $body): array
    {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedVersion'] ?? null;
        $expectedSheet = $body['expectedSheetVersion'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $targetKey = $body['followUpTargetKey'] ?? null;
        $chosenAmounts = $this->chosenAmounts($body['chosenAmounts'] ?? []);
        $actor = $this->pair($body['actor'] ?? null);
        $processRuleCode = $body['processRuleCode'] ?? null;
        $processStepCode = $body['processStepCode'] ?? null;
        if (!is_int($battleId) || !is_int($expectedBattle) || !is_int($expectedSheet)) {
            throw new GameInvalidException('Game action is invalid');
        }

        $this->assertProcess($actionRuleCode, $targetKey, $processRuleCode, $processStepCode);

        return [
            'battleId' => $battleId,
            'expectedBattle' => $expectedBattle,
            'expectedSheet' => $expectedSheet,
            'actionRuleCode' => $actionRuleCode,
            'targetKey' => $targetKey,
            'chosenAmounts' => $chosenAmounts,
            'actor' => $actor,
            'processRuleCode' => $processRuleCode,
            'processStepCode' => $processStepCode,
        ];
    }

    /**
     * Разбирает тело реакции.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    public function reactionInput(array $body): array
    {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedVersion'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $context = $body['context'] ?? null;
        if (!is_int($battleId) || !is_int($expectedBattle) || !is_array($context)) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        if (!is_string($actionRuleCode) || $actionRuleCode === '') {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        if (!is_int($context['expectedSheetVersion'] ?? null)) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        return [
            'battleId' => $battleId,
            'expectedBattle' => $expectedBattle,
            'actionRuleCode' => $actionRuleCode,
            'context' => $context,
            'actor' => $this->pair($body['actor'] ?? null),
        ];
    }

    /**
     * Проверяет вид и поля процесса.
     *
     * @param mixed $actionRuleCode Карточка действия.
     * @param mixed $targetKey Ключ цели.
     * @param mixed $processRuleCode Карточка процесса.
     * @param mixed $processStepCode Шаг процесса.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function assertProcess(
        mixed $actionRuleCode,
        mixed $targetKey,
        mixed $processRuleCode,
        mixed $processStepCode,
    ): void {
        if (!is_string($actionRuleCode) || $actionRuleCode === '') {
            throw new GameInvalidException('Game action is invalid');
        }

        if ($targetKey !== null && !is_string($targetKey)) {
            throw new GameInvalidException('Game action is invalid');
        }

        $this->assertCodes($processRuleCode, $processStepCode);
    }

    /**
     * Карточка и шаг процесса согласованы.
     *
     * @param mixed $processRuleCode Карточка процесса.
     * @param mixed $processStepCode Шаг процесса.
     *
     * @return void
     *
     * @throws GameInvalidException Если код чужой.
     */
    private function assertCodes(mixed $processRuleCode, mixed $processStepCode): void
    {
        if (!$this->validCode($processRuleCode) || !$this->validCode($processStepCode)) {
            throw new GameInvalidException('Game action is invalid');
        }

        if ($processRuleCode === null && $processStepCode !== null) {
            throw new GameInvalidException('Game action is invalid');
        }
    }

    /**
     * Код процесса пуст или непустая строка.
     *
     * @param mixed $code Значение.
     *
     * @return bool Истина, если код допустим.
     */
    private function validCode(mixed $code): bool
    {
        return $code === null || (is_string($code) && $code !== '');
    }

    /**
     * Проверяет карту выбранных сумм.
     *
     * @param mixed $value Значение из JSON.
     *
     * @return array<string, int> Суммы по ресурсам.
     *
     * @throws GameInvalidException Если карта чужая.
     */
    private function chosenAmounts(mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new GameInvalidException('Game action chosen amounts are invalid');
        }

        $amounts = [];
        foreach ($value as $code => $amount) {
            if (!$this->validAmount($code, $amount)) {
                throw new GameInvalidException('Game action chosen amounts are invalid');
            }

            $amounts[$code] = $amount;
        }

        return $amounts;
    }

    /**
     * Пара выбранной суммы допустима.
     *
     * @param mixed $code Код ресурса.
     * @param mixed $amount Сумма.
     *
     * @return bool Истина, если запись допустима.
     */
    private function validAmount(mixed $code, mixed $amount): bool
    {
        return is_string($code) && $code !== '' && is_int($amount) && $amount >= 0;
    }

    /**
     * Пара type/id из JSON.
     *
     * @param mixed $pair Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function pair(mixed $pair): array
    {
        $type = is_array($pair) ? ($pair['type'] ?? null) : null;
        $id = is_array($pair) ? ($pair['id'] ?? null) : null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game action actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }
}
