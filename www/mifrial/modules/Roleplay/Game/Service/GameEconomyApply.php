<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Один проход операции внутри уже открытой транзакции.
 */
final class GameEconomyApply
{
    /**
     * Создаёт проход.
     *
     * @param GameEconomyPorts $ports Порты экономики.
     *
     * @return void
     */
    public function __construct(private readonly GameEconomyPorts $ports)
    {
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
        $context = new GameEconomyContext($game, $actorUserId, $editAll, $viewAll);
        $actor = new GameEconomyActor($this->ports, $context);
        $ledger = new GameEconomyLedger($this->ports, $actor);
        $sheets = new GameEconomySheets($this->ports, new GameEconomyConflict($this->ports, $context, $actor));
        $sheets->readExpected($expectedVersions);
        $ledger->loadShop($this->ports->shopPositions->getListByGame($game->getId()));

        $partsApplier = new GameEconomyParts($actor, $ledger);
        foreach ($parts as $part) {
            $partsApplier->apply($part);
        }

        return $sheets->write($ledger);
    }
}
