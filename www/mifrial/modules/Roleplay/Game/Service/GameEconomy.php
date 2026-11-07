<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameEconomy;
use Mifrial\Roleplay\Game\Repository\GameEconomyOperationRepository;

/**
 * Магазин и проведение EconomyOperation в одной транзакции шлюза.
 */
final class GameEconomy implements IGameEconomy
{
    private readonly GameEconomyOperationRepository $operations;

    private readonly GameDeliverySignal $deliverySignal;

    private readonly GameDeliveryKeys $deliveryKeys;

    private readonly GameShopCatalog $shop;

    /**
     * Создаёт фасад.
     *
     * @param IEventManager $events Сигнал доставки.
     * @param GameEconomyAccess $access Видимость и права.
     * @param GameEconomyPorts $ports Порты экономики.
     *
     * @return void
     */
    public function __construct(
        IEventManager $events,
        private readonly GameEconomyAccess $access,
        private readonly GameEconomyPorts $ports,
    ) {
        $this->operations = new GameEconomyOperationRepository($ports->gateway);
        $this->deliverySignal = new GameDeliverySignal($events);
        $this->deliveryKeys = new GameDeliveryKeys();
        $this->shop = new GameShopCatalog($ports, $access);
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

        $game = $this->access->visibleGame($gameId, $actorUserId, $viewAll);
        $replay = $this->replayed($gameId, $idempotencyKey, $parts, $expectedVersions);
        if ($replay !== null) {
            return $replay;
        }

        $this->access->assertOpen($game);

        return $this->stored($game, $actorUserId, $editAll, $viewAll, $idempotencyKey, $parts, $expectedVersions);
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
        return $this->shop->positions($gameId, $actorUserId, $viewAll);
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
        return $this->shop->replace($gameId, $actorUserId, $editAll, $viewAll, $positions, $expectedPositions);
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
     * @throws GameInvalidException Если тело не кодируется.
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
     * Итог прежнего ключа или null.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array $parts Части.
     * @param array $expectedVersions Версии.
     *
     * @return array<string, mixed>|null Итог.
     *
     * @throws GameEconomyConflictException Если тело другое.
     * @throws GameInvalidException Если тело не кодируется.
     */
    private function replayed(int $gameId, string $idempotencyKey, array $parts, array $expectedVersions): ?array
    {
        $replay = $this->operations->findByKey($gameId, $idempotencyKey);
        if ($replay === null) {
            return null;
        }

        return $this->replay(
            $replay->getId(),
            $idempotencyKey,
            $replay->getBody(),
            $replay->getResult(),
            $parts,
            $expectedVersions,
        );
    }

    /**
     * Проводит и сообщает о доставке.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array $parts Части.
     * @param array $expectedVersions Версии.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameEconomyConflictException Если версия.
     * @throws GameInvalidException Если часть или баланс.
     * @throws GameNotFoundException Если карточка или NPC скрыты.
     */
    private function stored(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $parts,
        array $expectedVersions,
    ): array {
        $stored = $this->store($game, $actorUserId, $editAll, $viewAll, $idempotencyKey, $parts, $expectedVersions);
        $this->deliverySignal->recorded(
            $game->getId(),
            'economy',
            $stored['operationId'],
            $this->deliveryKeys->fromEconomy($stored['versions']),
        );

        return $stored;
    }

    /**
     * Транзакция прохода с переводом конфликтов.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array $parts Части.
     * @param array $expectedVersions Версии.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameEconomyConflictException Если версия.
     * @throws GameInvalidException Если часть или баланс.
     * @throws GameNotFoundException Если карточка или NPC скрыты.
     */
    private function store(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $parts,
        array $expectedVersions,
    ): array {
        try {
            return $this->transaction($game, $actorUserId, $editAll, $viewAll, $idempotencyKey, $parts, $expectedVersions);
        } catch (GameEconomyConflictException $exception) {
            throw $this->economyConflict($exception);
        }
    }

    /**
     * Пишет листы и строку ключа в одной транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array $parts Части.
     * @param array $expectedVersions Версии.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameEconomyConflictException Если версия.
     * @throws GameInvalidException Если часть или баланс.
     * @throws GameNotFoundException Если карточка или NPC скрыты.
     */
    private function transaction(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $parts,
        array $expectedVersions,
    ): array {
        return $this->ports->gateway->transaction(function () use ($game, $actorUserId, $editAll, $viewAll, $idempotencyKey, $parts, $expectedVersions): array {
            $versions = (new GameEconomyApply($this->ports))->run($game, $actorUserId, $editAll, $viewAll, $parts, $expectedVersions);
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
    }

    /**
     * Конфликт экономики как есть.
     *
     * @param GameEconomyConflictException $exception Исходный конфликт.
     *
     * @return GameEconomyConflictException Конфликт Game.
     */
    private function economyConflict(GameEconomyConflictException $exception): GameEconomyConflictException
    {
        return new GameEconomyConflictException(
            $exception->getCurrentVersion(),
            $exception->getMessage(),
            $exception,
            $exception->getCurrentSheet(),
        );
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
}
