/**
 * Создание/редактирование записи добычи ведущим (запас, prepared). Ровно один из
 * `itemRuleCode` (предмет) или `moneyAmount` (деньги) должен быть задан.
 */
export interface CreateLootData {
  group: string | null;
  itemRuleCode: string | null;
  quantity: number;
  moneyAmount: number | null;
  notes: string | null;
}
