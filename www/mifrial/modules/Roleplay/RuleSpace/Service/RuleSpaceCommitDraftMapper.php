<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Разбор JSON правил черновика в put.
 */
final class RuleSpaceCommitDraftMapper
{
    /**
     * Список put из JSON.
     *
     * @param mixed $rules Тело rules.
     *
     * @return array<int, RuleCommitEntry> Puts.
     *
     * @throws RuleSpaceInvalidException Если форма или тело.
     */
    public function mapPuts(mixed $rules): array
    {
        if (!is_array($rules) || !array_is_list($rules)) {
            throw new RuleSpaceInvalidException('Commit rules must be a list');
        }

        $entries = [];
        foreach ($rules as $ruleRow) {
            $entries[] = $this->mapPut($ruleRow);
        }

        return $entries;
    }

    /**
     * Коды tombstone.
     *
     * @param mixed $removedCodes JSON.
     *
     * @return array<int, string> Коды.
     *
     * @throws RuleSpaceInvalidException Если не list строк.
     */
    public function mapRemovedCodes(mixed $removedCodes): array
    {
        if (!is_array($removedCodes) || !array_is_list($removedCodes)) {
            throw new RuleSpaceInvalidException('Removed codes must be a list');
        }

        $codes = [];
        foreach ($removedCodes as $removedCode) {
            if (!is_string($removedCode)) {
                throw new RuleSpaceInvalidException('Removed codes must be a list');
            }

            $codes[] = $removedCode;
        }

        return $codes;
    }

    /**
     * Один put.
     *
     * @param mixed $ruleRow Объект правила.
     *
     * @return RuleCommitEntry Put.
     *
     * @throws RuleSpaceInvalidException Если поля.
     */
    private function mapPut(mixed $ruleRow): RuleCommitEntry
    {
        if (!is_array($ruleRow)) {
            throw new RuleSpaceInvalidException('Commit rule must be an object');
        }

        $code = $ruleRow['code'] ?? null;
        if (!is_string($code)) {
            throw new RuleSpaceInvalidException('Commit rule code is invalid');
        }

        try {
            return RuleCommitEntry::put(
                $code,
                $this->mapBody($ruleRow, $code),
                $this->optionalBool($ruleRow, 'active', true, $code),
            );
        } catch (RuleInvalidException $exception) {
            throw new RuleSpaceInvalidException(
                sprintf('Commit rule "%s" is invalid: %s', $code, $exception->getMessage()),
                $exception,
            );
        }
    }

    /**
     * Тело снимка.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $ruleCode Код правила.
     *
     * @return RuleVersionBody Тело.
     *
     * @throws RuleSpaceInvalidException Если поля.
     * @throws RuleInvalidException Если инвариант тела.
     */
    private function mapBody(array $ruleRow, string $ruleCode): RuleVersionBody
    {
        $type = $ruleRow['type'] ?? null;
        $name = $ruleRow['name'] ?? null;
        if (!is_string($type) || !is_string($name)) {
            throw new RuleSpaceInvalidException(
                sprintf('Commit rule "%s" type and name are required', $ruleCode),
            );
        }

        return new RuleVersionBody(
            $type,
            $name,
            $this->optionalString($ruleRow, 'description', '', $ruleCode),
            $this->optionalArray($ruleRow, 'spec', $ruleCode),
            $this->optionalIntList($ruleRow, 'keywordIds', $ruleCode),
            $this->optionalMechanicId($ruleRow, $ruleCode),
            $this->optionalArray($ruleRow, 'mechanicPayload', $ruleCode),
            $this->optionalString($ruleRow, 'contentStatus', 'needs_work', $ruleCode),
            $this->optionalString($ruleRow, 'contentNote', '', $ruleCode),
        );
    }

    /**
     * Строка или default.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     * @param string $defaultValue Нет ключа.
     * @param string $ruleCode Код правила.
     *
     * @return string Значение.
     *
     * @throws RuleSpaceInvalidException Если не строка.
     */
    private function optionalString(
        array $ruleRow,
        string $fieldName,
        string $defaultValue,
        string $ruleCode,
    ): string {
        if (!array_key_exists($fieldName, $ruleRow) || $ruleRow[$fieldName] === null) {
            return $defaultValue;
        }

        $value = $ruleRow[$fieldName];
        if (!is_string($value)) {
            $this->rejectField($ruleCode, $fieldName);
        }

        return $value;
    }

    /**
     * Массив или [].
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     * @param string $ruleCode Код правила.
     *
     * @return array<string|int, mixed> JSON.
     *
     * @throws RuleSpaceInvalidException Если не массив и не null.
     */
    private function optionalArray(array $ruleRow, string $fieldName, string $ruleCode): array
    {
        if (!array_key_exists($fieldName, $ruleRow) || $ruleRow[$fieldName] === null) {
            return [];
        }

        $value = $ruleRow[$fieldName];
        if (!is_array($value)) {
            $this->rejectField($ruleCode, $fieldName);
        }

        return $value;
    }

    /**
     * List int или [].
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     * @param string $ruleCode Код правила.
     *
     * @return array<int, int> Id.
     *
     * @throws RuleSpaceInvalidException Если форма.
     */
    private function optionalIntList(array $ruleRow, string $fieldName, string $ruleCode): array
    {
        if (!array_key_exists($fieldName, $ruleRow) || $ruleRow[$fieldName] === null) {
            return [];
        }

        $value = $ruleRow[$fieldName];
        if (!is_array($value) || !array_is_list($value)) {
            $this->rejectField($ruleCode, $fieldName);
        }

        $keywordIds = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                $this->rejectField($ruleCode, $fieldName);
            }

            $keywordIds[] = $item;
        }

        return $keywordIds;
    }

    /**
     * Читает необязательный mechanicId.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $ruleCode Код правила.
     *
     * @return int|null Id.
     *
     * @throws RuleSpaceInvalidException Если не int|null.
     */
    private function optionalMechanicId(array $ruleRow, string $ruleCode): ?int
    {
        if (!array_key_exists('mechanicId', $ruleRow) || $ruleRow['mechanicId'] === null) {
            return null;
        }

        $value = $ruleRow['mechanicId'];
        if (!is_int($value)) {
            $this->rejectField($ruleCode, 'mechanicId');
        }

        return $value;
    }

    /**
     * Bool или default.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     * @param bool $defaultValue Нет ключа.
     * @param string $ruleCode Код правила.
     *
     * @return bool Значение.
     *
     * @throws RuleSpaceInvalidException Если не bool.
     */
    private function optionalBool(array $ruleRow, string $fieldName, bool $defaultValue, string $ruleCode): bool
    {
        if (!array_key_exists($fieldName, $ruleRow) || $ruleRow[$fieldName] === null) {
            return $defaultValue;
        }

        $value = $ruleRow[$fieldName];
        if (!is_bool($value)) {
            $this->rejectField($ruleCode, $fieldName);
        }

        return $value;
    }

    /**
     * Бросает INVALID с кодом правила и именем поля.
     *
     * @param string $ruleCode Код.
     * @param string $fieldName Поле JSON.
     *
     * @return never
     *
     * @throws RuleSpaceInvalidException Всегда.
     */
    private function rejectField(string $ruleCode, string $fieldName): never
    {
        throw new RuleSpaceInvalidException(
            sprintf('Commit rule "%s" field %s is invalid', $ruleCode, $fieldName),
        );
    }
}
