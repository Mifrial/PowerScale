# Нарезка Messages/Chat

**Статус:** план, 2026-09-04. Канон UI/DTO — [`chat-system.md`](chat-system.md). Стандарты PHP — [`php-coding-standards.md`](php-coding-standards.md). Конвейер — [`architecture.md`](architecture.md). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). User/Auth закрыты.

Порядок сверху вниз. PHP-модуль **`Messages/Chat`** (ленивый, не `Core/*`). Хост: не Roleplay, не Auth `Service/`, публично User.

Poll-API `chat.sync` на `action.php` **нет ни в одном шаге**. Живой поток — SSE (шаг 3). HTTP — только команды и страницы.

Блокеры до шага 1: [`smarttable-plan-16-composite-unique.md`](smarttable-plan-16-composite-unique.md), [`user-plan-07-member-unique.md`](user-plan-07-member-unique.md) (не копировать `member_key`). `pair_key` на private-чате планы 16/7 **не** снимают.

## 0. Сделано (блокер)

Kernel `action.php`, cookie, актор, CSRF. SmartTable Basic, setup, `powerscale_test`. User/Auth. Чат только с учётки (гость на SSE — не этот трек).

## 1. Таблицы и фасад

Подробно: [`chat-plan-01.md`](chat-plan-01.md).

Модуль на диске. Карты без FK на Game: `chat` (`type` `private`/`group`, имя; нет `game_id`), `chat_member` (`last_read_message_id`, пара чат+юзер как User membership), `chat_message` (`content`, `attachments` json, `created_at`/`updated_at`; не `dice_result`). Лента: индекс FK `chat_id`. На датах — одноколоночный `indexed` (задел sync). Составной INDEX — не этот шаг; когда будет в ST, накат картой.

Фасад: создать private/group, члены, страница сообщений, send, markRead. Mysql, без HTTP и без SSE.

## 2. HTTP commands

Подробно: [`chat-plan-02.md`](chat-plan-02.md).

`chat.getChats` / `findMessagePage` / `sendMessage` / `markChatRead`. CSRF, актор, `AUTH_REQUIRED`. Chat остаётся `lazy`: на `boot()` в карте маршруты, контейнер — при первом `chat.*` / `get(IChatContainer)`. Вложения opaque json, без Files. **`sync` не action.**

## 3. SSE sync

`API/chat-sync.php`, rewrite `/api/chat/sync`. Cookie, без CSRF-заголовка. Кадр `SyncResponse` + `afterId`; `event: sync`; heartbeat `: ping` ~60 с; reconnect с `(since, afterId)`. Несколько соединений на `userId` — норма. Цикл по cursor, не брокер. Apache без буфера/gzip, `Timeout` не 60 с. Не кадры сцены. Подробно: [`chat-plan-03.md`](chat-plan-03.md).

## 4. Vue real

Подробно: [`chat-plan-04.md`](chat-plan-04.md).

`ChatApi` + `Engine.openSse('/chat/sync')` + `ChatSyncService` `mode: 'sse'` при `VITE_API_MODE=real`. Unix `(since, afterId)`. `runAction('chat.sync')` нет. Mock poll не удалять. Не плагины Game.

## 5. Visibility

Подробно: [`chat-plan-05.md`](chat-plan-05.md). Сделано: `updateMessageVisibility`; фильтр выдачи, unread/preview через `aggregate`. Не spoiler-сегменты. Не PHP-COUNT пачкой как User `memberCount`. `forRole` / `see_all` — позже.

## 6. Создать host private/group

Подробно: [`chat-plan-06.md`](chat-plan-06.md). **Сделано:** HTTP `chat.addPrivate` / `chat.addGroup` + UI мессенджера. Inbox и SSE — только `private`/`group`. Game / обсуждения — не этот список и не этот create.

## Позже

`sendSystemMessage` / `thread`; типы `game` / `character_discussion`; Files; macros; хаб Kernel (два `EventSource` на battlemap до хаба); SSE battleground.

## Параллелить нельзя

- 2 без 1; 3 без 2 (кадр sync читает те же строки, что send); 4 без 3.
- `chat.sync` как action «пока нет SSE».
- Одно соединение на `userId`.
- Сцена в chat-sync.
- FK `game_id` до Game.
- 5 без [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md), если в шаге нужны unread/preview пачкой (не копировать `getCountsByGroupIds`).
