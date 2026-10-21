# Карта документации

**СГЕНЕРИРОВАНО.** Это сводка; не редактировать вручную.
Обновление: `composer maps` (из `www/mifrial`); сверка: `composer maps-check`.
Детали по документам — запросом, а не чтением файла:

```bash
php www/mifrial/tools/doc-index/show.php --module=Game --zone=tr
php www/mifrial/tools/doc-index/show.php --module=Roleplay/Rule --zone=tr --state=ПЛАН
php www/mifrial/tools/doc-index/show.php --zone=tr --kind=plan --no-status
php www/mifrial/tools/doc-index/show.php --broken --limit=0
```

`doc-index.json` — машинный источник для `show.php`; целиком его не читать.
Карточки `module-map/` — факты из кода; здесь — заявленные статусы документов.
Документов: 313, планов: 194.

## Схема статусов

- Состояние (один токен): `~ПЛАН~`, `~В РАБОТЕ~`, `~ЧАСТИЧНО~`, `~СДЕЛАНО~`, `~ОТЛОЖЕН~`, `~ОТМЕНЁН~`, `~СНИМОК~`.
- Слои реализации: `~PHP~`, `~VUE~`, `~МОК~`, `~НИГДЕ~`.
- Основание: `~PHP~`, `~МОК~`, `~НЕ ОСНОВА~` — на что опираться при постройке бэкенда.

Шапка нового документа:

```text
**Статус:** ~СДЕЛАНО~
**Реализация:** ~PHP~, ~VUE~
**Опираться на:** ~МОК~
```

Старый статус прозой распознаётся по строке `**Статус:**`; нераспознанное — в разделе
«Требуют внимания». Исходная строка лежит в `doc-index.json` в поле `statusRaw`.

## Состояния по зонам

| Зона | доков | ПЛАН | В РАБОТЕ | ЧАСТИЧНО | СДЕЛАНО | ОТЛОЖЕН | ОТМЕНЁН | СНИМОК | нет |
|---|---|---|---|---|---|---|---|---|---|
| `old` | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 |
| `promts` | 2 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 2 |
| `review` | 63 | 1 | 0 | 0 | 1 | 0 | 0 | 49 | 12 |
| `rule` | 7 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 6 |
| `specs` | 22 | 2 | 0 | 0 | 0 | 4 | 0 | 5 | 11 |
| `tr` | 216 | 31 | 12 | 27 | 110 | 2 | 4 | 7 | 23 |
| `tz` | 2 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 1 |

## Модуль × состояние × слой

| Модуль | доков | ПЛАН | В РАБОТЕ | ЧАСТИЧНО | СДЕЛАНО | нет | PHP | VUE | МОК |
|---|---|---|---|---|---|---|---|---|---|
| [Core/Agent](module-map/Core/Agent.md) | 1 | 0 | 0 | 0 | 1 | 0 | 1 | 0 | 0 |
| [Core/Auth](module-map/Core/Auth.md) | 5 | 0 | 0 | 0 | 4 | 1 | 5 | 2 | 0 |
| [Core/Kernel](module-map/Core/Kernel.md) | 2 | 0 | 0 | 0 | 2 | 0 | 2 | 0 | 0 |
| [Core/Logger](module-map/Core/Logger.md) | 5 | 0 | 0 | 0 | 5 | 0 | 2 | 2 | 0 |
| [Core/Mail](module-map/Core/Mail.md) | 2 | 1 | 0 | 0 | 1 | 0 | 2 | 0 | 0 |
| [Core/SmartTable](module-map/Core/SmartTable.md) | 22 | 5 | 0 | 0 | 17 | 0 | 18 | 0 | 0 |
| `Core/UI` | 1 | 0 | 0 | 0 | 1 | 0 | 1 | 1 | 0 |
| [Core/User](module-map/Core/User.md) | 11 | 0 | 0 | 0 | 9 | 1 | 8 | 1 | 0 |
| [Messages/Chat](module-map/Messages/Chat.md) | 9 | 1 | 0 | 0 | 7 | 1 | 8 | 4 | 1 |
| [Roleplay/Character](module-map/Roleplay/Character.md) | 17 | 2 | 0 | 2 | 10 | 2 | 13 | 2 | 0 |
| [Roleplay/Game](module-map/Roleplay/Game.md) | 42 | 6 | 0 | 3 | 26 | 1 | 35 | 3 | 1 |
| [Roleplay/Keyword](module-map/Roleplay/Keyword.md) | 3 | 0 | 0 | 3 | 0 | 0 | 3 | 2 | 0 |
| [Roleplay/Mechanic](module-map/Roleplay/Mechanic.md) | 9 | 1 | 1 | 3 | 3 | 0 | 6 | 4 | 0 |
| [Roleplay/Rule](module-map/Roleplay/Rule.md) | 42 | 11 | 6 | 3 | 17 | 3 | 7 | 10 | 1 |
| [Roleplay/RuleSpace](module-map/Roleplay/RuleSpace.md) | 8 | 0 | 0 | 8 | 0 | 0 | 6 | 3 | 0 |
| [Versioning/Space](module-map/Versioning/Space.md) | 2 | 0 | 0 | 0 | 2 | 0 | 2 | 0 | 0 |
| — | 132 | 7 | 5 | 5 | 6 | 46 | 3 | 10 | 0 |

## Реализация: где есть PHP и где только мок

- **Не опираться на реализацию** (`~НЕ ОСНОВА~`): нет.
- **Только мок, PHP нет**: нет.
- **Есть PHP** — колонка `PHP` в таблице модулей ниже.

## Отложено до следующих релизов

`~ОТЛОЖЕН~` — известно, осознанно отложено, не потеряно: [battlegroundIdeaContext.md](../specs/battlegroundIdeaContext.md), [battlegroundImplementationPlan.md](../specs/battlegroundImplementationPlan.md), [battlegroundLockedDecisions.md](../specs/battlegroundLockedDecisions.md), [battlegroundPerformanceBenchmarkPlan.md](../specs/battlegroundPerformanceBenchmarkPlan.md), [battleground-system.md](battleground-system.md), [rule-content-plan-03-senses.md](rule-content-plan-03-senses.md).

## Требуют внимания

- **Статус прозой, но не распознан** (18, `docs/tr`): [TR.md](TR.md), [architecture.md](architecture.md), [auth-system.md](auth-system.md), [character-system.md](character-system.md), [chat-system.md](chat-system.md), [combat-layers-prerequisite.md](combat-layers-prerequisite.md), [contract-status.md](contract-status.md), [cross-domain.md](cross-domain.md), и ещё 10.
- Полный список: `show.php --unrecognized --zone=tr --limit=0`.
- **Планы без строки статуса** (0): нет.
- **Битые ссылки** (3):
- `docs/review/tr-code-reconciliation-2026-08.md` -> `tr-audit-2026-08.md` — исключение: снимок 2026-08-30: ссылается на документ аудита, который в репозиторий не попал
- `docs/tr/smarttable-plan-01-connection.md` -> `../../.cursor/plans` — исключение: внешний каталог планировщика, в репозитории его нет
- `docs/tr/spell-roadmap.md` -> `spell-plan-10-backend.md` — исключение: документ запланирован (веха M10, статус TODO), ещё не написан

