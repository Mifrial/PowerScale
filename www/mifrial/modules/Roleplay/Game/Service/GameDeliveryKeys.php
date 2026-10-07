<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Ключи листа из уже сохранённого итога. Позиции магазина не входят.
 */
final class GameDeliveryKeys
{
    /**
     * Персонажи и NPC из result экономики.
     *
     * @param array<string, mixed> $result Колонка result.
     *
     * @return array<int, array<string, mixed>> Ключи.
     */
    public function fromEconomy(array $result): array
    {
        return [
            ...$this->sheetKeys($result['characters'] ?? null, 'character', 'characterId'),
            ...$this->sheetKeys($result['npcs'] ?? null, 'npc', 'npcId'),
        ];
    }

    /**
     * У удара в итоге нет type и id листа.
     *
     * @param array<string, mixed> $result Колонка result.
     *
     * @return array<int, array<string, mixed>> Пустой список.
     */
    public function fromStrike(array $result): array
    {
        unset($result);

        return [];
    }

    /**
     * Пары id и версии одного вида.
     *
     * @param mixed $rows Список итога.
     * @param string $type character или npc.
     * @param string $idKey Поле id.
     *
     * @return array<int, array<string, mixed>> Ключи.
     */
    private function sheetKeys(mixed $rows, string $type, string $idKey): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row[$idKey] ?? null) || !is_int($row['actualVersion'] ?? null)) {
                continue;
            }

            $keys[] = [
                'type' => $type,
                'id' => $row[$idKey],
                'actualVersion' => $row['actualVersion'],
            ];
        }

        return $keys;
    }
}
