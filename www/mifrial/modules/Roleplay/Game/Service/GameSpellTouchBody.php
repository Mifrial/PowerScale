<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор блока spellCast в контексте удара касания.
 */
final class GameSpellTouchBody
{
    /**
     * Нормализует заклинание касания.
     *
     * @param mixed $raw Значение из контекста удара.
     *
     * @return array{
     *     spellCode: string,
     *     pathCode: string,
     *     parameters: array<string, int|array{base: int, size: int}>,
     *     usedPower: array{source: array<string, mixed>, value: array{base: int, size: int}},
     *     spellTarget: array{type: string, id: int}|null
     * }|null Блок или null.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        if (!is_array($raw) || array_is_list($raw)) {
            throw $this->invalid();
        }

        $allowed = ['spellCode', 'pathCode', 'parameters', 'usedPower'];
        foreach (['spellTarget', 'upgrades'] as $key) {
            if (array_key_exists($key, $raw)) {
                $allowed[] = $key;
            }
        }

        $this->assertKeys($raw, $allowed);

        return [
            'spellCode' => $this->code($raw, 'spellCode'),
            'pathCode' => $this->code($raw, 'pathCode'),
            'parameters' => $this->parameters($raw['parameters'] ?? null),
            'usedPower' => $this->usedPower($raw['usedPower'] ?? null),
            'spellTarget' => array_key_exists('spellTarget', $raw) && $raw['spellTarget'] !== null
                ? (new GameRunActionFields())->side($raw['spellTarget'])
                : null,
            'upgrades' => (new GameSpellCastUpgradeBody())->parse($raw['upgrades'] ?? null),
        ];
    }

    /**
     * Проверяет ровно названные ключи.
     *
     * @param array<string, mixed> $body Тело.
     * @param list<string> $allowed Ключи.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertKeys(array $body, array $allowed): void
    {
        $keys = array_keys($body);
        sort($keys);
        sort($allowed);
        if ($keys !== $allowed) {
            throw $this->invalid();
        }
    }

    /**
     * Обязательный код.
     *
     * @param array<string, mixed> $body Тело.
     * @param string $key Ключ.
     *
     * @return string Код.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function code(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw $this->invalid();
        }

        return $value;
    }

    /**
     * Карта активационных параметров.
     *
     * @param mixed $raw Значение.
     *
     * @return array<string, int|array{base: int, size: int}> Параметры.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function parameters(mixed $raw): array
    {
        if (!is_array($raw) || ($raw !== [] && array_is_list($raw))) {
            throw $this->invalid();
        }

        $normalized = [];
        foreach ($raw as $code => $value) {
            if (!is_string($code) || $code === '') {
                throw $this->invalid();
            }

            $normalized[$code] = is_int($value) ? $value : $this->pair($value);
        }

        return $normalized;
    }

    /**
     * Источник и значение используемой мощи.
     *
     * @param mixed $raw Значение.
     *
     * @return array{source: array<string, mixed>, value: array{base: int, size: int}} Мощь.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function usedPower(mixed $raw): array
    {
        if (!is_array($raw) || array_is_list($raw)) {
            throw $this->invalid();
        }

        $this->assertKeys($raw, ['source', 'value']);
        $source = $raw['source'] ?? null;
        if (!is_array($source) || array_is_list($source)) {
            throw $this->invalid();
        }

        $kind = $source['kind'] ?? null;
        $normalized = match ($kind) {
            'characteristic' => $this->sourceCode($source),
            'item' => $this->sourceItem($source),
            'grant' => $this->sourceGrant($source),
            default => null,
        };
        if ($normalized === null) {
            throw $this->invalid();
        }

        return ['source' => $normalized, 'value' => $this->pair($raw['value'] ?? null)];
    }

    /**
     * Источник-характеристика.
     *
     * @param array<mixed> $source Источник.
     *
     * @return array{kind: string, code: string} Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sourceCode(array $source): array
    {
        $this->assertKeys($source, ['code', 'kind']);
        $code = $source['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw $this->invalid();
        }

        return ['kind' => 'characteristic', 'code' => $code];
    }

    /**
     * Источник-предмет.
     *
     * @param array<mixed> $source Источник.
     *
     * @return array{kind: string, inventoryId: int} Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sourceItem(array $source): array
    {
        $this->assertKeys($source, ['inventoryId', 'kind']);
        $inventoryId = $source['inventoryId'] ?? null;
        if (!is_int($inventoryId) || $inventoryId < 1) {
            throw $this->invalid();
        }

        return ['kind' => 'item', 'inventoryId' => $inventoryId];
    }

    /**
     * Источник-грант.
     *
     * @param array<mixed> $source Источник.
     *
     * @return array{kind: string, abilityCode: string} Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sourceGrant(array $source): array
    {
        $this->assertKeys($source, ['abilityCode', 'kind']);
        $abilityCode = $source['abilityCode'] ?? null;
        if (!is_string($abilityCode) || $abilityCode === '') {
            throw $this->invalid();
        }

        return ['kind' => 'grant', 'abilityCode' => $abilityCode];
    }

    /**
     * Размерная пара.
     *
     * @param mixed $value Значение.
     *
     * @return array{base: int, size: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function pair(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw $this->invalid();
        }

        $this->assertKeys($value, ['base', 'size']);
        $base = $value['base'] ?? null;
        $size = $value['size'] ?? null;
        if (!is_int($base) || !is_int($size)) {
            throw $this->invalid();
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Ошибка формы.
     *
     * @return ActionException Исключение.
     */
    private function invalid(): ActionException
    {
        return new ActionException('INVALID_PARAMS', 'Invalid parameter: spell cast');
    }
}
