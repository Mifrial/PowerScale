<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Порядок хода из уже существующей проверки. Своего броска нет.
 */
final class GameBattleOrder
{
    /**
     * Создаёт расчёт.
     *
     * @param GameCheckRoll $roll Проверка G18.
     * @param GameNpcRepository $npcs Лист NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $roll,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Полный порядок текущего состава.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param list<array{type: string, id: int}> $participants Состав.
     *
     * @return list<array<string, mixed>> Порядок.
     *
     * @throws GameInvalidException Если карточка, пул или состав пуст.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function rollAll(int $spaceId, int $rulesRevision, array $participants): array
    {
        if ($participants === []) {
            throw new GameInvalidException('Game battle participants are invalid');
        }

        return $this->sortRolled($this->rolled($spaceId, $rulesRevision, $participants));
    }

    /**
     * Оставшиеся сохраняют места. Новые встают в конец.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<int, array<string, mixed>> $turnOrder Уже записанный порядок.
     * @param list<array{type: string, id: int}> $participants Новый состав.
     *
     * @return list<array<string, mixed>> Порядок.
     *
     * @throws GameInvalidException Если новых несколько и карточка чужая.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function appendJoined(
        int $spaceId,
        int $rulesRevision,
        array $turnOrder,
        array $participants,
    ): array {
        $kept = $this->kept($turnOrder, $participants);
        $joined = $this->joined($turnOrder, $participants);
        if (count($joined) < 2) {
            return array_merge($kept, $this->unrolled($joined));
        }

        return array_merge($kept, $this->sortRolled($this->rolled($spaceId, $rulesRevision, $joined)));
    }

    /**
     * Бросок каждого участника.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param list<array{type: string, id: int}> $participants Участники.
     *
     * @return list<array<string, mixed>> Записи в порядке состава.
     *
     * @throws GameInvalidException Если карточка или пул чужие.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function rolled(int $spaceId, int $rulesRevision, array $participants): array
    {
        $ruleCode = $this->roll->findInitiativeCode($spaceId, $rulesRevision);
        $rows = [];
        foreach ($participants as $participant) {
            $thrown = $this->roll->throwCheck(
                $spaceId,
                $rulesRevision,
                $ruleCode,
                'solo',
                $this->sheet($participant),
                null,
            );
            $rows[] = ['type' => $participant['type'], 'id' => $participant['id']] + $thrown;
        }

        return $rows;
    }

    /**
     * Один новый без итога проверки.
     *
     * @param list<array{type: string, id: int}> $joined Новые.
     *
     * @return list<array<string, mixed>> Хвост.
     */
    private function unrolled(array $joined): array
    {
        if ($joined === []) {
            return [];
        }

        $participant = $joined[0];

        return [[
            'type' => $participant['type'],
            'id' => $participant['id'],
            'difficulty' => null,
            'roll' => null,
            'success' => null,
            'rating' => null,
        ],
        ];
    }

    /**
     * Больше успехов раньше. Равные остаются в прежнем порядке.
     *
     * @param list<array<string, mixed>> $rows Записи.
     *
     * @return list<array<string, mixed>> Порядок.
     */
    private function sortRolled(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $index => $row) {
            $indexed[] = ['index' => $index, 'row' => $row];
        }

        usort($indexed, $this->bySuccess(...));
        $sorted = [];
        foreach ($indexed as $item) {
            $sorted[] = $item['row'];
        }

        return $sorted;
    }

    /**
     * Сравнение двух бросков.
     *
     * @param array{index: int, row: array<string, mixed>} $left Левый.
     * @param array{index: int, row: array<string, mixed>} $right Правый.
     *
     * @return int Порядок usort.
     */
    private function bySuccess(array $left, array $right): int
    {
        $leftBase = is_array($left['row']['roll'] ?? null) ? ($left['row']['roll']['base'] ?? 0) : 0;
        $rightBase = is_array($right['row']['roll'] ?? null) ? ($right['row']['roll']['base'] ?? 0) : 0;
        if ($leftBase === $rightBase) {
            return $left['index'] <=> $right['index'];
        }

        return $rightBase <=> $leftBase;
    }

    /**
     * Кто остался, в прежнем порядке.
     *
     * @param array<int, array<string, mixed>> $turnOrder Порядок.
     * @param list<array{type: string, id: int}> $participants Новый состав.
     *
     * @return list<array<string, mixed>> Префикс.
     */
    private function kept(array $turnOrder, array $participants): array
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
    private function joined(array $turnOrder, array $participants): array
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
     * Лист персонажа или NPC.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameInvalidException Если у NPC нет листа.
     * @throws GameNotFoundException Если строки нет.
     */
    private function sheet(array $participant): array
    {
        if ($participant['type'] === 'character') {
            return $this->roll->characterSheet($participant['id'])['sheet'];
        }

        $sheet = $this->npcs->getById($participant['id'])->getVersion()['sheet'] ?? null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game initiative sheet is invalid');
        }

        return $sheet;
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
