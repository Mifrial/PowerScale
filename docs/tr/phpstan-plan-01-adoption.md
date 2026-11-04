# План PHPStan 1 — подключение статического анализа

**Статус:** ~В РАБОТЕ~
**Реализация:** ~PHP~
**Опираться на:** ~PHP~

Цель: добавить PHPStan как отдельный блокирующий гейт к существующим PHPCS/PHPUnit и убрать найденные им дефекты. Правила стека — [`php-coding-standards.md`](php-coding-standards.md) § Проверки.

## Решения

| Решение | Значение |
|---|---|
| Уровень | 5 (без `missingType.iterableValue`, который даёт +330 аннотаций на уровне 6) |
| Область | `modules`, тесты исключены — симметрично `phpcs.xml.dist` |
| Зависимость | `phpstan/phpstan: ^2.3` в `require-dev`, не phar |
| Подавления | baseline и глобальные `@phpstan-ignore` запрещены; точечный ignore — только с комментарием и после согласования |
| Порог `quality` | долг PHPCS (1057 ошибок в 178 файлах) закрывается **после** PHPStan, отдельной задачей |

Замеры на 2.3.5 до правок: level 0 — 8 находок, level 5 — 471 (373 в src, 98 в тестах), level 6 — 929; прогон 98 с, пик RSS 378 МБ.

## Этап 0 — инструмент

- [x] `composer require --dev phpstan/phpstan:^2.3`
- [x] `phpstan.neon.dist`: level 5, `paths: modules`, `tmpDir: var/cache/phpstan`, исключение `tests/`
- [x] `composer stan` → preflight + `phpstan analyse --no-progress`
- [x] `tools/phpcs/preflight.php`: проверка `vendor/bin/phpstan` рядом с phpcs/php-cs-fixer

## Этап 1 — настоящие дефекты

- [x] `Core/Kernel/Service/ModuleContainerBinder.php` — нет `use` на `Kernel\Container\ModuleContainer`
- [x] `Roleplay/Game/Service/GameStrikeTargetClose.php` — нет `use` на `Game\Exception\GameInvalidException`
- [x] `Roleplay/Game/Repository/GameBattleRepository.php` — `$cycle` не существует, `concentration_cycle` не пишется
- [x] `Roleplay/Game/Service/GameStrikeOpen.php` — `$hold` не существует, cycle уходит `null`
- [x] `Roleplay/Game/Service/GameStrikeConsequences.php` — `willForDelta()` не возвращает `outcome`/`declineCodes`, которые читаются строкой ниже; `postOutcome()` зовут с лишним аргументом
- [x] `Roleplay/Game/Service/GameEconomyApply.php` — форма `array{money, lines, version}` вместо `baseMoney`/`baseLines`
- [x] `Roleplay/Game/Service/GameStrikeResolution.php`, `Roleplay/Rule/Dto/Spec/Item/ItemModifierEffect.php` — несуществующие классы в PHPDoc типов
- [x] `Core/Mail/Service/MailPortFactory.php` — `IModuleContainer` в `IKernelContainer`
- [x] `Core/SmartTable/Service/Schema/{ColumnSchema,MfvSchema}.php` — `Fluent` → `ColumnDefinition` (там объявлен `nullable()`)

## Этап 2 — триаж 373 находок

Порядок: жёсткие → дрейф PHPDoc → мёртвые сигналы.

- [x] жёсткие (≈45): `class.notFound`, `offsetAccess.notFound`, `argument.type`, `return.type`, `variable.undefined`, `method.notFound`, `arguments.count`, `foreach.nonIterable`
- [x] дрейф контракта (≈140): `property.phpDocType`, `parameter.phpDocType`, `parameter.notFound`, `return.phpDocType`, `throws.unusedType`, `throws.notThrowable`, `return.unusedType`
- [x] мёртвые сигналы (≈190): `function.alreadyNarrowedType`, `nullCoalesce.offset`, `instanceof.alwaysTrue`, `catch.neverThrown`, `property.onlyWritten`, `identical.*`, `deadCode.*`

Правило: если guard защищает внешние данные (JSON, БД, конфиг), а тип объявлен слишком узко — правится тип, а не удаляется проверка. Удаление возможно, только когда значение заведомо внутреннее и типизировано. Изменение поведения — отдельным коммитом с пояснением. Расширять сигнатуры ради гашения ошибки запрещено.

Итог: 373 → 0 без baseline и без `@phpstan-ignore`.

Сопутствующие решения по конфигу:

- `rememberPossiblyImpureFunctionValues: false` — иначе сужение типа с первого вызова метода («если не null — throw») переносится на повторный вызов того же метода (`GameAdmissions::requestJoin`), и код после проверки выглядит мёртвым;
- `ISmartTableGateway::transaction()` документирует `@throws Throwable`: транзакция возвращает исключение работы наружу (см. `runTransaction`). Без этого PHPStan не видит транзитивные `@throws` методов, обёрнутых в `transaction(Closure)`, и считает их теги мёртвыми.

## Этап 3 — полный прогон

- [x] `tools/test-full.sh`: полоса `static` первой, лог `$OUT/static.log`, `--memory-limit=1G`, exit-код в общую проверку

## Этап 4 — документация

- [x] `docs/tr/php-coding-standards.md`: `composer stan` в итерационный список + подраздел «Статический анализ»
- [x] `AGENTS.md` § «Тесты и гейт»: `composer stan` в самопроверку, статическая полоса в полном прогоне
- [ ] `composer maps-check`, `composer docs-check`
- [ ] полный прогон `bash tools/test-full.sh` — после ручной проверки пользователем

## Сдача

`composer stan` → `composer cs-check` → `composer quality` → `composer test:unit` → затронутые модули. Полный прогон — после ручной проверки пользователем.

Состояние на момент сдачи: `composer stan` — 0 ошибок; `composer test:unit` — 719 тестов зелёные (1 skip); `cs-check` — новых ошибок не добавлено, но проект остаётся красным на старом долге PHPCS (≈80 ошибок и 164 предупреждения в 106 файлах, включая `GameStrikeRules`); `composer quality` — старый долг 1038 ошибок в 179 файлах, отдельная задача.


## Не входит

- Долг `composer quality`: 1038 ошибок в 179 файлах, включая `GameStrikeRules.php` (≈3990 строк, 58 public, complexity 371). Отдельная задача: нарезка класса и разбор остальных файлов.
- Уровень 6 и выше (`missingType.iterableValue`, `missingType.generics`), strict-rules, deprecation-rules.
- Анализ тестов, `tools/`, `config/`.
- Добавление `quality` четвёртой полосой в `test-full.sh` — после того, как он позеленеет.
