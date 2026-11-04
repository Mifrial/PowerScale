# Core\File

Карта входа: как загрузить файл, получить его метаданные и отдать байты.
Пути даны от корня репозитория.

**Роль:** хранение файла — байты на диске, метаданные строкой в SmartTable,
доступ к байтам отдельным HTTP-entrypoint'ом. Модуль не решает, что файл
значит (аватарка, подложка), и не хранит превью. План и решения —
[`file-plan-01.md`](../../../../../docs/tr/file-plan-01.md).

**Сверено по коду:** 2026-11-04.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `IFiles` | `Interface/Service/IFiles.php` | Порт соседа: `requireReadable`, `requireOwned`, `archive` по `publicId` |
| `FileReadService` | `Service/FileReadService.php` | Реализация `IFiles` (актор, владелец, архив) |
| `IFileStorage` / `LocalFileStorage` | `Interface/Service/IFileStorage.php`, `Service/LocalFileStorage.php` | Диск: `hash`, `mimeType`, `imageSize`, `store`, `chunks`, `delete` |
| `FileUploadService` | `Service/FileUploadService.php` | Проверка multipart, путь, строка, JSON-вид |
| `FileByteEmitter` / `FileByteService` | `Service/FileByteEmitter.php`, `Service/FileByteService.php` | Заголовки, ETag, 304, ошибки и байты |
| `FileHttpService` | `Service/FileHttpService.php` | `findPage`, `get`, `upload`, `sendBytes` |
| `FilePortFactory` | `Service/FilePortFactory.php` | Сборка порта, HTTP-сервиса и сервисов |
| `FilePermissionKeys` | `Service/FilePermissionKeys.php` | `VIEW = 'file.view'` |
| `FileRepository` | `Repository/FileRepository.php` | `add`, `findByPublicId`, `getById`, `findPage`, `markArchived` |
| `FileTable` | `Table/FileTable.php` | Карта `file`: `id`, `public_id`, `owner_id`, `original_name`, `mime`, `size`, `sha256`, `storage_path`, `width`, `height`, `is_archived`, `archived_at`, `created_at` |
| `FileReferenceField` | `Field/FileReferenceField.php` | `reference` на `FileTable` для потребителей |
| `FileSchema` / `FileModuleSetup` | `Schema/FileSchema.php`, `Setup/FileModuleSetup.php` | Накат таблицы; data-шагов нет |
| `IFileContainer` / `FileContainer` | `Interface/Container/IFileContainer.php`, `Container/FileContainer.php` | Локатор модуля |
| `FindFilePageAction` / `GetFileAction` | `Action/*.php` | Маршруты `file.findPage`, `file.get` (оба `csrf: true`) |
| `FileRecord` / `NewFile` / `FileSettings` / `IncomingUpload` / `FilePage` / `FilePageQuery` | `Dto/*.php` | Домен, вход и страница |
| `File*Exception` | `Exception/*.php` | `FILE_*`; дисковый сбой — `FileStorageException` |

## HTTP

- `POST /api/file/upload` → `API/file-upload.php`, тело `multipart/form-data`,
  поле `file`; CSRF обязателен; ответ — обычный JSON-конверт `ActionResponse`.
- `GET /api/file/{publicId}` → `API/file-get.php`; байты или голый статус
  (`403` нет актора, `404` нет строки или архив), `Cache-Control`,
  `ETag`/`304`.

## Как пользоваться

### Прикрепить файл (сосед)

```php
/** @var IFiles $files */
$files = $serviceLocator->get(IFileContainer::class)->get(IFiles::class);
$fileRecord = $files->requireOwned($publicIdFromClient);  // чужой → FILE_DENIED
$files->archive($publicId);                               // только владелец
```

`requireReadable` пускает любого аутентифицированного актора, знающего
`publicId` (capability URL). Архивная строка читается как метаданные; байты
по ней дают `404`.

### Загрузить

```php
$http = $serviceLocator->get(IFileContainer::class)->get(FileHttpService::class);
$view = $http->upload(IncomingUpload::fromRequest($httpRequest));
```

`size` сверяется с `files.max_size`, `mime` — с `files.mime` через `finfo`,
картинки дополнительно проверяются `getimagesize` и пределом 20000 px.

### Админка

`file.findPage` (`limit` 1..100, `offset ≥ 0`, фильтры `mime`, `ownerId`,
`isArchived`, `from`/`to`) и `file.get` требуют `file.view`. JSON-вид:
`id`, `publicId`, `ownerId`, `originalName`, `mime`, `size`, `width`,
`height`, `sha256`, `isArchived`, `archivedAt`, `createdAt`. `storagePath`
наружу не отдаётся.

## Инварианты

- Таблица только через SmartTable; SQL/PDO в модуле нет.
- `public_id` — 32 hex, единственный внешний идентификатор; `id` — внутренний.
- Путь `ab/cd/<public_id>` по первому и последнему байту `sha256`, без
  расширения.
- Байты и URL неизменяемы: новая загрузка — новая строка и новый `public_id`.
- `immutable`-кеш отдаётся только аутентифицированному актору; проектного
  запрета на смену аккаунта по тому же URL нет (см. риски плана).
- Ошибки загрузки — наследники `ActionException`, дисковые — `MifrialException`.
- Байты принимаются в PHP-tmp, проверяются на месте и переносятся в шард
  (`rename`, при другой ФС — `copy` + `unlink`); отдельного `var/file/tmp` нет.
- На dev-стенде каталог `var/file` создаёт `install-local.sh` (root) с
  владельцем пользователя Apache; без этого upload отдаёт `FILE_STORAGE`.

## Зависимости

- Импортирует: `Core/Kernel`, `Core/SmartTable`, `Core/User`
  (`UserReferenceField`, `IUserAccess`).
- Импортируется: потребители берут `IFiles` из `IFileContainer`; прямых
  импортов репозитория/таблицы у соседей нет.

## Тесты

- `tests/FileSettingsTest.php` — дефолты и валидация среза `files`.
- `tests/LocalFileStorageTest.php` — шард, хеш, mime, размеры, store/chunks,
  запрет `..`.
- `tests/FileUploadServiceTest.php` — отказы размера/mime/картинки/имени на
  фейковом хранилище.
- `tests/FileByteEmitterTest.php` — заголовки, ETag, `304`, `no-store`.
- `tests/FileByteServiceTest.php` — `403`/`404`, байты активного файла.
- `tests/FilePageQueryParserTest.php` — границы и фильтры.
- `tests/FileViewAssemblerTest.php` — JSON без `storagePath`.
- `tests/FileReferenceFieldTest.php` — цель `file`, `onDelete`.
- `tests/FileMysqlTest.php` — insert/find/unique/archive/findPage (MySQL).
- `tests/FileHttpMysqlTest.php` — `file.view`, upload, `IFiles`, архивные байты
  `404` (MySQL).

## Не описано / OPEN

- Нет серверных превью и конвертации; хранятся исходные байты.
- Нет личного экрана «Мои файлы»: только админская страница.
- Нет отдельной таблицы прав на файл: доступ — аутентификация + знание
  `publicId`.
- SVG и другой `image/*`, который не читает `getimagesize`, не принимается
  даже после добавления в `files.mime`. Не-картинки отдаются `inline`:
  расширять политику типов нужно осознанно.
