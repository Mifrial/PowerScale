# Core/File

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/File --zone=tr`.

- **Путь:** `modules/Core/File`
- **Неймспейс:** `Mifrial\Core\File`
- **Контейнер:** `Mifrial\Core\File\Container\FileContainer`
- **Локатор:** `Mifrial\Core\File\Interface\Container\IFileContainer`
- **Setup:** `Mifrial\Core\File\Setup\FileModuleSetup`
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/File/README.md)
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Core\File\Interface\Service\IFiles`
- `Mifrial\Core\File\Service\FileHttpService`
- `Mifrial\Core\File\Action\FindFilePageAction`
- `Mifrial\Core\File\Action\FindOwnImagesAction`
- `Mifrial\Core\File\Action\GetFileAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `file.findPage` | `FindFilePageAction` | да |
| `file.findOwnImages` | `FindOwnImagesAction` | да |
| `file.get` | `GetFileAction` | да |

## Контракты `Interface/`

- `Interface/Container/IFileContainer.php` — Публичный контейнер модуля File.
- `Interface/Service/IFileStorage.php` — Хранение байтов: диск за интерфейсом, чтобы сервисы тестировались без ФС.
- `Interface/Service/IFiles.php` — Публичный порт метаданных файла для соседей.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md), [Core/SmartTable](SmartTable.md), [Core/User](User.md)
- **От него зависят:** [Core/User](User.md), [Roleplay/Map](../Roleplay/Map.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [file-plan-01.md](../../file-plan-01.md) | план | `~СДЕЛАНО~` | — |

