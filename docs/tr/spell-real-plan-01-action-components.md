# План M1 — компоненты действия: соматика и свободные руки

**Статус:** ~ПЛАН~, 2026-11-09.

**Реализация:** ~PHP~, ~VUE~.

**Опираться на:** ~PHP~ (`Rule/Spec/ActionComponents`,
`Rule/Dto/Spec/Ability/Component/SomaticComponent`,
`Game/Service/GameWoundHands`, `GameSpellCastFacts`,
`GameActionPerformSteps`), ~VUE~ (`CharacterHandsService`,
`SpellCastDialog`, `ActionLaunchDialog`, `HitLaunchDialog`),
[`spell-real-roadmap.md`](spell-real-roadmap.md),
[`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md),
[`game-combat-readiness.md`](game-combat-readiness.md) (§4 «Модель рук»).

**Не основа:** `SpellCastExecutionService`, `MockGameApi`, `mockSpellImport` —
~НЕ ОСНОВА~.

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M1.
**Связанные:** [`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md)
(§12 «Соматика»), [`spell-plan-07-runtime-slice.md`](spell-plan-07-runtime-slice.md)
и [`spell-plan-08-runtime-expansion.md`](spell-plan-08-runtime-expansion.md)
(исторические пометки «соматика без `occupy_hands`», не контракт).

Соматический компонент — это компонент **действия**, а не заклинания.
Поэтому шаг вводит не «проверку рук для каста», а общий слой
`action_components`: любая карточка с компонентами — заклинание, обычное
действие, шаг процесса — объявляет требования, а сервер проверяет их до
списания ОД и броска. Сейчас сервер читает только ресурсные компоненты:
соматику заклинаний он молча игнорирует, а карточку действия с соматикой
отвергает как «неподдержанную».

## 1. Границы

Входят:

- единый объект `CharacterHands` в Character: `getOccupiedHands`,
  `getFreeHands`, `getItemOccupancy`, `getRequiredHands`, `canEquip`
  (выделяется из `GameWoundHands`);
- чтение `SomaticComponent::getOccupyHands()` у любой `AbilityWithComponents`;
- проверка при объявлении заклинания (`kind: 'spell'` и касание через
  `spellCast`) и не-атакующей карточки действия;
- единый источник занятости: строка инвентаря `occupyHands`, зажатая в
  `min`/`max` предмета, иначе `min`; предмет с `weapon`/`shield` без
  `occupy_hands` занимает 1 руку; `innate` не занимает;
- отказ до списания ОД, броска, записи листа и чата;
- фронт: блокировка кнопки и подсказка «нужно N свободных рук» в окнах
  каста, действия и касания; сервер остаётся авторитетом.

Не входят:

- вербальные и материальные компоненты: в текущей выгрузке их нет, а
  материал требует отдельного решения по инвентарю и расходу;
- штраф к сложности за занятые руки: канонической формулы нет, берём отказ;
- число рук по расе: сейчас 2 и на сервере, и на фронте;
- `extra_hands` профиля сверх уже существующей модели удара;
- проверка `equipped` при сохранении листа (отдельная дырка: сервер доверяет
  флагу `equipped` из `choices`);
- перенос расчёта каста или действия на клиент;
- состояния и раны как источник занятости сверх уже учитываемых зажатых ран.

## 2. Что уже есть

| Место | Что делает | Чего не делает |
| --- | --- | --- |
| `Rule/Spec/ActionComponents.php` | читает `resource`, `verbal`, `somatic`, `material`; у соматики — `note` и `occupy_hands` | не проверяет требования |
| `AbilityActionSpec`, `AbilitySpellSpec` | оба реализуют `AbilityWithComponents::getComponents()` | — |
| `GameActionResourceComponents` | выбирает из компонентов только `ResourceComponent` | соматику не видит |
| `GameSpellCastFacts::prepare` | считает доступность, мощь, контроль и траты | читает только ресурсные траты; `occupy_hands` не смотрит |
| `GameActionCards::assertComponents` | требует, чтобы **все** компоненты были ресурсными | карточка действия с соматикой получает «component is not supported on the server» |
| `GameWoundHands::freeHands` | свободные руки = `2 − min` надетого `−` зажатые раны | используется только `GameWoundPatch`; берёт `min` предмета и игнорирует строку `occupyHands`; `handCount()` — заглушка 2 |
| `GameStrikeEquipment::occupyHands` | руки оружия в конкретном ударе: удержание + `extra_hands` | это не свободные руки актора, а занятость самого действия |
| `GameManualSheetOperations::setOccupyHands` | вручную меняет занятость строки инвентаря | не влияет на объявление действий |
| `CharacterHandsService` (фронт) | `remainingHands`, `restOccupiedTotal`, `canSetEquipped` | не подключён к окнам каста и действия |
| `SpellParamsViewService` (фронт) | показывает у соматики «(1 рука)» | только отображение |
| Контент | соматика у «Разряда» (без числа, касание) и «Удара молнии» (`occupy_hands: 1`) | карточек действия с соматикой/вербаликой/материалом нет |

Вывод: проверка рук **частично есть**, но она локальная — только для зажатия
ран. Объявление каста или действия её не вызывает. Вдобавок два расчёта
занятости расходятся: раны берут `min` предмета, удар — выбранную строку
`occupyHands`. Шаг не изобретает третий расчёт, а сводит оба к одному.

## 3. Модель

### 3.1. Руки — один объект, а не счётчик

Согласен: формула и операции с руками должны жить в одном месте. Но «занять(1)»
как отдельный счётчик — ловушка: руки не являются независимым состоянием.
Их источник истины — строки инвентаря (`equipped`, `occupyHands`) плюс внешние
держатели (зажатые раны). Если завести второй счётчик, его придётся хранить,
синхронизировать с инвентарём и чинить после ручной правки листа, экипировки
или `setOccupyHands`. Рано или поздно счётчик и инвентарь разойдутся.

Поэтому «одно место» — это доменный объект `CharacterHands`, который строится
из `choices` и среза правил и сам считает:

```text
getItemOccupancy(ruleCode, rowValue)  → сколько рук занимает предмет
getOccupiedHands()                   → Σ getItemOccupancy + external
getFreeHands()                       → max(0, total − occupied)
getRequiredHands(ability)            → сумма occupy_hands соматики
canEquip(ruleCode, rowValue)         → getFreeHands() ≥ getItemOccupancy, без второго счётчика
```

- `equip` / `release` остаются операциями над строкой инвентаря, но проходят
  через `canEquip()` / `getItemOccupancy()` того же объекта. Отдельного
  `hands->occupy(1)` нет: иначе непонятно, какую строку он занимает и когда
  освобождать.
- Внешняя занятость (зажатые раны, будущие эффекты «рука занята») передаётся
  в объект явным входом `extraOccupied` или списком держателей, а не вычитается
  по месту в Game.
- Game-адаптер добавляет только игровое состояние (раны) и политику отказа;
  саму арифметику не повторяет.
- Текущие классы становятся вызывающими: `GameWoundHands`,
  `GameStrikeEquipment`, `GameManualSheetOperations`, `GameSpellCastFacts`,
  `GameActionPerformSteps`. Новых формул в них не остаётся.
- На фронте `CharacterHandsService` приводится к тем же методам и тем же
  правилам зажима, чтобы лист и боевая карточка считали одинаково.

- `occupy_hands` соматики — это **требование** «столько свободных рук в момент
  объявления», а не постоянная занятость и не дополнительная стоимость.
  После действия руки снова свободны.
- Отсутствие числа — требование `0`, компонент описательный. «Разряд» с
  соматикой «Касание цели» без числа можно творить и с занятыми руками: рука
  не обязана быть пустой.
- Несколько соматических компонентов складываются (нужно освободить сумму),
  а не берётся максимум. В текущем контенте соматика одна.
- Свободные руки:

  ```text
  freeHands = hands(2)
            − Σ занятости надетого
            − зажатые раны
  ```

  Занятость строки: `clamp(row.occupyHands ?? item.min, item.min, item.max)`;
  для предмета без `occupy_hands`, но с `weapon`/`shield` — 1; `innate` — 0.
  Для отображения `freeHands` не опускается ниже 0, для проверки достаточно
  сравнения.
- Руки, которыми выполняется само действие (удар оружием), не вычитаются
  второй раз: они уже входят в занятость надетого. Соматика проверяет
  остаток.
- Нехватка рук — **отказ** (`GameInvalidException`, `GAME_INVALID`) до
  списания ОД и броска. В чат ничего не пишется: это ошибка объявления, как
  нехватка ОД. Альтернатива «надбавка к сложности» не берётся, пока нет
  формулы и контента; при появлении канона меняется только проверка, не
  контракт компонентов.

## 4. Контракт

Доменный объект `Character/Service/CharacterHands.php`:

```php
final class CharacterHands
{
    public function __construct(
        CharacterRuleSlice $slice,
        array $choices,              // документ choices с inventory
        int $extraOccupied = 0,      // зажатые раны и другие внешние держатели
    );

    public function getTotalHands(): int;
    public function getItemOccupancy(string $ruleCode, ?int $rowValue = null): int;
    public function getOccupiedHands(): int;
    public function getFreeHands(): int;
    public function getRequiredHands(AbilityWithComponents $spec): int;
    public function canEquip(string $ruleCode, ?int $rowValue = null, ?int $exceptInventoryId = null): bool;
}
```

- `getItemOccupancy()` — `clamp(row.occupyHands ?? item.min, item.min, item.max)`;
  для предмета без `occupy_hands`, но с `weapon`/`shield` — 1; `innate` — 0;
  неизвестный код — 0.
- `getRequiredHands()` суммирует `SomaticComponent::getOccupyHands()`; `null` и
  отрицательное значение — `0`.
- `getFreeHands()` не опускается ниже 0; для проверки достаточно
  `getFreeHands() >= getRequiredHands()`.
- `canEquip()` не пишет лист: он только отвечает, поместится ли предмет с
  учётом уже надетого и `exceptInventoryId` (текущая строка при смене
  занятости). Запись остаётся в `setEquipped` / `setOccupyHands`.

Game-адаптер `Game/Service/GameActionHands.php`:

```php
final class GameActionHands
{
    public function build(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $choices,
        array $actor,          // {type: character|npc, id: int}
    ): CharacterHands;

    public function assertAvailable(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $choices,
        array $actor,
        AbilityWithComponents $spec,
    ): void;
}
```

- `build()` считает `extraOccupied` из зажатых ран (перенос из
  `GameWoundHands::heldWounds`) и возвращает `CharacterHands`.
- `assertAvailable()` сравнивает `getFreeHands()` и `getRequiredHands()` и
  бросает `GameInvalidException` с числами: `Game spell requires N free hands,
  M available` / `Game action requires N free hands, M available`.
- `GameWoundHands` становится тонкой обёрткой над `build()->getFreeHands()`
  (или удаляется, а `GameWoundPatch` получает новый сервис). Сигнатура
  `GameWoundPatch` не меняется.
- `GameStrikeEquipment::occupyHands` и `GameStrikeEquipment::gripBonus`
  используют `getItemOccupancy()`/`getTotalHands()`, чтобы не держать третий
  зажим.
- `GameManualSheetOperations::setEquip`/`setOccupyHands` проверяют через
  `canEquip()`/`getItemOccupancy()`; ручная операция ГМ может остаться
  принудительной, но зажим в `min`/`max` — общий.
- `GameActionCards::assertComponents` допускает `ResourceComponent` и
  `SomaticComponent`; `VerbalComponent` и `MaterialComponent` по-прежнему
  отвергаются с понятным сообщением.

## 5. Точки проверки

| Вход | Где проверять | Почему здесь |
| --- | --- | --- |
| `runAction`, `kind: 'spell'` | `GameSpellCastFacts::prepare` | после расчёта листа кастера, до `assertSpends` |
| касание через `strike` + `spellCast` | тот же `GameSpellCastFacts::prepare` | вызывается и при объявлении, и при завершении; проверка идемпотентна |
| `game.castSpell` (алиас) | тот же `prepare` | HTTP-слой второй проверки не делает |
| не-атакующая карточка действия | `GameActionPerformSteps::prepare` | там уже есть `choices` и `sheet`; проверка до трат |
| карточка удара с соматикой | `GameStrikeOpenPreparation` — точка расширения | в текущем контенте таких карточек нет; руки оружия уже считает `GameStrikeEquipment` |

Проверка не дублируется в `GameSpellCastHttp`, `GameRunActionSpellDeclare` и
`GameSpellTouchFinish`: все они идут через `prepare`.

## 6. Фронт

- `SpellCastDialog`: `requiredHands` из `action_components` выбранного
  заклинания и `freeHands` из `CharacterHandsService::remainingHands`.
  Кнопка «Сотворить» блокируется, рядом — «Нужно N свободных рук».
  В `spellAvailable` добавляется условие рук, но итог каста по-прежнему
  считает сервер.
- `ActionLaunchDialog`: `isActionItemDisabled()` дополнительно возвращает
  `true`, если карточке нужно больше рук, чем свободно; в подсказке — причина.
- `HitLaunchDialog` / `AttackLaunchDialog` (касание): тот же расчёт для
  `spellCast` перед отправкой; профиль касания и последняя цель не меняются.
- Общий хелпер на фронте (например, `actionHandsService.requiredHands(spec)`)
  чтобы не дублировать сумму соматики в трёх окнах.
- Ошибку сервера показываем существующим способом; отдельных кодов не вводим.

## 7. Срезы

| Срез | Содержание | Результат |
| --- | --- | --- |
| 1.1 | `CharacterHands` в Character + адаптер `GameActionHands`; на него переводятся `GameWoundHands`, `GameStrikeEquipment`, `GameManualSheetOperations`; строка `occupyHands` вместо `min` | unit-тесты; зажатие ран и удар ведут себя как раньше |
| 1.2 | проверка соматики в `GameSpellCastFacts::prepare` | «Удар молнии» требует 1 свободную руку; «Разряд» без числа не требует; отказ до трат |
| 1.3 | допуск соматики в `GameActionCards`, проверка в `GameActionPerformSteps::prepare` | карточка действия с соматикой выполняется; ресурсные карточки не изменились |
| 1.4 | фронт: блокировка и подсказка в трёх окнах | vitest; ручная проверка |
| 1.5 | гейт | `composer stan`, `composer test:unit`, Game MySQL, `npx vitest run`; полный `tools/test-full.sh` — по подтверждению пользователя |

## 8. Тесты

Unit:

- `CharacterHandsTest`: ничего не надето — 2; одна одноручная вещь — 1;
  двуручная — 0; строка `occupyHands` меньше `min` и больше `max` — зажим;
  `weapon`/`shield` без `occupy_hands` — 1; `innate` — 0; неизвестный код
  предмета; `canEquip` с `exceptInventoryId`; `extraOccupied` уменьшает
  `getFreeHands()`.
- `getRequiredHands()`: нет компонентов — 0; соматика без числа — 0; `occupy_hands:
  1` — 1; две соматики `1 + 1` — 2; отрицательное значение — 0.
- `GameActionHandsTest`: зажатые раны попадают в `extraOccupied`; отказ
  содержит требуемое и доступное число.
- `GameActionCards`: карточка с ресурсом и соматикой проходит; с вербаликой
  или материалом — отказ с понятным сообщением.

Game/MySQL:

- «Удар молнии», обе руки свободны — каст проходит;
- обе руки заняты — `GAME_INVALID`, ОД не списаны, лист и цель не изменены,
  чат пуст, открытого действия нет;
- одноручное оружие в руке и вторая рука свободна — каст проходит;
- двуручное оружие — отказ;
- «Разряд» без `occupy_hands` — двуручное оружие не мешает;
- касание: объявление и завершение дают одинаковый результат, повтор не
  списывает ОД дважды;
- карточка действия с соматикой (фикстура): свободная рука — выполняется,
  занятая — отказ до трат.

Фронт (`vitest`):

- `requiredHands` для соматики без числа, с числом и с двумя компонентами;
- `SpellCastDialog`: кнопка заблокирована и видна подсказка при нехватке рук;
- `ActionLaunchDialog`: пункт недоступен с причиной «нужна свободная рука»;
- при свободных руках поведение окон не меняется.

## 9. Риски и открытые вопросы

- **Смысл `occupy_hands`.** Мы трактуем поле как «сколько свободных рук
  требует жест». Если позже оно станет «сколько рук жест занимает
  постоянно», изменится только расчёт, не точки проверки.
- **Двуручное оружие и каст.** Отказ может оказаться слишком строгим; канон
  пока не даёт штрафа. Решение обратимо: заменить отказ на надбавку к
  сложности в одной точке.
- **Зажатые раны.** `freeHands` вычитает их как занятые руки. Для каста это
  сохраняет текущую логику ран, но стоит подтвердить у автора канона.
- **Смена `min` на строку `occupyHands`.** Меняет поведение зажатия ран и
  бонуса удержания для предметов, у которых `min < max` (например, посох).
  Нужны тесты на старое и новое поведение; если бонус удержания должен
  считаться иначе, это отдельное правило, а не второй расчёт рук.
- **Где живёт `CharacterHands`.** Объект кладётся в Character и читает
  `ItemSpec`/`SomaticComponent` из Rule. Character уже зависит от Rule, но
  если появится вторая реализация листа (например, проекция без Rule),
  понадобится отдельный порт. Это осознанный компромисс ради одной формулы.
- **`equipped` без проверки.** Сервер по-прежнему доверяет флагу из
  `choices`; неверная экипировка может дать отрицательные свободные руки.
  Для проверки требования это безопасно, но отдельная дырка остаётся.
- **Локализация ошибок.** `GAME_INVALID` несёт английский текст; окна должны
  показывать свой понятный текст, а серверный — только как запасной.

## 10. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-09 | План заведён: соматика как общий компонент действия, единый расчёт свободных рук, проверка в `GameSpellCastFacts` и `GameActionPerformSteps`. Зафиксировано, что проверка рук уже есть в `GameWoundHands`, но подключена только к зажатию ран. |
| 2026-11-09 | Расчёт рук вынесен в один доменный объект `CharacterHands` (Character): `getOccupiedHands`/`getFreeHands`/`getItemOccupancy`/`getRequiredHands`/`canEquip`. `GameActionHands` — только адаптер с зажатыми ранами и политикой отказа. Отдельного счётчика `занять(1)` нет: источник истины — строки инвентаря. |
