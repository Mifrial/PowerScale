/**
 * Применимость определяется только по признакам предмета (`rule.keywordIds` → коды keywords).
 * Подтип предмета (оружие/щит/доспех) — тоже признак: `weapon`, `shield-item`, `armor-item`.
 * Пустые `keyword_all` + `keyword_any` означают «применимо всему».
 */
export interface ItemModifierApplies {
  /** AND: предмет обязан иметь ВСЕ эти признаки. Пусто — не ограничивает. */
  keyword_all: string[];
  /** OR: предмет обязан иметь хотя бы один. Пусто — не ограничивает. */
  keyword_any: string[];
  /** AND-запрет: предмет НЕ должен иметь ни одного из них. */
  keyword_none: string[];
}
