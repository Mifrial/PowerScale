<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * Общие сервисы ударного домена.
 */
final class GameStrikeShared
{
    public readonly GameStrikeRepositories $repos;

    public readonly GameStrikeAccess $access;

    public readonly GameStrikeSheets $sheets;

    public readonly GameStrikeSheetWrites $sheetWrites;

    public readonly GameStrikeChat $chat;

    public readonly GameStrikeSpend $spend;

    public readonly GameReplayTransaction $replay;

    public readonly GameStrikeAnnounce $announce;

    public readonly GameStrikeReady $ready;

    /**
     * Создаёт общие сервисы удара.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param IGames $games Строка игры.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameStrikeRules $rules Правила.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param IEventManager $events Доставка.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $gateway,
        IGames $games,
        ICharacterActualMutations $mutations,
        GameStrikeRules $rules,
        GameCardAccess $cardAccess,
        IEventManager $events,
    ) {
        $this->repos = new GameStrikeRepositories($gateway);
        $this->access = $this->access($cardAccess);
        $this->sheets = $this->sheets($rules);
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->repos->npcs);
        $this->chat = new GameStrikeChat($this->repos->npcs, $rules);
        $this->spend = new GameStrikeSpend($rules, $this->sheetWrites);
        $this->replay = new GameReplayTransaction($gateway);
        $this->announce = $this->announce($events);
        $this->ready = new GameStrikeReady(
            $games,
            $this->access,
            $this->repos->sessions,
            $this->repos->commands,
            $this->replay,
        );
    }

    /**
     * Права ударного домена.
     *
     * @param GameCardAccess $cardAccess Фильтр карточки.
     *
     * @return GameStrikeAccess Права.
     */
    private function access(GameCardAccess $cardAccess): GameStrikeAccess
    {
        return new GameStrikeAccess(
            $this->repos->members,
            $this->repos->characters,
            $this->repos->battles,
            $cardAccess,
        );
    }

    /**
     * Снимки листов удара.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameStrikeSheets Снимки.
     */
    private function sheets(GameStrikeRules $rules): GameStrikeSheets
    {
        return new GameStrikeSheets(
            $rules,
            $this->repos->npcs,
            $this->repos->characters,
            $this->repos->members,
            new ConflictSheetProjection(
                new CharacterSectionMask(),
                new GameCharacterProjectionMask(),
                new GameNpcVisibility(),
            ),
        );
    }

    /**
     * Сигнал доставки команды.
     *
     * @param IEventManager $events Доставка.
     *
     * @return GameStrikeAnnounce Сигнал.
     */
    private function announce(IEventManager $events): GameStrikeAnnounce
    {
        return new GameStrikeAnnounce(
            $this->repos->commands,
            new GameDeliverySignal($events),
        );
    }
}
