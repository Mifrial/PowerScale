# Production reliability capability: что решили и чем закончилось

**Статус:** ~СДЕЛАНО~ (2026-11-06). Исторический документ: решение от
2026-10-09 и фактическое состояние после реализации. Это не инструкция к
работе и не канон.

**Реализация:** ~PHP~

**Опираться на:** ~PHP~

Действующий канон — [`game-combat-readiness.md`](../tr/game-combat-readiness.md)
(гейт P1) и [`damage-type-design.md`](../specs/damage-type-design.md)
(семантика среза); механика как система —
[`rule-system.md`](../tr/rule-system.md).

## Что было решено 2026-10-09

- capability — маркер `IReliabilityCut`, а не код механики, поле spec или
  клиентский флаг;
- Game спрашивает `IMechanicEngine::hasReliabilityCut()` на bindings живого
  типа урона;
- handler marker-only: `IMechanicHandler` + `IReliabilityCut`, без подписок
  на события и без побочных эффектов;
- payload привязки — `{}`;
- порядок: фильтр по типу урона → срез по надёжности → source collapse →
  penetration;
- порог: `durability === null` — слой остаётся всегда; `durability > РУ` —
  остаётся; `durability <= РУ` — снимается; defense и resistance
  обрабатываются одинаково;
- сломанная привязка (нет строки каталога, нет handler, битый id) — отказ
  `GAME_INVALID`, а не «среза нет»;
- replay не повторяет срез; single и wide используют одну семантику;
  Character и NPC — одну проекцию.

Эти пункты остаются действующим контрактом.

## Чем план разошёлся с реализацией

1. **Ключ механики.** В плане каноническим ключом назван
   `reliability_cut@1.0.0`. На деле опубликованный контент уже использовал
   `pay_sr_vs_reliability` (каталог `mechanic.id = 11`, «РУ против
   надёжностью»), и ревизия 38 привязывает его к «Режущий», «Колющий» и
   «Рубящий». Рабочим ключом стал `pay_sr_vs_reliability@1.0.0`:
   `PaySrVsReliabilityHandler` добавлен 2026-10-18 под существующий контент.
2. **Второй маркер.** `ReliabilityCutHandler` (`reliability_cut@1.0.0`) был
   добавлен 2026-10-10 по этому плану, но ни один мир его не привязывал:
   строки `reliability_cut` в каталоге не было. Удалён 2026-11-06 вместе с
   тестовым дублем; тесты Game переведены на production-код.
3. **Проверка binding.** Отдельная доработка `RuleVersionRecord` не
   понадобилась: строгая проверка строки есть и в Rule
   (`RuleVersionBody::assertMechanics()`), и в Game-адаптере
   (`GameStrikeLayers::bindings()`). Мелкое расхождение: Game-адаптер
   трактует отсутствующий `mechanic_payload` как `{}`, тогда как Rule при
   записи требует ключ; на живом контенте это не срабатывает.
4. **Penetration** остался отдельным этапом, как и планировалось.

## Где это живёт

- маркер — `Roleplay/Mechanic/Interface/IReliabilityCut.php`;
- handler — `Roleplay/Mechanic/Service/Handler/PaySrVsReliabilityHandler.php`
  (`run()` пуст намеренно: событий и оплачиваемого шага в каноне нет);
- реестр — `Roleplay/Mechanic/Service/MechanicPortFactory.php`;
- резолв — `Roleplay/Mechanic/Service/MechanicEngine.php`
  (`hasReliabilityCut()`);
- срез — `Roleplay/Game/Service/GameStrikeLayers.php` (`total()` → `kept()`);
- контент — ревизия 38 мира, механика id 11 на трёх типах урона.

## Evidence 2026-11-06

- `GameStrikeMysqlTest::testReliabilityCutRemovesLayersAtOrBelowRating` —
  слой с надёжностью не выше РУ снимается, слой без порога остаётся;
- `GameStrikeMysqlTest::testCanonicalDamageTypesResolvePublishedReliabilityBinding`
  — цепочка published damage type → catalog id → production handler;
- `GameStrikeMysqlTest::testDamageTypeWithoutReliabilityCutKeepsLayer` —
  без механики среза нет;
- `GameWideStrikeMysqlTest` — тот же путь для широкого удара;
- `GameStrikeLayersTest` — порог, `durability = null` и сдвиг единиц.

Протокол гейтов и остаток бэклога — в
[`game-combat-readiness.md`](../tr/game-combat-readiness.md).
