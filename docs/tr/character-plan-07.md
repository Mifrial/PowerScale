# План Character 7 — чтение, видимость, заметки (C6)

**Статус:** PHP сделан. Нарезка — [`character-roadmap.md`](character-roadmap.md). Контракт — [`character-plan-01.md`](character-plan-01.md) §6–7. Хранение — [`character-plan-02.md`](character-plan-02.md). Save — [`character-plan-06.md`](character-plan-06.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: публичные list и detail, отдельные операции заметок и видимости. Create, update, validate, снимок и spec не переписываются. Миграция ревизии, Game и runtime вне create не входят. `customRules` остаются во входе save.

## Зафиксировано

- Секции — enum из восьми кодов. Хранение — `visibility_fields` и `character_viewer.fields`, не массив аудиторий. Клиент `is_public` не шлёт. `is_public` истинен, когда после нормализации список «всем» непуст.
- Владелец видит свой лист без `character.view`. Чужой — ключ `character.view` и хотя бы одна секция во «всем» или в своей строке зрителя. Иначе `CHARACTER_NOT_FOUND`.
- List: `owner_id = я`, либо при ключе ещё `is_public` или id зрителя. JSON в WHERE нет.
- GET чужому — объединение секций и только их ключи `choices` и `sheet`. Имя видно, если лист виден. Заметки, зрители, `actualVersion`, `limits`, `customRules` и `ageYears` чужому не отдаются.
- Заметки и видимость не входят в choices/sheet, не зовут валидатор и не меняют `actual_version`.
- Три слоя: `CharacterRead` собирает JSON, `CharacterSheetAccess` решает право и единственный вызывает `CharacterVisibilityRepository`. `ICharacters` не расширяется.

## Тест

Suite `character`. Маска и коды без MySQL. Доступ — MySQL: владелец, NotFound, list, объединение секций, снятие зрителя, `actual_version` на месте.
