# План Character 5 — сборка листа и валидатор (C4)

**Статус:** PHP сделан. `BACKEND_OPEN`. Нарезка — [`character-roadmap.md`](character-roadmap.md). Контракт входа — [`character-plan-01.md`](character-plan-01.md). Доплата — [`character-plan-04.md`](character-plan-04.md). Поля spec — [`decisions.md`](decisions.md) `DEC-086`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один валидатор `ICharacterSheets::validate` строит снимок и `problems` формы C0. Его позже вызовут create, update и validate-only. Записи персонажа нет.

`DEC-086` до этой правки не содержал каталог `abilities` расы и вида и называл `parent_race_code` только шагом к таблице лет. Запись поправлена: каталог читается. Модуль Rule, HTTP Rule и набор `RuleType` не закрыты.

## Зафиксировано

- Порт `ICharacterSheets::validate(CharacterRuleSlice, CharacterChoices): CharacterValidation`. Сосед: `$locator->get(ICharacterContainer::class)->get(ICharacterSheets::class)`.
- Срез уже загружен. `ICharacterRuleSlices::get` из шага не звать.
- `problems`: `code`, `message`, `path?`, `stage`. Доменный отказ — элемент списка, не `CharacterInvalidException`. Обёртка `CHARACTER_INVALID` — C5.
- Spec читать полями DEC-086, включая `race.abilities` и `species.abilities`. Чужой ключ не читать. `occupy_hands` не читать.
- Пара пути только `studyPairId` и `studyPairRole`. Пустой `magic_study.path_code` ничего не покрывает. Покрытие — этот код и транзитивный `includes_path_codes`.
- Несколько `skill_study` или денежных грантов без указателя — problem. Однозначный грант `ability` на код можно собрать без `grantedBy`.
- `abilityLevels` — максимум уровня выборов одного `ruleCode`. Флаг `automatic` уровень не подставляет. `racialAbilityCodes` — все `ability_code` каталога расы и видов по `parent_race_code`. Доплату считает только `ICharacterOsSteps::runOsSteps`.
- Строка, которую C3 превращает в binding `purchase_surcharge`, при поставке каталога не `purchase_surcharge` / `1.0.0` — problem `mechanic`. Нет строки каталога — шаг не ронять. Иная форма payload problem не является.
- `equipped` — булев выбор. В снимок надетого: `armor.strength_penalty`, `armor.max_agility`, `characteristic_limits` брони и щита. Слоты не проверяются.
- Innate, ОЛ, spent зон и остаток шопа в снимок не входят.

## Что не закрыто

- HTTP `character.create` / `character.update` / `character.validate`, запись actual, optimistic guard.
- Оплата сверх параметра расы. Хендлер параметры не читает.
- Заклинания, слоты и руки, `item_modifier`.

## Тест

Suite `character`, без MySQL. Мок `IMechanics`, движок из контейнера через порт шага «Основа».
