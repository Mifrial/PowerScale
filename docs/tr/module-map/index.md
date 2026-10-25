# Реестр модулей

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`) — обновляет `doc-index` и реестр;
сверка — `composer maps-check`.
Карточки: `<Группа>/<Модуль>.md` рядом с этим файлом; машиночитаемо — `../module-map.json`.
Планы в карточках — из `doc-index.json` (`composer docs-map`).

## Сводка

| Группа | Модуль | Локатор | Портов | Маршрутов | Зависит от | Документов |
|---|---|---|---|---|---|---|
| Core | [Agent](Core/Agent.md) | `IAgentContainer` | 1 | 0 | Core/Kernel, Core/SmartTable | 1 |
| Core | [Auth](Core/Auth.md) | `IAuthContainer` | 15 | 10 | Core/Kernel, Core/Mail, Core/SmartTable, Core/User | 5 |
| Core | [Cache](Core/Cache.md) | `ICacheContainer` | 1 | 0 | Core/Kernel | 0 |
| Core | [Event](Core/Event.md) | `IEventContainer` | 1 | 0 | Core/Kernel | 0 |
| Core | [Kernel](Core/Kernel.md) | `IKernelContainer` | 1 | 1 | Core/SmartTable | 2 |
| Core | [Logger](Core/Logger.md) | `ILoggerContainer` | 4 | 2 | Core/Kernel, Core/SmartTable, Core/User | 5 |
| Core | [Mail](Core/Mail.md) | `IMailContainer` | 2 | 0 | Core/Agent, Core/Kernel, Core/SmartTable | 2 |
| Core | [SmartTable](Core/SmartTable.md) | `ISmartTableContainer` | 4 | 0 | Core/Cache, Core/Kernel | 22 |
| Core | [User](Core/User.md) | `IUserContainer` | 17 | 11 | Core/Kernel, Core/SmartTable | 10 |
| Messages | [Chat](Messages/Chat.md) | `IChatContainer` | 11 | 7 | Core/Kernel, Core/SmartTable, Core/User | 9 |
| Roleplay | [Character](Roleplay/Character.md) | `ICharacterContainer` | 16 | 8 | Core/Kernel, Core/SmartTable, Core/User, Roleplay/Keyword, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace | 16 |
| Roleplay | [Game](Roleplay/Game.md) | `IGameContainer` | 69 | 57 | Core/Event, Core/Kernel, Core/SmartTable, Core/User, Messages/Chat, Roleplay/Character, Roleplay/Mechanic, Roleplay/Rule, Roleplay/RuleSpace | 39 |
| Roleplay | [Keyword](Roleplay/Keyword.md) | `IKeywordContainer` | 7 | 5 | Core/Kernel, Core/SmartTable, Core/User | 3 |
| Roleplay | [Mechanic](Roleplay/Mechanic.md) | `IMechanicContainer` | 8 | 4 | Core/Kernel, Core/SmartTable, Core/User | 8 |
| Roleplay | [Rule](Roleplay/Rule.md) | `IRuleContainer` | 2 | 0 | Core/Kernel, Core/SmartTable, Roleplay/Keyword, Roleplay/Mechanic, Versioning/Space | 41 |
| Roleplay | [RuleSpace](Roleplay/RuleSpace.md) | `IRuleSpaceContainer` | 11 | 9 | Core/Kernel, Core/SmartTable, Core/User, Roleplay/Rule | 8 |
| Versioning | [Space](Versioning/Space.md) | `ISpaceContainer` | 1 | 0 | Core/Cache, Core/Kernel, Core/SmartTable | 2 |
