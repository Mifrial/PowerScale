<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameShopPositionRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Магазин игры: чтение набора и полная замена по версиям.
 */
final class GameShopCatalog
{
    /**
     * Создаёт каталог.
     *
     * @param GameEconomyPorts $ports Порты.
     * @param GameEconomyAccess $access Видимость и права.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyPorts $ports,
        private readonly GameEconomyAccess $access,
    ) {
    }

    /**
     * Отдаёт позиции.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return array<string, mixed> Список.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если строка битая.
     */
    public function positions(int $gameId, int $actorUserId, bool $viewAll): array
    {
        $this->access->visibleGame($gameId, $actorUserId, $viewAll);

        return ['positions' => $this->positionJsonList($this->ports->shopPositions->getListByGame($gameId))];
    }

    /**
     * Заменяет набор, если версии всех текущих позиций совпали.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param array $positions Будущий набор.
     * @param array $expectedPositions Текущие пары.
     *
     * @return array<string, mixed> Набор.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameEconomyConflictException Если набор версий другой.
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function replace(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $positions,
        array $expectedPositions,
    ): array {
        $game = $this->access->visibleGame($gameId, $actorUserId, $viewAll);
        $this->access->assertOpen($game);
        $this->access->assertWriter($game, $actorUserId, $editAll);

        return $this->ports->gateway->transaction(function () use ($gameId, $positions, $expectedPositions): array {
            $this->replaceRows($gameId, $positions, $expectedPositions);

            return ['positions' => $this->positionJsonList($this->ports->shopPositions->getListByGame($gameId))];
        });
    }

    /**
     * Сверяет ожидаемые пары и пишет набор.
     *
     * @param int $gameId Игра.
     * @param array $positions Будущие строки.
     * @param array $expectedPositions Текущие пары.
     *
     * @return void
     *
     * @throws GameEconomyConflictException Если пары другие.
     * @throws GameInvalidException Если поле.
     */
    private function replaceRows(int $gameId, array $positions, array $expectedPositions): void
    {
        $current = $this->ports->shopPositions->getListByGame($gameId);
        $this->assertExpectedPositions($current, $expectedPositions);
        $incoming = $this->incomingPositions($positions);
        foreach ($current as $row) {
            $this->keepOrDrop($row, $incoming);
        }

        foreach ($incoming as $code => $position) {
            if ($this->findPosition($current, $code) === null) {
                $this->ports->shopPositions->add(
                    $gameId,
                    $code,
                    $position['buyPrice'],
                    $position['sellPrice'],
                    $position['quantity'],
                );
            }
        }
    }

    /**
     * Набор пар совпал с базой.
     *
     * @param list<GameShopPositionRecord> $current Строки.
     * @param array $expectedPositions Пары клиента.
     *
     * @return void
     *
     * @throws GameEconomyConflictException Если набор другой.
     * @throws GameInvalidException Если пара битая.
     */
    private function assertExpectedPositions(array $current, array $expectedPositions): void
    {
        $expected = [];
        foreach ($expectedPositions as $pair) {
            if (!is_array($pair) || !is_string($pair['ruleCode'] ?? null) || !is_int($pair['version'] ?? null)) {
                throw new GameInvalidException('Shop expected position is invalid');
            }

            $expected[$pair['ruleCode']] = $pair['version'];
        }

        $actual = [];
        foreach ($current as $row) {
            $actual[$row->getRuleCode()] = $row->getVersion();
        }

        ksort($expected);
        ksort($actual);
        if ($expected !== $actual) {
            throw new GameEconomyConflictException(null);
        }
    }

    /**
     * Разбирает будущий набор.
     *
     * @param array $positions Тело.
     *
     * @return array<string, array{buyPrice: int, sellPrice: int|null, quantity: int}> Позиции.
     *
     * @throws GameInvalidException Если поле.
     */
    private function incomingPositions(array $positions): array
    {
        $incoming = [];
        foreach ($positions as $position) {
            $parsed = $this->onePosition($position);
            if (array_key_exists($parsed['ruleCode'], $incoming)) {
                throw new GameInvalidException('Shop rule code is duplicated');
            }

            $incoming[$parsed['ruleCode']] = $parsed;
        }

        return $incoming;
    }

    /**
     * Одна позиция тела.
     *
     * @param mixed $position Объект.
     *
     * @return array{ruleCode: string, buyPrice: int, sellPrice: int|null, quantity: int} Поля.
     *
     * @throws GameInvalidException Если поле.
     */
    private function onePosition(mixed $position): array
    {
        if (!is_array($position)) {
            throw new GameInvalidException('Shop position is invalid');
        }

        $sellPrice = $position['sellPrice'] ?? null;
        $valid = is_string($position['ruleCode'] ?? null)
            && $position['ruleCode'] !== ''
            && is_int($position['buyPrice'] ?? null)
            && $position['buyPrice'] >= 0
            && is_int($position['quantity'] ?? null)
            && $position['quantity'] >= 0
            && ($sellPrice === null || (is_int($sellPrice) && $sellPrice >= 0));
        if (!$valid) {
            throw new GameInvalidException('Shop position is invalid');
        }

        return [
            'ruleCode' => $position['ruleCode'],
            'buyPrice' => $position['buyPrice'],
            'sellPrice' => $sellPrice,
            'quantity' => $position['quantity'],
        ];
    }

    /**
     * Обновляет оставшуюся позицию или удаляет отсутствующую.
     *
     * @param GameShopPositionRecord $row Строка.
     * @param array<string, array{buyPrice: int, sellPrice: int|null, quantity: int}> $incoming Набор.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если строка исчезла.
     */
    private function keepOrDrop(GameShopPositionRecord $row, array $incoming): void
    {
        if (!array_key_exists($row->getRuleCode(), $incoming)) {
            $this->ports->shopPositions->delete($row->getId());

            return;
        }

        $next = $incoming[$row->getRuleCode()];
        $this->ports->shopPositions->save($row, $next['buyPrice'], $next['sellPrice'], $next['quantity']);
    }

    /**
     * Ищет код в текущем наборе.
     *
     * @param list<GameShopPositionRecord> $current Строки.
     * @param string $ruleCode Код.
     *
     * @return GameShopPositionRecord|null Строка.
     */
    private function findPosition(array $current, string $ruleCode): ?GameShopPositionRecord
    {
        foreach ($current as $row) {
            if ($row->getRuleCode() === $ruleCode) {
                return $row;
            }
        }

        return null;
    }

    /**
     * JSON позиций.
     *
     * @param list<GameShopPositionRecord> $rows Строки.
     *
     * @return list<array<string, mixed>> Позиции.
     */
    private function positionJsonList(array $rows): array
    {
        $json = [];
        foreach ($rows as $row) {
            $json[] = [
                'ruleCode' => $row->getRuleCode(),
                'buyPrice' => $row->getBuyPrice(),
                'sellPrice' => $row->getSellPrice(),
                'quantity' => $row->getQuantity(),
                'version' => $row->getVersion(),
            ];
        }

        return $json;
    }
}
