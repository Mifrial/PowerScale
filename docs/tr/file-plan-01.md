# План File 1 — Core/File: хранение и отдача файлов

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~, ~МОК~

**Опираться на:** ~PHP~ (`Kernel`, `SmartTable`, `User`)

Модуль `Core/File` (`Mifrial\Core\File`, `www/mifrial/modules/Core/File`)
принимает байты, хранит их на диске, отдаёт метаданные строкой и байты
отдельным HTTP-entrypoint'ом. Фронтенд — `Core/File` в
`draft-front_1.2ds/src/modules/`.

Модуль отвечает только за файл: байты, метаданные, URL, доступ к байтам и
архив. Что файл значит (аватарка, подложка), какие у него ограничения по
содержанию и когда его заменять — ответственность потребителя.

## 1. Место и роль

- `Core/File` — eager-модуль: `ModuleManager::loadCore()` сканирует
  `modules/Core`, поэтому `config/modules.php` не меняется.
- Публичная точка бэкенда для соседей:
  `$locator->get(IFileContainer::class)->get(IFiles::class)`.
- Публичная точка фронта: `registerFileApi()` / `getFileApi()` из
  `Core/File/init.ts`.
- Таблица `file` — только через SmartTable. В модуле нет SQL-выражений,
  `PDO` и `Illuminate`: `Table/FileTable` — карта,
  `Repository/FileRepository` работает через
  `ISmartTableGateway::open(FileTable::class)->records()`,
  `Schema/FileSchema` — через `->schema()`.
- Байты лежат на диске, метаданные — в SmartTable. Второго источника истины
  нет: путь читается из строки, обратного вычисления нет.

## 2. Идентичность

- `id` — автоинкремент, внутренняя идентичность. Чужие таблицы ссылаются на
  него через `FileReferenceField` (§4), то есть с физическим FK.
- `public_id` — внешняя идентичность: 32 hex (16 случайных байт),
  `UNIQUE`, генерируется сервером, никогда не переиспользуется. Только он
  попадает в URL и в проекции потребителей. Перед записью файла сервис
  проверяет, что строки с таким id нет, и при совпадении генерирует новый;
  `UNIQUE` — финальный предохранитель.
- Разделение обязательно: перебираемый `id` нельзя ставить в URL, а FK по
  `public_id` лишает целостности и типизации.

## 3. Таблица (SmartTable)

Карта — `Core/File/Table/FileTable.php`:

```php
final class FileTable extends SmartTableDefinition
{
    protected function tableName(): string
    {
        return 'file';
    }

    protected function defineFields(): array
    {
        return [
            new IdField(),
            new StringField('public_id', FieldSettings::fromOptions(['required' => true, 'unique' => true]), 32),
            new UserReferenceField('owner_id', FieldSettings::fromOptions(['required' => true, 'indexed' => true])),
            new StringField('original_name', FieldSettings::fromOptions(['required' => true])),
            new StringField('mime', FieldSettings::fromOptions(['required' => true]), 127),
            new IntField('size', FieldSettings::fromOptions(['required' => true]), 0, 33554432),
            new StringField('sha256', FieldSettings::fromOptions(['required' => true]), 64),
            new StringField('storage_path', FieldSettings::fromOptions(['required' => true]), 255),
            new IntField('width', FieldSettings::fromOptions([]), 1, 20000),
            new IntField('height', FieldSettings::fromOptions([]), 1, 20000),
            new BoolField('is_archived', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
                'indexed' => true,
            ])),
            new DateTimeField('archived_at', FieldSettings::fromOptions([])),
            new DateTimeField('created_at', FieldSettings::fromOptions([
                'required' => true,
                'default' => DateTimeNow::instance(),
            ])),
        ];
    }
}
```

- `IdField()` без `big()`: ссылки на файл объявляются `ReferenceField` (INT).
  `IdField::big()` дал бы BIGINT и физический FK с INT-полем не создался.
- `owner_id` — `UserReferenceField`, `onDelete: restrict`; удаление учётки
  файлы не уносит.
- `public_id` — единственный `UNIQUE`.
- `mime` — серверное определение типа, не клиентский заголовок.
- `size` — предохранитель `IntField` на 32 МБ (общий потолок, не политика
  потребителя). Верхняя граница карты согласована с дефолтным
  `files.max_size`; поднимать лимит конфигом без правки карты нельзя.
- `sha256` — ETag и контроль целостности. Индекс не нужен: по хешу не ищем.
- `storage_path` — относительно `files.path` (`ab/cd/<public_id>`), чтобы
  переезд стенда не ломал строки. Имя известно до вставки, поэтому строка
  пишется один раз, без «вставить → узнать id → дописать путь».
- `width`/`height` — размеры картинки: потребитель получает размеры
  подложки, а строка подтверждает, что файл действительно изображение.
  При дефолтном `files.mime` (только картинки) заполнены всегда; колонки
  nullable, чтобы расширение `mime`-политики не требовало миграции.
- `is_archived`/`archived_at` — архив. Архивная строка: метаданные читаются,
  байты не отдаются.
- `created_at` — `DateTimeNow`, как в остальных таблицах.

Индексы: `public_id` (`UNIQUE`), `owner_id` (FK-индекс MySQL + явный `idx`),
`is_archived`.

## 4. Поля-ссылки и уборка

### 4.1 `UserReferenceField` и `UserLinkSetField` (`Core/User/Field/`)

Ссылка на учётку сейчас объявляется двумя способами: импортом чужого
`Core/User/Table/UserTable::class` (`Auth`, `User`, `RuleSpace`) и магической
строкой `ReferenceField::forTable(…, 'user')` (`Game`, `Character`, `Chat`).
Для набора ссылок — то же самое: `GameTable::whitelist` пишет `'user'`.
Оба способа плохи: первый тянет чужой внутренний `Table/`, второй ломается
молча при переименовании.

`ReferenceField` и `LinkSetField` перестают быть `final`; появляются два
публичных типа модуля:

```php
final class UserReferenceField extends ReferenceField
{
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        string $onDelete = 'restrict',
    ) {
        parent::__construct($fieldName, $fieldSettings, UserTable::class, $onDelete);
    }
}

final class UserLinkSetField extends LinkSetField
{
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        string $onDelete = 'restrict',
    ) {
        parent::__construct($fieldName, $fieldSettings, UserTable::class, $onDelete);
    }
}
```

- `type()` остаётся `reference` / `linkset`, метаданные и DDL не меняются.
- Цель — class-string, поэтому SmartTable проверяет её при сборке карты, а
  `FieldPathWalker` для reference резолвит цель напрямую, без словаря.
- `onDelete` у linkset — `restrict` или `none`, как в базовом классе; обёртка
  не расширяет допустимые значения.
- `Core/SmartTable/README.md` и `docs/tr/smarttable.md`: специализация поля
  наследованием разрешена, новый `type` вводить нельзя, правила `onDelete`
  и `assertReferenceSettings`/`assertLinkSetSettings` наследник не обходит.

### 4.2 `FileReferenceField` (`Core/File/Field/`)

`final class FileReferenceField extends ReferenceField` с целью
`FileTable::class` и `onDelete` по умолчанию `restrict`: потребитель не
импортирует `Core/File/Table/FileTable.php`, а тип цели проверяется
SmartTable. Поле объявляется вместе с модулем (F1) и готово для будущих
потребителей; в этом плане потребителей нет — ни `User.avatar_file_id`, ни
колонки сцены не добавляются. [`user.md`](user.md) оставляет аватар
отдельным решением (тип `file`/`picture`, не `int avatar_file_id`), а
физика сцены — `OPEN` в [`battleground-system.md`](battleground-system.md).

Ограничение: конструктор `ReferenceField` инстанцирует цель
(`targetTableName()` → `new $targetClass()`), поэтому взаимные class-string
ссылки двух таблиц дают рекурсию. Пока `FileTable` ссылается на
`UserTable`, `UserTable` не должен получить `FileReferenceField`; такой
потребитель решается отдельно (поле-тип или резолв id на уровне сервиса).

### 4.3 Уборка существующих объявлений

`UserReferenceField` заменяет текущие `UserTable::class` и
`forTable(…, 'user')` в 15 таблицах:

- `Core/Auth`: `UserIdentityTable`, `AuthPasswordResetTable`,
  `AuthSessionTable`;
- `Core/User`: `UserGroupMemberTable`;
- `Roleplay/RuleSpace`: `RuleSpaceMetaTable`;
- `Roleplay/Game`: `GameTable`, `GameMemberTable`, `GameInvitationTable`,
  `GameCharacterTable`, `GameChronicleEntryTable`, `GameJoinRequestTable`;
- `Roleplay/Character`: `CharacterTable`, `CharacterViewerTable`;
- `Messages/Chat`: `ChatMessageTable`, `ChatMemberTable`.

`UserLinkSetField` заменяет `LinkSetField::forTable(…, 'user')` в
`Roleplay/Game/Table/GameTable.php` (`whitelist`).

Замена сохраняет `required`, `indexed` и `onDelete` каждого поля: у
`AuthPasswordResetTable.user_id` и `AuthSessionTable.user_id` стоит
`cascade`, и обёртка обязана получить его явно, иначе DDL тихо сменится на
`restrict`. `AuthSessionTable.user_id` при этом остаётся `required: false`.

Не трогаем legacy-фикстуру
`Core/User/tests/Fixture/UserGroupMemberLegacyTable.php` — она намеренно
описывает старую схему.

Переход с физического имени на class-string меняет только резолв цели в
`FieldPathWalker` (словарь больше не нужен); DDL, имена констрейнтов и JSON
остаются прежними. Поэтому прогоняются тесты `Auth`, `User`, `RuleSpace`,
`Game`, `Character`, `Chat`, а не только SmartTable.

## 5. Диск

- Корень — `files.path`, по умолчанию `www/mifrial/var/file`.
- Путь: `ab/cd/<public_id>` по первому и последнему байту `sha256`,
  без расширения — тип берётся из строки. `public_id` известен до вставки,
  поэтому одна запись строки и один `rename`; `id` в путь не попадает.
- Запись: сгенерировать `public_id` → проверить PHP-tmp → `rename` в шард →
  вставить строку. `rename` атомарен, частично записанных файлов в шардах
  нет; если вставка не удалась, файл удаляется. Отдельного
  «осиротевшего» состояния в шардах нет. Если PHP-tmp и `files.path` на
  разных ФС, `rename` не срабатывает, выполняется `copy` + `unlink`.
  Каталога `var/file/tmp` нет: промежуточный шаг не нужен, PHP-tmp уже
  временный.
- Каталоги `0770`, файлы `0640`; шардовые каталоги создаются лениво при
  первой загрузке.
- `www/mifrial/var` принадлежит разработчику и `www-data` в него писать не
  может, поэтому `install-local.sh` (root) заранее создаёт `var/file` с
  владельцем Apache-пользователя; иначе первая загрузка падает на правах,
  а не на коде.
- `www/.htaccess` запрещает всё в `mifrial/`, кроме разрешённых
  `API/*.php`, поэтому байты отдаёт только PHP с проверкой доступа.

## 6. Настройки

`config/local.php.dist` и `config/test.php.dist` — ключ `files`. Рабочие
`config/local.php` и `config/test.php` в репозиторий не входят: ключ
добавляется в них локально (install/вручную), код без ключа не падает.

Разбор — `Dto/FileSettings::fromSection()`, по образцу `AuthSettings` и
`MailSettings`. Срез берётся из
`$serviceLocator->get(IKernelContainer::class)->get(IRuntimeConfig::class)->section('files')`;
дефолтный `path` передаёт `FilePortFactory` (`dirname(__DIR__, 4) . '/var/file'`),
а не `Dto` — `__DIR__ . '/../var/file'` внутри `Dto/` указывает не туда.

```php
'files' => [
    'path' => __DIR__ . '/../var/file',
    'max_size' => 33554432,
    'mime' => ['image/png', 'image/jpeg', 'image/webp'],
],
```

`max_size` и `mime` — общий предохранитель хранилища. Более строгие правила
(например, «аватарка ≤ 5 МБ») проверяет потребитель перед тем, как
прикрепить файл.

## 7. Транспорт

Обычные действия ходят JSON-конвертом; байты в JSON не кладём (base64 даёт
+33% к размеру и поднимает файл в память). Поэтому upload — отдельный
entrypoint с телом `multipart/form-data`, а ответ — тот же JSON-конверт;
скачивание — второй entrypoint рядом с `API/action.php` (тот же приём, что
у SSE). `www/.htaccess`:

```apache
RewriteRule ^api/file/upload$ mifrial/API/file-upload.php [L,QSA]
RewriteRule ^api/file/([a-f0-9]{32})$ mifrial/API/file-get.php?publicId=$1 [L,QSA]
```

и оба файла добавляются в allowlist строки
`RewriteRule ^mifrial/(?!API/(?:action|chat-sync|game-sync)\.php$) - [F,L,NC]`.

`POST /api/file/upload` (multipart, одно поле `file`):

- bootstrap как у `chat-sync.php`: `HttpRequest::fromGlobals()` +
  `Application::prepareHttp()` (привязка актора);
- CSRF — `new CsrfGuard()` + `isValid($httpRequest)`; отказ — конверт с
  кодом `CSRF` (HTTP 403), как у маршрутов action;
- актор — `IUserAccess::requireActor()`, владелец — `getUserId()`;
- вход — `getFileMap()` → `Dto/IncomingUpload`; распаковка `$_FILES`,
  проверка `UPLOAD_ERR_*` и `CONTENT_LENGTH` (превышение `post_max_size`
  даёт пустой `$_FILES` — это `FILE_TOO_LARGE`/`FILE_UPLOAD_BROKEN`,
  а не «нет файла»);
- ответ — `ActionResponse::ok(...)` с JSON-видом записи, ошибки —
  `ActionResponse::fail(...)` через `Application::emitJson`.

`GET /api/file/{publicId}` — байты; безопасный метод, CSRF не нужен, актор и
строка проверяются. Ошибки этого entrypoint'а — статус без JSON-конверта
(§8), а не `Application::emitHttpError`: конверт здесь не нужен, а
`Cache-Control: no-store` и 404 не выражаются текущим `ResponseEmitter`.

Вход: в `Kernel` добавляются багаж `files` в `HttpRequest::fromGlobals()`,
геттер `getFileMap()` в `IHttpRequest`/`HttpRequest` и тест. Больше `Kernel`
не меняется: `postForm` и байтовый эмиттер живут в `Core/File`/`Core/Engine`,
а HTTP-граница остаётся в одном месте.

Лимиты PHP: сейчас `upload_max_filesize=2M`, `post_max_size=8M` (Apache
mod_php 8.2). `www/mifrial/install-local.sh` идемпотентно пишет
`/etc/php/*/apache2/conf.d/99-powerscale-upload.ini`
(`upload_max_filesize=40M`, `post_max_size=48M`) и делает reload; версия PHP
не хардкодится. Чтение потоковое (`hash_file`, `getimagesize`, `rename`/
`copy`), в память целиком не поднимаем. `move_uploaded_file` не используется:
диск спрятан за `IFileStorage`, а PHP-tmp проверяется на месте и переносится
в шард.

## 8. Отдача и кеш

```text
Content-Type: <mime из строки>
Content-Length: <size>
Cache-Control: private, max-age=31536000, immutable
ETag: "<sha256>"
X-Content-Type-Options: nosniff
Content-Security-Policy: default-src 'none'
Content-Disposition: inline; filename*=UTF-8''<original_name>
```

- `immutable` корректен, потому что байты и URL неизменяемы: новая загрузка
  — новая строка и новый `public_id`. Переиспользовать URL нельзя.
- `private` — кеширует браузер, не общий прокси; CDN в этом плане нет.
- `Vary` не ставим.
- `If-None-Match` совпал → `304` с теми же `Cache-Control`/`ETag`, без тела.
- Ошибки байтового endpoint'а — голый статус с `Cache-Control: no-store`,
  без JSON-конверта: `403` нет актора, `404` нет строки или файл в архиве.
  JSON-конверт остаётся транспортом action/upload, а не потока байтов.
- Байты отдаёт `Service/FileByteEmitter` с внедряемым `write` (по образцу
  `SseEmitter`): он же ставит заголовки, `ETag`, `304` и ошибочные статусы,
  поэтому `ResponseEmitter` менять не нужно.
- `file-get.php` берёт строку через `IFiles::requireReadable($publicId)`
  (актор + запись), архивную отбивает `404`, затем отдаёт байты эмиттером;
  отдельного второго правила доступа у endpoint'а нет.
- Фронт показывает картинки через `<img src>` (`FileImage.vue`), не через
  `fetch`→`blob:`: blob обходит HTTP-кеш.
- На endpoint нельзя вызывать `session_start()`: нативные сессии PHP
  добавляют `no-store`/`no-cache` и убивают кеш. Проект их не использует.

## 9. Доступ

- Загрузка — только аутентифицированный актор; владелец = актор.
- Чтение байтов — аутентифицированный актор + знание `public_id`.
  Это capability URL: 128 бит в URL, отдельной таблицы прав нет. Нет
  актора — `403`; нет строки или она в архиве — `404`.
- Архив (`IFiles::archive`) — только владелец.
- Архивный файл: байты 404, метаданные через порт читаются.
- Строгий контекст (участник игры, видимость сцены) в `Core/File` не
  реализуется: файл не знает про Game. Если появится секретный контент —
  отдельный резолвер доступа, контракт URL не меняется.

## 10. Валидация

До записи:

- размер ≤ `files.max_size` (`FILE_TOO_LARGE`, в `details` фактический лимит);
- mime из `finfo_file` входит в `files.mime` (`FILE_MIME_INVALID`);
  клиентский `Content-Type` не используется;
- при дефолтном `files.mime` (только картинки) `getimagesize` даёт
  `width`/`height`, каждый ≤ 20000 (`FILE_IMAGE_INVALID`); для не-картинки
  допускается `null`;
- `original_name` — непустое, ≤ 255 символов, без управляющих символов;
  в путь не попадает;
- `sha256` считается по принятому файлу; расширение выводится из mime;
- битый/недописанный upload (`UPLOAD_ERR_*`, пустой `$_FILES` при
  непустом `CONTENT_LENGTH`) — `FILE_UPLOAD_BROKEN`, а превышение
  `post_max_size` — `FILE_TOO_LARGE`.

HTTP-ошибки — наследники `ActionException` (как `LoggerException`), иначе
`Dispatcher`/`emitHttpError` отдаст `INTERNAL` вместо `FILE_*`. Дисковые
сбои — отдельный `MifrialException` без HTTP-кода (журналируются как
`INTERNAL`). `FILE_DENIED` и `FILE_NOT_FOUND` мапятся на `403`/`404` только
в байтовом endpoint'е; в JSON-действиях остаются конверт и HTTP 400.

## 11. Контракты

Бэкенд, `Interface/Service/IFiles.php`:

```php
interface IFiles
{
    /** Строка по public_id; нет строки — FILE_NOT_FOUND. Архивная строка читается. */
    public function requireReadable(string $publicId): FileRecord;

    /** Строка, если владелец — текущий актор; иначе FILE_DENIED. */
    public function requireOwned(string $publicId): FileRecord;

    /** Архив владельцем; чужая строка — FILE_DENIED. */
    public function archive(string $publicId): void;
}
```

Порт принимает `public_id`, а не внутренний `id`: §2 запрещает внутренний id
во внешних входах и проекциях, а потребитель получает от клиента именно
`publicId` после upload. Внутренний id остаётся в Repository/`FileTable` и в
админских действиях (§11.1).

`requireReadable` первым делом требует актора, поэтому порт нельзя случайно
использовать в CLI. `requireOwned` — для потребителя, который прикрепляет
файл к своей сущности и не должен принимать чужой файл. Архивная строка
читается обоими методами (метаданные живы), но байты по ней не отдаются.
CLI-сценарии работают через репозиторий модуля.

`FileRecord` (`Dto/FileRecord.php`) — Record с геттерами по `DEC-079`:
`getId`, `getPublicId`, `getOwnerId`, `getOriginalName`, `getMime`,
`getSize`, `getWidth`, `getHeight`, `getSha256`, `getStoragePath`,
`isArchived`, `getArchivedAt`, `getCreatedAt`. `getStoragePath` — для
чтения байтов внутри модуля, в JSON не попадает. `fromNormalized` — граница
Repository → домен; JSON-вид собирает `FileViewAssembler` (`createdAt` —
unix, `width`/`height` — `null` допустим).

### 11.1 Админская страница «Файлы»

Поверхность — по образцу журнала Logger: таблица с фильтрами и деталка.
Страница административная, личного экрана «Мои файлы» нет (если понадобится —
отдельный модуль диска поверх `Core/File`).

Право — `file.view`:

- бэкенд: `Service/FilePermissionKeys.php`, `VIEW = 'file.view'`,
  проверка через `IUserAccess::requireKey`;
- фронтенд: `Constant/Permission/FILE_PERMISSION_CATEGORY.ts`,
  `Constant/Permission/FILES_ADMIN_SECTION.ts`,
  `Constant/Navigation/FILES_MENU_ITEM.ts`, регистрация в
  `registerFileModule()`.

HTTP-действия (CSRF включён, оба требуют `file.view`):

| Action | Назначение |
|---|---|
| `file.findPage` | страница файлов: `limit`, `offset`, фильтры `createdAt` (`from`/`to`, unix-секунды, как у Logger), `mime`, `ownerId` (равенство), `isArchived` → `{items, total}` |
| `file.get` | JSON-вид записи по `id` для деталки (без `storagePath`) |

Бэкенд: `Action/FindFilePageAction.php`, `Action/GetFileAction.php`,
`Dto/Action/FindFilePageInput.php`, `Dto/FilePageQuery.php`,
`Dto/FilePage.php`, `Service/FilePageQueryParser.php`,
`Service/FileHttpService.php`.

Фронтенд: `Page/FileListPage.vue` (`FilterBar` + `SmartGrid`, строка →
деталка), `Composables/useFileList.ts`,
`Constant/Grid/files/columns.ts`, `Constant/Grid/files/filterFields.ts`,
`Component/FileDetailsDialog.vue`, `Service/FileQueryFromGridService.ts`,
`Service/Instance/fileQueryFromGridService.ts`, `Dto/FileQuery.ts`,
`Dto/FilePage.ts`, `routes.ts` с `adminChildren`, импорт в
`router/moduleRoutes.ts`.

Колонки: `id`, `originalName`, `mime`, `size`, `width`, `height`,
`ownerId`, `isArchived`, `createdAt`. `width` и `height` — отдельные
колонки: у `SmartGrid` нет составного типа «1920×1080». `createdAt` в JSON
— unix-секунды, как у Logger (ячейка `datetime` ждёт число). Фильтр
`ownerId` — только равенство: контракт `file.findPage` не принимает
диапазон, поэтому число-фильтр не показывает режимы «≥/≤/интервал».

Деталка: превью через `FileImage`, `publicId`, `ownerId`, `mime`, `size`,
`width`/`height`, `sha256`, ссылка на файл. `storagePath` в JSON и UI не
отдаётся: это раскладка диска, а не контракт. Архивные строки видны и
отличаются флагом; превью для них не запрашивается — байты архивного файла
дают 404.

Фронтенд, `Interface/IFileApi.ts`:

```ts
upload(file: File, signal?: AbortSignal): Promise<FileRecord>;
findPage(query: FileQuery, signal?: AbortSignal): Promise<FilePage>;
get(id: number, signal?: AbortSignal): Promise<FileRecord>;
getViewUrl(publicId: string): string;
```

- `getViewUrl` — метод, а не поле DTO: real строит
  `${engine.getBaseUrl()}/file/${publicId}`, мок — `URL.createObjectURL`.
  Иначе мок-URL протёк бы в контракт.
- Для upload в `Core/Engine` добавляется `HttpClient.postForm()` и фасад
  `Engine.postForm<T>(path, form, signal)` (CSRF, `credentials: include`,
  тот же разбор конверта и `AUTH_REQUIRED`). `FileApi` работает через
  `Engine`, а не через приватный `HttpClient`; прямой `fetch` запрещён —
  второй транспорт.
- `upload(file)` кладёт `File` в `FormData` под полем `file` и шлёт
  `multipart/form-data`; base64 и JSON-тело с байтами не используются.
- `upload` разворачивает конверт: `success === false` → типизированная
  ошибка `FileApiError` с `code`/`message`/`details` (нужна для
  `FILE_TOO_LARGE`, `FILE_MIME_INVALID`, `AUTH_*`), а не голый `Error`.
- `FileRecord` (frontend) — все поля записи в camelCase плюс `publicId` для
  URL; `createdAt` — unix-секунды, `width`/`height` — `number | null`.
- Компоненты: `Component/FileImage.vue` (`<img>` + `loading="lazy"` +
  `decoding="async"` + состояния загрузки/ошибки) и
  `Component/FileUploadField.vue` (выбор, клиентская проверка размера/типа,
  ошибка, повтор). Строгие правила конкретного потребителя задаются его
  пропсами, не модулем.
- `Mock/mockFileApi.ts` — in-memory `Map`, object URL на сгенерированный
  placeholder отзывается при сбросе; `findPage` фильтрует тот же набор.

## 12. Срезы

### F0 — поля-ссылки и уборка

- `Core/SmartTable`: снять `final` с `ReferenceField` и `LinkSetField`,
  docblock, README (пример объявления таблицы), `smarttable.md`.
- `Core/User/Field/UserReferenceField.php`,
  `Core/User/Field/UserLinkSetField.php` (`Core/User` README нет — не
  создаём).
- Замена в 15 таблицах и `GameTable::whitelist` (§4.3), с сохранением
  `required`/`indexed`/`onDelete` (у `Auth` два `cascade`).
- `docs/tr/user.md`: ссылка на учётку объявляется
  `UserReferenceField`/`UserLinkSetField`, а не импортом `UserTable`.
- Тесты: SmartTable (наследники = `reference`/`linkset`, FK, `onDelete`,
  `assertReferenceSettings`/`assertLinkSetSettings`), User, плюс прогон
  `Auth`, `RuleSpace`, `Game`, `Character`, `Chat`. Отдельный тест hop по
  `Game.owner_id.login` фиксирует переход с словаря на class-string.

### F1 — бэкенд `Core/File`

- `module.config.php` (маршруты `file.findPage`, `file.get`), `Container/`,
  `Interface/Container/IFileContainer.php`, `Setup/FileModuleSetup.php`,
  `Schema/FileSchema.php`, `Table/FileTable.php`,
  `Field/FileReferenceField.php`, `README.md` (карта входа модуля).
- `Dto/`: `FileRecord`, `NewFile`, `FileSettings`, `IncomingUpload`,
  `FilePageQuery`, `FilePage`, `Action/FindFilePageInput`.
- `Interface/Service/IFiles.php`, `Interface/Service/IFileStorage.php`,
  `Action/FindFilePageAction.php`, `Action/GetFileAction.php`,
  `Exception/File*`.
- `Repository/FileRepository.php` (`add`, `findByPublicId`, `findById`,
  `findPage`, `markArchived`), `Service/`: `LocalFileStorage`,
  `FileStorageFactory`, `FileUploadService`, `FileReadService`, `FileByteService`,
  `FileByteEmitter`, `FilePageQueryParser`, `FileHttpService`,
  `FileViewAssembler`, `FilePermissionKeys`, `FilePortFactory`.
  `FileUploadService` зависит от `IFileStorage`, поэтому его unit-тесты идут
  на фейковом хранилище, без диска; `FileByteService` проверяется на фейковом
  `IFiles`, `FileReadService` — MySQL-сценарием.
- `API/file-upload.php`, `API/file-get.php`, `www/.htaccess`. Оба
  entrypoint'а берут сервис через `FilePortFactory::fromContainer($locator)`
  (как `LoggerHttpService`), а не собирают зависимости вручную.
- `Kernel`: багаж `files` в `HttpRequest::fromGlobals()` + `getFileMap()` в
  `IHttpRequest`/`HttpRequest` + тест.
- `composer.json` (PSR-4, `exclude-from-classmap`, `autoload-dev`),
  `phpunit.xml.dist` (testsuite `file`; `phpunit-db.xml.dist` уже ловит все
  `*MysqlTest.php` одним suite `db` и не меняется).
- `www/mifrial/install-local.sh` (лимиты PHP через
  `/etc/php/*/apache2/conf.d/`, каталог `var/file` с владельцем Apache),
  `config/local.php.dist` и `config/test.php.dist` (`files`),
  `.gitignore` (`www/mifrial/var/file/`).
- Ручная проверка: `curl` заливает PNG 5–10 МБ, `GET` отдаёт байты,
  повторный `GET` с `If-None-Match` даёт `304`, архивный файл — `404`.

### F2 — фронтенд `Core/File`

- `Core/Engine`: `HttpClient.postForm()` + фасад `Engine.postForm()` + тест.
- `Dto/FileRecord`, `Dto/FilePage`, `Dto/FileQuery`, `Interface/IFileApi`,
  `Service/FileApi`, `Service/Instance/fileApi`, `Dto/FileApiError`,
  `Service/FileQueryFromGridService`, `Service/Instance/fileQueryFromGridService`,
  `Service/FormatFileSize`, `Service/ValidateFileSelection`, `Service/FileImageSource`,
  `Mock/mockFileApi`.
- Страница: `Page/FileListPage.vue`, `Composables/useFileList.ts`,
  `Constant/Grid/files/columns.ts`, `Constant/Grid/files/filterFields.ts`,
  `Component/FileDetailsDialog.vue`, `Component/FileImage.vue`,
  `Component/FileUploadField.vue`.
- Регистрация: `Constant/Permission/FILE_PERMISSION_CATEGORY.ts`,
  `Constant/Permission/FILES_ADMIN_SECTION.ts`,
  `Constant/Navigation/FILES_MENU_ITEM.ts`, `routes.ts` (`adminChildren`),
  импорт в `router/moduleRoutes.ts`, `init.ts`, `main.ts`.
- Тесты: контракт мока (upload/findPage/get), клиентская валидация,
  `postForm`, `fileQueryFromGrid`.
- Ручная проверка: `/admin/files` — таблица, фильтры, деталка, превью и
  повторное открытие без повторного скачивания файла; загрузка проверяется
  в F1 через `curl`.

### F3 — доки и приёмка

- `docs/tr/architecture.md`: два entrypoint'а в «Контракт фундамента
  сервера», ребро `Core/File → SmartTable + User`.
- `docs/tr/data-model.md`: legacy-блок `files()` **не** переписывается (это
  legacy-требование, а не текущая схема); в раздел «Current domain storage
  semantics» добавляется заметка про `file`/`FileTable`; строка 517
  («candidate `files()`») и [`battleground-system.md`](battleground-system.md)
  §169 переводятся на `Core/File`.
- `www/mifrial/tools/doc-index/generate.php`: слаг `'file' => 'Core/File'`.
- `composer maps` / `composer docs-check`.

## 13. Тесты

Бэкенд:

- unit: `FileSettings` (дефолты, лимиты, отсутствие ключа), раскладка пути,
  `public_id`, валидация имени/размера/mime, `FilePageQueryParser`,
  `FileViewAssembler`; `FileUploadService` на фейковом `IFileStorage`
  (без диска); `FileByteService` на фейковом `IFiles` (`403`/`404`/байты);
- MySQL: `FileMysqlTest` — insert/find по `public_id` и `id`, уникальность,
  archive, `findPage`; `FileHttpMysqlTest` — upload под актором, отказ по
  размеру/mime, `IFiles::requireReadable`/`requireOwned` по `publicId`,
  архивная строка читается как метаданные, `file.findPage`/`file.get` без
  `file.view` → `AUTH_DENIED`;
- `FileByteEmitterTest` — заголовки, `ETag`, `304`, `403`/`404` и
  `Cache-Control: no-store` (эмиттер с внедряемым `write`, по образцу
  `SseEmitter`); `FileByteServiceTest` — маппинг отказов порта на статусы
  и отдача активного файла;
- `FileReferenceField` — цель `file`, FK, `onDelete`; `UserLinkSetField` —
  цель `user`, `restrict`/`none`;
- `Kernel` — `HttpRequest` с багажом `files`;
- F0 — наследники `ReferenceField`/`LinkSetField` в SmartTable и hop по
  `owner_id.login`.

Фронтенд:

- мок `IFileApi` соответствует контракту (upload/findPage/get/getViewUrl);
- клиентская валидация размера/типа; `upload` отдаёт `FileApiError` с
  `code`/`details`, а не голый `Error`;
- `HttpClient.postForm` (CSRF-заголовок, конверт, `AUTH_REQUIRED`);
- `FileImage` не делает `fetch` (проверяется по `src` и вызову
  `getViewUrl`) и не просит байты для архивной строки;
- `fileQueryFromGrid` переводит грид-запрос в `FileQuery` (`from`/`to`).

## 14. Гейт

- Итерация: `composer test:unit` + `composer test:module file`
  (+ `smarttable`, `user`, `auth`, `chat`, `game`, `character`, `rulespace`
  для F0); `phpcs`/`php-cs-fixer`.
- Фронт: `npm run format` → `npm run lint` → `npx vitest run <файлы>`.
- `npx vue-tsc --noEmit` перед сдачей.
- Полный прогон `bash tools/test-full.sh` и `npm run test` — после ручной
  проверки, по подтверждению.
- Тестовая MySQL — контейнер `127.0.0.1:3307`; два PHPUnit одновременно не
  запускать.

## 15. Риски

- Лимиты PHP — внешняя зависимость: без поднятого `post_max_size` большая
  подложка не пройдёт, а клиентская проверка покажет «ok». Сервер возвращает
  `FILE_TOO_LARGE` / `FILE_UPLOAD_BROKEN`, фронт показывает это.
- Серверных превью и конвертации нет: модуль хранит и отдаёт исходные байты.
  `fileinfo`/`getimagesize` есть; `gd` включён в PHP 8.2 (Apache и CLI 8.2),
  но в CLI 8.3 по умолчанию отсутствует, а `imagick` нет. Если потребителю
  понадобится уменьшенная копия, это его шаг, не этот план.
- `immutable` экономит трафик, но не память декодирования больших текстур —
  тайлинг остаётся задачей баттлмапа.
- Приватный кеш браузера не разделён по cookie: файл, показанный одному
  аккаунту, может отдаться из кеша при смене аккаунта по тому же URL.
  Для аватарок и подложек это принято; для секретного контента схема не
  подходит.
- Полная замена ссылок на `user` меняет резолв цели в `FieldPathWalker`:
  class-string идёт напрямую в definition, словарь (`ITableCatalog`) больше
  не участвует. Поведение снимается hop-тестом `owner_id.login`, а регресс —
  прогоном `Chat`/`Game`/`Character`.
- `ReferenceField`/`LinkSetField` инстанцируют цель в конструкторе; взаимные
  class-string ссылки двух таблиц дают рекурсию (§4.2). Это ограничение
  держится документацией и тестом сборки карт.

## 16. Связанные документы

- [`architecture.md`](architecture.md) — контракт фундамента сервера,
  entrypoint'ы, рёбра модулей.
- [`smarttable.md`](smarttable.md) — карта таблицы, `reference`, `onDelete`.
- [`data-model.md`](data-model.md) — legacy `files()` (не переписывается) и
  current-заметка про `file`.
- [`user.md`](user.md) — аватар не входит в этот план; `UserReferenceField`
  для ссылок на учётку.
- [`contract-status.md`](contract-status.md) — правила для стабильных
  модулей `Core`.
- [`php-coding-standards.md`](php-coding-standards.md) — Record/New/Patch,
  слои, исключения.
- [`battleground-system.md`](battleground-system.md) — подложка как
  ассет, копия сцены ссылается на тот же файл.
