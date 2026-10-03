# План Character 6 — authoritative create/update (C5)

**Статус:** PHP сделан. Нарезка — [`character-roadmap.md`](character-roadmap.md). Контракт входа — [`character-plan-01.md`](character-plan-01.md). Хранение — [`character-plan-02.md`](character-plan-02.md). Срез — [`character-plan-03.md`](character-plan-03.md). Валидатор — [`character-plan-05.md`](character-plan-05.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: HTTP `character.create`, `character.update`, `character.validate` пишут actual только после того же `ICharacterSheets::validate`. Пустые `problems` — лист сервера и `actual_version`. Иначе записи нет.

`ICharacterSheets::validate` не переписан. Поля DEC-086 не расширены. Публичный list/detail, маска, `ownerNotes`, миграция ревизии, Game и чат не входят.

## Зафиксировано

- Срез: `ICharacterRuleSlices::get(spaceId, revision)`.
- Create и validate без id: право `character.create`. Update и validate с id: только владелец. Чужой id — `CHARACTER_NOT_FOUND`.
- Create не принимает наличные `money` и `expectedVersion`. После insert `actual_version = 1`. Идемпотентности нет.
- Update: нет `expectedVersion` — `CHARACTER_INVALID`. Не int — `INVALID_PARAMS` биндера. Lock — колонка `actual_version` внутри прежнего `writeGuarded`. В details конфликта — `currentVersion`. `spaceId` и `revision` строки не меняются.
- `limits.money` только на create и на validate без id. Нет ключа и `null` — потолка нет.
- Наличные update/validate — `OptionalInt`. Нет ключа — остаток из сохранённого `sheet`.
- Отказ домена — `CHARACTER_INVALID` и `problems` в details. Validate-only — 200 `{ valid, problems, sheet }` без записи.
- Колонка `choices` — вход. Колонка `sheet` — снимок валидатора и наличные. Ответ save не копирует тело клиента.
- Один guard-write: имя, `active`, choices, sheet, version+1. Видимость и заметки не трогает.
- Шоп create: бюджет + денежные гранты − `cost_gm` купленного не-innate инвентаря. Считается после `validate` и до сверки `expectedSheet`.

## Тест

Suite `character`. Отказы валидатора и упавший двойник листа не вызывают запись. MySQL: stale `actual_version` не меняет payload.
