# Roleplay/Character

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Character --zone=tr`.

- **Путь:** `modules/Roleplay/Character`
- **Неймспейс:** `Mifrial\Roleplay\Character`
- **Контейнер:** `Mifrial\Roleplay\Character\Container\CharacterContainer`
- **Локатор:** `Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer`
- **Setup:** `Closure(IServiceLocator)`
- **Канон:** не зафиксирован

## Порты

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
- `Mifrial\Roleplay\Character\Action\GetCharacterListAction`
- `Mifrial\Roleplay\Character\Action\GetCharacterAction`
- `Mifrial\Roleplay\Character\Action\UpdateCharacterVisibilityAction`
- `Mifrial\Roleplay\Character\Action\UpdateCharacterOwnerNotesAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `character.create` | `CreateCharacterAction` | да |
| `character.update` | `UpdateCharacterAction` | да |
| `character.validate` | `ValidateCharacterAction` | да |
| `character.getList` | `GetCharacterListAction` | да |
| `character.get` | `GetCharacterAction` | да |
| `character.updateVisibility` | `UpdateCharacterVisibilityAction` | да |
| `character.updateOwnerNotes` | `UpdateCharacterOwnerNotesAction` | да |
| `character.applyActualPatch` | `ApplyCharacterActualPatchAction` | да |

## Контракты `Interface/`

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

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md), [Roleplay/Keyword](Keyword.md), [Roleplay/Mechanic](Mechanic.md), [Roleplay/Rule](Rule.md), [Roleplay/RuleSpace](RuleSpace.md)
- **От него зависят:** [Roleplay/Game](Game.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [character-actual-session-source-plan.md](../../../specs/character-actual-session-source-plan.md) | план | `~ПЛАН~` | — |
| [character-plan-01.md](../../character-plan-01.md) | план | `~ПЛАН~` | 2026-09-09 |
| [character-plan-02.md](../../character-plan-02.md) | план | `~ЧАСТИЧНО~` | 2026-09-10 |
| [character-plan-03.md](../../character-plan-03.md) | план | `~ЧАСТИЧНО~` | 2026-09-10 |
| [character-plan-04.md](../../character-plan-04.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-05.md](../../character-plan-05.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-06.md](../../character-plan-06.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-07.md](../../character-plan-07.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-08.md](../../character-plan-08.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-09.md](../../character-plan-09.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-10.md](../../character-plan-10.md) | план | `~СДЕЛАНО~` | — |
| [character-plan-11.md](../../character-plan-11.md) | план | `~СДЕЛАНО~` | 2026-10-28 |
| [character-plan-12.md](../../character-plan-12.md) | план | `~СДЕЛАНО~` | 2026-10-07 |
| [character-roadmap.md](../../character-roadmap.md) | план | `~СДЕЛАНО~` | 2026-10-05 |
| [character-actual-session-source-roadmap.md](../../../specs/character-actual-session-source-roadmap.md) | план | ? | 2026-09-28 |
| [character-system.md](../../character-system.md) | канон | ? | 2026-08-30 |

