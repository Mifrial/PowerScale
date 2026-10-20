# Roleplay/Game

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Game --zone=tr`.

- **Путь:** `modules/Roleplay/Game`
- **Неймспейс:** `Mifrial\Roleplay\Game`
- **Контейнер:** `Mifrial\Roleplay\Game\Container\GameContainer`
- **Локатор:** `Mifrial\Roleplay\Game\Interface\Container\IGameContainer`
- **Setup:** `Closure(IServiceLocator)`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Game\Interface\Service\IGames`
- `Mifrial\Roleplay\Game\Interface\Service\IGameMemberships`
- `Mifrial\Roleplay\Game\Interface\Service\IGameAdmissions`
- `Mifrial\Roleplay\Game\Interface\Service\IGameChronicles`
- `Mifrial\Roleplay\Game\Interface\Service\IGameEconomy`
- `Mifrial\Roleplay\Game\Interface\Service\IGameBattles`
- `Mifrial\Roleplay\Game\Interface\Service\IGameProcesses`
- `Mifrial\Roleplay\Game\Interface\Service\IGameChecks`
- `Mifrial\Roleplay\Game\Interface\Service\IGameStrikes`
- `Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes`
- `Mifrial\Roleplay\Game\Interface\Service\IGameProjections`
- `Mifrial\Roleplay\Game\Action\GetGameRosterAction`
- `Mifrial\Roleplay\Game\Action\GetGameSheetsAction`
- `Mifrial\Roleplay\Game\Action\GetGameBattleSheetsAction`
- `Mifrial\Roleplay\Game\Action\DeclareGameCheckAction`
- `Mifrial\Roleplay\Game\Action\ProposeGameCheckAction`
- `Mifrial\Roleplay\Game\Action\AnswerGameCheckAction`
- `Mifrial\Roleplay\Game\Action\GameWoundAction`
- `Mifrial\Roleplay\Game\Action\GameManualSheetAction`
- `Mifrial\Roleplay\Game\Action\EndGameTurnAction`
- `Mifrial\Roleplay\Game\Action\ContinueGameAction`
- `Mifrial\Roleplay\Game\Action\AbortGameAction`
- `Mifrial\Roleplay\Game\Action\DeclareGameStrikeAction`
- `Mifrial\Roleplay\Game\Action\ResolveGameStrikeAction`
- `Mifrial\Roleplay\Game\Action\DeclareGameWideStrikeAction`
- `Mifrial\Roleplay\Game\Action\ResolveGameWideStrikeAction`
- `Mifrial\Roleplay\Game\Action\StartGameBattleAction`
- `Mifrial\Roleplay\Game\Action\SetGameBattleRosterAction`
- `Mifrial\Roleplay\Game\Action\EndGameBattleAction`
- `Mifrial\Roleplay\Game\Action\RollGameInitiativeAction`
- `Mifrial\Roleplay\Game\Action\ApplyGameEconomyAction`
- `Mifrial\Roleplay\Game\Action\GetGameShopAction`
- `Mifrial\Roleplay\Game\Action\ReplaceGameShopAction`
- `Mifrial\Roleplay\Game\Action\GetGameChronicleAction`
- `Mifrial\Roleplay\Game\Action\GetGameChronicleEntriesAction`
- `Mifrial\Roleplay\Game\Action\CreateGameChronicleEntryAction`
- `Mifrial\Roleplay\Game\Action\UpdateGameChronicleEntryAction`
- `Mifrial\Roleplay\Game\Action\DeleteGameChronicleEntryAction`
- `Mifrial\Roleplay\Game\Action\InviteGameAction`
- `Mifrial\Roleplay\Game\Action\GetGameInvitationsAction`
- `Mifrial\Roleplay\Game\Action\GetMyGameInvitationsAction`
- `Mifrial\Roleplay\Game\Action\RespondGameInvitationAction`
- `Mifrial\Roleplay\Game\Action\RequestGameJoinAction`
- `Mifrial\Roleplay\Game\Action\GetGameJoinRequestsAction`
- `Mifrial\Roleplay\Game\Action\RespondGameJoinRequestAction`
- `Mifrial\Roleplay\Game\Action\GetGameWhitelistAction`
- `Mifrial\Roleplay\Game\Action\SetGameWhitelistAction`
- `Mifrial\Roleplay\Game\Action\JoinGameWhitelistAction`
- `Mifrial\Roleplay\Game\Action\CreateGameAction`
- `Mifrial\Roleplay\Game\Action\GetGameAction`
- `Mifrial\Roleplay\Game\Action\GetGameListAction`
- `Mifrial\Roleplay\Game\Action\UpdateGameAction`
- `Mifrial\Roleplay\Game\Action\AddGameMemberAction`
- `Mifrial\Roleplay\Game\Action\UpdateGameMemberAction`
- `Mifrial\Roleplay\Game\Action\RemoveGameMemberAction`
- `Mifrial\Roleplay\Game\Action\GetGameMemberListAction`
- `Mifrial\Roleplay\Game\Action\SubmitGameCharacterAction`
- `Mifrial\Roleplay\Game\Action\ApproveGameCharacterAction`
- `Mifrial\Roleplay\Game\Action\ReturnGameCharacterAction`
- `Mifrial\Roleplay\Game\Action\RejectGameCharacterAction`
- `Mifrial\Roleplay\Game\Action\LeaveGameCharacterAction`
- `Mifrial\Roleplay\Game\Action\SetGameCharacterBonusAction`
- `Mifrial\Roleplay\Game\Action\SetGameCharacterSectionVisibilityAction`
- `Mifrial\Roleplay\Game\Action\GetGameCharacterAction`
- `Mifrial\Roleplay\Game\Action\GetGameCharacterListAction`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants`
- `Mifrial\Roleplay\Character\Action\MigrateCharacterAction`
- `Mifrial\Roleplay\Game\Action\StartGameSessionAction`
- `Mifrial\Roleplay\Game\Action\StopGameSessionAction`
- `Mifrial\Roleplay\Game\Action\CreateGameNpcAction`
- `Mifrial\Roleplay\Game\Action\GetGameNpcAction`
- `Mifrial\Roleplay\Game\Action\UpdateGameNpcAction`
- `Mifrial\Roleplay\Game\Action\TranslateGameNpcAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `game.create` | `CreateGameAction` | да |
| `game.get` | `GetGameAction` | да |
| `game.getList` | `GetGameListAction` | да |
| `game.update` | `UpdateGameAction` | да |
| `game.addMember` | `AddGameMemberAction` | да |
| `game.updateMember` | `UpdateGameMemberAction` | да |
| `game.removeMember` | `RemoveGameMemberAction` | да |
| `game.getMemberList` | `GetGameMemberListAction` | да |
| `game.submitCharacter` | `SubmitGameCharacterAction` | да |
| `game.approveCharacter` | `ApproveGameCharacterAction` | да |
| `game.returnCharacter` | `ReturnGameCharacterAction` | да |
| `game.rejectCharacter` | `RejectGameCharacterAction` | да |
| `game.leaveCharacter` | `LeaveGameCharacterAction` | да |
| `game.setCharacterBonus` | `SetGameCharacterBonusAction` | да |
| `game.setCharacterSectionVisibility` | `SetGameCharacterSectionVisibilityAction` | да |
| `game.getCharacter` | `GetGameCharacterAction` | да |
| `game.getCharacterList` | `GetGameCharacterListAction` | да |
| `character.migrate` | `MigrateCharacterAction` | да |
| `game.startSession` | `StartGameSessionAction` | да |
| `game.stopSession` | `StopGameSessionAction` | да |
| `game.createNpc` | `CreateGameNpcAction` | да |
| `game.getNpc` | `GetGameNpcAction` | да |
| `game.getRoster` | `GetGameRosterAction` | да |
| `game.getSheets` | `GetGameSheetsAction` | да |
| `game.getBattleSheets` | `GetGameBattleSheetsAction` | да |
| `game.updateNpc` | `UpdateGameNpcAction` | да |
| `game.translateNpc` | `TranslateGameNpcAction` | да |
| `game.invite` | `InviteGameAction` | да |
| `game.getInvitations` | `GetGameInvitationsAction` | да |
| `game.getMyInvitations` | `GetMyGameInvitationsAction` | да |
| `game.respondInvitation` | `RespondGameInvitationAction` | да |
| `game.requestJoin` | `RequestGameJoinAction` | да |
| `game.getJoinRequests` | `GetGameJoinRequestsAction` | да |
| `game.respondJoinRequest` | `RespondGameJoinRequestAction` | да |
| `game.getWhitelist` | `GetGameWhitelistAction` | да |
| `game.setWhitelist` | `SetGameWhitelistAction` | да |
| `game.joinWhitelist` | `JoinGameWhitelistAction` | да |
| `game.getChronicle` | `GetGameChronicleAction` | да |
| `game.getChronicleEntries` | `GetGameChronicleEntriesAction` | да |
| `game.createChronicleEntry` | `CreateGameChronicleEntryAction` | да |
| `game.updateChronicleEntry` | `UpdateGameChronicleEntryAction` | да |
| `game.deleteChronicleEntry` | `DeleteGameChronicleEntryAction` | да |
| `game.startBattle` | `StartGameBattleAction` | да |
| `game.setBattleRoster` | `SetGameBattleRosterAction` | да |
| `game.endBattle` | `EndGameBattleAction` | да |
| `game.rollInitiative` | `RollGameInitiativeAction` | да |
| `game.declareCheck` | `DeclareGameCheckAction` | да |
| `game.proposeCheck` | `ProposeGameCheckAction` | да |
| `game.answerCheck` | `AnswerGameCheckAction` | да |
| `game.woundAction` | `GameWoundAction` | да |
| `game.manualSheet` | `GameManualSheetAction` | да |
| `game.endTurn` | `EndGameTurnAction` | да |
| `game.continueAction` | `ContinueGameAction` | да |
| `game.abortAction` | `AbortGameAction` | да |
| `game.declareStrike` | `DeclareGameStrikeAction` | да |
| `game.resolveStrike` | `ResolveGameStrikeAction` | да |
| `game.declareWideStrike` | `DeclareGameWideStrikeAction` | да |
| `game.resolveWideStrike` | `ResolveGameWideStrikeAction` | да |
| `game.applyEconomy` | `ApplyGameEconomyAction` | да |
| `game.getShop` | `GetGameShopAction` | да |
| `game.replaceShop` | `ReplaceGameShopAction` | да |

## Контракты `Interface/`

- `Interface/Container/IGameContainer.php` — Публичный контейнер модуля Roleplay/Game.
- `Interface/Service/IGameAdmissions.php` — Приглашение, заявка и whitelist. Не строка игры.
- `Interface/Service/IGameBattles.php` — Идентичность боя, состав и CAS. Лист не пишет.
- `Interface/Service/IGameChecks.php` — Соло-проверка и pairwise одной цели.
- `Interface/Service/IGameChronicles.php` — Записи летописи одной игры. Не лист и не сессия.
- `Interface/Service/IGameDelivery.php` — Доставка уже применённой команды. Лист не пишет.
- `Interface/Service/IGameEconomy.php` — Магазин и typed EconomyOperation.
- `Interface/Service/IGameMemberships.php` — Строка персонажа в игре. Не пишет таблицу character.
- `Interface/Service/IGameProcesses.php` — Строка process. Проверку и удар не считает.
- `Interface/Service/IGameProjections.php` — Краткий roster и полный лист по ключам. Сессию и бой не пишет.
- `Interface/Service/IGameSessionRoster.php` — Чтение текущей сессии и её состава. Запись сессии сюда не входит.
- `Interface/Service/IGameStrikes.php` — Один удар 1 → 1. Лист пишет только непустой список операций.
- `Interface/Service/IGameSyncClock.php` — Пауза и обрыв потока доставки. Не часы чата.
- `Interface/Service/IGameWideStrikes.php` — Широкий удар 1 → N. Успех и урон этот шаг не считает.
- `Interface/Service/IGames.php` — Строка игры без HTTP и без сессии.

## Зависимости

- **Зависит от:** [Core/Event](../Core/Event.md), [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md), [Messages/Chat](../Messages/Chat.md), [Roleplay/Character](Character.md), [Roleplay/Mechanic](Mechanic.md), [Roleplay/Rule](Rule.md), [Roleplay/RuleSpace](RuleSpace.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [game-plan-29.md](../../game-plan-29.md) | план | `~ПЛАН~` | 2026-10-11 |
| [game-plan-30.md](../../game-plan-30.md) | план | `~ПЛАН~` | 2026-10-11 |
| [game-plan-31.md](../../game-plan-31.md) | план | `~ПЛАН~` | 2026-10-12 |
| [game-plan-32.md](../../game-plan-32.md) | план | `~ПЛАН~` | 2026-10-12 |
| [game-plan-34.md](../../game-plan-34.md) | план | `~ПЛАН~` | 2026-10-16 |
| [game-plan-35.md](../../game-plan-35.md) | план | `~ПЛАН~` | 2026-10-16 |
| [game-plan-01.md](../../game-plan-01.md) | план | `~ЧАСТИЧНО~` | 2026-10-04 |
| [game-plan-13.md](../../game-plan-13.md) | план | `~ЧАСТИЧНО~` | 2026-10-04 |
| [game-plan-18.md](../../game-plan-18.md) | план | `~ЧАСТИЧНО~` | — |
| [game-plan-16.md](../../game-plan-16.md) | план | `~ОТМЕНЁН~` | 2026-10-04 |
| [game-plan-17.md](../../game-plan-17.md) | план | `~ОТМЕНЁН~` | 2026-10-04 |
| [game-plan-02.md](../../game-plan-02.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-03.md](../../game-plan-03.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-04.md](../../game-plan-04.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-05.md](../../game-plan-05.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-06.md](../../game-plan-06.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-07.md](../../game-plan-07.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-08.md](../../game-plan-08.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-09.md](../../game-plan-09.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-10.md](../../game-plan-10.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-11.md](../../game-plan-11.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-12.md](../../game-plan-12.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-14.md](../../game-plan-14.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-15.md](../../game-plan-15.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [game-plan-19.md](../../game-plan-19.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-20.md](../../game-plan-20.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-21.md](../../game-plan-21.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-22.md](../../game-plan-22.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-23.md](../../game-plan-23.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-24.md](../../game-plan-24.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-25.md](../../game-plan-25.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-26.md](../../game-plan-26.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-27.md](../../game-plan-27.md) | план | `~СДЕЛАНО~` | — |
| [game-plan-28.md](../../game-plan-28.md) | план | `~СДЕЛАНО~` | 2026-10-11 |
| [game-plan-36-strike-owner-actions.md](../../game-plan-36-strike-owner-actions.md) | план | `~СДЕЛАНО~` | — |
| [game-roadmap.md](../../game-roadmap.md) | план | `~СДЕЛАНО~` | 2026-10-05 |
| [game-system.md](../../game-system.md) | канон | `~СДЕЛАНО~` | 2026-08-30 |
| [game-combat-readiness.md](../../game-combat-readiness.md) | канон | ? | 2026-10-07 |

