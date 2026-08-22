/**
 * Предмет в инвентаре персонажа. `ruleId` — предмет из правил; **null — кастомный
 * «предмет мастера»** (создан ведущим на ходу): имя/описание задаёт мастер, правила нет.
 * При миграции персонажа на новую ревизию предметы с удалённым правилом превращаются
 * в кастомные (сохраняя старое имя/описание правила).
 */
export interface InventoryItem {
  id: number;
  ruleId: string | null;
  quantity: number;
  equipped: boolean;
  durabilityLeft?: number | null;
  note?: string | null;
  /** Имя кастомного предмета (ruleId null); для rule-предмета берётся имя правила. */
  name?: string | null;
  /** Описание кастомного предмета (ruleId null). */
  description?: string | null;
  /**
   * Применённые модификаторы предмета (id правил type item_modifier).
   * Пусто/отсутствует — немодифицированная копия. Identity строки = (ruleId, sorted ids).
   */
  modifierRuleIds?: string[];
}
