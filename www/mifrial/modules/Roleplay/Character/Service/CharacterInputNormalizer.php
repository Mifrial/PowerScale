<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Trim имени, JSON и гранты секций.
 */
final class CharacterInputNormalizer
{
    /**
     * Имя после trim.
     *
     * @param string $name Вход.
     *
     * @return string Непустое имя.
     *
     * @throws CharacterInvalidException Если пусто.
     */
    public function normalizeName(string $name): string
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new CharacterInvalidException('Character name must not be empty');
        }

        return $trimmedName;
    }

    /**
     * choices или sheet: любой PHP array.
     *
     * @param mixed $payload JSON.
     *
     * @return array Массив.
     *
     * @throws CharacterInvalidException Если не array.
     */
    public function normalizePayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new CharacterInvalidException('Character payload must be an array');
        }

        return $payload;
    }

    /**
     * Список кодов секций: list, trim, без дублей, затем sort.
     *
     * @param mixed $visibilityFields Вход.
     *
     * @return list<string> Канон.
     *
     * @throws CharacterInvalidException Если форма неверна.
     */
    public function normalizeVisibilityFields(mixed $visibilityFields): array
    {
        if (!is_array($visibilityFields) || !array_is_list($visibilityFields)) {
            throw new CharacterInvalidException('Character visibility fields must be a list');
        }

        $normalizedFields = $this->uniqueTrimmedCodes($visibilityFields);
        sort($normalizedFields, SORT_STRING);

        return $normalizedFields;
    }

    /**
     * Целое не меньше 1.
     *
     * @param int $value Число.
     * @param string $message Текст ошибки.
     *
     * @return int То же значение.
     *
     * @throws CharacterInvalidException Если меньше 1.
     */
    public function requirePositiveInt(int $value, string $message): int
    {
        if ($value < 1) {
            throw new CharacterInvalidException($message);
        }

        return $value;
    }

    /**
     * Непустой список секций — публичный грант.
     *
     * @param array $normalizedVisibilityFields Уже канон.
     *
     * @return bool true, если грант всем непуст.
     */
    public function isPublic(array $normalizedVisibilityFields): bool
    {
        return $normalizedVisibilityFields !== [];
    }

    /**
     * Trim кодов без дублей.
     *
     * @param array $visibilityFields List.
     *
     * @return list<string> Коды.
     *
     * @throws CharacterInvalidException Если элемент не строка или дубль.
     */
    private function uniqueTrimmedCodes(array $visibilityFields): array
    {
        $normalizedFields = [];
        $seenCodes = [];
        foreach ($visibilityFields as $visibilityField) {
            $trimmedCode = $this->visibilityCode($visibilityField);
            if (isset($seenCodes[$trimmedCode])) {
                throw new CharacterInvalidException('Character visibility field is duplicated');
            }

            $seenCodes[$trimmedCode] = true;
            $normalizedFields[] = $trimmedCode;
        }

        return $normalizedFields;
    }

    /**
     * Один код секции.
     *
     * @param mixed $visibilityField Элемент.
     *
     * @return string Trim.
     *
     * @throws CharacterInvalidException Если не непустая строка.
     */
    private function visibilityCode(mixed $visibilityField): string
    {
        if (!is_string($visibilityField)) {
            throw new CharacterInvalidException('Character visibility field must be a string');
        }

        $trimmedCode = trim($visibilityField);
        if ($trimmedCode === '') {
            throw new CharacterInvalidException('Character visibility field must not be empty');
        }

        return $trimmedCode;
    }
}
