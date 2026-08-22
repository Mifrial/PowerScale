/**
 * Read-only снимок персонажа для оценки механик. Передаётся по ссылке, одна на проход,
 * без копий; хендлер не мутирует его (immutable build редактора → чистая build()).
 * Поля зеркалят CharacterSnapshot, чтобы редактор мог передать совместимый снимок.
 */
export interface MechanicState {
  /** Уровни способностей по кодам правил. */
  abilityLevels: ReadonlyMap<string, number>;
  /** Признаки (ключевые слова) каждой способности по коду. */
  abilityKeywords: ReadonlyMap<string, ReadonlySet<string>>;
  /** Коды способностей, доступных выбранной расе/виду (для фильтра по расе). */
  racialAbilityCodes: ReadonlySet<string>;
}
