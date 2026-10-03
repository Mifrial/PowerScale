<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Read;

use Mifrial\Roleplay\Character\Enum\CharacterSheetSection;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Список кодов секций: строгая нормализация записи и тихий отбор при чтении.
 */
final class CharacterSectionCodes
{
    /**
     * List, trim, без дублей, только enum, затем sort.
     *
     * @param mixed $visibilityFields Вход.
     *
     * @return list<string> Коды.
     *
     * @throws CharacterInvalidException Если форма, дубль или чужой код.
     */
    public function normalize(mixed $visibilityFields): array
    {
        $tokens = $this->tokens($visibilityFields);
        $codes = $this->uniqueCodes($tokens);
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * Оставляет известные коды. Чужой токен не даёт грант.
     *
     * @param array<mixed> $codes Сырые коды строки.
     *
     * @return list<string> Enum.
     */
    public function keepKnown(array $codes): array
    {
        $known = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }

            $token = trim($code);
            if (CharacterSheetSection::tryFrom($token) === null || isset($known[$token])) {
                continue;
            }

            $known[$token] = $token;
        }

        $normalized = array_values($known);
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * Проверяет, что вход — list.
     *
     * @param mixed $visibilityFields Вход.
     *
     * @return list<mixed> Элементы.
     *
     * @throws CharacterInvalidException Если не list.
     */
    private function tokens(mixed $visibilityFields): array
    {
        if (!is_array($visibilityFields) || !array_is_list($visibilityFields)) {
            throw new CharacterInvalidException('Character visibility fields must be a list');
        }

        return $visibilityFields;
    }

    /**
     * Trim и запрет дубля или чужого кода.
     *
     * @param list<mixed> $tokens Элементы.
     *
     * @return list<string> Коды без sort.
     *
     * @throws CharacterInvalidException Если токен плохой.
     */
    private function uniqueCodes(array $tokens): array
    {
        $codes = [];
        foreach ($tokens as $token) {
            $code = $this->requireCode($token);
            if (isset($codes[$code])) {
                throw new CharacterInvalidException('Character visibility field is duplicated');
            }

            $codes[$code] = $code;
        }

        return array_values($codes);
    }

    /**
     * Один код enum после trim.
     *
     * @param mixed $token Элемент.
     *
     * @return string Код.
     *
     * @throws CharacterInvalidException Если не строка enum.
     */
    private function requireCode(mixed $token): string
    {
        if (!is_string($token)) {
            throw new CharacterInvalidException('Character visibility field must be a string');
        }

        $code = trim($token);
        if ($code === '' || CharacterSheetSection::tryFrom($code) === null) {
            throw new CharacterInvalidException('Character visibility field is invalid');
        }

        return $code;
    }
}
