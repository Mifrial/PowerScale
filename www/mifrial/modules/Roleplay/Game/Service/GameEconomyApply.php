<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameShopPositionRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameShopPositionRepository;

/**
 * Один проход операции внутри уже открытой транзакции.
 */
final class GameEconomyApply
{
    /**
     * Персонажи прохода: деньги, количества и база до дельт.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $characters = [];

    /**
     * NPC прохода: документ и количества.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $npcSheets = [];

    /**
     * @var array<string, array{row: GameShopPositionRecord, quantity: int}>
     */
    private array $shop = [];

    /**
     * @var array<int, int>
     */
    private array $characterVersions = [];

    /**
     * @var array<int, int>
     */
    private array $npcVersions = [];

    /**
     * @var array<string, int>
     */
    private array $shopVersions = [];

    /**
     * Коды позиций, которые части реально трогали.
     *
     * @var array<string, true>
     */
    private array $touchedShop = [];

    private GameRecord $game;

    private int $actorUserId;

    private bool $editAll;

    private bool $viewAll;

    /**
     * Создаёт проход.
     *
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param ICharacters $charactersPort Строки персонажа.
     * @param IGameMemberships $memberships Персонажи игры.
     * @param GameMemberRepository $members Люди.
     * @param GameNpcRepository $npcs NPC.
     * @param GameShopPositionRepository $shopPositions Магазин.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterActualMutations $mutations,
        private readonly ICharacters $charactersPort,
        private readonly IGameMemberships $memberships,
        private readonly GameMemberRepository $members,
        private readonly GameNpcRepository $npcs,
        private readonly GameShopPositionRepository $shopPositions,
        private readonly ConflictSheetProjection $conflictProjection,
    ) {
    }

    /**
     * Считает дельты, пишет листы и строку ключа.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     * @param array $parts Части.
     * @param array $expectedVersions Версии.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameEconomyConflictException Если версия или ключ.
     * @throws GameInvalidException Если часть.
     * @throws GameNotFoundException Если NPC или персонажа нет.
     */
    public function run(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $parts,
        array $expectedVersions,
    ): array {
        $this->game = $game;
        $this->actorUserId = $actorUserId;
        $this->editAll = $editAll;
        $this->viewAll = $viewAll;
        $this->readExpected($expectedVersions);
        foreach ($this->shopPositions->getListByGame($game->getId()) as $row) {
            $this->shop[$row->getRuleCode()] = ['row' => $row, 'quantity' => $row->getQuantity()];
        }

        foreach ($parts as $part) {
            $this->applyPart($game, $actorUserId, $editAll, $part);
        }

        return $this->writeSheets();
    }

    /**
     * Читает три списка версий.
     *
     * @param array $expectedVersions Тело.
     *
     * @return void
     *
     * @throws GameInvalidException Если форма.
     */
    private function readExpected(array $expectedVersions): void
    {
        $this->characterVersions = $this->versionMap($expectedVersions['characters'] ?? null, 'characterId');
        $this->npcVersions = $this->versionMap($expectedVersions['npcs'] ?? null, 'npcId');
        $this->shopVersions = $this->versionMap($expectedVersions['positions'] ?? null, 'ruleCode');
    }

    /**
     * Пары id/код → версия.
     *
     * @param mixed $rows Список.
     * @param string $idKey Поле идентификатора.
     *
     * @return array<int|string, int> Карта.
     *
     * @throws GameInvalidException Если пара битая.
     */
    private function versionMap(mixed $rows, string $idKey): array
    {
        if (!is_array($rows)) {
            throw new GameInvalidException('Economy versions are invalid');
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['actualVersion'] ?? $row['version'] ?? null)) {
                throw new GameInvalidException('Economy version row is invalid');
            }

            $id = $row[$idKey] ?? null;
            $version = $row['actualVersion'] ?? $row['version'];
            if ((!is_int($id) && !is_string($id)) || !is_int($version)) {
                throw new GameInvalidException('Economy version row is invalid');
            }

            $map[$id] = $version;
        }

        return $map;
    }

    /**
     * Одна часть.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param mixed $part Объект.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если вид или дельта.
     * @throws GameNotFoundException Если цели нет.
     */
    private function applyPart(GameRecord $game, int $actorUserId, bool $editAll, mixed $part): void
    {
        if (!is_array($part) || !is_string($part['kind'] ?? null)) {
            throw new GameInvalidException('Economy part is invalid');
        }

        $kind = $part['kind'];
        if ($kind === 'buy' || $kind === 'sell' || $kind === 'discard') {
            $this->applyStock($game, $actorUserId, $editAll, $kind, $part);

            return;
        }

        if ($kind === 'transfer_item' || $kind === 'transfer_money') {
            $this->applyTransfer($game, $actorUserId, $editAll, $kind, $part);

            return;
        }

        if ($kind === 'loot') {
            $this->applyLoot($game, $actorUserId, $editAll, $part);

            return;
        }

        throw new GameInvalidException('Economy part kind is invalid');
    }

    /**
     * buy, sell или discard одного персонажа.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param string $kind Вид.
     * @param array $part Часть.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если числа или остаток.
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function applyStock(GameRecord $game, int $actorUserId, bool $editAll, string $kind, array $part): void
    {
        $this->assertKeys($part, ['kind', 'characterId', 'ruleCode', 'quantity']);
        $characterId = $this->requireInt($part, 'characterId');
        $ruleCode = $this->requireCode($part);
        $quantity = $this->requirePositive($part, 'quantity');
        $this->assertCharacterActor($game, $actorUserId, $editAll, $characterId);
        $this->addItem($game, 'character', $characterId, $ruleCode, $kind === 'buy' ? $quantity : -$quantity);
        if ($kind === 'discard') {
            return;
        }

        $position = $this->requireShop($ruleCode);
        $unit = $kind === 'buy' ? $position->getBuyPrice() : $this->requireSellPrice($position);
        $signed = $kind === 'buy' ? -1 : 1;
        $this->addMoney($game, 'character', $characterId, $this->product($unit, $quantity) * $signed);
        $this->addShop($ruleCode, $kind === 'buy' ? -$quantity : $quantity);
    }

    /**
     * Передача предмета или денег.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param string $kind Вид.
     * @param array $part Часть.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если концы совпали.
     * @throws GameNotFoundException Если конца нет.
     */
    private function applyTransfer(GameRecord $game, int $actorUserId, bool $editAll, string $kind, array $part): void
    {
        $allowed = $kind === 'transfer_money'
            ? ['kind', 'from', 'to', 'amount']
            : ['kind', 'from', 'to', 'ruleCode', 'quantity'];
        $this->assertKeys($part, $allowed);
        $this->assertEndpointKeys($part['from'] ?? null, false);
        $this->assertEndpointKeys($part['to'] ?? null, false);
        $from = $this->endpoint($part['from'] ?? null, false);
        $to = $this->endpoint($part['to'] ?? null, false);
        if ($from === $to) {
            throw new GameInvalidException('Economy transfer ends are the same');
        }

        $this->assertEndpointActor($game, $actorUserId, $editAll, $from);
        $this->assertEndpointKnown($game, $to);
        if ($kind === 'transfer_money') {
            $amount = $this->requirePositive($part, 'amount');
            $this->addMoney($game, $from[0], $from[1], -$amount);
            $this->addMoney($game, $to[0], $to[1], $amount);

            return;
        }

        $ruleCode = $this->requireCode($part);
        $quantity = $this->requirePositive($part, 'quantity');
        $this->addItem($game, $from[0], $from[1], $ruleCode, -$quantity);
        $this->addItem($game, $to[0], $to[1], $ruleCode, $quantity);
    }

    /**
     * Выдача предмета или долей денег.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array $part Часть.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если оба набора полей.
     * @throws GameNotFoundException Если получателя нет.
     */
    private function applyLoot(GameRecord $game, int $actorUserId, bool $editAll, array $part): void
    {
        $this->assertWriter($game, $actorUserId, $editAll);
        $hasItem = array_key_exists('ruleCode', $part) || array_key_exists('to', $part);
        $hasShares = array_key_exists('shares', $part);
        if ($hasItem === $hasShares) {
            throw new GameInvalidException('Economy loot mixes item and money');
        }

        if ($hasShares) {
            $this->assertKeys($part, ['kind', 'shares']);
            $this->applyShares($game, $part['shares']);

            return;
        }

        $this->assertKeys($part, ['kind', 'ruleCode', 'quantity', 'to']);
        $this->assertEndpointKeys($part['to'] ?? null, true);
        $to = $this->endpoint($part['to'] ?? null, true);
        $this->addItem($game, $to[0], $to[1], $this->requireCode($part), $this->requirePositive($part, 'quantity'));
    }

    /**
     * Доли денег loot.
     *
     * @param GameRecord $game Игра.
     * @param mixed $shares Список.
     *
     * @return void
     *
     * @throws GameInvalidException Если доля битая.
     * @throws GameNotFoundException Если получателя нет.
     */
    private function applyShares(GameRecord $game, mixed $shares): void
    {
        if (!is_array($shares) || $shares === []) {
            throw new GameInvalidException('Economy loot shares are empty');
        }

        foreach ($shares as $share) {
            if (!is_array($share)) {
                throw new GameInvalidException('Economy loot share is invalid');
            }

            $this->assertShareKeys($share);
            $to = $this->endpoint($share, true);
            $this->addMoney($game, $to[0], $to[1], $this->requirePositive($share, 'amount'));
        }
    }

    /**
     * Конец передачи.
     *
     * @param mixed $endpoint Объект.
     * @param bool $allowNowhere true для loot.
     *
     * @return array{0: string, 1: int} Тип и id. id 0 у nowhere.
     *
     * @throws GameInvalidException Если форма.
     */
    private function endpoint(mixed $endpoint, bool $allowNowhere): array
    {
        if (!is_array($endpoint) || !is_string($endpoint['type'] ?? null)) {
            throw new GameInvalidException('Economy endpoint is invalid');
        }

        if ($endpoint['type'] === 'nowhere' && $allowNowhere) {
            return ['nowhere', 0];
        }

        if (($endpoint['type'] !== 'character' && $endpoint['type'] !== 'npc') || !is_int($endpoint['id'] ?? null)) {
            throw new GameInvalidException('Economy endpoint is invalid');
        }

        return [$endpoint['type'], $endpoint['id']];
    }

    /**
     * Актор может менять этого персонажа.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если не active.
     * @throws GameNotFoundException Если строки нет.
     */
    private function assertCharacterActor(GameRecord $game, int $actorUserId, bool $editAll, int $characterId): void
    {
        $row = $this->activeCharacter($game->getId(), $characterId);
        if ($row->getCharacterOwnerId() === $actorUserId || $this->isStaff($game, $actorUserId, $editAll)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Источник передачи.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array{0: string, 1: int} $endpoint Конец.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если не active.
     * @throws GameNotFoundException Если нет.
     */
    private function assertEndpointActor(GameRecord $game, int $actorUserId, bool $editAll, array $endpoint): void
    {
        if ($endpoint[0] === 'npc') {
            $this->assertWriter($game, $actorUserId, $editAll);
            $this->requireNpc($game->getId(), $endpoint[1]);

            return;
        }

        $this->assertCharacterActor($game, $actorUserId, $editAll, $endpoint[1]);
    }

    /**
     * Цель существует.
     *
     * @param GameRecord $game Игра.
     * @param array{0: string, 1: int} $endpoint Конец.
     *
     * @return void
     *
     * @throws GameInvalidException Если персонаж не active.
     * @throws GameNotFoundException Если нет.
     */
    private function assertEndpointKnown(GameRecord $game, array $endpoint): void
    {
        if ($endpoint[0] === 'npc') {
            $this->requireNpc($game->getId(), $endpoint[1]);

            return;
        }

        $this->activeCharacter($game->getId(), $endpoint[1]);
    }

    /**
     * Ведущий.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertWriter(GameRecord $game, int $actorUserId, bool $editAll): void
    {
        if (!$this->isStaff($game, $actorUserId, $editAll)) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Владелец игры, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return bool Да, если может вести.
     */
    private function isStaff(GameRecord $game, int $actorUserId, bool $editAll): bool
    {
        if ($game->getOwnerId() === $actorUserId || $editAll) {
            return true;
        }

        try {
            return $this->members->getByPair($game->getId(), $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * Active-строка персонажа.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameInvalidException Если не active.
     * @throws GameNotFoundException Если нет.
     */
    private function activeCharacter(int $gameId, int $characterId): GameCharacterRecord
    {
        $row = $this->memberships->get($gameId, $characterId);
        if ($row->getStatus() !== 'active') {
            throw new GameInvalidException('Game character is not active');
        }

        return $row;
    }

    /**
     * NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return GameNpcRecord Строка.
     *
     * @throws GameNotFoundException Если чужой.
     */
    private function requireNpc(int $gameId, int $npcId): GameNpcRecord
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException();
        }

        return $npc;
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
    private function requireShop(string $ruleCode): GameShopPositionRecord
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
    private function requireSellPrice(GameShopPositionRecord $position): int
    {
        $sellPrice = $position->getSellPrice();
        if ($sellPrice === null) {
            throw new GameInvalidException('Shop does not buy this item');
        }

        return $sellPrice;
    }

    /**
     * Меняет количество предмета листа.
     *
     * @param GameRecord $game Игра.
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
    private function addItem(GameRecord $game, string $type, int $id, string $ruleCode, int $delta): void
    {
        if ($type === 'nowhere') {
            return;
        }

        if ($type === 'character') {
            $this->activeCharacter($game->getId(), $id);
        }

        $lines = $this->linesOf($game, $type, $id);
        $next = $this->sum($lines[$ruleCode] ?? 0, $delta);
        if ($next < 0) {
            throw new GameInvalidException('Economy quantity is short');
        }

        $this->storeLines($type, $id, $ruleCode, $next);
    }

    /**
     * Меняет деньги листа.
     *
     * @param GameRecord $game Игра.
     * @param string $type character, npc или nowhere.
     * @param int $id Id.
     * @param int $delta Дельта.
     *
     * @return void
     *
     * @throws GameInvalidException Если баланс отрицательный.
     * @throws GameNotFoundException Если NPC нет.
     */
    private function addMoney(GameRecord $game, string $type, int $id, int $delta): void
    {
        if ($type === 'nowhere') {
            return;
        }

        if ($type === 'character') {
            $this->activeCharacter($game->getId(), $id);
        }

        $holder = $type === 'character' ? $this->characterHolder($id) : $this->npcHolder($game->getId(), $id);
        $money = $this->sum($holder['money'], $delta);
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
    private function addShop(string $ruleCode, int $delta): void
    {
        $this->touchedShop[$ruleCode] = true;
        $next = $this->sum($this->shop[$ruleCode]['quantity'], $delta);
        if ($next < 0) {
            throw new GameInvalidException('Shop stock is short');
        }

        $this->shop[$ruleCode]['quantity'] = $next;
    }

    /**
     * Количества листа. Загружает держателя.
     *
     * @param GameRecord $game Игра.
     * @param string $type character или npc.
     * @param int $id Id.
     *
     * @return array<string, int> Количества.
     *
     * @throws GameNotFoundException Если NPC нет.
     */
    private function linesOf(GameRecord $game, string $type, int $id): array
    {
        if ($type === 'character') {
            return $this->characterHolder($id)['lines'];
        }

        return $this->npcHolder($game->getId(), $id)['lines'];
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
     * @return array{money: int, lines: array<string, int>, version: int} Держатель.
     */
    private function characterHolder(int $characterId): array
    {
        if (isset($this->characters[$characterId])) {
            return $this->characters[$characterId];
        }

        $record = $this->charactersPort->get($characterId);
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
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array{money: int, lines: array<string, int>, version: int, document: array<string, mixed>} Держатель.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если лист битый.
     */
    private function npcHolder(int $gameId, int $npcId): array
    {
        if (isset($this->npcSheets[$npcId])) {
            return $this->npcSheets[$npcId];
        }

        $npc = $this->requireNpc($gameId, $npcId);
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

    /**
     * Пишет листы и магазин. Версии сверяются здесь.
     *
     * @return array<string, mixed> Итог версий.
     *
     * @throws GameEconomyConflictException Если версия другая.
     * @throws GameInvalidException Если версия не передана.
     */
    private function writeSheets(): array
    {
        $characters = [];
        foreach ($this->characters as $characterId => $holder) {
            $characters[] = $this->writeCharacter($characterId, $holder);
        }

        $npcs = [];
        foreach ($this->npcSheets as $npcId => $holder) {
            $npcs[] = $this->writeNpc($npcId, $holder);
        }

        $positions = [];
        foreach ($this->shop as $code => $slot) {
            if (!isset($this->touchedShop[$code])) {
                continue;
            }

            $positions[] = $this->writeShop($code, $slot);
        }

        $this->assertVersionsUsed();

        return [
            'characters' => $characters,
            'npcs' => $npcs,
            'positions' => $positions,
        ];
    }

    /**
     * Персонаж через apply.
     *
     * @param int $characterId Персонаж.
     * @param array{money: int, lines: array<string, int>, version: int} $holder Итог.
     *
     * @return array{characterId: int, actualVersion: int} Версия.
     *
     * @throws GameEconomyConflictException Если версия не названа.
     * @throws GameInvalidException Если версия не передана.
     */
    private function writeCharacter(int $characterId, array $holder): array
    {
        $expected = $this->pullVersion($this->characterVersions, $characterId);
        $operations = $this->operationsOf($holder);
        if ($operations === []) {
            if ($expected !== $holder['version']) {
                $record = $this->charactersPort->get($characterId);
                throw $this->characterConflict(
                    $characterId,
                    new CharacterConflictException(
                        $record->getActualVersion(),
                        currentSheet: [
                            'choices' => $record->getChoices(),
                            'sheet' => $record->getSheet(),
                        ],
                    ),
                );
            }

            return ['characterId' => $characterId, 'actualVersion' => $expected];
        }

        try {
            $record = $this->mutations->apply($characterId, $expected, $operations);
        } catch (CharacterConflictException $exception) {
            throw $this->characterConflict($characterId, $exception);
        }

        return ['characterId' => $characterId, 'actualVersion' => $record->getActualVersion()];
    }

    /**
     * NPC через applyToDocument.
     *
     * @param int $npcId NPC.
     * @param array{money: int, lines: array<string, int>, version: int, document: array<string, mixed>} $holder Итог.
     *
     * @return array{npcId: int, actualVersion: int} Версия.
     *
     * @throws GameEconomyConflictException Если версия не названа или другая.
     * @throws GameInvalidException Если лист без ревизии.
     */
    private function writeNpc(int $npcId, array $holder): array
    {
        $expected = $this->pullVersion($this->npcVersions, $npcId);
        $document = $holder['document'];
        $parts = $this->npcDocumentParts($document);
        $choices = $parts['choices'];
        $sheet = $parts['sheet'];
        $spaceId = $parts['spaceId'];
        $revision = $parts['revision'];

        $operations = $this->operationsOf($holder);
        if ($operations === []) {
            $this->assertNpcNoOpVersion($npcId, $expected, $holder['version']);
            return ['npcId' => $npcId, 'actualVersion' => $expected];
        }

        $patched = $this->mutations->applyToDocument(
            $spaceId,
            $revision,
            $choices,
            $sheet,
            $operations,
        );
        $document['choices'] = $patched['choices'];
        $document['sheet'] = $patched['sheet'];
        try {
            $saved = $this->npcs->replaceVersion($npcId, $document, $expected);
        } catch (GameEconomyConflictException $exception) {
            $this->throwNpcConflict($npcId, $exception);
        }

        return ['npcId' => $npcId, 'actualVersion' => $saved->getActualVersion()];
    }

    /**
     * Проверяет версию NPC для операции без изменений.
     *
     * @param int $npcId NPC.
     * @param int $expected Ожидаемая версия.
     * @param int $actual Переданная версия.
     *
     * @return void
     *
     * @throws GameEconomyConflictException Если версия устарела.
     */
    private function assertNpcNoOpVersion(int $npcId, int $expected, int $actual): void
    {
        if ($expected === $actual) {
            return;
        }

        $npc = $this->npcs->getById($npcId);
        $this->throwNpcConflict(
            $npcId,
            new GameEconomyConflictException($npc->getActualVersion()),
        );
    }

    /**
     * Проецирует актуальный лист NPC и выбрасывает economy-конфликт.
     *
     * @param int $npcId NPC.
     * @param GameEconomyConflictException $exception Исходный конфликт.
     *
     * @return never Не возвращает управление.
     *
     * @throws GameNotFoundException Если NPC исчез.
     * @throws GameEconomyConflictException С проекцией листа.
     */
    private function throwNpcConflict(int $npcId, GameEconomyConflictException $exception): never
    {
        $npc = $this->npcs->getById($npcId);
        $full = $this->viewAll
            || $this->game->getOwnerId() === $this->actorUserId
            || $this->editAll
            || $this->isGm($this->game->getId(), $this->actorUserId);
        $current = $exception->getCurrentSheet() ?? [
            'choices' => $npc->getVersion()['choices'] ?? [],
            'sheet' => $npc->getVersion()['sheet'] ?? [],
        ];
        $projected = $this->conflictProjection->projectNpc(
            $current,
            $npc->getVisibility(),
            $full,
        );

        throw new GameEconomyConflictException(
            $exception->getCurrentVersion(),
            $exception->getMessage(),
            $exception,
            [
                'choices' => is_array($projected['choices'] ?? null) ? $projected['choices'] : [],
                'sheet' => is_array($projected['sheet'] ?? null) ? $projected['sheet'] : [],
            ],
        );
    }

    /**
     * Переводит stale Character в Game projection.
     *
     * @param int $characterId Персонаж.
     * @param CharacterConflictException $exception Исходный конфликт.
     *
     * @return GameEconomyConflictException Конфликт Game.
     *
     * @throws GameNotFoundException Если membership отсутствует.
     * @throws GameInvalidException Если snapshot битый.
     */
    private function characterConflict(
        int $characterId,
        CharacterConflictException $exception,
    ): GameEconomyConflictException {
        $membership = $this->memberships->get($this->game->getId(), $characterId);
        $record = $this->charactersPort->get($characterId);
        $full = $this->viewAll
            || $this->game->getOwnerId() === $this->actorUserId
            || $this->editAll
            || $membership->getCharacterOwnerId() === $this->actorUserId
            || $this->isGm($this->game->getId(), $this->actorUserId);
        $current = $exception->getCurrentSheet();
        if ($current === null) {
            $current = ['choices' => $record->getChoices(), 'sheet' => $record->getSheet()];
        }

        return new GameEconomyConflictException(
            $exception->getCurrentVersion(),
            'Game economy character version conflict',
            $exception,
            $this->conflictProjection->projectGameCharacter(
                $current['choices'],
                $current['sheet'],
                $membership->getSectionVisibility(),
                $full,
            ),
        );
    }

    /**
     * Роль GM.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если актор GM.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * Проверяет и разбирает document NPC для mutation.
     *
     * @param array<string, mixed> $document Документ NPC.
     *
     * @return array{choices: array<mixed>, sheet: array<mixed>, spaceId: int, revision: int} Части документа.
     *
     * @throws GameInvalidException Если документ без ревизии.
     */
    private function npcDocumentParts(array $document): array
    {
        $choices = $document['choices'] ?? null;
        $sheet = $document['sheet'] ?? null;
        $spaceId = $document['spaceId'] ?? null;
        $revision = $document['rulesRevision'] ?? null;
        if (!is_array($choices) || !is_array($sheet) || !is_int($spaceId) || !is_int($revision)) {
            throw new GameInvalidException('NPC sheet is invalid');
        }

        return [
            'choices' => $choices,
            'sheet' => $sheet,
            'spaceId' => $spaceId,
            'revision' => $revision,
        ];
    }

    /**
     * Позиция магазина.
     *
     * @param string $ruleCode Код.
     * @param array{row: GameShopPositionRecord, quantity: int} $slot Итог.
     *
     * @return array{ruleCode: string, version: int} Версия.
     *
     * @throws GameEconomyConflictException Если версия другая.
     * @throws GameInvalidException Если версия не передана.
     */
    private function writeShop(string $ruleCode, array $slot): array
    {
        $expected = $this->pullVersion($this->shopVersions, $ruleCode);
        if ($expected !== $slot['row']->getVersion()) {
            throw new GameEconomyConflictException($slot['row']->getVersion());
        }

        if ($slot['quantity'] === $slot['row']->getQuantity()) {
            return ['ruleCode' => $ruleCode, 'version' => $expected];
        }

        $this->shopPositions->save(
            $slot['row'],
            $slot['row']->getBuyPrice(),
            $slot['row']->getSellPrice(),
            $slot['quantity'],
        );

        return ['ruleCode' => $ruleCode, 'version' => $expected + 1];
    }

    /**
     * Забирает ожидаемую версию. Повторное отсутствие — лишняя не проверяется здесь.
     *
     * @param array<int|string, int> $map Карта.
     * @param int|string $id Ключ.
     *
     * @return int Версия.
     *
     * @throws GameInvalidException Если ключа нет.
     */
    private function pullVersion(array &$map, int|string $id): int
    {
        if (!array_key_exists($id, $map)) {
            throw new GameInvalidException('Economy expected version is missing');
        }

        $version = $map[$id];
        unset($map[$id]);

        return $version;
    }

    /**
     * Лишние ожидаемые версии.
     *
     * @return void
     *
     * @throws GameInvalidException Если остались.
     */
    private function assertVersionsUsed(): void
    {
        if ($this->characterVersions !== [] || $this->npcVersions !== [] || $this->shopVersions !== []) {
            throw new GameInvalidException('Economy expected version is unused');
        }
    }

    /**
     * Абсолютные операции порта.
     *
     * @param array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>} $holder Итог.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function operationsOf(array $holder): array
    {
        $operations = [];
        if ($holder['money'] !== $holder['baseMoney']) {
            $operations[] = ['kind' => 'setMoney', 'amount' => $holder['money']];
        }

        foreach ($holder['lines'] as $ruleCode => $quantity) {
            $before = $holder['baseLines'][$ruleCode] ?? null;
            if ($before === $quantity) {
                continue;
            }

            $operations[] = [
                'kind' => 'putInventoryQuantity',
                'ruleCode' => $ruleCode,
                'quantity' => $quantity,
            ];
        }

        return $operations;
    }

    /**
     * Лишний ключ части или конца.
     *
     * @param array $part Объект.
     * @param list<string> $allowed Допустимые ключи.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function assertKeys(array $part, array $allowed): void
    {
        foreach ($part as $key => $unused) {
            if (!in_array($key, $allowed, true)) {
                throw new GameInvalidException('Economy part key is invalid');
            }
        }
    }

    /**
     * Ключи конца передачи или loot.
     *
     * @param mixed $endpoint Объект.
     * @param bool $allowNowhere true для loot.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function assertEndpointKeys(mixed $endpoint, bool $allowNowhere): void
    {
        if (!is_array($endpoint)) {
            return;
        }

        $nowhere = $allowNowhere && ($endpoint['type'] ?? null) === 'nowhere';
        $this->assertKeys($endpoint, $nowhere ? ['type'] : ['type', 'id']);
    }

    /**
     * Ключи доли денег. У nowhere нет id.
     *
     * @param array $share Доля.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function assertShareKeys(array $share): void
    {
        $nowhere = ($share['type'] ?? null) === 'nowhere';
        $this->assertKeys($share, $nowhere ? ['type', 'amount'] : ['type', 'id', 'amount']);
    }

    /**
     * Положительное целое поле.
     *
     * @param array $part Часть.
     * @param string $key Поле.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если не положительное.
     */
    private function requirePositive(array $part, string $key): int
    {
        $value = $part[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new GameInvalidException('Economy amount is invalid');
        }

        return $value;
    }

    /**
     * Целый id.
     *
     * @param array $part Часть.
     * @param string $key Поле.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если не int.
     */
    private function requireInt(array $part, string $key): int
    {
        $value = $part[$key] ?? null;
        if (!is_int($value)) {
            throw new GameInvalidException('Economy id is invalid');
        }

        return $value;
    }

    /**
     * Непустой код.
     *
     * @param array $part Часть.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если пусто.
     */
    private function requireCode(array $part): string
    {
        $ruleCode = $part['ruleCode'] ?? null;
        if (!is_string($ruleCode) || $ruleCode === '') {
            throw new GameInvalidException('Economy rule code is invalid');
        }

        return $ruleCode;
    }

    /**
     * Произведение двух неотрицательных int.
     *
     * @param int $price Цена.
     * @param int $quantity Количество.
     *
     * @return int Сумма.
     *
     * @throws GameInvalidException Если не помещается в int.
     */
    private function product(int $price, int $quantity): int
    {
        if ($quantity !== 0 && $price > intdiv(PHP_INT_MAX, $quantity)) {
            throw new GameInvalidException('Economy total is too large');
        }

        return $price * $quantity;
    }

    /**
     * Сумма без переполнения int.
     *
     * @param int $left База.
     * @param int $right Дельта.
     *
     * @return int Сумма.
     *
     * @throws GameInvalidException Если не помещается.
     */
    private function sum(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new GameInvalidException('Economy total is too large');
        }

        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            throw new GameInvalidException('Economy total is too large');
        }

        return $left + $right;
    }
}
