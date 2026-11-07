<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Разбор части экономики и её проводка по копилке.
 */
final class GameEconomyParts
{
    private const STOCK_KINDS = ['buy', 'sell', 'discard'];

    private const TRANSFER_KINDS = ['transfer_item', 'transfer_money'];

    /**
     * Создаёт разбор.
     *
     * @param GameEconomyActor $actor Права актора.
     * @param GameEconomyLedger $ledger Копилка прохода.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyActor $actor,
        private readonly GameEconomyLedger $ledger,
    ) {
    }

    /**
     * Одна часть.
     *
     * @param mixed $part Объект.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если вид или дельта.
     * @throws GameNotFoundException Если цели нет.
     */
    public function apply(mixed $part): void
    {
        if (!is_array($part) || !is_string($part['kind'] ?? null)) {
            throw new GameInvalidException('Economy part is invalid');
        }

        $kind = $part['kind'];
        if (in_array($kind, self::STOCK_KINDS, true)) {
            $this->applyStock($kind, $part);

            return;
        }

        if (in_array($kind, self::TRANSFER_KINDS, true)) {
            $this->applyTransfer($kind, $part);

            return;
        }

        if ($kind === 'loot') {
            $this->applyLoot($part);

            return;
        }

        throw new GameInvalidException('Economy part kind is invalid');
    }

    /**
     * buy, sell или discard одного персонажа.
     *
     * @param string $kind Вид.
     * @param array $part Часть.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если числа или остаток.
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function applyStock(string $kind, array $part): void
    {
        $this->assertKeys($part, ['kind', 'characterId', 'ruleCode', 'quantity']);
        $characterId = $this->requireInt($part, 'characterId');
        $ruleCode = $this->requireCode($part);
        $quantity = $this->requirePositive($part, 'quantity');
        $this->actor->assertCharacterActor($characterId);
        $this->ledger->addItem('character', $characterId, $ruleCode, $kind === 'buy' ? $quantity : -$quantity);
        if ($kind === 'discard') {
            return;
        }

        $position = $this->ledger->requireShop($ruleCode);
        $unit = $kind === 'buy' ? $position->getBuyPrice() : $this->ledger->requireSellPrice($position);
        $signed = $kind === 'buy' ? -1 : 1;
        $this->ledger->addMoney('character', $characterId, GameEconomyMath::product($unit, $quantity) * $signed);
        $this->ledger->addShop($ruleCode, $kind === 'buy' ? -$quantity : $quantity);
    }

    /**
     * Передача предмета или денег.
     *
     * @param string $kind Вид.
     * @param array $part Часть.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если концы совпали.
     * @throws GameNotFoundException Если конца нет.
     */
    private function applyTransfer(string $kind, array $part): void
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

        $this->actor->assertEndpointActor($from);
        $this->actor->assertEndpointKnown($to);
        if ($kind === 'transfer_money') {
            $amount = $this->requirePositive($part, 'amount');
            $this->ledger->addMoney($from[0], $from[1], -$amount);
            $this->ledger->addMoney($to[0], $to[1], $amount);

            return;
        }

        $ruleCode = $this->requireCode($part);
        $quantity = $this->requirePositive($part, 'quantity');
        $this->ledger->addItem($from[0], $from[1], $ruleCode, -$quantity);
        $this->ledger->addItem($to[0], $to[1], $ruleCode, $quantity);
    }

    /**
     * Выдача предмета или долей денег.
     *
     * @param array $part Часть.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если оба набора полей.
     * @throws GameNotFoundException Если получателя нет.
     */
    private function applyLoot(array $part): void
    {
        $this->actor->assertWriter();
        $hasItem = array_key_exists('ruleCode', $part) || array_key_exists('to', $part);
        $hasShares = array_key_exists('shares', $part);
        if ($hasItem === $hasShares) {
            throw new GameInvalidException('Economy loot mixes item and money');
        }

        if ($hasShares) {
            $this->assertKeys($part, ['kind', 'shares']);
            $this->applyShares($part['shares']);

            return;
        }

        $this->assertKeys($part, ['kind', 'ruleCode', 'quantity', 'to']);
        $this->assertEndpointKeys($part['to'] ?? null, true);
        $to = $this->endpoint($part['to'] ?? null, true);
        $this->ledger->addItem($to[0], $to[1], $this->requireCode($part), $this->requirePositive($part, 'quantity'));
    }

    /**
     * Доли денег loot.
     *
     * @param mixed $shares Список.
     *
     * @return void
     *
     * @throws GameInvalidException Если доля битая.
     * @throws GameNotFoundException Если получателя нет.
     */
    private function applyShares(mixed $shares): void
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
            $this->ledger->addMoney($to[0], $to[1], $this->requirePositive($share, 'amount'));
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
}
