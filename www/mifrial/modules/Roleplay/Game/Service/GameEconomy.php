<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameShopPositionRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameEconomy;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameEconomyOperationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameShopPositionRepository;

/**
 * Магазин и проведение EconomyOperation в одной транзакции шлюза.
 */
final class GameEconomy implements IGameEconomy
{
    private readonly GameShopPositionRepository $shopPositions;

    private readonly GameEconomyOperationRepository $operations;

    private readonly GameNpcRepository $npcs;

    private readonly GameDeliverySignal $deliverySignal;

    private readonly GameDeliveryKeys $deliveryKeys;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param IGameMemberships $memberships Персонажи игры.
     * @param GameMemberRepository $members Люди игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param ICharacters $characters Строки персонажа.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     * @param IEventManager $events Сигнал доставки.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly IGameMemberships $memberships,
        private readonly GameMemberRepository $members,
        private readonly GameCardAccess $cardAccess,
        private readonly ICharacterActualMutations $mutations,
        private readonly ICharacters $characters,
        private readonly ConflictSheetProjection $conflictProjection,
        IEventManager $events,
    ) {
        $this->shopPositions = new GameShopPositionRepository($smartTableGateway);
        $this->operations = new GameEconomyOperationRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->deliverySignal = new GameDeliverySignal($events);
        $this->deliveryKeys = new GameDeliveryKeys();
    }

    /**
     * Проводит части или возвращает итог того же ключа.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array $parts Части.
     * @param array $expectedVersions Версии.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameEconomyConflictException Если версия или чужое тело ключа.
     * @throws GameInvalidException Если часть или баланс.
     * @throws GameNotFoundException Если карточка или NPC скрыты.
     */
    public function apply(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $parts,
        array $expectedVersions,
    ): array {
        if ($idempotencyKey === '' || $parts === []) {
            throw new GameInvalidException('Economy operation is empty');
        }

        $game = $this->visibleGame($gameId, $actorUserId, $viewAll);
        $replay = $this->operations->findByKey($gameId, $idempotencyKey);
        if ($replay !== null) {
            return $this->replay(
                $replay->getId(),
                $idempotencyKey,
                $replay->getBody(),
                $replay->getResult(),
                $parts,
                $expectedVersions,
            );
        }

        $this->assertOpen($game);

        try {
            $stored = $this->smartTableGateway->transaction(function () use (
                $game,
                $actorUserId,
                $editAll,
                $viewAll,
                $idempotencyKey,
                $parts,
                $expectedVersions,
            ): array {
                $versions = (new GameEconomyApply(
                    $this->mutations,
                    $this->characters,
                    $this->memberships,
                    $this->members,
                    $this->npcs,
                    $this->shopPositions,
                    $this->conflictProjection,
                ))->run($game, $actorUserId, $editAll, $viewAll, $parts, $expectedVersions);
                $operation = $this->operations->add($game->getId(), $idempotencyKey, [
                    'parts' => $parts,
                    'expectedVersions' => $expectedVersions,
                ], $versions);

                return [
                    'operationId' => $operation->getId(),
                    'idempotencyKey' => $idempotencyKey,
                    'versions' => $versions,
                ];
            });
        } catch (CharacterConflictException $exception) {
            throw new GameEconomyConflictException(
                $exception->getCurrentVersion(),
                'Game economy character version conflict',
                $exception,
                $exception->getCurrentSheet(),
            );
        } catch (GameEconomyConflictException $exception) {
            throw new GameEconomyConflictException(
                $exception->getCurrentVersion(),
                $exception->getMessage(),
                $exception,
                $exception->getCurrentSheet(),
            );
        }
        $this->deliverySignal->recorded(
            $game->getId(),
            'economy',
            $stored['operationId'],
            $this->deliveryKeys->fromEconomy($stored['versions']),
        );

        return $stored;
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
    public function getShop(int $gameId, int $actorUserId, bool $viewAll): array
    {
        $this->visibleGame($gameId, $actorUserId, $viewAll);

        return ['positions' => $this->positionJsonList($this->shopPositions->getListByGame($gameId))];
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
    public function replaceShop(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $positions,
        array $expectedPositions,
    ): array {
        $game = $this->visibleGame($gameId, $actorUserId, $viewAll);
        $this->assertOpen($game);
        $this->assertWriter($game, $actorUserId, $editAll);

        return $this->smartTableGateway->transaction(function () use ($gameId, $positions, $expectedPositions): array {
            $this->replaceRows($gameId, $positions, $expectedPositions);

            return ['positions' => $this->positionJsonList($this->shopPositions->getListByGame($gameId))];
        });
    }

    /**
     * Повтор ключа с тем же телом.
     *
     * @param int $operationId Строка.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $storedBody Тело в базе.
     * @param array<string, mixed> $storedResult Итог в базе.
     * @param array $parts Новые части.
     * @param array $expectedVersions Новые версии.
     *
     * @return array<string, mixed> Прежний итог.
     *
     * @throws GameEconomyConflictException Если тело другое.
     */
    private function replay(
        int $operationId,
        string $idempotencyKey,
        array $storedBody,
        array $storedResult,
        array $parts,
        array $expectedVersions,
    ): array {
        $fresh = ['parts' => $parts, 'expectedVersions' => $expectedVersions];
        if ($this->canonical($storedBody) !== $this->canonical($fresh)) {
            throw new GameEconomyConflictException(null);
        }

        return [
            'operationId' => $operationId,
            'idempotencyKey' => $idempotencyKey,
            'versions' => $storedResult,
        ];
    }

    /**
     * Стабильный JSON для сравнения тела.
     *
     * @param mixed $value Значение.
     *
     * @return string JSON.
     *
     * @throws GameInvalidException Если значение не кодируется.
     */
    private function canonical(mixed $value): string
    {
        try {
            $encoded = json_encode($this->sortKeys($value), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Economy body is invalid', $exception);
        }

        return $encoded;
    }

    /**
     * Сортирует ключи объектов.
     *
     * @param mixed $value Значение.
     *
     * @return mixed Значение.
     */
    private function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $sorted = [];
            foreach ($value as $item) {
                $sorted[] = $this->sortKeys($item);
            }

            return $sorted;
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortKeys($item);
        }

        return $value;
    }

    /**
     * Видимая игра.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     *
     * @return GameRecord Строка.
     *
     * @throws GameNotFoundException Если скрыта.
     */
    private function visibleGame(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        return $game;
    }

    /**
     * completed не пишет.
     *
     * @param GameRecord $game Игра.
     *
     * @return void
     *
     * @throws GameInvalidException Если завершена.
     */
    private function assertOpen(GameRecord $game): void
    {
        if ($game->getStatus() === 'completed') {
            throw new GameInvalidException('Game is completed');
        }
    }

    /**
     * Владелец, gm или edit_all.
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
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Роль gm.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool Да, если gm.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            $member = $this->members->getByPair($gameId, $actorUserId);
        } catch (GameNotFoundException) {
            return false;
        }

        return $member->getRole() === 'gm';
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
        $current = $this->shopPositions->getListByGame($gameId);
        $this->assertExpectedPositions($current, $expectedPositions);
        $incoming = $this->incomingPositions($positions);
        foreach ($current as $row) {
            $this->keepOrDrop($row, $incoming);
        }

        foreach ($incoming as $code => $position) {
            if ($this->findPosition($current, $code) === null) {
                $this->shopPositions->add(
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
            $this->shopPositions->delete($row->getId());

            return;
        }

        $next = $incoming[$row->getRuleCode()];
        $this->shopPositions->save($row, $next['buyPrice'], $next['sellPrice'], $next['quantity']);
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
