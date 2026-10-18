<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- разбор способов и G21-порядок одного класса.

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
        $parsed = $this->parsedRolls($rolls);
        $this->assertSameSet($participants, $parsed);
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
            $value = $item['value'];

            return $head + [
                'difficulty' => null,
                'roll' => $value,
                'success' => null,
                'rating' => $value['base'],
            ];
        }

        $thrown = $this->roll->throwCheck(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            'solo',
            $this->sheet(['type' => $item['type'], 'id' => $item['id']]),
            null,
            $this->overrideOf($check, $item),
        );

        return $head + $thrown;
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
     * Разбор списка способов.
     *
     * @param array<mixed> $rolls JSON.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function parsedRolls(array $rolls): array
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
     * Тот же набор участников.
     *
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param list<array<string, mixed>> $rolls Способы.
     *
     * @return void
     *
     * @throws GameInvalidException Если набор другой.
     */
    private function assertSameSet(array $participants, array $rolls): void
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
