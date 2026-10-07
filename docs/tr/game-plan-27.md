# План Game 27 — деление повреждения на стойкость при закрытии удара

**Статус:** деление повреждения на стойкость и запись `putDamageSplit` в PHP сделаны. В [`game-roadmap.md`](game-roadmap.md) шаг не вписывается. G22 не начинается. Повреждение в итоге — пара `{base, size}`: [`game-plan-26.md`](game-plan-26.md). Kind — [`character-plan-11.md`](character-plan-11.md): `putDamageSplit`, ключи `kind`, `remainder`, `quotient`. Карточка ищется по срезу, код не зашивается: [`rule-system.md`](rule-system.md), «Экземпляр может отсутствовать». Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Закрытие `1 → 1` и `1 → N` делит уже посчитанный `injury` на пару единственной живой характеристики с `isDamageEndurance()` и в той же транзакции пишет одну операцию `putDamageSplit`. `damage`, `success`, `resistance`, `S` и `injury` в итоге не переприсваиваются. Новый kind не заводится. Rule и Character не меняются.

Сверка до плана. `DimensionalNumber::divide` уже возвращает `DimensionalQuotient`: частное `int`, остаток `DimensionalNumber` на меньшем размере. База меньше 0, делитель с базой 0 и сдвиг, который не влезает в int, бросают `RuleInvalidException`. `ICharacterActualMutations` уже принимает `putDamageSplit`. Частное `0` строку истощения не создаёт, но карточки остатка и истощения в срезе ревизии документа всё равно обязательны: нет их или их две — `CharacterInvalidException`, лист не пишется. `GameStrikeRules::operations()` возвращает `[]`. `GameStrikes::applyOperations` и `GameWideStrikes::keepEmptySheet` при непустом списке бросают `GAME_INVALID` и порт не вызывают. В `Game/` нет вызова `divide` и нет kind `putDamageSplit`. План пишется.

Зависимость — G26 и уже сделанный kind C11. Хендлеры Mechanic не ставятся.

## Физическая схема

Новых таблиц и колонок нет. Команда удара по-прежнему хранит JSON итога. В этот JSON дописывается только уже существующий `sheetVersion` принятой цели: версия после записи. Ключи `damage`, `success`, `resistance`, `injury` и при `dodge` ключ `S` остаются как в G26. Новый kind не заводится. Ключ `sheet` документа не растёт: `states` меняет порт.

## Сверка входов

- `injury` в итоге уже `DimensionalNumber`, в JSON — `{base, size}`. Деление вызывается на этом объекте до `view`. Пара в JSON не разбирается обратно.
- Стойкость — единственная живая карточка среза ревизии игры, тип `characteristic`, `getSpec()` — `CharacteristicSpec`, `isDamageEndurance()` истинно. Тот же срез, что у `evaluateSoak`: `GameStrikeRules` уже грузит его по `spaceId` и `rulesRevision` игры. `getLiveRules` tombstone не отдаёт. Код карточки в Game не пишется. Ноль или две и больше — `GAME_INVALID`.
- Пара делителя читается так же, как закупка уклонения: `ICharacterFormulaContexts::build($sheet)->findCharacteristic($code)`. `build` берёт пару из `sheet.characteristicPurchases`, формулу не считает. Лист персонажа уже читает приватный `storedSheet`, лист NPC — переданный снимок `sheet`. Нет пары — `null` и `GAME_INVALID`. Повтор кода и битый `value` — `CharacterInvalidException` из `build`, снаружи `GAME_INVALID`. Второй разбор списка закупок не пишется.
- `injury->divide(стойкость)`. Частное и остаток в лист не пересчитываются: в операцию кладутся `getRemainder()` как `{base, size}` и `getQuotient()`. Любой `RuleInvalidException` деления снаружи становится `GAME_INVALID`. Удар не закрывается.
- Операция одна: `{ kind: putDamageSplit, remainder: {base, size}, quotient }`. Других ключей нет. Частное `0` в операцию входит; строку истощения не создаёт порт. Карточки `isDamageRemainder()` и `isDamageExhaustion()` ищет порт в срезе ревизии документа, не Game. Для персонажа это `rulesRevision` строки, для NPC — `rulesRevision` уже лежащего `version`.
- Персонаж — `ICharacterActualMutations::apply` с ожидаемой версией уже проверенного листа. `apply` заново собирает лист и вызывает `validate`: `CharacterSaveRejectedException` снаружи — `GAME_INVALID`, удар не закрывается. NPC — `applyToDocument` по `choices`, `sheet`, `spaceId` и `rulesRevision` уже лежащей строки и `GameNpcRepository::replaceVersion` с той же ожидаемой версией. `applyToDocument` строку не пишет и `validate` не вызывает. Образец записи и разбора ошибок порта — `GameChecks::writeSheet`, не новый порт.
- `operations()` и оба стража пустого списка снимаются. Они порт не вызывают. Второй список операций не заводится. Закрытие пишет одну операцию само.
- `GameStrikes` (831 строка), `GameWideStrikes` (646) и `GameStrikeRules` (537, десять публичных методов вместе с конструктором) уже на пороге стандарта. `operations()` снимается, на его место встаёт один публичный метод: карточка, пара и операция. Тело — новый класс, его создаёт `GameStrikeRules`, как `GameStrikeSoaks`. Конструкторы `GameStrikes` и `GameWideStrikes` не растут. `GameWideStrikeResults` получает уже лежащий в `GameWideStrikes` порт `mutations`.

## Зафиксировано (модель и права)

**Стойкость.** Единственная живая характеристика среза с `isDamageEndurance()`. Пара — закупка листа цели. Нет карточки, их две или закупки нет — `GAME_INVALID`, удар не закрывается.

**Деление.** `injury->divide(стойкость)`. Частное и остаток в лист не пересчитываются. Деление на ноль и отрицательная база — `GAME_INVALID`.

**Запись.** Та же транзакция, что закрытие `1 → 1` и `1 → N`. Персонаж — `apply`. NPC — `applyToDocument` и версия уже лежащей строки. Одна операция `putDamageSplit`. Частное `0` порт сам не создаёт строку истощения.

**1 → N.** У каждой принятой цели своё деление и своя операция. Отказ цели, который уже ловит `refusal`, её лист не пишет: `apply` и `applyToDocument` не вызываются, `sheetVersion` остаётся `null`. Деление и запись — в `one`, пока `injury` ещё `DimensionalNumber`, снаружи `try` метода `refusal`. После `build` в итоге лежит уже `{base, size}`; из этого массива пара не собирается обратно. Отказ среза стойкости, закупки, деления или порта у принятой цели срывает всё закрытие. `commitClose` уже крутит транзакцию шлюза, и `apply` пишет через тот же шлюз, поэтому откат снимает и листы этого удара. `commitClose` порт вторым проходом не вызывает. В запись цели операция не кладётся.

**Итог.** `damage`, `success`, `resistance`, `S` и `injury` не переприсваиваются. У принятой цели `sheetVersion` становится версией после `apply` или `replaceVersion`. У отказавшей цели слоты по-прежнему пустые. Повтор уже сохранённого итога возвращает JSON команды как есть и лист второй раз не пишет.

**Порядок.** У `1 → 1` проверка версии листа, сопротивление, `S`, `success` и `injury` остаются до записи. Деление и порт — после `injury`, пока это `DimensionalNumber`, и до `strikes->close` и `commands->add`. У `1 → N` то же внутри `one`. Чужая версия боя — `GameBattleConflictException`, деление не вызывается. `ignore` и `block` делят и пишут так же: отсутствие ключа `S` запись не отменяет.

**Чего шаг не делает.** Не меняет Rule и Character. Не заводит kind. Не переписывает `damage`, `success`, `resistance`, `S` и `injury`. Не считает увечье, конец хода, снятие остатка, DOT и каст. Не начинает G22 роадмапа и не вписывает шаг в `game-roadmap.md`. Не ставит хендлеры. Не считает `N → 1`, `N → N` и сцену.

## Что даёт этот заход

Закрытие принятой цели делит `injury` на стойкость и пишет остаток и дельту истощения одним `putDamageSplit`. Итог удара по пяти величинам остаётся итогом G26. `sheetVersion` принятой цели — версия после записи.

## Что не закрыто

- G22 роадмапа целиком: этот документ его не начинает и в [`game-roadmap.md`](game-roadmap.md) не вписывается.
- Увечье, конец хода, снятие остатка.
- DOT, каст, сцена.
- `N → 1`, `N → N`.
- `personalNotes`.

## Точки кода

Старые планы не переписываются. Новых портов, action и ключей прав нет. `declareStrike` и `declareWideStrike` не меняются. Rule не меняется. Character не меняется. Новый kind не заводится.

- Новый класс Game, создаётся в `GameStrikeRules`. Карточка `isDamageEndurance()` на срезе ревизии игры, пара через `findCharacteristic` уже загруженного листа, `divide`, одна операция `putDamageSplit`. Наружу — один публичный метод вместо `operations()`.
- `GameStrikes::close` — после `injury` вызывает этот метод и пишет лист тем же `mutations`, что уже лежит в конструкторе. `applyOperations` снимается. `sheetVersion` — версия после записи. Пять величин итога не переприсваиваются.
- `GameWideStrikeResults::one` — у принятой цели после `injury` вызывает тот же метод и пишет лист. Порт приходит в конструктор из `GameWideStrikes`. Отказ `refusal` до деления не доходит. Отказ стойкости, закупки, деления или порта не ловится как `code` цели. `row` операцию не получает.
- `GameWideStrikes::commitClose` — `keepEmptySheet` снимается. Порт отсюда не вызывается: запись уже внутри `build`.

## Модуль

Нового порта Game нет. `events` остаётся `[]`. Новый код ошибки не заводится. Разбор ошибок порта — как `GameChecks::writeSheet`: `CharacterConflictException` — `GAME_CONFLICT`, `CharacterInvalidException` — `GAME_INVALID`, `CharacterNotFoundException` — `GAME_NOT_FOUND`. `CharacterSaveRejectedException` `writeSheet` не ловит; `apply` его бросает, здесь он становится `GAME_INVALID`. `RuleInvalidException` деления — `GAME_INVALID`. Удар при этом не закрывается.

| Ситуация | Код |
|---|---|
| одна карточка стойкости и одна закупка-пара | операция `putDamageSplit`, удар закрывается |
| частное `0` | операция уходит в порт, строку истощения создаёт не Game |
| нет карточки, две карточки, нет закупки, битая пара | `GAME_INVALID`, удар открыт |
| `RuleInvalidException` деления, в том числе база меньше 0, база делителя `0`, сдвиг не влезает в int | `GAME_INVALID`, удар открыт |
| нет карточки остатка или истощения в срезе ревизии документа, в том числе при частном `0` | `GAME_INVALID`, удар открыт |
| `validate` на `apply` вернул problems | `GAME_INVALID`, удар открыт |
| отказ одной цели `1 → N` | её лист не пишется, остальные принятые пишутся |
| отказ стойкости у принятой цели `1 → N` | всё закрытие срывается |
| нет строки листа или ревизии документа на записи | `GAME_NOT_FOUND`, удар открыт |
| чужая версия боя; чужой `expectedSheetVersion` у `1 → 1` | `GAME_CONFLICT`, удар открыт |
| чужая версия листа одной цели `1 → N` | удар закрывается, у этой цели лист не пишется |

## Фасад

`IGameStrikes` и `IGameWideStrikes` не растут. Закрытие после уже собранного `injury` делит и пишет. Объявление число не делит.

## HTTP

Новых action нет. `game.resolveStrike` и `game.resolveWideStrike` по-прежнему отдают `damage`, `resistance`, `injury` и при `dodge` ключ `S` объектами `{base, size}`. `success` остаётся целым. У принятой цели `sheetVersion` — версия после записи. Отказ цели `1 → N` оставляет слоты пустыми и `sheetVersion` равным `null`. Ключи входа те же.

## Тесты

Suite `game`. Меняются `GameStrikeMysqlTest`, `GameWideStrikeMysqlTest` и `GameDeliveryMysqlTest`: там `game.resolveStrike` уже закрывает удар по NPC с `ignore`, и без фикстуры этого шага успех станет `GAME_INVALID`. Других вызовов `game.resolveStrike` и `game.resolveWideStrike` в тестах нет. В ревизию игры добавляются одна характеристика `isDamageEndurance()` и две карточки `state` — `isDamageRemainder()` и `isDamageExhaustion()`. В лист принятой цели — одна закупка этой характеристики. Персонаж, которого пишет `apply`, должен проходить `validate`: порт вызывает его до записи. NPC `validate` не проходит. Suite `rule`, `character` и `mechanic` не расширяются. Код карточки в Game не зашит. Деление в тесте сверяется с `DimensionalNumber::divide`, частное и остаток в лист заново не складываются.

Mysql `1 → 1`, персонаж. После закрытия в `states` остаток `{base, size}` и при частном не `0` дельта истощения. `sheetVersion` итога — новая `actual_version`. `damage`, `success`, `resistance`, `S` и `injury` те же, что до записи. Повтор ключа лист второй раз не пишет.

Mysql `1 → 1`, NPC. `applyToDocument` и `replaceVersion` уже лежащей строки. Версия строки растёт на один.

Mysql частного `0`. Операция уходит, строки истощения нет, остаток записан.

Mysql отказа среза. Нет карточки, две карточки, нет закупки, база делителя `0` — `GAME_INVALID`, команда закрытия не появляется, лист прежний.

Mysql `1 → N`. Две принятые цели с разной стойкостью получают разные остаток и частное. Отказ одной цели её лист не пишет и не затирает запись второй. Нет карточки стойкости у одной принятой цели — `GAME_INVALID`, ни один лист удара не меняется, удар открыт.

## Todo

- [x] **split** — единственная живая `isDamageEndurance()` среза ревизии игры, пара через `findCharacteristic` листа, `injury->divide`. Нет карточки, их две, нет закупки, `RuleInvalidException` деления — `GAME_INVALID`. Отдельный разбор закупок не пишется.
- [x] **write** — в транзакции закрытия одна операция `putDamageSplit`. Персонаж — `apply`, NPC — `applyToDocument` и версия лежащей строки. Частное `0` строку истощения не создаёт. Пять величин итога не переприсваиваются. `sheetVersion` принятой цели — версия после записи.
- [x] **wide** — у каждой принятой цели своё деление. Отказ цели её лист не пишет. Отказ среза стойкости срывает всё закрытие.
- [x] **gates** — phpunit `game`. Rule и Character не меняются. Новый kind не заводится. Увечье, конец хода, снятие остатка, DOT и каст не входят. G22 не начинается. Шаг в `game-roadmap.md` не вписывается.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| деление | новый класс внутри `GameStrikeRules` | карточка, `findCharacteristic`, `divide`, операция; наружу вместо `operations()` |
| закрытие `1 → 1` | `GameStrikes` | запись сразу после `injury`, `applyOperations` снят |
| закрытие `1 → N` | `GameWideStrikeResults` | запись в `one`; `commitClose` порт не вызывает |

## Acceptance

- Стойкость — единственная живая характеристика среза ревизии игры с `isDamageEndurance()`. Пара — `findCharacteristic` по `sheet.characteristicPurchases`. Код карточки не зашит. Отдельный разбор закупок не пишется.
- Нет карточки, их две или закупки нет — `GAME_INVALID`, удар не закрывается.
- Деление — `injury->divide(стойкость)`. Частное и остаток в лист не пересчитываются. Деление на ноль и отрицательная база — `GAME_INVALID`.
- В той же транзакции персонаж пишется через `apply`, NPC — через `applyToDocument` и версию уже лежащей строки. Операция одна: `{ kind: putDamageSplit, remainder: {base, size}, quotient }`. Частное `0` порт сам не создаёт строку истощения.
- У `1 → N` у каждой принятой цели своё деление. Отказ одной цели её лист не пишет. Отказ среза стойкости срывает всё закрытие.
- `damage`, `success`, `resistance`, `S` и `injury` в итоге не переприсваиваются.
- Увечье, конец хода, снятие остатка, DOT и каст не входят. G22 не начинается. Шаг в `game-roadmap.md` не вписывается.
- Rule и Character не меняются. Новый kind не заводится.

## Документы захода

этот файл; [`game-plan-26.md`](game-plan-26.md); [`character-plan-11.md`](character-plan-11.md); [`rule-system.md`](rule-system.md); [`php-coding-standards.md`](php-coding-standards.md).
