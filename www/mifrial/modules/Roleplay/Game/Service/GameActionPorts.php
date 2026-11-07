<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Собранные порты команды действия: параметры конструктора — порты и шлюз.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Порты команды не-атакующего действия.
 */
final class GameActionPorts
{
    public readonly GameBattleRepository $battles;

    public readonly GameActionRepository $actions;

    public readonly GameCommandRepository $commands;

    public readonly GameSessionRepository $sessions;

    public readonly GameStrikeAccess $access;

    public readonly GameReplayTransaction $replay;

    public readonly GameDeliverySignal $delivery;

    /**
     * Создаёт порты.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameActionPerform $perform Общий сценарий действия.
     * @param GamePreparedDefense $prepared Подготовка.
     * @param GameActionChat $chat Сообщение чата.
     * @param GameStrikeSheets $sheets Проекция конфликта листа.
     * @param IEventManager $events События доставки.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeRules $rules Срез.
     *
     * @return void
     */
    public function __construct(
        public readonly ISmartTableGateway $gateway,
        public readonly IGames $games,
        public readonly GameCardAccess $cardAccess,
        public readonly GameActionPerform $perform,
        public readonly GamePreparedDefense $prepared,
        public readonly GameActionChat $chat,
        public readonly GameStrikeSheets $sheets,
        IEventManager $events,
        public readonly GameProcessFlow $processes,
        public readonly GameStrikeRules $rules,
    ) {
        $this->battles = new GameBattleRepository($gateway);
        $this->actions = new GameActionRepository($gateway);
        $this->commands = new GameCommandRepository($gateway);
        $this->sessions = new GameSessionRepository($gateway);
        $characters = new GameCharacterRepository($gateway);
        $members = new GameMemberRepository($gateway);
        $this->access = new GameStrikeAccess($members, $characters, $this->battles, $cardAccess);
        $this->replay = new GameReplayTransaction($gateway);
        $this->delivery = new GameDeliverySignal($events);
    }
}
