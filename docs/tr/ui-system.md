# Интерфейс, уведомления и frontend-решения

**Статус:** каноническая документация подтверждённых frontend-паттернов; backend-части с `OPEN` не считаются реализованными.

## Интерфейсные разделы

Основные группы экранов:

- authentication, sessions и guest entry;
- users и groups;
- keywords;
- spaces и их revision context;
- characters;
- games;
- chats и notifications.

Общий layout содержит topbar, sidebar и контекст пространства/времени. Отдельный исторический footer и независимое верхнее меню не являются текущим контрактом. Фильтры, horizontal editor panel и expansion panels — UI-паттерны, а не доменные модели.

## Frontend-поведение

- Сервисы модулей регистрируются через ServiceLocator; consumers используют фасады `getXxxApi()`.
- Draft persistence и URL сохраняют контекст редактирования/просмотра, но не подменяют backend publication.
- Длинные списки используют виртуализацию.
- Поиск и частые события debounce-ятся.
- Ошибки загрузки показывают retry; тихие провалы запрещены.
- Error boundaries отделяют ошибку страницы/секции от всего приложения.
- Batch endpoints используются там, где пользовательская операция работает с набором объектов.

## Уведомления

Уведомление может содержать шаблон, параметры отображения и action-кнопки. UI-контракт шаблона переносится только после сверки с фактическим типом данных.

Генераторы событий, подписчики модулей, идемпотентность, хранение и delivery policy backend — `OPEN`. Полузаглушечный frontend не следует описывать как готовую систему уведомлений.

Topbar содержит bell badge и открывает right-side slider. Полная лента — `/notifications`; template administration — `/admin/notification-templates`, `/new` и `/:id/edit`. Удаление template — soft-delete через confirmation dialog, отдельный delete route не является текущим контрактом.

Минимальный набор событий: game invitation с Accept/Decline, character moderation и завершение migration. Повторное событие с тем же типом и ключевыми параметрами обновляет существующее уведомление, а не создаёт дубль (`REQUIREMENT`, backend idempotency `OPEN`).

Шаблон уведомления содержит `key`, `title_template`, `body_template`, `buttons_json` и `active`. Кнопка имеет `label`, `action_type` (`event | url | action`), `action` и opaque `payload`. Notification хранит `from_user_id`, `to_user_id`, `template_key`, `data_json`, `read`, `read_at` и `created_at`. Плейсхолдеры шаблона разрешаются из `data_json`; HTML body должен проходить sanitization.

## Frontend adapter boundaries

Domain/backend DTO не передаются в UI без адаптации. Notification adapter преобразует domain notification в компактную UI-модель `id`, `title`, `preview`, `createdAt`, `icon`, `read`, `actions[]`. Поля backend template (`template_key`, `data_json`, placeholders) не становятся универсальным UI-контрактом; `actions[].payload` остаётся opaque и обрабатывается конкретным action handler.

Для отображения владельцев, авторов и участников по набору идентификаторов UI использует batch API `IUserApi.getUsersByIds(ids, signal?)`, а не последовательные `getUser()` в цикле. `signal` является необязательным `AbortSignal`: consumer может отменить устаревший или уже ненужный запрос при смене страницы, нового поиска или размонтировании компонента. Отмена не заменяет retry/error state.

Chat является host-модулем сообщений и не импортирует прикладные Rule, Character или Game. Донор регистрируется через публичный Chat API. `ChatRulesContext` и `ChatInlineRendererContext` содержат только общие/opaque данные; Chat не знает DTO донора. Donor-owned callbacks (`processAttachments`, `tokenSources`) передаются через typed plugin contract.

Visibility фильтруется до передачи данных в UI. Ошибка adapter/API публикуется через стандартное `error`/`retry` состояние; host-модуль не интерпретирует opaque action payload и не создаёт N+1 lookup.

## Статус real-time

Polling/mock-поведение и целевой SSE-контракт не смешиваются. SSE, reconnect, retention, visibility filtering и authorization требуют отдельного backend-контракта и до его подтверждения имеют статус `OPEN`.

## Связанные решения

- Chat plugin и inline-чипы описаны в [`chat-system.md`](chat-system.md).
- ServiceLocator, слои и DAG описаны в [`architecture.md`](architecture.md).
- Отменённые UI-варианты находятся в [`history.md`](history.md).

## Route и UI acceptance catalog

Auth routes: `/login`, `/register`, `/forgot-password`, `/reset-password`. Logout не является отдельной страницей или frontend route: authenticated action `auth.logout` запускается через confirmation dialog; после успеха сессия сбрасывается и пользователь попадает на `/login`. Core routes: `/users`, `/users/new`, `/users/:id`, `/users/:id/edit`, `/admin/groups` и `/admin/keywords` с permission guards из `auth-system.md`.

Space/Rule routes выбирают `space code` и revision context. Character routes описаны в `character-system.md`; Game routes и tabs — в `game-system.md`. Деактивация сущности выполняется action-dialog на странице сущности, а не отдельной страницей.

Guest видит публичные games/spaces и публичные chats, но не создаёт и не редактирует доменные объекты и не пишет в Chat. Auth screens не используют основной sidebar.

## Current visual contracts

- Shell: `v-app-bar` с menu toggle, breadcrumbs и notifications; sidebar содержит user block и domain/admin navigation;
- filter bar: chips, text search, filter popup, apply/reset и chip-level removal;
- длинные rows/grids/chats — virtualized;
- editor stages — horizontal navigation with active stage, budget summary и characteristic chips;
- expansion rows показывают summary, selected/open state, requirements, keywords и contextual edit action;
- content errors разделяются по page/section, operation exposes retry;
- forms use AbortController for cancellable requests and debounce for search/high-frequency input.

Footer и отдельное top-menu не являются current contract (`HISTORICAL`).
