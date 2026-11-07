<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\JsonObject;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Разбор ключей проекции: пары {type, id} без повторов.
 */
final class GameProjectionKeys
{
    private const KEY_LIMIT = 32;

    /**
     * Разбор ключей. Пустой и длинный список отвергаются.
     *
     * @param array<mixed> $keys JSON.
     *
     * @return list<array{type: string, id: int}> Ключи.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameInvalidException Если повтор.
     */
    public static function read(array $keys): array
    {
        if (!array_is_list($keys) || $keys === [] || count($keys) > self::KEY_LIMIT) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: keys');
        }

        $parsed = [];
        $seen = [];
        foreach ($keys as $key) {
            $parsed[] = self::one($key, $seen);
        }

        return $parsed;
    }

    /**
     * Один ключ.
     *
     * @param mixed $key Элемент.
     * @param array<string, true> $seen Уже принятые.
     *
     * @return array{type: string, id: int} Ключ.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameInvalidException Если повтор.
     */
    private static function one(mixed $key, array &$seen): array
    {
        if ($key instanceof JsonObject) {
            $key = $key->getMembers();
        }

        $names = is_array($key) ? array_keys($key) : [];
        sort($names);
        if ($names !== ['id', 'type']) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: keys');
        }

        $type = $key['type'];
        $id = $key['id'];
        if (!is_string($type) || !in_array($type, ['character', 'npc'], true) || !is_int($id)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: keys');
        }

        $mark = $type . ':' . $id;
        if (isset($seen[$mark])) {
            throw new GameInvalidException('Projection key is repeated');
        }

        $seen[$mark] = true;

        return ['type' => $type, 'id' => $id];
    }
}
