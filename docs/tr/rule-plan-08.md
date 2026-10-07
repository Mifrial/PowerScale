# План Rule — nullable durability слота

**Статус:** сделан, 2026-10-06. Отсутствие `durability` — абсолютная
применимость. Отдельный флаг полной надёжности не вводится.
[`rule-plan-07.md`](rule-plan-07.md) не переписывается. `game-plan-28`
использует этот nullable threshold только в частично закрытом базовом path;
полный P1 ещё не закрыт. Фронт по-прежнему шлёт целое.

## Подтверждённый канон

Сейчас [`rule-system.md`](rule-system.md) говорит, что слоты хранят `durability`. `ItemSpecs` требует целое у `DefenseSlot` и `ResistanceSlot`. `BlockProfile.defense` поля не имеет и не получает.

Разбор после этого шага:

- отсутствие ключа `durability` у обоих слотов допустимо и хранится как null;
- null — абсолютная применимость, не ноль и не порог frontend;
- явное целое остаётся threshold среза;
- прочность предмета у weapon/shield block к этому ключу не относится.

Парсер в этой сессии не ослаблялся. Шаг можно начинать отдельно от Game. Вместе с парсером правится фраза в [`rule-system.md`](rule-system.md): слот хранит `durability` или не хранит её. Frontend `DefenseSlot.durability` и `ResistanceSlot.durability` сейчас обязательный `number`. Этот шаг их не меняет: редактор по-прежнему шлёт целое, а PHP принимает и документ без ключа.

## Вход и выход

Вход — JSON spec предмета той же формы. Выход — те же DTO, у `getDurability()` тип `?int`.

Новых слоёв, порта расчёта и полей `BlockProfile` нет. `source_code` остаётся optional там, где он уже optional. У гранта `resistance` источник по-прежнему обязателен.

## Зависимости

- Канон — [`combat-layers-prerequisite.md`](combat-layers-prerequisite.md), §4.
- Потребитель null — [`character-plan-12.md`](character-plan-12.md) и будущий удар. Этот шаг их не пишет.

## Приёмка и тесты

| Случай | Ожидание |
| --- | --- |
| Целая `durability` | Как сейчас |
| Ключа нет | null, spec не битый |
| `BlockProfile.defense` | Поля по-прежнему нет |
| Грант без `source_code` | Отказ разбора, как сейчас |

Срез, проекцию и Game этот шаг не реализует.
