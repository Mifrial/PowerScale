<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Порядок хода из уже существующей проверки. Своего броска нет.
 */
final class GameBattleOrder
{
    private readonly GameBattleOrderRolls $rolls;

    private readonly GameBattleOrderRoster $roster;

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
        $this->rolls = new GameBattleOrderRolls();
        $this->roster = new GameBattleOrderRoster();
    }

    /**
     * Полный порядок текущего состава.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param array<mixed>|null $rolls Способы или null — бросок карточки всем.
     *
     * @return list<array<string, mixed>> Порядок.
     *
     * @throws GameInvalidException Если карточка, пул или состав пуст.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function rollAll(int $spaceId, int $rulesRevision, array $participants, ?array $rolls = null): array
    {
        if ($participants === []) {
            throw new GameInvalidException('Game battle participants are invalid');
        }

        if ($rolls === null) {
            return $this->sortRolled($this->rolled($spaceId, $rulesRevision, $participants));
        }

        return $this->sortRolled($this->rolledByMethod($spaceId, $rulesRevision, $participants, $rolls));
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
        $kept = $this->roster->kept($turnOrder, $participants);
        $joined = $this->roster->joined($turnOrder, $participants);
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
            $rows[] = [
                'type' => $participant['type'],
                'id' => $participant['id'],
                'name' => $this->nameOf($participant),
            ] + $thrown;
        }

        return $rows;
    }

    /**
     * Бросок по способу на участника.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param list<array{type: string, id: int}> $participants Состав боя.
     * @param array<mixed> $rolls Вход способов.
     *
     * @return list<array<string, mixed>> Записи в порядке состава.
     *
     * @throws GameInvalidException Если состав, карточка или способ чужие.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function rolledByMethod(int $spaceId, int $rulesRevision, array $participants, array $rolls): array
    {
        $parsed = $this->rolls->parse($rolls);
        $this->roster->assertSame($participants, $parsed);
        $check = $this->roll->initiativeCheck($spaceId, $rulesRevision);
        $ruleCode = $this->roll->findInitiativeCode($spaceId, $rulesRevision);
        $byKey = [];
        foreach ($parsed as $item) {
            $byKey[$this->key($item['type'], $item['id'])] = $item;
        }

        $rows = [];
        foreach ($participants as $participant) {
            $item = $byKey[$this->key($participant['type'], $participant['id'])];
            $rows[] = $this->rowOf($spaceId, $rulesRevision, $ruleCode, $check, $item);
        }

        return $rows;
    }

    /**
     * Одна запись порядка.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код карточки.
     * @param CheckSpec $check Карточка.
     * @param array<string, mixed> $item Способ.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws GameInvalidException Если способ или пул чужие.
     * @throws GameNotFoundException Если листа нет.
     */
    private function rowOf(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        CheckSpec $check,
        array $item,
    ): array {
        $head = ['type' => $item['type'], 'id' => $item['id'], 'name' => $this->nameOf($item)];
        if ($item['method'] === 'fixed') {
            return $head + $this->fixedRow($item);
        }

        return $head + $this->thrownRow($spaceId, $rulesRevision, $ruleCode, $check, $item);
    }

    /**
     * Запись фиксированного способа.
     *
     * @param array<string, mixed> $item Способ.
     *
     * @return array<string, mixed> Поля строки.
     */
    private function fixedRow(array $item): array
    {
        $value = $item['value'];

        return [
            'difficulty' => null,
            'roll' => $value,
            'success' => null,
            'rating' => $value['base'],
        ];
    }

    /**
     * Запись брошенного способа.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код карточки.
     * @param CheckSpec $check Карточка.
     * @param array<string, mixed> $item Способ.
     *
     * @return array<string, mixed> Поля строки.
     *
     * @throws GameInvalidException Если способ или пул чужие.
     * @throws GameNotFoundException Если листа нет.
     */
    private function thrownRow(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        CheckSpec $check,
        array $item,
    ): array {
        return $this->roll->throwCheck(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            'solo',
            $this->sheet(['type' => $item['type'], 'id' => $item['id']]),
            null,
            $this->overrideOf($check, $item),
        );
    }

    /**
     * Смена пула или null.
     *
     * @param CheckSpec $check Карточка.
     * @param array<string, mixed> $item Способ.
     *
     * @return GameCheckThrowOverride|null Смена.
     *
     * @throws GameInvalidException Если override или код чужие.
     */
    private function overrideOf(
        CheckSpec $check,
        array $item,
    ): ?GameCheckThrowOverride {
        if ($item['method'] === 'free') {
            return new GameCheckThrowOverride(null, $item['diceCount'], $item['advantage']);
        }

        $sent = $item['characteristicCode'];
        if ($sent !== null) {
            if (!$check->isAllowCharacteristicOverride()) {
                throw new GameInvalidException('Game initiative characteristic is invalid');
            }

            return new GameCheckThrowOverride($sent);
        }

        $code = $check->getCharacteristicCode();
        if ($code !== null && $code !== '') {
            return null;
        }

        throw new GameInvalidException('Game initiative characteristic is invalid');
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
     * Имя участника.
     *
     * @param array<string, mixed> $participant type и id.
     *
     * @return string Имя.
     */
    private function nameOf(array $participant): string
    {
        $type = $participant['type'] ?? null;
        $id = $participant['id'] ?? null;
        if (!is_string($type) || !is_int($id)) {
            return '';
        }

        if ($type === 'npc') {
            return $this->npcs->getById($id)->getName();
        }

        return $this->roll->characterName($id);
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
