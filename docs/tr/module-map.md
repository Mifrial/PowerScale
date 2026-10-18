# Реестр модулей

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer map` (из `www/mifrial`); сверка — `composer map-check`.
Источник: `module.config.php`, каталоги `Interface/` и импорты модулей.
Карты входа стабильных Core-модулей — `README.md` внутри модуля;
что считать каноном — раздел «Карта доверия: стабильные модули Core» в
[`contract-status.md`](contract-status.md).

## Сводка

| Группа | Модуль | Локатор | Портов | Маршрутов | Зависит от |
|---|---|---|---|---|---|
| Core | `Agent` | `IAgentContainer` | 1 | 0 | Core/Kernel, Core/SmartTable |
| Core | `Auth` | `IAuthContainer` | 15 | 10 | Core/Kernel, Core/Mail, Core/SmartTable, Core/User |
| Core | `Cache` | `ICacheContainer` | 1 | 0 | Core/Kernel |
| Core | `Event` | `IEventContainer` | 1 | 0 | Core/Kernel |
| Core | `Kernel` | `IKernelContainer` | 1 | 1 | Core/SmartTable |
| Core | `Logger` | `ILoggerContainer` | 4 | 2 | Core/Kernel, Core/SmartTable, Core/User |
| Core | `Mail` | `IMailContainer` | 2 | 0 | Core/Agent, Core/Kernel, Core/SmartTable |
| Core | `SmartTable` | `ISmartTableContainer` | 4 | 0 | Core/Cache, Core/Kernel |
| Core | `User` | `IUserContainer` | 17 | 11 | Core/Kernel, Core/SmartTable |
| Messages | `Chat` | `IChatContainer` | 11 | 7 | Core/Kernel, Core/SmartTable, Core/User |
| Roleplay | `Character` | `ICharacterContainer` | 17 | 9 | Core/Kernel, Core/SmartTable, Core/User, Roleplay/Game, Roleplay/Keyword, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace |
| Roleplay | `Game` | `IGameContainer` | 72 | 60 | Core/Event, Core/Kernel, Core/SmartTable, Core/User, Messages/Chat, Roleplay/Character, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace |
| Roleplay | `Keyword` | `IKeywordContainer` | 7 | 5 | Core/Kernel, Core/SmartTable, Core/User |
| Roleplay | `Mechanic` | `IMechanicContainer` | 8 | 4 | Core/Kernel, Core/SmartTable, Core/User |
| Roleplay | `Rule` | `IRuleContainer` | 2 | 0 | Core/Kernel, Core/SmartTable, Roleplay/Keyword, Roleplay/Mechanic, Versioning/Space |
| Roleplay | `RuleSpace` | `IRuleSpaceContainer` | 11 | 9 | Core/Kernel, Core/SmartTable, Core/User, Roleplay/Rule |
| Versioning | `Space` | `ISpaceContainer` | 1 | 0 | Core/Cache, Core/Kernel, Core/SmartTable |

## Core/Agent

- **Путь:** `modules/Core/Agent`
- **Неймспейс:** `Mifrial\Core\Agent`
- **Контейнер:** `Mifrial\Core\Agent\Container\AgentContainer`
- **Локатор:** `Mifrial\Core\Agent\Interface\Container\IAgentContainer`
- **Setup:** `Mifrial\Core\Agent\Setup\AgentModuleSetup`

### Порты

- `Mifrial\Core\Agent\Interface\Service\IAgents`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/IAgentContainer.php` — Публичный контейнер модуля Agent.
- `Interface/Service/IAgentHandler.php` — Обработчик тика агента. Класс донора, не PHP в БД.
- `Interface/Service/IAgents.php` — Расписание агентов и тик CLI.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable
- **От него зависят:** Core/Mail

### События

—


## Core/Auth

- **Путь:** `modules/Core/Auth`
- **Неймспейс:** `Mifrial\Core\Auth`
- **Контейнер:** `Mifrial\Core\Auth\Container\AuthContainer`
- **Локатор:** `Mifrial\Core\Auth\Interface\Container\IAuthContainer`
- **Setup:** `Closure(IServiceLocator)`
- **`request_bind`:** `AuthSessionBinder`
- **Карта входа:** `README.md` в каталоге модуля

### Порты

- `Mifrial\Core\Auth\Service\AuthService`
- `Mifrial\Core\Auth\Service\UserCreateService`
- `Mifrial\Core\Auth\Service\AuthSessionBinder`
- `Mifrial\Core\Auth\Action\LoginAction`
- `Mifrial\Core\Auth\Action\RegisterAction`
- `Mifrial\Core\Auth\Action\LogoutAction`
- `Mifrial\Core\Auth\Action\GetCurrentUserAction`
- `Mifrial\Core\Auth\Action\GuestAction`
- `Mifrial\Core\Auth\Action\GetPasswordPolicyAction`
- `Mifrial\Core\Auth\Action\UserCreateAction`
- `Mifrial\Core\Auth\Service\PasswordResetService`
- `Mifrial\Core\Auth\Action\StartPasswordResetAction`
- `Mifrial\Core\Auth\Action\FinalPasswordResetAction`
- `Mifrial\Core\Auth\Service\SetPasswordService`
- `Mifrial\Core\Auth\Action\SetPasswordAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `auth.login` | `LoginAction` | нет |
| `auth.register` | `RegisterAction` | нет |
| `auth.logout` | `LogoutAction` | да |
| `auth.getCurrentUser` | `GetCurrentUserAction` | нет |
| `auth.guest` | `GuestAction` | нет |
| `auth.getPasswordPolicy` | `GetPasswordPolicyAction` | нет |
| `user.create` | `UserCreateAction` | да |
| `auth.startPasswordReset` | `StartPasswordResetAction` | нет |
| `auth.finalPasswordReset` | `FinalPasswordResetAction` | нет |
| `auth.setPassword` | `SetPasswordAction` | да |

### Контракты `Interface/`

- `Interface/Container/IAuthContainer.php` — Публичный контейнер модуля Auth.
- `Interface/Service/IPasswordResetNotifier.php` — Доставка сырого reset-токена.

### Зависимости

- **Зависит от:** Core/Kernel, Core/Mail, Core/SmartTable, Core/User
- **От него зависят:** —

### События

—


## Core/Cache

- **Путь:** `modules/Core/Cache`
- **Неймспейс:** `Mifrial\Core\Cache`
- **Контейнер:** `Mifrial\Core\Cache\Container\CacheContainer`
- **Локатор:** `Mifrial\Core\Cache\Interface\Container\ICacheContainer`
- **Setup:** —
- **Карта входа:** `README.md` в каталоге модуля

### Порты

- `Mifrial\Core\Cache\Interface\Service\ICacheStore`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/ICacheContainer.php` — Публичный контейнер модуля Cache.
- `Interface/Service/ICacheStore.php` — Байты по ключу с TTL и опциональными тегами.

### Зависимости

- **Зависит от:** Core/Kernel
- **От него зависят:** Core/SmartTable, Versioning/Space

### События

—


## Core/Event

- **Путь:** `modules/Core/Event`
- **Неймспейс:** `Mifrial\Core\Event`
- **Контейнер:** `Mifrial\Core\Event\Container\EventContainer`
- **Локатор:** `Mifrial\Core\Event\Interface\Container\IEventContainer`
- **Setup:** —
- **Карта входа:** `README.md` в каталоге модуля

### Порты

- `Mifrial\Core\Event\Interface\Service\IEventManager`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/IEventContainer.php` — Публичный контейнер портов модуля Event.
- `Interface/Service/IEventListener.php` — Контракт синхронного listener runtime-события.
- `Interface/Service/IEventManager.php` — Публичный порт синхронной runtime-доставки событий.
- `Interface/Value/IEventIssue.php` — Контракт структурированной ошибки или warning события.
- `Interface/Value/IEventPayload.php` — Контракт неизменяемого payload runtime-события.
- `Interface/Value/IEventResult.php` — Контракт локального или агрегированного результата события.

### Зависимости

- **Зависит от:** Core/Kernel
- **От него зависят:** Roleplay/Game

### События

—


## Core/Kernel

- **Путь:** `modules/Core/Kernel`
- **Неймспейс:** `Mifrial\Core\Kernel`
- **Контейнер:** `Mifrial\Core\Kernel\Container\KernelContainer`
- **Локатор:** `Mifrial\Core\Kernel\Interface\Container\IKernelContainer`
- **Setup:** `Mifrial\Core\Kernel\Setup\KernelModuleSetup`
- **Карта входа:** `README.md` в каталоге модуля

### Порты

- `Mifrial\Core\Kernel\Action\PingAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `mifrial.ping` | `PingAction` | нет |

### Контракты `Interface/`

- `Interface/Action/IActionHandler.php` — Маркер обработчика действия.
- `Interface/Action/IActionInput.php` — Маркер входного DTO action: JSON гидрирует конструктор, не имя параметра handle.
- `Interface/Container/IKernelContainer.php` — Публичный контейнер модуля Kernel.
- `Interface/Container/IModuleContainer.php` — Контракт контейнера портов модуля.
- `Interface/Http/IHttpRequest.php` — Контракт снимка HTTP-запроса.
- `Interface/Http/IRequestBinder.php` — Кладёт снимок актора в контекст запроса. Реализация — модуль сессии.
- `Interface/Http/IRequestContext.php` — Процессный снимок входящих cookie и очередь исходящих.
- `Interface/Service/IApplication.php` — Контракт фасада процесса: HTTP и внутренний dispatch.
- `Interface/Service/IDispatcher.php` — Контракт диспетчера действий.
- `Interface/Service/ILogger.php` — Порт записи журнала ядра.
- `Interface/Service/IModuleManager.php` — Контракт загрузки модулей и маршрутов.
- `Interface/Service/IModuleSetup.php` — Карты и data-шаги модуля для CLI setup. Не порт контейнера соседа.
- `Interface/Service/IRuntimeConfig.php` — Локальная конфигурация процесса для портов модулей.
- `Interface/Service/IServiceLocator.php` — Контракт каталога контейнеров модулей.
- `Interface/Service/ISetupStep.php` — Один data-шаг установки модуля. Идемпотентен при повторном CLI.
- `Interface/Service/ITransactionRunner.php` — Одна единица работы на соединении вызывающего.

### Зависимости

- **Зависит от:** Core/SmartTable
- **От него зависят:** Core/Agent, Core/Auth, Core/Cache, Core/Event, Core/Logger, Core/Mail, Core/SmartTable, Core/User, Messages/Chat, Roleplay/Character, Roleplay/Game, Roleplay/Keyword, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace, Versioning/Space

### События

—


## Core/Logger

- **Путь:** `modules/Core/Logger`
- **Неймспейс:** `Mifrial\Core\Logger`
- **Контейнер:** `Mifrial\Core\Logger\Container\LoggerContainer`
- **Локатор:** `Mifrial\Core\Logger\Interface\Container\ILoggerContainer`
- **Setup:** `Mifrial\Core\Logger\Setup\LoggerModuleSetup`
- **Карта входа:** `README.md` в каталоге модуля

### Порты

- `Mifrial\Core\Logger\Service\TableLogWriter`
- `Mifrial\Core\Logger\Service\LoggerHttpService`
- `Mifrial\Core\Logger\Action\FindLogPageAction`
- `Mifrial\Core\Logger\Action\GetLogAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `logger.findPage` | `FindLogPageAction` | да |
| `logger.get` | `GetLogAction` | да |

### Контракты `Interface/`

- `Interface/Container/ILoggerContainer.php` — Публичный контейнер модуля Logger.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Core/User
- **От него зависят:** —

### События

—


## Core/Mail

- **Путь:** `modules/Core/Mail`
- **Неймспейс:** `Mifrial\Core\Mail`
- **Контейнер:** `Mifrial\Core\Mail\Container\MailContainer`
- **Локатор:** `Mifrial\Core\Mail\Interface\Container\IMailContainer`
- **Setup:** `Closure(IServiceLocator)`

### Порты

- `Mifrial\Core\Mail\Interface\Service\IMail`
- `Mifrial\Core\Mail\Service\MailFlushHandler`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/IMailContainer.php` — Публичный контейнер модуля Mail.
- `Interface/Service/IMail.php` — Сосед: поставить событие в очередь.

### Зависимости

- **Зависит от:** Core/Agent, Core/Kernel, Core/SmartTable
- **От него зависят:** Core/Auth

### События

—


## Core/SmartTable

- **Путь:** `modules/Core/SmartTable`
- **Неймспейс:** `Mifrial\Core\SmartTable`
- **Контейнер:** `Mifrial\Core\SmartTable\Container\SmartTableContainer`
- **Локатор:** `Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer`
- **Setup:** `Mifrial\Core\SmartTable\Setup\SmartTableModuleSetup`
- **Карта входа:** `README.md` в каталоге модуля

### Порты

- `Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection`
- `Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway`
- `Mifrial\Core\SmartTable\Interface\Service\ITableCatalog`
- `Mifrial\Core\SmartTable\Service\HydratorRegistry`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/ISmartTableContainer.php` — Публичный контейнер модуля SmartTable.
- `Interface/Field/IFieldHydrator.php` — Преобразование JSON-документа в объект модуля и обратно.
- `Interface/Service/IConditionalOpenedRecords.php` — Условные операции одной открытой карты.
- `Interface/Service/IDatabaseConnection.php` — Внутреннее ленивое соединение с MySQL без SQL-API для соседей.
- `Interface/Service/IOpenedRecords.php` — Строки одной открытой карты.
- `Interface/Service/IOpenedSchema.php` — DDL одной открытой карты.
- `Interface/Service/IOpenedTable.php` — Сумка после open: схема и строки одной карты.
- `Interface/Service/ISmartTableGateway.php` — Порт открытия таблиц по PHP-классу и транзакции соединения.
- `Interface/Service/ITableCatalog.php` — Порт словаря: meta DDL и runtime-таблицы по имени.

### Зависимости

- **Зависит от:** Core/Cache, Core/Kernel
- **От него зависят:** Core/Agent, Core/Auth, Core/Kernel, Core/Logger, Core/Mail, Core/User, Messages/Chat, Roleplay/Character, Roleplay/Game, Roleplay/Keyword, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace, Versioning/Space

### События

—


## Core/User

- **Путь:** `modules/Core/User`
- **Неймспейс:** `Mifrial\Core\User`
- **Контейнер:** `Mifrial\Core\User\Container\UserContainer`
- **Локатор:** `Mifrial\Core\User\Interface\Container\IUserContainer`
- **Setup:** `Mifrial\Core\User\Setup\UserModuleSetup`

### Порты

- `Mifrial\Core\User\Interface\Service\IUserAccounts`
- `Mifrial\Core\User\Interface\Service\IUserGroups`
- `Mifrial\Core\User\Interface\Service\IUserViews`
- `Mifrial\Core\User\Interface\Service\IUserAccess`
- `Mifrial\Core\User\Service\UserHttpService`
- `Mifrial\Core\User\Action\FindUserPageAction`
- `Mifrial\Core\User\Action\GetUserAction`
- `Mifrial\Core\User\Action\GetUsersByIdsAction`
- `Mifrial\Core\User\Action\UpdateUserAction`
- `Mifrial\Core\User\Action\DeactivateUserAction`
- `Mifrial\Core\User\Service\GroupHttpService`
- `Mifrial\Core\User\Action\FindGroupPageAction`
- `Mifrial\Core\User\Action\GetGroupAction`
- `Mifrial\Core\User\Action\GetGroupMembersAction`
- `Mifrial\Core\User\Action\CreateGroupAction`
- `Mifrial\Core\User\Action\UpdateGroupAction`
- `Mifrial\Core\User\Action\DeactivateGroupAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `user.findPage` | `FindUserPageAction` | да |
| `user.get` | `GetUserAction` | да |
| `user.getByIds` | `GetUsersByIdsAction` | да |
| `user.update` | `UpdateUserAction` | да |
| `user.deactivate` | `DeactivateUserAction` | да |
| `userGroup.findPage` | `FindGroupPageAction` | да |
| `userGroup.get` | `GetGroupAction` | да |
| `userGroup.getMembers` | `GetGroupMembersAction` | да |
| `userGroup.create` | `CreateGroupAction` | да |
| `userGroup.update` | `UpdateGroupAction` | да |
| `userGroup.deactivate` | `DeactivateGroupAction` | да |

### Контракты `Interface/`

- `Interface/Container/IUserContainer.php` — Публичный контейнер модуля User.
- `Interface/Service/IUserAccess.php` — Guards HTTP учётки по снимку актора.
- `Interface/Service/IUserAccounts.php` — Фасад учётки для соседей: профиль, без схемы и без таблиц.
- `Interface/Service/IUserGroups.php` — Фасад групп и членства для соседей: без схемы и без таблиц.
- `Interface/Service/IUserViews.php` — JSON-профиль User для HTTP и Auth.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable
- **От него зависят:** Core/Auth, Core/Logger, Messages/Chat, Roleplay/Character, Roleplay/Game, Roleplay/Keyword, Roleplay/Mechanic, Roleplay/RuleSpace

### События

—


## Messages/Chat

- **Путь:** `modules/Messages/Chat`
- **Неймспейс:** `Mifrial\Messages\Chat`
- **Контейнер:** `Mifrial\Messages\Chat\Container\ChatContainer`
- **Локатор:** `Mifrial\Messages\Chat\Interface\Container\IChatContainer`
- **Setup:** `Mifrial\Messages\Chat\Setup\ChatModuleSetup`

### Порты

- `Mifrial\Messages\Chat\Interface\Service\IChatTypeRegistry`
- `Mifrial\Messages\Chat\Interface\Service\IChats`
- `Mifrial\Messages\Chat\Service\ChatHttpService`
- `Mifrial\Messages\Chat\Service\ChatSseService`
- `Mifrial\Messages\Chat\Action\GetChatsAction`
- `Mifrial\Messages\Chat\Action\FindMessagePageAction`
- `Mifrial\Messages\Chat\Action\SendMessageAction`
- `Mifrial\Messages\Chat\Action\MarkChatReadAction`
- `Mifrial\Messages\Chat\Action\UpdateMessageVisibilityAction`
- `Mifrial\Messages\Chat\Action\AddPrivateChatAction`
- `Mifrial\Messages\Chat\Action\AddGroupChatAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `chat.getChats` | `GetChatsAction` | да |
| `chat.findMessagePage` | `FindMessagePageAction` | да |
| `chat.sendMessage` | `SendMessageAction` | да |
| `chat.markChatRead` | `MarkChatReadAction` | да |
| `chat.updateMessageVisibility` | `UpdateMessageVisibilityAction` | да |
| `chat.addPrivate` | `AddPrivateChatAction` | да |
| `chat.addGroup` | `AddGroupChatAction` | да |

### Контракты `Interface/`

- `Interface/Container/IChatContainer.php` — Публичный контейнер модуля Chat.
- `Interface/Service/IChatTypeRegistry.php` — Память процесса: непрозрачные строки типов донора.
- `Interface/Service/IChats.php` — Фасад чатов для соседей: без схемы и без таблиц.
- `Interface/Service/ISseClock.php` — Часы и пауза цикла SSE; в тестах подмена без sleep(2).

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Core/User
- **От него зависят:** Roleplay/Game

### События

—


## Roleplay/Character

- **Путь:** `modules/Roleplay/Character`
- **Неймспейс:** `Mifrial\Roleplay\Character`
- **Контейнер:** `Mifrial\Roleplay\Character\Container\CharacterContainer`
- **Локатор:** `Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer`
- **Setup:** `Closure(IServiceLocator)`

### Порты

- `Mifrial\Roleplay\Character\Interface\Service\ICharacters`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines`
- `Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations`
- `Mifrial\Roleplay\Character\Action\ApplyCharacterActualPatchAction`
- `Mifrial\Roleplay\Character\Action\CreateCharacterAction`
- `Mifrial\Roleplay\Character\Action\UpdateCharacterAction`
- `Mifrial\Roleplay\Character\Action\ValidateCharacterAction`
- `Mifrial\Roleplay\Character\Action\MigrateCharacterAction`
- `Mifrial\Roleplay\Character\Action\GetCharacterListAction`
- `Mifrial\Roleplay\Character\Action\GetCharacterAction`
- `Mifrial\Roleplay\Character\Action\UpdateCharacterVisibilityAction`
- `Mifrial\Roleplay\Character\Action\UpdateCharacterOwnerNotesAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `character.create` | `CreateCharacterAction` | да |
| `character.update` | `UpdateCharacterAction` | да |
| `character.validate` | `ValidateCharacterAction` | да |
| `character.migrate` | `MigrateCharacterAction` | да |
| `character.getList` | `GetCharacterListAction` | да |
| `character.get` | `GetCharacterAction` | да |
| `character.updateVisibility` | `UpdateCharacterVisibilityAction` | да |
| `character.updateOwnerNotes` | `UpdateCharacterOwnerNotesAction` | да |
| `character.applyActualPatch` | `ApplyCharacterActualPatchAction` | да |

### Контракты `Interface/`

- `Interface/Container/ICharacterContainer.php` — Публичный контейнер модуля Roleplay/Character.
- `Interface/Service/ICharacterActualMutations.php` — Точечная запись actual: операция и ожидаемая actual_version.
- `Interface/Service/ICharacterCombatLayers.php` — Чтение боевых слоёв из документов листа и выборов. Документы не пишет.
- `Interface/Service/ICharacterFormulaContexts.php` — Сборка узкого контекста формулы из уже записанного снимка листа.
- `Interface/Service/ICharacterOsSteps.php` — Шаг «Основа»: binding среза и узкий снимок в порт Mechanic Engine.
- `Interface/Service/ICharacterRuleSlices.php` — Срез правил мира для построения персонажа: без persist и без HTTP.
- `Interface/Service/ICharacterSessionParticipants.php` — Персонаж уже в текущей сессии какой-либо игры. Реализацию даёт Game.
- `Interface/Service/ICharacterSheetEngines.php` — Сборка и ремап листа без записи в character.
- `Interface/Service/ICharacterSheets.php` — Сборка листа и тот же валидатор, что пойдёт на validate-only.
- `Interface/Service/ICharacters.php` — Фасад actual-строки персонажа: без схемы и без HTTP.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Core/User, Roleplay/Game, Roleplay/Keyword, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace
- **От него зависят:** Roleplay/Game

### События

—


## Roleplay/Game

- **Путь:** `modules/Roleplay/Game`
- **Неймспейс:** `Mifrial\Roleplay\Game`
- **Контейнер:** `Mifrial\Roleplay\Game\Container\GameContainer`
- **Локатор:** `Mifrial\Roleplay\Game\Interface\Container\IGameContainer`
- **Setup:** `Closure(IServiceLocator)`

### Порты

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
- `Mifrial\Roleplay\Game\Action\StartGameSessionAction`
- `Mifrial\Roleplay\Game\Action\StopGameSessionAction`
- `Mifrial\Roleplay\Game\Action\CreateGameNpcAction`
- `Mifrial\Roleplay\Game\Action\GetGameNpcAction`
- `Mifrial\Roleplay\Game\Action\UpdateGameNpcAction`
- `Mifrial\Roleplay\Game\Action\TranslateGameNpcAction`

### Маршруты

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

### Контракты `Interface/`

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

### Зависимости

- **Зависит от:** Core/Event, Core/Kernel, Core/SmartTable, Core/User, Messages/Chat, Roleplay/Character, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace
- **От него зависят:** Roleplay/Character

### События

—


## Roleplay/Keyword

- **Путь:** `modules/Roleplay/Keyword`
- **Неймспейс:** `Mifrial\Roleplay\Keyword`
- **Контейнер:** `Mifrial\Roleplay\Keyword\Container\KeywordContainer`
- **Локатор:** `Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer`
- **Setup:** `Mifrial\Roleplay\Keyword\Setup\KeywordModuleSetup`

### Порты

- `Mifrial\Roleplay\Keyword\Interface\Service\IKeywords`
- `Mifrial\Roleplay\Keyword\Service\KeywordHttpService`
- `Mifrial\Roleplay\Keyword\Action\GetKeywordListAction`
- `Mifrial\Roleplay\Keyword\Action\GetKeywordAction`
- `Mifrial\Roleplay\Keyword\Action\CreateKeywordAction`
- `Mifrial\Roleplay\Keyword\Action\UpdateKeywordAction`
- `Mifrial\Roleplay\Keyword\Action\DeactivateKeywordAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `keyword.getList` | `GetKeywordListAction` | да |
| `keyword.get` | `GetKeywordAction` | да |
| `keyword.create` | `CreateKeywordAction` | да |
| `keyword.update` | `UpdateKeywordAction` | да |
| `keyword.deactivate` | `DeactivateKeywordAction` | да |

### Контракты `Interface/`

- `Interface/Container/IKeywordContainer.php` — Публичный контейнер модуля Roleplay/Keyword.
- `Interface/Service/IKeywords.php` — Фасад справочника признаков: без схемы и без таблиц.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Core/User
- **От него зависят:** Roleplay/Character, Roleplay/Rule

### События

—


## Roleplay/Mechanic

- **Путь:** `modules/Roleplay/Mechanic`
- **Неймспейс:** `Mifrial\Roleplay\Mechanic`
- **Контейнер:** `Mifrial\Roleplay\Mechanic\Container\MechanicContainer`
- **Локатор:** `Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer`
- **Setup:** `Mifrial\Roleplay\Mechanic\Setup\MechanicModuleSetup`

### Порты

- `Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics`
- `Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine`
- `Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls`
- `Mifrial\Roleplay\Mechanic\Service\MechanicHttpService`
- `Mifrial\Roleplay\Mechanic\Action\GetMechanicListAction`
- `Mifrial\Roleplay\Mechanic\Action\GetMechanicAction`
- `Mifrial\Roleplay\Mechanic\Action\CreateMechanicAction`
- `Mifrial\Roleplay\Mechanic\Action\UpdateMechanicAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `mechanic.getList` | `GetMechanicListAction` | да |
| `mechanic.get` | `GetMechanicAction` | да |
| `mechanic.create` | `CreateMechanicAction` | да |
| `mechanic.update` | `UpdateMechanicAction` | да |

### Контракты `Interface/`

- `Interface/Container/IMechanicContainer.php` — Публичный контейнер модуля Roleplay/Mechanic.
- `Interface/IBloodClotting.php` — Маркер свёртывания на карточке состояния. runEvent его не зовёт.
- `Interface/IBluntUnconscious.php` — Потеря сознания при пороге успеха и повреждении не ниже стойкости.
- `Interface/IDamageAsWounds.php` — Повреждения этого удара пишутся раной, а не остатком.
- `Interface/IExhaustionAmount.php` — Истощение этого удара пишется в состояние той же силы.
- `Interface/IExhaustionWound.php` — Истощение этого удара пишется в рану с множителем.
- `Interface/IInjuryEfficiency.php` — Помеха проверки увечья. Дельта лежит в payload хука.
- `Interface/IMechanicHandler.php` — Хендлер механики: подписки события на приоритет и мутация контекста.
- `Interface/IReliabilityCut.php` — Маркер хендлера, который срезает надёжность слоёв с порогом.
- `Interface/MechanicPayload.php` — Payload binding: хендлер сам сужает тип. Общего поведения нет.
- `Interface/Service/IMechanicEngine.php` — Публичный событийный движок: сосед не регистрирует хендлеры и не видит реестр.
- `Interface/Service/IMechanicRolls.php` — Порт броска: сосед собирает binding и не пишет формулу кубов.
- `Interface/Service/IMechanics.php` — Фасад справочника механик: без схемы и без таблиц.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Core/User
- **От него зависят:** Roleplay/Character, Roleplay/Game, Roleplay/Rule

### События

—


## Roleplay/Rule

- **Путь:** `modules/Roleplay/Rule`
- **Неймспейс:** `Mifrial\Roleplay\Rule`
- **Контейнер:** `Mifrial\Roleplay\Rule\Container\RuleContainer`
- **Локатор:** `Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer`
- **Setup:** `Closure(IServiceLocator)`

### Порты

- `Mifrial\Roleplay\Rule\Interface\Service\IRules`
- `Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/IRuleContainer.php` — Публичный контейнер модуля Roleplay/Rule.
- `Interface/Service/IFormulaEvaluations.php` — Обход уже разобранных узлов формулы.
- `Interface/Service/IRules.php` — Фасад правил: identity по code, состав на часах.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Roleplay/Keyword, Roleplay/Mechanic, Versioning/Space
- **От него зависят:** Roleplay/Character, Roleplay/Game, Roleplay/RuleSpace

### События

—


## Roleplay/RuleSpace

- **Путь:** `modules/Roleplay/RuleSpace`
- **Неймспейс:** `Mifrial\Roleplay\RuleSpace`
- **Контейнер:** `Mifrial\Roleplay\RuleSpace\Container\RuleSpaceContainer`
- **Локатор:** `Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer`
- **Setup:** `Mifrial\Roleplay\RuleSpace\Setup\RuleSpaceModuleSetup`

### Порты

- `Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces`
- `Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceListAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceByCodeAction`
- `Mifrial\Roleplay\RuleSpace\Action\CreateSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\UpdateSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\DeactivateSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceRevisionsAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceRevisionAction`
- `Mifrial\Roleplay\RuleSpace\Action\CommitSpaceDraftAction`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `ruleSpace.getList` | `GetSpaceListAction` | да |
| `ruleSpace.get` | `GetSpaceAction` | да |
| `ruleSpace.getByCode` | `GetSpaceByCodeAction` | да |
| `ruleSpace.create` | `CreateSpaceAction` | да |
| `ruleSpace.update` | `UpdateSpaceAction` | да |
| `ruleSpace.deactivate` | `DeactivateSpaceAction` | да |
| `ruleSpace.getRevisions` | `GetSpaceRevisionsAction` | да |
| `ruleSpace.getRevision` | `GetSpaceRevisionAction` | да |
| `ruleSpace.commitDraft` | `CommitSpaceDraftAction` | да |

### Контракты `Interface/`

- `Interface/Container/IRuleSpaceContainer.php` — Публичный контейнер модуля Roleplay/RuleSpace.
- `Interface/Service/IRuleSpaces.php` — Оператор миров правил: sidecar и состав через IRules.

### Зависимости

- **Зависит от:** Core/Kernel, Core/SmartTable, Core/User, Roleplay/Rule
- **От него зависят:** Roleplay/Character, Roleplay/Game

### События

—


## Versioning/Space

- **Путь:** `modules/Versioning/Space`
- **Неймспейс:** `Mifrial\Versioning\Space`
- **Контейнер:** `Mifrial\Versioning\Space\Container\SpaceContainer`
- **Локатор:** `Mifrial\Versioning\Space\Interface\Container\ISpaceContainer`
- **Setup:** —

### Порты

- `Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog`

### Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

### Контракты `Interface/`

- `Interface/Container/ISpaceContainer.php` — Публичный контейнер модуля Versioning/Space.
- `Interface/Service/IVersionedCatalog.php` — Открывает репозиторий по реестру или явной спецификации кластера.
- `Interface/Service/IVersionedRepository.php` — Часы и срез одной версионируемой сущности.

### Зависимости

- **Зависит от:** Core/Cache, Core/Kernel, Core/SmartTable
- **От него зависят:** Roleplay/Rule

### События

—

