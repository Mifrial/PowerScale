# План 01 — вынос Messages/Inline из Chat

**Статус:** ~СДЕЛАНО~ — 2026-11-08. Шаг 0 нарезки
[`notes-roadmap.md`](notes-roadmap.md).
**Реализация:** ~VUE~
**Опираться на:** ~НЕ ОСНОВА~

Цель — забрать у Chat инлайн-примитивы и общий TipTap-редактор так, чтобы Notes
мог их использовать, не импортируя Chat. Поведение чата не меняется: Chat
реэкспортирует старые имена, Game/Rule/Character в этом шаге не редактируются.
Прямые импорты типов из Game/Rule/Character закрываются тонкими файлами-мостами
по прежним путям Chat; это временно, до перевода потребителей.

## 1. Границы

Входят:

- `Messages/Inline` как отдельный Vue-модуль;
- реестр инлайн-рендереров, источники токенов, разбор `[[type:value]]`;
- `InlineTokenPicker` для обычных textarea;
- общий `RichTextEditor` на TipTap, `RichTextHtml` для чтения;
- TipTap-узел `inlineEntity` и его NodeView;
- расширение санитайзера на inline-атрибуты;
- реэкспорт прежних символов из `Messages/Chat/init.ts`.

Не входят:

- перевод Rule-редактора, Game-чипов, Character-чата на прямые импорты Inline —
  отдельный шаг после стабилизации;
- перенос attachment-процессоров, toolbar-расширений и команд чата;
- PHP, новые таблицы, HTTP;
- Notes UI — шаг 2.

## 2. Куда переезжает

| Сейчас в `Messages/Chat` | В `Messages/Inline` |
|---|---|
| `Dto/InlineSegment.ts` | `Dto/InlineSegment.ts` |
| `Interface/IRenderer.ts` | **разделить**: Inline получает `Interface/IInlineRenderer.ts` (`component`, `describe`), Chat оставляет `IRenderer` для content-рендереров (`component`, `layout`) |
| `Interface/ITokenSource.ts`, `ITokenOption.ts` | те же пути |
| `Constant/Chat/INLINE_CONTENT_TOKEN_RE.ts` | `Constant/INLINE_CONTENT_TOKEN_RE.ts` |
| `Service/InlineContentService.ts` + instance | `Service/InlineContentService.ts` + instance |
| `Service/ChatInlineRendererRegistry.ts` + instance | `Service/InlineRendererRegistry.ts` + instance |
| `Component/InlineTokenPicker.vue` | `Component/InlineTokenPicker.vue` |

`Utils/hostInlineRendererContext.ts` остаётся в Chat: это chat-специфичный
преобразователь `ChatInlineRendererContext` в мешок рендерера. Inline его не
использует.

`IRenderer` сейчас обслуживает и inline-, и content-рендереры, но `layout`
относится только к вложениям чата. Поэтому контракт делится: Inline описывает
чип (`IInlineRenderer`), Chat — вложение (`IRenderer`). Внешние доноры
(`registerInlineRenderer`) импортируют только функции из `init.ts` и правок не
требуют.

Прямые импорты вне Chat, которые нельзя ломать в этом шаге:

- `Chat/Dto/InlineSegment` — Game-чипы и `RuleChip`;
- `Chat/Interface/ITokenSource` — `GameChatRulesContextService`,
  `CharacterChatRulesContextService`, `ChronicleEntryDialog`, `GameChatTab`;
- `Chat/Interface/ITokenOption` — `ChronicleEntryDialog`.

На прежних путях остаются файлы-мосты с одной строкой реэкспорта из
`Messages/Inline`. Это не баррели, а совместимость на один шаг; удаляются
вместе с реэкспортом `Chat/init.ts`.

`ChatInlineRendererContext` остаётся в Chat: это доменный срез чата
(`tokenLabels`, `spaceId`, `rulesRevision`). Общий Inline принимает
`InlineRendererContext = Record<string, unknown>` — ровно тот мешок, который
хост передаёт рендерерам. Chat-функция `chatInlineRendererContext()` не
меняется.

После переезда внутри Chat все импорты указывают на `Messages/Inline`.
`Chat/init.ts` сохраняет публичные имена:

- `InlineTokenPicker`, `INLINE_CONTENT_TOKEN_RE`;
- `inlineContentService`;
- `registerInlineRenderer`, `getInlineRenderer`;
- `registerTokenSource`, `getTokenSources`;
- `chatInlineRendererContext`, тип `ChatInlineRendererContext`.

Реэкспорт — временный мост. Его удаление — отдельный шаг вместе с переводом
потребителей на `Messages/Inline/init`.

## 3. Инлайн-реестр

`InlineRendererRegistry` — единственный реестр после переезда. Экземпляр
`inlineRendererRegistry` живёт в `Service/Instance/`. Chat-компоненты
(`ChatMessageRow`, `ChatFoldChrome`, `ChatFoldPanel`, `ChatListItem`) читают
его через Inline-импорт, а не через второй реестр.

`InlineContentService` принимает регэксп и функцию подписи, как сейчас.
`inlineContentService` собирается поверх общего реестра и экспортируется из
`Messages/Inline/init`; Chat-компоненты берут его оттуда, а не из Chat. Поэтому
`describe()` из Game/Rule/Character продолжает работать без правок.
`InlineTokenPicker` в `ChatInput` — асинхронный компонент из
`Chat/init`/`Inline/init`, без статического импорта чужого `.vue`.

## 4. TipTap

Новые файлы:

- `Component/RichTextEditor.vue` — редактор заметки;
- `Component/RichTextHtml.vue` — read-only просмотр HTML;
- `Component/InlineNodePicker.vue` — поиск по `ITokenSource` и вставка узла;
- `Service/Description/InlineEntityExtension.ts` — TipTap-узел;
- `Component/InlineEntityView.vue` — NodeView, берёт рендерер из реестра.

`RichTextEditor` собирает StarterKit + TableKit + `ruleLinkExtension` (чтобы
читать старые `<a data-rule-code>`) + `inlineEntityExtension`. Расширения
описания `descriptionMarkExtension` и `descriptionExpandedBlockExtension` в
заметки не входят: это домен правил, а не общий rich-text. Пропсы:

```ts
{
  modelValue: string;
  tokenSources?: ITokenSource[];
  inlineContext?: Record<string, unknown>;
  placeholder?: string;
  disabled?: boolean;
}
```

События: `update:modelValue`, `save` (Ctrl+Enter). Панель — жирный, курсив,
зачёркивание, код, заголовок, списки, цитата, таблица и «Вставить ссылку».
Отдельного меню «Правило» нет: правило приходит обычным `ITokenSource` с типом
`rule`, вставка идёт узлом `inlineEntity`, а не `<a data-rule-code>`.

HTML-контракт узла:

```html
<span data-inline-type="character" data-inline-value="3" data-inline-label="Гаррик">Гаррик</span>
```

Атрибуты: `data-inline-type`, `data-inline-value`, `data-inline-label`.
Текст внутри — подпись. `parseHTML` читает `span[data-inline-type]`,
`renderHTML` пишет те же атрибуты и подпись. Неизвестный тип рендерится
обычным текстом подписи — без падения и без запроса к хосту.

`RichTextHtml` используется только для **открытой** заметки: read-only TipTap
разбирает тот же HTML и монтирует NodeView, поэтому зарегистрированные
рендереры применяются. Список заметок остаётся на `body_text`-превью, без
TipTap на каждую карточку.

`InlineTokenPicker` не меняет контракт: это вставка `[[type:value]]` в
textarea для чата и летописи. В rich-text вставка идёт через
`InlineNodePicker`.

## 5. Санитайзер

В `DESCRIPTION_HTML_CONFIG.ALLOWED_ATTR` добавить:

- `data-inline-type`;
- `data-inline-value`;
- `data-inline-label`.

`ALLOW_DATA_ATTR` остаётся `false`: разрешены только эти три атрибута.
Остальные `data-*` по-прежнему снимаются. Санитайзер не знает типов инлайнов.

В `ALLOWED_TAGS` добавить теги, которые реально выдаёт StarterKit, но которых
сейчас нет в конфиге: `h1`, `code`, `pre`, `hr`, `s`. Иначе заметка с кодом,
зачёркиванием или горизонтальной линией молча теряет разметку при показе.
Атрибуты этим тегам не добавляются.

## 6. Порядок

1. Создать `Messages/Inline` и перенести файлы; поправить внутренние импорты.
2. Собрать общий реестр и `inlineContentService`; Chat-компоненты перевести на
   него.
3. Добавить `InlineEntityExtension`, `InlineEntityView`, `RichTextEditor`,
   `RichTextHtml`, `InlineNodePicker`.
4. Расширить санитайзер.
5. Вернуть реэкспорт в `Chat/init.ts`.
6. Прогнать тесты чата и новые тесты Inline.

## 7. Тесты

- Перенести `inlineContentService.test.ts` и `hostInlineRendererContext.test.ts`
  в `Messages/Inline/__tests__`.
- `inlineEntity`: parse/render HTML, подпись, неизвестный тип.
- `RichTextEditor`: вставка токена даёт `data-inline-*`; `update:modelValue`
  отдаёт HTML.
- Санитайзер: три inline-атрибута и `h1`/`code`/`pre`/`hr`/`s` сохраняются,
  посторонний `data-*` — нет.
- Реэкспорт: `registerInlineRenderer` через Chat и через Inline попадает в один
  реестр.
- Файлы-мосты: внешние импорты `InlineSegment`, `ITokenSource`, `ITokenOption`
  разрешаются в те же типы Inline.
- Существующие тесты Chat, Game, Rule, Character зелёные без правок.

## 8. Гейты

- `npx vitest run` по `Messages/Inline`, `Messages/Chat` и затронутым тестам;
- `npx eslint` по новым файлам;
- PHP не меняется: `composer stan` и PHPUnit не обязательны, но полный прогон
  перед сдачей остаётся финальным гейтом.
