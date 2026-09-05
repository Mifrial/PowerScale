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
                $this->mapBody($ruleRow),
                $this->optionalBool($ruleRow, 'active', true),
            );
        } catch (RuleInvalidException $exception) {
            throw new RuleSpaceInvalidException('Commit rule is invalid', $exception);
        }
    }

    /**
     * Тело снимка.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     *
     * @return RuleVersionBody Тело.
     *
     * @throws RuleSpaceInvalidException Если поля.
     * @throws RuleInvalidException Если инвариант тела.
     */
    private function mapBody(array $ruleRow): RuleVersionBody
    {
        $type = $ruleRow['type'] ?? null;
        $name = $ruleRow['name'] ?? null;
        if (!is_string($type) || !is_string($name)) {
            throw new RuleSpaceInvalidException('Commit rule type and name are required');
        }

        return new RuleVersionBody(
            $type,
            $name,
            $this->optionalString($ruleRow, 'description', ''),
            $this->optionalArray($ruleRow, 'spec'),
            $this->optionalIntList($ruleRow, 'keywordIds'),
            $this->optionalMechanicId($ruleRow),
            $this->optionalArray($ruleRow, 'mechanicPayload'),
            $this->optionalString($ruleRow, 'contentStatus', 'needs_work'),
        );
    }

    /**
     * Строка или default.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     * @param string $defaultValue Нет ключа.
     *
     * @return string Значение.
     *
     * @throws RuleSpaceInvalidException Если не строка.
     */
    private function optionalString(array $ruleRow, string $fieldName, string $defaultValue): string
    {
        if (!array_key_exists($fieldName, $ruleRow)) {
            return $defaultValue;
        }

        $value = $ruleRow[$fieldName];
        if (!is_string($value)) {
            throw new RuleSpaceInvalidException('Commit rule field is invalid');
        }

        return $value;
    }

    /**
     * Массив или [].
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     *
     * @return array<string|int, mixed> JSON.
     *
     * @throws RuleSpaceInvalidException Если не массив.
     */
    private function optionalArray(array $ruleRow, string $fieldName): array
    {
        if (!array_key_exists($fieldName, $ruleRow)) {
            return [];
        }

        $value = $ruleRow[$fieldName];
        if (!is_array($value)) {
            throw new RuleSpaceInvalidException('Commit rule field is invalid');
        }

        return $value;
    }

    /**
     * List int или [].
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     *
     * @return array<int, int> Id.
     *
     * @throws RuleSpaceInvalidException Если форма.
     */
    private function optionalIntList(array $ruleRow, string $fieldName): array
    {
        if (!array_key_exists($fieldName, $ruleRow)) {
            return [];
        }

        $value = $ruleRow[$fieldName];
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuleSpaceInvalidException('Commit rule field is invalid');
        }

        $keywordIds = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new RuleSpaceInvalidException('Commit rule field is invalid');
            }

            $keywordIds[] = $item;
        }

        return $keywordIds;
    }

    /**
     * Читает необязательный mechanicId.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     *
     * @return int|null Id.
     *
     * @throws RuleSpaceInvalidException Если не int|null.
     */
    private function optionalMechanicId(array $ruleRow): ?int
    {
        if (!array_key_exists('mechanicId', $ruleRow) || $ruleRow['mechanicId'] === null) {
            return null;
        }

        $value = $ruleRow['mechanicId'];
        if (!is_int($value)) {
            throw new RuleSpaceInvalidException('Commit rule field is invalid');
        }

        return $value;
    }

    /**
     * Bool или default.
     *
     * @param array<string|int, mixed> $ruleRow JSON.
     * @param string $fieldName Ключ.
     * @param bool $defaultValue Нет ключа.
     *
     * @return bool Значение.
     *
     * @throws RuleSpaceInvalidException Если не bool.
     */
    private function optionalBool(array $ruleRow, string $fieldName, bool $defaultValue): bool
    {
        if (!array_key_exists($fieldName, $ruleRow)) {
            return $defaultValue;
        }

        $value = $ruleRow[$fieldName];
        if (!is_bool($value)) {
            throw new RuleSpaceInvalidException('Commit rule field is invalid');
        }

        return $value;
    }
}
