# Messages/Notes

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Messages/Notes --zone=tr`.

- **Путь:** `modules/Messages/Notes`
- **Неймспейс:** `Mifrial\Messages\Notes`
- **Контейнер:** `Mifrial\Messages\Notes\Container\NotesContainer`
- **Локатор:** `Mifrial\Messages\Notes\Interface\Container\INotesContainer`
- **Setup:** `Mifrial\Messages\Notes\Setup\NotesModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Messages\Notes\Interface\Service\INotes`
- `Mifrial\Messages\Notes\Service\NoteHttpService`
- `Mifrial\Messages\Notes\Action\FindNotePageAction`
- `Mifrial\Messages\Notes\Action\FindNoteHostsAction`
- `Mifrial\Messages\Notes\Action\GetNoteAction`
- `Mifrial\Messages\Notes\Action\CreateNoteAction`
- `Mifrial\Messages\Notes\Action\UpdateNoteAction`
- `Mifrial\Messages\Notes\Action\DeleteNoteAction`
- `Mifrial\Messages\Notes\Action\ReorderNotesAction`
- `Mifrial\Messages\Notes\Action\PinNoteAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `note.findPage` | `FindNotePageAction` | да |
| `note.findHosts` | `FindNoteHostsAction` | да |
| `note.get` | `GetNoteAction` | да |
| `note.create` | `CreateNoteAction` | да |
| `note.update` | `UpdateNoteAction` | да |
| `note.delete` | `DeleteNoteAction` | да |
| `note.reorder` | `ReorderNotesAction` | да |
| `note.pin` | `PinNoteAction` | да |

## Контракты `Interface/`

- `Interface/Container/INotesContainer.php` — Публичный контейнер модуля Notes.
- `Interface/Service/INotes.php` — Фасад личных заметок: без схемы и без HTTP.

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [notes-plan-01-inline-extract.md](../../notes-plan-01-inline-extract.md) | план | `~СДЕЛАНО~` | 2026-11-08 |
| [notes-plan-02-storage-http.md](../../notes-plan-02-storage-http.md) | план | `~СДЕЛАНО~` | 2026-11-08 |
| [notes-plan-03-vue.md](../../notes-plan-03-vue.md) | план | `~СДЕЛАНО~` | 2026-11-08 |
| [notes-plan-04-character.md](../../notes-plan-04-character.md) | план | `~СДЕЛАНО~` | 2026-11-08 |
| [notes-roadmap.md](../../notes-roadmap.md) | план | `~СДЕЛАНО~` | 2026-11-08 |

