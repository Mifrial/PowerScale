# Промт: план и старт разработки модуля Game

> Промт для новой сессии. Цель сессии: разведка (read-only) → план → создание
> контекст-файла `docs/specs/game-module-context.md` → согласование объёма →
> реализация первой фазы.

---

## Контекст

Проект: **PowerScale**, фронт `draft-front_1.2ds` (Vue 3 + Vite + TS + Vuetify + Pinia).
Правила работы — `AGENTS.md`:
- Dev на порту 3000 (сервер пользователя) — **не убивать и не занимать порт**.
- Верификация: `npm run format` + `npm run lint` (автофиксят), `npx vue-tsc --noEmit`,
  `npm run test`; **полный гейт на завершении шага/задачи**.
- Читать файлы лениво, только по задаче; не сканировать проект на старте.
- Память — `.opencode/memory.jsonl` (читать по требованию, `work_item` в конце сессии).

Фронтовый стандарт — `draft-front_1.2ds/frontend-rules.md` (**обязателен**, все разделы):
анатомия модуля (Dto/Enum/Interface/Service/Mock/Store/Constant/Page/Component/Utils/__tests__/init.ts/routes.ts),
один экспорт на файл, типы в Dto/Enum/Interface, сервисы через ServiceLocator,
регистрация API/прав в `init.ts`, комментарии «почему».

### Документы
- `docs/tr/TR.md`:
  - **§8 «Игры»** — целевая функциональность: сущность игры, жизненный цикл статусов
    (`draft → recruiting → in_process → paused → playing → completed`), видимость, join-policy,
    лимиты ОС/ОР/денег, права, раздел `/games`.
  - **§3 БД** — `games`, `game_members`, `game_member_permissions`, `game_invitations`,
    `game_characters`.
  - **§7.8 + §3 `game_characters`** — модель модерации персонажей в игре (зафиксирована
    2026-08-13): **два состояния листа + черновик** (`active_json`/`pending_json`/`draft_json`),
    модерация = diff(`active`, `pending`) → approve/reject; несколько игр — свои записи, не
    пересекаются; снимки на записи, без истории.
  - **ВАЖНО: ТР — НЕ ИСТИНА.** Мы дорабатываем его в процессе разработки. При расхождении
    кода и ТР решаем осознанно, какой вариант оставить, и актуализируем ТР.
- `docs/specs/character-module-context.md` (D115–D117: статус листа как вход API,
  чат обсуждения при создании персонажа, модель модерации), `docs/specs/inventory-module-context.md`,
  `docs/specs/development-module-context.md` — образцы живой контекст-спеки.
- Память: узлы «Roleplay/Character (фронт)», «Заход „Инвентарь“ — модель оружия и владения»,
  `work_item:2026-08-13:сохранение/редактирование персонажа + авто-ресурс ОД`.

### Макеты
`makets/game-detail.html` и `makets/games-list.html` (также `game-new.html`, `game-moderate.html`,
`game-detail-characters.html`) — **выглядят плохо и местами неверны; на их дизайн НЕ полагаться**.
Ориентир дизайна — **модуль Character**: списки/карточки/редактор, вкладки, FilterBar,
VirtualList, лёгкие строки, светлые панели, попапы.

## Допустимые зависимости (жёсткое правило)

- **Game МОЖЕТ зависеть от**: группы модулей Core, Messages/Notifications, Messages/Chat,
  Roleplay/Rule, Roleplay/Character.
- **Обратное — НЕЛЬЗЯ**: ни один из перечисленных модулей/групп (включая Character, Rule,
  Core, Chat) **не должен зависеть от Game**. Направление только `Game → (перечисленные)`.
  Проверять каждый импорт.

## Цель

Составить план разработки модуля Game (read-only → план → создать контекст-файл) и после
согласования объёма начать реализацию. Цель модуля — **замкнуть группу Roleplay**: игры
используют правила (пространство + ревизия), персонажей (roster + модерация), чат
(game chats), Core (права/авторизация).

## Что изучить

1. ТР §8 «Игры»: статусы, видимость, join-policy, лимиты ОС/ОР/денег, права, таблица `/games`.
2. ТР §3: `games`, `game_members`, `game_member_permissions`, `game_invitations`, `game_characters`.
3. §7.8 + `game_characters`: модель модерации (сценарии: создан для игры / вне игры /
   подан готовый / правка в игре), diff до/после, approve/reject.
4. Модуль Character: список/карточка/редактор (страницы, стора, моки, `IGameApi`-паттерн
   регистрации, `Utils/access.ts`, `CharacterEditPage`), как подать персонажа в игру и
   получить его состояние.
5. Rule/Space: `fetchRevision`, ревизии, лимиты создания (`CharacterCreationConfig`:
   osTotal/orTotal/moneyBudget) — игра должна задавать эти лимиты при создании персонажа.
6. Messages/Chat: game-чаты (`GAME_CHAT_TABS`/`GAME_CHAT_TYPES`), встраивание обсуждения
   (как `character_discussion` в Character) — так же для игры.
7. Core: права (route-perms, AccessService, паттерн canView/canEdit), пользователи.
8. `draft-front_1.2ds/frontend-rules.md` — все разделы.
9. `AGENTS.md` — команды, верификация, память.

## Что сделать (порядок)

1. **Read-only разведка** по списку выше (не сканировать проект на старте — только по задаче).
2. **Составить план**: гэпы/риски, фазы (ядро → roster/модерация → in-game сессия), тесты,
   объём; зафиксировать, что в скоупе, что отложить.
3. **Создать файл контекста разработки `docs/specs/game-module-context.md`** — по образцу
   `character-module-context.md`/`inventory-module-context.md`:
   статус модуля, цель, план фаз, реестр решений (нумеровать D-номерами по мере принятия),
   файлы, верификация, ссылки на ТР.
4. **Согласовать объём с пользователем** (что в скоуп первой фазы, что отложить).
5. После согласования — **реализовать первую фазу (ядро Game)**:
   Dto/Enum/Interface/Service/Mock/Store/Constant/Page/Component, `routes.ts`, `init.ts`
   (регистрация API + права), гейтинг доступа. UI — в стиле Character.

## Ограничения

- Не импортировать недопустимые модули (только разрешённые зависимости, однонаправленно).
- Дизайн — по стилю модуля Character, НЕ по макетам `makets/game-*.html`.
- Следовать `frontend-rules.md`.
- ТР — живой документ, дорабатывается; не догма.
- Верификация по `AGENTS.md`; полный гейт (`format`+`lint`+`vue-tsc`+`test`) на завершении шага.

## Результат

1. План разработки модуля Game (гэпы, фазы, риски, тесты, скоуп).
2. Созданный `docs/specs/game-module-context.md`.
3. Согласованный с пользователем объём + (по согласованию) реализация первой фазы.
