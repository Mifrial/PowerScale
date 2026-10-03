# План Character 8 — миграция ревизии (C7)

**Статус:** PHP сделан. Нарезка — [`character-roadmap.md`](character-roadmap.md). Контракт входа — [`character-plan-01.md`](character-plan-01.md). Хранение — [`character-plan-02.md`](character-plan-02.md). Валидатор и save — [`character-plan-05.md`](character-plan-05.md), [`character-plan-06.md`](character-plan-06.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: HTTP `character.migrate` переводит лист на другую ревизию того же мира. Пишет actual только после того же `CharacterSaveAssembly`, что update, с `shopCreate=false`. Game, бонусы мастера, смена мира, история `CharacterVersion` и runtime вне create не входят.

## Зафиксировано

- Вход: `id`, `expectedVersion`, `revision` цели. Ключа `spaceId` нет. Лишний ключ — `INVALID_PARAMS` биндера. Та же ревизия, нет `expectedVersion` или неполное тело продолжения — `CHARACTER_INVALID` до сборки. Нет ревизии — `CHARACTER_NOT_FOUND`, как у среза. Чужой лист — `CHARACTER_NOT_FOUND`. Устаревшая version — `CHARACTER_CONFLICT` в `writeGuarded`.
- Нет полей листа — ремап сохранённых choices. Поля листа — `Optional*`: хотя бы один ключ значит продолжение и требует `name`, `limits`, `raceCode`, `abilities`, `inventory`, `characteristicPurchases`, `customRules`. Второй ремап не делается.
- Живой код — `findLive`. Нет живого, включая tombstone: пустой `raceCode`, способность снимается, предмет теряет `ruleCode` и получает `custom` с именем и описанием из исходного среза. `characteristicPurchases` и `modifiers` не разбираются. `limits.money` с сохранённых choices снимается и во входе запрещён. Наличные — остаток sheet, если ключа `money` нет.
- `conflicts` — 200 `{ kind, problems, revision, choices, sheet }`, записи нет. `ok` — problems пуст и ремап ничего не менял. `resolved` — problems пуст и ремап что-то снял или превратил в custom, либо это продолжение с телом. Запись — `ICharacters::replaceMigrated`: имя, active, choices, sheet, `rules_revision`, `actual_version` +1. Видимость и заметки не трогает. Create, update и validate ревизию по-прежнему не меняют.

## Тест

Suite `character`. Конфликты, tombstone-предмет, чистый ремап, снятая способность, неполное продолжение и продолжение без второго ремапа — без MySQL. MySQL: `replaceMigrated` пишет ревизию и version одним bump; устаревшая version строку не меняет.
