<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameShopPositionRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Копилка прохода: деньги, количества и остатки магазина до записи.
 */
final class GameEconomyLedger
{
    /**
     * Персонажи прохода: деньги, количества и база до дельт.
     *
     * @var array<int, array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int}>
     */
    private array $characters = [];

    /**
     * NPC прохода: документ и количества.
     *
     * @var array<int, array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int, document: array<string, mixed>}>
     */
    private array $npcSheets = [];

    /**
     * @var array<string, array{row: GameShopPositionRecord, quantity: int}>
     */
    private array $shop = [];

    /**
     * Коды позиций, которые части реально трогали.
     *
     * @var array<string, true>
     */
    private array $touched = [];

    /**
     * Создаёт проход.
     *
     * @param GameEconomyPorts $ports Порты.
     * @param GameEconomyActor $actor Права актора.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyPorts $ports,
        private readonly GameEconomyActor $actor,
    ) {
    }

    /**
     * Кладёт позиции магазина на старте.
     *
     * @param list<GameShopPositionRecord> $rows Строки.
     *
     * @return void
     */
    public function loadShop(array $rows): void
    {
        foreach ($rows as $row) {
            $this->shop[$row->getRuleCode()] = ['row' => $row, 'quantity' => $row->getQuantity()];
        }
    }

    /**
     * Меняет количество предмета листа.
     *
     * @param string $type character, npc или nowhere.
     * @param int $id Id.
     * @param string $ruleCode Код.
     * @param int $delta Дельта.
     *
     * @return void
     *
     * @throws GameInvalidException Если уйдёт ниже нуля.
     * @throws GameNotFoundException Если NPC нет.
     */
    public function addItem(string $type, int $id, string $ruleCode, int $delta): void
    {
        if ($type === 'nowhere') {
            return;
        }

        if ($type === 'character') {
            $this->actor->activeCharacter($id);
        }

        $lines = $this->linesOf($type, $id);
        $next = GameEconomyMath::sum($lines[$ruleCode] ?? 0, $delta);
        if ($next < 0) {
            throw new GameInvalidException('Economy quantity is short');
        }

        $this->storeLines($type, $id, $ruleCode, $next);
    }

    /**
     * Меняет деньги листа.
     *
     * @param string $type character, npc или nowhere.
     * @param int $id Id.
     * @param int $delta Дельта.
     *
     * @return void
     *
     * @throws GameInvalidException Если баланс отрицательный.
     * @throws GameNotFoundException Если NPC нет.
     */
    public function addMoney(string $type, int $id, int $delta): void
    {
        if ($type === 'nowhere') {
            return;
        }

        if ($type === 'character') {
            $this->actor->activeCharacter($id);
        }

        $holder = $type === 'character' ? $this->characterHolder($id) : $this->npcHolder($id);
        $money = GameEconomyMath::sum($holder['money'], $delta);
        if ($money < 0) {
            throw new GameInvalidException('Economy balance is short');
        }

        if ($type === 'character') {
            $this->characters[$id]['money'] = $money;

            return;
        }

        $this->npcSheets[$id]['money'] = $money;
    }

    /**
     * Меняет остаток позиции.
     *
     * @param string $ruleCode Код.
     * @param int $delta Дельта.
     *
     * @return void
     *
     * @throws GameInvalidException Если остатка нет.
     */
    public function addShop(string $ruleCode, int $delta): void
    {
        $this->touched[$ruleCode] = true;
        $next = GameEconomyMath::sum($this->shop[$ruleCode]['quantity'], $delta);
        if ($next < 0) {
            throw new GameInvalidException('Shop stock is short');
        }

        $this->shop[$ruleCode]['quantity'] = $next;
    }

    /**
     * Позиция магазина.
     *
     * @param string $ruleCode Код.
     *
     * @return GameShopPositionRecord Строка.
     *
     * @throws GameInvalidException Если кода нет.
     */
    public function requireShop(string $ruleCode): GameShopPositionRecord
    {
        if (!isset($this->shop[$ruleCode])) {
            throw new GameInvalidException('Shop position was not found');
        }

        return $this->shop[$ruleCode]['row'];
    }

    /**
     * Цена выкупа задана.
     *
     * @param GameShopPositionRecord $position Позиция.
     *
     * @return int Цена.
     *
     * @throws GameInvalidException Если null.
     */
    public function requireSellPrice(GameShopPositionRecord $position): int
    {
        $sellPrice = $position->getSellPrice();
        if ($sellPrice === null) {
            throw new GameInvalidException('Shop does not buy this item');
        }

        return $sellPrice;
    }

    /**
     * Загруженные персонажи.
     *
     * @return array<int, array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int}> Держатели.
     */
    public function characters(): array
    {
        return $this->characters;
    }

    /**
     * Загруженные NPC.
     *
     * @return array<int, array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int, document: array<string, mixed>}> Держатели.
     */
    public function npcSheets(): array
    {
        return $this->npcSheets;
    }

    /**
     * Позиции, которых коснулись части.
     *
     * @return array<string, array{row: GameShopPositionRecord, quantity: int}> Позиции.
     */
    public function touchedShop(): array
    {
        $touched = [];
        foreach ($this->shop as $ruleCode => $slot) {
            if (isset($this->touched[$ruleCode])) {
                $touched[$ruleCode] = $slot;
            }
        }

        return $touched;
    }

    /**
     * Количества листа. Загружает держателя.
     *
     * @param string $type character или npc.
     * @param int $id Id.
     *
     * @return array<string, int> Количества.
     *
     * @throws GameNotFoundException Если NPC нет.
     */
    private function linesOf(string $type, int $id): array
    {
        if ($type === 'character') {
            return $this->characterHolder($id)['lines'];
        }

        return $this->npcHolder($id)['lines'];
    }

    /**
     * Пишет одно количество обратно.
     *
     * @param string $type character или npc.
     * @param int $id Id.
     * @param string $ruleCode Код.
     * @param int $quantity Итог.
     *
     * @return void
     */
    private function storeLines(string $type, int $id, string $ruleCode, int $quantity): void
    {
        if ($type === 'character') {
            $this->characters[$id]['lines'][$ruleCode] = $quantity;

            return;
        }

        $this->npcSheets[$id]['lines'][$ruleCode] = $quantity;
    }

    /**
     * Ленивая загрузка персонажа. Actual берётся через membership.
     *
     * @param int $characterId Персонаж.
     *
     * @return array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int} Держатель.
     *
     * @throws GameInvalidException Если деньги битые.
     */
    private function characterHolder(int $characterId): array
    {
        if (isset($this->characters[$characterId])) {
            return $this->characters[$characterId];
        }

        $record = $this->ports->characters->get($characterId);
        $choices = $record->getChoices();
        if (!is_int($choices['money'] ?? null)) {
            throw new GameInvalidException('Character money is invalid');
        }

        $lines = $this->lineMap($choices['inventory'] ?? null);
        $this->characters[$characterId] = [
            'money' => $choices['money'],
            'lines' => $lines,
            'baseMoney' => $choices['money'],
            'baseLines' => $lines,
            'version' => $record->getActualVersion(),
        ];

        return $this->characters[$characterId];
    }

    /**
     * Ленивая загрузка NPC.
     *
     * @param int $npcId NPC.
     *
     * @return array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int, document: array<string, mixed>} Держатель.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если лист битый.
     */
    private function npcHolder(int $npcId): array
    {
        if (isset($this->npcSheets[$npcId])) {
            return $this->npcSheets[$npcId];
        }

        $npc = $this->actor->requireNpc($npcId);
        $document = $npc->getVersion();
        $choices = $document['choices'] ?? null;
        if (!is_array($choices) || !is_int($choices['money'] ?? null)) {
            throw new GameInvalidException('NPC money is invalid');
        }

        $lines = $this->lineMap($choices['inventory'] ?? null);
        $this->npcSheets[$npcId] = [
            'money' => $choices['money'],
            'lines' => $lines,
            'baseMoney' => $choices['money'],
            'baseLines' => $lines,
            'version' => $npc->getActualVersion(),
            'document' => $document,
        ];

        return $this->npcSheets[$npcId];
    }

    /**
     * Инвентарь в карту количеств.
     *
     * @param mixed $inventory Список.
     *
     * @return array<string, int> Количества.
     *
     * @throws GameInvalidException Если список битый.
     */
    private function lineMap(mixed $inventory): array
    {
        if (!is_array($inventory)) {
            throw new GameInvalidException('Economy inventory is invalid');
        }

        $lines = [];
        foreach ($inventory as $row) {
            if (!is_array($row) || !is_string($row['ruleCode'] ?? null) || !is_int($row['quantity'] ?? null)) {
                continue;
            }

            if (array_key_exists($row['ruleCode'], $lines)) {
                throw new GameInvalidException('Economy inventory rule code is ambiguous');
            }

            $lines[$row['ruleCode']] = $row['quantity'];
        }

        return $lines;
    }
}
