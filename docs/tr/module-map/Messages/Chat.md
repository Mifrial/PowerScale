# Messages/Chat

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Messages/Chat --zone=tr`.

- **Путь:** `modules/Messages/Chat`
- **Неймспейс:** `Mifrial\Messages\Chat`
- **Контейнер:** `Mifrial\Messages\Chat\Container\ChatContainer`
- **Локатор:** `Mifrial\Messages\Chat\Interface\Container\IChatContainer`
- **Setup:** `Mifrial\Messages\Chat\Setup\ChatModuleSetup`
- **Канон:** не зафиксирован

## Порты

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

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `chat.getChats` | `GetChatsAction` | да |
| `chat.findMessagePage` | `FindMessagePageAction` | да |
| `chat.sendMessage` | `SendMessageAction` | да |
| `chat.markChatRead` | `MarkChatReadAction` | да |
| `chat.updateMessageVisibility` | `UpdateMessageVisibilityAction` | да |
| `chat.addPrivate` | `AddPrivateChatAction` | да |
| `chat.addGroup` | `AddGroupChatAction` | да |

## Контракты `Interface/`

- `Interface/Container/IChatContainer.php` — Публичный контейнер модуля Chat.
- `Interface/Service/IChatTypeRegistry.php` — Память процесса: непрозрачные строки типов донора.
- `Interface/Service/IChats.php` — Фасад чатов для соседей: без схемы и без таблиц.
- `Interface/Service/ISseClock.php` — Часы и пауза цикла SSE; в тестах подмена без sleep(2).

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** [Roleplay/Game](../Roleplay/Game.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [chat-plan-01.md](../../chat-plan-01.md) | план | `~ПЛАН~` | 2026-09-04 |
| [chat-plan-02.md](../../chat-plan-02.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [chat-plan-03.md](../../chat-plan-03.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [chat-plan-04.md](../../chat-plan-04.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [chat-plan-05.md](../../chat-plan-05.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [chat-plan-06.md](../../chat-plan-06.md) | план | `~СДЕЛАНО~` | 2026-09-05 |
| [chat-plan-07.md](../../chat-plan-07.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [chat-roadmap.md](../../chat-roadmap.md) | план | `~СДЕЛАНО~` | 2026-10-04 |
| [chat-system.md](../../chat-system.md) | канон | ? | 2026-08-30 |

