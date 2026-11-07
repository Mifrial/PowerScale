<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Сверка состава боя и хвост порядка при его смене.
 */
final class GameBattleOrderRoster
{
    /**
     * Тот же набор участников.
     *
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param list<array<string, mixed>> $rolls Способы.
     *
     * @return void
     *
     * @throws GameInvalidException Если набор другой.
     */
    public function assertSame(array $participants, array $rolls): void
    {
        $left = [];
        foreach ($participants as $participant) {
            $left[] = $this->key($participant['type'], $participant['id']);
        }

        $right = [];
        foreach ($rolls as $roll) {
            $right[] = $this->key($roll['type'], $roll['id']);
        }

        sort($left);
        sort($right);
        if ($left !== $right) {
            throw new GameInvalidException('Game initiative rolls are invalid');
        }
    }

    /**
     * Кто остался, в прежнем порядке.
     *
     * @param array<int, array<string, mixed>> $turnOrder Порядок.
     * @param list<array{type: string, id: int}> $participants Новый состав.
     *
     * @return list<array<string, mixed>> Префикс.
     */
    public function kept(array $turnOrder, array $participants): array
    {
        $allowed = [];
        foreach ($participants as $participant) {
            $allowed[$this->key($participant['type'], $participant['id'])] = true;
        }

        $kept = [];
        foreach ($turnOrder as $entry) {
            $type = $entry['type'] ?? null;
            $id = $entry['id'] ?? null;
            if (is_string($type) && is_int($id) && isset($allowed[$this->key($type, $id)])) {
                $kept[] = $entry;
            }
        }

        return $kept;
    }

    /**
     * Кого не было в записанном порядке, в порядке нового списка.
     *
     * @param array<int, array<string, mixed>> $turnOrder Порядок.
     * @param list<array{type: string, id: int}> $participants Новый состав.
     *
     * @return list<array{type: string, id: int}> Новые.
     */
    public function joined(array $turnOrder, array $participants): array
    {
        $present = [];
        foreach ($turnOrder as $entry) {
            $type = $entry['type'] ?? null;
            $id = $entry['id'] ?? null;
            if (is_string($type) && is_int($id)) {
                $present[$this->key($type, $id)] = true;
            }
        }

        $joined = [];
        foreach ($participants as $participant) {
            if (!isset($present[$this->key($participant['type'], $participant['id'])])) {
                $joined[] = $participant;
            }
        }

        return $joined;
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
