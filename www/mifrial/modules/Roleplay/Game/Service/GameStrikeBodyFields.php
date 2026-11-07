<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Общие проверки формы тела удара.
 */
final class GameStrikeBodyFields
{
    /**
     * Дополняет список ключей необязательными, если они есть в теле.
     *
     * @param array<string, mixed> $body Тело.
     * @param list<string> $allowed Обязательные ключи.
     * @param list<string> $optional Необязательные ключи.
     *
     * @return list<string> Ключи.
     */
    public function optional(array $body, array $allowed, array $optional): array
    {
        foreach ($optional as $key) {
            if (array_key_exists($key, $body)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Ключи реакции: база, блок и необязательные поля.
     *
     * @param array<string, mixed> $row Тело.
     * @param list<string> $base Базовые ключи.
     *
     * @return list<string> Ключи.
     */
    public function reactionKeys(array $row, array $base): array
    {
        if (
            array_key_exists('blockItemInventoryId', $row)
            || array_key_exists('blockItemProfileIndex', $row)
        ) {
            $base[] = 'blockItemInventoryId';
            $base[] = 'blockItemProfileIndex';
        }

        return $this->optional($row, $base, ['blockItemRuleCode', 'turn', 'spendConcentration']);
    }

    /**
     * Ровно названные ключи.
     *
     * @param array<string, mixed> $body Тело.
     * @param list<string> $allowed Ключи.
     * @param string $subject Подлежащее сообщения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function assertKeys(array $body, array $allowed, string $subject = 'strike'): void
    {
        $keys = array_keys($body);
        sort($keys);
        $expected = $allowed;
        sort($expected);
        if ($keys !== $expected) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $subject);
        }
    }

    /**
     * Участник.
     *
     * @param mixed $side Объект.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function side(mixed $side): array
    {
        if (!is_array($side)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        $keys = array_keys($side);
        sort($keys);
        $type = $side['type'] ?? null;
        $id = $side['id'] ?? null;
        if ($keys !== ['id', 'type'] || !is_string($type) || !is_int($id)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        return ['type' => $type, 'id' => $id];
    }
}
