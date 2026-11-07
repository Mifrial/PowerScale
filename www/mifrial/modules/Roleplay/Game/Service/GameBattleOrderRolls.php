<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Разбор способов броска инициативы из тела команды.
 */
final class GameBattleOrderRolls
{
    /**
     * Разбор списка способов.
     *
     * @param array<mixed> $rolls JSON.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function parse(array $rolls): array
    {
        if (!array_is_list($rolls) || $rolls === []) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        $parsed = [];
        $seen = [];
        foreach ($rolls as $roll) {
            $item = $this->parsedRoll($roll);
            $key = $this->key($item['type'], $item['id']);
            if (isset($seen[$key])) {
                throw new GameInvalidException('Game battle participant is duplicated');
            }

            $seen[$key] = true;
            $parsed[] = $item;
        }

        return $parsed;
    }

    /**
     * Один способ.
     *
     * @param mixed $roll JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function parsedRoll(mixed $roll): array
    {
        if (!is_array($roll) || !isset($roll['method']) || !is_string($roll['method'])) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        return match ($roll['method']) {
            'characteristic' => $this->characteristicRoll($roll),
            'free' => $this->freeRoll($roll),
            'fixed' => $this->fixedRoll($roll),
            default => throw new GameInvalidException('Game initiative rolls are invalid'),
        };
    }

    /**
     * Способ характеристики.
     *
     * @param array<mixed> $roll JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если ключи чужие.
     */
    private function characteristicRoll(array $roll): array
    {
        $pair = $this->typedId($roll);
        $keys = array_keys($roll);
        sort($keys);
        $code = $roll['characteristicCode'] ?? null;
        if ($keys === ['id', 'method', 'type']) {
            return $pair + ['method' => 'characteristic', 'characteristicCode' => null];
        }

        if ($keys !== ['characteristicCode', 'id', 'method', 'type'] || !is_string($code) || $code === '') {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        return $pair + ['method' => 'characteristic', 'characteristicCode' => $code];
    }

    /**
     * Свободный пул.
     *
     * @param array<mixed> $roll JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если ключи чужие.
     */
    private function freeRoll(array $roll): array
    {
        $pair = $this->typedId($roll);
        $diceCount = $roll['diceCount'] ?? null;
        $advantage = $roll['advantage'] ?? null;
        $keys = array_keys($roll);
        sort($keys);
        if ($advantage === null) {
            if ($keys !== ['diceCount', 'id', 'method', 'type']) {
                throw new GameInvalidException('Game initiative rolls are invalid');
            }
        } elseif ($keys !== ['advantage', 'diceCount', 'id', 'method', 'type']) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        if (!is_int($diceCount) || $diceCount < 1) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        if ($advantage !== null && !is_int($advantage)) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        return $pair + [
            'method' => 'free',
            'diceCount' => $diceCount,
            'advantage' => is_int($advantage) ? $advantage : null,
        ];
    }

    /**
     * Фиксированное значение.
     *
     * @param array<mixed> $roll JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если ключи чужие.
     */
    private function fixedRoll(array $roll): array
    {
        $pair = $this->typedId($roll);
        $keys = array_keys($roll);
        sort($keys);
        if ($keys !== ['id', 'method', 'type', 'value']) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        $value = $roll['value'] ?? null;
        $valueKeys = is_array($value) ? array_keys($value) : [];
        sort($valueKeys);
        $base = is_array($value) ? ($value['base'] ?? null) : null;
        $size = is_array($value) ? ($value['size'] ?? null) : null;
        if ($valueKeys !== ['base', 'size'] || !is_int($base) || !is_int($size)) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        return $pair + ['method' => 'fixed', 'value' => ['base' => $base, 'size' => $size]];
    }

    /**
     * type и id.
     *
     * @param array<mixed> $roll JSON.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если пара чужая.
     */
    private function typedId(array $roll): array
    {
        $type = $roll['type'] ?? null;
        $id = $roll['id'] ?? null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Ключ участника.
     *
     * @param string $type character или npc.
     * @param int $id Id.
     *
     * @return string Ключ.
     */
    private function key(string $type, int $id): string
    {
        return $type . ':' . $id;
    }
}
