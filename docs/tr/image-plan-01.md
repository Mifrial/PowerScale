# План Image 1 — Core/Image: производные изображения и аватар

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~

**Опираться на:** ~PHP~ (`Kernel`, `File`, `User`), `file-plan-01.md`

> Выбор кружка, источник аватара и показ по сайту — следующий заход,
> [`image-plan-02-avatar-crop.md`](image-plan-02-avatar-crop.md). Он заменяет
> центр-кроп 512×512 на выбранный пользователем квадрат 256×256.

`Core/Image` — тонкая обёртка над Intervention Image: один порт
`IImageTransform`, один адаптер, никаких таблиц, маршрутов, агентов и HTTP.
Первый потребитель — аватар пользователя: сервер сам уменьшает и
перекодирует картинку, клиент присылает только `file.upload`.

План закрывает три вещи: сам модуль, два новых входа `IFiles` для производных
байтов и аватар учётки через `user_avatar`.

## 1. Место и роль

- `Core/Image` — eager-модуль: `ModuleManager::loadCore()` обходит
  `modules/Core` и требует `module.config.php` у каждой папки, поэтому модулю
  нужен хотя бы пустой каркас с контейнером.
- Публичная точка — порт `IImageTransform` из
  `IImageContainer::get(IImageTransform::class)`.
- Модуль ничего не знает про `Core/File`, `Core/User`, аватарки и актора.
  Вход порта — путь к локальному файлу и спецификация; выход — временный файл
  плюс mime и размеры. Куда положить результат и что он значит, решает
  потребитель.
- Зависимость от Intervention Image не выходит за пределы
  `Service/InterventionImageTransform`: домен и потребители видят только
  `IImageTransform`, `ImageTransformSpec`, `ImageResult`.

## 2. Решения

- **Intervention Image v4** (`^4.4`), не v3. Ветка v4 — `main` репозитория
  (первый релиз 2026-03-28, к ноябрю 2026 — 27 релизов), требует `php ^8.3` и
  потому стала доступна только после переезда платформы (`install-local.sh`,
  PHP 8.3.36). v3.11 — maintenance-ветка: патчи есть, развития нет.
  Миграция между ветками изолирована адаптером.
- **Драйвер Imagick.** Стенд уже ставит `php8.3-imagick` из sury
  (`install-local.sh`), в CLI `gd` нет ни в 8.3, ни в 8.4/8.5. Imagick даёт
  авто-ориентацию по EXIF и лучшее ресемплирование. Драйвер выбирается в
  одном месте — `ImagePortFactory::create()`; переход на GD или на другую
  библиотеку не затрагивает порт.
- **Обёртка, а не прямое использование библиотеки.** Потребители не видят
  ни `ImageManager`, ни `ImageInterface`, ни исключений Intervention: смена
  версии библиотеки, её API или замена на другую — правка одного адаптера.
- **Константы вместо настроек.** Пока нет механизма настроек модулей
  (аналог `files` в `local.php` не делаем): пределы модуля — разумные
  константы в коде. Когда появится каталог настроек модулей, они переедут
  туда без смены контракта.
- **File не знает про Image.** `Core/File` оперирует только байтами
  (`copyOwnedTo`, `storeDerived`, `activePublicIds`), `Core/Image` — только
  картинками. Оркестрация — в потребителе (`UserAvatarService`). Обратного
  ребра `File → Image` нет и не будет.
- **Пропорции не искажаются.** `FitMode::Cover` — пропорциональное
  масштабирование под рамку и центральный кроп лишнего; `Contain` — вписывание
  без кропа, свободное место заливается фоном (белый по умолчанию библиотеки),
  canvas результата равен рамке. Потребитель, которому нужен только
  пропорциональный размер без рамки, в текущем контракте не обслуживается —
  это отдельный режим, когда появится реальная задача.

## 3. Границы

Делает:

- читает картинку с диска, проверяет формат и пределы, отдаёт временный файл
  в заданном формате и размере;
- сообщает размеры и mime результата.

Не делает:

- не хранит файлы, не пишет в `file`, не трогает `user_avatar`;
- не удаляет исходники и не убирает за вызывающим временные файлы;
- не решает, какой размер нужен аватару (это `Core/User`);
- не отдаёт HTTP, не проверяет права и актора;
- не конвертирует видео, PDF и прочее — только растровые png/jpeg/webp.

## 4. Контракт

```php
namespace Mifrial\Core\Image\Interface\Service;

interface IImageTransform
{
    /**
     * Строит производную картинку во временном файле.
     *
     * @throws ImageSourceInvalidException Формат вне политики или файл не читается.
     * @throws ImageTransformException Сбой драйвера или записи результата.
     */
    public function transform(string $sourcePath, ImageTransformSpec $spec): ImageResult;
}
```

`ImageTransformSpec` (`Dto/`) — параметры запроса, без дефолтов: размеры,
режим, формат и качество задаёт потребитель.

```php
final class ImageTransformSpec
{
    public function __construct(
        public readonly int $width,          // 1..20000
        public readonly int $height,         // 1..20000
        public readonly FitMode $mode,       // Cover | Contain
        public readonly OutputFormat $format,// Webp | Jpeg | Png
        public readonly int $quality,        // 1..100
    ) {}
}
```

`ImageResult` (`Dto/`) — `getPath()` (временный файл), `getMime()`,
`getWidth()`, `getHeight()`, `getSize()`. Жизненный цикл: файл удаляет
вызывающий в `finally`; модуль за ним не следит.

Исключения — наследники `ImageException` (`KernelException`/`MifrialException`,
без HTTP-кодов действий):

| Исключение | Код | Когда |
|---|---|---|
| `ImageSpecInvalidException` | `IMAGE_SPEC_INVALID` | размеры/качество вне диапазона |
| `ImageSourceInvalidException` | `IMAGE_INVALID` | нет файла, битые байты, формат вне политики |
| `ImageTransformException` | `IMAGE_TRANSFORM` | драйвер не смог прочитать/закодировать, не записался tmp |

Коды не отдаются клиенту напрямую: потребитель (`Core/User`) переводит их в
свой `USER_INVALID` / `FILE_DENIED`.

## 5. Адаптер Intervention

`Service/InterventionImageTransform`:

1. `getimagesize($sourcePath)` — существование файла, читаемость, формат.
   Политика входа: `IMAGETYPE_PNG`, `IMAGETYPE_JPEG`, `IMAGETYPE_WEBP`,
   иначе `IMAGE_INVALID`. Формат берётся из байтов, не из имени файла.
2. Пределы: `width * height ≤ 40 000 000` и сторона `≤ 20 000`
   (`MAX_SOURCE_PIXELS`, `MAX_SOURCE_SIDE` в адаптере). Это защита от
   «бомбы декодирования»: `files.max_size` (32 МиБ) сам по себе не мешает
   картинке 30 000×30 000.
3. `$manager->decode($sourcePath)` → `orient()` → `cover($w, $h)` или
   `contain($w, $h)` → `encode(new WebpEncoder($quality, strip: true))`
   (для jpeg/png — соответствующий encoder). `strip: true` — метаданные
   (EXIF, ICC) в производную не переносим.
4. Результат пишется в `tempnam(sys_get_temp_dir(), 'mifrial-image-')`;
   размеры берутся из закодированного изображения, а не из спецификации
   (contain может дать меньше заданного).
5. Любое исключение Intervention заворачивается в `ImageTransformException`
   с `previous`.

Политика ImageMagick: вход ограничен png/jpeg/webp до вызова драйвера, поэтому
экзотические форматы и их делегаты не открываются. `/etc/ImageMagick-6/policy.xml`
дополнительно не правим, но в §13 это остаётся риском на случай, если политику
входа когда-нибудь расширят.

Драйвер создаётся в `ImagePortFactory::create()`:

```php
return new InterventionImageTransform(new ImageManager(new ImagickDriver()));
```

Замена драйвера — одна строка; замена библиотеки — один класс.

## 6. Каркас модуля

`www/mifrial/modules/Core/Image/`:

```
Container/ImageContainer.php
Interface/Container/IImageContainer.php
Interface/Service/IImageTransform.php
Dto/ImageTransformSpec.php
Dto/ImageResult.php
Enum/FitMode.php            // enum: Cover, Contain
Enum/OutputFormat.php       // enum: Webp, Jpeg, Png
Exception/ImageException.php
Exception/ImageSpecInvalidException.php
Exception/ImageSourceInvalidException.php
Exception/ImageTransformException.php
Service/ImagePortFactory.php
Service/InterventionImageTransform.php
module.config.php
tests/
```

`module.config.php` — по образцу `Core/Event`: `container`, `locator`, `ports`
(`IImageTransform`), пустые `routes`, `events`; ключа `setup` нет — таблиц у
модуля нет.

Подключение:

- `composer.json`: `"php": "^8.3"`, `"ext-imagick": "*"`,
  `"intervention/image": "^4.4"` (+ транзитивный `intervention/gif ^5`);
- PSR-4 `Mifrial\Core\Image\` → `modules/Core/Image/`;
- `phpunit.xml.dist`: suite `image` → `modules/Core/Image/tests`;
- `exclude-from-classmap`: `modules/Core/Image/tests/`.

`install-local.sh` менять не нужно: `php8.3-imagick` уже ставится шагом PHP 8.3.

## 7. Стык с File

`Core/File` получает три метода в `IFiles` (`Interface/Service/IFiles.php`).
Ни один из них не знает про картинки.

```php
/** Копирует байты своей активной строки в $targetPath; возвращает запись. */
public function copyOwnedTo(string $publicId, string $targetPath): FileRecord;

/** Кладёт байты из $sourcePath новой строкой; владелец — текущий актор. */
public function storeDerived(string $sourcePublicId, string $sourcePath, string $originalName): FileRecord;

/** Проекция id → public_id для живых строк; без актора. */
public function activePublicIds(array $fileIds): array;
```

- `copyOwnedTo` требует актора и владельца строки; архивная строка —
  `FILE_DENIED`. Копирование потоковое через `IFileStorage::chunks()`; в
  `IFileStorage` добавляется `exportTo(string $storagePath, string $targetPath)`,
  чтобы `LocalFileStorage` не гонял байты через память.
- `storeDerived` повторяет путь загрузки без multipart: mime/hash/размеры
  читаются с диска, проверяются `files.mime` и `files.max_size`, генерируется
  `public_id`, байты кладутся в шард, вставляется строка с владельцем-актором.
  Исходная строка нужна для проверки владения и для имени по умолчанию (если
  `$originalName` пуст). Исходный файл **переносится** в шард (move), а не
  копируется: вызывающий передаёт одноразовый временный файл.
- Общий код upload и derive вынесен в `FileIntake` (`insert(ownerId,
  sourcePath, originalName): FileRecord`): проверки размера/mime/размеров,
  дедупликация `public_id`, шард, строка и уборка байтов при сбое вставки.
- `activePublicIds` — только проекция: возвращает `[id => public_id]` для
  существующих неархивных строк. Прав не проверяет и актора не требует;
  вызывать можно только с id, полученными из уже авторизованных строк
  (например, `user_avatar.file_id`). Используется в `UserViewAssembler`,
  чтобы список учёток не давал N+1.

Внутренний `id` по-прежнему не выходит наружу: наружу идёт `public_id`.

## 8. Аватар (первый потребитель)

### 8.1 Таблица `user_avatar`

Отдельная таблица, а не колонка `user.avatar_file_id`: `file.owner_id → user`
и `user.avatar_file_id → file` дают цикл FK, на котором
`TableSetupOrder` бросает `SetupException` (проверено).

```
user_avatar
  id         IdField
  user_id    UserReferenceField  required, unique, onDelete cascade
  file_id    IntField            required, indexed, без FK на `file`
  created_at DateTimeField       default: now
```

`file_id` намеренно без физического FK: `Core/User` не должен тянуть схему
`Core/File` в свою установку (иначе каждая установка User требует таблицу
`file`, а тесты — файловый модуль). Строки `file` не удаляются, только
архивируются, поэтому `ON DELETE SET NULL` ничего не охраняет. Если строка
файла исчезнет, проекция просто отдаст `avatar: null`.

`user_id` уникален через настройку поля (`unique => true`), а не составным
ключом: `defineUniqueKeys()` в SmartTable обслуживает только кортежи из 2+
колонок. `UserSchema` получает четвёртую карту `UserAvatarTable`;
`UserModuleSetup` менять не нужно — он читает `UserSchema::getTableClasses()`.

### 8.2 Сервис

`UserAvatarService` (в `Core/User/Service/`) — единственное место, где живут
параметры аватара:

```php
private function avatarSpec(): ImageTransformSpec
{
    return new ImageTransformSpec(512, 512, FitMode::Cover, OutputFormat::Webp, 90);
}
```

`setAvatar(?string $filePublicId): array` (JSON User):

1. `$filePublicId === null` — снять: `old file_id` → `IFiles::archive`,
   ссылка обнуляется (строка `user_avatar` остаётся с `file_id = null` или
   удаляется — §8.4).
2. `IFiles::requireOwned($filePublicId)` — чужой файл → `FILE_DENIED`.
3. Потолки потребителя: mime `image/png|image/jpeg|image/webp`, размер
   ≤ 2 МиБ, сторона ≤ 4096 px, строка не архивная. Нарушение → `USER_INVALID`
   (HTTP 400). Сторона важна: cover из 20 000×20 000 — это память и CPU,
   даже если Image формально справится.
4. `IFiles::copyOwnedTo($filePublicId, $tmp)` → `IImageTransform::transform($tmp, $spec)`
   в `try/finally` с удалением обоих временных файлов.
5. `IFiles::storeDerived($filePublicId, $result->getPath(), 'avatar.webp')`.
6. Старая производная (если была и отличается) — `IFiles::archive`.
   Исходник, загруженный пользователем, **не** трогаем: это его файл.
7. Upsert `user_avatar` (замена ссылки), вернуть JSON User.

Транзакция: строка `file` пишется до upsert `user_avatar`; если upsert упал,
производная строка остаётся «сиротой» (архивная уборка — отдельная задача).
Обратный порядок хуже: ссылка на несуществующий файл ломает проекцию.

### 8.3 HTTP

- Новый маршрут `user.setAvatar`, `csrf: true`, `SetUserAvatarInput`
  (`filePublicId: string|null`), `SetUserAvatarAction`.
- Действие зависит от порта `IUserAvatar`, а не от `UserHttpService`: у
  сценария учётки уже 6 зависимостей, а аватар — отдельный пайплайн
  (файл → Image → File). Порт собирается `UserHttpServiceFactory::createAvatar()`.
- `user.update` аватар не принимает: нормализатор и биндер отвергают
  неизвестный ключ (`INVALID_PARAMS`), поэтому отдельное действие.
- Ошибки: нет/чужой файл — `FILE_DENIED`/`FILE_NOT_FOUND` (как у File),
  не картинка/большой/архивный — `USER_INVALID` (400).

### 8.4 Проекция `avatar`

- `UserViewAssembler::assembleMany()` собирает `user_id → file_id` по
  `UserAvatarRepository` одним запросом, затем `IFiles::activePublicIds()`
  одним запросом; в JSON — `avatar: string|null`.
- `assemble()` (одна учётка) идёт тем же путём через `assembleMany`, поэтому
  `lastLogin` остаётся отдельным ключом.
- Поле добавляется и в ответы Auth (`login`, `register`): они собирают User
  через `IUserViews`, правок в `Core/Auth` не требуется.
- Пустая ссылка (нет строки или `file_id = null`) даёт `avatar: null`;
  JSON-ключ присутствует всегда.
- `user_avatar` с `file_id = null` при снятии удаляется (строки-пустышки не
  копим); при замене — обновляется существующая строка.

## 9. Фронтенд

- `User` DTO: `avatar?: string | null` (как `deactivatedUntil`: сервер шлёт
  всегда, тип опционален ради старых фикстур); `setUserAvatar` в API и моке;
  `store.updateUser` обновляет `currentUser`, чтобы шапка и профиль
  перерисовались без перезагрузки.
- `useAvatarUpload` (`Core/User/Composables/`): проверка типа и размера
  (те же 2 МиБ, чтобы не гонять заведомо отвергаемый файл), `file.upload`,
  затем `user.setAvatar`. Canvas-downscale не нужен: сервер ресайзит.
- `UserAvatarField.vue` (`Core/User/Component/`): превью через существующий
  `FileImage` (динамический импорт — публичная точка чужого модуля), кнопки
  «загрузить» и «снять», состояния загрузки и ошибки. `FileImage` получил
  режим `cover`, чтобы круглый аватар не растягивался.
- Места: страница профиля, `/users/:id/edit` (только своя учётка: сервер
  ставит аватар актору) и `UserProfileSlider`.
- Валидация файла до загрузки переиспользует `validateFileSelection` из
  `Core/File`: функция отдана публично через `Core/File/init.ts`, потому что
  `Service/` чужого модуля импортировать нельзя.
- Ошибки сервера показываются как есть (`FILE_DENIED`, `USER_INVALID`),
  клиент не подменяет их своей валидацией.

## 10. Срезы

- **I1 — модуль Image.** Каркас, контракт, адаптер, `composer`-зависимости,
  suite `image`, тесты адаптера.
- **I2 — File.** `IFileStorage::exportTo`, `IFiles::{copyOwnedTo, storeDerived,
  activePublicIds}`, `FileIntake` (общий путь upload/derive),
  `FileRepository::activePublicIds`, тесты (unit + MySQL).
- **I3 — User/аватар.** `UserAvatarTable`, `UserAvatarRepository`,
  `UserAvatarService`, `SetUserAvatarInput/Action`, маршрут, `avatar` в
  `UserViewAssembler`, `UserSchema`, MySQL-тест.
- **I4 — фронт.** DTO, API/mock, композабл, компонент, профиль и edit-страница,
  vitest.
- **I5 — доки.** `architecture.md` (модуль Image, рёбра `User → Image`,
  `User → File`), `user.md`, `data-model.md`, `file-plan-01.md` (§11 контракт,
  §15 риск), карточка модуля, `composer maps`.

## 11. Тесты

Image:

- cover: 64×48 png → 64×64 webp, кроп по центру, mime `image/webp`;
- contain: та же картинка → 64×64 с белым фоном сверху и снизу, содержимое
  не растянуто;
- jpeg-источник с EXIF-ориентацией (готовится Imagick в тесте) → после
  `orient()` ширина и высота меняются местами;
- битые байты → `IMAGE_INVALID`; `gif` → `IMAGE_INVALID`;
- спецификация вне диапазона → `IMAGE_SPEC_INVALID`;
- отсутствующий файл → `IMAGE_INVALID`; временный файл результата удаляется
  в тесте.

File:

- `copyOwnedTo`: своя строка копируется, чужая → `FILE_DENIED`, архивная →
  `FILE_DENIED`;
- `storeDerived`: mime и размеры читаются с диска, политика `files.mime`
  соблюдается, владелец — актор, `public_id` уникален, строка читается;
- `activePublicIds`: живые id → `public_id`, архивные и несуществующие
  отсутствуют, актор не нужен.

User:

- `setAvatar` создаёт строку и ссылку; повторный вызов заменяет ссылку,
  старый производный файл архивный;
- чужой файл → `FILE_DENIED`; не картинка / >2 МиБ / сторона >4096 → `USER_INVALID`;
- `null` снимает аватар;
- `assembleMany` для трёх учёток делает один запрос `activePublicIds` и
  отдаёт `avatar` без N+1 (шпион на `IFiles`).

Фронт: `useAvatarUpload` (валидация, отказ, успешный путь), `UserAvatarField`
(превью, снятие), store обновляет `currentUser`.

## 12. Гейт

- `composer require intervention/image:^4.4` (лок поднимает `php ^8.3`,
  `ext-imagick`, `intervention/gif ^5`);
- `composer stan`, `composer test:unit`, `composer test:module image`,
  `composer test:module file`, `composer test:module user`;
- MySQL-тесты File/User — по одному, общая `powerscale_test`;
- `npx vitest run` по затронутым файлам;
- `composer maps` / `composer maps-check` после правок доков;
- полный прогон (`bash tools/test-full.sh`) — после ручной проверки и с
  разрешения пользователя.

Ручная проверка: загрузить png 2000×1500 в профиль → аватар 512×512 webp в
списке и шапке; заменить → старый архивный; снять → `null`; попробовать
подсунуть чужой `public_id` → `FILE_DENIED`.

## 13. Риски

- **`ext-imagick` — жёсткое требование.** Без расширения падают
  `composer install` и `bin/setup.php`. Ставит `install-local.sh`; на новом
  стенде это обязательный шаг, иначе модуль не поднимется.
- **Делегаты ImageMagick** — известная поверхность атаки на пользовательских
  картинках. Закрыто политикой входа (png/jpeg/webp) до драйвера; расширение
  политики требует правки `policy.xml` и отдельного решения.
- **Память.** `files.max_size` не ограничивает пиксели; 40M — компромисс,
  при `memory_limit` 256M большая картинка может упереться в память раньше.
  Потребитель дополнительно режет сторону до 4096.
- **v4 моложе v3.** Используются четыре вызова (`decode`, `orient`, `cover`/
  `contain`, `encode`), они покрыты тестами на реальных файлах; откат на v3 —
  правка адаптера.
- **Сироты производных.** Замена и снятие аватара архивируют старую строку, но
  байты не удаляются; уборка архивных файлов — отдельная задача (как и в
  `file-plan-01`).
- **Двойная работа при гонке.** Два параллельных `setAvatar` могут создать две
  производные строки; выигрывает последний upsert, проигравшая остаётся
  архивной. Отдельная блокировка не нужна: гонка редкая и безопасная.
- **Формат webp.** Все современные браузеры поддерживают; если появится
  требование jpeg-фолбэка, это одна строка в `avatarSpec()`.

## 14. Связанные документы

- [`file-plan-01.md`](file-plan-01.md) — `Core/File`, `IFiles`, политика
  `files.mime` / `files.max_size`, байты на диске.
- [`user.md`](user.md) — учётка и профиль; аватар как поле User.
- [`data-model.md`](data-model.md) — таблицы `user`, `user_avatar`, `file`.
- [`architecture.md`](architecture.md) — состав модулей и рёбра зависимостей.
- [`php-coding-standards.md`](php-coding-standards.md) — стиль, исключения,
  контракты Record/JSON.
