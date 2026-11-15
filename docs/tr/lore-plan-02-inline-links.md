# План 02 — inline-ссылки кодекса в заметках и чатах

**Статус:** ~СДЕЛАНО~ — 2026-11-15
**Реализация:** ~VUE~, ~МОК~; PHP, схема и HTTP не меняются
**Опираться на:** ~PHP~ (`lore.findWorldPage`, `lore.findArticlePage`, `lore.getArticle`), ~МОК~ (`mockLoreApi`), ~НЕ ОСНОВА~ (реализация Notes/Chat как контракт бэкенда)

Цель: в заметке и сообщении чата можно вставить ссылку на статью кодекса, а
по клику открыть её в слайдере. Мир выбирается в самом пикере: у заметки и
чата нет собственного мира, а статья кодекса живёт ровно в одном мире.

## 1. Границы и решения

- Ссылка — inline-сущность `lore`, а не `a[data-lore-code]`: заметки хранят
  HTML, чаты — plain-text токены. Один тип для обоих хостов.
- Значение ссылки — `<worldCode>/<articleCode>`; `/` не входит в допустимые
  коды (`^[a-z0-9][a-z0-9_-]{0,63}$`), поэтому разделитель однозначен.
- В заметке подпись статьи хранится в `data-inline-label`: заметка приватна
  автору. В чате токен хранит **только** `world/article`; название резолвится
  запросом и не попадает в текст сообщения.
- Мир — область (scope) источника в общем пикере: селект «Мир» + поиск статьи.
  Источник без области работает как раньше.
- В поиске — доступные `published`-статьи выбранного мира. Черновики и архив
  не предлагаются. Secret-статья предлагается, если сервер её отдаёт.
- Клик по чипу открывает существующий `LoreArticleSlider`; вложенные ссылки
  и ссылки на правила работают внутри слайдера.
- Недоступная статья (чужая, private-мир, удалённая, secret без доступа)
  показывается как «Объект скрыт»: сервер отвечает `LORE_NOT_FOUND`.
- Кросс-мирные ссылки внутри статей кодекса по-прежнему не входят; здесь
  мир — часть ссылки, потому что заметка/чат вне кодекса.
- Новых прав и действий нет. Доступ проверяют существующие `lore.*`.

## 2. Контракт ссылки

| Хост | Форма | Подпись |
|---|---|---|
| Заметка | `span[data-inline-type="lore"][data-inline-value="myth/god"][data-inline-label="Бог"]` | `data-inline-label` |
| Чат | `[[lore:myth/god]]` | резолв по `lore.getArticle` |

- Тип `lore` регистрируется в `Messages/Inline` из `Roleplay/Lore/init.ts`.
- `describe` для превью списка чатов отдаёт подпись из токена или из кэша
  резолва; без кэша остаётся код — как у существующих токенов.
- Формат `[[type:value]]` не меняется; `InlineContentService` уже допускает
  `/` в значении.

## 3. Пикер

`Messages/Inline` получает общую панель поиска, чтобы `InlineNodePicker`
(заметки) и `InlineTokenPicker` (чаты и летопись) не дублировали скоупы:

- `ITokenSource` расширяется необязательными `scopeLabel` и
  `loadScopes(): Promise<InlineTokenScope[]>`, а `search` получает
  необязательный `scope?: string`;
- панель показывает источники, селект области, поиск, загрузку, ошибку с
  повтором и список результатов;
- смена источника сбрасывает область и запрос, смена области запускает поиск;
- устаревший ответ поиска не перетирает новый (номер запроса);
- источник без `loadScopes` работает без селекта.

Источник Lore:

```text
loadScopes -> lore.findWorldPage({ state: 'active', limit: 100 })
search(q, worldCode) -> lore.findArticlePage({
  worldCode, q, status: 'published', limit: 20, offset: 0,
})
value -> `${worldCode}/${articleCode}`, label -> title
```

## 4. Чип и слайдер

`Component/Inline/LoreInlineChip.vue`:

- разбирает значение, резолвит статью через кэш `LoreInlineArticleService`;
- состояния: загрузка (подпись из токена или «Статья»), готова (название),
  «Объект скрыт» (`LORE_NOT_FOUND`/`LORE_DENIED`), ошибка (чип кликабелен,
  слайдер покажет повтор);
- клик/Enter/Space открывает `LoreArticleLinkSlider`.

`Component/Inline/LoreArticleLinkSlider.vue`:

- грузит мир для `knownCodes` и `spaceCode`, затем показывает
  `LoreArticleSlider` с выбранной статьёй;
- вложенная `open-lore` в том же мире меняет статью; неизвестный код —
  тост «Статья ещё не написана»;
- `open-rule` резолвит пространство по `spaceCode` мира (`getSpaceByCode`) и
  открывает `RuleSlider` поверх;
- переход на страницу статьи — штатная кнопка слайдера.

`LoreInlineArticleService` — сессионный кэш `getArticle` по `world/article`:
один запрос на статью, `null` на 404/403, сетевые ошибки не кэшируются,
`peek` отдаёт название для `describe`.

## 5. Файлы

### Messages/Inline

- создать `Dto/InlineTokenScope.ts`;
- изменить `Interface/ITokenSource.ts` (область и `search(scope?)`),
  поправить импорт `ITokenOption` на собственный `Inline/Interface`;
- создать `Component/InlineTokenSearchPanel.vue`;
- упростить `Component/InlineNodePicker.vue` и `Component/InlineTokenPicker.vue`
  до меню + панель + вставка.

### Roleplay/Lore

- создать `Constant/Inline/LORE_INLINE_SOURCE.ts`;
- создать `Dto/Inline/LoreInlineRef.ts`;
- создать `Service/Inline/LoreInlineValueService.ts`;
- создать `Service/Inline/LoreInlineTokenSourceService.ts`;
- создать `Service/Inline/LoreInlineArticleService.ts`;
- создать `Component/Inline/LoreInlineChip.vue`;
- создать `Component/Inline/LoreArticleLinkSlider.vue`;
- изменить `Component/LoreArticleSlider.vue` — `knownCodes` необязателен,
  пока мир не загружен;
- изменить `init.ts` — регистрация чипа и источника в `Messages/Inline`,
  экспорт сервисов для чипа;
- тесты `__tests__/Service/loreInlineValue.test.ts`,
  `__tests__/Service/loreInlineTokenSource.test.ts`,
  `__tests__/Service/loreInlineArticle.test.ts`.

### Документы

- этот план; после реализации — `~СДЕЛАНО~` и раздел «Как сделано»;
- `lore-system.md` — §5 ссылки, §9 UI, §10 рёбра;
- `chat-system.md` — донор Lore в inline-реестре;
- `architecture.md` — фронтовое ребро `Lore → Messages/Inline`;
- `TR.md` — ссылка на план 02;
- `contract-status.md` — описание owner при необходимости;
- `decisions.md` — `DEC-094`;
- `doc-index`/`module-map` — генерация и сверка.

## 6. Не входит

- Летопись, заметки на карте и другие хосты, которые не передают глобальные
  источники;
- создание статьи из «красной» ссылки в заметке/чате;
- кросс-мирные ссылки внутри статей кодекса;
- читатели на уровне мира, гостевой доступ;
- хранение подписи статьи в chat-токене (утечка названия секретной статьи);
- глобальный поиск статей по всем мирам без выбора мира.

## 7. Проверка

- unit: сборка/разбор значения, фильтры `loadScopes`/`search`, кэш и 404
  `LoreInlineArticleService`;
- `prettier`/`eslint` по затронутым файлам;
- `npx vitest run` по `Roleplay/Lore` и `Messages/Inline`;
- `npx vue-tsc --noEmit` — в новых/изменённых файлах ошибок нет;
- `composer docs-check`, `maps-check`;
- ручная проверка:
  - заметка об игре/персонаже: вставка, сохранение, чип, слайдер, вложенная
    статья, правило;
  - игровой и обычный чат: токен, чип после перезагрузки, превью списка;
  - недоступная/несуществующая статья → «Объект скрыт»;
  - нет миров и несколько миров в селекте;
  - мок-режим;
- полный `bash tools/test-full.sh` — только после подтверждения пользователя.

## 8. Порядок

1. Общий пикер Inline: область, панель, тонкие обёртки.
2. Lore: значение, источник, кэш статей; регистрация.
3. Чип и слайдер статьи; ручная проверка.
4. Документы и индекс; после подтверждения — полный прогон.

## 9. Как сделано (2026-11-15)

- `Messages/Inline`: `ITokenSource` получил `scopeLabel`/`loadScopes` и
  `search(query, scope?)`; общая панель `InlineTokenSearchPanel` заменила
  дублирующий поиск в `InlineNodePicker` и `InlineTokenPicker`. Смена
  источника/области сбрасывает запрос, устаревшие ответы отбрасываются.
- `LoreInlineValueService` собирает/разбирает `<world>/<article>`;
  `LoreInlineTokenSourceService` отдаёт миры как области и ищет `published`
  статьи; `LoreInlineArticleService` кэширует `getArticle` на сессию
  (404/403 → `null`, сетевые ошибки не кэшируются).
- `LoreInlineChip` показывает название после резолва, «Объект скрыт» для
  недоступной статьи; `LoreArticleLinkSlider` грузит мир для `knownCodes`,
  переключает вложенные статьи и открывает `RuleSlider` по `spaceCode`.
- Регистрация — в `Lore/init.ts` через `registerInlineRenderer`/
  `registerTokenSource` из `Messages/Inline`; `Lore → Inline` добавлено в
  `architecture.md`.
- Проверено в браузере: пикер в заметке и игровом чате, вставка
  `[[lore:myth/...]]`, чип после сохранения, слайдер, вложенная статья,
  правило, «Объект скрыт» для несуществующей статьи. Тестовые статьи
  (`inline-check`, `inline-target`) заархивированы, тестовая заметка удалена.
- Тесты: 161 passed по `Messages` + `Roleplay/Lore` (25 файлов); `vue-tsc` — в
  затронутых файлах ошибок нет; `docs-check`/`maps-check` зелёные.
