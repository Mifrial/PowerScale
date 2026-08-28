# Карта перераспределения правил по секциям каталога

Статус: согласованная карта для mock-seed. Этот файл является входом для
одноразовой миграции backend, а не runtime-правилом классификации.

В текущем frontend-моке согласованное дерево находится в
`Space/Mock/mockAbilitySectionTree.ts`, а карта кодов боевых способностей —
в `Rule/Mock/mockCombatAbilitySectionByCode.ts`. Это seed-данные мока, не
fallback доменного сервиса и не замена таблицам backend.

## Правила назначения

- `catalogSection` — явная секция правила в каталоге.
- `catalogSortOrder` — порядок правила внутри секции.
- `catalogRootFor` — необязательная метка корневой секции вкладки:
  `base`, `race`, `personality`, `development` или `inventory`. Она принадлежит
  узлу дерева и не дублирует ссылку правила на секцию.
- Keywords, `RuleType` и структурные блоки спеки используются только для
первоначальной подсказки.
- `parent_ability_code` не заменяется секциями: это отдельное дерево улучшений.
- `parent_race_code` не заменяется секциями: это связь наследования рас и видов.
- Если правило не подходит в существующую секцию, оно помечается как спорное,
а не отправляется автоматически в «Прочее».

## Целевая структура

```text
Основные правила
├─ Характеристики
├─ Проверки
├─ Ресурсы
└─ Детальные сцены
   ├─ Сражение
   │  ├─ Базовые атаки
   │  ├─ Удар / Бросок / Выстрел
   │  ├─ Защита и реакции
   │  ├─ Тактика
   │  ├─ Типы урона
   │  ├─ Состояния
   │  └─ Прочее
   ├─ Яды
   └─ Прочее

Расы
└─ Секции видов, записанные вручную и повторяющие дерево species
   └─ Правило вида (первое) + правила race, наследующие от этого вида

Предметы
├─ Экипировка
│  ├─ Оружие
│  │  ├─ Ближний бой
│  │  └─ Дальний бой
│  ├─ Щиты
│  └─ Доспехи
├─ Расходуемые
│  ├─ Зелья
│  └─ Магические кристаллы
└─ Прочее

Способности
├─ Врождённые
│  ├─ Характеристики
│  ├─ Общедоступные
│  └─ Прочие
├─ Личность
└─ Приобретённые
   ├─ Ментальные
   │  ├─ Восприятие
   │  ├─ Интеллект
   │  └─ Воля
   ├─ Физическое развитие
   ├─ Социальные
   ├─ Ближний бой
   │  ├─ Мастерство боя и владение оружием
   │  ├─ Оружейные навыки
   │  └─ Сражение
   │     ├─ Скорость
   │     ├─ Точность
   │     ├─ Сила
   │     └─ Прочее
   ├─ Дальний бой
   └─ Прочие
```

## Основные правила


| Кластер            | Примеры rule code                                                                                                                                                                          | Целевая секция                | Порядок                        | Обоснование                                                                    |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------- | ------------------------------ | ------------------------------------------------------------------------------ |
| Характеристики     | `strength`, `dexterity`, `endurance`, `attention`, `reaction`, `memory`, `reasoning`, `perception`, `intellect`, `medicine`, `willpower`, `communication`, `melee-combat`, `ranged-combat` | `basic-characteristics`       | По смысловой группе, затем имя | Это самостоятельные правила характеристик, не способности развития             |
| Проверки           | Правила с `CheckSpec`                                                                                                                                                                      | `basic-checks`                | Уточнить                       | Нужна отдельная проверка фактических кодов и назначения                        |
| Ресурсы            | `os`, `ol`, `or`                                                                                                                                                                           | `basic-resources`             | 10, 20, 30                     | Системные очки используются в разных вкладках                                  |
| Инициатива         | `check-initiative` / «Проверка на инициативу»                                                                                                                                                  | `basic-checks`                | После остальных корневых проверок | Это правило является `type: check`, поэтому попадает в общий раздел проверок |
| Базовые атаки      | `simple-melee-attack`, `simple-ranged-attack`                                                                                                                                              | `scenes-combat-basic-attacks` | 10, 20                         | Базовые способности запуска атаки                                              |
| Процедуры          | `strike-procedure`, `throw-procedure`, `shoot-procedure`                                                                                                                                   | `scenes-combat-procedures`    | 10, 20, 30                     | Удар, бросок и выстрел — процедуры детальной сцены                             |
| Защита и реакции   | Боевые способности/правила реакций с keywords реакции                                                                                                                                      | `scenes-combat-defense`       | Уточнить                       | Не смешивать с обычными атаками                                                |
| Тактика            | `flanking-attack` и аналогичные правила                                                                                                                                                    | `scenes-combat-tactics`       | Уточнить                       | Тактические правила выделены в отдельную секцию                                 |
| Типы урона         | `slashing`, `piercing`, `blunt`, `cutting`                                                                                                                                                 | `scenes-damage-types`         | По имени                       | `damage_type` — самостоятельные правила                                        |
| Состояния и увечья | `wound`, `maim`, `blood-loss`, `burning`, `stunned`                                                                                                                                        | `scenes-states`               | По имени                       | Увечья являются состояниями, а не способностями развития                       |
| Яды и эффекты      | `poison-scorpion`, `poison-viper` и другие `poison`                                                                                                                                        | `scenes-poisons`              | По имени                       | Яды получают собственную секцию                                                |

В текущем каталоге проверок есть: «Простая проверка», «Проверка на увечье»,
«Проверка на попадание», «Проверка на истощение», «Проверка на инициативу»,
«Проверка на общение», «Запугивание», «Убеждение», «Обман», «Обольщение»,
«Торговля», «Проверка на скрытность», «Проверка на Акробатику», а также
проверки характеристик («Проверка на Силу», «Проверка на Ловкость» и т.д.).
Все эти правила относятся к `basic-checks`; `check-initiative` не дублируется
в отдельной секции инициативы.

Правила `language` (например, «Общий язык», «Эльфийский», «Дварфийский» и
«Орочий») относятся к `abilities-acquired-mental-intellect`, поскольку являются
доменными значениями навыка «Владение языком», а не самостоятельной игровой
механикой. Правила `age` требуют отдельной проверки связи с видами; на время
миграции их секция — `races`.


### Спорные места

- «Проверка на инициативу» находится в `basic-checks`, потому что все правила
типа `check` размещаются в «Проверки». Отдельная секция «Инициатива» сейчас не
создаётся: в каталоге нет самостоятельной инициативной процедуры.

## Расы и виды

Под `races` вручную записываются секции видов. Их структура повторяет только
дерево правил `species`, а правила `race` секциями не становятся.

Текущий каркас:

```text
Расы
├─ Человек
│  └─ Человек (species, первое правило) + расы с parent_race_code=human
├─ Эльфы
│  └─ Эльфы (species, первое правило)
│     └─ Лесные эльфы
│        └─ Лесные эльфы (species, первое правило)
│           ├─ Арилет
│           ├─ Литен
│           └─ Труул
├─ Дворфы
│  └─ Дворфы (species, первое правило)
└─ Орки
   └─ Орки (species, первое правило)
```

Коды, требующие проверки при миграции:

- species: `human`, `elves`, `wood-elves`, `verto`, `dwarves`, `orcs`;
- race: `alierets`, `duariets`, `aeron`, `arilet`, `liten`, `truul`,
`orgul`, `orhan`.

Правило назначения:

- секция корневого вида находится непосредственно под `races`;
- секция дочернего вида вложена в секцию его `parent_race_code`;
- правило вида — первое правило собственной секции;
- правило race находится в секции вида, указанного в его `parent_race_code`;
- при `parent_race_code: null` у race требуется отдельное решение, так как
текущая модель допускает orphan-race, хотя доменная документация это не
считает корректным.

## Предметы


| Критерий                                                                            | Примеры                                                                                                     | Целевая секция                             |
| ----------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| `spec.weapon` и keyword `item-section-ranged`                                       | `ruchnaya-kulevrina`, `ruchnoy-arbalet`, `korotkiy-luk`, `dlinnyy-luk`                                      | `items-equipment-weapons-ranged`           |
| `spec.weapon` без дальнего keyword                                                  | `boevoy-posokh`, `kinzhal`, `sablya`, `dvuruchnyy-mech`, `boevoy-topor`, `kope`                             | `items-equipment-weapons-melee`            |
| `spec.shield`                                                                       | `malenkiy-shchit`, `klassicheskiy-shchit`                                                                   | `items-equipment-shields`                  |
| `spec.armor` или `item-section-armor`                                               | Все правила доспехов                                                                                        | `items-equipment-armor`                    |
| keyword `potion`/`item-section-potion`                                              | `maloe-zele-vosstanovleniya`, `boevoe-zele-iroda`, `pot-ogra`, `zele-lozhnoy-zhizni`, `zele-kontsentratsii` | `items-potions`                            |
| keyword `artifact`/`item-section-artifact` и `crystal`/`item-section-crystal`       | «Кристалл отворота», «Кристалл одностороннего купола», «Кристалл электромагнитного щита», варианты `3↓`, `4↑` и т.п. | `items-consumables-magic-crystals`         |
| `item-section-goods`                                                                | Товары                                                                                                      | `items-other` или отдельная секция товаров |
| `item-section-crystal`                                                              | Магические кристаллы                                                                                        | `items-consumables-magic-crystals`         |
| `item-section-other`                                                                | Кристаллы, бомбы, материалы и неизвестные предметы                                                          | `items-other`                              |


`group_code` у предметов описывает игровую группу предметов и не определяет
секцию каталога.

Навыки, в требованиях которых есть `min_weapon_mastery`, всегда относятся к
`Способности → Приобретённые → Ближний бой → Оружейные навыки`, даже если в
названии или описании встречаются слова «атака» или «удар». Это отделяет
владение конкретным оружием от универсальных веток быстрых, точных и силовых
ударов.

`weapon_family` относится к ветке оружия, а `item_modifier` и
`item_modifier_type` — к `items-other`: это служебные правила предметного
каталога, не отдельные игровые предметы.

## Способности

### Врождённые

Кандидаты:

- `keen-hearing`, `night-vision`, `humanoid-body` и аналогичные `trait` —
`abilities-innate-common` или `abilities-innate-other`;
- способности, связанные с органами чувств и врождёнными особенностями, —
`abilities-innate-other` до отдельного решения;
- правила, которые дают врождённый бонус к характеристике или даруются на
стадии «Основа», идут в `abilities-innate-characteristics`;
- правила типа `characteristic` не дублируются здесь: они идут в
`basic-characteristics`.

### Личность

Кандидаты:

- `sociability`, `attentiveness`, `wealth` и связанные черты —
`abilities-personality`;
- группы `appearance`, `voice`, `hearing`, `vision`, `sociability`,
`attentiveness`, `wealth` — пока не отдельные секции, а дополнительные
признаки для уточнения migration map.

### Приобретённые


| Кластер/примеры                                                                           | Целевая секция                                                                       | Источник подсказки                      |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ | --------------------------------------- |
| `razvitie-vospriyatiya`, `orientirovanie`, `orientirovanie-po-sledam` и методы perception | `abilities-acquired-mental-perception`                                               | keywords `method-perception`, aggregate |
| `razvitie-intellekta`, `razvitie-pamyati`, `razvitie-myshleniya` и их улучшения           | `abilities-acquired-mental-intellect`                                                | keywords `method-intellect`, aggregate  |
| `kontsentratsiya` и семь её улучшений                                                     | `abilities-acquired-mental-intellect`                                                | `parent_ability_code` и назначение      |
| `vladenie-yazykom` и языковые навыки                                                      | `abilities-acquired-mental-intellect`                                                | `multiple`, `domain_ref`, keywords      |
| `pervaya-pomosch` и медицинские улучшения                                                 | `abilities-acquired-medicine`                                                        | legacy `section-medicine`               |
| `trenirovka-voli`                                                                         | `abilities-acquired-mental-will`                                                     | legacy `section-willpower`              |
| `fizicheskoe-razvitie`                                                                    | `abilities-acquired-physical`                                                        | legacy `section-body`                   |
| `skrytnost`, `akrobatika`                                                                 | `abilities-acquired-physical` или `abilities-acquired-other`                         | keywords и смысл способности            |
| `manernost`, `etiket`                                                                     | `abilities-acquired-social`                                                          | `parent_ability_code`, social keywords  |
| оружейные навыки из `mockWeaponSkillsImport.ts`                                           | `abilities-acquired-melee-weapon-skills`                                             | weapon keywords                         |
| `blizhniy-boy`, `prikrytie`, `sovmestnaya-ataka`                                          | `abilities-acquired-melee-combat-other`                                              | боевые keywords                         |
| улучшения скорости/точности/силы                                                          | соответствующая секция `...-combat-speed`, `...-combat-accuracy`, `...-combat-power` | `parent_ability_code` и смысл           |
| остальные навыки развития                                                                 | `abilities-acquired-other`                                                           | требует индивидуальной проверки         |


`parent_ability_code` сохраняется поверх секционной сортировки. Если улучшение
попадает в другую секцию, это должно быть явно подтверждено: автоматический
перенос родителя запрещён.

## Требует решения перед миграцией

1. Требуется проверить полный список кодов правил `CheckSpec`; все они должны
  попасть в `basic-checks`, включая `check-initiative`.
2. Нужно подтвердить, что товары и материалы остаются в `items-other`, а не
  получают отдельные секции.
3. Мутагены не получают секцию: в текущей выгрузке таких правил нет.

